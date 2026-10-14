import json
import pathlib
import subprocess
import sys
import paramiko


def read_connection():
    """Read local private/config/keys.php through the framework, never from Git."""
    root = pathlib.Path(__file__).resolve().parent
    php = root.parents[1] / 'php/php.exe'
    core = root.parent / 'KumbiaPHP/core/kumbia/config.php'
    def quote(path):
        return "'" + path.as_posix().replace("'", "\\'") + "'"
    code = ("define('APP_PATH', " + quote(root / 'private') + " . '/');"
            "require " + quote(core) + ";"
            "$connection=Config::get('keys.ssh');"
            "if (!is_array($connection)) { fwrite(STDERR, 'Missing SSH configuration'); exit(1); }"
            "echo json_encode($connection);")
    result = subprocess.run([str(php), '-n', '-r', code], capture_output=True, check=True)
    values = json.loads(result.stdout)
    if any(not isinstance(values.get(key), str) or not values[key] for key in ('host', 'user', 'password')):
        raise RuntimeError('Incomplete SSH configuration')
    return values


def main():
    connection = read_connection()
    command = sys.argv[1]
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        ssh.connect(connection['host'], username=connection['user'], password=connection['password'], timeout=10)
        _, stdout, stderr = ssh.exec_command(command)
        output = stdout.read().decode('utf-8', errors='replace')
        errors = stderr.read().decode('utf-8', errors='replace')
        if output:
            print(output)
        if errors:
            print(errors, file=sys.stderr)
        status = stdout.channel.recv_exit_status()
        if status:
            raise SystemExit(status)
    finally:
        ssh.close()


if __name__ == '__main__':
    main()
