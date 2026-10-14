from ssh_cmd import read_connection
connection = read_connection()
import paramiko
import sys
import os

host = connection['host']
user = connection['user']
password = connection['password']

local_path = sys.argv[1]
remote_path = sys.argv[2]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    ssh.connect(host, username=user, password=password, timeout=10)
    sftp = ssh.open_sftp()
    sftp.put(local_path, remote_path)
    print(f"Successfully uploaded {local_path} to {remote_path}")
    sftp.close()
except Exception as e:
    print(f"Error: {e}")
finally:
    ssh.close()
