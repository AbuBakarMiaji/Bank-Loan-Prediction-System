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

        } elseif (in_array($action, ['approve_loan', 'reject_loan', 'pending_loan'])) {
            $loan_id   = (int)($_POST['loan_id'] ?? 0);
            $reason    = trim($_POST['rejection_reason'] ?? '');
            $new_status = match($action) {
                'approve_loan'  => 'Approved',
                'reject_loan'   => 'Rejected',
                'pending_loan'  => 'Pending',
            };

            if ($new_status === 'Rejected' && $reason === '') {
                $message = 'Please provide a rejection reason before rejecting.';
                $message_type = 'error';
            } else {
                $clear_reason = ($new_status !== 'Rejected') ? null : $reason;
                $stmt = $db->prepare(
                    'UPDATE loans SET status = ?, rejection_reason = ?, reviewed_at = NOW() WHERE id = ?'
                );
                $stmt->execute([$new_status, $clear_reason, $loan_id]);

                if ($stmt->rowCount() > 0) {
                    $message = "Loan application #{$loan_id} has been set to {$new_status}.";
                } else {
                    $message = "Failed to update loan application #{$loan_id}.";
                    $message_type = 'error';
                }
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
          <h3 style="margin:0 0 4px;font-size:1.1rem;"> Bank Interest Rate Settings</h3>
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
    <style>
      /* ── Loan Applications table overrides ── */
      .loans-table-wrap { overflow-x: auto; }
      .loans-table { width: 100%; border-collapse: collapse; font-size: .88rem; }

      .loans-table thead th {
        background: var(--navy-900);
        color: #8fa0b0;
        font-family: var(--mono);
        font-size: .65rem;
        letter-spacing: .12em;
        text-transform: uppercase;
        padding: 13px 16px;
        border: none;
        white-space: nowrap;
      }
      .loans-table thead th:first-child { border-radius: 0; }

      .loans-table tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--line);
        vertical-align: middle;
        color: var(--text);
      }
      .loans-table tbody tr:last-child td { border-bottom: none; }
      .loans-table tbody tr:hover { background: #f8f9f6; }

      /* Loan ID chip */
      .loan-id-chip {
        font-family: var(--mono);
        font-size: .78rem;
        font-weight: 700;
        color: var(--navy-700);
        background: var(--paper);
        border: 1px solid var(--line-strong);
        border-radius: 4px;
        padding: 4px 9px;
        display: inline-block;
        letter-spacing: .04em;
      }

      /* Customer cell */
      .customer-name { font-weight: 700; font-size: .9rem; color: var(--navy-900); display: block; }
      .customer-email { font-size: .75rem; color: var(--text-muted); margin-top: 1px; display: block; }

      /* Amount */
      .amount-cell { font-family: var(--mono); font-weight: 700; font-size: .92rem; color: var(--navy-900); }

      /* Term pill row */
      .term-pills { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
      .term-pill {
        font-family: var(--mono);
        font-size: .72rem;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 20px;
        background: var(--paper);
        border: 1px solid var(--line-strong);
        color: var(--navy-800);
        white-space: nowrap;
      }
      .rate-pill {
        font-family: var(--mono);
        font-size: .72rem;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 20px;
        background: var(--brass-tint);
        border: 1px solid #e0c980;
        color: var(--brass-dark);
        white-space: nowrap;
      }

      /* Monthly EMI */
      .emi-cell { font-family: var(--mono); font-weight: 800; font-size: .95rem; color: var(--brass-dark); }

      /* Status badges — larger and cleaner */
      .status-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 13px; border-radius: 20px;
        font-size: .76rem; font-weight: 700; letter-spacing: .03em;
        white-space: nowrap;
      }
      .status-badge.approved { background: var(--sage-tint); color: var(--sage); }
      .status-badge.rejected { background: var(--brick-tint); color: var(--brick); }
      .status-badge.pending  { background: var(--brass-tint); color: var(--brass-dark); }
      .status-badge.ai-approved { background: #e6f5ec; color: #276940; }
      .status-badge.ai-rejected { background: var(--brick-tint); color: var(--brick); }

      /* View button */
      .btn-view {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 14px; border-radius: 6px;
        font-size: .78rem; font-weight: 700;
        background: var(--navy-900); color: #fff;
        border: none; cursor: pointer; text-decoration: none;
        transition: background .15s;
        white-space: nowrap;
      }
      .btn-view:hover { background: var(--navy-700); color: #fff; }

      /* Action button group */
      .action-group { display: flex; justify-content: flex-end; gap: 6px; align-items: center; }
    </style>

    <div class="table-card">
      <div class="table-head" style="padding:18px 22px;">
        <div>
          <h3 style="margin:0 0 2px;font-size:1rem;">Loan Applications &amp; Approvals</h3>
          <span style="font-size:.78rem;color:var(--text-muted);">Recent <?= count($loans) ?> application<?= count($loans) !== 1 ? 's' : '' ?> — click <strong>View</strong> for full details</span>
        </div>
        <a href="search_customer.php" class="btn btn-outline btn-sm">Search all customers</a>
      </div>

      <?php if ($loans): ?>
        <div class="loans-table-wrap">
          <table class="loans-table">
            <thead>
              <tr>
                <th>Loan ID</th>
                <th>Customer</th>
                <th>Loan Amount</th>
                <th>Term &amp; Rate</th>
                <th>Monthly EMI</th>
                <th>AI Advisory</th>
                <th>Status</th>
                <th style="text-align:center;">Details</th>
                <th style="text-align:right;">Quick Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($loans as $loan): ?>
                <tr>
                  <!-- Loan ID -->
                  <td><span class="loan-id-chip">#<?= str_pad($loan['id'], 5, '0', STR_PAD_LEFT) ?></span></td>

                  <!-- Customer -->
                  <td>
                    <span class="customer-name"><?= htmlspecialchars($loan['full_name']) ?></span>
                    <span class="customer-email"><?= htmlspecialchars($loan['email']) ?></span>
                  </td>

                  <!-- Amount -->
                  <td><span class="amount-cell">&#2547;<?= number_format($loan['loan_amount'], 2) ?></span></td>

                  <!-- Term & Rate — compact pills side by side -->
                  <td>
                    <div class="term-pills">
                      <span class="term-pill"><?= $loan['loan_term_years'] ?? round(($loan['loan_term_months'] ?? 12)/12) ?> yr</span>
                      <span class="term-pill"><?= $loan['loan_term_months'] ?> mo</span>
                      <span class="rate-pill"><?= number_format($loan['interest_rate'] ?? $current_interest_rate, 2) ?>% p.a.</span>
                    </div>
                  </td>

                  <!-- Monthly EMI -->
                  <td><span class="emi-cell">&#2547;<?= number_format($loan['monthly_payment'] ?? 0, 2) ?></span></td>

                  <!-- AI Advisory -->
                  <td>
                    <?php if ($loan['prediction_result'] === 'Approved'): ?>
                      <span class="status-badge ai-approved">&#10003; AI Eligible</span>
                    <?php else: ?>
                      <span class="status-badge ai-rejected">&#10005; AI Risk</span>
                    <?php endif; ?>
                  </td>

                  <!-- Admin Status -->
                  <td>
                    <?php if ($loan['status'] === 'Approved'): ?>
                      <span class="status-badge approved">&#10003; Approved</span>
                    <?php elseif ($loan['status'] === 'Rejected'): ?>
                      <span class="status-badge rejected">&#10005; Rejected</span>
                    <?php else: ?>
                      <span class="status-badge pending">&#9679; Pending</span>
                    <?php endif; ?>
                  </td>

                  <!-- View Details -->
                  <td style="text-align:center;">
                    <a href="loan_detail.php?loan_id=<?= $loan['id'] ?>" class="btn-view">
                      &#128269; View
                    </a>
                  </td>

                  <!-- Quick Action: all 3 states always available -->
                  <td>
                    <div class="action-group">
                      <?php if ($loan['status'] !== 'Approved'): ?>
                        <form method="post" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                          <input type="hidden" name="action" value="approve_loan">
                          <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                          <button type="submit" class="btn btn-brass btn-sm"
                            onclick="return confirm('Approve loan #<?= $loan['id'] ?>?');">&#10003; Approve</button>
                        </form>
                      <?php endif; ?>

                      <?php if ($loan['status'] !== 'Rejected'): ?>
                        <button type="button" class="btn btn-danger btn-sm"
                          onclick="openRejectModal(<?= $loan['id'] ?>, '<?= htmlspecialchars($loan['full_name'], ENT_QUOTES) ?>')">
                          &#10005; Reject
                        </button>
                      <?php endif; ?>

                      <?php if ($loan['status'] !== 'Pending'): ?>
                        <form method="post" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                          <input type="hidden" name="action" value="pending_loan">
                          <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                          <button type="submit" class="btn btn-outline btn-sm"
                            onclick="return confirm('Reset loan #<?= $loan['id'] ?> to Pending?');">&#8635; Pending</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <div class="glyph">&#128466;</div>
          <p>No loan applications submitted yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- ══ REJECTION REASON MODAL ══ -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(11,27,43,0.65);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:8px;padding:36px 32px;max-width:480px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,0.35);position:relative;">
    <button onclick="closeRejectModal()" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#888;">&#10005;</button>
    <div style="font-size:.72rem;color:var(--brick);font-family:var(--mono);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px;">Admin Action &mdash; Rejection</div>
    <h3 style="margin:0 0 6px;font-size:1.15rem;">Reject Loan Application</h3>
    <p id="rejectModalDesc" style="font-size:.88rem;color:var(--text-muted);margin:0 0 20px;"></p>
    <form method="post" id="rejectForm">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="reject_loan">
      <input type="hidden" name="loan_id" id="rejectLoanId">
      <div style="margin-bottom:16px;">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy-900);display:block;margin-bottom:6px;">
          Rejection Reason <span style="color:var(--brick);">*</span>
        </label>
        <textarea name="rejection_reason" id="rejectReason" required rows="4"
          placeholder="e.g. Insufficient income relative to loan amount, poor credit history..."
          style="width:100%;padding:10px 12px;border:1.5px solid var(--line-strong);border-radius:4px;font-family:var(--sans);font-size:.9rem;resize:vertical;"></textarea>
        <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px;">This reason will be visible to the customer on their dashboard.</div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closeRejectModal()" class="btn btn-outline btn-sm">Cancel</button>
        <button type="submit" class="btn btn-danger btn-sm">&#10005; Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(loanId, customerName) {
  document.getElementById('rejectLoanId').value = loanId;
  document.getElementById('rejectReason').value = '';
  document.getElementById('rejectModalDesc').textContent =
    'Provide a clear reason for rejecting loan #' + String(loanId).padStart(5,'0') + ' for ' + customerName + '. The customer will see this message.';
  const modal = document.getElementById('rejectModal');
  modal.style.display = 'flex';
  setTimeout(() => document.getElementById('rejectReason').focus(), 100);
}
function closeRejectModal() {
  document.getElementById('rejectModal').style.display = 'none';
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});
</script>

<?php include '../includes/footer.php'; ?>
