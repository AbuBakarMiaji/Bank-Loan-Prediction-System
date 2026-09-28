<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$user = current_user();
$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect('search_customer.php');
}

$stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$loans = $stmt->fetchAll();

$base_path = '../';
$page_title = htmlspecialchars($customer['full_name']) . ' — Ledger';
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
        <div class="eyebrow">Admin module · Customer record</div>
        <h1><?= htmlspecialchars($customer['full_name']) ?></h1>
        <p><?= htmlspecialchars($customer['email']) ?> · <?= htmlspecialchars($customer['phone'] ?: 'No phone on file') ?> · Account #<?= str_pad($customer['id'], 5, '0', STR_PAD_LEFT) ?></p>
      </div>
      <a href="search_customer.php" class="btn btn-outline">Back to search</a>
    </div>

    <div class="table-card">
      <div class="table-head"><h3>Loan applications</h3></div>
      <?php if ($loans): ?>
        <table>
          <thead><tr><th>Loan ID</th><th>Amount</th><th>Term</th><th>Applied</th><th>Prediction</th><th>Confidence</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($loans as $loan): ?>
              <tr>
                <td class="mono">#<?= str_pad($loan['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td class="mono">$<?= number_format($loan['loan_amount'], 2) ?></td>
                <td><?= $loan['loan_term_months'] ?> months</td>
                <td><?= date('M j, Y', strtotime($loan['created_at'])) ?></td>
                <td><?php if ($loan['prediction_result'] === 'Approved'): ?>
                      <span class="badge sage">Approved</span>
                    <?php else: ?>
                      <span class="badge brick">Rejected</span>
                    <?php endif; ?></td>
                <td class="mono"><?= $loan['prediction_confidence'] ?>%</td>
                <td><span class="badge slate"><?= $loan['status'] ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state"><div class="glyph">📄</div><p>This customer hasn't applied for a loan yet.</p></div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
