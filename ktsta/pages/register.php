<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Register';

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int)($_POST['step'] ?? 1);
    $db = getDB();

    if ($step === 1) {
        // Store step 1 in session
        $_SESSION['reg_step1'] = [
            'full_name' => clean($_POST['full_name'] ?? ''),
            'email' => clean($_POST['email'] ?? ''),
            'phone' => clean($_POST['phone'] ?? ''),
        ];
        // Check if email/phone taken
        $email = $_SESSION['reg_step1']['email'];
        $phone = $_SESSION['reg_step1']['phone'];
        $check = $db->query("SELECT id FROM users WHERE email='$email' OR phone='$phone'");
        if ($check->num_rows > 0) {
            $error = 'Email or phone number already registered.';
            unset($_SESSION['reg_step1']);
        } else {
            $_SESSION['reg_current_step'] = 2;
            // Generate OTP
            $otp = rand(100000, 999999);
            $_SESSION['reg_otp'] = $otp;
            $success = "Step 1 complete! OTP sent to {$phone}. (Demo OTP: <strong>$otp</strong>)";
        }
    } elseif ($step === 2) {
        $otp_input = clean($_POST['otp'] ?? '');
        if ((string)$otp_input === (string)$_SESSION['reg_otp']) {
            $_SESSION['reg_current_step'] = 3;
            $success = 'Phone verified! Please complete your registration.';
        } else {
            $error = 'Invalid OTP. Please try again.';
            $_SESSION['reg_current_step'] = 2;
        }
    } elseif ($step === 3) {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $nin = clean($_POST['nin'] ?? '');

        if (strlen($password) < 8) { $error = 'Password must be at least 8 characters.'; }
        elseif ($password !== $confirm) { $error = 'Passwords do not match.'; }
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $s1 = $_SESSION['reg_step1'];
            $db->query("INSERT INTO users (full_name, email, phone, password, nin, is_verified, role) 
                VALUES ('{$s1['full_name']}', '{$s1['email']}', '{$s1['phone']}', '$hash', '$nin', 1, 'passenger')");
            $newId = $db->insert_id;
            addNotification($newId, 'Welcome to KTSTA!', 'Your account has been created. Start booking your trips today.', 'system');
            unset($_SESSION['reg_step1'], $_SESSION['reg_otp'], $_SESSION['reg_current_step']);
            header('Location: ' . BASE_URL . '/pages/login.php?registered=1');
            exit;
        }
    }
}

$currentStep = $_SESSION['reg_current_step'] ?? 1;
include '../includes/header.php';
?>
<style>
.reg-page { min-height:calc(100vh - 64px); background:var(--gray-50); display:flex; align-items:center; justify-content:center; padding:40px 20px; }
.reg-card { background:white; border-radius:24px; width:100%; max-width:520px; box-shadow:var(--shadow-lg); overflow:hidden; }
.reg-header { background:linear-gradient(135deg,var(--orange),var(--orange-dark)); padding:30px; text-align:center; color:white; }
.reg-header h2 { font-size:22px; font-weight:800; }
.reg-header p { font-size:13px; opacity:.85; margin-top:4px; }
.reg-steps { display:flex; justify-content:center; gap:0; margin-top:20px; }
.reg-step { display:flex; align-items:center; }
.step-circle { width:34px; height:34px; border-radius:50%; border:2px solid rgba(255,255,255,.4); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:rgba(255,255,255,.6); transition:all .3s; }
.step-circle.done { background:rgba(255,255,255,.3); border-color:white; color:white; }
.step-circle.active { background:white; border-color:white; color:var(--orange); }
.step-line { width:50px; height:2px; background:rgba(255,255,255,.3); margin:0 4px; }
.step-line.done { background:rgba(255,255,255,.8); }
.reg-body { padding:32px; }
.step-panel { display:none; }
.step-panel.active { display:block; }
</style>

