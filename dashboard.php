<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
if (is_admin()) redirect('admin/dashboard.php');

$user = current_user();
$db   = get_db();

$stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$loans = $stmt->fetchAll();

$total    = count($loans);
$approved = count(array_filter($loans, fn($l) => $l['status'] === 'Approved'));
$pending  = count(array_filter($loans, fn($l) => $l['status'] === 'Pending'));
$rejected = count(array_filter($loans, fn($l) => $l['status'] === 'Rejected'));
$latest   = $loans[0] ?? null;

// Find first newly approved / rejected loan to highlight
$newly_approved = null;
$newly_rejected = null;
foreach ($loans as $l) {
    if ($l['status'] === 'Approved' && !$newly_approved) $newly_approved = $l;
    if ($l['status'] === 'Rejected' && !$newly_rejected) $newly_rejected = $l;
}

$base_path  = '';
$page_title = 'Customer Dashboard — BU Bank Ltd';
include 'includes/header.php';
?>

<style>
/* Congratulations banner */
.congrats-banner {
    background: linear-gradient(135deg, #1a4731 0%, #276940 60%, #3a8a58 100%);
    border-radius: 8px;
    padding: 28px 32px;
    margin-bottom: 28px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
    animation: bannerSlideIn .5s cubic-bezier(.4,0,.2,1);
}
@keyframes bannerSlideIn { from{opacity:0;transform:translateY(-16px);} to{opacity:1;transform:none;} }
.congrats-banner::after {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}
.congrats-icon {
    font-size: 3rem; line-height: 1; flex-shrink: 0;
}
.congrats-body { flex: 1; position: relative; z-index: 1; }
.congrats-title { font-size: 1.35rem; font-weight: 800; margin: 0 0 4px; }
.congrats-sub   { font-size: .9rem; color: #a0e0b8; margin: 0 0 12px; }
.congrats-detail {
    display: flex; gap: 20px; flex-wrap: wrap;
}
.congrats-detail span {
    background: rgba(255,255,255,0.12); border-radius: 4px;
    padding: 5px 12px; font-size: .82rem; font-family: var(--mono); font-weight: 700;
}

/* Rejection notice */
.reject-notice {
    background: #fff;
    border: 2px solid var(--brick);
    border-radius: 8px;
    padding: 24px 28px;
    margin-bottom: 28px;
    display: flex;
    gap: 18px;
    align-items: flex-start;
    animation: bannerSlideIn .5s cubic-bezier(.4,0,.2,1);
}
.reject-notice-icon {
    width: 44px; height: 44px; border-radius: 50%;
    background: var(--brick-tint); color: var(--brick);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0; margin-top: 2px;
}
.reject-notice-body { flex: 1; }
.reject-notice-title { font-size: 1rem; font-weight: 800; color: var(--brick); margin: 0 0 4px; }
.reject-notice-sub   { font-size: .84rem; color: var(--text-muted); margin: 0 0 10px; }
.reject-reason-box {
    background: #fdf2f0; border: 1px solid #f5c8c0;
    border-radius: 4px; padding: 12px 14px;
    font-size: .88rem; color: var(--text); line-height: 1.55;
    font-style: italic;
}
</style>

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
       
        <h1>Welcome, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></h1>
        <p>Overview of your submitted loan applications and approval status.</p>
      </div>
      <a href="loan_apply.php" class="btn btn-brass">Apply for a new loan</a>
    </div>

    <!-- ✅ CONGRATULATIONS BANNER — show if any loan approved -->
    <?php if ($newly_approved): ?>
    <div class="congrats-banner">
      
      <div class="congrats-body">
        <div class="congrats-title">Congratulations! Your loan has been approved!</div>
        <div class="congrats-sub">
          Loan #<?= str_pad($newly_approved['id'], 5, '0', STR_PAD_LEFT) ?> &mdash;
          The bank administration has approved your application.
          <?php if (!empty($newly_approved['reviewed_at'])): ?>
            Reviewed on <?= date('F j, Y', strtotime($newly_approved['reviewed_at'])) ?>.
          <?php endif; ?>
        </div>
        <div class="congrats-detail">
          <span>&#2547;<?= number_format($newly_approved['loan_amount'], 2) ?> Loan</span>
          <span><?= $newly_approved['loan_term_years'] ?> Years &middot; <?= number_format($newly_approved['interest_rate'], 2) ?>% p.a.</span>
          <span>EMI: &#2547;<?= number_format($newly_approved['monthly_payment'], 2) ?>/mo</span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ❌ REJECTION NOTICE — show if any loan rejected -->
    <?php if ($newly_rejected): ?>
    <div class="reject-notice">
      <div class="reject-notice-icon">&#10005;</div>
      <div class="reject-notice-body">
        <div class="reject-notice-title">
          Loan Application #<?= str_pad($newly_rejected['id'], 5, '0', STR_PAD_LEFT) ?> was Rejected
        </div>
        <div class="reject-notice-sub">
          We regret to inform you that your loan application has not been approved at this time.
          <?php if (!empty($newly_rejected['reviewed_at'])): ?>
            Reviewed on <?= date('F j, Y', strtotime($newly_rejected['reviewed_at'])) ?>.
          <?php endif; ?>
        </div>
        <?php if (!empty($newly_rejected['rejection_reason'])): ?>
          <div style="font-size:.78rem;font-weight:700;color:var(--brick);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">
            Reason provided by the bank:
          </div>
          <div class="reject-reason-box">
            "<?= htmlspecialchars($newly_rejected['rejection_reason']) ?>"
          </div>
        <?php else: ?>
          <div class="reject-reason-box" style="color:var(--text-muted);">
            No specific reason was provided. Please contact the bank for more information.
          </div>
        <?php endif; ?>
        <div style="margin-top:14px;">
          <a href="loan_apply.php" class="btn btn-brass btn-sm">Apply with different parameters</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stat-grid">
      <div class="stat-card"><div class="label">Total Applications</div><div class="value"><?= $total ?></div></div>
      <div class="stat-card"><div class="label">Pending Approval</div><div class="value brass"><?= $pending ?></div></div>
      <div class="stat-card"><div class="label">Approved Loans</div><div class="value sage"><?= $approved ?></div></div>
      <div class="stat-card"><div class="label">Rejected</div><div class="value brick"><?= $rejected ?></div></div>
    </div>

    <!-- LATEST APPLICATION TABLE -->
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
              <th>Monthly EMI</th>
              <th>Applied</th>
              <th>AI Advisory</th>
              <th>Approval Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="mono">#<?= str_pad($latest['id'], 5, '0', STR_PAD_LEFT) ?></td>
              <td class="mono" style="font-weight:700;">&#2547;<?= number_format($latest['loan_amount'], 2) ?></td>
              <td>
                <?= $latest['loan_term_years'] ?? round(($latest['loan_term_months'] ?? 12)/12) ?> Yrs (<?= $latest['loan_term_months'] ?> Mo)<br>
                <span class="text-muted mono" style="font-size:.78rem;"><?= number_format($latest['interest_rate'] ?? 8.5, 2) ?>% / yr</span>
              </td>
              <td class="mono" style="font-weight:700;color:var(--brass-dark);">
                &#2547;<?= number_format($latest['monthly_payment'] ?? 0, 2) ?>
              </td>
              <td><?= date('M j, Y', strtotime($latest['created_at'])) ?></td>
              <td>
                <?php if ($latest['prediction_result'] === 'Approved'): ?>
                  <span class="badge sage">&#10003; AI Eligible</span>
                <?php else: ?>
                  <span class="badge brick">&#10005; AI Risk</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($latest['status'] === 'Approved'): ?>
                  <span class="badge sage" style="font-size:.82rem;padding:6px 14px;">&#10003; Approved</span>
                <?php elseif ($latest['status'] === 'Rejected'): ?>
                  <span class="badge brick" style="font-size:.82rem;padding:6px 14px;">&#10005; Rejected</span>
                  <?php if (!empty($latest['rejection_reason'])): ?>
                    <div style="margin-top:6px;">
                      <button onclick="document.getElementById('reasonModal').style.display='flex'"
                        style="font-size:.72rem;color:var(--brick);background:none;border:none;cursor:pointer;padding:0;text-decoration:underline;font-family:var(--sans);">
                        View reason &#8250;
                      </button>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge brass" style="font-size:.82rem;padding:6px 14px;">&#9679; Pending Approval</span>
                <?php endif; ?>
              </td>
            </tr>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <div class="glyph">&#128196;</div>
          <p>You haven't applied for a loan yet.</p>
          <a href="loan_apply.php" class="btn btn-brass btn-sm">Start an application</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Rejection reason inline modal (for latest loan row) -->
<?php if ($latest && $latest['status'] === 'Rejected' && !empty($latest['rejection_reason'])): ?>
<div id="reasonModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(11,27,43,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;"
  onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:8px;padding:32px;max-width:460px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,0.3);position:relative;">
    <button onclick="document.getElementById('reasonModal').style.display='none'"
      style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:1.3rem;cursor:pointer;color:#888;">&#10005;</button>
    <div style="font-size:.7rem;color:var(--brick);font-family:var(--mono);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px;">Rejection Notice</div>
    <h3 style="margin:0 0 6px;color:var(--brick);">Loan Application Rejected</h3>
    <p style="font-size:.85rem;color:var(--text-muted);margin:0 0 16px;">
      Application #<?= str_pad($latest['id'], 5, '0', STR_PAD_LEFT) ?> &mdash;
      <?php if (!empty($latest['reviewed_at'])): ?>
        Reviewed <?= date('F j, Y', strtotime($latest['reviewed_at'])) ?>
      <?php endif; ?>
    </p>
    <div style="background:#fdf2f0;border:1px solid #f5c8c0;border-radius:4px;padding:14px 16px;font-size:.9rem;line-height:1.6;font-style:italic;color:var(--text);">
      "<?= htmlspecialchars($latest['rejection_reason']) ?>"
    </div>
    <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
      <a href="loan_apply.php" class="btn btn-brass btn-sm">Apply again</a>
      <button onclick="document.getElementById('reasonModal').style.display='none'" class="btn btn-outline btn-sm">Close</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
