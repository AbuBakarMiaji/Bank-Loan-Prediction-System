<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
if (is_admin()) redirect('admin/dashboard.php');

$user = current_user();
$db = get_db();

$stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$loans = $stmt->fetchAll();

$total = count($loans);
$approved = count(array_filter($loans, fn($l) => $l['status'] === 'Approved'));
$pending  = count(array_filter($loans, fn($l) => $l['status'] === 'Pending'));
$latest   = $loans[0] ?? null;

$base_path = '';
$page_title = 'Dashboard — BU Bank Ltd';
include 'includes/header.php';
?>

<div class="app-shell">
  <aside class="sidebar">
    <div class="user-chip">
      <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div class="role">Customer</div>
    </div>
    <nav>
      <a href="dashboard.php" class="active">Dashboard</a>
      <a href="loan_apply.php">Apply for a loan</a>
      <a href="loan_history.php">Loan history</a>
      <div class="divider"></div>
      <a href="logout.php">Log out</a>
    </nav>
  </aside>

  <main class="main-content">
    <div class="page-head">
      <div>
        <div class="eyebrow">User module · Customer dashboard</div>
        <h1>Welcome, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></h1>
        <p>Here's where things stand with your applications.</p>
      </div>
      <a href="loan_apply.php" class="btn btn-brass">Apply for a new loan</a>
    </div>

    <div class="stat-grid">
      <div class="stat-card"><div class="label">Total applications</div><div class="value"><?= $total ?></div></div>
      <div class="stat-card"><div class="label">Approved</div><div class="value sage"><?= $approved ?></div></div>
      <div class="stat-card"><div class="label">Pending review</div><div class="value brass"><?= $pending ?></div></div>
    </div>

    <div class="table-card">
      <div class="table-head">
        <h3>Most recent application</h3>
        <a href="loan_history.php" class="btn btn-outline btn-sm">View all history</a>
      </div>
      <?php if ($latest): ?>
        <table>
          <thead><tr><th>Loan ID</th><th>Amount</th><th>Term</th><th>Applied</th><th>Prediction</th><th>Status</th></tr></thead>
          <tbody>
            <tr>
              <td class="mono">#<?= str_pad($latest['id'], 5, '0', STR_PAD_LEFT) ?></td>
              <td class="mono">$<?= number_format($latest['loan_amount'], 2) ?></td>
              <td><?= $latest['loan_term_months'] ?> months</td>
              <td><?= date('M j, Y', strtotime($latest['created_at'])) ?></td>
              <td>
                <?php if ($latest['prediction_result'] === 'Approved'): ?>
                  <span class="badge sage">Approved · <?= $latest['prediction_confidence'] ?>%</span>
                <?php else: ?>
                  <span class="badge brick">Rejected · <?= $latest['prediction_confidence'] ?>%</span>
                <?php endif; ?>
              </td>
              <td><span class="badge slate"><?= $latest['status'] ?></span></td>
            </tr>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <div class="glyph">📄</div>
          <p>You haven't applied for a loan yet.</p>
          <a href="loan_apply.php" class="btn btn-brass btn-sm">Start an application</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include 'includes/footer.php'; ?>
