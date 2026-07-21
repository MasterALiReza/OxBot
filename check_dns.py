import paramiko
import sys

def check_dns():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect('94.183.225.232', username='root', password='clgWc4fHtn', timeout=10)
        
        stdin, stdout, stderr = client.exec_command('ping -c 1 pcodm.wxnet.pro')
        print(stdout.read().decode())
        
        client.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == '__main__':
    check_dns()
