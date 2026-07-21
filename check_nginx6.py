import paramiko

try:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect('94.183.225.232', username='root', password='clgWc4fHtn', timeout=10)
    
    stdin, stdout, stderr = client.exec_command('netstat -tulpn | grep nginx')
    print("NETSTAT:", stdout.read().decode('utf-8'))
    
except Exception as e:
    print("ERROR:", str(e))
