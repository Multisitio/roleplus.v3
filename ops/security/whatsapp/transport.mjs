import makeWASocket, { Browsers, DisconnectReason, fetchLatestBaileysVersion, useMultiFileAuthState, jidNormalizedUser } from '@whiskeysockets/baileys';
import pino from 'pino';
import QRCode from 'qrcode';
import { createServer } from 'node:net';
import { readFile, writeFile, rename, unlink, mkdir, chmod } from 'node:fs/promises';
import { randomUUID } from 'node:crypto';

process.umask(0o077);
const stateDir = '/var/lib/vigilante-wa';
const socketPath = '/run/vigilante-wa/control.sock';
const logger = pino({ level: 'silent' });
const config = JSON.parse(await readFile('/etc/vigilante-wa/recipient.json', 'utf8'));
if (Object.keys(config).length !== 1 || !/^\d{10,15}$/.test(config.phone)) throw new Error('Recipient configuration invalid');
let connection;
let connected = false;
let stopping = false;
let timer;
let delay = 5000;
let lastTest = 0;
let lastPairing = 0;
let busy = false;
let qrChain = Promise.resolve();
let status = { version: 1, state: 'starting', connected: false, linked: false, test: null };

async function atomic(path, content) {
  const temporary = path + '.' + randomUUID();
  await writeFile(temporary, content, { mode: 0o600, flag: 'wx' });
  await rename(temporary, path);
}

async function publish() {
  status.connected = connected;
  status.updated_at = Math.floor(Date.now() / 1000);
  await atomic(stateDir + '/status.json', JSON.stringify(status));
}

async function clearQr() {
  await unlink(stateDir + '/pairing.png').catch(error => { if (error.code !== 'ENOENT') throw error; });
  delete status.qr_created_at;
}

async function start() {
  if (stopping) return;
  const { state, saveCreds } = await useMultiFileAuthState(stateDir + '/session');
  status.linked = Boolean(state.creds.registered);
  const { version } = await fetchLatestBaileysVersion();
  const sock = makeWASocket({
    version,
    auth: state,
    logger,
    browser: Browsers.ubuntu('Chrome'),
    markOnlineOnConnect: false,
    syncFullHistory: false,
    shouldSyncHistoryMessage: () => false,
    emitOwnEvents: false,
    generateHighQualityLinkPreview: false,
    getMessage: async () => undefined,
    connectTimeoutMs: 30000,
    defaultQueryTimeoutMs: 30000,
    qrTimeout: 45000,
  });
  connection = sock;
  for (const event of ['CB:failure', 'CB:stream:error']) {
    sock.ws.on(event, node => {
      const code = Number(node?.attrs?.reason || node?.attrs?.code);
      if (Number.isInteger(code) && code > 0 && code < 1000) {
        status.last_protocol_error_code = code;
        publish().catch(fail);
      }
    });
  }
  sock.ev.on('creds.update', () => saveCreds().catch(fail));
  sock.ev.on('connection.update', update => {
    if (connection !== sock || stopping) return;
    qrChain = qrChain.then(async () => {
      if (update.qr) {
        const png = await QRCode.toBuffer(update.qr, { type: 'png', width: 640, margin: 4, errorCorrectionLevel: 'M' });
        await atomic(stateDir + '/pairing.png', png);
        status.state = 'awaiting_qr';
        status.qr_created_at = Math.floor(Date.now() / 1000);
        await publish();
      }
      if (update.connection === 'open') {
        if (jidNormalizedUser(sock.user?.id || '') !== config.phone + '@s.whatsapp.net') {
          status.state = 'identity_mismatch';
          await publish();
          stopping = true;
          sock.end(new Error('Identity mismatch'));
          process.exit(1);
        }
        connected = true;
        delay = 5000;
        status.state = 'connected';
        status.linked = true;
        await clearQr();
        await publish();
      }
      if (update.connection === 'close') {
        connected = false;
        const code = update.lastDisconnect?.error?.output?.statusCode;
        if (Number.isInteger(code)) status.last_disconnect_code = code;
        await clearQr();
        status.state = code === DisconnectReason.loggedOut ? 'needs_link' : 'reconnecting';
        await publish();
        if (code === DisconnectReason.loggedOut) {
          stopping = true;
          server.close();
          process.exitCode = 0;
          setTimeout(() => process.exit(0), 1000).unref();
          return;
        }
        clearTimeout(timer);
        timer = setTimeout(() => start().catch(fail), code === DisconnectReason.restartRequired ? 1000 : delay);
        delay = Math.min(delay * 2, 300000);
      }
    }).catch(fail);
  });
  // Sin manejador de mensajes entrantes: ningun chat se interpreta como orden.
  // Solo se conserva el recibo del mensaje de prueba, sin cuerpos ni remitentes.
  sock.ev.on('message-receipt.update', updates => {
    for (const update of updates) {
      if (status.test && update.key?.id === status.test.message_id &&
          (update.receipt?.receiptTimestamp || update.receipt?.readTimestamp)) {
        status.test.receipt_observed = true;
        publish().catch(fail);
      }
    }
  });
  await publish();
}

