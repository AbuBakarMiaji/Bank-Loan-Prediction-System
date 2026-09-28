<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$user = current_user();
$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$message = '';
$message_type = 'success';

$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect('search_customer.php');
}

// Handle loan status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $message = 'Session expired. Please try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $loan_id = (int)($_POST['loan_id'] ?? 0);

        if ($action === 'approve_loan' || $action === 'reject_loan') {
            $new_status = ($action === 'approve_loan') ? 'Approved' : 'Rejected';
            $update_stmt = $db->prepare('UPDATE loans SET status = ? WHERE id = ? AND user_id = ?');
            $update_stmt->execute([$new_status, $loan_id, $id]);

            if ($update_stmt->rowCount() > 0) {
                $message = "Loan application #{$loan_id} has been set to {$new_status}.";
            } else {
                $message = "Failed to update loan application #{$loan_id}.";
                $message_type = 'error';
            }
        }
    }
}

$stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$loans = $stmt->fetchAll();

$base_path = '../';
$page_title = htmlspecialchars($customer['full_name']) . ' — Ledger Admin';
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

    <?php if ($message): ?>
      <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>" style="margin-bottom:24px;">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <div class="table-card">
      <div class="table-head"><h3>Loan applications for <?= htmlspecialchars($customer['full_name']) ?></h3></div>
      <?php if ($loans): ?>
        <table>
          <thead>
            <tr>
              <th>Loan ID</th>
              <th>Amount (BDT)</th>
              <th>Term / Rate</th>
              <th>Monthly Payment</th>
              <th>Applied</th>
              <th>AI Advisory</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($loans as $loan): ?>
              <tr>
                <td class="mono">#<?= str_pad($loan['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td class="mono">৳<?= number_format($loan['loan_amount'], 2) ?></td>
                <td>
                  <?= $loan['loan_term_years'] ?? round(($loan['loan_term_months'] ?? 12)/12) ?> Yrs (<?= $loan['loan_term_months'] ?> Mo)<br>
                  <span class="text-muted mono" style="font-size:.78rem;"><?= number_format($loan['interest_rate'] ?? 8.5, 2) ?>% / yr</span>
                </td>
                <td class="mono" style="font-weight:600;">৳<?= number_format($loan['monthly_payment'] ?? 0, 2) ?></td>
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
                    <span class="badge sage">Approved</span>
                  <?php elseif ($loan['status'] === 'Rejected'): ?>
                    <span class="badge brick">Rejected</span>
                  <?php else: ?>
                    <span class="badge brass">Pending Approval</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;">
                  <div style="display:flex;justify-content:flex-end;gap:6px;">
                    <?php if ($loan['status'] !== 'Approved'): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="approve_loan">
                        <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                        <button type="submit" class="btn btn-brass btn-sm" onclick="return confirm('Approve loan #<?= $loan['id'] ?>?');">Approve</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($loan['status'] !== 'Rejected'): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="reject_loan">
                        <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject loan #<?= $loan['id'] ?>?');">Reject</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
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
