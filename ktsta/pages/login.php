<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Login';

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = clean($_POST['role'] ?? 'passenger');

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db = getDB();
        $res = $db->query("SELECT * FROM users WHERE (email='$email' OR phone='$email') AND role='$role' AND status='active'");
        if ($res && $row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['user_name'] = $row['full_name'];

                $db->query("UPDATE users SET updated_at=NOW() WHERE id={$row['id']}");

                // Redirect by role
                $redirects = [
                    'passenger' => BASE_URL . '/passenger/dashboard.php',
                    'admin' => BASE_URL . '/admin/dashboard.php',
                    'officer' => BASE_URL . '/officer/dashboard.php',
                    'driver' => BASE_URL . '/driver/dashboard.php',
                ];
                header('Location: ' . ($redirects[$row['role']] ?? BASE_URL));
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'No account found with those credentials or account is inactive.';
        }
    }
}

include '../includes/header.php';
?>
<style>
.login-page { min-height: calc(100vh - 64px); display: flex; }
.login-left {
  flex: 1; background: linear-gradient(160deg, var(--blue-dark) 0%, #0B2A56 50%, #0F1A2E 100%);
  padding: 60px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden;
}
.login-left::before { content:''; position:absolute; top:-100px; right:-100px; width:400px; height:400px; border-radius:50%; background:radial-gradient(circle,rgba(232,82,10,.2),transparent 70%); }
.login-left::after { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; border-radius:50%; background:radial-gradient(circle,rgba(27,79,155,.3),transparent 70%); }
.login-left-content { position:relative; z-index:1; }
.login-left h2 { color:white; font-size:36px; font-weight:800; line-height:1.2; margin-bottom:16px; }
.login-left p { color:rgba(255,255,255,.6); font-size:15px; line-height:1.7; margin-bottom:40px; }
.login-features { display:flex; flex-direction:column; gap:16px; }
.login-feature { display:flex; align-items:center; gap:14px; color:rgba(255,255,255,.8); font-size:14px; }
.login-feature-icon { width:40px; height:40px; border-radius:10px; background:rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center; color:var(--orange); font-size:16px; flex-shrink:0; }

.login-right { width: 500px; background: white; padding: 60px 50px; display: flex; flex-direction: column; justify-content: center; }
.login-logo { text-align:center; margin-bottom:32px; }
.login-logo .badge { display:inline-flex; align-items:center; gap:10px; background:var(--gray-100); border-radius:12px; padding:10px 16px; }
.login-logo .badge .icon { width:44px; height:44px; border-radius:10px; background:linear-gradient(135deg,var(--orange),var(--orange-dark)); display:flex; align-items:center; justify-content:center; color:white; font-size:20px; }
.login-logo .badge span { font-weight:800; font-size:16px; color:var(--gray-900); }

.role-selector { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:24px; }
.role-option { cursor:pointer; }
.role-option input { display:none; }
.role-btn { display:flex; flex-direction:column; align-items:center; gap:6px; padding:12px 8px; border-radius:12px; border:2px solid var(--gray-200); font-size:11px; font-weight:600; color:var(--gray-500); transition:all .2s; text-align:center; background:white; }
.role-btn i { font-size:18px; }
.role-option input:checked + .role-btn { border-color:var(--orange); background:#FFF0E8; color:var(--orange); }

.login-form-title { font-size:22px; font-weight:800; color:var(--gray-900); margin-bottom:4px; }
.login-form-sub { font-size:13px; color:var(--gray-400); margin-bottom:28px; }

.pass-wrapper { position:relative; }
.pass-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:var(--gray-400); border:none; background:none; padding:4px; }

.error-alert { background:#FEF2F2; border:1px solid #FCA5A5; border-radius:10px; padding:12px 16px; font-size:13px; color:var(--danger); display:flex; align-items:center; gap:8px; margin-bottom:20px; }

@media (max-width:900px) {
  .login-page { flex-direction:column; }
  .login-left { display:none; }
  .login-right { width:100%; padding:40px 24px; }
}
</style>

<div class="login-page">
  <div class="login-left">
    <div class="login-left-content">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:40px">
        <div style="width:50px;height:50px;border-radius:14px;background:var(--orange);display:flex;align-items:center;justify-content:center;font-size:22px;color:white"><i class="fas fa-bus"></i></div>
        <div style="color:white;font-weight:800;font-size:20px">KTSTA</div>
      </div>
      <h2>Welcome Back to<br>Katsina's Finest<br>Transport Service</h2>
      <p>Sign in to manage bookings, track your journeys, and enjoy a seamless travel experience across Katsina State.</p>
      <div class="login-features">
        <div class="login-feature"><div class="login-feature-icon"><i class="fas fa-shield-alt"></i></div>Secure & encrypted login</div>
        <div class="login-feature"><div class="login-feature-icon"><i class="fas fa-ticket-alt"></i></div>Access all your tickets</div>
        <div class="login-feature"><div class="login-feature-icon"><i class="fas fa-wallet"></i></div>Manage your wallet balance</div>
        <div class="login-feature"><div class="login-feature-icon"><i class="fas fa-bell"></i></div>Real-time trip notifications</div>
      </div>
      <div style="margin-top:40px;padding:16px;background:rgba(255,255,255,.07);border-radius:12px;border:1px solid rgba(255,255,255,.1)">
        <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Demo Credentials</div>
        <div style="font-size:12px;color:rgba(255,255,255,.7);font-family:var(--mono)">
          Admin: admin@ktsta.gov.ng<br>
          Officer: officer@ktsta.gov.ng<br>
          Driver: driver1@ktsta.gov.ng<br>
          Passenger: passenger@test.com<br>
          <span style="color:var(--orange)">Password: password</span>
        </div>
      </div>
    </div>
  </div>

  <div class="login-right">
    <div class="login-logo">
      <div class="badge"><div class="icon"><i class="fas fa-bus"></i></div><span>KTSTA Portal</span></div>
    </div>
    <div class="login-form-title">Sign In</div>
    <div class="login-form-sub">Select your role and enter your credentials</div>

    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

      <!-- Role Selector -->
      <div style="margin-bottom:24px">
        <div class="form-label">I am a:</div>
        <div class="role-selector">
          <?php $roles=[['passenger','fas fa-user','Passenger'],['admin','fas fa-cog','Admin'],['officer','fas fa-id-badge','Officer'],['driver','fas fa-bus','Driver']]; ?>
          <?php foreach($roles as [$val,$icon,$label]): ?>
          <label class="role-option">
            <input type="radio" name="role" value="<?= $val ?>" <?= ($val==='passenger')?'checked':'' ?>>
            <div class="role-btn"><i class="<?= $icon ?>"></i><?= $label ?></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if($error): ?>
      <div class="error-alert"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if(isset($_GET['registered'])): ?>
      <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;font-size:13px;color:var(--success);display:flex;align-items:center;gap:8px;margin-bottom:20px"><i class="fas fa-check-circle"></i> Account created! Please sign in.</div>
      <?php endif; ?>
      <?php if(isset($_GET['reset'])): ?>
      <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;font-size:13px;color:var(--success);display:flex;align-items:center;gap:8px;margin-bottom:20px"><i class="fas fa-check-circle"></i> Password reset successfully! Please sign in with your new password.</div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Email or Phone</label>
        <input type="text" name="email" class="form-control" placeholder="Enter email or phone number" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="pass-wrapper">
          <input type="password" name="password" id="passInput" class="form-control" placeholder="Enter your password" required>
          <button type="button" class="pass-toggle" onclick="togglePass()"><i class="fas fa-eye" id="passIcon"></i></button>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer"><input type="checkbox" style="accent-color:var(--orange)">Remember me</label>
        <a href="<?= BASE_URL ?>/pages/forgot-password.php" style="font-size:13px;color:var(--orange);font-weight:600">Forgot password?</a>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:15px">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </button>

      <div style="text-align:center;margin-top:20px;font-size:13px;color:var(--gray-500)">
        Don't have an account? <a href="<?= BASE_URL ?>/pages/register.php" style="color:var(--orange);font-weight:700">Register here</a>
      </div>
    </form>
  </div>
</div>

<script>
function togglePass() {
  const i = document.getElementById('passInput');
  const icon = document.getElementById('passIcon');
  if (i.type === 'password') { i.type = 'text'; icon.className = 'fas fa-eye-slash'; }
  else { i.type = 'password'; icon.className = 'fas fa-eye'; }
}
</script>
