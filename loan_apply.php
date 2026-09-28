<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/predict.php';
require_login();
if (is_admin()) redirect('admin/dashboard.php');

$user = current_user();
$errors = [];
$result = null;
$old = [
    'loan_amount' => '', 'loan_term_months' => '360', 'applicant_income' => '',
    'coapplicant_income' => '0', 'credit_history' => '1', 'dependents' => '0',
    'education' => 'Graduate', 'self_employed' => 'No', 'property_area' => 'Urban',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        foreach ($old as $key => $default) {
            $old[$key] = trim($_POST[$key] ?? $default);
        }

        if (!is_numeric($old['loan_amount']) || $old['loan_amount'] <= 0) $errors[] = 'Enter a valid loan amount.';
        if (!is_numeric($old['loan_term_months']) || $old['loan_term_months'] <= 0) $errors[] = 'Enter a valid loan term.';
        if (!is_numeric($old['applicant_income']) || $old['applicant_income'] < 0) $errors[] = 'Enter a valid applicant income.';
        if (!is_numeric($old['coapplicant_income']) || $old['coapplicant_income'] < 0) $errors[] = 'Enter a valid co-applicant income.';

        if (!$errors) {
            $prediction = predict_loan($old);

            $db = get_db();
            $stmt = $db->prepare(
                'INSERT INTO loans (user_id, loan_amount, loan_term_months, applicant_income, coapplicant_income,
                    credit_history, dependents, education, self_employed, property_area,
                    prediction_result, prediction_confidence, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'], $old['loan_amount'], $old['loan_term_months'], $old['applicant_income'],
                $old['coapplicant_income'], $old['credit_history'], $old['dependents'], $old['education'],
                $old['self_employed'], $old['property_area'],
                $prediction['result'], $prediction['confidence'], $prediction['result'],
            ]);
            $loanId = $db->lastInsertId();
            $result = $prediction;
            $result['loan_id'] = $loanId;
        }
    }
}

$base_path = '';
$page_title = 'Apply for a loan — BU Bank Ltd';
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
        <div class="eyebrow">Loan module · Prediction module</div>
        <h1>Apply for a loan</h1>
        <p>Your application is scored instantly against five key eligibility signals.</p>
      </div>
    </div>

    <?php if ($result): ?>
      <div class="result-panel" style="margin-bottom:32px;">
        <div class="result-stamp-wrap">
          <div class="result-stamp <?= $result['result'] === 'Approved' ? 'approved' : 'rejected' ?>">
            <?= strtoupper($result['result']) ?>
            <small>Loan #<?= str_pad($result['loan_id'], 5, '0', STR_PAD_LEFT) ?></small>
          </div>
        </div>
        <div>
          <h3 class="mt-0">Model confidence: <?= $result['confidence'] ?>%</h3>
          <div class="confidence-bar"><span style="width:<?= $result['confidence'] ?>%"></span></div>
          <p class="text-muted" style="margin:6px 0 0;font-size:.85rem;">
            <?= $result['result'] === 'Approved'
                ? 'Based on the details provided, this application is likely eligible.'
                : 'Based on the details provided, this application is unlikely to be eligible as submitted.' ?>
          </p>
          <ul class="factor-list">
            <?php foreach ($result['factors'] as $label => $note): ?>
              <li><span><?= htmlspecialchars($label) ?></span> <b><?= htmlspecialchars($note) ?></b></li>
            <?php endforeach; ?>
          </ul>
          <div style="margin-top:20px;">
            <a href="loan_history.php" class="btn btn-outline btn-sm">View in loan history</a>
            <a href="loan_apply.php" class="btn btn-brass btn-sm">Apply for another loan</a>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="card" style="padding:32px;max-width:760px;">
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-row two">
          <div>
            <label for="applicant_income">Applicant monthly income ($)</label>
            <input type="number" min="0" step="0.01" id="applicant_income" name="applicant_income" value="<?= htmlspecialchars($old['applicant_income']) ?>" required>
          </div>
          <div>
            <label for="coapplicant_income">Co-applicant monthly income ($)</label>
            <input type="number" min="0" step="0.01" id="coapplicant_income" name="coapplicant_income" value="<?= htmlspecialchars($old['coapplicant_income']) ?>">
          </div>
        </div>

        <div class="form-row two">
          <div>
            <label for="loan_amount">Loan amount requested ($)</label>
            <input type="number" min="0" step="0.01" id="loan_amount" name="loan_amount" value="<?= htmlspecialchars($old['loan_amount']) ?>" required>
          </div>
          <div>
            <label for="loan_term_months">Loan term (months)</label>
            <select id="loan_term_months" name="loan_term_months">
              <?php foreach ([120, 180, 240, 300, 360] as $t): ?>
                <option value="<?= $t ?>" <?= $old['loan_term_months'] == $t ? 'selected' : '' ?>><?= $t ?> months</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row two">
          <div>
            <label for="dependents">Dependents</label>
            <select id="dependents" name="dependents">
              <?php foreach ([0, 1, 2, 3] as $d): ?>
                <option value="<?= $d ?>" <?= $old['dependents'] == $d ? 'selected' : '' ?>><?= $d ?><?= $d === 3 ? '+' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="property_area">Property area</label>
            <select id="property_area" name="property_area">
              <?php foreach (['Urban', 'Semiurban', 'Rural'] as $a): ?>
                <option value="<?= $a ?>" <?= $old['property_area'] === $a ? 'selected' : '' ?>><?= $a ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <label>Credit history</label>
          <div class="radio-group">
            <label><input type="radio" name="credit_history" value="1" <?= $old['credit_history'] == '1' ? 'checked' : '' ?>> Clean repayment record</label>
            <label><input type="radio" name="credit_history" value="0" <?= $old['credit_history'] == '0' ? 'checked' : '' ?>> No / poor repayment record</label>
          </div>
        </div>

        <div class="form-row two">
          <div>
            <label>Education</label>
            <div class="radio-group">
              <label><input type="radio" name="education" value="Graduate" <?= $old['education'] === 'Graduate' ? 'checked' : '' ?>> Graduate</label>
              <label><input type="radio" name="education" value="Not Graduate" <?= $old['education'] === 'Not Graduate' ? 'checked' : '' ?>> Not graduate</label>
            </div>
          </div>
          <div>
            <label>Self-employed</label>
            <div class="radio-group">
              <label><input type="radio" name="self_employed" value="No" <?= $old['self_employed'] === 'No' ? 'checked' : '' ?>> No</label>
              <label><input type="radio" name="self_employed" value="Yes" <?= $old['self_employed'] === 'Yes' ? 'checked' : '' ?>> Yes</label>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-brass btn-block">Run eligibility prediction</button>
      </form>
    </div>
  </main>
</div>

<?php include 'includes/footer.php'; ?>
