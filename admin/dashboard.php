<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$user = current_user();
$db = get_db();

$totalCustomers = $db->query("SELECT COUNT(*) c FROM users WHERE role = 'customer'")->fetch()['c'];
$totalLoans     = $db->query("SELECT COUNT(*) c FROM loans")->fetch()['c'];
$approved       = $db->query("SELECT COUNT(*) c FROM loans WHERE prediction_result = 'Approved'")->fetch()['c'];
$rejected       = $db->query("SELECT COUNT(*) c FROM loans WHERE prediction_result = 'Rejected'")->fetch()['c'];

$recent = $db->query(
    "SELECT l.*, u.full_name, u.email FROM loans l
     JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC LIMIT 8"
)->fetchAll();

$base_path = '../';
$page_title = 'Admin dashboard — BU Bank Ltd';
include '../includes/header.php';
?>

<div class="app-shell">
  <aside class="sidebar">
    <div class="user-chip">
      <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div class="role">Administrator</div>
    </div>
    <nav>
      <a href="dashboard.php" class="active">Admin dashboard</a>
      <a href="search_customer.php">Search customers</a>
      <a href="reports.php">Reports</a>
      <div class="divider"></div>
      <a href="../logout.php">Log out</a>
    </nav>
  </aside>

  <main class="main-content">
    <div class="page-head">
      <div>
        <div class="eyebrow">Admin module · Dashboard</div>
        <h1>Portfolio overview</h1>
        <p>System-wide activity across all customer accounts.</p>
      </div>
      <a href="search_customer.php" class="btn btn-brass">Search a customer</a>
    </div>

    <div class="stat-grid">
      <div class="stat-card"><div class="label">Customer accounts</div><div class="value"><?= $totalCustomers ?></div></div>
      <div class="stat-card"><div class="label">Total applications</div><div class="value"><?= $totalLoans ?></div></div>
      <div class="stat-card"><div class="label">Predicted approved</div><div class="value sage"><?= $approved ?></div></div>
      <div class="stat-card"><div class="label">Predicted rejected</div><div class="value brick"><?= $rejected ?></div></div>
    </div>

    <div class="table-card">
      <div class="table-head">
        <h3>Most recent applications</h3>
        <a href="reports.php" class="btn btn-outline btn-sm">Full reports</a>
      </div>
      <?php if ($recent): ?>
        <table>
          <thead><tr><th>Loan ID</th><th>Customer</th><th>Amount</th><th>Applied</th><th>Prediction</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($recent as $loan): ?>
              <tr>
                <td class="mono">#<?= str_pad($loan['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($loan['full_name']) ?><br><span class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($loan['email']) ?></span></td>
                <td class="mono">$<?= number_format($loan['loan_amount'], 2) ?></td>
                <td><?= date('M j, Y', strtotime($loan['created_at'])) ?></td>
                <td><?php if ($loan['prediction_result'] === 'Approved'): ?>
                      <span class="badge sage">Approved</span>
                    <?php else: ?>
                      <span class="badge brick">Rejected</span>
                    <?php endif; ?></td>
                <td><span class="badge slate"><?= $loan['status'] ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state"><div class="glyph">🗂</div><p>No applications submitted yet.</p></div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
