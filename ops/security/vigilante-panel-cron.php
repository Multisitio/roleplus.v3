<?php
/** Conserva el propietario y horario de ISPConfig; captura solo contenido textual. */
class cronjob_sysbackup extends cronjob {
    protected $_schedule = '0 3 * * *';
    protected $_max_runtime = 0;

    public function onRunJob() {
        global $app, $conf;
        if (intval($conf['server_id']) !== 1) {
            throw new RuntimeException('Servidor no revisado para copias textuales');
        }
        $app->load('monitor_tools');
        $tools = new monitor_tools();
        $record = $app->db->queryOneRecord('SELECT * FROM server WHERE server_id = ?', 1);
        $config = $app->ini_parser->parse_ini_string(stripslashes($record['config']), 'server');
        if (intval($config['server']['sysbackup_copies']) !== 3 || $config['server']['backup_dir_is_mount'] === 'y') {
            throw new RuntimeException('Politica de copias del panel cambiada; requiere revision');
        }
        $started = time();
        $output = array(); $exit = 1;
        exec('/usr/bin/timeout --kill-after=3s 190s /usr/bin/systemctl start vigilante-panel-backups.service 2>&1', $output, $exit);
        $status = json_decode(file_get_contents('/var/lib/vigilante-panel-backups/status.json'), true);
        $ok = $exit === 0 && is_array($status) && !empty($status['ok'])
            && intval($status['last_success']) >= $started - 86400;
        $stats = $ok ? $status['stats'] : array();
        $app->dbmaster->query('INSERT INTO monitor_data (server_id,type,created,data,state) VALUES (?,?,?,?,?)',
            1, 'sysbackup', time(), serialize($stats), $ok ? 'ok' : 'error');
        $tools->delOldRecords('sysbackup', 1);
        if (!$ok) {
            $app->log('Copia textual del panel fallida; revisar vigilante-panel-backups.service', LOGLEVEL_WARN);
            throw new RuntimeException('La copia textual del panel no esta confirmada');
        }
        parent::onRunJob();
    }
}
