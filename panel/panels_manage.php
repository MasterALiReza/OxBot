<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../panels.php'; // For ManagePanel
require_auth();

// Ensure sanaei_group column exists safely
try {
    $colCheck = $pdo->query("SELECT sanaei_group FROM marzban_panel LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE marzban_panel ADD COLUMN sanaei_group VARCHAR(255) DEFAULT ''");
    } catch (Exception $ex) {}
}

// Ensure panel_category_id column exists safely
try {
    $pdo->query("SELECT panel_category_id FROM marzban_panel LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE marzban_panel ADD COLUMN panel_category_id VARCHAR(50) NULL");
    } catch (Exception $ex) {}
}

// Ensure custom_sub_domain column exists safely
try {
    $pdo->query("SELECT custom_sub_domain FROM marzban_panel LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE marzban_panel ADD COLUMN custom_sub_domain VARCHAR(255) DEFAULT ''");
    } catch (Exception $ex) {}
}

// Fetch Panel Categories
try {
    $panel_categories = db_fetchAll($pdo, "SELECT * FROM panel_category WHERE status = 'active' ORDER BY name ASC");
} catch (Exception $e) {
    $panel_categories = [];
}

// TEST CONNECTION AJAX
if (isset($_GET['action']) && $_GET['action'] === 'test_connection' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    try {
        $panel = db_fetch($pdo, "SELECT * FROM marzban_panel WHERE id = ?", [$id]);
        if (!$panel) {
            echo json_encode(['success' => false, 'message' => 'پنل یافت نشد.']);
            exit;
        }
        
        $mp = new ManagePanel();
        $res = $mp->DataUser($panel['name_panel'], 'mirza_test_connection_fake_user');
        
        $msg = $res['msg'] ?? '';
        $msgLower = strtolower($msg);
        
        if ($res['status'] === 'Unsuccessful' && (
            strpos($msgLower, 'not found') !== false || 
            strpos($msgLower, 'object invalid') !== false ||
            strpos($msgLower, 'unsuccessful') !== false ||
            empty($msg)
        )) {
            echo json_encode(['success' => true, 'message' => 'اتصال با موفقیت برقرار شد. پنل در دسترس است.']);
        } elseif ($res['status'] === 'Unsuccessful') {
            echo json_encode(['success' => false, 'message' => 'خطا در اتصال: ' . $msg]);
        } else {
            echo json_encode(['success' => true, 'message' => 'اتصال با موفقیت برقرار شد.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطای سیستمی: ' . $e->getMessage()]);
    }
    exit;
}

// HANDLE ADD / EDIT POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_check_post();
    $action = $_POST['action'];
    $name_panel = trim($_POST['name_panel'] ?? '');
    $url_panel = trim($_POST['url_panel'] ?? '');
    $username_panel = trim($_POST['username_panel'] ?? '');
    $password_panel = trim($_POST['password_panel'] ?? '');
    $type = trim($_POST['type'] ?? 'marzban');
    $status = trim($_POST['status'] ?? 'active');
    $agent = trim($_POST['agent'] ?? 'all');
    $custom_sub_domain = trim($_POST['custom_sub_domain'] ?? '');
    
    $inboundid = trim($_POST['inboundid'] ?? '');
    if (empty($inboundid) || $inboundid === '1') {
        if ($type === 'WGDashboard') {
            $inboundid = 'wg0';
        } else {
            $inboundid = '1';
        }
    }
    $sanaei_group = trim($_POST['sanaei_group'] ?? '');
    $inboundstatus = trim($_POST['inboundstatus'] ?? 'offinbounddisable');
    $inbound_deactive = trim($_POST['inbound_deactive'] ?? '0');
    $conecton = trim($_POST['conecton'] ?? 'offconecton');
    
    $MethodUsername = trim($_POST['MethodUsername'] ?? $textbotlang['keyboard']['numericIdRandom']);
    $namecustom = trim($_POST['namecustom'] ?? 'vpn');
    $limit_panel = trim($_POST['limit_panel'] ?? 'unlimited');
    $sublink = trim($_POST['sublink'] ?? 'onsublink');
    $config = trim($_POST['config'] ?? 'offconfig');
    $qr_wgd = trim($_POST['qr_wgd'] ?? 'offqrwgd');
    
    $Methodextend = trim($_POST['Methodextend'] ?? $textbotlang['keyboard']['resetVolumeTime']);
    $status_extend = trim($_POST['status_extend'] ?? 'on_extend');
    $TestAccount = trim($_POST['TestAccount'] ?? 'ONTestAccount');
    $val_usertest = trim($_POST['val_usertest'] ?? '100');
    $time_usertest = trim($_POST['time_usertest'] ?? '1');
    $on_hold_test = trim($_POST['on_hold_test'] ?? '1');
    
    $changeloc = trim($_POST['changeloc'] ?? 'offchangeloc');
    $subvip = trim($_POST['subvip'] ?? 'offsubvip');
    
    $sanitizeTierInput = function($key, $default = "0") {
        $f = trim($_POST[$key . '_f'] ?? '');
        $n = trim($_POST[$key . '_n'] ?? '');
        $n2 = trim($_POST[$key . '_n2'] ?? '');
        return json_encode([
            'f' => ($f !== '' && is_numeric($f)) ? $f : $default,
            'n' => ($n !== '' && is_numeric($n)) ? $n : $default,
            'n2' => ($n2 !== '' && is_numeric($n2)) ? $n2 : $default
        ]);
    };
    
    $mainvolume = $sanitizeTierInput('mainvolume', "1");
    $maxvolume = $sanitizeTierInput('maxvolume', "1000");
    $maintime = $sanitizeTierInput('maintime', "1");
    $maxtime = $sanitizeTierInput('maxtime', "365");
    $customvolume = $sanitizeTierInput('customvolume', "0");
    
    $priceextravolume = $sanitizeTierInput('priceextravolume', "4000");
    $pricecustomvolume = $sanitizeTierInput('pricecustomvolume', "4000");
    $pricecustomtime = $sanitizeTierInput('pricecustomtime', "4000");
    $priceextratime = $sanitizeTierInput('priceextratime', "4000");
    
    $priceChangeloc = trim($_POST['priceChangeloc'] ?? '0');
    if (!is_numeric($priceChangeloc)) $priceChangeloc = '0';
    
    $panel_category_id = trim($_POST['panel_category_id'] ?? '');
    if ($panel_category_id === '') $panel_category_id = null;

    if ($action === 'add') {
        if (empty($name_panel) || empty($url_panel)) {
            flash('error', 'وارد کردن نام پنل و آدرس پنل الزامی است.');
            header('Location: panels_manage.php');
            exit;
        }
        try {
            // Generate code_panel
            $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(code_panel, 3) AS UNSIGNED)) as max_num FROM marzban_panel WHERE code_panel LIKE '7e%'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $max_num = $row ? (int)$row['max_num'] : 0;
            $code_panel = '7e' . ($max_num + 1);

            db_query($pdo, "INSERT INTO marzban_panel 
                (name_panel, url_panel, username_panel, password_panel, type, status, code_panel, MethodUsername, inboundstatus, inbound_deactive, agent, inboundid, conecton, Methodextend, namecustom, limit_panel, TestAccount, sublink, config, qr_wgd, version_panel, on_hold_test, subvip, changeloc, status_extend, priceChangeloc, sanaei_group, mainvolume, maxvolume, maintime, maxtime, customvolume, priceextravolume, pricecustomvolume, pricecustomtime, priceextratime, val_usertest, time_usertest, panel_category_id, custom_sub_domain) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                [
                    $name_panel, $url_panel, $username_panel, $password_panel, $type, $status, $code_panel,
                    $MethodUsername, $inboundstatus, $inbound_deactive, $agent, $inboundid, $conecton, $Methodextend,
                    $namecustom, $limit_panel, $TestAccount, $sublink, $config, $qr_wgd, $on_hold_test, $subvip, $changeloc,
                    $status_extend, $priceChangeloc, $sanaei_group, $mainvolume, $maxvolume, $maintime, $maxtime,
                    $customvolume, $priceextravolume, $pricecustomvolume, $pricecustomtime, $priceextratime, $val_usertest, $time_usertest, $panel_category_id, $custom_sub_domain
                ]
            );
            flash('success', 'پنل جدید با موفقیت اضافه شد.');
        } catch (Exception $e) {
            flash('error', 'خطا در افزودن پنل: ' . $e->getMessage());
        }
        header('Location: panels_manage.php');
        exit;
    } elseif ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if (empty($name_panel) || empty($url_panel)) {
            flash('error', 'وارد کردن نام پنل و آدرس پنل الزامی است.');
            header('Location: panels_manage.php');
            exit;
        }
        try {
            $stmt_old = $pdo->prepare("SELECT name_panel, password_panel FROM marzban_panel WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_panel = $stmt_old->fetch(PDO::FETCH_ASSOC);
            $old_name_panel = $old_panel['name_panel'] ?? '';
            
            if (empty($password_panel)) {
                $password_panel = $old_panel['password_panel'] ?? '';
            }

            db_query($pdo, "UPDATE marzban_panel SET 
                name_panel = ?, url_panel = ?, username_panel = ?, password_panel = ?, type = ?, status = ?, agent = ?, 
                MethodUsername = ?, inboundstatus = ?, inbound_deactive = ?, inboundid = ?, conecton = ?, Methodextend = ?,
                namecustom = ?, limit_panel = ?, TestAccount = ?, sublink = ?, config = ?, qr_wgd = ?, on_hold_test = ?, subvip = ?,
                changeloc = ?, status_extend = ?, priceChangeloc = ?, sanaei_group = ?, mainvolume = ?, maxvolume = ?,
                maintime = ?, maxtime = ?, customvolume = ?, priceextravolume = ?, pricecustomvolume = ?, pricecustomtime = ?,
                priceextratime = ?, val_usertest = ?, time_usertest = ?, panel_category_id = ?, custom_sub_domain = ?
                WHERE id = ?",
                [
                    $name_panel, $url_panel, $username_panel, $password_panel, $type, $status, $agent,
                    $MethodUsername, $inboundstatus, $inbound_deactive, $inboundid, $conecton, $Methodextend,
                    $namecustom, $limit_panel, $TestAccount, $sublink, $config, $qr_wgd, $on_hold_test, $subvip,
                    $changeloc, $status_extend, $priceChangeloc, $sanaei_group, $mainvolume, $maxvolume,
                    $maintime, $maxtime, $customvolume, $priceextravolume, $pricecustomvolume, $pricecustomtime,
                    $priceextratime, $val_usertest, $time_usertest, $panel_category_id, $custom_sub_domain, $id
                ]
            );
            
            if ($old_name_panel && $old_name_panel !== $name_panel) {
                db_query($pdo, "UPDATE invoice SET Service_location = ? WHERE Service_location = ?", [$name_panel, $old_name_panel]);
                db_query($pdo, "UPDATE product SET Location = ? WHERE Location = ?", [$name_panel, $old_name_panel]);
            }
            flash('success', 'پنل با موفقیت ویرایش شد.');
        } catch (Exception $e) {
            flash('error', 'خطا در ویرایش پنل: ' . $e->getMessage());
        }
        header('Location: panels_manage.php');
        exit;
    }
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    csrf_check_get();
    $id = (int)$_GET['id'];
    try {
        db_query($pdo, "DELETE FROM marzban_panel WHERE id = ?", [$id]);
        flash('success', "پنل با موفقیت حذف شد.");
    } catch (Exception $e) {
        flash('error', "خطا در حذف پنل.");
    }
    header('Location: panels_manage.php');
    exit;
}

