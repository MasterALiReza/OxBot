<?php
ob_start();
require '../inc/config.php';

$old_cwd = getcwd();
chdir(__DIR__ . '/../../');
require_once 'function.php';
require_once 'botapi.php';
require_once 'MHSanaei-3.2.php';
require_once 'panels.php';
chdir($old_cwd);

$ManagePanel = new ManagePanel();

ob_end_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'نشست منقضی شده است']);
    exit;
}

$agent_id = $_SESSION['agent_id'];
$action = $_POST['action'] ?? '';

// Check agent access level
$stmtAgent = $pdo->prepare("SELECT Balance, agent FROM user WHERE id = :id");
$stmtAgent->execute([':id' => $agent_id]);
$agentUser = $stmtAgent->fetch(PDO::FETCH_ASSOC);

if (!$agentUser || !in_array($agentUser['agent'], ['n', 'n2', 'all'])) {
    echo json_encode(['status' => 'error', 'message' => 'شما دسترسی نمایندگی ندارید']);
    exit;
}

$agentType = $agentUser['agent'];
$wallet = (float)($agentUser['Balance'] ?? 0);

$negativeCreditLimit = 0;
if ($agentType === 'n2' || $agentType === 'all') {
    $stmtNC = $pdo->prepare("SELECT value FROM shopSetting WHERE Namevalue = 'agent_n2_negative_credit' LIMIT 1");
    $stmtNC->execute();
    $ncRow = $stmtNC->fetch(PDO::FETCH_ASSOC);
    if ($ncRow && is_numeric($ncRow['value'])) {
        $negativeCreditLimit = -1 * abs((float)$ncRow['value']);
    }
}

