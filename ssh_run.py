import paramiko
import sys
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('94.183.225.232', username='root', password='clgWc4fHtn', timeout=10)
stdin, stdout, stderr = client.exec_command(sys.argv[1])
out = stdout.read()
try: print(out.decode('utf-8'))
except: print(out)
err = stderr.read()
if err: print('STDERR:', err.decode('utf-8'))
client.close()