try {
    $panels = db_fetchAll($pdo, "SELECT * FROM marzban_panel ORDER BY id ASC");
} catch (Exception $e) {
    $panels = [];
    error_log('panels_manage.php: ' . $e->getMessage());
}

$totalPanels = count($panels);
$activePanels = 0;
$marzbanPanels = 0;
$sanaeiPanels = 0;

foreach ($panels as $p) {
    if (($p['status'] ?? '') === 'active') $activePanels++;
    $t = $p['type'] ?? '';
    if ($t === 'marzban') $marzbanPanels++;
    if ($t === 'MHSanaei-3.2') $sanaeiPanels++;
}

$pageTitle = $textbotlang['panel']['panelsManageTitle'];
$pageLede = $textbotlang['panel']['panelsManageSubtitle'];
$activeNav = 'panels_manage';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="stats fade-up" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px;">
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-blue"><?= icon('layers', 20) ?></div>
            <div class="dash-card-title">کل پنل‌ها</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill neutral">Total</span></div>
            <div class="dash-card-value"><?= number_format($totalPanels) ?></div>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-emerald"><?= icon('check-circle', 20) ?></div>
            <div class="dash-card-title">پنل‌های فعال</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill success">Active</span></div>
            <div class="dash-card-value"><?= number_format($activePanels) ?></div>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-purple"><?= icon('server', 20) ?></div>
            <div class="dash-card-title">مرزبان / سنایی</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill panel-pill">Main Types</span></div>
            <div class="dash-card-value-flex"><span class="dash-card-value"><?= number_format($marzbanPanels) ?></span> <span class="dash-card-unit">/ <?= number_format($sanaeiPanels) ?></span></div>
        </div>
    </div>
</div>

