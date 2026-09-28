<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/predict.php';
require_login();
if (is_admin()) redirect('admin/dashboard.php');

$user = current_user();
$admin_interest_rate = get_annual_interest_rate();
$errors = [];
$submitted_loan = null;

$old = [
    'loan_amount'       => '500000',
    'loan_term_years'   => '5',
    'applicant_income'  => '45000',
    'coapplicant_income'=> '0',
    'credit_history'    => '1',
    'dependents'        => '0',
    'education'         => 'Graduate',
    'self_employed'     => 'No',
    'property_area'     => 'Urban',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        foreach ($old as $key => $default) {
            $old[$key] = trim($_POST[$key] ?? $default);
        }

        if (!is_numeric($old['loan_amount']) || $old['loan_amount'] <= 0) {
            $errors[] = 'Enter a valid loan amount in BDT.';
        }
        if (!is_numeric($old['loan_term_years']) || $old['loan_term_years'] <= 0 || $old['loan_term_years'] > 40) {
            $errors[] = 'Enter a valid loan term in years (1 to 40 years).';
        }
        if (!is_numeric($old['applicant_income']) || $old['applicant_income'] < 0) {
            $errors[] = 'Enter a valid applicant monthly income in BDT.';
        }
        if (!is_numeric($old['coapplicant_income']) || $old['coapplicant_income'] < 0) {
            $errors[] = 'Enter a valid co-applicant income in BDT.';
        }

        if (!$errors) {
            $loan_amount     = (float)$old['loan_amount'];
            $loan_term_years = (int)$old['loan_term_years'];
            $loan_term_months= $loan_term_years * 12;

            // Interest & Monthly payment calculation (EMI formula)
            $r = ($admin_interest_rate / 100) / 12;
            $n = $loan_term_months;
            if ($r > 0 && $n > 0) {
                $monthly_payment = ($loan_amount * $r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
            } else {
                $monthly_payment = $loan_amount / ($n ?: 1);
            }
            $total_payment  = $monthly_payment * $n;
            $total_interest = $total_payment - $loan_amount;

            // Prepare array for prediction engine
            $prediction_input = $old;
            $prediction_input['loan_term_months'] = $loan_term_months;
            $prediction = predict_loan($prediction_input);

            $db = get_db();
            $stmt = $db->prepare(
                'INSERT INTO loans (
                    user_id, loan_amount, interest_rate, loan_term_years, loan_term_months,
                    monthly_payment, total_interest, total_payment,
                    applicant_income, coapplicant_income, credit_history, dependents,
                    education, self_employed, property_area,
                    prediction_result, prediction_confidence, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'],
                $loan_amount,
                $admin_interest_rate,
                $loan_term_years,
                $loan_term_months,
                round($monthly_payment, 2),
                round($total_interest, 2),
                round($total_payment, 2),
                $old['applicant_income'],
                $old['coapplicant_income'],
                $old['credit_history'],
                $old['dependents'],
                $old['education'],
                $old['self_employed'],
                $old['property_area'],
                $prediction['result'],
                $prediction['confidence'],
                'Pending' // Default status is always Pending awaiting Admin Approval
            ]);

            $loan_id = $db->lastInsertId();
            $submitted_loan = [
                'id'              => $loan_id,
                'loan_amount'     => $loan_amount,
                'interest_rate'   => $admin_interest_rate,
                'loan_term_years' => $loan_term_years,
                'monthly_payment' => $monthly_payment,
                'total_interest'  => $total_interest,
                'total_payment'   => $total_payment,
                'prediction'      => $prediction,
                'status'          => 'Pending'
            ];
        }
    }
}

