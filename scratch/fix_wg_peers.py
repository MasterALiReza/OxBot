import re
import ipaddress
import sys

file_path = r'C:\Users\iWexort\Documents\Github\EGPROXY-3.conf'

try:
    with open(file_path, 'r', encoding='utf-8') as f:
        lines = f.readlines()
except Exception as e:
    print(f"Error reading file: {e}")
    sys.exit(1)

# Collect all used IPs
used_ips = set()
for line in lines:
    m = re.search(r'AllowedIPs\s*=\s*([0-9\.]+)', line)
    if m:
        try:
            ip_str = m.group(1).split('/')[0]
            ip = ipaddress.IPv4Address(ip_str)
            used_ips.add(int(ip))
        except:
            pass

def get_next_ip():
    base = int(ipaddress.IPv4Address('10.0.0.0'))
    # Range 2 to ~1024 to accommodate /23 and a bit more
    for i in range(2, 1024):
        cand = base + i
        if cand % 256 == 0 or cand % 256 == 255:
            continue
        if cand not in used_ips:
            used_ips.add(cand)
            return str(ipaddress.IPv4Address(cand))
    return None

new_lines = []
fixed_count = 0
assigned_ips = []

in_peer = False
current_peer_lines = []
has_allowed_ips = False

for line in lines:
    stripped = line.strip()
    if stripped == '[Peer]':
        if in_peer:
            if not has_allowed_ips:
                next_ip = get_next_ip()
                if next_ip:
                    idx_to_insert = len(current_peer_lines)
                    for i, pline in enumerate(current_peer_lines):
                        if pline.strip().startswith('PublicKey') or pline.strip().startswith('PresharedKey'):
                            idx_to_insert = i + 1
                    current_peer_lines.insert(idx_to_insert, f"AllowedIPs = {next_ip}/32\n")
                    fixed_count += 1
                    assigned_ips.append(next_ip)
            new_lines.extend(current_peer_lines)
            
        in_peer = True
        current_peer_lines = [line]
        has_allowed_ips = False
    elif stripped == '[Interface]':
        if in_peer:
            if not has_allowed_ips:
                next_ip = get_next_ip()
                if next_ip:
                    idx_to_insert = len(current_peer_lines)
                    for i, pline in enumerate(current_peer_lines):
                        if pline.strip().startswith('PublicKey') or pline.strip().startswith('PresharedKey'):
                            idx_to_insert = i + 1
                    current_peer_lines.insert(idx_to_insert, f"AllowedIPs = {next_ip}/32\n")
                    fixed_count += 1
                    assigned_ips.append(next_ip)
            new_lines.extend(current_peer_lines)
        in_peer = False
        current_peer_lines = [line]
        new_lines.extend(current_peer_lines)
    else:
        if in_peer:
            current_peer_lines.append(line)
            if stripped.startswith('AllowedIPs'):
                has_allowed_ips = True
        else:
            if not current_peer_lines and not in_peer: # We are not in a peer block yet, append to new_lines directly
                # Actually, [Interface] lines are added in the block above, or if there's header stuff
                # wait, when starting, in_peer is False.
                # So we just append to new_lines.
                if stripped != '[Interface]': # Because [Interface] is handled above
                    new_lines.append(line)

# Handle last peer
if in_peer:
    if not has_allowed_ips:
        next_ip = get_next_ip()
        if next_ip:
            idx_to_insert = len(current_peer_lines)
            for i, pline in enumerate(current_peer_lines):
                if pline.strip().startswith('PublicKey') or pline.strip().startswith('PresharedKey'):
                    idx_to_insert = i + 1
            current_peer_lines.insert(idx_to_insert, f"AllowedIPs = {next_ip}/32\n")
            fixed_count += 1
            assigned_ips.append(next_ip)
    new_lines.extend(current_peer_lines)

with open(file_path, 'w', encoding='utf-8') as f:
    f.writelines(new_lines)

print(f"Fixed {fixed_count} peers.")
for i in range(0, len(assigned_ips), 10):
    print(", ".join(assigned_ips[i:i+10]))