<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title"><?= $textbotlang['panel']['panelsHeading'] ?> <small>(<?= count($panels) ?>)</small></div>
        </div>
        <div class="toolbar-end">
            <button class="btn btn-primary btn-sm" onclick="openPanelModal('add')">
                <?= icon('plus', 14) ?> <?= $textbotlang['panel']['panelsAddBtn'] ?>
            </button>
        </div>
    </div>

    <div class="tbl-wrap admin-panels-table-wrap">
        <table class="tbl-xl admin-panels-table">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th><?= $textbotlang['panel']['panelsColName'] ?></th>
                    <th><?= $textbotlang['panel']['panelsColUrl'] ?></th>
                    <th><?= $textbotlang['panel']['panelsColType'] ?></th>
                    <th><?= $textbotlang['panel']['panelsColStatus'] ?></th>
                    <th style="width:140px"><?= $textbotlang['panel']['panelsColActions'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($panels)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty">
                                <svg class="ill" viewBox="0 0 200 160" fill="none">
                                    <circle cx="100" cy="60" r="40" fill="var(--sf3)" />
                                    <path d="M62 105 Q100 88 138 105" stroke="var(--bds)" stroke-width="8" stroke-linecap="round" fill="none" />
                                </svg>
                                <p><?= $textbotlang['panel']['panelsNoData'] ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $i = 1;
                    foreach ($panels as $p):
                        $isActive = ($p['status'] ?? '') === 'active';
                        $type = $p['type'] ?? 'marzban';
                        $panelData = json_encode([
                            'id' => $p['id'],
                            'name_panel' => $p['name_panel'], 'url_panel' => $p['url_panel'],
                            'username_panel' => $p['username_panel'], 'password_panel' => $p['password_panel'],
                            'type' => $type, 'status' => $p['status'], 'agent' => $p['agent'],
                            'inboundid' => $p['inboundid'], 'sanaei_group' => $p['sanaei_group'],
                            'inboundstatus' => $p['inboundstatus'], 'inbound_deactive' => $p['inbound_deactive'],
                            'conecton' => $p['conecton'], 'MethodUsername' => $p['MethodUsername'],
                            'namecustom' => $p['namecustom'], 'limit_panel' => $p['limit_panel'],
                            'sublink' => $p['sublink'], 'config' => $p['config'], 'qr_wgd' => $p['qr_wgd'],
                            'Methodextend' => $p['Methodextend'], 'status_extend' => $p['status_extend'],
                            'TestAccount' => $p['TestAccount'], 'val_usertest' => $p['val_usertest'],
                            'time_usertest' => $p['time_usertest'], 'on_hold_test' => $p['on_hold_test'],
                            'changeloc' => $p['changeloc'], 'subvip' => $p['subvip'],
                            'mainvolume' => $p['mainvolume'], 'maxvolume' => $p['maxvolume'],
                            'maintime' => $p['maintime'], 'maxtime' => $p['maxtime'], 'customvolume' => $p['customvolume'],
                            'priceextravolume' => $p['priceextravolume'], 'pricecustomvolume' => $p['pricecustomvolume'],
                            'pricecustomtime' => $p['pricecustomtime'],
                            'priceextratime' => $p['priceextratime'],
                            'priceChangeloc' => $p['priceChangeloc'],
                            'panel_category_id' => $p['panel_category_id'],
                            'custom_sub_domain' => $p['custom_sub_domain']
                        ]);
                        ?>
                        <tr>
                            <td data-label="#" class="cf"><?= $i++ ?></td>
                            <td data-label="<?= $textbotlang['panel']['panelsColName'] ?>" class="cs" style="font-weight:600">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <?= icon('server', 18) ?>
                                    <?= htmlspecialchars($p['name_panel'] ?? '—') ?>
                                </div>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['panelsColUrl'] ?>">
                                <div style="display:flex;align-items:center;gap:6px">
                                    <span style="color:var(--ts)"><?= icon('link', 14) ?></span>
                                    <a href="<?= htmlspecialchars($p['url_panel'] ?? '#') ?>" target="_blank" style="color:var(--ac);text-decoration:none;display:inline-block;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:ltr;vertical-align:middle;font-size:0.9rem;">
                                        <?= htmlspecialchars($p['url_panel'] ?? '—') ?>
                                    </a>
                                </div>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['panelsColType'] ?>">
                                <div style="display:flex;align-items:center;gap:6px">
                                    <span style="color:var(--ts)"><?= icon('layers', 14) ?></span>
                                    <span class="tag tag-plain" style="text-transform:uppercase"><?= htmlspecialchars($type) ?></span>
                                </div>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['panelsColStatus'] ?>">
                                <?php if ($isActive): ?>
                                    <span class="tag tag-ok"><?= $textbotlang['panel']['panelsStatusActive'] ?></span>
                                <?php else: ?>
                                    <span class="tag tag-no"><?= $textbotlang['panel']['panelsStatusInactive'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['panelsColActions'] ?>">
                                <div style="display:flex;gap:4px">
                                    <button class="btn btn-ghost btn-sm btn-icon test-conn-btn" data-id="<?= $p['id'] ?>" title="<?= $textbotlang['panel']['panelsActionTest'] ?>">
                                        <?= icon('dashboard', 14) ?>
                                    </button>
                                    <button class="btn btn-ghost btn-sm btn-icon" data-panel="<?= htmlspecialchars($panelData, ENT_QUOTES, 'UTF-8') ?>" onclick="openPanelModal('edit', this)" title="ویرایش">
                                        <?= icon('edit', 14) ?>
                                    </button>
                                    <a href="?action=delete&id=<?= (int)$p['id'] ?>&_csrf=<?= csrf_token() ?>" class="btn btn-no btn-sm btn-icon" title="<?= $textbotlang['panel']['panelsActionDelete'] ?>" onclick="return confirm('<?= sprintf($textbotlang['panel']['panelsConfirmDelete'], htmlspecialchars($p['name_panel'] ?? '')) ?>')">
                                        <?= icon('block', 14) ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Panel Modal -->
<div id="panelModalVeil" class="modal-veil">
    <div class="modal" style="max-width:650px;width:95%">
        <div class="modal-head" style="border-bottom:none; padding-bottom:10px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="icon-glow" style="color:var(--ac);background:rgba(var(--ac-rgb, 59,130,246), 0.1);padding:8px;border-radius:12px;">
                    <?= icon('layers', 20) ?>
                </div>
                <h3 id="panelModalTitle" style="margin:0;font-size:1.2rem;font-weight:700;">مدیریت پنل</h3>
            </div>
            <button type="button" class="modal-x" onclick="closePanelModal()" style="background:var(--bg-sec);border-radius:50%;padding:6px;transition:all 0.3s;">
                <?= icon('x', 16) ?>
            </button>
        </div>
        
        <div class="tabs-nav-wrapper" style="padding:0 20px 15px; border-bottom:1px solid var(--bd); position:relative;">
            <div class="tabs-nav" style="display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none;">
                <button type="button" class="tab-btn active" onclick="switchTab('tab-general', this)" style="border-radius:10px;padding:8px 16px;white-space:nowrap;transition:all 0.3s;font-weight:600;display:flex;align-items:center;gap:6px;">
                    <?= icon('settings', 16) ?> اصلی
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('tab-inbounds', this)" style="border-radius:10px;padding:8px 16px;white-space:nowrap;transition:all 0.3s;font-weight:600;display:flex;align-items:center;gap:6px;">
                    <?= icon('link', 16) ?> اینباند
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('tab-users', this)" style="border-radius:10px;padding:8px 16px;white-space:nowrap;transition:all 0.3s;font-weight:600;display:flex;align-items:center;gap:6px;">
                    <?= icon('users', 16) ?> کاربران
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('tab-tests', this)" style="border-radius:10px;padding:8px 16px;white-space:nowrap;transition:all 0.3s;font-weight:600;display:flex;align-items:center;gap:6px;">
                    <?= icon('clock', 16) ?> تست و تمدید
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('tab-prices', this)" style="border-radius:10px;padding:8px 16px;white-space:nowrap;transition:all 0.3s;font-weight:600;display:flex;align-items:center;gap:6px;">
                    <?= icon('credit-card', 16) ?> قیمت‌ها
                </button>
            </div>
            <style>
                .tabs-nav::-webkit-scrollbar { display: none; }
                .tab-btn { background: var(--bg-sec); color: var(--ts); border: 1px solid transparent; cursor:pointer; }
                .tab-btn:hover { background: var(--bg); color: var(--fg); border-color: var(--bd); }
                .tab-btn.active { background: rgba(var(--ac-rgb, 59,130,246), 0.1) !important; color: var(--ac) !important; border-color: rgba(var(--ac-rgb, 59,130,246), 0.2) !important; }
                .field-group label { display:flex; align-items:center; gap:6px; font-weight:600; color:var(--fg); margin-bottom:8px; font-size:0.9rem; }
                .field-group .input { width: 100%; box-sizing: border-box; border-radius: 12px; background: var(--bg-sec); border: 1px solid var(--bd); padding: 10px 14px; font-family: inherit; font-size: 0.95rem; color: var(--fg); transition: all 0.3s; }
                .field-group .input:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(var(--ac-rgb, 59,130,246), 0.15); background: var(--bg); outline:none; }
                .field-group select.input { appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: left 12px center; background-size: 16px; padding-left: 36px; }
                @media (max-width: 600px) {
                    .field-grid { grid-template-columns: 1fr !important; }
                }
            </style>
        </div>

        <form method="post" action="panels_manage.php">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" id="panelAction" value="add">
            <input type="hidden" name="id" id="panelId" value="">
            
            <div class="modal-body" style="min-height:350px; max-height:55vh; overflow-y:auto; padding:20px;">
                
                <!-- TAB 1: GENERAL -->
                <div id="tab-general" class="tab-content active">
                    <div class="field-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                        <div class="field-group">
                            <label>نوع پنل</label>
                            <select name="type" id="panelType" class="input" required>
                                <option value="marzban">Marzban (مرزبان)</option>
                                <option value="marzneshin">Marzneshin (مرزنشین)</option>
                                <option value="MHSanaei-3.2">MHSanaei (سنایی)</option>
                                <option value="x-ui_single">X-UI (ایکس یو آی)</option>
                                <option value="alireza_single">Alireza (علیرضا)</option>
                                <option value="hiddify">Hiddify (هیدیفای)</option>
                                <option value="s_ui">S-UI (اس یو آی)</option>
                                <option value="WGDashboard">WGDashboard (وایرگارد)</option>
                                <option value="ibsng">IBSng</option>
                                <option value="mikrotik">Mikrotik</option>
                                <option value="Manualsale">Manual (دستی)</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>نام پنل</label>
                            <input type="text" name="name_panel" id="panelName" class="input" required placeholder="مثلا: سرور آلمان 1">
                        </div>
                        <div class="field-group">
                            <label>وضعیت اتصال</label>
                            <select name="status" id="panelStatus" class="input">
                                <option value="active">فعال</option>
                                <option value="deactive">غیرفعال</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>دسترسی نمایندگان</label>
                            <select name="agent" id="panelAgent" class="input">
                                <option value="all">همه</option>
                                <option value="f">فقط فروشنده عادی</option>
                                <option value="n">فقط نماینده (درصددهی)</option>
                                <option value="n2">فقط نماینده (درصددهی عمده)</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>دسته‌بندی پنل (اختیاری)</label>
                            <select name="panel_category_id" id="panelCategoryId" class="input">
                                <option value="">بدون دسته‌بندی</option>
                                <?php foreach ($panel_categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="field-group" style="margin-top:15px">
                        <label>آدرس پنل (URL)</label>
                        <input type="url" name="url_panel" id="panelUrl" class="input" required placeholder="https://panel.example.com:2053" style="direction:ltr;text-align:left;">
                    </div>
                    <div class="field-group" style="margin-top:15px">
                        <label>دامنه/پیشوند سفارشی سابسکریپشن (اختیاری)</label>
                        <input type="url" name="custom_sub_domain" id="panelCustomSubDomain" class="input" placeholder="https://sub.example.com/sub/" style="direction:ltr;text-align:left;">
                        <small style="color:var(--ts);font-size:11px;display:block;margin-top:4px;">در صورت مقداردهی، در لینک‌های ساب این آدرس به جای آدرس اصلی پنل جایگزین می‌شود.</small>
                    </div>
                    <div class="field-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:15px">
                        <div class="field-group">
                            <label>نام کاربری پنل</label>
                            <input type="text" name="username_panel" id="panelUsername" class="input" placeholder="admin" style="direction:ltr;text-align:left;">
                        </div>
                        <div class="field-group">
                            <label id="panelPasswordLabel">رمز عبور یا توکن API پنل</label>
                            <div id="changePasswordToggleContainer" style="display:none; align-items:center; gap:6px; margin-bottom:6px;">
                                <input type="checkbox" id="changePasswordToggle" onchange="togglePasswordEdit()" style="width:auto; margin:0; cursor:pointer;">
                                <label for="changePasswordToggle" style="font-size:12px; color:var(--ts); cursor:pointer; margin:0; font-weight:normal;">تغییر رمز عبور / توکن API</label>
                            </div>
                            <input type="password" name="password_panel" id="panelPassword" class="input" placeholder="••••••••" autocomplete="new-password" style="direction:ltr;text-align:left;">
                            <small id="panelPasswordTip" style="color:var(--ts);font-size:11px;display:block;margin-top:4px;">برای پنل ثنایی نسخه ۳.۲ به بالا می‌توانید به جای رمز عبور، توکن API (API Key) را وارد کنید.</small>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: INBOUNDS -->
                <div id="tab-inbounds" class="tab-content">
                    <div class="field-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                        <div class="field-group">
                            <label>وضعیت اینباندها (تک‌پورت)</label>
                            <select name="inboundstatus" id="panelInboundStatus" class="input">
                                <option value="oninbounddisable">فعال (On)</option>
                                <option value="offinbounddisable">غیرفعال (Off)</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>غیرفعال‌سازی پس از اتمام</label>
                            <select name="inbound_deactive" id="panelInboundDeactive" class="input">
                                <option value="1">بله</option>
                                <option value="0">خیر</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>اتصال خودکار ربات به پنل (API)</label>
                            <small style="color:var(--ts);font-size:11px;display:block;margin-bottom:6px;">برای آپدیت خودکار حجم و زمان کاربران، روشن کنید.</small>
                            <select name="conecton" id="panelConecton" class="input">
                                <option value="onconecton">روشن</option>
                                <option value="offconecton">خاموش</option>
                            </select>
                            <button type="button" onclick="testCurrentPanelConnection()" style="margin-top:6px; font-size:12px; padding:5px 12px; border:1px solid var(--ac); border-radius:6px; background:transparent; color:var(--ac); cursor:pointer; width:100%;">🔌 تست اتصال با اطلاعات فعلی</button>
                            <div id="inlineConnResult" style="display:none; margin-top:5px; font-size:12px; padding:5px 8px; border-radius:5px;"></div>
                        </div>
                        <div class="field-group sanaei-group">
                            <label>گروه‌بندی سنایی</label>
                            <input type="text" name="sanaei_group" id="panelSanaeiGroup" class="input" placeholder="VIP" style="direction:ltr;text-align:left;">
                        </div>
                    </div>

                    <div class="field-group inboundid-group" style="margin-top:15px">
                        <label>شناسه اینباند (Inbound ID)</label>
                        <div id="sanaeiInboundsFetcher" style="display:none; margin-bottom:10px; padding:10px; border:1px solid var(--border); border-radius:8px; background:var(--bg-sec);">
                            <div style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
                                <button type="button" class="btn btn-secondary" onclick="fetchSanaeiInbounds()" style="font-size:12px; padding:6px 12px; border:1px solid var(--border); border-radius:6px; background:var(--bg); cursor:pointer;">دریافت لیست اینباندها</button>
                                <span id="inboundsLoader" style="display:none; font-size:12px; color:var(--ts);">در حال دریافت...</span>
                            </div>
                            <div id="inboundsList" style="display:flex; flex-direction:column; gap:8px; max-height:150px; overflow-y:auto;">
                                <small style="color:var(--ts);font-size:11px;">برای نمایش اینباندها روی دکمه بالا کلیک کنید.</small>
                            </div>
                        </div>
                        <input type="text" name="inboundid" id="panelInboundId" class="input" placeholder="1,2,3" style="direction:ltr;text-align:left;" oninput="updateInboundCheckboxes()">
                        <small style="color:var(--ts);font-size:12px;">میتوانید چند شناسه را با کاما جدا کنید.</small>
                    </div>
                </div>

                <!-- TAB 3: USERS -->
                <div id="tab-users" class="tab-content">
                    <div class="field-group">
                        <label>روش ایجاد نام کاربری</label>
                        <select name="MethodUsername" id="panelMethodUsername" class="input">
                            <option value="<?= $textbotlang['keyboard']['numericIdRandom'] ?? 'numericIdRandom' ?>">آیدی عددی تصادفی</option>
                            <option value="<?= $textbotlang['users']['customusername'] ?? 'customusername' ?>">نام کاربری دلخواه</option>
                            <option value="<?= $textbotlang['keyboard']['customTextSequential'] ?? 'customTextSequential' ?>">متن دلخواه + عدد ترتیبی</option>
                            <option value="<?= $textbotlang['keyboard']['usernameSequential'] ?? 'usernameSequential' ?>">نام کاربری + عدد ترتیبی</option>
                            <option value="<?= $textbotlang['keyboard']['numericIdSequential'] ?? 'numericIdSequential' ?>">آیدی عددی ترتیبی</option>
                            <option value="<?= $textbotlang['keyboard']['agentCustomTextSequential'] ?? 'agentCustomTextSequential' ?>">متن دلخواه نماینده + عدد ترتیبی</option>
                        </select>
                    </div>
                    
                    <div class="field-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:15px">
                        <div class="field-group">
                            <label>پیشوند کاستوم</label>
                            <input type="text" name="namecustom" id="panelNamecustom" class="input" placeholder="vpn" style="direction:ltr;text-align:left;">
                        </div>
                        <div class="field-group">
                            <label>محدودیت IP</label>
                            <select name="limit_panel" id="panelLimitPanel" class="input">
                                <option value="unlimited">نامحدود</option>
                                <?php for($j=1; $j<=100; $j++): ?>
                                    <option value="<?= $j ?>"><?= $j ?> کاربره</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>نمایش ساب‌لینک</label>
                            <select name="sublink" id="panelSublink" class="input">
                                <option value="onsublink">بله (فقط ساب‌لینک)</option>
                                <option value="bothsubandconfig">ساب‌لینک + کانفیگ</option>
                                <option value="offsublink">خیر</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label id="panelConfigLabel">نمایش کانفیگ‌ها</label>
                            <select name="config" id="panelConfig" class="input">
                                <option value="onconfig">بله</option>
                                <option value="offconfig">خیر</option>
                            </select>
                        </div>
                        <div class="field-group" id="qrWgdContainer" style="display:none;">
                            <label>ارسال بارکد WGDashboard</label>
                            <select name="qr_wgd" id="panelQrWgd" class="input">
                                <option value="onqrwgd">بله</option>
                                <option value="offqrwgd">خیر</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>امکان تغییر لوکیشن</label>
                            <select name="changeloc" id="panelChangeloc" class="input">
                                <option value="onchangeloc">بله</option>
                                <option value="offchangeloc">خیر</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>وضعیت VIP کاربر</label>
                            <select name="subvip" id="panelSubvip" class="input">
                                <option value="onsubvip">فعال</option>
                                <option value="offsubvip">غیرفعال</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: TESTS & EXTENDS -->
                <div id="tab-tests" class="tab-content">
                    <div class="field-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                        <div class="field-group">
                            <label>روش تمدید</label>
                            <select name="Methodextend" id="panelMethodextend" class="input">
                                <option value="<?= $textbotlang['keyboard']['resetVolumeTime'] ?? 'reset' ?>">ریست حجم و زمان</option>
                                <option value="<?= $textbotlang['keyboard']['addVolumeTime'] ?? 'add' ?>">افزودن به حجم و زمان</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>قابلیت تمدید سرویس</label>
                            <select name="status_extend" id="panelStatusExtend" class="input">
                                <option value="on_extend">مجاز</option>
                                <option value="off_extend">غیرمجاز</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>اکانت تست</label>
                            <select name="TestAccount" id="panelTestAccount" class="input">
                                <option value="ONTestAccount">روشن</option>
                                <option value="OFFTestAccount">خاموش</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>تست در انتظار</label>
                            <select name="on_hold_test" id="panelOnHoldTest" class="input">
                                <option value="1">بله</option>
                                <option value="0">خیر</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>حجم اکانت تست (مگابایت)</label>
                            <input type="text" name="val_usertest" id="panelValUsertest" class="input" placeholder="100">
                        </div>
                        <div class="field-group">
                            <label>زمان اکانت تست (ساعت)</label>
                            <input type="text" name="time_usertest" id="panelTimeUsertest" class="input" placeholder="1">
                        </div>
                    </div>
                </div>

                <!-- TAB 5: PRICES & LIMITS -->
                <div id="tab-prices" class="tab-content">
                    <div class="prices-tab-intro" style="background:var(--bg-sec); border:1px solid var(--bd); border-radius:14px; padding:14px 18px; margin-bottom:20px; display:flex; align-items:center; gap:12px;">
                        <div class="icon-glow" style="color:var(--ac); background:rgba(var(--ac-rgb, 59,130,246),0.12); padding:10px; border-radius:12px; flex-shrink:0;">
                            <?= icon('credit-card', 20) ?>
                        </div>
                        <div>
                            <div style="font-weight:700; font-size:0.95rem; color:var(--fg); margin-bottom:2px;">تنظیمات قیمت و محدودیت‌های پنل</div>
                            <div style="font-size:0.82rem; color:var(--ts);">تمام مقادیر برای ۳ سطح فروشنده (عادی، نماینده درصددهی و عمده) قابل شخصی‌سازی است. روی علامت <b>!</b> نگه دارید یا تپ کنید تا راهنما را ببینید.</div>
                        </div>
                    </div>

                    <!-- GROUP 1: LIMITS & VOLUMES -->
                    <div class="price-category-card">
                        <div class="price-category-header">
                            <span class="price-category-icon">📦</span>
                            <span class="price-category-title">محدودیت‌ها و حجم / زمان اولیه</span>
                        </div>
                        
                        <div class="price-matrix-table">
                            <div class="price-matrix-head">
                                <div class="price-matrix-th-title">ویژگی / محدودیت</div>
                                <div class="price-matrix-th-tier">
                                    <span class="tier-tag tier-f">عادی (f)</span>
                                </div>
                                <div class="price-matrix-th-tier">
                                    <span class="tier-tag tier-n">درصددهی (n)</span>
                                </div>
                                <div class="price-matrix-th-tier">
                                    <span class="tier-tag tier-n2">عمده (n2)</span>
                                </div>
                            </div>

                            <?php 
                            $limitFields = [
                                ['id'=>'mainvolume', 'label'=>'حجم اصلی', 'unit'=>'GB', 'tooltip'=>'حجم اولیه و پیش‌فرض هنگام ساخت سرویس جدید در این پنل.'],
                                ['id'=>'maxvolume', 'label'=>'حداکثر حجم مجاز', 'unit'=>'GB', 'tooltip'=>'حداکثر حجمی که نمایندگان این سطح مجاز هستند برای کاربر تعیین کنند.'],
                                ['id'=>'maintime', 'label'=>'زمان اصلی', 'unit'=>'روز', 'tooltip'=>'مدت زمان اولیه و پیش‌فرض سرویس جدید به روز.'],
                                ['id'=>'maxtime', 'label'=>'حداکثر زمان مجاز', 'unit'=>'روز', 'tooltip'=>'حداکثر تعداد روزی که نمایندگان این سطح مجازند سرویس بسازند.'],
                                ['id'=>'customvolume', 'label'=>'حجم دلخواه مجاز', 'unit'=>'1 یا 0', 'tooltip'=>'عدد 1 یعنی نماینده اجازه وارد کردن حجم دلخواه دارد، عدد 0 یعنی خیر.']
                            ];
                            foreach($limitFields as $pf): ?>
                                <div class="price-matrix-row">
                                    <div class="price-matrix-td-title">
                                        <span class="price-item-label"><?= $pf['label'] ?></span>
                                        <span class="price-item-unit">(<?= $pf['unit'] ?>)</span>
                                        <div class="custom-tooltip-wrapper">
                                            <button type="button" class="tooltip-trigger-btn" data-tooltip="<?= htmlspecialchars($pf['tooltip'], ENT_QUOTES, 'UTF-8') ?>" aria-label="راهنما">!</button>
                                        </div>
                                    </div>
                                    <div class="price-matrix-td-input">
                                        <span class="mobile-tier-label tier-f">عادی (f)</span>
                                        <input type="text" name="<?= $pf['id'] ?>_f" id="<?= $pf['id'] ?>_f" class="input price-input input-tier-f" placeholder="0">
                                    </div>
                                    <div class="price-matrix-td-input">
                                        <span class="mobile-tier-label tier-n">درصددهی (n)</span>
                                        <input type="text" name="<?= $pf['id'] ?>_n" id="<?= $pf['id'] ?>_n" class="input price-input input-tier-n" placeholder="0">
                                    </div>
                                    <div class="price-matrix-td-input">
                                        <span class="mobile-tier-label tier-n2">عمده (n2)</span>
                                        <input type="text" name="<?= $pf['id'] ?>_n2" id="<?= $pf['id'] ?>_n2" class="input price-input input-tier-n2" placeholder="0">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- GROUP 2: PRICING RATES -->
                    <div class="price-category-card" style="margin-top:20px;">
                        <div class="price-category-header">
                            <span class="price-category-icon">💰</span>
                            <span class="price-category-title">تعرفه‌ها و قیمت‌گذاری</span>
                        </div>

                        <div class="price-matrix-table">
                            <div class="price-matrix-head">
                                <div class="price-matrix-th-title">نوع تعرفه</div>
                                <div class="price-matrix-th-tier">
                                    <span class="tier-tag tier-f">عادی (f)</span>
                                </div>
                                <div class="price-matrix-th-tier">
                                    <span class="tier-tag tier-n">درصددهی (n)</span>
                                </div>
                                <div class="price-matrix-th-tier">
                                    <span class="tier-tag tier-n2">عمده (n2)</span>
                                </div>
                            </div>

                            <?php 
                            $pricingFields = [
                                ['id'=>'priceextravolume', 'label'=>'قیمت حجم اضافه', 'unit'=>'تومان / GB', 'tooltip'=>'قیمت هر گیگابایت حجم اضافی که کاربر یا نماینده هنگام تمدید یا افزایش حجم خریداری می‌کند.'],
                                ['id'=>'pricecustomvolume', 'label'=>'قیمت حجم دلخواه', 'unit'=>'تومان / GB', 'tooltip'=>'قیمت محاسبه هر گیگابایت حجم در سرویس‌های حجم دلخواه.'],
                                ['id'=>'pricecustomtime', 'label'=>'قیمت زمان دلخواه', 'unit'=>'تومان / روز', 'tooltip'=>'قیمت محاسبه هر روز زمان در سفارشات زمان دلخواه.'],
                                ['id'=>'priceextratime', 'label'=>'قیمت زمان اضافه', 'unit'=>'تومان / روز', 'tooltip'=>'قیمت هر روز زمان اضافه شده در تمدید یا افزایش زمان سرویس.']
                            ];
                            foreach($pricingFields as $pf): ?>
                                <div class="price-matrix-row">
                                    <div class="price-matrix-td-title">
                                        <span class="price-item-label"><?= $pf['label'] ?></span>
                                        <span class="price-item-unit">(<?= $pf['unit'] ?>)</span>
                                        <div class="custom-tooltip-wrapper">
                                            <button type="button" class="tooltip-trigger-btn" data-tooltip="<?= htmlspecialchars($pf['tooltip'], ENT_QUOTES, 'UTF-8') ?>" aria-label="راهنما">!</button>
                                        </div>
                                    </div>
                                    <div class="price-matrix-td-input">
                                        <span class="mobile-tier-label tier-f">عادی (f)</span>
                                        <input type="text" name="<?= $pf['id'] ?>_f" id="<?= $pf['id'] ?>_f" class="input price-input input-tier-f" placeholder="0">
                                    </div>
                                    <div class="price-matrix-td-input">
                                        <span class="mobile-tier-label tier-n">درصددهی (n)</span>
                                        <input type="text" name="<?= $pf['id'] ?>_n" id="<?= $pf['id'] ?>_n" class="input price-input input-tier-n" placeholder="0">
                                    </div>
                                    <div class="price-matrix-td-input">
                                        <span class="mobile-tier-label tier-n2">عمده (n2)</span>
                                        <input type="text" name="<?= $pf['id'] ?>_n2" id="<?= $pf['id'] ?>_n2" class="input price-input input-tier-n2" placeholder="0">
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- GLOBAL PRICE FIELD: CHANGE LOCATION -->
                            <div class="price-matrix-row global-price-row">
                                <div class="price-matrix-td-title">
                                    <span class="price-item-label">قیمت تغییر لوکیشن</span>
                                    <span class="price-item-unit">(تومان - سراسری)</span>
                                    <div class="custom-tooltip-wrapper">
                                        <button type="button" class="tooltip-trigger-btn" data-tooltip="هزینه ثابت انتقال یا تغییر لوکیشن سرویس کاربر به این پنل (برای همه نمایندگان یکسان است)." aria-label="راهنما">!</button>
                                    </div>
                                </div>
                                <div class="price-matrix-td-input global-input-wrap" style="grid-column: span 3;">
                                    <input type="text" name="priceChangeloc" id="panelPriceChangeloc" class="input price-input" placeholder="0" style="direction:ltr; text-align:center;">
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
            
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" onclick="closePanelModal()">انصراف</button>
                <button type="submit" class="btn btn-primary" style="margin-right:auto">ذخیره پنل</button>
            </div>
        </form>
    </div>
</div>

<!-- Test Connection Modal -->
<div id="testConnModalVeil" class="modal-veil">
    <div class="modal" style="max-width:400px;text-align:center">
        <div class="modal-body" style="padding:30px 20px">
            <div id="testConnLoader">
                <div class="spinner" style="margin:0 auto 15px"></div>
                <h4 style="margin:0">در حال تست اتصال به پنل...</h4>
                <p style="color:var(--ts);font-size:13px;margin:5px 0 0">لطفا چند لحظه صبر کنید</p>
            </div>
            <div id="testConnResult" style="display:none">
                <div id="testConnIcon" style="font-size:40px;margin-bottom:10px"></div>
                <h4 id="testConnTitle" style="margin:0 0 10px"></h4>
                <p id="testConnMessage" style="margin:0;font-size:14px;color:var(--ts);"></p>
                <button class="btn btn-ghost btn-sm" style="margin-top:20px" onclick="closeTestConnModal()">بستن</button>
            </div>
        </div>
    </div>
</div>

<style>
.tab-btn { background:none; border:none; padding:8px 12px; color:var(--ts); cursor:pointer; font-weight:600; border-bottom:2px solid transparent; transition:0.2s; white-space:nowrap; }
.tab-btn.active { color:var(--ac); border-bottom-color:var(--ac); }
.tab-content { display:none; flex-direction:column; gap:15px; }
.tab-content.active { display:flex; }

.spinner {
    width: 30px;
    height: 30px;
    border: 3px solid var(--sf3);
    border-top-color: var(--ac);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }

/* Modern premium custom styles for Inbounds list */
#inboundsList {
    max-height: 220px !important;
    padding-right: 4px;
    direction: rtl;
}
.inbound-item-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg);
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
    position: relative;
    text-align: right;
    box-sizing: border-box;
}
.inbound-item-card:hover {
    border-color: var(--ac);
    background: rgba(0, 180, 216, 0.02);
}
.inbound-item-card.selected {
    border-color: var(--ac);
    background: rgba(0, 180, 216, 0.08);
    box-shadow: 0 0 8px rgba(0, 180, 216, 0.1);
}
.inbound-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--ac);
    flex-shrink: 0;
    margin: 0;
}
.inbound-card-content {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: calc(100% - 30px);
}
.inbound-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    width: 100%;
}
.inbound-remark {
    font-weight: 600;
    font-size: 13px;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 75%;
}
.inbound-badge {
    font-size: 9px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}