$base_path = '';
$page_title = 'Apply for a Loan — BU Bank Ltd';
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
      <a href="loan_apply.php" class="active">Apply for a loan</a>
      <a href="loan_history.php">Loan history</a>
      <div class="divider"></div>
      <a href="logout.php">Log out</a>
    </nav>
  </aside>

  <main class="main-content">
    <div class="page-head">
      <div>
        <div class="eyebrow">Loan module · Online Application</div>
        <h1>Apply for a Loan (in BDT ৳)</h1>
        <p>Interactive loan calculator with instant monthly payment estimation &amp; automated eligibility check in Bangladeshi Taka (BDT ৳).</p>
      </div>
    </div>

    <?php if ($submitted_loan): ?>
      <div class="alert alert-info" style="margin-bottom:24px;font-size:.95rem;padding:16px;">
        <strong>⏳ Application Submitted Successfully!</strong><br>
        Your application <strong>#<?= str_pad($submitted_loan['id'], 5, '0', STR_PAD_LEFT) ?></strong> is currently <strong>Pending Admin Approval</strong>. The bank administration will review your request shortly.
      </div>

      <div class="result-panel" style="margin-bottom:32px;">
        <div class="result-stamp-wrap">
          <div class="result-stamp pending">
            PENDING
            <small>Awaiting Admin Review</small>
          </div>
        </div>
        <div>
          <h3 class="mt-0" style="margin-bottom:8px;">AI Eligibility Advisory: <?= $submitted_loan['prediction']['result'] ?> (<?= $submitted_loan['prediction']['confidence'] ?>% Confidence)</h3>
          <div class="confidence-bar"><span style="width:<?= $submitted_loan['prediction']['confidence'] ?>%"></span></div>
          <p class="text-muted" style="margin:8px 0 16px;font-size:.85rem;">
            <?= $submitted_loan['prediction']['result'] === 'Approved'
                ? 'AI prediction system indicates high eligibility probability based on financial parameters.'
                : 'AI prediction system notes lower eligibility probability based on current income or credit criteria.' ?>
          </p>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;background:var(--paper);padding:14px;border-radius:6px;margin-bottom:16px;">
            <div><small class="text-muted" style="display:block;">Loan Amount</small><strong class="mono">৳<?= number_format($submitted_loan['loan_amount'], 2) ?></strong></div>
            <div><small class="text-muted" style="display:block;">Interest Rate</small><strong class="mono"><?= number_format($submitted_loan['interest_rate'], 2) ?>% / yr</strong></div>
            <div><small class="text-muted" style="display:block;">Term</small><strong class="mono"><?= $submitted_loan['loan_term_years'] ?> Years</strong></div>
            <div><small class="text-muted" style="display:block;">Monthly Payment</small><strong class="mono" style="color:var(--brass-dark);">৳<?= number_format($submitted_loan['monthly_payment'], 2) ?></strong></div>
            <div><small class="text-muted" style="display:block;">Total Interest</small><strong class="mono">৳<?= number_format($submitted_loan['total_interest'], 2) ?></strong></div>
            <div><small class="text-muted" style="display:block;">Total Payable</small><strong class="mono">৳<?= number_format($submitted_loan['total_payment'], 2) ?></strong></div>
          </div>
          <div>
            <a href="loan_history.php" class="btn btn-outline btn-sm">View in loan history</a>
            <a href="loan_apply.php" class="btn btn-brass btn-sm">Submit another application</a>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div style="display:grid;grid-template-columns: 1fr 340px;gap:24px;align-items:start;">
      <!-- FORM CARD -->
      <div class="card" style="padding:32px;">
        <h3 style="margin-top:0;margin-bottom:20px;font-size:1.15rem;border-bottom:1px solid var(--line);padding-bottom:12px;">Loan Details &amp; Financial Information (BDT ৳)</h3>
        <form method="post" id="loanForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <!-- LOAN CALCULATOR INPUTS -->
          <div class="form-row two">
            <div>
              <label for="loan_amount">Requested Loan Amount (BDT ৳)</label>
              <input type="number" min="5000" step="5000" id="loan_amount" name="loan_amount" value="<?= htmlspecialchars($old['loan_amount']) ?>" required>
              <div class="hint">e.g. ৳50,000 to ৳10,000,000 BDT</div>
            </div>
            <div>
              <label for="loan_term_years">Pay Period (Paid Years)</label>
              <select id="loan_term_years" name="loan_term_years" required>
                <?php foreach ([1, 2, 3, 4, 5, 7, 10, 15, 20, 25, 30] as $y): ?>
                  <option value="<?= $y ?>" <?= $old['loan_term_years'] == $y ? 'selected' : '' ?>><?= $y ?> Year<?= $y > 1 ? 's' : '' ?> (<?= $y * 12 ?> Months)</option>
                <?php endforeach; ?>
              </select>
              <div class="hint">Select repayment duration in years</div>
            </div>
          </div>

          <div class="form-row two">
            <div>
              <label for="interest_rate_display">Interest Rate per Year (%)</label>
              <input type="text" id="interest_rate_display" value="<?= number_format($admin_interest_rate, 2) ?>%" readonly style="background:#f0f2ee;font-weight:700;color:var(--navy-900);cursor:not-allowed;">
              <div class="hint">Configured by Bank Administration</div>
            </div>
            <div>
              <label for="applicant_income">Applicant Monthly Income (BDT ৳)</label>
              <input type="number" min="0" step="1000" id="applicant_income" name="applicant_income" value="<?= htmlspecialchars($old['applicant_income']) ?>" required>
            </div>
          </div>

          <div class="form-row two">
            <div>
              <label for="coapplicant_income">Co-Applicant Monthly Income (BDT ৳)</label>
              <input type="number" min="0" step="1000" id="coapplicant_income" name="coapplicant_income" value="<?= htmlspecialchars($old['coapplicant_income']) ?>">
            </div>
            <div>
              <label for="dependents">Dependents</label>
              <select id="dependents" name="dependents">
                <?php foreach ([0, 1, 2, 3] as $d): ?>
                  <option value="<?= $d ?>" <?= $old['dependents'] == $d ? 'selected' : '' ?>><?= $d ?><?= $d === 3 ? '+' : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row two">
            <div>
              <label for="property_area">Property Area</label>
              <select id="property_area" name="property_area">
                <?php foreach (['Urban', 'Semiurban', 'Rural'] as $a): ?>
                  <option value="<?= $a ?>" <?= $old['property_area'] === $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Education Level</label>
              <div class="radio-group" style="margin-top:6px;">
                <label><input type="radio" name="education" value="Graduate" <?= $old['education'] === 'Graduate' ? 'checked' : '' ?>> Graduate</label>
                <label><input type="radio" name="education" value="Not Graduate" <?= $old['education'] === 'Not Graduate' ? 'checked' : '' ?>> Not Graduate</label>
              </div>
            </div>
          </div>

          <div class="form-row two">
            <div>
              <label>Credit History</label>
              <div class="radio-group" style="margin-top:6px;">
                <label><input type="radio" name="credit_history" value="1" <?= $old['credit_history'] == '1' ? 'checked' : '' ?>> Clean record (1)</label>
                <label><input type="radio" name="credit_history" value="0" <?= $old['credit_history'] == '0' ? 'checked' : '' ?>> Poor / No record (0)</label>
              </div>
            </div>
            <div>
              <label>Self-Employed Status</label>
              <div class="radio-group" style="margin-top:6px;">
                <label><input type="radio" name="self_employed" value="No" <?= $old['self_employed'] === 'No' ? 'checked' : '' ?>> No</label>
                <label><input type="radio" name="self_employed" value="Yes" <?= $old['self_employed'] === 'Yes' ? 'checked' : '' ?>> Yes</label>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-brass btn-block" style="margin-top:10px;padding:14px;font-size:1rem;">Submit Application for Admin Approval</button>
        </form>
      </div>

      <!-- LIVE LOAN CALCULATOR PREVIEW SIDEBAR -->
      <div class="card" style="padding:24px;background:var(--navy-900);color:#fff;border-color:var(--navy-800);position:sticky;top:90px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;color:var(--brass-tint);font-family:var(--mono);font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;">
          <span>🧮 Live Loan Calculator (BDT ৳)</span>
        </div>

        <div style="border-bottom:1px solid rgba(255,255,255,0.12);padding-bottom:16px;margin-bottom:16px;">
          <div style="font-size:.8rem;color:#a0b0b9;text-transform:uppercase;letter-spacing:.05em;">Estimated Monthly Payment</div>
          <div id="calc_monthly" style="font-family:var(--mono);font-size:2rem;font-weight:700;color:var(--brass-tint);margin-top:4px;">৳0.00</div>
          <div style="font-size:.76rem;color:#8fa0a9;margin-top:2px;">Per month for <span id="calc_term_text">5 years</span></div>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;font-size:.88rem;margin-bottom:20px;">
          <div style="display:flex;justify-content:space-between;border-bottom:1px dashed rgba(255,255,255,0.1);padding-bottom:6px;">
            <span style="color:#b0c0c9;">Requested Amount:</span>
            <strong id="calc_principal" class="mono" style="color:#fff;">৳0.00</strong>
          </div>
          <div style="display:flex;justify-content:space-between;border-bottom:1px dashed rgba(255,255,255,0.1);padding-bottom:6px;">
            <span style="color:#b0c0c9;">Interest Rate / Year:</span>
            <strong class="mono" style="color:#fff;"><?= number_format($admin_interest_rate, 2) ?>%</strong>
          </div>
          <div style="display:flex;justify-content:space-between;border-bottom:1px dashed rgba(255,255,255,0.1);padding-bottom:6px;">
            <span style="color:#b0c0c9;">Total Interest:</span>
            <strong id="calc_interest" class="mono" style="color:var(--brass-tint);">৳0.00</strong>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <span style="color:#b0c0c9;">Total Payable Amount:</span>
            <strong id="calc_total" class="mono" style="color:#fff;">৳0.00</strong>
          </div>
        </div>

        <div style="background:rgba(255,255,255,0.06);padding:12px;border-radius:4px;font-size:.76rem;color:#a0b0ba;line-height:1.4;">
          📌 <em>Monthly payments are calculated automatically in BDT ৳ using standard compound amortized schedule at <strong><?= number_format($admin_interest_rate, 2) ?>%</strong> annual rate.</em>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const annualInterestRate = <?= json_encode((float)$admin_interest_rate) ?>;
    const loanAmountInput = document.getElementById('loan_amount');
    const loanTermSelect = document.getElementById('loan_term_years');

    const calcMonthly = document.getElementById('calc_monthly');
    const calcPrincipal = document.getElementById('calc_principal');
    const calcInterest = document.getElementById('calc_interest');
    const calcTotal = document.getElementById('calc_total');
    const calcTermText = document.getElementById('calc_term_text');

    function calculateLoan() {
        const principal = parseFloat(loanAmountInput.value) || 0;
        const years = parseInt(loanTermSelect.value) || 1;
        const months = years * 12;

        calcTermText.textContent = years + (years === 1 ? ' year' : ' years') + ' (' + months + ' months)';
        calcPrincipal.textContent = '৳' + principal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        if (principal <= 0 || months <= 0) {
            calcMonthly.textContent = '৳0.00';
            calcInterest.textContent = '৳0.00';
            calcTotal.textContent = '৳0.00';
            return;
        }

        const monthlyRate = (annualInterestRate / 100) / 12;
        let monthlyPayment = 0;

        if (monthlyRate > 0) {
            monthlyPayment = (principal * monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
        } else {
            monthlyPayment = principal / months;
        }

        const totalPayable = monthlyPayment * months;
        const totalInterest = totalPayable - principal;

        calcMonthly.textContent = '৳' + monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        calcInterest.textContent = '৳' + totalInterest.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        calcTotal.textContent = '৳' + totalPayable.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    loanAmountInput.addEventListener('input', calculateLoan);
    loanTermSelect.addEventListener('change', calculateLoan);

    // Initial calculation trigger
    calculateLoan();
});
</script>

<?php include 'includes/footer.php'; ?>
