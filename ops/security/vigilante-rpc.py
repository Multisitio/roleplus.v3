"""Transporte acotado para el protocolo local de Codex; no registra mensajes."""
import json
import os
import selectors
import time


class Rejected(RuntimeError):
    def __init__(self, method, code):
        super().__init__('Consulta rechazada')
        self.method = method
        self.code = code if type(code) is int else None


class Rpc:
    def __init__(self, process, seconds=90):
        self.process = process
        self.selector = selectors.DefaultSelector()
        self.selector.register(process.stdout, selectors.EVENT_READ)
        self.buffer = b''
        self.total = 0
        self.deadline = time.monotonic()+seconds
        self.current_method = None

    def send(self, message):
        self.process.stdin.write((json.dumps(message)+'\n').encode())
        self.process.stdin.flush()

    def read(self):
        while time.monotonic() < self.deadline:
            if b'\n' in self.buffer:
                line, self.buffer = self.buffer.split(b'\n', 1)
                if not line:
                    continue
                message = json.loads(line)
                if not isinstance(message, dict):
                    raise ValueError('Mensaje no valido')
                return message
            if not self.selector.select(min(1, max(0, self.deadline-time.monotonic()))):
                continue
            chunk = os.read(self.process.stdout.fileno(), 65536)
            if not chunk:
                raise RuntimeError('Cliente cerrado antes de responder')
            self.buffer += chunk; self.total += len(chunk)
            if self.total > 2*1024*1024 or len(self.buffer) > 1024*1024:
                raise ValueError('Respuesta excesiva')
        raise TimeoutError('Tiempo de consulta agotado')

    def request(self, method, request_id, params=None):
        self.current_method = method
        message = {'id': request_id, 'method': method}
        if params is not None:
            message['params'] = params
        self.send(message)
        while True:
            message = self.read()
            if 'method' in message and 'id' in message:
                raise PermissionError('Operacion del servidor no autorizada')
            if type(message.get('id')) is int and message['id'] == request_id:
                if 'error' in message or 'result' not in message:
                    error = message.get('error')
                    raise Rejected(method, error.get('code') if isinstance(error, dict) else None)
                return message['result']

    def close(self):
        self.selector.close()
