<?php
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Where each role goes after logging in
$redirects = [
    'admin'  => 'admin/dashboard.php',
    'leader' => 'leader/dashboard.php',
    'member' => 'member/dashboard.php',
];

// Already logged in? Send them straight to their dashboard
if (isset($_SESSION['user_id'], $_SESSION['role']) && isset($redirects[$_SESSION['role']])) {
    header("Location: " . $redirects[$_SESSION['role']]);
    exit;
}

$errors = [];
$email = '';
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = "Enter your email and password.";
    } else {
        // LEFT JOIN because the admin has no youth profile
        $stmt = $conn->prepare(
            "SELECT u.user_id, u.password_hash, u.role, p.full_name
             FROM users u
             LEFT JOIN youth_profiles p ON p.user_id = u.user_id
             WHERE u.email = ?"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['full_name'] = $user['full_name'] ?? 'Parish Admin';
            $_SESSION['email']     = $email;

            // Record the login in SESSION_LOGS (log_id fills itself)
            $log = $conn->prepare("INSERT INTO session_logs (user_id, action) VALUES (?, 'login')");
            $log->bind_param("s", $user['user_id']);
            $log->execute();

            header("Location: " . $redirects[$user['role']]);
            exit;
        }

        // Same message for wrong email or wrong password, on purpose
        $errors[] = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | KilosKabataan</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Marcellus+SC&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <main class="auth-page">
    <div class="auth-card">

      <h2>Welcome Back</h2>
      <p class="auth-sub">Log in to your KilosKabataan account</p>

      <?php if ($registered): ?>
        <div class="auth-success">Account created! You can now log in.</div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="auth-errors">
          <?php foreach ($errors as $e): ?>
            <p><?= htmlspecialchars($e) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php">

        <label for="email">Email</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($email) ?>" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn btn-login btn-full">LOGIN</button>

        <p class="auth-switch">No account yet? <a href="register.php">Register</a></p>
      </form>

    </div>
  </main>

</body>
</html>