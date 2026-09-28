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

$total    = count($loans);
$approved = count(array_filter($loans, fn($l) => $l['status'] === 'Approved'));
$pending  = count(array_filter($loans, fn($l) => $l['status'] === 'Pending'));
$rejected = count(array_filter($loans, fn($l) => $l['status'] === 'Rejected'));
$latest   = $loans[0] ?? null;

$base_path = '';
$page_title = 'Customer Dashboard — BU Bank Ltd';
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
        <p>Overview of your submitted loan applications and approval status.</p>
      </div>
      <a href="loan_apply.php" class="btn btn-brass">Apply for a new loan</a>
    </div>

    <div class="stat-grid">
      <div class="stat-card"><div class="label">Total Applications</div><div class="value"><?= $total ?></div></div>
      <div class="stat-card"><div class="label">Pending Approval</div><div class="value brass"><?= $pending ?></div></div>
      <div class="stat-card"><div class="label">Approved Loans</div><div class="value sage"><?= $approved ?></div></div>
      <div class="stat-card"><div class="label">Rejected</div><div class="value brick"><?= $rejected ?></div></div>
    </div>

    <div class="table-card">
      <div class="table-head">
        <h3>Most recent application</h3>
        <a href="loan_history.php" class="btn btn-outline btn-sm">View full history</a>
      </div>
      <?php if ($latest): ?>
        <table>
          <thead>
            <tr>
              <th>Loan ID</th>
              <th>Amount (BDT)</th>
              <th>Term / Rate</th>
              <th>Monthly Payment</th>
              <th>Applied</th>
              <th>AI Advisory</th>
              <th>Approval Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="mono">#<?= str_pad($latest['id'], 5, '0', STR_PAD_LEFT) ?></td>
              <td class="mono">৳<?= number_format($latest['loan_amount'], 2) ?></td>
              <td>
                <?= $latest['loan_term_years'] ?? round(($latest['loan_term_months'] ?? 12)/12) ?> Yrs (<?= $latest['loan_term_months'] ?> Mo)<br>
                <span class="text-muted mono" style="font-size:.78rem;"><?= number_format($latest['interest_rate'] ?? 8.5, 2) ?>% / yr</span>
              </td>
              <td class="mono" style="font-weight:600;color:var(--navy-900);">
                ৳<?= number_format($latest['monthly_payment'] ?? 0, 2) ?>
              </td>
              <td><?= date('M j, Y', strtotime($latest['created_at'])) ?></td>
              <td>
                <?php if ($latest['prediction_result'] === 'Approved'): ?>
                  <span class="badge sage">AI Approved</span>
                <?php else: ?>
                  <span class="badge brick">AI Rejected</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($latest['status'] === 'Approved'): ?>
                  <span class="badge sage" style="font-size:.8rem;padding:6px 12px;">✓ Approved</span>
                <?php elseif ($latest['status'] === 'Rejected'): ?>
                  <span class="badge brick" style="font-size:.8rem;padding:6px 12px;">✕ Rejected</span>
                <?php else: ?>
                  <span class="badge brass" style="font-size:.8rem;padding:6px 12px;">⏳ Pending Approval</span>
                <?php endif; ?>
              </td>
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
