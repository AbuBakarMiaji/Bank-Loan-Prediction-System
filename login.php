<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $db = get_db();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Incorrect email or password.';
        } else {
            $_SESSION['user'] = [
                'id'        => $user['id'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
            ];
            redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
        }
    }
}

$base_path = '';
$page_title = 'Log in — BU Bank Ltd';
include 'includes/header.php';
?>

<div class="auth-wrap">
  <div class="card auth-card">
   
    <h1>Welcome back</h1>
    <p class="sub">Log in to your dashboard to apply for a loan or check a decision.</p>

    <?php if (isset($_GET['registered'])): ?>
      <div class="alert alert-success">Account created. You can log in now.</div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-row">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus>
      </div>
      <div class="form-row">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-brass btn-block">Log in</button>
    </form>
    <div class="auth-switch">New here? <a href="register.php">Open an account</a></div>
    <p class="hint" style="text-align:center;margin-top:18px;">Admin demo login: admin@bubank.com / Admin@123</p>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
