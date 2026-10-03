<?php
/**
 * MODULE 9 — Admin Question Bank
 * Manually add, edit and delete questions for each category.
 * Uses the `question_bank` table (run database/migrate_admin.php first).
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = [];
$editQuestion = null;

// Check if question_bank table exists
$tableCheck = $pdo->query("SHOW TABLES LIKE 'question_bank'")->fetchColumn();
$tableExists = (bool) $tableCheck;

if ($tableExists) {
    // ── Handle POST actions ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Invalid CSRF token.';
        } else {
            $action = $_POST['action'];

            if ($action === 'add') {
                $categoryId = (int) ($_POST['category_id'] ?? 0);
                $difficulty = trim($_POST['difficulty'] ?? 'Intermediate');
                $text       = trim($_POST['question_text'] ?? '');
                if ($text === '' || $categoryId <= 0) {
                    $errors[] = 'Question text and category are required.';
                } else {
                    $pdo->prepare(
                        "INSERT INTO question_bank (category_id, difficulty, question_text, created_by_admin)
                         VALUES (?, ?, ?, ?)"
                    )->execute([$categoryId, $difficulty, $text, (int)$_SESSION['admin_id']]);
                    set_flash('success', 'Question added to the bank.');
                    redirect('/admin/questions.php');
                }
            } elseif ($action === 'edit') {
                $id         = (int) ($_POST['id'] ?? 0);
                $categoryId = (int) ($_POST['category_id'] ?? 0);
                $difficulty = trim($_POST['difficulty'] ?? 'Intermediate');
                $text       = trim($_POST['question_text'] ?? '');
                if ($text === '' || $categoryId <= 0) {
                    $errors[] = 'Question text and category are required.';
                } else {
                    $pdo->prepare(
                        "UPDATE question_bank SET category_id = ?, difficulty = ?, question_text = ? WHERE id = ?"
                    )->execute([$categoryId, $difficulty, $text, $id]);
                    set_flash('success', 'Question updated.');
                    redirect('/admin/questions.php');
                }
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare("DELETE FROM question_bank WHERE id = ?")->execute([$id]);
                set_flash('success', 'Question deleted.');
                redirect('/admin/questions.php');
            }
        }
    }

    // ── Load edit target ──
    if (isset($_GET['edit'])) {
        $editId = (int) $_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM question_bank WHERE id = ?");
        $stmt->execute([$editId]);
        $editQuestion = $stmt->fetch();
    }

    // ── Filter by category ──
    $filterCat = (int) ($_GET['category'] ?? 0);

    $whereQ = $filterCat > 0 ? "WHERE qb.category_id = {$filterCat}" : '';

    $questions = $pdo->query(
        "SELECT qb.*, c.name AS category_name
         FROM question_bank qb
         JOIN categories c ON c.id = qb.category_id
         {$whereQ}
         ORDER BY qb.created_at DESC"
    )->fetchAll();

    $totalQuestions = (int) $pdo->query("SELECT COUNT(*) FROM question_bank")->fetchColumn();
}

// ── Load categories for forms ──
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$adminActivePage = 'questions';
$pageTitle = 'Question Bank';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/admin.css">

<div class="admin-shell">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-content">

    <div class="admin-page-header">
      <div>
        <h1>Question Bank</h1>
        <p>Manually manage interview questions by category.</p>
      </div>
      <?php if ($tableExists): ?>
        <button class="btn-admin-sm primary" onclick="openModal('addQModal')">
          <i class="bi bi-plus-lg"></i> Add Question
        </button>
      <?php endif; ?>
    </div>

    <!-- Table not created warning -->
    <?php if (!$tableExists): ?>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius-md);padding:1.25rem 1.5rem;margin-bottom:1.5rem;">
        <div style="font-weight:600;color:#92400e;margin-bottom:.5rem;"><i class="bi bi-exclamation-triangle"></i> Database table not found</div>
        <p style="font-size:.88rem;color:#78350f;margin:0;">
          The <code>question_bank</code> table doesn't exist yet. Please import <code>database/schema.sql</code> into your MySQL database.
        </p>
      </div>

    <?php else: ?>

      <?php if (!empty($errors)): ?>
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-md);padding:.9rem 1.2rem;margin-bottom:1.25rem;font-size:.87rem;color:#b91c1c;">
          <?php foreach ($errors as $err): ?><div><i class="bi bi-exclamation-circle"></i> <?= e($err) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Category filter -->
      <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:center;">
        <span style="font-size:.83rem;color:var(--mist);">Filter by category:</span>
        <a href="?" class="btn-admin-sm <?= $filterCat === 0 ? 'primary' : '' ?>">All (<?= $totalQuestions ?>)</a>
        <?php foreach ($categories as $cat): ?>
          <a href="?category=<?= (int)$cat['id'] ?>"
             class="btn-admin-sm <?= $filterCat === (int)$cat['id'] ? 'primary' : '' ?>">
            <?= e($cat['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Questions list -->
      <div class="admin-panel">
        <div class="admin-panel-header">
          <h2><i class="bi bi-patch-question" style="margin-right:.4rem;color:var(--navy);"></i>Questions</h2>
          <span style="font-size:.82rem;color:var(--mist);"><?= count($questions) ?> question<?= count($questions) !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($questions)): ?>
          <div class="admin-empty">
            <i class="bi bi-patch-question"></i>
            <p>No questions yet. Click "Add Question" to get started.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Question</th>
                <th>Category</th>
                <th>Difficulty</th>
                <th>Added</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($questions as $i => $q):
                $diffColors = ['Beginner'=>'badge-green','Intermediate'=>'badge-amber','Advanced'=>'badge-red'];
                $dc = $diffColors[$q['difficulty']] ?? 'badge-gray';
              ?>
              <tr>
                <td style="color:var(--mist);font-size:.78rem;"><?= $i + 1 ?></td>
                <td style="max-width:350px;line-height:1.5;">
                  <?= e($q['question_text']) ?>
                </td>
                <td><span class="badge-pill badge-blue"><?= e($q['category_name']) ?></span></td>
                <td><span class="badge-pill <?= $dc ?>"><?= e($q['difficulty']) ?></span></td>
                <td style="font-size:.78rem;color:var(--mist);"><?= e(date('M j, Y', strtotime($q['created_at']))) ?></td>
                <td>
                  <div style="display:flex;gap:.4rem;">
                    <a href="?edit=<?= (int)$q['id'] ?>" class="btn-admin-sm">
                      <i class="bi bi-pencil"></i> Edit
                    </a>
                    <form method="post" class="delete-form"
                          onsubmit="return confirm('Delete this question?');">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                      <button type="submit" class="btn-admin-sm danger">
                        <i class="bi bi-trash"></i>
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

      <!-- Inline Edit Form -->
      <?php if ($editQuestion): ?>
      <div class="admin-panel" style="border-color:var(--navy);">
        <div class="admin-panel-header">
          <h2><i class="bi bi-pencil" style="margin-right:.4rem;color:var(--navy);"></i>Edit Question</h2>
          <a href="<?= BASE_URL ?>/admin/questions.php" class="btn-admin-sm">Cancel</a>
        </div>
        <div class="admin-panel-body">
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= (int)$editQuestion['id'] ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
              <div class="admin-form-group">
                <label>Category *</label>
                <select name="category_id" class="form-control admin-select" required>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"
                      <?= (int)$cat['id'] === (int)$editQuestion['category_id'] ? 'selected' : '' ?>>
                      <?= e($cat['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="admin-form-group">
                <label>Difficulty</label>
                <select name="difficulty" class="form-control admin-select">
                  <?php foreach (['Beginner','Intermediate','Advanced'] as $d): ?>
                    <option value="<?= $d ?>" <?= $editQuestion['difficulty'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="admin-form-group">
              <label>Question Text *</label>
              <textarea name="question_text" class="form-control" rows="3" required><?= e($editQuestion['question_text']) ?></textarea>
            </div>
            <button type="submit" class="btn-admin-sm primary"><i class="bi bi-check-lg"></i> Save Changes</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

    <?php endif; // tableExists ?>

  </main>
</div>

<!-- Add Question Modal -->
<div class="admin-modal-overlay" id="addQModal">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h3><i class="bi bi-patch-plus" style="color:var(--navy);margin-right:.4rem;"></i>Add Question</h3>
      <button class="admin-modal-close" onclick="closeModal('addQModal')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <form method="post" action="">
      <div class="admin-modal-body">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="admin-form-group">
            <label>Category *</label>
            <select name="category_id" class="form-control admin-select" required>
              <option value="">Select…</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-form-group">
            <label>Difficulty</label>
            <select name="difficulty" class="form-control admin-select">
              <option value="Beginner">Beginner</option>
              <option value="Intermediate" selected>Intermediate</option>
              <option value="Advanced">Advanced</option>
            </select>
          </div>
        </div>
        <div class="admin-form-group">
          <label>Question Text *</label>
          <textarea name="question_text" class="form-control" rows="3"
                    placeholder="e.g. Explain the difference between SQL and NoSQL databases." required></textarea>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn-admin-sm" onclick="closeModal('addQModal')">Cancel</button>
        <button type="submit" class="btn-admin-sm primary"><i class="bi bi-plus-lg"></i> Add Question</button>
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
