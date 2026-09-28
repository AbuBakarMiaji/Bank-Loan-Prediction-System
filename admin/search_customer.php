<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$user = current_user();
$db = get_db();
$query = trim($_GET['q'] ?? '');
$results = [];

if ($query !== '') {
    $stmt = $db->prepare(
        "SELECT * FROM users WHERE role = 'customer'
         AND (full_name LIKE ? OR email LIKE ? OR id = ?)
         ORDER BY full_name LIMIT 25"
    );
    $like = '%' . $query . '%';
    $stmt->execute([$like, $like, is_numeric($query) ? (int)$query : 0]);
    $results = $stmt->fetchAll();
}

$base_path = '../';
$page_title = 'Search customers — Ledger';
include '../includes/header.php';
?>

<div class="app-shell">
  <aside class="sidebar">
    <div class="user-chip">
      <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div class="role">Administrator</div>
    </div>
    <nav>
      <a href="dashboard.php">Admin dashboard</a>
      <a href="search_customer.php" class="active">Search customers</a>
      <a href="reports.php">Reports</a>
      <div class="divider"></div>
      <a href="../logout.php">Log out</a>
    </nav>
  </aside>

  <main class="main-content">
    <div class="page-head">
      <div>
        <div class="eyebrow">Admin module · Search customer</div>
        <h1>Find a customer</h1>
        <p>Look up any account by name, email, or account ID.</p>
      </div>
    </div>

    <form method="get" class="search-form">
      <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Search by name, email, or account ID…">
      <button type="submit" class="btn btn-brass">Search</button>
    </form>

    <div class="table-card">
      <?php if ($query === ''): ?>
        <div class="empty-state"><div class="glyph">🔍</div><p>Enter a name, email, or account ID to begin.</p></div>
      <?php elseif (!$results): ?>
        <div class="empty-state"><div class="glyph">🗂</div><p>No customers matched "<?= htmlspecialchars($query) ?>".</p></div>
      <?php else: ?>
        <table>
          <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Applications</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($results as $c):
              $count = $db->prepare('SELECT COUNT(*) c FROM loans WHERE user_id = ?');
              $count->execute([$c['id']]);
              $appCount = $count->fetch()['c'];
            ?>
              <tr>
                <td class="mono">#<?= str_pad($c['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($c['full_name']) ?></td>
                <td><?= htmlspecialchars($c['email']) ?></td>
                <td><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
                <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                <td class="mono"><?= $appCount ?></td>
                <td><a href="customer_view.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">View records</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