.badge-vless { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
.badge-vmess { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.badge-trojan { background: rgba(236, 72, 153, 0.15); color: #ec4899; }
.badge-shadowsocks, .badge-ss { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.badge-hysteria, .badge-hysteria2 { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.badge-tuic { background: rgba(6, 182, 212, 0.15); color: #06b6d4; }
.inbound-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: var(--ts);
}
.inbound-id-tag, .inbound-port-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    direction: ltr;
}
.inbound-id-tag b, .inbound-port-tag b {
    color: var(--text);
}
#inboundsList::-webkit-scrollbar {
    width: 5px;
}
#inboundsList::-webkit-scrollbar-track {
    background: var(--bg-sec);
    border-radius: 3px;
}
#inboundsList::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}
#inboundsList::-webkit-scrollbar-thumb:hover {
    background: var(--ts);
}
@media (max-width: 576px) {
    .inbound-item-card {
        padding: 8px 10px;
        gap: 8px;
    }
    .inbound-remark {
        font-size: 12px;
    }
    .inbound-badge {
        font-size: 8px;
        padding: 1px 4px;
    }
    .inbound-card-footer {
        font-size: 10px;
    }
}

/* Modern Prices Tab Matrix Table & Tooltip Styling */
.price-category-card {
    background: var(--bg-sec);
    border: 1px solid var(--bd);
    border-radius: 16px;
    padding: 18px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
}
.price-category-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    margin-bottom: 16px;
    border-bottom: 1px dashed var(--bd);
}
.price-category-icon {
    font-size: 1.3rem;
}
.price-category-title {
    font-weight: 700;
    font-size: 0.98rem;
    color: var(--fg);
}
.price-matrix-table {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.price-matrix-head {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 10px;
    align-items: center;
    padding: 8px 12px;
    background: rgba(128,128,128,0.05);
    border-radius: 10px;
    margin-bottom: 4px;
}
.price-matrix-th-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--ts);
}
.price-matrix-th-tier {
    text-align: center;
}
.price-matrix-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 10px;
    align-items: center;
    padding: 10px 12px;
    background: var(--bg);
    border: 1px solid var(--bd);
    border-radius: 12px;
    transition: all 0.2s ease;
}
.price-matrix-row:hover {
    border-color: rgba(var(--ac-rgb, 59,130,246), 0.35);
    background: rgba(var(--ac-rgb, 59,130,246), 0.02);
}
.price-matrix-td-title {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.price-item-label {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--fg);
}
.price-item-unit {
    font-size: 0.75rem;
    color: var(--ts);
    direction: ltr;
}
.price-matrix-td-input {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.mobile-tier-label {
    display: none;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    width: max-content;
}
.price-input {
    direction: ltr;
    text-align: center;
    font-weight: 600;
    font-size: 0.92rem;
    border-radius: 8px !important;
    transition: all 0.2s ease;
}
.input-tier-f:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
}
.input-tier-n:focus {
    border-color: #a855f7 !important;
    box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2) !important;
}
.input-tier-n2:focus {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2) !important;
}

