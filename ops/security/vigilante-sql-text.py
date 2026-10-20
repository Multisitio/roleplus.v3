"""Representacion textual de ETX dentro de valores INSERT de mariadb-dump."""
import re


def encode_controls(data):
    data.decode('utf-8-sig')
    count = data.count(b'\x03')
    if not count:
        return data, 0
    if b"SQL_MODE='NO_AUTO_VALUE_ON_ZERO'" not in data[:8192]:
        raise ValueError('Modo SQL del volcado no acreditado')
    output = []
    changed = 0
    for line in data.split(b'\n'):
        if b'\x03' not in line:
            output.append(line)
            continue
        match = re.match(rb'INSERT INTO `[a-zA-Z0-9_]+` VALUES ', line)
        if not match:
            raise ValueError('Control fuera de INSERT permitido')
        pieces = [line[:match.end()]]
        i = match.end()
        while i < len(line):
            if line[i] == 3:
                raise ValueError('Control fuera de literal')
            if line[i] != 39:
                pieces.append(line[i:i + 1])
                i += 1
                continue
            start = i
            i += 1
            while i < len(line):
                if line[i] == 92:
                    i += 2
                elif line[i] == 39:
                    if i + 1 < len(line) and line[i + 1] == 39:
                        i += 2
                    else:
                        break
                else:
                    i += 1
            if i >= len(line):
                raise ValueError('Literal sin cierre')
            literal = line[start:i + 1]
            if b'\x03' in literal:
                if line[start - 1] not in b'(, \t' or b'\\\x03' in literal:
                    raise ValueError('Literal con prefijo o escape requiere revision')
                chunks = literal[1:-1].split(b'\x03')
                replacement = b'CONCAT(' + b',CHAR(3 USING utf8mb4),'.join(
                    b"'" + chunk + b"'" for chunk in chunks) + b')'
                changed += len(chunks) - 1
                pieces.append(replacement)
            else:
                pieces.append(literal)
            i += 1
        output.append(b''.join(pieces))
    if changed != count:
        raise ValueError('No se representaron todos los controles')
    return b'\n'.join(output), changed
