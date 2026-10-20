#!/usr/bin/python3
"""Escritor sintetico accesible solo en el Apache privado del ensayo."""
import os
from pathlib import Path

counter = Path('/run/vigilante-maintenance-check/counter')
if os.environ.get('REQUEST_METHOD') == 'POST':
    counter.write_text(str(int(counter.read_text()) + 1))
print('Content-Type: text/plain\r\n\r\nlectura de ensayo')
