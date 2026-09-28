<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$user    = current_user();
$db      = get_db();
$loan_id = (int)($_GET['loan_id'] ?? 0);
$message = '';
$message_type = 'success';

if (!$loan_id) redirect('dashboard.php');

// Fetch loan + customer in one query
$stmt = $db->prepare("
    SELECT l.*, u.full_name, u.email, u.phone, u.created_at AS member_since
    FROM loans l
    JOIN users u ON u.id = l.user_id
    WHERE l.id = ?
");
$stmt->execute([$loan_id]);
$loan = $stmt->fetch();

if (!$loan) redirect('dashboard.php');

// Handle approve / reject / pending POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $message      = 'Session expired. Please try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $reason = trim($_POST['rejection_reason'] ?? '');

        $new_status = match($action) {
            'approve_loan' => 'Approved',
            'reject_loan'  => 'Rejected',
            'pending_loan' => 'Pending',
            default        => '',
        };

        if ($new_status) {
            if ($new_status === 'Rejected' && $reason === '') {
                $message      = 'Please provide a rejection reason.';
                $message_type = 'error';
            } else {
                $clear_reason = ($new_status !== 'Rejected') ? null : $reason;
                $upd = $db->prepare(
                    'UPDATE loans SET status = ?, rejection_reason = ?, reviewed_at = NOW() WHERE id = ?'
                );
                $upd->execute([$new_status, $clear_reason, $loan_id]);
                if ($upd->rowCount() > 0) {
                    $loan['status']           = $new_status;
                    $loan['rejection_reason'] = $clear_reason;
                    $message = "Loan application #{$loan_id} has been set to {$new_status}.";
                } else {
                    $message      = "Failed to update loan #{$loan_id}.";
                    $message_type = 'error';
                }
            }
        }
    }
}

$base_path  = '../';
$page_title = 'Loan Application #' . str_pad($loan_id, 5, '0', STR_PAD_LEFT) . ' — Admin Details';
include '../includes/header.php';
?>

<style>
.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px;
    margin-bottom: 28px;
}
@media (max-width: 860px) { .detail-grid { grid-template-columns: 1fr; } }

.detail-card {
    background: var(--paper-raised);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow-card);
    overflow: hidden;
}
.detail-card-header {
    background: var(--navy-900);
    color: #fff;
    padding: 14px 20px;
    font-size: .78rem;
    font-family: var(--mono);
    letter-spacing: .1em;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 8px;
}
.detail-card-body { padding: 20px; }

.field-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 0;
    border-bottom: 1px solid var(--line);
    gap: 12px;
    flex-wrap: wrap;
}
.field-row:last-child { border-bottom: none; }
.field-label {
    font-size: .82rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    flex-shrink: 0;
}
.field-value {
    font-size: .95rem;
    font-weight: 700;
    color: var(--navy-900);
    font-family: var(--mono);
    text-align: right;
}
.field-value.normal { font-family: var(--sans); font-size: .92rem; }

