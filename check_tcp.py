import paramiko
import sys

def check_tcp():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect('94.183.225.232', username='root', password='clgWc4fHtn', timeout=10)
        
        # Test bot.vipvirtualnet.eu
        stdin, stdout, stderr = client.exec_command('curl -I -m 5 http://91.107.244.215')
        print("--- bot.vipvirtualnet.eu (91.107.244.215) ---")
        print(stdout.read().decode())
        print(stderr.read().decode())
        
        # Test bot.grootvip.eu
        stdin, stdout, stderr = client.exec_command('curl -I -m 5 http://65.109.181.134')
        print("--- bot.grootvip.eu (65.109.181.134) ---")
        print(stdout.read().decode())
        print(stderr.read().decode())

        client.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == '__main__':
    check_tcp()