/* Tooltip System with '!' Trigger Button */
.custom-tooltip-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.tooltip-trigger-btn {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 1.5px solid var(--ac);
    background: rgba(var(--ac-rgb, 59,130,246), 0.12);
    color: var(--ac);
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    padding: 0;
    line-height: 1;
    user-select: none;
    position: relative;
}
.tooltip-trigger-btn:hover,
.tooltip-trigger-btn:focus {
    background: var(--ac);
    color: #fff;
    transform: scale(1.15);
    box-shadow: 0 0 10px rgba(var(--ac-rgb, 59,130,246), 0.4);
}
.tooltip-trigger-btn[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: calc(100% + 10px);
    right: 50%;
    transform: translateX(50%) translateY(4px);
    background: #0f172a;
    color: #f8fafc;
    border: 1px solid rgba(255,255,255,0.15);
    font-size: 0.78rem;
    font-weight: 400;
    line-height: 1.5;
    padding: 8px 12px;
    border-radius: 8px;
    width: max-content;
    max-width: 240px;
    text-align: right;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.5);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 9999;
    white-space: normal;
}
.tooltip-trigger-btn[data-tooltip]::before {
    content: '';
    position: absolute;
    bottom: calc(100% + 4px);
    right: 50%;
    transform: translateX(50%);
    border: 6px solid transparent;
    border-top-color: #0f172a;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease;
    z-index: 9999;
}
.tooltip-trigger-btn[data-tooltip]:hover::after,
.tooltip-trigger-btn[data-tooltip]:hover::before,
.tooltip-trigger-btn.active::after,
.tooltip-trigger-btn.active::before {
    opacity: 1;
    visibility: visible;
    transform: translateX(50%) translateY(0);
}

