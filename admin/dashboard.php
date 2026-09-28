<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$user = current_user();
$db = get_db();
$message = '';
$message_type = 'success';

// Handle POST actions (interest rate update, loan approval/rejection)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $message = 'Session expired. Please try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_interest_rate') {
            $new_rate = (float)($_POST['annual_interest_rate'] ?? 0);
            if ($new_rate > 0 && $new_rate <= 100) {
                set_annual_interest_rate($new_rate);
                $message = 'Annual Interest Rate updated successfully to ' . number_format($new_rate, 2) . '% per year.';
            } else {
                $message = 'Please enter a valid interest rate between 0.1% and 100%.';
                $message_type = 'error';
            }
        } elseif ($action === 'approve_loan' || $action === 'reject_loan') {
            $loan_id = (int)($_POST['loan_id'] ?? 0);
            $new_status = ($action === 'approve_loan') ? 'Approved' : 'Rejected';

            $stmt = $db->prepare('UPDATE loans SET status = ? WHERE id = ?');
            $stmt->execute([$new_status, $loan_id]);

            if ($stmt->rowCount() > 0) {
                $message = "Loan application #{$loan_id} has been set to {$new_status}.";
            } else {
                $message = "Failed to update loan application #{$loan_id}.";
                $message_type = 'error';
            }
        }
    }
}

$current_interest_rate = get_annual_interest_rate();

$totalCustomers  = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role = 'customer'")->fetch()['c'];
$totalLoans      = (int)$db->query("SELECT COUNT(*) c FROM loans")->fetch()['c'];
$pendingLoans    = (int)$db->query("SELECT COUNT(*) c FROM loans WHERE status = 'Pending'")->fetch()['c'];
$approvedLoans   = (int)$db->query("SELECT COUNT(*) c FROM loans WHERE status = 'Approved'")->fetch()['c'];
$rejectedLoans   = (int)$db->query("SELECT COUNT(*) c FROM loans WHERE status = 'Rejected'")->fetch()['c'];

$loans = $db->query(
    "SELECT l.*, u.full_name, u.email FROM loans l
     JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC LIMIT 15"
)->fetchAll();

$base_path = '../';
$page_title = 'Admin Dashboard — BU Bank Ltd';
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
        <div class="eyebrow">Admin module · Control Panel</div>
        <h1>Portfolio &amp; Loan Approval Overview</h1>
        <p>Manage interest rates, review pending loan applications, and issue approvals/rejections.</p>
      </div>
      <a href="search_customer.php" class="btn btn-brass">Search customer records</a>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>" style="margin-bottom:24px;">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- STATS GRID -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="label">Customer Accounts</div>
        <div class="value"><?= $totalCustomers ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Pending Approval</div>
        <div class="value brass"><?= $pendingLoans ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Approved Loans</div>
        <div class="value sage"><?= $approvedLoans ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Rejected Loans</div>
        <div class="value brick"><?= $rejectedLoans ?></div>
      </div>
    </div>

    <!-- INTEREST RATE MANAGEMENT CARD -->
    <div class="card" style="padding:24px;margin-bottom:32px;background:var(--paper-raised);">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
          <h3 style="margin:0 0 4px;font-size:1.1rem;">⚙️ Bank Interest Rate Settings</h3>
          <p class="text-muted" style="margin:0;font-size:.88rem;">
            Set the global annual interest rate used in the loan calculator and customer application forms.
          </p>
        </div>
        <form method="post" style="display:flex;align-items:center;gap:12px;" class="form-inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="update_interest_rate">
          <div style="display:flex;align-items:center;gap:6px;">
            <input type="number" step="0.01" min="0.1" max="100" name="annual_interest_rate" value="<?= htmlspecialchars(number_format($current_interest_rate, 2, '.', '')) ?>" style="width:110px;font-weight:700;font-family:var(--mono);" required>
            <span style="font-weight:700;color:var(--navy-900);">% / year</span>
          </div>
          <button type="submit" class="btn btn-brass btn-sm">Update Interest Rate</button>
        </form>
      </div>
    </div>

    <!-- APPLICATIONS TABLE WITH APPROVAL ACTIONS -->
    <div class="table-card">
      <div class="table-head">
        <h3>Loan Applications &amp; Approvals</h3>
        <span class="text-muted" style="font-size:.82rem;">Showing recent applications</span>
      </div>
      <?php if ($loans): ?>
        <table>
          <thead>
            <tr>
              <th>Loan ID</th>
              <th>Customer</th>
              <th>Amount (BDT)</th>
              <th>Term / Rate</th>
              <th>Monthly Payment</th>
              <th>AI Advisory</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($loans as $loan): ?>
              <tr>
                <td class="mono">#<?= str_pad($loan['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <strong><?= htmlspecialchars($loan['full_name']) ?></strong><br>
                  <span class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($loan['email']) ?></span>
                </td>
                <td class="mono">৳<?= number_format($loan['loan_amount'], 2) ?></td>
                <td>
                  <?= $loan['loan_term_years'] ?? round(($loan['loan_term_months'] ?? 12)/12) ?> Yrs (<?= $loan['loan_term_months'] ?> Mo)<br>
                  <span class="text-muted mono" style="font-size:.78rem;"><?= number_format($loan['interest_rate'] ?? $current_interest_rate, 2) ?>% / yr</span>
                </td>
                <td class="mono" style="font-weight:600;color:var(--navy-900);">
                  ৳<?= number_format($loan['monthly_payment'] ?? 0, 2) ?>
                </td>
                <td>
                  <?php if ($loan['prediction_result'] === 'Approved'): ?>
                    <span class="badge sage">AI Approved</span>
                  <?php else: ?>
                    <span class="badge brick">AI Rejected</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($loan['status'] === 'Approved'): ?>
                    <span class="badge sage" style="font-size:.8rem;padding:6px 12px;">✓ Approved</span>
                  <?php elseif ($loan['status'] === 'Rejected'): ?>
                    <span class="badge brick" style="font-size:.8rem;padding:6px 12px;">✕ Rejected</span>
                  <?php else: ?>
                    <span class="badge brass" style="font-size:.8rem;padding:6px 12px;">⏳ Pending</span>
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
        <div class="empty-state">
          <div class="glyph">🗂</div>
          <p>No loan applications submitted yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
