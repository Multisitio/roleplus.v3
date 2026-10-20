"""Comprime SQL textual por bloques, despues de liberar sus bloqueos."""
import gzip
import hashlib
import importlib.util
from pathlib import Path
import os
import re

spec = importlib.util.spec_from_file_location('sqltext', Path(__file__).with_name('vigilante-sql-text.py'))
sqltext = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sqltext)
MAX_SQL = 1024 ** 3
MAX_LINE = 16 * 1024 ** 2


def digest(path):
    h = hashlib.sha256()
    with path.open('rb') as stream:
        while chunk := stream.read(1024 * 1024):
            h.update(chunk)
    return h.hexdigest()


def compress(source, target):
    original, encoded = hashlib.sha256(), hashlib.sha256()
    size = encoded_size = controls = 0
    header = bytearray()
    with source.open('rb') as src, target.open('xb') as dst:
        os.fchmod(dst.fileno(), 0o600)
        with gzip.GzipFile(fileobj=dst, mode='wb', compresslevel=1) as zipped:
            while line := src.readline(MAX_LINE + 1):
                if len(line) > MAX_LINE:
                    raise ValueError('Linea SQL fuera de presupuesto')
                size += len(line)
                if size > MAX_SQL:
                    raise ValueError('Volcado fuera de presupuesto')
                if len(header) < 8192:
                    header.extend(line[:8192 - len(header)])
                mode = b"SQL_MODE='NO_AUTO_VALUE_ON_ZERO'" in header
                converted, count = sqltext.encode_controls(line, verified_dump_mode=mode)
                if re.search(r'[\x00-\x08\x0b\x0e-\x1f\x7f-\x9f]', converted.decode('utf-8-sig')):
                    raise ValueError('Contenido no textual en SQL')
                original.update(line)
                encoded.update(converted)
                encoded_size += len(converted)
                if encoded_size > MAX_SQL:
                    raise ValueError('SQL textual fuera de presupuesto')
                controls += count
                zipped.write(converted)
        dst.flush()
        os.fsync(dst.fileno())
    verified = hashlib.sha256()
    with gzip.open(target, 'rb') as stream:
        while chunk := stream.read(1024 * 1024):
            verified.update(chunk)
    if verified.digest() != encoded.digest():
        raise ValueError('El comprimido no coincide con el SQL textual')
    return {'sql_bytes_original': size, 'sql_bytes': encoded_size, 'source_sql_sha256': original.hexdigest(),
            'sql_sha256': encoded.hexdigest(), 'gzip_sha256': digest(target),
            'gzip_bytes': target.stat().st_size, 'escaped_control_characters': controls}
