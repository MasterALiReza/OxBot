<?php
include_once 'baseinfo.php';
include_once 'config.php';
include_once 'WGDashboard.php';

echo "========================================\n";
echo " WGDashboard N/A IP Fixer \n";
echo "========================================\n\n";

global $connect, $pdo;

// Fetch all WGDashboard panels
$panels_stmt = $connect->prepare("SELECT * FROM marzban_panel WHERE type = 'WGDashboard'");
$panels_stmt->execute();
$panels = $panels_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$panels_stmt->close();

$total_fixed = 0;

foreach ($panels as $panel) {
    $namepanel = $panel['name_panel'];
    echo "Checking panel: {$namepanel}\n";
    
    // Get all peers from API
    $url = $panel['url_panel'] . '/api/getWireguardConfigurationInfo?configurationName=' . $panel['inboundid'];
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $panel['password_panel']
    );
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $api_res = $req->get();
    
    if (empty($api_res['status']) || $api_res['status'] != 200 || empty($api_res['body'])) {
        echo "  [X] Failed to connect to WGDashboard panel {$namepanel}.\n";
        continue;
    }
    
    $response = json_decode($api_res['body'], true);
    if (!is_array($response) || empty($response['status'])) {
        echo "  [X] Invalid response from panel {$namepanel}.\n";
        continue;
    }
    
    $peers = array_merge(
        $response['data']['configurationPeers'] ?? [],
        $response['data']['configurationRestrictedPeers'] ?? []
    );
    
    $subnet = getCachedSubnet($namepanel, $panel);
    if (!$subnet) {
        $subnet = '10.0.0.0/24'; // Fallback
    }
    echo "  Subnet detected: {$subnet}\n";
    
    foreach ($peers as $peer) {
        $allowed_ips = $peer['allowed_ips'] ?? [];
        $has_na = false;
        
        if (empty($allowed_ips) || (is_array($allowed_ips) && in_array('N/A', $allowed_ips)) || $allowed_ips === 'N/A' || $allowed_ips === ['N/A']) {
            $has_na = true;
        }
        
        if ($has_na) {
            echo "  [!] Found peer with N/A IP: {$peer['name']} (PubKey: {$peer['public_key']})\n";
            
            // Generate a valid IP
            $db_used_ips = getUsedIPsFromDb($namepanel);
            $panel_used_ips = getUsedIPs($namepanel);
            $clean_used_ips = array();
            foreach (array_merge($db_used_ips, $panel_used_ips) as $ip) {
                if (!empty($ip) && filter_var(explode('/', $ip)[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $clean_used_ips[explode('/', $ip)[0]] = true;
                }
            }
            $clean_used_ips = array_keys($clean_used_ips);
            
            if (isSubnetFull($subnet, $clean_used_ips)) {
                echo "      [ERROR] Subnet is full, cannot assign new IP.\n";
                continue;
            }
            
            $ipToAssign = getNextAvailableIP($subnet, $clean_used_ips);
            if (empty($ipToAssign) || !filter_var($ipToAssign, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                echo "      [ERROR] Failed to generate valid IP.\n";
                continue;
            }
            
            echo "      -> Assigning new IP: {$ipToAssign}\n";
            
            // To update the IP we need preshared and private key from DB
            $stmt = $connect->prepare("SELECT * FROM invoice WHERE username = ? AND Name_panel = ? LIMIT 1");
            $stmt->bind_param("ss", $peer['name'], $namepanel);
            $stmt->execute();
            $invoice = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $private_key = '';
            $preshared_key = '';
            $peerConfig = $peer; // Base config
            
            if ($invoice && !empty($invoice['user_info'])) {
                $db_info = json_decode($invoice['user_info'], true);
                if (isset($db_info['private_key'])) {
                    $private_key = $db_info['private_key'];
                }
                if (isset($db_info['preshared_key'])) {
                    $preshared_key = $db_info['preshared_key'];
                }
                $peerConfig = array_merge($peerConfig, $db_info);
            }
            
            $update_success = false;
            for ($i = 0; $i < 5; $i++) {
                $updateData = [
                    'id'                     => $peer['public_key'],
                    'name'                   => $peer['name'],
                    'allowed_ip'             => $ipToAssign . '/32',
                    'endpoint_allowed_ip'    => '0.0.0.0/0',
                    'DNS'                    => '1.1.1.1',
                    'mtu'                    => 1420,
                    'keepalive'              => 21,
                ];
                if (!empty($private_key)) {
                    $updateData['private_key'] = $private_key;
                }
                if (!empty($preshared_key)) {
                    $updateData['preshared_key'] = $preshared_key;
                }
                
                $ipUpdateResult = updatepear($namepanel, $updateData);
                
                if (!empty($ipUpdateResult['status']) && $ipUpdateResult['status'] == 200) {
                    $update_success = true;
                    break;
                }
                sleep(1);
            }
            
            if ($update_success) {
                // Update DB
                $peerConfig['allowed_ips'] = [$ipToAssign . '/32'];
                $peerConfig['address'] = [$ipToAssign . '/32'];
                $user_info_json = json_encode($peerConfig);
                
                if ($invoice) {
                    $stmt = $connect->prepare("UPDATE invoice SET user_info = ? WHERE id_invoice = ?");
                    $stmt->bind_param("ss", $user_info_json, $invoice['id_invoice']);
                    $stmt->execute();
                    $stmt->close();
                    echo "      [OK] Updated peer in panel and database!\n";
                    $total_fixed++;
                } else {
                    echo "      [OK] Updated peer in panel, but no invoice found in database!\n";
                }
            } else {
                echo "      [ERROR] WGDashboard API failed to update the IP.\n";
            }
        }
    }
}

echo "\n========================================\n";
echo " Done! Fixed {$total_fixed} peers.\n";
echo "========================================\n";