/* Tier Badges */
.tier-tag {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 6px;
    display: inline-block;
    text-align: center;
    user-select: none;
}
.tier-f {
    background: rgba(59, 130, 246, 0.12);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.25);
}
.tier-n {
    background: rgba(168, 85, 247, 0.12);
    color: #a855f7;
    border: 1px solid rgba(168, 85, 247, 0.25);
}
.tier-n2 {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

@media (max-width: 650px) {
    .price-matrix-head {
        display: none;
    }
    .price-matrix-row {
        grid-template-columns: 1fr;
        gap: 10px;
        padding: 14px;
    }
    .mobile-tier-label {
        display: inline-block;
        margin-bottom: 2px;
    }
    .price-matrix-td-title {
        border-bottom: 1px dashed var(--bd);
        padding-bottom: 8px;
        margin-bottom: 4px;
    }
    .global-input-wrap {
        grid-column: span 1 !important;
    }
}
</style>

<script>
function switchTab(tabId, btnTarget = null) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    const target = document.getElementById(tabId);
    if (target) target.classList.add('active');
    
    if (btnTarget) {
        btnTarget.classList.add('active');
    } else {
        const btn = document.querySelector(`.tab-btn[onclick*="${tabId}"]`);
        if (btn) btn.classList.add('active');
    }
}

// Mobile tap handler for tooltips
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tooltip-trigger-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const wrapper = this.closest('.custom-tooltip-wrapper');
            const isActive = wrapper.classList.contains('active');
            document.querySelectorAll('.custom-tooltip-wrapper').forEach(w => w.classList.remove('active'));
            if (!isActive) wrapper.classList.add('active');
        });
    });
    document.addEventListener('click', function() {
        document.querySelectorAll('.custom-tooltip-wrapper').forEach(w => w.classList.remove('active'));
    });
});

function togglePasswordEdit() {
    const cb = document.getElementById('changePasswordToggle');
    const input = document.getElementById('panelPassword');
    if (cb.checked) {
        input.removeAttribute('disabled');
        input.value = '';
        input.placeholder = '';
        input.focus();
    } else {
        input.setAttribute('disabled', 'true');
        input.value = '';
        input.placeholder = '••••••••';
    }
}

