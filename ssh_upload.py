import paramiko
import sys

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('65.109.181.134', username='root', password='qeEehW7rrvNT', timeout=10)

local_path = sys.argv[1]
remote_path = sys.argv[2]

sftp = client.open_sftp()
sftp.put(local_path, remote_path)
sftp.close()
client.close()
print(f"Uploaded {local_path} -> {remote_path}")
