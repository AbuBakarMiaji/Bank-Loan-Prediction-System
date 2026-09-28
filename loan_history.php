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

$base_path = '';
$page_title = 'Loan history — BU Bank Ltd';
include 'includes/header.php';
?>

<div class="app-shell">
  <aside class="sidebar">
    <div class="user-chip">
      <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div class="role">Customer</div>
    </div>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="loan_apply.php">Apply for a loan</a>
      <a href="loan_history.php" class="active">Loan history</a>
      <div class="divider"></div>
      <a href="logout.php">Log out</a>
    </nav>
  </aside>

  <main class="main-content">
    <div class="page-head">
      <div>
        <div class="eyebrow">Loan module · History</div>
        <h1>Your loan history</h1>
        <p>Every application you've submitted, with its prediction and current status.</p>
      </div>
      <a href="loan_apply.php" class="btn btn-brass">Apply for a new loan</a>
    </div>

    <div class="table-card">
      <?php if ($loans): ?>
        <table>
          <thead>
            <tr><th>Loan ID</th><th>Amount</th><th>Term</th><th>Applied</th><th>Prediction</th><th>Confidence</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($loans as $loan): ?>
              <tr>
                <td class="mono">#<?= str_pad($loan['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td class="mono">$<?= number_format($loan['loan_amount'], 2) ?></td>
                <td><?= $loan['loan_term_months'] ?> months</td>
                <td><?= date('M j, Y', strtotime($loan['created_at'])) ?></td>
                <td>
                  <?php if ($loan['prediction_result'] === 'Approved'): ?>
                    <span class="badge sage">Approved</span>
                  <?php else: ?>
                    <span class="badge brick">Rejected</span>
                  <?php endif; ?>
                </td>
                <td class="mono"><?= $loan['prediction_confidence'] ?>%</td>
                <td><span class="badge slate"><?= $loan['status'] ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <div class="glyph">📄</div>
          <p>No applications on record yet.</p>
          <a href="loan_apply.php" class="btn btn-brass btn-sm">Start an application</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include 'includes/footer.php'; ?>