if ($action === 'create_user') {
    $location = $_POST['location'] ?? '';
    $product_id = $_POST['product_id'] ?? '';
    $username_req = trim($_POST['username'] ?? '');

    if (empty($location) || empty($product_id)) {
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    // Check product access
    $loc_cond = getProductLocCondition($location);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :id AND (agent = :agent OR agent = 'all') AND $loc_cond LIMIT 1");
    $stmt->execute([':id' => $product_id, ':agent' => $agentType]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'پلن نامعتبر یا عدم دسترسی']);
        exit;
    }

    $price = (float)$product['price_product'];

    // --- TEST ACCOUNT DAILY LIMIT CHECK ---
    if ($product['name_product'] === 'سرویس تست') {
        $stmtLimit = $pdo->prepare("SELECT value FROM shopSetting WHERE Namevalue = 'agent_test_limit' LIMIT 1");
        $stmtLimit->execute();
        $limitRow = $stmtLimit->fetch(PDO::FETCH_ASSOC);
        
        if ($limitRow && $limitRow['value'] !== '' && is_numeric($limitRow['value'])) {
            $dailyLimit = (int)$limitRow['value'];
            if ($dailyLimit > 0) {
                $todayStart = strtotime("today");
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = :id AND name_product = 'سرویس تست' AND time_sell >= :today");
                $stmtCount->execute([':id' => $agent_id, ':today' => $todayStart]);
                $createdToday = $stmtCount->fetchColumn();
                
                if ($createdToday >= $dailyLimit) {
                    echo json_encode(['status' => 'error', 'message' => "سقف ساخت اکانت تست روزانه شما ($dailyLimit) پر شده است."]);
                    exit;
                }
            } elseif ($dailyLimit === 0) {
                // If set to 0 explicitly, maybe they are not allowed at all
                echo json_encode(['status' => 'error', 'message' => "شما اجازه ساخت اکانت تست ندارید."]);
                exit;
            }
        }
    }

    if ($wallet - $price < $negativeCreditLimit) {
        echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست (سقف اعتبار تکمیل است)']);
        exit;
    }

    // Determine Username
    $username = $username_req;
    if (empty($username)) {
        $username = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 5) . rand(1000, 9999);
    }

    $Data_Config = [
        'expire' => $product['Service_time'] == 0 ? 0 : strtotime('+' . $product['Service_time'] . ' days'),
        'data_limit' => $product['Volume_constraint'] == 0 ? 0 : $product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $agent_id,
        'username' => 'AgentPanel',
        'type' => 'buy'
    ];

    // --- ATOMIC WALLET DEDUCTION FIRST (PREVENT RACE CONDITIONS) ---
    if ($price > 0) {
        $stmtW = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND (Balance - :p) >= :limit");
        $stmtW->execute([':p' => $price, ':id' => $agent_id, ':limit' => $negativeCreditLimit]);
        if ($stmtW->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست یا تراکنش همزمان رخ داده است.']);
            exit;
        }
    }

    // Fetch Panel Name
    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $location]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $location;

    // Call Creation API
    $response = $ManagePanel->createUser($name_panel, $product['code_product'], $username, $Data_Config);

    if (isset($response['status']) && $response['status'] === 'successful') {

        // Insert Invoice
        $randomString = bin2hex(random_bytes(4));
        $notifctions = json_encode(['volume' => false, 'time' => false]);
        $link_sub = $response['subscription_url'] ?? '';

        if (empty($link_sub) && !empty($response['configs'])) {
            $link_sub = is_array($response['configs']) ? implode("\n", $response['configs']) : $response['configs'];
        }

        $stmtInv = $pdo->prepare("INSERT IGNORE INTO invoice 
            (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status, notifctions, refral) 
            VALUES (:id_user, :id_invoice, :username, :time_sell, :Service_location, :name_product, :price_product, :Volume, :Service_time, :Status, :notifctions, :refral)");

        $stmtInv->execute([
            ':id_user' => $agent_id,
            ':id_invoice' => $randomString,
            ':username' => $username,
            ':time_sell' => time(),
            ':Service_location' => $location,
            ':name_product' => $product['name_product'],
            ':price_product' => $price,
            ':Volume' => $product['Volume_constraint'],
            ':Service_time' => $product['Service_time'],
            ':Status' => 'active',
            ':notifctions' => $notifctions,
            ':refral' => $agent_id
        ]);

        echo json_encode(['status' => 'success', 'message' => 'سرویس ساخته شد']);
    } else {
        // --- REFUND WALLET IF API FAILED ---
        if ($price > 0) {
            $stmtR = $pdo->prepare("UPDATE user SET Balance = Balance + :p WHERE id = :id");
            $stmtR->execute([':p' => $price, ':id' => $agent_id]);
        }
        echo json_encode(['status' => 'error', 'message' => 'خطا در ارتباط با پنل: ' . ($response['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'bulk_create_users') {
    if ($agentType !== 'n2' && $agentType !== 'all') {
        echo json_encode(['status' => 'error', 'message' => 'شما دسترسی ایجاد گروهی ندارید']);
        exit;
    }

    $location = $_POST['location'] ?? '';
    $product_id = $_POST['product_id'] ?? '';
    $count = (int)($_POST['count'] ?? 1);
    $prefix = trim($_POST['prefix'] ?? '');

    if (empty($location) || empty($product_id) || $count <= 0 || $count > 50) {
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ناقص یا تعداد نامعتبر است (حداکثر 50)']);
        exit;
    }

    // Check product access
    $loc_cond = getProductLocCondition($location);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :id AND (agent = :agent OR agent = 'all') AND $loc_cond LIMIT 1");
    $stmt->execute([':id' => $product_id, ':agent' => $agentType]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'پلن نامعتبر یا عدم دسترسی']);
        exit;
    }
    
    if ($product['name_product'] === 'سرویس تست') {
        echo json_encode(['status' => 'error', 'message' => 'ساخت گروهی برای اکانت تست مجاز نیست']);
        exit;
    }

    $unitPrice = (float)$product['price_product'];
    $totalPrice = $unitPrice * $count;

    if ($wallet - $totalPrice < $negativeCreditLimit) {
        echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما برای این تعداد کافی نیست']);
        exit;
    }

    // Fetch Panel Name
    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $location]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $location;

    // --- ATOMIC WALLET DEDUCTION FIRST ---
    if ($totalPrice > 0) {
        $stmtW = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND (Balance - :p) >= :limit");
        $stmtW->execute([':p' => $totalPrice, ':id' => $agent_id, ':limit' => $negativeCreditLimit]);
        if ($stmtW->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست یا تراکنش همزمان رخ داده است.']);
            exit;
        }
    }

    $Data_Config = [
        'expire' => $product['Service_time'] == 0 ? 0 : strtotime('+' . $product['Service_time'] . ' days'),
        'data_limit' => $product['Volume_constraint'] == 0 ? 0 : $product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $agent_id,
        'username' => 'AgentPanelBulk',
        'type' => 'buy'
    ];

    $successCount = 0;
    $failedCount = 0;
    $created_accounts = [];

    for ($i = 0; $i < $count; $i++) {
        $username = $prefix;
        if (empty($username)) {
            $username = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 5) . rand(1000, 9999);
        } else {
            $username .= '_' . substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 3) . rand(10, 99);
        }

        $response = $ManagePanel->createUser($name_panel, $product['code_product'], $username, $Data_Config);

        if (isset($response['status']) && $response['status'] === 'successful') {
            $randomString = bin2hex(random_bytes(4));
            $notifctions = json_encode(['volume' => false, 'time' => false]);
            $link_sub = $response['subscription_url'] ?? '';

            if (empty($link_sub) && !empty($response['configs'])) {
                $link_sub = is_array($response['configs']) ? implode("\n", $response['configs']) : $response['configs'];
            }

            $stmtInv = $pdo->prepare("INSERT IGNORE INTO invoice 
                (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status, notifctions, refral) 
                VALUES (:id_user, :id_invoice, :username, :time_sell, :Service_location, :name_product, :price_product, :Volume, :Service_time, :Status, :notifctions, :refral)");

            $stmtInv->execute([
                ':id_user' => $agent_id,
                ':id_invoice' => $randomString,
                ':username' => $username,
                ':time_sell' => time(),
                ':Service_location' => $location,
                ':name_product' => $product['name_product'],
                ':price_product' => $unitPrice,
                ':Volume' => $product['Volume_constraint'],
                ':Service_time' => $product['Service_time'],
                ':Status' => 'active',
                ':notifctions' => $notifctions,
                ':refral' => $agent_id
            ]);

            $successCount++;
            $created_accounts[] = $username;
        } else {
            $failedCount++;
        }
    }

    // Refund for failed creations
    if ($failedCount > 0 && $unitPrice > 0) {
        $refundAmount = $failedCount * $unitPrice;
        $stmtR = $pdo->prepare("UPDATE user SET Balance = Balance + :p WHERE id = :id");
        $stmtR->execute([':p' => $refundAmount, ':id' => $agent_id]);
    }

    echo json_encode([
        'status' => 'success', 
        'message' => "تعداد $successCount سرویس با موفقیت ساخته شد. (خطا: $failedCount)",
        'accounts' => $created_accounts
    ]);
    exit;
}

if ($action === 'renew_user') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    $product_id = $_POST['product_id'] ?? '';

    if (empty($invoice_id) || empty($product_id)) {
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    // Verify Invoice Ownership
    $stmtInv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :uid1 OR refral = :uid2) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid1' => $agent_id, ':uid2' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر یا عدم دسترسی']);
        exit;
    }

    // Check product access
    $loc_cond = getProductLocCondition($invoice['Service_location']);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :id AND (agent = :agent OR agent = 'all') AND $loc_cond LIMIT 1");
    $stmt->execute([':id' => $product_id, ':agent' => $agentType]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'پلن نامعتبر']);
        exit;
    }

    $price = (float)$product['price_product'];
    if ($wallet - $price < $negativeCreditLimit) {
        echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست (سقف اعتبار تکمیل است)']);
        exit;
    }

    // --- ATOMIC WALLET DEDUCTION FIRST ---
    if ($price > 0) {
        $stmtW = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND (Balance - :p) >= :limit");
        $stmtW->execute([':p' => $price, ':id' => $agent_id, ':limit' => $negativeCreditLimit]);
        if ($stmtW->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست یا تراکنش همزمان رخ داده است.']);
            exit;
        }
    }

    // Fetch Panel Name
    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $invoice['Service_location']]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $invoice['Service_location'];

    // Call Renew API
    $response = $ManagePanel->extend($invoice['Service_location'], $product['Volume_constraint'], $product['Service_time'], $invoice['username'], $product['code_product'], $name_panel);

    if (isset($response['status']) && $response['status'] === true) {

        // Update Invoice
        $stmtU = $pdo->prepare("UPDATE invoice SET Status = 'active', time_sell = :time_sell, price_product = :p, Volume = :v, Service_time = :st WHERE id_invoice = :id");
        $stmtU->execute([
            ':time_sell' => time(),
            ':p' => $price,
            ':v' => $product['Volume_constraint'],
            ':st' => $product['Service_time'],
            ':id' => $invoice_id
        ]);

        echo json_encode(['status' => 'success', 'message' => 'سرویس تمدید شد']);
    } else {
        // --- REFUND WALLET IF API FAILED ---
        if ($price > 0) {
            $stmtR = $pdo->prepare("UPDATE user SET Balance = Balance + :p WHERE id = :id");
            $stmtR->execute([':p' => $price, ':id' => $agent_id]);
        }
        echo json_encode(['status' => 'error', 'message' => 'خطا در ارتباط با پنل: ' . ($response['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'change_location') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    $new_location = $_POST['new_location'] ?? ''; // This is code_panel

    if (empty($invoice_id) || empty($new_location)) {
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    // Verify Invoice Ownership
    $stmtInv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :uid1 OR refral = :uid2) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid1' => $agent_id, ':uid2' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر یا عدم دسترسی']);
        exit;
    }

    $old_location = $invoice['Service_location'];

    if ($old_location === $new_location) {
        echo json_encode(['status' => 'error', 'message' => 'لوکیشن جدید با لوکیشن فعلی یکسان است']);
        exit;
    }

    // Fetch new panel info
    $stmtNewPnl = $pdo->prepare("SELECT name_panel, type FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtNewPnl->execute([':code' => $new_location]);
    $newPanelData = $stmtNewPnl->fetch(PDO::FETCH_ASSOC);
    if (!$newPanelData) {
        echo json_encode(['status' => 'error', 'message' => 'لوکیشن جدید نامعتبر است']);
        exit;
    }
    $new_name_panel = $newPanelData['name_panel'];

    // Fetch old panel info
    $stmtOldPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtOldPnl->execute([':code' => $old_location]);
    $oldPanelData = $stmtOldPnl->fetch(PDO::FETCH_ASSOC);
    $old_name_panel = $oldPanelData ? $oldPanelData['name_panel'] : $old_location;

    // Get current user data from old panel
    $userData = $ManagePanel->DataUser($old_name_panel, $invoice['username']);
    
    // Check if user actually exists on old panel and we got data
    if (isset($userData['status']) && $userData['status'] === 'Unsuccessful') {
        echo json_encode(['status' => 'error', 'message' => 'خطا در دریافت اطلاعات از لوکیشن مبدا: ' . ($userData['msg'] ?? '')]);
        exit;
    }

    $data_limit = $userData['data_limit'] ?? 0;
    $expire = $userData['expire'] ?? 0;

    // Get product code for new panel
    $name_product = $invoice['name_product'];
    $loc_cond = getProductLocCondition($new_name_panel);
    $stmtProd = $pdo->prepare("SELECT code_product FROM product WHERE name_product = :np AND (agent = :agent OR agent = 'all') AND $loc_cond LIMIT 1");
    $stmtProd->execute([':np' => $name_product, ':agent' => $agentType]);
    $newProd = $stmtProd->fetch(PDO::FETCH_ASSOC);
    
    $code_product = "customvolume"; // fallback
    if ($newProd) {
        $code_product = $newProd['code_product'];
    }

    $Data_Config = [
        'expire' => $expire,
        'data_limit' => $data_limit,
        'from_id' => $agent_id,
        'username' => 'AgentPanel',
        'type' => 'buy'
    ];

    // Create on new panel first
    $createRes = $ManagePanel->createUser($new_name_panel, $code_product, $invoice['username'], $Data_Config);

    if (isset($createRes['status']) && $createRes['status'] === 'successful') {
        // Remove from old panel
        $ManagePanel->RemoveUser($old_name_panel, $invoice['username']);
        
        // Update DB with new location
        $updateFields = ["Service_location = :loc"];
        $updateParams = [':loc' => $new_location, ':id' => $invoice_id];

        if (isset($createRes['user_info'])) {
            $updateFields[] = "user_info = :uinfo";
            $updateParams[':uinfo'] = $createRes['user_info'];
        } elseif (isset($createRes['subscription_url']) && in_array($newPanelData['type'], ['Manualsale', 'ibsng', 'mikrotik'])) {
            $updateFields[] = "user_info = :uinfo";
            $updateParams[':uinfo'] = $createRes['subscription_url'];
        }

        if (isset($createRes['sub_id'])) {
            $updateFields[] = "sub_id = :subid";
            $updateParams[':subid'] = $createRes['sub_id'];
        }

        if (isset($createRes['subscription_url']) && !in_array($newPanelData['type'], ['Manualsale', 'ibsng', 'mikrotik'])) {
            $updateFields[] = "sub_link = :sublink";
            $updateParams[':sublink'] = $createRes['subscription_url'];
        }

        $setQuery = implode(", ", $updateFields);
        $stmtU = $pdo->prepare("UPDATE invoice SET $setQuery WHERE id_invoice = :id");
        $stmtU->execute($updateParams);

        echo json_encode(['status' => 'success', 'message' => 'لوکیشن با موفقیت تغییر کرد']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'خطا در ساخت کاربر در لوکیشن جدید: ' . ($createRes['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'delete_user') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    if (empty($invoice_id)) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر']);
        exit;
    }

    // Verify Invoice
    $stmtInv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :uid1 OR refral = :uid2) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid1' => $agent_id, ':uid2' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // Fetch Panel Name
    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $invoice['Service_location']]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $invoice['Service_location'];

    // Call Delete API
    $response = $ManagePanel->RemoveUser($name_panel, $invoice['username']);

    if (isset($response['status']) && $response['status'] === true) {
        // Delete or mark inactive
        $stmtD = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :id");
        $stmtD->execute([':id' => $invoice_id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'خطا در حذف: ' . ($response['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'get_link') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    
    $stmtInv = $pdo->prepare("SELECT username, Service_location FROM invoice WHERE id_invoice = :id AND (id_user = :uid1 OR refral = :uid2) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid1' => $agent_id, ':uid2' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر']);
        exit;
    }

    $link = '';
    
    // Fetch Panel Name
    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $invoice['Service_location']]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $invoice['Service_location'];

    $res = $ManagePanel->DataUser($name_panel, $invoice['username']);
    if (isset($res['status']) && $res['status'] !== 'Unsuccessful') {
        $link = $res['subscription_url'] ?? '';
        
        // FIX FOR WGDashboard: Often the "subscription_url" actually contains the raw WireGuard .conf content
        if (!empty($link) && (stripos(trim($link), '[Interface]') === 0 || stripos(trim($link), 'PrivateKey') !== false)) {
            $res['links'] = array_merge((array)($res['links'] ?? []), [$link]);
            $link = '';
        }

        if (empty($link) && !empty($res['configs'])) {
            $link = is_array($res['configs']) ? implode("\n", $res['configs']) : $res['configs'];
        }
        if (empty($link) && !empty($res['links'])) {
            $link = is_array($res['links']) ? implode("\n", $res['links']) : $res['links'];
        }
    }

    echo json_encode(['status' => 'success', 'link' => $link]);
    exit;
}

if ($action === 'change_status') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    if (empty($invoice_id)) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر']);
        exit;
    }

    $stmtInv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :uid1 OR refral = :uid2) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid1' => $agent_id, ':uid2' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $invoice['Service_location']]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $invoice['Service_location'];

    $response = $ManagePanel->Change_status($invoice['username'], $name_panel);

    if (isset($response['status']) && $response['status'] === 'successful') {
        $newStatus = ($invoice['Status'] === 'active') ? 'disabled' : 'active';
        $stmtU = $pdo->prepare("UPDATE invoice SET Status = :st WHERE id_invoice = :id");
        $stmtU->execute([':st' => $newStatus, ':id' => $invoice_id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'خطا در تغییر وضعیت: ' . ($response['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'edit_remark') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    $new_remark = $_POST['remark'] ?? '';

    if (empty($invoice_id)) {
        echo json_encode(['status' => 'error', 'message' => 'شناسه نامعتبر']);
        exit;
    }

    $stmtInv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :uid1 OR refral = :uid2) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid1' => $agent_id, ':uid2' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmtUpdate = $pdo->prepare("UPDATE invoice SET note = :note WHERE id_invoice = :id");
    $stmtUpdate->execute([':note' => $new_remark, ':id' => $invoice_id]);

    echo json_encode(['status' => 'success', 'message' => 'توضیحات با موفقیت ثبت شد']);
    exit;
}

if ($action === 'transfer_balance') {
    if ($agentUser['agent'] !== 'n2' && $agentUser['agent'] !== 'all') {
        echo json_encode(['status' => 'error', 'message' => 'شما دسترسی انتقال شارژ را ندارید.']);
        exit;
    }
    
    $target_id = $_POST['target_id'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $amount = intval($amount);
    
    if (empty($target_id) || $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'آیدی مقصد و مبلغ معتبر نیست.']);
        exit;
    }
    
    if ($target_id == $agent_id) {
        echo json_encode(['status' => 'error', 'message' => 'امکان انتقال به خود وجود ندارد.']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        $stmtSrc = $pdo->prepare("SELECT Balance FROM user WHERE id = :id FOR UPDATE");
        $stmtSrc->execute([':id' => $agent_id]);
        $srcUser = $stmtSrc->fetch(PDO::FETCH_ASSOC);
        
        if (!$srcUser || $srcUser['Balance'] < $amount) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'موجودی شما کافی نیست.']);
            exit;
        }
        
        $stmtDst = $pdo->prepare("SELECT id, Balance FROM user WHERE id = :id FOR UPDATE");
        $stmtDst->execute([':id' => $target_id]);
        $dstUser = $stmtDst->fetch(PDO::FETCH_ASSOC);
        
        if (!$dstUser) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'کاربر مقصد یافت نشد.']);
            exit;
        }
        
        $pdo->prepare("UPDATE user SET Balance = Balance - :amt WHERE id = :id")->execute([':amt' => $amount, ':id' => $agent_id]);
        $pdo->prepare("UPDATE user SET Balance = Balance + :amt WHERE id = :id")->execute([':amt' => $amount, ':id' => $target_id]);
        
        $time = time();
        $descSrc = "کاهش موجودی بابت انتقال شارژ به همکار (آیدی: $target_id)";
        $pdo->prepare("INSERT INTO Payment_report (user_id, Payment_Method, payment_Status, price, time, Token) VALUES (?, 'transfer_out', 'paid', ?, ?, ?)")->execute([$agent_id, $amount, $time, $descSrc]);
        
        $descDst = "افزایش موجودی از طرف همکار (آیدی: $agent_id)";
        $pdo->prepare("INSERT INTO Payment_report (user_id, Payment_Method, payment_Status, price, time, Token) VALUES (?, 'transfer_in', 'paid', ?, ?, ?)")->execute([$target_id, $amount, $time, $descDst]);
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'مبلغ با موفقیت انتقال یافت.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'خطا در انجام تراکنش: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'reset_traffic') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    if (empty($invoice_id)) {
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    // Verify Invoice Ownership
    $stmtInv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :uid1 OR refral = :uid2) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid1' => $agent_id, ':uid2' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر یا عدم دسترسی']);
        exit;
    }

    // Get Reset Price from shopSetting
    $stmtPrice = $pdo->prepare("SELECT value FROM shopSetting WHERE Namevalue = 'price_reset_agent' LIMIT 1");
    $stmtPrice->execute();
    $priceRow = $stmtPrice->fetch(PDO::FETCH_ASSOC);
    $price = $priceRow ? (float)$priceRow['value'] : 5000.0;

    if ($wallet - $price < $negativeCreditLimit) {
        echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست (سقف اعتبار تکمیل است)']);
        exit;
    }

    // --- ATOMIC WALLET DEDUCTION FIRST ---
    if ($price > 0) {
        $stmtW = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND (Balance - :p) >= :limit");
        $stmtW->execute([':p' => $price, ':id' => $agent_id, ':limit' => $negativeCreditLimit]);
        if ($stmtW->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست یا تراکنش همزمان رخ داده است.']);
            exit;
        }
    }

    // Fetch Panel Name
    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $invoice['Service_location']]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $invoice['Service_location'];

    // Call Reset Traffic API
    $response = $ManagePanel->ResetUserDataUsage($invoice['username'], $name_panel);

    if (isset($response['status']) && $response['status'] === true) {
        // Log transaction in Payment_report
        $time = time();
        $desc = "ریست ترافیک سرویس " . $invoice['username'] . " (فاکتور: " . $invoice_id . ")";
        $stmtPay = $pdo->prepare("INSERT INTO Payment_report (user_id, Payment_Method, payment_Status, price, time, Token) VALUES (?, 'reset_traffic', 'paid', ?, ?, ?)");
        $stmtPay->execute([$agent_id, $price, $time, $desc]);

        echo json_encode(['status' => 'success', 'message' => 'ترافیک سرویس ریست شد']);
    } else {
        // --- REFUND WALLET IF API FAILED ---
        if ($price > 0) {
            $stmtR = $pdo->prepare("UPDATE user SET Balance = Balance + :p WHERE id = :id");
            $stmtR->execute([':p' => $price, ':id' => $agent_id]);
        }
        echo json_encode(['status' => 'error', 'message' => 'خطا در ارتباط با پنل: ' . ($response['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'add_volume') {
    if ($agentType !== 'n2' && $agentType !== 'all') {
        echo json_encode(['status' => 'error', 'message' => 'شما دسترسی افزایش حجم ندارید']);
        exit;
    }

    $invoice_id = $_POST['invoice_id'] ?? '';
    $volume_gb = intval($_POST['volume_gb'] ?? 0);

    if (empty($invoice_id) || $volume_gb <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    // Verify Invoice Ownership
    $stmtInv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :uid1 OR refral = :uid2) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid1' => $agent_id, ':uid2' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر یا عدم دسترسی']);
        exit;
    }

    // Fetch Panel details
    $stmtPnl = $pdo->prepare("SELECT * FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $invoice['Service_location']]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);

    $price_per_gb = 4000.0; // Default fallback
    if ($panelData && !empty($panelData['pricecustomvolume'])) {
        $prices = json_decode($panelData['pricecustomvolume'], true);
        if (isset($prices[$agentType])) {
            $price_per_gb = (float)$prices[$agentType];
        }
    } else {
        // Try shopSetting fallback
        $optName = 'customvolme' . $agentType; // e.g., customvolmen2
        $stmtOpt = $pdo->prepare("SELECT value FROM shopSetting WHERE Namevalue = :name LIMIT 1");
        $stmtOpt->execute([':name' => $optName]);
        $optRow = $stmtOpt->fetch(PDO::FETCH_ASSOC);
        if ($optRow) {
            $price_per_gb = (float)$optRow['value'];
        }
    }

    $price = $volume_gb * $price_per_gb;

    if ($wallet - $price < $negativeCreditLimit) {
        echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست (سقف اعتبار تکمیل است)']);
        exit;
    }

    // --- ATOMIC WALLET DEDUCTION FIRST ---
    if ($price > 0) {
        $stmtW = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND (Balance - :p) >= :limit");
        $stmtW->execute([':p' => $price, ':id' => $agent_id, ':limit' => $negativeCreditLimit]);
        if ($stmtW->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست یا تراکنش همزمان رخ داده است.']);
            exit;
        }
    }

    // Call extra_volume API
    $response = $ManagePanel->extra_volume($invoice['username'], $invoice['Service_location'], $volume_gb);

    if (isset($response['status']) && $response['status'] === true) {
        // Update database invoice Volume
        $new_total_volume = (float)$invoice['Volume'] + $volume_gb;
        $stmtU = $pdo->prepare("UPDATE invoice SET Volume = :v WHERE id_invoice = :id");
        $stmtU->execute([':v' => $new_total_volume, ':id' => $invoice_id]);

        // Log transaction in Payment_report
        $time = time();
        $desc = "افزایش حجم سرویس " . $invoice['username'] . " به میزان " . $volume_gb . " گیگابایت (فاکتور: " . $invoice_id . ")";
        $stmtPay = $pdo->prepare("INSERT INTO Payment_report (user_id, Payment_Method, payment_Status, price, time, Token) VALUES (?, 'add_volume', 'paid', ?, ?, ?)");
        $stmtPay->execute([$agent_id, $price, $time, $desc]);

        echo json_encode(['status' => 'success', 'message' => 'حجم سرویس با موفقیت افزایش یافت']);
    } else {
        // --- REFUND WALLET IF API FAILED ---
        if ($price > 0) {
            $stmtR = $pdo->prepare("UPDATE user SET Balance = Balance + :p WHERE id = :id");
            $stmtR->execute([':p' => $price, ':id' => $agent_id]);
        }
        echo json_encode(['status' => 'error', 'message' => 'خطا در ارتباط با پنل: ' . ($response['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'get_payment_gateways') {
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $nowPayment = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $carttocart = getPaySettingValue('carttocartstatus', 'offcarttocart');
    
    // Also fetch card number if cart to cart is enabled
    $cardNumber = '';
    $cardName = '';
    if ($carttocart === 'oncarttocart') {
        $card_info = json_decode(getPaySettingValue('carttocart'), true);
        if ($card_info) {
            $cardNumber = $card_info['cart'];
            $cardName = $card_info['namecard'];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'zarinpal' => $zarinpal === 'onzarinpal',
            'nowpayments' => $nowPayment === 'onnowpayment',
            'carttocart' => $carttocart === 'oncarttocart',
            'card_number' => $cardNumber,
            'card_name' => $cardName
        ]
    ]);
    exit;
}

if ($action === 'charge_wallet_request') {
    $amount = (int)($_POST['amount'] ?? 0);
    $gateway = $_POST['gateway'] ?? '';
    
    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'مبلغ نامعتبر است']);
        exit;
    }
    
    // Limits check
    $minBalance = 0;
    $maxBalance = 9999999999;
    
    if ($gateway === 'zarinpal') {
        if (getPaySettingValue('zarinpalstatus') !== 'onzarinpal') {
            echo json_encode(['status' => 'error', 'message' => 'درگاه زرین پال غیرفعال است']);
            exit;
        }
        $minBalance = (int)getPaySettingValue('minbalancezarinpal', 0);
        $maxBalance = (int)getPaySettingValue('maxbalancezarinpal', 9999999999);
    } elseif ($gateway === 'nowpayments') {
        if (getPaySettingValue('nowpaymentstatus') !== 'onnowpayment') {
            echo json_encode(['status' => 'error', 'message' => 'درگاه NowPayments غیرفعال است']);
            exit;
        }
        $minBalance = (int)getPaySettingValue('minbalancenowpayment', 0);
        $maxBalance = (int)getPaySettingValue('maxbalancenowpayment', 9999999999);
    } elseif ($gateway === 'cart_to_cart') {
        if (getPaySettingValue('carttocartstatus') !== 'oncarttocart') {
            echo json_encode(['status' => 'error', 'message' => 'کارت به کارت غیرفعال است']);
            exit;
        }
        $minBalance = (int)getPaySettingValue('minbalancecart', 0);
        $maxBalance = (int)getPaySettingValue('maxbalancecart', 9999999999);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'درگاه نامعتبر است']);
        exit;
    }
    
    if ($amount < $minBalance) {
        echo json_encode(['status' => 'error', 'message' => "حداقل مبلغ شارژ " . number_format($minBalance) . " تومان است"]);
        exit;
    }
    if ($amount > $maxBalance) {
        echo json_encode(['status' => 'error', 'message' => "حداکثر مبلغ شارژ " . number_format($maxBalance) . " تومان است"]);
        exit;
    }
    
    $randomString = bin2hex(random_bytes(5));
    $dateacc = date('Y/m/d H:i:s');
    $invoice = 'charge_wallet|webpanel';
    
    if ($gateway === 'zarinpal') {
        $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "zarinpal";
        $stmt->execute([$agent_id, $randomString, $dateacc, $amount, $payment_Status, $Payment_Method, $invoice]);
        
        $pay = createPayZarinpal($amount, $randomString);
        if ($pay['data']['code'] == 100) {
            echo json_encode(['status' => 'success', 'redirect' => 'https://www.zarinpal.com/pg/StartPay/' . $pay['data']['authority']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'خطا در ارتباط با زرین‌پال']);
        }
        exit;
    } elseif ($gateway === 'nowpayments') {
        $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "nowpayment";
        $stmt->execute([$agent_id, $randomString, $dateacc, $amount, $payment_Status, $Payment_Method, $invoice]);
        
        $arze_rate = rate_arze();
        $amount_usd = $amount / $arze_rate['USD'];
        
        $pay = nowPayments('invoice', $amount_usd, $randomString, "Charge Wallet $agent_id");
        if (isset($pay['invoice_url'])) {
            echo json_encode(['status' => 'success', 'redirect' => $pay['invoice_url']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'خطا در ارتباط با NowPayments']);
        }
        exit;
    } elseif ($gateway === 'cart_to_cart') {
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'لطفاً تصویر رسید را آپلود کنید']);
            exit;
        }
        
        $tmpName = $_FILES['receipt']['tmp_name'];
        $photoid = new CURLFile($tmpName);
        
        $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,at_updated) VALUES (?,?,?,?,?,?,?,?)");
        $payment_Status = "waiting";
        $Payment_Method = "cart to cart";
        $stmt->execute([$agent_id, $randomString, $dateacc, $amount, $payment_Status, $Payment_Method, $invoice, $dateacc]);
        
        $stmtAdmins = $pdo->prepare("SELECT id_admin, rule FROM admin");
        $stmtAdmins->execute();
        $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
        
        $caption = "رسید شارژ حساب کاربری از پنل وب\nآیدی کاربر: $agent_id\nمبلغ: " . number_format($amount) . " تومان\nشماره سفارش: $randomString";
        
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید پرداخت', 'callback_data' => "Confirm_pay_{$randomString}"],
                    ['text' => '❌ رد پرداخت', 'callback_data' => "reject_pay_{$randomString}"],
                ],
                [
                    ['text' => '➕ تایید و افزایش موجودی کاربر', 'callback_data' => "addbalamceuser_{$randomString}"],
                    ['text' => '🚫 مسدود کاربر فیک', 'callback_data' => "blockuserfake_{$agent_id}"],
                ]
            ]
        ]);
        
        foreach ($admins as $admin) {
            if ($admin['rule'] == "support") continue;
            $id_admin = $admin['id_admin'];
            
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'caption' => 'عکس رسید ارسالی کاربر:',
                'parse_mode' => "HTML",
            ]);
            
            $res_msg = sendmessage($id_admin, $caption, $Confirm_pay, 'HTML');
            if (isset($res_msg['ok']) && $res_msg['ok'] && isset($res_msg['result']['message_id'])) {
                $msg_id = $res_msg['result']['message_id'];
                $sql_insert = "INSERT INTO admin_payment_messages (id_order, admin_id, message_id) VALUES (?, ?, ?)";
                try {
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $stmt_insert->execute([$randomString, $id_admin, $msg_id]);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Base table or view not found') !== false || strpos($e->getMessage(), 'doesn\'t exist') !== false) {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_payment_messages (
                            id INT(11) AUTO_INCREMENT PRIMARY KEY,
                            id_order VARCHAR(255) NOT NULL,
                            admin_id VARCHAR(255) NOT NULL,
                            message_id VARCHAR(255) NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                        $stmt_insert = $pdo->prepare($sql_insert);
                        $stmt_insert->execute([$randomString, $id_admin, $msg_id]);
                    }
                }
            }
        }
        
        echo json_encode(['status' => 'success', 'message' => 'رسید با موفقیت ارسال شد و پس از تایید ادمین، حساب شما شارژ خواهد شد.']);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'عملیات نامعتبر']);
