<?php
/**
 * smart_fix_na_ips.php
 * =====================================================
 * This script connects to the WGDashboard API to dynamically 
 * discover ALL WireGuard interfaces (even those not explicitly
 * in marzban_panel). It identifies peers with "N/A" IPs
 * and assigns them a valid IP from the interface's subnet.
 *
 * Run:
 *   php crons/smart_fix_na_ips.php
 *
 * Dry-run (no changes):
 *   php crons/smart_fix_na_ips.php --dry-run
 * =====================================================
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

$isDryRun = in_array('--dry-run', $argv ?? []);
echo "=========================================\n";
echo "  WG Smart Peer N/A IP Fixer\n";
echo "  Mode: " . ($isDryRun ? "DRY-RUN (no changes)" : "LIVE") . "\n";
echo "=========================================\n\n";

require_once __DIR__ . '/../config.php';

// Ensure helper functions from WGDashboard.php are loaded
if (!function_exists('getNextAvailableIP')) {
    require_once __DIR__ . '/../WGDashboard.php';
}

// ─── 1. Fetch Unique WGDashboard Servers from Database ──────────────────
$panels_result = $connect->query("SELECT DISTINCT url_panel, password_panel FROM marzban_panel WHERE type = 'WGDashboard'");
if (!$panels_result) {
    die("ERROR: Could not query marzban_panel table.\n");
}
$servers = $panels_result->fetch_all(MYSQLI_ASSOC);

if (empty($servers)) {
    echo "No WGDashboard servers found in database. Exiting.\n";
    exit(0);
}

echo "Found " . count($servers) . " unique WGDashboard server(s).\n\n";

$totalFixed   = 0;
$totalFailed  = 0;
$totalSkipped = 0;

foreach ($servers as $server) {
    $apiBase = rtrim($server['url_panel'], '/');
    $apiKey  = $server['password_panel'];

    echo "─────────────────────────────────────────\n";
    echo "Server URL : {$apiBase}\n\n";

    // ─── 2. Get All Configurations from Server ────────────────────────────
    $confUrl = $apiBase . '/api/getWireguardConfigurations';
    $ch = curl_init($confUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'wg-dashboard-apikey: ' . $apiKey,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status != 200 || !$body) {
        echo "  [ERROR] Could not fetch configs from server (HTTP {$status}). Skipping.\n\n";
        continue;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['status'])) {
        echo "  [ERROR] Invalid API response from getWireguardConfigurations. Skipping.\n\n";
        continue;
    }

    $configs = $data['data'] ?? [];
    if (empty($configs)) {
        echo "  No configurations found on this server.\n\n";
        continue;
    }

    foreach ($configs as $confData) {
        $confName = is_string($confData) ? $confData : ($confData['name'] ?? null);
        if (!$confName) continue;

        echo "  => Checking Interface: {$confName}\n";

        // ─── 3. Fetch Info for the specific Configuration ────────────────
        $infoUrl = $apiBase . '/api/getWireguardConfigurationInfo?configurationName=' . urlencode($confName);
        $ch2 = curl_init($infoUrl);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'wg-dashboard-apikey: ' . $apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $infoBody   = curl_exec($ch2);
        $infoStatus = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($infoStatus != 200 || !$infoBody) {
            echo "     [ERROR] Could not fetch info for {$confName}. Skipping.\n";
            continue;
        }

        $infoData = json_decode($infoBody, true);
        if (!is_array($infoData) || empty($infoData['status'])) {
            echo "     [ERROR] Invalid info response for {$confName}. Skipping.\n";
            continue;
        }

        $allPeers = array_merge(
            $infoData['data']['configurationPeers']           ?? [],
            $infoData['data']['configurationRestrictedPeers'] ?? []
        );

        $subnet = null;
        if (!empty($infoData['data']['configurationInfo']['Address'])) {
            $subnet = $infoData['data']['configurationInfo']['Address'];
        } elseif (!empty($infoData['data']['conf_address'])) {
            $subnet = $infoData['data']['conf_address'];
        }

        if (empty($subnet)) {
            echo "     [ERROR] Could not determine subnet. Skipping.\n";
            continue;
        }
        
        // Handle multiple subnets e.g. "10.0.0.1/24, fd00::1/64" -> get first IPv4
        if (strpos($subnet, ',') !== false) {
            foreach (explode(',', $subnet) as $part) {
                $part = trim($part);
                if (strpos($part, ':') === false && strpos($part, '/') !== false) {
                    $subnet = $part;
                    break;
                }
            }
        }

        echo "     Subnet: {$subnet} | Total Peers: " . count($allPeers) . "\n";

        // ─── 4. Find N/A peers ───────────────────────────────────────────
        $naPeers = [];
        foreach ($allPeers as $peer) {
            $allowedIps = $peer['allowed_ips'] ?? [];
            $isNA = false;
            if (empty($allowedIps)) {
                $isNA = true;
            } else {
                foreach ($allowedIps as $ip) {
                    $cleanIp = explode('/', trim($ip))[0];
                    if (
                        strtoupper($cleanIp) === 'N/A' ||
                        $cleanIp === '' ||
                        $cleanIp === '0.0.0.0' ||
                        !filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    ) {
                        $isNA = true;
                        break;
                    }
                }
            }
            if ($isNA) $naPeers[] = $peer;
        }

        echo "     N/A Peers: " . count($naPeers) . "\n";

        if (empty($naPeers)) {
            echo "     No N/A peers to fix.\n\n";
            continue;
        }

        // ─── 5. Build used IPs list ──────────────────────────────────────
        $usedIps = [];
        foreach ($allPeers as $peer) {
            foreach ($peer['allowed_ips'] ?? [] as $ip) {
                $cleanIp = explode('/', trim($ip))[0];
                if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $usedIps[$cleanIp] = true;
                }
            }
        }
        $dbIps = getUsedIPsFromDb($confName); // This relies on WGDashboard.php logic
        foreach ($dbIps as $ip) {
            $cleanIp = explode('/', trim($ip))[0];
            if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $usedIps[$cleanIp] = true;
            }
        }

        // ─── 6. Fix each N/A peer ────────────────────────────────────────
        foreach ($naPeers as $peer) {
            $pubKey  = $peer['id'] ?? $peer['publicKey'] ?? null;
            $name    = $peer['name'] ?? $peer['id'] ?? 'unknown';
            $privKey = $peer['private_key'] ?? $peer['privateKey'] ?? '';
            $psk     = $peer['preshared_key'] ?? $peer['presharedKey'] ?? '';

            if (empty($pubKey)) {
                echo "       [SKIP] Peer {$name} has no public key.\n";
                $totalSkipped++;
                continue;
            }

            $newIp = getNextAvailableIP($subnet, array_keys($usedIps));
            if (empty($newIp) || !filter_var($newIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                echo "       [FAIL] {$name} - No available IPs left in subnet!\n";
                $totalFailed++;
                continue;
            }

            echo "       -> Fixing Peer : {$name} | Assigning: {$newIp}/32\n";

            if ($isDryRun) {
                $usedIps[$newIp] = true;
                $totalFixed++;
                continue;
            }

            // A. Call WGDashboard updatePeerSettings
            $updatePayload = json_encode([
                'id'                  => $pubKey,
                'name'                => $name,
                'allowed_ip'          => $newIp . '/32',       // the crucial singular key
                'allowed_ips'         => [$newIp . '/32'],     // plural just in case
                'endpoint_allowed_ip' => '0.0.0.0/0',
                'DNS'                 => '1.1.1.1',
                'mtu'                 => 1420,
                'keepalive'           => 21,
                'preshared_key'       => $psk,
                'private_key'         => $privKey,
            ]);

            $updateUrl = $apiBase . '/api/updatePeerSettings/' . urlencode($confName);
            $success   = false;

            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $ch3 = curl_init($updateUrl);
                curl_setopt_array($ch3, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $updatePayload,
                    CURLOPT_HTTPHEADER     => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'wg-dashboard-apikey: ' . $apiKey,
                    ],
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $updateBody   = curl_exec($ch3);
                $updateStatus = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
                curl_close($ch3);

                if ($updateStatus == 200) {
                    $success = true;
                    break;
                }
                echo "          Attempt {$attempt} failed (HTTP {$updateStatus}), retrying...\n";
                usleep(500000);
            }

            if (!$success) {
                echo "       [FAIL] {$name} - updatePeerSettings failed after 5 attempts.\n";
                $totalFailed++;
                continue;
            }

            // B. Update invoice table in DB
            $invoiceQuery = $connect->prepare(
                "SELECT id_invoice, user_info FROM invoice WHERE username = ? LIMIT 1"
            );
            $invoiceQuery->bind_param("s", $name);
            $invoiceQuery->execute();
            $invoiceResult = $invoiceQuery->get_result();

            if ($invoiceResult && $invoiceResult->num_rows > 0) {
                $row = $invoiceResult->fetch_assoc();
                $id_invoice = $row['id_invoice'];
                
                $userInfo = json_decode($row['user_info'], true) ?: [];
                $userInfo['allowed_ips'] = [$newIp . '/32'];
                $userInfoJson = json_encode($userInfo);

                $updateInvoice = $connect->prepare(
                    "UPDATE invoice SET ip = ?, user_info = ? WHERE id_invoice = ?"
                );
                $newIpVal = $newIp . '/32';
                $updateInvoice->bind_param("ssi", $newIpVal, $userInfoJson, $id_invoice);
                $updateInvoice->execute();
                
                echo "          -> Updated DB invoice id: {$id_invoice}\n";
            } else {
                echo "          -> Note: Peer '{$name}' not found in local invoice DB.\n";
            }

            $usedIps[$newIp] = true;
            $totalFixed++;
        }
        echo "\n";
    }
}

echo "=========================================\n";
echo "Summary:\n";
echo "  Fixed   : {$totalFixed}\n";
echo "  Failed  : {$totalFailed}\n";
echo "  Skipped : {$totalSkipped}\n";
echo "=========================================\n";
