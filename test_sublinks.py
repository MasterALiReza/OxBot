import requests
import json
import urllib3
urllib3.disable_warnings()

base_url = 'https://tr.vipvirtualnet.eu:2053/l91KugYhhUMJyjqfsC'
s = requests.Session()
s.verify = False

# login
csrf = s.get(f"{base_url}/csrf-token", headers={"User-Agent":"Mozilla/5.0"}).json().get('obj')
login = s.post(f"{base_url}/login", data={"username":"AdminWexort","password":"AdminWexort123"}, headers={
    "User-Agent":"Mozilla/5.0",
    "Accept":"application/json",
    "Content-Type":"application/x-www-form-urlencoded",
    "X-Requested-With":"XMLHttpRequest",
    "Origin":"https://tr.vipvirtualnet.eu:2053",
    "Referer":f"{base_url}/login",
    "X-CSRF-Token":csrf
})

# fetch clients to get a subId
clients = s.get(f"{base_url}/panel/api/inbounds/list", headers={"Accept":"application/json", "X-Requested-With":"XMLHttpRequest"}).json()
subid = None
if 'obj' in clients and len(clients['obj']) > 0:
    for inbound in clients['obj']:
        settings = inbound['settings']
        if 'clients' in settings and len(settings['clients']) > 0:
            subid = settings['clients'][0].get('subId')
            if subid: break

if subid:
    print(f"Testing subId: {subid}")
    links = s.get(f"{base_url}/panel/api/clients/subLinks/{subid}", headers={"Accept":"application/json", "X-Requested-With":"XMLHttpRequest"}).json()
    print("Links API Response:", json.dumps(links, indent=2))
else:
    print("No subId found to test.")
