<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'dashboard.php');
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['full_name'] = trim($_POST['full_name'] ?? '');
        $old['email']     = trim($_POST['email'] ?? '');
        $old['phone']     = trim($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password_confirm'] ?? '';

        if ($old['full_name'] === '') $errors[] = 'Full name is required.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $password2) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            $db = get_db();
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with that email already exists.';
            }
        }

        if (!$errors) {
            $stmt = $db->prepare(
                'INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, "customer")'
            );
            $stmt->execute([
                $old['full_name'],
                $old['email'],
                $old['phone'],
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            redirect('login.php?registered=1');
        }
    }
}

$base_path = '';
$page_title = 'Open an account — BU Bank Ltd';
include 'includes/header.php';
?>

<div class="auth-wrap">
  <div class="card auth-card">
   
    <h1>Open an account</h1>
    <p class="sub">Register once to apply for loans and track every decision.</p>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-row">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($old['full_name']) ?>" required>
      </div>
      <div class="form-row">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required>
      </div>
      <div class="form-row">
        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" placeholder="Optional">
      </div>
      <div class="form-row two">
        <div>
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div>
          <label for="password_confirm">Confirm password</label>
          <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
        </div>
      </div>
      <p class="hint">Use at least 8 characters.</p>
      <button type="submit" class="btn btn-brass btn-block">Create account</button>
    </form>
    <div class="auth-switch">Already registered? <a href="login.php">Log in</a></div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
