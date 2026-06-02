<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
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

$pageTitle = $textbotlang['panel']['panelsManageTitle'];
$pageLede = $textbotlang['panel']['panelsManageSubtitle'];
$activeNav = 'panels_manage';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title"><?= $textbotlang['panel']['panelsHeading'] ?> <small>(<?= count($panels) ?>)</small></div>
        </div>
        <div class="toolbar-end">
            <!-- Add Button (Currently placeholder) -->
            <button class="btn btn-primary btn-sm" onclick="alert('قابلیت افزودن پنل از طریق ربات تلگرام در دسترس است.')">
                <?= icon('plus', 14) ?> <?= $textbotlang['panel']['panelsAddBtn'] ?>
            </button>
        </div>
    </div>

    <div class="tbl-wrap">
        <table class="tbl-xl">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th><?= $textbotlang['panel']['panelsColName'] ?></th>
                    <th><?= $textbotlang['panel']['panelsColUrl'] ?></th>
                    <th><?= $textbotlang['panel']['panelsColType'] ?></th>
                    <th><?= $textbotlang['panel']['panelsColStatus'] ?></th>
                    <th style="width:120px"><?= $textbotlang['panel']['panelsColActions'] ?></th>
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
                        ?>
                        <tr>
                            <td class="cf"><?= $i++ ?></td>
                            <td class="cs" style="font-weight:600"><?= htmlspecialchars($p['name_panel'] ?? '—') ?></td>
                            <td class="cm" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:ltr;text-align:left;">
                                <a href="<?= htmlspecialchars($p['url_panel'] ?? '#') ?>" target="_blank" style="color:var(--ac);text-decoration:none">
                                    <?= htmlspecialchars($p['url_panel'] ?? '—') ?>
                                </a>
                            </td>
                            <td>
                                <span class="tag tag-plain" style="text-transform:uppercase"><?= htmlspecialchars($type) ?></span>
                            </td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="tag tag-ok"><?= $textbotlang['panel']['panelsStatusActive'] ?></span>
                                <?php else: ?>
                                    <span class="tag tag-no"><?= $textbotlang['panel']['panelsStatusInactive'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px">
                                    <button class="btn btn-ghost btn-sm btn-icon" onclick="alert('قابلیت ویرایش و تست اتصال به زودی در نسخه وب اضافه خواهد شد. در حال حاضر از ربات تلگرام استفاده کنید.')" title="<?= $textbotlang['panel']['panelsActionTest'] ?>">
                                        <?= icon('dashboard', 14) ?>
                                    </button>
                                    <a href="?action=delete&id=<?= (int)$p['id'] ?>" class="btn btn-no btn-sm btn-icon" title="<?= $textbotlang['panel']['panelsActionDelete'] ?>" onclick="return confirm('<?= sprintf($textbotlang['panel']['panelsConfirmDelete'], htmlspecialchars($p['name_panel'])) ?>')">
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

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
