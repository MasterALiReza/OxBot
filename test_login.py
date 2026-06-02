import requests
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

base_url = 'https://tr.vipvirtualnet.eu:2053/l91KugYhhUMJyjqfsC'
username = 'AdminWexort'
password = 'AdminWexort123'

s = requests.Session()
s.verify = False

# 1. Fetch CSRF Token
csrf_resp = s.get(f"{base_url}/csrf-token", headers={"User-Agent": "Mozilla/5.0"})
print("CSRF Fetch Status:", csrf_resp.status_code)
csrf_token = csrf_resp.json().get('obj', '')
print("CSRF Token:", csrf_token)

# 2. Login (JSON)
headers = {
    "User-Agent": "Mozilla/5.0",
    "Accept": "application/json, text/plain, */*",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    "X-CSRF-Token": csrf_token,
    "Origin": "https://tr.vipvirtualnet.eu:2053",
    "Referer": f"{base_url}/login"
}
payload_json = {
    "username": username,
    "password": password
}

print("\n--- Trying JSON Login ---")
login_resp_json = s.post(f"{base_url}/login", headers=headers, json=payload_json)
print("Login JSON Status:", login_resp_json.status_code)
print("Login JSON Body:", login_resp_json.text)

# Reset session
s = requests.Session()
s.verify = False
csrf_resp = s.get(f"{base_url}/csrf-token", headers={"User-Agent": "Mozilla/5.0"})
csrf_token = csrf_resp.json().get('obj', '')

# 3. Login (Form)
headers_form = {
    "User-Agent": "Mozilla/5.0",
    "Accept": "application/json, text/plain, */*",
    "Content-Type": "application/x-www-form-urlencoded",
    "X-Requested-With": "XMLHttpRequest",
    "X-CSRF-Token": csrf_token,
    "Origin": "https://tr.vipvirtualnet.eu:2053",
    "Referer": f"{base_url}/login"
}
payload_form = {
    "username": username,
    "password": password
}

print("\n--- Trying Form Login ---")
login_resp_form = s.post(f"{base_url}/login", headers=headers_form, data=payload_form)
print("Login Form Status:", login_resp_form.status_code)
print("Login Form Body:", login_resp_form.text)
