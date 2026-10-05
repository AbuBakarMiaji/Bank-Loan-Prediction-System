<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$user = current_user();
$db   = get_db();
$id   = (int)($_GET['id'] ?? 0);
$message      = '';
$message_type = 'success';

$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect('search_customer.php');
}

// Handle loan status updates from this page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $message      = 'Session expired. Please try again.';
        $message_type = 'error';
    } else {
        $action  = $_POST['action'] ?? '';
        $loan_id = (int)($_POST['loan_id'] ?? 0);

        if ($action === 'approve_loan' || $action === 'reject_loan') {
            $new_status  = ($action === 'approve_loan') ? 'Approved' : 'Rejected';
            $update_stmt = $db->prepare('UPDATE loans SET status = ? WHERE id = ? AND user_id = ?');
            $update_stmt->execute([$new_status, $loan_id, $id]);

            if ($update_stmt->rowCount() > 0) {
                $message = "Loan application #{$loan_id} has been set to {$new_status}.";
            } else {
                $message      = "Failed to update loan application #{$loan_id}.";
                $message_type = 'error';
            }
        }
    }
}

$stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$loans = $stmt->fetchAll();

// Summary stats
$total_loan_amount  = array_sum(array_column($loans, 'loan_amount'));
$total_approved     = count(array_filter($loans, fn($l) => $l['status'] === 'Approved'));
$total_pending      = count(array_filter($loans, fn($l) => $l['status'] === 'Pending'));
$total_rejected     = count(array_filter($loans, fn($l) => $l['status'] === 'Rejected'));

$base_path  = '../';
$page_title = htmlspecialchars($customer['full_name']) . ' — Customer Detail';
include '../includes/header.php';
?>

<style>
.cust-info-card {
    background: linear-gradient(135deg, var(--navy-900), var(--navy-700));
    color: #fff;
    border-radius: var(--radius);
    padding: 28px 32px;
    margin-bottom: 28px;
    display: flex;
    gap: 32px;
    align-items: center;
    flex-wrap: wrap;
}
.cust-avatar {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: var(--brass);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 800;
    color: #fff; flex-shrink: 0;
    font-family: var(--mono);
}
.cust-meta { flex: 1; }
.cust-name { font-size: 1.5rem; font-weight: 800; margin-bottom: 4px; }
.cust-email { font-size: .88rem; color: #a0c0d0; margin-bottom: 2px; }
.cust-phone { font-size: .85rem; color: #8fafc0; }
.cust-stats {
    display: flex; gap: 20px; flex-wrap: wrap;
}
.cust-stat { text-align: center; }
.cust-stat-val { font-size: 1.4rem; font-weight: 800; font-family: var(--mono); color: var(--brass-tint); }
.cust-stat-lbl { font-size: .7rem; color: #8fa0b0; text-transform: uppercase; letter-spacing: .06em; }
</style>

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
       
        <h1><?= htmlspecialchars($customer['full_name']) ?></h1>
        <p>Account #<?= str_pad($customer['id'], 5, '0', STR_PAD_LEFT) ?> &middot; Member since <?= date('F Y', strtotime($customer['created_at'])) ?></p>
      </div>
      <a href="search_customer.php" class="btn btn-outline">&larr; Back to search</a>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>" style="margin-bottom:24px;">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- CUSTOMER INFO BANNER -->
    <div class="cust-info-card">
      <div class="cust-avatar">
        <?= strtoupper(substr($customer['full_name'], 0, 1)) ?>
      </div>
      <div class="cust-meta">
        <div class="cust-name"><?= htmlspecialchars($customer['full_name']) ?></div>
        <div class="cust-email">&#9993; <?= htmlspecialchars($customer['email']) ?></div>
        <div class="cust-phone">&#128222; <?= htmlspecialchars($customer['phone'] ?: 'No phone on file') ?></div>
      </div>
      <div class="cust-stats">
        <div class="cust-stat">
          <div class="cust-stat-val"><?= count($loans) ?></div>
          <div class="cust-stat-lbl">Applications</div>
        </div>
        <div class="cust-stat">
          <div class="cust-stat-val" style="color:#a0d0b0;"><?= $total_approved ?></div>
          <div class="cust-stat-lbl">Approved</div>
        </div>
        <div class="cust-stat">
          <div class="cust-stat-val" style="color:var(--brass-tint);"><?= $total_pending ?></div>
          <div class="cust-stat-lbl">Pending</div>
        </div>
        <div class="cust-stat">
          <div class="cust-stat-val" style="color:#f0a090;"><?= $total_rejected ?></div>
          <div class="cust-stat-lbl">Rejected</div>
        </div>
        <?php if ($total_loan_amount > 0): ?>
        <div class="cust-stat">
          <div class="cust-stat-val" style="font-size:1.1rem;">&#2547;<?= number_format($total_loan_amount, 0) ?></div>
          <div class="cust-stat-lbl">Total Applied</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- LOAN APPLICATIONS TABLE -->
    <div class="table-card">
      <div class="table-head">
        <h3>Loan Applications — <?= htmlspecialchars($customer['full_name']) ?></h3>
        <span class="text-muted" style="font-size:.82rem;">Click "View Details" to see the full application</span>
      </div>
      <?php if ($loans): ?>
        <table>
          <thead>
            <tr>
              <th>Loan ID</th>
              <th>Amount (BDT)</th>
              <th>Term / Rate</th>
              <th>Monthly EMI</th>
              <th>Applied</th>
              <th>AI Advisory</th>
              <th>Status</th>
              <th style="text-align:center;">Full Details</th>
              <th style="text-align:right;">Quick Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($loans as $loan): ?>
              <tr>
                <td class="mono">#<?= str_pad($loan['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td class="mono" style="font-weight:700;">&#2547;<?= number_format($loan['loan_amount'], 2) ?></td>
                <td>
                  <?= $loan['loan_term_years'] ?? round(($loan['loan_term_months'] ?? 12)/12) ?> Yrs (<?= $loan['loan_term_months'] ?> Mo)<br>
                  <span class="text-muted mono" style="font-size:.78rem;"><?= number_format($loan['interest_rate'] ?? 8.5, 2) ?>% / yr</span>
                </td>
                <td class="mono" style="font-weight:700;color:var(--brass-dark);">&#2547;<?= number_format($loan['monthly_payment'] ?? 0, 2) ?></td>
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
                    <span class="badge sage">&#10003; Approved</span>
                  <?php elseif ($loan['status'] === 'Rejected'): ?>
                    <span class="badge brick">&#10005; Rejected</span>
                  <?php else: ?>
                    <span class="badge brass">&#9203; Pending</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;">
                  <a href="loan_detail.php?loan_id=<?= $loan['id'] ?>" class="btn btn-brass btn-sm">
                    &#128269; View Details
                  </a>
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
          <div class="glyph">&#128196;</div>
          <p>This customer hasn't applied for a loan yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
