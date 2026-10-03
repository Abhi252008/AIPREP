<?php
/**
 * MODULE 9 — Admin Categories Management
 * List, add, edit and delete interview categories.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = [];
$editCategory = null;

// ── Handle Add ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'];

        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? 'bi-briefcase');
            if ($name === '') {
                $errors[] = 'Category name is required.';
            } else {
                $pdo->prepare("INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)")
                    ->execute([$name, $desc, $icon]);
                set_flash('success', 'Category "' . $name . '" added.');
                redirect('/admin/categories.php');
            }
        } elseif ($action === 'edit') {
            $id   = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? 'bi-briefcase');
            if ($name === '') {
                $errors[] = 'Category name is required.';
            } else {
                $pdo->prepare("UPDATE categories SET name = ?, description = ?, icon = ? WHERE id = ?")
                    ->execute([$name, $desc, $icon, $id]);
                set_flash('success', 'Category updated.');
                redirect('/admin/categories.php');
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            set_flash('success', 'Category deleted.');
            redirect('/admin/categories.php');
        }
    }
}

// ── Load edit target ──
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $editCategory = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $editCategory->execute([$editId]);
    $editCategory = $editCategory->fetch();
}

// ── Load all categories with session count ──
$categories = $pdo->query(
    "SELECT c.*, COUNT(s.id) AS session_count
     FROM categories c
     LEFT JOIN interview_sessions s ON s.category_id = c.id
     GROUP BY c.id
     ORDER BY c.id"
)->fetchAll();

$adminActivePage = 'categories';
$pageTitle = 'Manage Categories';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/admin.css">

<div class="admin-shell">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-content">

    <div class="admin-page-header">
      <div>
        <h1>Categories</h1>
        <p>Manage interview categories available to users.</p>
      </div>
      <button class="btn-admin-sm primary" onclick="openModal('addModal')">
        <i class="bi bi-plus-lg"></i> Add Category
      </button>
    </div>

    <?php if (!empty($errors)): ?>
      <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-md);padding:.9rem 1.2rem;margin-bottom:1.25rem;font-size:.87rem;color:#b91c1c;">
        <?php foreach ($errors as $e): ?><div><i class="bi bi-exclamation-circle"></i> <?= e($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="admin-panel">
      <div class="admin-panel-header">
        <h2><i class="bi bi-tags" style="margin-right:.4rem;color:var(--navy);"></i>All Categories</h2>
        <span style="font-size:.82rem;color:var(--mist);"><?= count($categories) ?> total</span>
      </div>
      <?php if (empty($categories)): ?>
        <div class="admin-empty"><i class="bi bi-tags"></i><p>No categories yet.</p></div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Icon</th>
              <th>Name</th>
              <th>Description</th>
              <th>Sessions</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $i => $cat): ?>
            <tr>
              <td style="color:var(--mist);font-size:.78rem;"><?= $cat['id'] ?></td>
              <td><i class="bi <?= e($cat['icon']) ?>" style="font-size:1.2rem;color:var(--navy);"></i></td>
              <td><strong><?= e($cat['name']) ?></strong></td>
              <td style="color:var(--mist);font-size:.83rem;max-width:200px;"><?= e($cat['description'] ?? '—') ?></td>
              <td><span class="badge-pill badge-blue"><?= (int)$cat['session_count'] ?></span></td>
              <td>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                  <a href="?edit=<?= (int)$cat['id'] ?>" class="btn-admin-sm">
                    <i class="bi bi-pencil"></i> Edit
                  </a>
                  <form method="post" action="" class="delete-form"
                        onsubmit="return confirm('Delete category \'<?= e(addslashes($cat['name'])) ?>\'? Sessions using it will also be deleted.');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                    <button type="submit" class="btn-admin-sm danger">
                      <i class="bi bi-trash"></i> Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Inline Edit Form (shown when ?edit=X) -->
    <?php if ($editCategory): ?>
    <div class="admin-panel" style="border-color:var(--navy);">
      <div class="admin-panel-header">
        <h2><i class="bi bi-pencil" style="margin-right:.4rem;color:var(--navy);"></i>Edit Category: <?= e($editCategory['name']) ?></h2>
        <a href="<?= BASE_URL ?>/admin/categories.php" class="btn-admin-sm">Cancel</a>
      </div>
      <div class="admin-panel-body">
        <form method="post" action="">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" value="<?= (int)$editCategory['id'] ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="admin-form-group">
              <label>Category Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= e($editCategory['name']) ?>">
            </div>
            <div class="admin-form-group">
              <label>Bootstrap Icon Class</label>
              <input type="text" name="icon" class="form-control" placeholder="bi-briefcase" value="<?= e($editCategory['icon'] ?? '') ?>">
            </div>
          </div>
          <div class="admin-form-group">
            <label>Description</label>
            <input type="text" name="description" class="form-control" value="<?= e($editCategory['description'] ?? '') ?>">
          </div>
          <button type="submit" class="btn-admin-sm primary"><i class="bi bi-check-lg"></i> Save Changes</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- Add Category Modal -->
<div class="admin-modal-overlay" id="addModal">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h3><i class="bi bi-plus-circle" style="color:var(--navy);margin-right:.4rem;"></i>Add New Category</h3>
      <button class="admin-modal-close" onclick="closeModal('addModal')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <form method="post" action="">
      <div class="admin-modal-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add">
        <div class="admin-form-group">
          <label>Category Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. System Design">
        </div>
        <div class="admin-form-group">
          <label>Bootstrap Icon Class</label>
          <input type="text" name="icon" class="form-control" placeholder="bi-briefcase" value="bi-briefcase">
          <small style="color:var(--mist);font-size:.75rem;">Browse icons at <a href="https://icons.getbootstrap.com" target="_blank" style="color:var(--navy);">icons.getbootstrap.com</a></small>
        </div>
        <div class="admin-form-group">
          <label>Description</label>
          <input type="text" name="description" class="form-control" placeholder="Short description…">
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn-admin-sm" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn-admin-sm primary"><i class="bi bi-plus-lg"></i> Add Category</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.admin-modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