async function execute(request) {
  if (!request || Object.keys(request).length !== 1 || !['status', 'send-test', 'pair-code'].includes(request.action)) {
    return { ok: false, reason: 'operation_not_allowed' };
  }
  if (request.action === 'status') return { ok: true, ...status };
  if (request.action === 'pair-code') {
    if (status.linked || connected || status.state !== 'awaiting_qr' || Date.now() - lastPairing < 60000) {
      return { ok: false, reason: 'pairing_not_available' };
    }
    lastPairing = Date.now();
    status.pairing_requested_at = Math.floor(Date.now() / 1000);
    await publish();
    try {
      const code = await connection.requestPairingCode(config.phone);
      return { ok: true, pairing_code: code };
    } catch {
      return { ok: false, reason: 'pairing_failed' };
    }
  }
  if (!connected || !connection?.user?.id) return { ok: false, reason: 'not_connected' };
  if (busy || Date.now() - lastTest < 60000) return { ok: false, reason: 'test_already_requested' };
  busy = true;
  lastTest = Date.now();
  const reference = randomUUID().slice(0, 8);
  try {
    // Destino fijado a la identidad vinculada, nunca recibido de una peticion.
    const recipient = jidNormalizedUser(connection.user.id);
    if (recipient !== config.phone + '@s.whatsapp.net') throw new Error('Identity mismatch');
    const message = await connection.sendMessage(recipient, {
      text: 'Vigilante del VPS: prueba de conexion por WhatsApp. Sin cambios en las webs. Referencia ' + reference + '.',
    });
    if (!message?.key?.id) throw new Error('Missing message id');
    status.test = { reference, message_id: message.key.id, accepted_at: Math.floor(Date.now() / 1000), receipt_observed: false };
    await publish();
    return { ok: true, accepted: true, receipt_observed: false, reference };
  } catch {
    return { ok: false, reason: 'send_failed_or_uncertain' };
  } finally {
    busy = false;
  }
}

function fail() {
  // No publicar excepciones de la biblioteca: pueden contener claves o mensajes.
  console.error('whatsapp_transport_error');
  process.exit(1);
}

await mkdir(stateDir + '/session', { recursive: true, mode: 0o700 });
await clearQr();
await unlink(socketPath).catch(error => { if (error.code !== 'ENOENT') throw error; });
const server = createServer(client => {
  let buffer = '';
  let processed = false;
  client.setTimeout(5000, () => client.destroy());
  client.on('error', () => {});
  client.on('data', chunk => {
    if (processed) return;
    buffer += chunk.toString('utf8');
    if (buffer.length > 256) return client.destroy();
    if (!buffer.includes('\n')) return;
    processed = true;
    client.setTimeout(35000, () => client.destroy());
    let request;
    try { request = JSON.parse(buffer); } catch { client.end('{"ok":false}\n'); return; }
    execute(request).then(result => client.end(JSON.stringify(result) + '\n')).catch(() => client.end('{"ok":false}\n'));
  });
});
server.maxConnections = 2;
await new Promise((resolve, reject) => { server.once('error', reject); server.listen(socketPath, resolve); });
await chmod(socketPath, 0o600);
process.on('SIGTERM', () => {
  stopping = true;
  clearTimeout(timer);
  connection?.end(new Error('Service stopped'));
  server.close();
  clearQr().finally(() => process.exit(0));
});
process.on('uncaughtException', fail);
process.on('unhandledRejection', fail);
await start();