<div class="reg-page">
  <div class="reg-card">
    <div class="reg-header">
      <h2><i class="fas fa-user-plus"></i> Create Your Account</h2>
      <p>Join KTSTA — Katsina State's trusted transport service</p>
      <div class="reg-steps">
        <div class="reg-step">
          <div class="step-circle <?= $currentStep >= 1 ? ($currentStep > 1 ? 'done' : 'active') : '' ?>">
            <?= $currentStep > 1 ? '<i class="fas fa-check"></i>' : '1' ?>
          </div>
        </div>
        <div class="step-line <?= $currentStep > 1 ? 'done' : '' ?>"></div>
        <div class="reg-step">
          <div class="step-circle <?= $currentStep >= 2 ? ($currentStep > 2 ? 'done' : 'active') : '' ?>">
            <?= $currentStep > 2 ? '<i class="fas fa-check"></i>' : '2' ?>
          </div>
        </div>
        <div class="step-line <?= $currentStep > 2 ? 'done' : '' ?>"></div>
        <div class="reg-step">
          <div class="step-circle <?= $currentStep >= 3 ? 'active' : '' ?>">3</div>
        </div>
      </div>
      <div style="display:flex;justify-content:center;gap:50px;margin-top:8px">
        <?php foreach(['Personal Info','Verify Phone','Set Password'] as $i=>$lbl): ?>
        <div style="font-size:10px;opacity:<?= $currentStep === $i+1 ? '1' : '.5' ?>;font-weight:<?= $currentStep === $i+1 ? '700' : '400' ?>"><?= $lbl ?></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="reg-body">
      <?php if ($error): ?><div class="error-alert" style="background:#FEF2F2;border:1px solid #FCA5A5;border-radius:10px;padding:12px 16px;font-size:13px;color:var(--danger);display:flex;align-items:center;gap:8px;margin-bottom:20px"><i class="fas fa-exclamation-circle"></i><?= $error ?></div><?php endif; ?>
      <?php if ($success): ?><div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;font-size:13px;color:var(--success);display:flex;align-items:center;gap:8px;margin-bottom:20px"><i class="fas fa-check-circle"></i><?= $success ?></div><?php endif; ?>

      <!-- STEP 1 -->
      <?php if ($currentStep === 1): ?>
      <form method="POST">
        <input type="hidden" name="step" value="1">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <div style="font-size:16px;font-weight:700;margin-bottom:20px">Personal Information</div>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" placeholder="e.g. Aliyu Musa Ibrahim" required value="<?= htmlspecialchars($_SESSION['reg_step1']['full_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="e.g. aliyu@email.com" required value="<?= htmlspecialchars($_SESSION['reg_step1']['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="e.g. 08012345678" required value="<?= htmlspecialchars($_SESSION['reg_step1']['phone'] ?? '') ?>">
          <div style="font-size:11px;color:var(--gray-400);margin-top:4px">An OTP will be sent to this number</div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px">Continue <i class="fas fa-arrow-right"></i></button>
        <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--gray-500)">Already have an account? <a href="login.php" style="color:var(--orange);font-weight:700">Sign in</a></div>
      </form>

      <!-- STEP 2 -->
      <?php elseif ($currentStep === 2): ?>
      <form method="POST">
        <input type="hidden" name="step" value="2">
        <div style="text-align:center;margin-bottom:24px">
          <div style="width:70px;height:70px;border-radius:50%;background:#FFF0E8;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:28px;color:var(--orange)"><i class="fas fa-mobile-alt"></i></div>
          <div style="font-size:16px;font-weight:700">Verify Your Phone</div>
          <div style="font-size:13px;color:var(--gray-500);margin-top:4px">Enter the 6-digit OTP sent to <strong><?= htmlspecialchars($_SESSION['reg_step1']['phone'] ?? '') ?></strong></div>
        </div>
        <div class="form-group">
          <label class="form-label" style="text-align:center;display:block">One-Time Password (OTP)</label>
          <input type="text" name="otp" class="form-control" placeholder="000000" maxlength="6" style="text-align:center;font-size:28px;font-weight:800;font-family:var(--mono);letter-spacing:12px" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px">Verify OTP <i class="fas fa-check"></i></button>
        <div style="text-align:center;margin-top:12px;font-size:13px;color:var(--gray-500)">Didn't receive OTP? <a href="register.php" style="color:var(--orange);font-weight:700">Resend</a></div>
      </form>

      <!-- STEP 3 -->
      <?php else: ?>
      <form method="POST">
        <input type="hidden" name="step" value="3">
        <div style="font-size:16px;font-weight:700;margin-bottom:20px">Set Your Password</div>
        <div class="form-group">
          <label class="form-label">NIN (Optional)</label>
          <input type="text" name="nin" class="form-control" placeholder="National Identification Number" maxlength="20">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
        </div>
        <div style="font-size:12px;color:var(--gray-400);background:var(--gray-50);border-radius:8px;padding:12px;margin-bottom:16px">
          <i class="fas fa-info-circle" style="color:var(--blue)"></i>
          By registering, you agree to KTSTA's <a href="#" style="color:var(--orange)">Terms of Service</a> and <a href="#" style="color:var(--orange)">Privacy Policy</a>.
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px"><i class="fas fa-user-check"></i> Complete Registration</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