function openPanelModal(action, btn = null) {
    const modalVeil = document.getElementById('panelModalVeil');
    const title = document.getElementById('panelModalTitle');
    const actionInput = document.getElementById('panelAction');
    
    let data = null;
    if (btn && btn.getAttribute('data-panel')) {
        data = JSON.parse(btn.getAttribute('data-panel'));
    }
    
    // Switch to first tab safely
    document.querySelectorAll('.tab-btn')[0].click();
    
    if (action === 'add') {
        title.innerText = 'افزودن پنل جدید';
        actionInput.value = 'add';
        document.getElementById('panelId').value = '';
        document.getElementById('panelName').value = '';
        document.getElementById('panelUrl').value = '';
        document.getElementById('panelUsername').value = '';
        document.getElementById('changePasswordToggleContainer').style.display = 'none';
        const passInput = document.getElementById('panelPassword');
        passInput.removeAttribute('disabled');
        passInput.value = '';
        passInput.placeholder = '';
        passInput.setAttribute('data-original', '');
        document.getElementById('panelType').value = 'marzban';
        document.getElementById('panelStatus').value = 'active';
        document.getElementById('panelAgent').value = 'all';
        document.getElementById('panelCategoryId').value = '';
        document.getElementById('panelCustomSubDomain').value = '';
        
        document.getElementById('panelInboundStatus').value = 'offinbounddisable';
        document.getElementById('panelInboundDeactive').value = '0';
        document.getElementById('panelConecton').value = 'offconecton';
        document.getElementById('panelInboundId').value = '1';
        document.getElementById('panelSanaeiGroup').value = '';
        
        document.getElementById('panelNamecustom').value = 'vpn';
        document.getElementById('panelLimitPanel').value = 'unlimited';
        document.getElementById('panelSublink').value = 'onsublink';
        document.getElementById('panelConfig').value = 'offconfig';
        document.getElementById('panelQrWgd').value = 'offqrwgd';
        document.getElementById('panelChangeloc').value = 'offchangeloc';
        document.getElementById('panelSubvip').value = 'offsubvip';
        
        document.getElementById('panelStatusExtend').value = 'on_extend';
        document.getElementById('panelTestAccount').value = 'ONTestAccount';
        document.getElementById('panelOnHoldTest').value = '1';
        document.getElementById('panelValUsertest').value = '100';
        document.getElementById('panelTimeUsertest').value = '1';
        
        document.getElementById('panelPriceChangeloc').value = '0';
        
        const jsonFields = ['mainvolume','maxvolume','maintime','maxtime','customvolume','priceextravolume','pricecustomvolume','pricecustomtime','priceextratime'];
        jsonFields.forEach(f => {
            document.getElementById(f+'_f').value = '';
            document.getElementById(f+'_n').value = '';
            document.getElementById(f+'_n2').value = '';
        });
        
    } else if (action === 'edit' && data) {
        title.innerText = 'ویرایش پنل: ' + data.name_panel;
        actionInput.value = 'edit';
        document.getElementById('panelId').value = data.id || '';
        document.getElementById('panelName').value = data.name_panel || '';
        document.getElementById('panelUrl').value = data.url_panel || '';
        document.getElementById('panelUsername').value = data.username_panel || '';
        document.getElementById('changePasswordToggleContainer').style.display = 'flex';
        document.getElementById('changePasswordToggle').checked = false;
        const passInput = document.getElementById('panelPassword');
        passInput.setAttribute('disabled', 'true');
        passInput.value = '';
        passInput.placeholder = '••••••••';
        passInput.setAttribute('data-original', data.password_panel || '');
        document.getElementById('panelType').value = data.type || 'marzban';
        document.getElementById('panelStatus').value = data.status || 'active';
        document.getElementById('panelAgent').value = data.agent || 'all';
        document.getElementById('panelCategoryId').value = data.panel_category_id || '';
        document.getElementById('panelCustomSubDomain').value = data.custom_sub_domain || '';
        
        document.getElementById('panelInboundStatus').value = data.inboundstatus || 'offinbounddisable';
        document.getElementById('panelInboundDeactive').value = data.inbound_deactive || '0';
        document.getElementById('panelConecton').value = data.conecton || 'offconecton';
        document.getElementById('panelInboundId').value = data.inboundid || '1';
        document.getElementById('panelSanaeiGroup').value = data.sanaei_group || '';
        
        if (data.MethodUsername) document.getElementById('panelMethodUsername').value = data.MethodUsername;
        document.getElementById('panelNamecustom').value = data.namecustom || 'vpn';
        document.getElementById('panelLimitPanel').value = data.limit_panel || 'unlimited';
        document.getElementById('panelSublink').value = data.sublink || 'onsublink';
        document.getElementById('panelConfig').value = data.config || 'offconfig';
        document.getElementById('panelQrWgd').value = data.qr_wgd || 'offqrwgd';
        document.getElementById('panelChangeloc').value = data.changeloc || 'offchangeloc';
        document.getElementById('panelSubvip').value = data.subvip || 'offsubvip';
        
        if (data.Methodextend) document.getElementById('panelMethodextend').value = data.Methodextend;
        document.getElementById('panelStatusExtend').value = data.status_extend || 'on_extend';
        document.getElementById('panelTestAccount').value = data.TestAccount || 'ONTestAccount';
        document.getElementById('panelOnHoldTest').value = data.on_hold_test || '1';
        document.getElementById('panelValUsertest').value = data.val_usertest || '100';
        document.getElementById('panelTimeUsertest').value = data.time_usertest || '1';
        
        document.getElementById('panelPriceChangeloc').value = data.priceChangeloc || '0';
        
        const jsonFields = ['mainvolume','maxvolume','maintime','maxtime','customvolume','priceextravolume','pricecustomvolume','pricecustomtime','priceextratime'];
        jsonFields.forEach(f => {
            try {
                const j = JSON.parse(data[f] || '{}');
                document.getElementById(f+'_f').value = j.f || '';
                document.getElementById(f+'_n').value = j.n || '';
                document.getElementById(f+'_n2').value = j.n2 || '';
            } catch(e) {
                document.getElementById(f+'_f').value = '';
                document.getElementById(f+'_n').value = '';
                document.getElementById(f+'_n2').value = '';
            }
        });
    }
    
    togglePanelFields();
    modalVeil.classList.add('open');
}

function togglePanelFields() {
    const panelType = document.getElementById('panelType').value;
    const inboundGroup = document.querySelector('.inboundid-group');
    const sanaeiGroup = document.querySelector('.sanaei-group');
    const sanaeiFetcher = document.getElementById('sanaeiInboundsFetcher');
    
    const usernameGroup = document.getElementById('panelUsername').closest('.field-group');
    const passwordLabel = document.getElementById('panelPasswordLabel') || document.getElementById('panelPassword').previousElementSibling;

    if (panelType === 'WGDashboard' || panelType === 's_ui') {
        usernameGroup.style.display = 'none';
        if (document.getElementById('panelUsername').value === '') {
            document.getElementById('panelUsername').value = 'null';
        }
        passwordLabel.innerText = 'توکن API پنل';
    } else {
        usernameGroup.style.display = 'block';
        if (document.getElementById('panelUsername').value === 'null') {
            document.getElementById('panelUsername').value = '';
        }
        passwordLabel.innerText = 'رمز عبور یا توکن API پنل';
    }

    const typesWithInbound = ['MHSanaei-3.2', 'x-ui_single', 'alireza_single', 's_ui', 'marzneshin', 'WGDashboard'];
    const typesWithFetcher = ['MHSanaei-3.2', 'x-ui_single', 'alireza_single', 's_ui', 'WGDashboard'];

    if (typesWithInbound.includes(panelType)) {
        if (inboundGroup) inboundGroup.style.display = 'block';
        
        // For WGDashboard, default to wg0 if empty
        if (panelType === 'WGDashboard') {
            const inboundIdInput = document.getElementById('panelInboundId');
            if (inboundIdInput && (!inboundIdInput.value || inboundIdInput.value === '1')) {
                inboundIdInput.value = 'wg0';
            }
        }
    } else {
        if (inboundGroup) inboundGroup.style.display = 'none';
    }

    if (panelType === 'MHSanaei-3.2') {
        if (sanaeiGroup) sanaeiGroup.style.display = 'block';
    } else {
        if (sanaeiGroup) sanaeiGroup.style.display = 'none';
    }

    if (sanaeiFetcher) {
        sanaeiFetcher.style.display = typesWithFetcher.includes(panelType) ? 'block' : 'none';
    }

    const qrWgdContainer = document.getElementById('qrWgdContainer');
    if (qrWgdContainer) {
        qrWgdContainer.style.display = (panelType === 'WGDashboard') ? 'block' : 'none';
    }

    const panelSublink = document.getElementById('panelSublink');
    if (panelSublink) {
        const sublinkGroup = panelSublink.closest('.field-group');
        if (sublinkGroup) {
            sublinkGroup.style.display = (panelType === 'WGDashboard') ? 'none' : 'block';
        }
    }

    const configLabel = document.getElementById('panelConfigLabel');
    if (configLabel) {
        if (panelType === 'WGDashboard') {
            configLabel.innerText = 'ارسال فایل کانفیگ وایرگارد (.conf)';
        } else {
            configLabel.innerText = 'نمایش کانفیگ‌ها';
        }
    }
}

