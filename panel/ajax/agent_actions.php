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
    if ($wallet < $price) {
        echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست']);
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
        $stmtW = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND Balance >= :p");
        $stmtW->execute([':p' => $price, ':id' => $agent_id]);
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
    if ($wallet < $price) {
        echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست']);
        exit;
    }

    // --- ATOMIC WALLET DEDUCTION FIRST ---
    if ($price > 0) {
        $stmtW = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND Balance >= :p");
        $stmtW->execute([':p' => $price, ':id' => $agent_id]);
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

echo json_encode(['status' => 'error', 'message' => 'عملیات نامعتبر']);
