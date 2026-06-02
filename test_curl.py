import subprocess
import json

base_url = 'https://tr.vipvirtualnet.eu:2053/l91KugYhhUMJyjqfsC'

# Fetch CSRF
proc1 = subprocess.run([
    'curl.exe', '-k', '-s', '-c', 'test_cookie.txt', '-b', 'test_cookie.txt',
    '-H', 'User-Agent: Mozilla/5.0',
    f'{base_url}/csrf-token'
], capture_output=True, text=True)

print("CSRF Fetch Output:", proc1.stdout)
csrf_obj = json.loads(proc1.stdout)
csrf_token = csrf_obj.get('obj', '')
print("Token:", csrf_token)

# POST Login
proc2 = subprocess.run([
    'curl.exe', '-k', '-s', '-i', '-c', 'test_cookie.txt', '-b', 'test_cookie.txt',
    '-X', 'POST',
    '-H', 'User-Agent: Mozilla/5.0',
    '-H', 'Accept: application/json, text/plain, */*',
    '-H', 'Content-Type: application/x-www-form-urlencoded',
    '-H', 'X-Requested-With: XMLHttpRequest',
    '-H', f'Origin: https://tr.vipvirtualnet.eu:2053',
    '-H', f'Referer: {base_url}/login',
    '-H', f'X-CSRF-Token: {csrf_token}',
    '-d', 'username=AdminWexort&password=AdminWexort123',
    f'{base_url}/login'
], capture_output=True, text=True)

print("Login Output:\n", proc2.stdout)
