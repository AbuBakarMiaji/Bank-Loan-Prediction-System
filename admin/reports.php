<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$user = current_user();
$db = get_db();

$totalLoans = (int)$db->query('SELECT COUNT(*) c FROM loans')->fetch()['c'];
$approved   = (int)$db->query("SELECT COUNT(*) c FROM loans WHERE prediction_result = 'Approved'")->fetch()['c'];
$rejected   = (int)$db->query("SELECT COUNT(*) c FROM loans WHERE prediction_result = 'Rejected'")->fetch()['c'];
$avgAmount  = (float)($db->query('SELECT AVG(loan_amount) a FROM loans')->fetch()['a'] ?? 0);
$totalExposure = (float)($db->query("SELECT SUM(loan_amount) s FROM loans WHERE prediction_result = 'Approved'")->fetch()['s'] ?? 0);

$approvalRate = $totalLoans ? round($approved / $totalLoans * 100, 1) : 0;

$byArea = $db->query(
    "SELECT property_area, COUNT(*) total,
        SUM(prediction_result = 'Approved') approved
     FROM loans GROUP BY property_area"
)->fetchAll();

$byEducation = $db->query(
    "SELECT education, COUNT(*) total,
        SUM(prediction_result = 'Approved') approved
     FROM loans GROUP BY education"
)->fetchAll();

$base_path = '../';
$page_title = 'Reports — Ledger';
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
      <a href="search_customer.php">Search customers</a>
      <a href="reports.php" class="active">Reports</a>
      <div class="divider"></div>
      <a href="../logout.php">Log out</a>
    </nav>
  </aside>

  <main class="main-content">
    <div class="page-head">
      <div>
        <div class="eyebrow">Admin module · Reports</div>
        <h1>Portfolio analytics</h1>
        <p>Approval trends and exposure across every application on record.</p>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card"><div class="label">Approval rate</div><div class="value sage"><?= $approvalRate ?>%</div></div>
      <div class="stat-card"><div class="label">Average loan amount</div><div class="value">$<?= number_format($avgAmount, 0) ?></div></div>
      <div class="stat-card"><div class="label">Approved exposure</div><div class="value brass">$<?= number_format($totalExposure, 0) ?></div></div>
      <div class="stat-card"><div class="label">Total applications</div><div class="value"><?= $totalLoans ?></div></div>
    </div>

    <div class="table-card" style="margin-bottom:24px;">
      <div class="table-head"><h3>By property area</h3></div>
      <?php if ($byArea): ?>
        <table>
          <thead><tr><th>Property area</th><th>Applications</th><th>Approved</th><th>Approval rate</th></tr></thead>
          <tbody>
            <?php foreach ($byArea as $row): $rate = $row['total'] ? round($row['approved'] / $row['total'] * 100, 1) : 0; ?>
              <tr>
                <td><?= htmlspecialchars($row['property_area']) ?></td>
                <td class="mono"><?= $row['total'] ?></td>
                <td class="mono"><?= $row['approved'] ?></td>
                <td class="mono"><?= $rate ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state"><div class="glyph">📊</div><p>No data yet — reports will populate once applications are submitted.</p></div>
      <?php endif; ?>
    </div>

    <div class="table-card">
      <div class="table-head"><h3>By education</h3></div>
      <?php if ($byEducation): ?>
        <table>
          <thead><tr><th>Education</th><th>Applications</th><th>Approved</th><th>Approval rate</th></tr></thead>
          <tbody>
            <?php foreach ($byEducation as $row): $rate = $row['total'] ? round($row['approved'] / $row['total'] * 100, 1) : 0; ?>
              <tr>
                <td><?= htmlspecialchars($row['education']) ?></td>
                <td class="mono"><?= $row['total'] ?></td>
                <td class="mono"><?= $row['approved'] ?></td>
                <td class="mono"><?= $rate ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state"><div class="glyph">📊</div><p>No data yet — reports will populate once applications are submitted.</p></div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
