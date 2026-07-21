<?php
include('config.php');
include('WGDashboard.php');

$namepanel = "سرور مخصوص کالاف 1 VIP";
$marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
if (!$marzban_list_get) {
    echo "Panel not found in DB\n";
} else {
    echo "Name in DB: " . $marzban_list_get['name_panel'] . "\n";
    echo "Cached subnet in DB: " . ($marzban_list_get['subnet_cache'] ?? 'NULL') . "\n";
    
    $db_used_ips = getUsedIPsFromDb($namepanel);
    $api_used_ips = getUsedIPs($namepanel);
    echo "DB used IPs count: " . count($db_used_ips) . "\n";
    echo "API used IPs count: " . ($api_used_ips === false ? 'false' : count($api_used_ips)) . "\n";
    
    $all_used_ips = array_merge($db_used_ips, is_array($api_used_ips) ? $api_used_ips : []);
    $clean_used_ips = [];
    foreach ($all_used_ips as $ip) {
        $clean_ip = explode('/', $ip)[0];
        if (filter_var($clean_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $clean_used_ips[$clean_ip] = true;
        }
    }
    $clean_used_ips = array_keys($clean_used_ips);
    echo "Total unique used IPs: " . count($clean_used_ips) . "\n";
    
    $subnet = getCachedSubnet($namepanel, $marzban_list_get);
    echo "Subnet returned by getCachedSubnet: " . $subnet . "\n";
    
    // Simulate isSubnetFull for /24 and /23
    echo "isSubnetFull (/24): " . (isSubnetFull("13.0.0.1/24", $clean_used_ips) ? "YES" : "NO") . "\n";
    echo "isSubnetFull (/23): " . (isSubnetFull("13.0.0.1/23", $clean_used_ips) ? "YES" : "NO") . "\n";
}
