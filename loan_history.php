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
$page_title = 'Loan History — BU Bank Ltd';
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
        <h1>Your Loan History (in BDT ৳)</h1>
        <p>Complete record of submitted applications, calculated repayment schedules, and admin approval status.</p>
      </div>
      <a href="loan_apply.php" class="btn btn-brass">Apply for a new loan</a>
    </div>

    <div class="table-card">
      <?php if ($loans): ?>
        <table>
          <thead>
            <tr>
              <th>Loan ID</th>
              <th>Loan Amount (BDT)</th>
              <th>Term / Rate</th>
              <th>Monthly Payment</th>
              <th>Total Interest</th>
              <th>Applied Date</th>
              <th>AI Advisory</th>
              <th>Approval Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($loans as $loan): ?>
              <tr>
                <td class="mono">#<?= str_pad($loan['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td class="mono" style="font-weight:700;">৳<?= number_format($loan['loan_amount'], 2) ?></td>
                <td>
                  <?= $loan['loan_term_years'] ?? round(($loan['loan_term_months'] ?? 12)/12) ?> Yrs (<?= $loan['loan_term_months'] ?> Mo)<br>
                  <span class="text-muted mono" style="font-size:.78rem;"><?= number_format($loan['interest_rate'] ?? 8.5, 2) ?>% / yr</span>
                </td>
                <td class="mono" style="font-weight:600;color:var(--navy-900);">
                  ৳<?= number_format($loan['monthly_payment'] ?? 0, 2) ?>
                </td>
                <td class="mono text-muted">
                  ৳<?= number_format($loan['total_interest'] ?? 0, 2) ?>
                </td>
                <td><?= date('M j, Y', strtotime($loan['created_at'])) ?></td>
                <td>
                  <?php if ($loan['prediction_result'] === 'Approved'): ?>
                    <span class="badge sage">AI Approved (<?= $loan['prediction_confidence'] ?>%)</span>
                  <?php else: ?>
                    <span class="badge brick">AI Rejected (<?= $loan['prediction_confidence'] ?>%)</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($loan['status'] === 'Approved'): ?>
                    <span class="badge sage" style="font-size:.8rem;padding:6px 12px;">✓ Approved</span>
                  <?php elseif ($loan['status'] === 'Rejected'): ?>
                    <span class="badge brick" style="font-size:.8rem;padding:6px 12px;">✕ Rejected</span>
                  <?php else: ?>
                    <span class="badge brass" style="font-size:.8rem;padding:6px 12px;">⏳ Pending Approval</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <div class="glyph">📄</div>
          <p>No loan applications on record yet.</p>
          <a href="loan_apply.php" class="btn btn-brass btn-sm">Start an application</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include 'includes/footer.php'; ?>
