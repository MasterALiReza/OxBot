import paramiko
import sys

try:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect('94.183.225.232', username='root', password='clgWc4fHtn', timeout=10)
    print("SSH Connected!")
    
    # Check domain resolution
    stdin, stdout, stderr = client.exec_command('ping -c 1 pcodm.wxnet.pro')
    print("PING:", stdout.read().decode('utf-8'))
    
    # Check curl
    stdin, stdout, stderr = client.exec_command('curl -s -i https://pcodm.wxnet.pro:8443/api/getWireguardConfigurations')
    print("CURL:", stdout.read().decode('utf-8'))
    
    # Look for bot files
    stdin, stdout, stderr = client.exec_command('find / -name "sanaei_inbounds.php" 2>/dev/null')
    print("FILES:", stdout.read().decode('utf-8'))
    
except Exception as e:
    print("ERROR:", str(e))