.loan-banner {
    background: linear-gradient(135deg, var(--navy-900) 0%, var(--navy-700) 100%);
    border-radius: var(--radius);
    padding: 28px 32px;
    color: #fff;
    margin-bottom: 28px;
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    align-items: center;
    justify-content: space-between;
}
.loan-banner-title { font-size: .7rem; color: #8fa0b0; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 4px; }
.loan-banner-value { font-family: var(--mono); font-weight: 700; font-size: 1.7rem; color: var(--brass-tint); }
.loan-banner-small { font-size: .8rem; color: #8fa0b0; margin-top: 2px; }
.loan-banner-stat { text-align: center; }

.conf-bar { background: rgba(255,255,255,0.15); height: 6px; border-radius: 3px; overflow: hidden; margin-top: 6px; }
.conf-bar-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, var(--brass), #f0b840); }

.status-large {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 24px; border-radius: 32px;
    font-weight: 800; font-size: 1rem; letter-spacing: .04em;
    text-transform: uppercase;
}
.status-large.pending  { background: #fff3cd; color: #8a6a24; border: 2px solid var(--brass); }
.status-large.approved { background: var(--sage-tint); color: var(--sage); border: 2px solid var(--sage); }
.status-large.rejected { background: var(--brick-tint); color: var(--brick); border: 2px solid var(--brick); }

.actions-bar {
    background: var(--paper-raised);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 20px 24px;
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 28px;
}
</style>

<div class="app-shell">
  <aside class="sidebar">
    <div class="user-chip">
      <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div class="role">Administrator</div>
    </div>
    <nav>
      <a href="dashboard.php">Admin dashboard</a>
      <a href="search_customer.php">Search customers</a>
      <a href="reports.php">Reports</a>
      <div class="divider"></div>
      <a href="../logout.php">Log out</a>
    </nav>
  </aside>

  <main class="main-content">
    <div class="page-head">
      <div>
        <div class="eyebrow">Admin module &middot; Loan Application Full Details</div>
        <h1>Application #<?= str_pad($loan_id, 5, '0', STR_PAD_LEFT) ?></h1>
        <p>Submitted by <strong><?= htmlspecialchars($loan['full_name']) ?></strong> on <?= date('F j, Y \a\t g:i A', strtotime($loan['created_at'])) ?></p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="customer_view.php?id=<?= $loan['user_id'] ?>" class="btn btn-outline">&larr; All Applications</a>
        <a href="dashboard.php" class="btn btn-outline">Dashboard</a>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>" style="margin-bottom:24px;">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- LOAN SUMMARY BANNER -->
    <div class="loan-banner">
      <div class="loan-banner-stat">
        <div class="loan-banner-title">Loan Amount</div>
        <div class="loan-banner-value">&#2547;<?= number_format($loan['loan_amount'], 2) ?></div>
        <div class="loan-banner-small">Bangladeshi Taka (BDT)</div>
      </div>
      <div class="loan-banner-stat">
        <div class="loan-banner-title">Monthly Payment (EMI)</div>
        <div class="loan-banner-value">&#2547;<?= number_format($loan['monthly_payment'], 2) ?></div>
        <div class="loan-banner-small"><?= $loan['loan_term_years'] ?> yrs &middot; <?= $loan['interest_rate'] ?>% p.a.</div>
      </div>
      <div class="loan-banner-stat">
        <div class="loan-banner-title">Total Payable</div>
        <div class="loan-banner-value">&#2547;<?= number_format($loan['total_payment'], 2) ?></div>
        <div class="loan-banner-small">Incl. &#2547;<?= number_format($loan['total_interest'], 2) ?> interest</div>
      </div>
      <div class="loan-banner-stat" style="text-align:center;">
        <div class="loan-banner-title">Admin Status</div>
        <?php $st = strtolower($loan['status']); ?>
        <div class="status-large <?= $st ?>">
          <?php if ($st === 'pending'): ?>&#9203; Pending
          <?php elseif ($st === 'approved'): ?>&#10003; Approved
          <?php else: ?>&#10005; Rejected<?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ACTIONS BAR -->
    <div class="actions-bar">
      <strong style="flex:1;font-size:.9rem;color:var(--navy-900);">Admin Decision:</strong>
      <?php if ($loan['status'] !== 'Approved'): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="approve_loan">
          <button type="submit" class="btn btn-brass" onclick="return confirm('Approve this loan application?');">
            &#10003; Approve Loan
          </button>
        </form>
      <?php endif; ?>
      <?php if ($loan['status'] !== 'Rejected'): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="reject_loan">
          <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this loan application?');">
            &#10005; Reject Loan
          </button>
        </form>
      <?php endif; ?>
    </div>

    <div class="detail-grid">

      <!-- CUSTOMER INFO -->
      <div class="detail-card">
        <div class="detail-card-header">&#128100; Customer Information</div>
        <div class="detail-card-body">
          <div class="field-row">
            <span class="field-label">Full Name</span>
            <span class="field-value normal"><?= htmlspecialchars($loan['full_name']) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Email Address</span>
            <span class="field-value normal"><?= htmlspecialchars($loan['email']) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Phone</span>
            <span class="field-value normal"><?= htmlspecialchars($loan['phone'] ?: '&mdash;') ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Account ID</span>
            <span class="field-value">#<?= str_pad($loan['user_id'], 5, '0', STR_PAD_LEFT) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Member Since</span>
            <span class="field-value normal"><?= date('F j, Y', strtotime($loan['member_since'])) ?></span>
          </div>
          <div style="margin-top:14px;">
            <a href="customer_view.php?id=<?= $loan['user_id'] ?>" class="btn btn-outline btn-sm">View All Applications</a>
          </div>
        </div>
      </div>

      <!-- LOAN FINANCIAL DETAILS -->
      <div class="detail-card">
        <div class="detail-card-header">&#128176; Loan Financial Details (BDT &#2547;)</div>
        <div class="detail-card-body">
          <div class="field-row">
            <span class="field-label">Requested Amount</span>
            <span class="field-value">&#2547;<?= number_format($loan['loan_amount'], 2) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Interest Rate (p.a.)</span>
            <span class="field-value"><?= number_format($loan['interest_rate'], 2) ?>% / year</span>
          </div>
          <div class="field-row">
            <span class="field-label">Loan Term</span>
            <span class="field-value"><?= $loan['loan_term_years'] ?> Years (<?= $loan['loan_term_months'] ?> Months)</span>
          </div>
          <div class="field-row">
            <span class="field-label">Monthly EMI</span>
            <span class="field-value" style="color:var(--brass-dark);">&#2547;<?= number_format($loan['monthly_payment'], 2) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Total Interest</span>
            <span class="field-value">&#2547;<?= number_format($loan['total_interest'], 2) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Total Repayment</span>
            <span class="field-value" style="color:var(--navy-900);">&#2547;<?= number_format($loan['total_payment'], 2) ?></span>
          </div>
        </div>
      </div>

      <!-- APPLICANT FINANCIAL PROFILE -->
      <div class="detail-card">
        <div class="detail-card-header">&#128202; Applicant Financial Profile</div>
        <div class="detail-card-body">
          <div class="field-row">
            <span class="field-label">Applicant Monthly Income</span>
            <span class="field-value">&#2547;<?= number_format($loan['applicant_income'], 2) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Co-Applicant Monthly Income</span>
            <span class="field-value">&#2547;<?= number_format($loan['coapplicant_income'], 2) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Total Combined Income</span>
            <span class="field-value" style="color:var(--sage);">&#2547;<?= number_format($loan['applicant_income'] + $loan['coapplicant_income'], 2) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Credit History</span>
            <span class="field-value normal">
              <?php if ($loan['credit_history'] == 1): ?>
                <span class="badge sage">&#10003; Clean Record (1)</span>
              <?php else: ?>
                <span class="badge brick">&#10005; Poor / No Record (0)</span>
              <?php endif; ?>
            </span>
          </div>
          <div class="field-row">
            <span class="field-label">Number of Dependents</span>
            <span class="field-value"><?= $loan['dependents'] ?><?= $loan['dependents'] >= 3 ? '+' : '' ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Debt-to-Income Ratio</span>
            <?php
              $combined = $loan['applicant_income'] + $loan['coapplicant_income'];
              $dti = $combined > 0 ? round(($loan['monthly_payment'] / $combined) * 100, 1) : 0;
            ?>
            <span class="field-value" style="color:<?= $dti > 40 ? 'var(--brick)' : 'var(--sage)' ?>;">
              <?= $dti ?>%
            </span>
          </div>
        </div>
      </div>

      <!-- PERSONAL & PROPERTY PROFILE -->
      <div class="detail-card">
        <div class="detail-card-header">&#127968; Personal &amp; Property Profile</div>
        <div class="detail-card-body">
          <div class="field-row">
            <span class="field-label">Education Level</span>
            <span class="field-value normal">
              <?php if ($loan['education'] === 'Graduate'): ?>
                <span class="badge sage">&#127891; Graduate</span>
              <?php else: ?>
                <span class="badge brass">Not Graduate</span>
              <?php endif; ?>
            </span>
          </div>
          <div class="field-row">
            <span class="field-label">Self-Employed</span>
            <span class="field-value normal">
              <?php if ($loan['self_employed'] === 'Yes'): ?>
                <span class="badge brass">Yes &mdash; Self-Employed</span>
              <?php else: ?>
                <span class="badge sage">No &mdash; Salaried/Other</span>
              <?php endif; ?>
            </span>
          </div>
          <div class="field-row">
            <span class="field-label">Property Area</span>
            <span class="field-value normal">
              <?php
              $area_icons = ['Urban' => '&#127749;', 'Semiurban' => '&#127960;', 'Rural' => '&#127807;'];
              $icon = $area_icons[$loan['property_area']] ?? '&#128205;';
              ?>
              <?= $icon ?> <?= htmlspecialchars($loan['property_area']) ?>
            </span>
          </div>
          <div class="field-row">
            <span class="field-label">Application Date</span>
            <span class="field-value normal"><?= date('F j, Y', strtotime($loan['created_at'])) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Application Time</span>
            <span class="field-value normal"><?= date('g:i A', strtotime($loan['created_at'])) ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Loan Reference ID</span>
            <span class="field-value">#<?= str_pad($loan['id'], 5, '0', STR_PAD_LEFT) ?></span>
          </div>
        </div>
      </div>

    </div><!-- /.detail-grid -->

    <!-- AI PREDICTION ANALYSIS FULL -->
    <div class="detail-card" style="margin-bottom:32px;">
      <div class="detail-card-header" style="background:linear-gradient(90deg,var(--navy-800),var(--navy-700));">
        &#129302; AI / ML Eligibility Prediction Analysis
      </div>
      <div class="detail-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;flex-wrap:wrap;">
          <div>
            <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;">ML Model Result</div>
            <?php if ($loan['prediction_result'] === 'Approved'): ?>
              <div style="font-size:1.4rem;font-weight:800;color:var(--sage);">&#10003; AI Predicts: APPROVED</div>
              <div style="font-size:.85rem;color:var(--text-muted);margin-top:4px;">High loan eligibility based on financial parameters.</div>
            <?php else: ?>
              <div style="font-size:1.4rem;font-weight:800;color:var(--brick);">&#10005; AI Predicts: REJECTED</div>
              <div style="font-size:.85rem;color:var(--text-muted);margin-top:4px;">Lower eligibility based on income or credit profile.</div>
            <?php endif; ?>
          </div>
          <div>
            <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;">Confidence Score</div>
            <div style="font-size:2.2rem;font-weight:800;color:var(--navy-900);font-family:var(--mono);">
              <?= number_format($loan['prediction_confidence'] ?? 0, 1) ?>%
            </div>
            <div style="background:var(--line);height:8px;border-radius:4px;overflow:hidden;margin-top:8px;">
              <div style="height:100%;width:<?= min(100, $loan['prediction_confidence'] ?? 0) ?>%;border-radius:4px;background:linear-gradient(90deg,var(--brass),#f0b840);"></div>
            </div>
          </div>
        </div>

        <!-- COMPLETE DATASET TABLE -->
        <div style="border-top:1px solid var(--line);padding-top:20px;">
          <div style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px;font-weight:700;">
            &#128203; Complete Application Fields (Dataset Reference)
          </div>
          <div style="overflow-x:auto;">
            <table style="width:100%;font-size:.85rem;border-collapse:collapse;">
              <thead>
                <tr style="background:var(--paper);">
                  <th style="padding:9px 14px;text-align:left;font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid var(--line);">Field Label</th>
                  <th style="padding:9px 14px;text-align:left;font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid var(--line);">Submitted Value</th>
                  <th style="padding:9px 14px;text-align:left;font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid var(--line);">Dataset Column</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $dataset_fields = [
                    ['Applicant Monthly Income',     '&#2547;' . number_format($loan['applicant_income'], 2),      'ApplicantIncome'],
                    ['Co-Applicant Monthly Income',  '&#2547;' . number_format($loan['coapplicant_income'], 2),    'CoapplicantIncome'],
                    ['Loan Amount (BDT)',             '&#2547;' . number_format($loan['loan_amount'], 2),           'LoanAmount'],
                    ['Loan Amount Term (Months)',     $loan['loan_term_months'] . ' Months',                       'Loan_Amount_Term'],
                    ['Interest Rate (per annum)',     number_format($loan['interest_rate'], 2) . '% / year',       'interest_rate'],
                    ['Loan Term (Years)',             $loan['loan_term_years'] . ' Years',                         'loan_term_years'],
                    ['Monthly EMI Payment',          '&#2547;' . number_format($loan['monthly_payment'], 2),      'monthly_payment'],
                    ['Total Interest Payable',       '&#2547;' . number_format($loan['total_interest'], 2),       'total_interest'],
                    ['Total Repayment Amount',       '&#2547;' . number_format($loan['total_payment'], 2),        'total_payment'],
                    ['Credit History',               ($loan['credit_history'] == 1 ? 'Clean Record (1)' : 'Poor / No Record (0)'), 'Credit_History'],
                    ['Number of Dependents',         $loan['dependents'] . ($loan['dependents'] >= 3 ? '+' : ''), 'Dependents'],
                    ['Education Level',              htmlspecialchars($loan['education']),                         'Education'],
                    ['Self Employed',                htmlspecialchars($loan['self_employed']),                     'Self_Employed'],
                    ['Property Area',                htmlspecialchars($loan['property_area']),                     'Property_Area'],
                    ['ML Prediction Result',         htmlspecialchars($loan['prediction_result'] ?? 'N/A'),        'Loan_Status (target)'],
                    ['Prediction Confidence',        number_format($loan['prediction_confidence'] ?? 0, 1) . '%', 'prediction_confidence'],
                    ['Admin Approval Status',        htmlspecialchars($loan['status']),                            'status'],
                ];
                foreach ($dataset_fields as $i => [$label, $value, $col_name]):
                ?>
                <tr style="border-bottom:1px solid var(--line);<?= ($i % 2 === 0) ? 'background:#fafbf9;' : '' ?>">
                  <td style="padding:9px 14px;font-weight:600;color:var(--text);"><?= $label ?></td>
                  <td style="padding:9px 14px;font-family:var(--mono);font-weight:700;color:var(--navy-900);"><?= $value ?></td>
                  <td style="padding:9px 14px;font-family:var(--mono);font-size:.78rem;color:var(--text-muted);background:#f5f7f3;"><?= htmlspecialchars($col_name) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>

<?php include '../includes/footer.php'; ?>