document.getElementById('panelType').addEventListener('change', togglePanelFields);

function closePanelModal() {
    document.getElementById('panelModalVeil').classList.remove('open');
}

// Test Connection Logic
(function() {
    const testBtns = document.querySelectorAll('.test-conn-btn');
    const testModalVeil = document.getElementById('testConnModalVeil');
    const loader = document.getElementById('testConnLoader');
    const resultView = document.getElementById('testConnResult');

    testBtns.forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            loader.style.display = 'block';
            resultView.style.display = 'none';
            testModalVeil.classList.add('open');
            try {
                const res = await fetch(`panels_manage.php?action=test_connection&id=${id}`);
                const data = await res.json();
                loader.style.display = 'none';
                resultView.style.display = 'block';
                
                const icon = document.getElementById('testConnIcon');
                const title = document.getElementById('testConnTitle');
                const msg = document.getElementById('testConnMessage');
                
                if (data.success) {
                    icon.innerHTML = '✅';
                    title.innerText = 'اتصال موفق!';
                    title.style.color = '#10b981';
                } else {
                    icon.innerHTML = '❌';
                    title.innerText = 'خطا در اتصال';
                    title.style.color = '#ef4444';
                }
                msg.innerText = data.message;
            } catch (err) {
                loader.style.display = 'none';
                resultView.style.display = 'block';
                document.getElementById('testConnIcon').innerHTML = '⚠️';
                document.getElementById('testConnTitle').innerText = 'خطای شبکه';
                document.getElementById('testConnTitle').style.color = '#f59e0b';
                document.getElementById('testConnMessage').innerText = 'ارتباط با سرور برقرار نشد.';
            }
        });
    });

    window.closeTestConnModal = function() {
        if (testModalVeil) {
            testModalVeil.classList.remove('open');
        }
    };
})();
</script>
<script>
    function updateInboundCheckboxes() {
        const val = document.getElementById('panelInboundId').value;
        const ids = val.split(',').map(x => x.trim()).filter(x => x !== '');
        document.querySelectorAll('.inbound-checkbox').forEach(cb => {
            const isChecked = ids.includes(cb.value);
            cb.checked = isChecked;
            const card = cb.closest('.inbound-item-card');
            if (card) {
                if (isChecked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            }
        });
    }

    function toggleInboundSelection(cb) {
        const val = document.getElementById('panelInboundId').value;
        let ids = val.split(',').map(x => x.trim()).filter(x => x !== '');
        const card = cb.closest('.inbound-item-card');
        if (cb.checked) {
            if (!ids.includes(cb.value)) ids.push(cb.value);
            if (card) card.classList.add('selected');
        } else {
            ids = ids.filter(id => id !== cb.value);
            if (card) card.classList.remove('selected');
        }
        document.getElementById('panelInboundId').value = ids.join(',');
    }

    function fetchSanaeiInbounds() {
        const url = document.getElementById('panelUrl').value;
        const user = document.getElementById('panelUsername').value;
        let pass = document.getElementById('panelPassword').value;
        const originalPass = document.getElementById('panelPassword').getAttribute('data-original') || '';
        if (!pass && originalPass) {
            pass = originalPass;
        }
        const loader = document.getElementById('inboundsLoader');
        const list = document.getElementById('inboundsList');
        if (!url || !pass) {
            list.innerHTML = '<small style="color:var(--red)">لطفاً ابتدا فیلدهای آدرس و رمزعبور/توکن پنل را پر کنید.</small>';
            return;
        }
        loader.style.display = 'inline';
        list.innerHTML = '<small style="color:var(--ts)">در حال دریافت اطلاعات از پنل...</small>';

        const formData = new FormData();
        formData.append('url_panel', url);
        formData.append('username_panel', user);
        formData.append('password_panel', pass);
        const panelType = document.getElementById('panelType').value;
        formData.append('panel_type', panelType);

        fetch('ajax/sanaei_inbounds.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            loader.style.display = 'none';
            if (data.success && data.inbounds) {
                if (data.inbounds.length === 0) {
                    list.innerHTML = '<small style="color:var(--ts)">هیچ اینباندی یافت نشد.</small>';
                    return;
                }
                const currentIds = document.getElementById('panelInboundId').value.split(',').map(x => x.trim());
                list.innerHTML = '';
                data.inbounds.forEach(inb => {
                    const isChecked = currentIds.includes(String(inb.id));
                    const label = document.createElement('label');
                    label.className = `inbound-item-card ${isChecked ? 'selected' : ''}`;
                    label.innerHTML = `
                        <input type="checkbox" class="inbound-checkbox" value="${inb.id}" ${isChecked ? 'checked' : ''} onchange="toggleInboundSelection(this)">
                        <div class="inbound-card-content">
                            <div class="inbound-card-header">
                                <span class="inbound-remark" title="${inb.remark || ''}">${inb.remark || 'بدون نام'}</span>
                                <span class="inbound-badge badge-${(inb.protocol || '').toLowerCase()}">${(inb.protocol || '').toUpperCase()}</span>
                            </div>
                            <div class="inbound-card-footer">
                                <span class="inbound-id-tag">ID: <b>${inb.id}</b></span>
                                <span class="inbound-port-tag">Port: <b>${inb.port}</b></span>
                            </div>
                        </div>
                    `;
                    list.appendChild(label);
                });
            } else {
                list.innerHTML = `<small style="color:var(--red)">خطا: ${data.msg || 'نامشخص'}</small>`;
            }
        }).catch(err => {
            loader.style.display = 'none';
            list.innerHTML = '<small style="color:var(--red)">خطا در ارتباط با سرور.</small>';
            console.error(err);
        });
    }

    function testCurrentPanelConnection() {
        const url = document.getElementById('panelUrl').value;
        const user = document.getElementById('panelUsername').value;
        let pass = document.getElementById('panelPassword').value;
        const originalPass = document.getElementById('panelPassword').getAttribute('data-original') || '';
        if (!pass && originalPass) {
            pass = originalPass;
        }
        const resultDiv = document.getElementById('inlineConnResult');

        if (!url || !pass) {
            resultDiv.style.display = 'block';
            resultDiv.style.background = 'rgba(239,68,68,0.1)';
            resultDiv.style.color = '#ef4444';
            resultDiv.innerHTML = '❌ لطفاً آدرس و رمزعبور/توکن را در تب «اصلی» وارد کنید.';
            return;
        }

        resultDiv.style.display = 'block';
        resultDiv.style.background = 'var(--bg-sec)';
        resultDiv.style.color = 'var(--ts)';
        resultDiv.innerHTML = '⏳ در حال تست اتصال...';

        const formData = new FormData();
        formData.append('url_panel', url);
        formData.append('username_panel', user);
        formData.append('password_panel', pass);
        const panelType = document.getElementById('panelType').value;
        const inboundid = document.getElementById('panelInboundId').value;
        formData.append('panel_type', panelType);
        formData.append('inboundid', inboundid);

        fetch('ajax/sanaei_inbounds.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                resultDiv.style.background = 'rgba(16,185,129,0.1)';
                resultDiv.style.color = '#10b981';
                resultDiv.innerHTML = '✅ اتصال موفق! پنل در دسترس است.' + (data.inbounds ? ' (' + data.inbounds.length + ' اینباند یافت شد)' : '');
            } else {
                resultDiv.style.background = 'rgba(239,68,68,0.1)';
                resultDiv.style.color = '#ef4444';
                resultDiv.innerHTML = '❌ خطا: ' + (data.msg || 'اتصال ناموفق');
            }
        }).catch(err => {
            resultDiv.style.background = 'rgba(245,158,11,0.1)';
            resultDiv.style.color = '#f59e0b';
            resultDiv.innerHTML = '⚠️ خطای شبکه - ارتباط با سرور برقرار نشد.';
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[action="panels_manage.php"]');
        if (form) {
            const requiredElements = form.querySelectorAll('input[required], select[required], textarea[required]');
            requiredElements.forEach(function(element) {
                element.addEventListener('invalid', function() {
                    const tabPane = this.closest('.tab-content');
                    if (tabPane && !tabPane.classList.contains('active')) {
                        const tabId = tabPane.id;
                        const tabBtn = document.querySelector('.tab-btn[onclick="switchTab(\'' + tabId + '\')"]');
                        if (tabBtn) {
                            tabBtn.click();
                        }
                    }
                });
            });
        }
    });
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
