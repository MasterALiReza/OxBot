import paramiko
import sys

def check_ssh():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect('94.183.225.232', username='root', password='clgWc4fHtn', timeout=10)
        
        # Check iptables
        stdin, stdout, stderr = client.exec_command('iptables -L -n | grep -E "91.107.244.215|DROP|REJECT"')
        print("--- iptables rules ---")
        print(stdout.read().decode())
        
        # Check fail2ban
        stdin, stdout, stderr = client.exec_command('fail2ban-client status')
        print("--- fail2ban status ---")
        print(stdout.read().decode())

        # Check UFW
        stdin, stdout, stderr = client.exec_command('ufw status numbered | grep 91.107.244.215')
        print("--- ufw status ---")
        print(stdout.read().decode())

        client.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == '__main__':
    check_ssh()
