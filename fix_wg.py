import re
import ipaddress
import os

input_file = r'C:\Users\iWexort\Documents\EGPROXY-3.conf'
output_file = r'C:\Users\iWexort\Documents\EGPROXY-3-FIXED.conf'

if not os.path.exists(input_file):
    print("File not found")
    exit(1)

with open(input_file, 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

blocks = re.split(r'(?=\[Peer\]|\[Interface\])', content)
fixed_blocks = []
removed_peers = []

for block in blocks:
    if not block.strip():
        continue
    
    if block.startswith('[Peer]'):
        # Extract AllowedIPs
        match = re.search(r'AllowedIPs\s*=\s*(.*)', block)
        if match:
            ips_str = match.group(1).strip()
            ips = [ip.strip() for ip in ips_str.split(',')]
            valid = True
            for ip in ips:
                try:
                    ipaddress.ip_network(ip)
                except ValueError:
                    valid = False
                    break
            
            if not valid:
                pk_match = re.search(r'PublicKey\s*=\s*(.*)', block)
                pk = pk_match.group(1).strip() if pk_match else 'Unknown'
                removed_peers.append(f"PublicKey: {pk} | Invalid AllowedIPs: {ips_str}")
                continue # Skip adding this block
        else:
            # Missing AllowedIPs completely
            pk_match = re.search(r'PublicKey\s*=\s*(.*)', block)
            pk = pk_match.group(1).strip() if pk_match else 'Unknown'
            removed_peers.append(f"PublicKey: {pk} | Missing AllowedIPs")
            continue # Skip adding this block
            
    fixed_blocks.append(block)

with open(output_file, 'w', encoding='utf-8') as f:
    f.write(''.join(fixed_blocks))

print(f"Total blocks originally: {len(blocks)}")
print(f"Total blocks after fix: {len(fixed_blocks)}")
print(f"Total invalid peers removed: {len(removed_peers)}")
if removed_peers:
    print("List of removed peers:")
    for p in removed_peers:
        print(f"  - {p}")
