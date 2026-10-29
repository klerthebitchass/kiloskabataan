<?php
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Generates the UUIDs your tables use as primary keys
function uuid_v4() {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

// Load puroks for the dropdown
$puroks = $conn->query("SELECT purok_id, purok_name FROM puroks ORDER BY purok_name")
               ->fetch_all(MYSQLI_ASSOC);
$purok_ids = array_column($puroks, 'purok_id');

$errors = [];
$old = [
    'full_name' => '', 'email' => '', 'purok_id' => '',
    'role' => 'member', 'emergency_contact' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name']         = trim($_POST['full_name'] ?? '');
    $old['email']             = trim($_POST['email'] ?? '');
    $old['purok_id']          = $_POST['purok_id'] ?? '';
    $old['role']              = $_POST['role'] ?? '';
    $old['emergency_contact'] = trim($_POST['emergency_contact'] ?? '');
    $password                 = $_POST['password'] ?? '';
    $confirm                  = $_POST['confirm_password'] ?? '';
    $consent                  = isset($_POST['consent']);

    // ----- Validation -----
    if ($old['full_name'] === '')                              $errors[] = "Full name is required.";
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))     $errors[] = "Enter a valid email address.";
    if (strlen($password) < 8)                                 $errors[] = "Password must be at least 8 characters.";
    if ($password !== $confirm)                                $errors[] = "Passwords do not match.";
    if (!in_array($old['purok_id'], $purok_ids, true))         $errors[] = "Please choose your purok.";
    if (!in_array($old['role'], ['member', 'leader'], true))   $errors[] = "Please choose a valid role.";
    if ($old['emergency_contact'] === '')                      $errors[] = "Emergency contact number is required.";
    if (!$consent)                                             $errors[] = "You must agree to the consent statement.";

    // ----- Email already used? -----
    if (!$errors) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $old['email']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "That email is already registered.";
        }
    }

    // ----- Save -----
    if (!$errors) {
        $user_id    = uuid_v4();
        $profile_id = uuid_v4();
        $hash       = password_hash($password, PASSWORD_DEFAULT);
        $consent_status = 'agreed';

        try {
            $conn->begin_transaction();

            $u = $conn->prepare(
                "INSERT INTO users (user_id, email, password_hash, role, purok_id)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $u->bind_param("sssss", $user_id, $old['email'], $hash, $old['role'], $old['purok_id']);
            $u->execute();

            $p = $conn->prepare(
                "INSERT INTO youth_profiles (profile_id, user_id, full_name, emergency_contact, consent_status)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $p->bind_param("sssss", $profile_id, $user_id, $old['full_name'], $old['emergency_contact'], $consent_status);
            $p->execute();

            $conn->commit();
            header("Location: login.php?registered=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | KilosKabataan</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Marcellus+SC&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <main class="auth-page">
    <div class="auth-card">

      <h2>Create Your Account</h2>
      <p class="auth-sub">Be part of our youth community of San Roque</p>

      <!-- Error from the browser check (NEXT button) -->
      <div class="auth-errors" id="clientError" style="display:none"></div>

      <!-- Errors from the server (PHP) -->
      <?php if ($errors): ?>
        <div class="auth-errors">
          <?php foreach ($errors as $e): ?>
            <p><?= htmlspecialchars($e) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="register.php" id="registerForm">

        <!-- ========== SCREEN 1 ========== -->
        <div class="step" id="step1">

          <label for="full_name">Full Name</label>
          <input type="text" id="full_name" name="full_name"
                 value="<?= htmlspecialchars($old['full_name']) ?>" required>

          <label for="email">Email</label>
          <input type="email" id="email" name="email"
                 value="<?= htmlspecialchars($old['email']) ?>" required>

          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>

          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>

          <button type="button" id="nextBtn" class="btn btn-login btn-next">NEXT</button>
        </div>

        <!-- ========== SCREEN 2 ========== -->
        <div class="step" id="step2" hidden>

          <label for="purok_id">Purok</label>
          <div class="select-wrap">
            <select id="purok_id" name="purok_id" required>
              <option value="">Select your purok</option>
              <?php foreach ($puroks as $p): ?>
                <option value="<?= htmlspecialchars($p['purok_id']) ?>"
                  <?= $old['purok_id'] === $p['purok_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['purok_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <label for="role">Register As</label>
          <div class="select-wrap">
            <select id="role" name="role" required>
              <option value="member" <?= $old['role'] === 'member' ? 'selected' : '' ?>>Youth Member</option>
              <option value="leader" <?= $old['role'] === 'leader' ? 'selected' : '' ?>>Youth Leader</option>
            </select>
          </div>

          <label for="emergency_contact">Emergency Contact Number</label>
          <input type="text" id="emergency_contact" name="emergency_contact"
                 value="<?= htmlspecialchars($old['emergency_contact']) ?>" required>

          <label class="consent">
            <input type="checkbox" name="consent" required>
            <span>I confirm that I am 18 years old or above, and I agree to the collection and use of my
            personal information by San Roque Parish for youth engagement activities, in accordance with
            the Data Privacy Act of 2012.</span>
          </label>

          <button type="submit" class="btn btn-login btn-full">REGISTER</button>
          <button type="button" id="backBtn" class="auth-back">Back</button>
        </div>

      </form>
    </div>
  </main>

  <script>
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const clientError = document.getElementById('clientError');

    function showError(msg) {
      clientError.textContent = msg;
      clientError.style.display = 'block';
    }

    function goNext() {
      clientError.style.display = 'none';

      // Check every field on screen 1
      for (const field of step1.querySelectorAll('input')) {
        if (!field.reportValidity()) return;
      }
      const pw = document.getElementById('password').value;
      if (pw.length < 8) return showError('Password must be at least 8 characters.');
      if (pw !== document.getElementById('confirm_password').value) return showError('Passwords do not match.');

      step1.hidden = true;
      step2.hidden = false;
    }

    document.getElementById('nextBtn').addEventListener('click', goNext);

    document.getElementById('backBtn').addEventListener('click', () => {
      clientError.style.display = 'none';
      step2.hidden = true;
      step1.hidden = false;
    });

    // Pressing Enter on screen 1 should go to the next screen, not submit
    step1.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); goNext(); }
    });
  </script>

</body>
</html>