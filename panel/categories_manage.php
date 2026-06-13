<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// HANDLE ADD / EDIT POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $category_name = trim($_POST['remark'] ?? '');
    
    if ($action === 'add' && !empty($category_name)) {
        // Check if exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = ?");
        $stmt->execute([$category_name]);
        if ($stmt->fetchColumn() == 0) {
            $stmt_add = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
            $stmt_add->execute([$category_name]);
            flash('success', 'دسته‌بندی با موفقیت اضافه شد.');
        } else {
            flash('error', 'این دسته‌بندی از قبل وجود دارد.');
        }
    } elseif ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        $stmt_old = $pdo->prepare("SELECT remark FROM category WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_name = $stmt_old->fetchColumn();
        
        if ($old_name && !empty($category_name) && $old_name !== $category_name) {
            // Check if new name exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = ? AND id != ?");
            $stmt->execute([$category_name, $id]);
            if ($stmt->fetchColumn() == 0) {
                // Update category
                $stmt_upd = $pdo->prepare("UPDATE category SET remark = ? WHERE id = ?");
                $stmt_upd->execute([$category_name, $id]);
                
                // Cascade update products
                $stmt_prod = $pdo->prepare("UPDATE product SET category = ? WHERE category = ?");
                $stmt_prod->execute([$category_name, $old_name]);
                
                flash('success', 'دسته‌بندی با موفقیت ویرایش شد و در تمامی محصولات اعمال گردید.');
            } else {
                flash('error', 'این نام قبلا برای دسته‌بندی دیگری ثبت شده است.');
            }
        }
    }
    
    header("Location: categories_manage.php");
    exit;
}

// HANDLE DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if (isset($_GET['_csrf']) && hash_equals($_SESSION['csrf_token'] ?? '', $_GET['_csrf'])) {
        $stmt_old = $pdo->prepare("SELECT remark FROM category WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_name = $stmt_old->fetchColumn();
        
        if ($old_name) {
            // Remove from products
            $stmt_prod = $pdo->prepare("UPDATE product SET category = NULL WHERE category = ?");
            $stmt_prod->execute([$old_name]);
            
            // Delete from category
            $stmt_del = $pdo->prepare("DELETE FROM category WHERE id = ?");
            $stmt_del->execute([$id]);
            flash('success', 'دسته‌بندی با موفقیت حذف شد.');
        }
    } else {
        flash('error', 'توکن امنیتی نامعتبر است.');
    }
    header("Location: categories_manage.php");
    exit;
}

// FETCH CATEGORIES
$stmt = $pdo->query("SELECT * FROM category ORDER BY id DESC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "مدیریت دسته‌بندی‌ها";
$activeNav = "categories_manage";
include __DIR__ . '/inc/layout_head.php';
?>

<div class="header-action">
  <div class="header-texts">
    <h1 class="page-title"><?= icon('layers') ?> مدیریت دسته‌بندی‌ها</h1>
    <p class="page-desc">دسته‌بندی‌های محصولات خود را مدیریت کنید.</p>
  </div>
  <div class="header-buttons">
    <button class="btn btn-primary" onclick="openAddModal()">
      <?= icon('plus') ?> افزودن دسته‌بندی جدید
    </button>
  </div>
</div>

<div class="page-card">
  <?php if (empty($categories)): ?>
    <div class="empty-state">
      <div class="empty-icon"><?= icon('layers') ?></div>
      <h3>هیچ دسته‌بندی ثبت نشده است</h3>
      <p>شما هنوز دسته‌بندی برای محصولات خود اضافه نکرده‌اید.</p>
      <button class="btn btn-primary" onclick="openAddModal()"><?= icon('plus', 16) ?> افزودن اولین دسته‌بندی</button>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>شناسه</th>
            <th>نام دسته‌بندی</th>
            <th class="ta-left">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td><span class="badge badge-dim"><?= $cat['id'] ?></span></td>
              <td><b><?= htmlspecialchars($cat['remark'] ?? '') ?></b></td>
              <td class="ta-left">
                <button class="btn btn-ghost btn-sm btn-icon" title="ویرایش"
                  onclick="openEditModal(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>)">
                  <?= icon('edit', 14) ?>
                </button>
                <a href="categories_manage.php?delete=<?= $cat['id'] ?>&_csrf=<?= csrf_token() ?>" 
                   class="btn btn-no btn-sm btn-icon" title="حذف"
                   data-confirm="آیا از حذف دسته‌بندی «<?= htmlspecialchars($cat['remark'] ?? '') ?>» مطمئن هستید؟ (محصولات این دسته بدون دسته‌بندی خواهند شد)">
                  <?= icon('trash', 14) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal-veil" id="catModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-head">
      <h3 id="modalTitle">افزودن دسته‌بندی</h3>
      <button class="modal-x" onclick="closeModal('catModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" id="modalAction" value="add">
        <input type="hidden" name="id" id="modalId" value="">
        <div class="form-grid">
          <div class="field full">
            <label>نام دسته‌بندی</label>
            <input type="text" name="remark" id="modalRemark" class="input" placeholder="مثال: گیمینگ" required>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary" id="modalSubmit"><?= icon('check', 14) ?> ثبت</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('catModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('modalTitle').innerText = 'افزودن دسته‌بندی جدید';
  document.getElementById('modalAction').value = 'add';
  document.getElementById('modalId').value = '';
  document.getElementById('modalRemark').value = '';
  document.getElementById('modalSubmit').innerHTML = '<?= icon('plus', 14) ?> افزودن';
  document.getElementById('catModal').classList.add('show');
}

function openEditModal(cat) {
  document.getElementById('modalTitle').innerText = 'ویرایش دسته‌بندی';
  document.getElementById('modalAction').value = 'edit';
  document.getElementById('modalId').value = cat.id;
  document.getElementById('modalRemark').value = cat.remark || '';
  document.getElementById('modalSubmit').innerHTML = '<?= icon('check', 14) ?> ذخیره تغییرات';
  document.getElementById('catModal').classList.add('show');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('show');
}
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
