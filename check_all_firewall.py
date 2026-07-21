import paramiko
import sys

def check_all_firewall():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect('94.183.225.232', username='root', password='clgWc4fHtn', timeout=10)
        
        # Check iptables
        stdin, stdout, stderr = client.exec_command('iptables -L -n')
        print("--- iptables rules ---")
        print(stdout.read().decode())
        
        client.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == '__main__':
    check_all_firewall()
