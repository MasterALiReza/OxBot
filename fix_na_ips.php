<?php
/**
 * fix_na_ips.php
 * =====================================================
 * این اسکریپت پیرهای WireGuard که آی‌پی N/A دارند رو پیدا می‌کند
 * و به هر کدام یک آی‌پی معتبر و خالی اختصاص می‌دهد.
 *
 * اجرا روی سرور:
 *   php fix_na_ips.php
 *
 * یا با dry-run (فقط نمایش، بدون تغییر):
 *   php fix_na_ips.php --dry-run
 * =====================================================
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

$isDryRun = in_array('--dry-run', $argv ?? []);
echo "==============================\n";
echo "  WG Peer N/A IP Fixer\n";
echo "  Mode: " . ($isDryRun ? "DRY-RUN (no changes)" : "LIVE") . "\n";
echo "==============================\n\n";

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Load WGDashboard helpers; guard double-include
if (!function_exists('getNextAvailableIP')) {
    require_once __DIR__ . '/WGDashboard.php';
}

// ─── Fetch all WGDashboard panels ────────────────────────────────────────
$panels_result = $connect->query("SELECT * FROM marzban_panel WHERE type = 'WGDashboard'");
if (!$panels_result) {
    die("ERROR: Could not query marzban_panel table.\n");
}
$panels = $panels_result->fetch_all(MYSQLI_ASSOC);

if (empty($panels)) {
    echo "No WGDashboard panels found in database. Exiting.\n";
    exit(0);
}

echo "Found " . count($panels) . " WGDashboard panel(s).\n\n";

$totalFixed   = 0;
$totalFailed  = 0;
$totalSkipped = 0;

foreach ($panels as $panel) {
    $panelName = $panel['name_panel'];
    $apiBase   = rtrim($panel['url_panel'], '/');
    $apiKey    = $panel['password_panel'];
    $confName  = $panel['inboundid'];

    echo "─────────────────────────────────────────\n";
    echo "Panel: {$panelName}\n";
    echo "URL  : {$apiBase}\n";
    echo "Conf : {$confName}\n\n";

    // 1. Fetch all peers
    $infoUrl = $apiBase . '/api/getWireguardConfigurationInfo?configurationName=' . urlencode($confName);
    $ch = curl_init($infoUrl);
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
        echo "  [ERROR] Could not reach panel API (HTTP {$status}). Skipping.\n\n";
        continue;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['status'])) {
        echo "  [ERROR] Invalid API response. Skipping.\n\n";
        continue;
    }

    $allPeers = array_merge(
        $data['data']['configurationPeers']           ?? [],
        $data['data']['configurationRestrictedPeers'] ?? []
    );

    // 2. Determine subnet
    $subnet = null;
    if (!empty($data['data']['configurationInfo']['Address'])) {
        $subnet = $data['data']['configurationInfo']['Address'];
    } elseif (!empty($data['data']['conf_address'])) {
        $subnet = $data['data']['conf_address'];
    }

    if (empty($subnet)) {
        echo "  [ERROR] Could not determine subnet. Skipping.\n\n";
        continue;
    }
    if (strpos($subnet, ',') !== false) {
        foreach (explode(',', $subnet) as $part) {
            $part = trim($part);
            if (strpos($part, ':') === false && strpos($part, '/') !== false) {
                $subnet = $part;
                break;
            }
        }
    }
    echo "  Subnet : {$subnet}\n";
    echo "  Total peers: " . count($allPeers) . "\n";

    // 3. Find N/A peers
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

    echo "  N/A peers: " . count($naPeers) . "\n\n";

    if (empty($naPeers)) {
        echo "  No N/A peers on this panel.\n\n";
        $totalSkipped++;
        continue;
    }

    // 4. Build used IPs list
    $usedIps = [];
    foreach ($allPeers as $peer) {
        foreach ($peer['allowed_ips'] ?? [] as $ip) {
            $cleanIp = explode('/', trim($ip))[0];
            if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $usedIps[$cleanIp] = true;
            }
        }
    }
    $dbIps = getUsedIPsFromDb($panelName);
    foreach ($dbIps as $ip) {
        $cleanIp = explode('/', trim($ip))[0];
        if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $usedIps[$cleanIp] = true;
        }
    }

    // 5. Fix each N/A peer
    foreach ($naPeers as $peer) {
        $pubKey  = $peer['id'] ?? $peer['publicKey'] ?? null;
        $name    = $peer['name'] ?? $peer['id'] ?? 'unknown';
        $privKey = $peer['private_key'] ?? $peer['privateKey'] ?? '';
        $psk     = $peer['preshared_key'] ?? $peer['presharedKey'] ?? '';

        if (empty($pubKey)) {
            echo "  [SKIP] Peer has no public key.\n";
            $totalSkipped++;
            continue;
        }

        $newIp = getNextAvailableIP($subnet, array_keys($usedIps));
        if (empty($newIp) || !filter_var($newIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            echo "  [FAIL] {$name} - No available IPs left in subnet!\n";
            $totalFailed++;
            continue;
        }

        echo "  -> Peer : {$name}\n";
        echo "     Assigning: {$newIp}/32\n";

        if ($isDryRun) {
            echo "     [DRY-RUN] Would assign {$newIp}/32\n";
            $usedIps[$newIp] = true;
            $totalFixed++;
            continue;
        }

        // 6a. Call WGDashboard updatePeerSettings
        $updatePayload = json_encode([
            'id'                  => $pubKey,
            'name'                => $name,
            'allowed_ip'          => $newIp . '/32',
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
            $ch2 = curl_init($updateUrl);
            curl_setopt_array($ch2, [
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
            $updateBody   = curl_exec($ch2);
            $updateStatus = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            if ($updateStatus == 200) {
                $success = true;
                break;
            }
            echo "     Attempt {$attempt} failed (HTTP {$updateStatus}), retrying...\n";
            usleep(500000);
        }

        if (!$success) {
            echo "  [FAIL] {$name} - updatePeerSettings failed after 5 attempts.\n";
            $totalFailed++;
            continue;
        }

        // 6b. Update invoice table in DB
        $invoiceQuery = $connect->prepare(
            "SELECT id_invoice, user_info FROM invoice WHERE username = ? AND Service_location = ? LIMIT 1"
        );
        $invoiceQuery->bind_param("ss", $name, $panelName);
        $invoiceQuery->execute();
        $invoiceResult = $invoiceQuery->get_result();
        $invoiceRow    = $invoiceResult->fetch_assoc();

        if ($invoiceRow) {
            $userInfo = json_decode($invoiceRow['user_info'] ?? '{}' , true) ?: [];
            $userInfo['allowed_ips'] = [$newIp . '/32'];
            $newUserInfo = json_encode($userInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $updateDb = $connect->prepare(
                "UPDATE invoice SET user_info = ? WHERE id_invoice = ? LIMIT 1"
            );
            $updateDb->bind_param("ss", $newUserInfo, $invoiceRow['id_invoice']);
            if ($updateDb->execute()) {
                echo "     DB updated (invoice: {$invoiceRow['id_invoice']})\n";
            } else {
                echo "     DB update FAILED: " . $connect->error . "\n";
            }
        } else {
            echo "     No matching invoice in DB for '{$name}' (manual peer, skipping DB)\n";
        }

        $usedIps[$newIp] = true;
        echo "     Fixed: {$newIp}/32 -> {$name}\n";
        $totalFixed++;
        usleep(300000); // 0.3s gentle pause
    }

    echo "\n";
}

echo "=======================================\n";
echo "SUMMARY\n";
echo "  Fixed  : {$totalFixed}\n";
echo "  Failed : {$totalFailed}\n";
echo "  Skipped: {$totalSkipped}\n";
echo "=======================================\n";
if ($isDryRun) {
    echo "\n[DRY-RUN] No actual changes were made.\n";
    echo "Run without --dry-run to apply fixes.\n";
}

