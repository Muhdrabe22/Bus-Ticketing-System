<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Forgot Password';

if (isLoggedIn()) { header('Location: ' . BASE_URL); exit; }

$step = $_SESSION['reset_step'] ?? 1;
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $action = $_POST['action'] ?? '';

    // STEP 1: Enter email/phone → send OTP
    if ($action === 'send_otp') {
        $identifier = clean($_POST['identifier'] ?? '');
        if (empty($identifier)) { $error = 'Please enter your email or phone number.'; }
        else {
            $res = $db->query("SELECT * FROM users WHERE email='$identifier' OR phone='$identifier' LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                // Generate token & OTP
                $token  = bin2hex(random_bytes(32));
                $otp    = rand(100000, 999999);
                $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $email  = $row['email'];

                // Delete old tokens
                $db->query("DELETE FROM password_resets WHERE email='$email'");

                // Store token
                $db->query("INSERT INTO password_resets (email, token, expires_at) VALUES ('$email', '$token', '$expiry')");

                // Store OTP in session (in production send via SMS/email)
                $_SESSION['reset_otp']   = $otp;
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_token'] = $token;
                $_SESSION['reset_name']  = $row['full_name'];
                $_SESSION['reset_step']  = 2;

                $success = "OTP sent! <strong>Demo OTP: $otp</strong> (valid 15 mins)";
                $step = 2;
            } else {
                $error = 'No account found with that email or phone number.';
            }
        }
    }

    // STEP 2: Verify OTP
    elseif ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        if ((string)$otp === (string)$_SESSION['reset_otp']) {
            $_SESSION['reset_step'] = 3;
            $step = 3;
            $success = 'OTP verified! Set your new password below.';
        } else {
            $error = 'Invalid OTP. Please try again.';
            $step  = 2;
        }
    }

    // STEP 3: Set new password
    elseif ($action === 'reset_password') {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $email    = $_SESSION['reset_email'] ?? '';
        $token    = $_SESSION['reset_token'] ?? '';

        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
            $step  = 3;
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
            $step  = 3;
        } elseif (!$email || !$token) {
            $error = 'Session expired. Please start over.';
            $step  = 1; unset($_SESSION['reset_step']);
        } else {
            // Verify token not expired
            $check = $db->query("SELECT * FROM password_resets WHERE email='$email' AND token='$token' AND expires_at > NOW() AND used=0");
            if ($check && $check->num_rows > 0) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->query("UPDATE users SET password='$hash' WHERE email='$email'");
                $db->query("UPDATE password_resets SET used=1 WHERE token='$token'");

                // Notify
                $user = $db->query("SELECT id FROM users WHERE email='$email'")->fetch_assoc();
                if ($user) addNotification($user['id'], 'Password Changed', 'Your password was successfully reset.', 'system');

                // Clean session
                unset($_SESSION['reset_step'], $_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_token'], $_SESSION['reset_name']);

                header('Location: ' . BASE_URL . '/pages/login.php?reset=1'); exit;
            } else {
                $error = 'Reset link expired or already used. Please start over.';
                $step = 1; unset($_SESSION['reset_step']);
            }
        }
    }
}

$step = $_SESSION['reset_step'] ?? $step;
include '../includes/header.php';
?>
<style>
.reset-page { min-height:calc(100vh - 64px); background:var(--gray-50); display:flex; align-items:center; justify-content:center; padding:40px 20px; }
.reset-card { background:white; border-radius:24px; width:100%; max-width:480px; box-shadow:var(--shadow-lg); overflow:hidden; }
.reset-header { background:linear-gradient(135deg,var(--blue-dark),var(--blue)); padding:32px; text-align:center; color:white; }
.reset-header .icon-wrap { width:72px; height:72px; border-radius:50%; background:rgba(255,255,255,.15); border:3px solid rgba(255,255,255,.3); display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:28px; }
.reset-header h2 { font-size:22px; font-weight:800; }
.reset-header p { font-size:13px; opacity:.8; margin-top:4px; }
.reset-body { padding:32px; }

/* Progress Steps */
.reset-progress { display:flex; align-items:center; justify-content:center; gap:0; margin-top:20px; }
.rp-step { display:flex; flex-direction:column; align-items:center; gap:4px; }
.rp-circle { width:30px; height:30px; border-radius:50%; border:2px solid rgba(255,255,255,.3); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:rgba(255,255,255,.5); transition:all .3s; }
.rp-circle.active { background:white; border-color:white; color:var(--blue); }
.rp-circle.done { background:rgba(255,255,255,.3); border-color:white; color:white; }
.rp-line { width:60px; height:2px; background:rgba(255,255,255,.2); margin:0 4px; }
.rp-line.done { background:rgba(255,255,255,.7); }
.rp-label { font-size:9px; color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:.5px; margin-top:2px; font-weight:600; }
.rp-step.active .rp-label, .rp-step.done .rp-label { color:rgba(255,255,255,.8); }

/* OTP Input */
.otp-input-group { display:flex; gap:10px; justify-content:center; margin:20px 0; }
.otp-digit { width:52px; height:60px; border:2px solid var(--gray-200); border-radius:12px; text-align:center; font-size:24px; font-weight:800; font-family:var(--mono); color:var(--gray-900); transition:all .2s; }
.otp-digit:focus { outline:none; border-color:var(--orange); box-shadow:0 0 0 3px rgba(232,82,10,.1); }

/* Password strength */
.strength-bar { height:4px; border-radius:2px; background:var(--gray-200); margin-top:6px; overflow:hidden; }
.strength-fill { height:100%; border-radius:2px; width:0; transition:width .3s, background .3s; }
.strength-label { font-size:11px; margin-top:4px; font-weight:600; }

.alert { border-radius:10px; padding:12px 16px; font-size:13px; display:flex; align-items:center; gap:8px; margin-bottom:20px; }
.alert-error { background:#FEF2F2; border:1px solid #FCA5A5; color:var(--danger); }
.alert-success { background:#F0FDF4; border:1px solid #86EFAC; color:var(--success); }
</style>

<div class="reset-page">
  <div class="reset-card">
    <!-- Header -->
    <div class="reset-header">
      <div class="icon-wrap">
        <?php if ($step === 1): ?><i class="fas fa-lock"></i>
        <?php elseif ($step === 2): ?><i class="fas fa-shield-alt"></i>
        <?php else: ?><i class="fas fa-key"></i><?php endif; ?>
      </div>
      <h2>
        <?php if ($step === 1): ?>Forgot Password
        <?php elseif ($step === 2): ?>Verify OTP
        <?php else: ?>Set New Password<?php endif; ?>
      </h2>
      <p>
        <?php if ($step === 1): ?>Enter your email or phone to receive a reset OTP
        <?php elseif ($step === 2): ?>Enter the 6-digit OTP sent to you
        <?php else: ?>Choose a strong new password<?php endif; ?>
      </p>

      <!-- Progress -->
      <div class="reset-progress">
        <?php for ($i = 1; $i <= 3; $i++):
          $cls = $step > $i ? 'done' : ($step === $i ? 'active' : '');
          $labels = ['Email','OTP','Password'];
        ?>
        <div class="rp-step <?= $cls ?>">
          <div class="rp-circle <?= $cls ?>"><?= $step > $i ? '<i class="fas fa-check"></i>' : $i ?></div>
          <div class="rp-label"><?= $labels[$i-1] ?></div>
        </div>
        <?php if ($i < 3): ?><div class="rp-line <?= $step > $i ? 'done' : '' ?>"></div><?php endif; ?>
        <?php endfor; ?>
      </div>
    </div>

    <div class="reset-body">
      <!-- Alerts -->
      <?php if ($error): ?>
      <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= $error ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= $success ?></div>
      <?php endif; ?>

      <!-- STEP 1: Enter Email / Phone -->
      <?php if ($step === 1): ?>
      <form method="POST" id="step1Form">
        <input type="hidden" name="action" value="send_otp">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <div class="form-group">
          <label class="form-label">Email Address or Phone Number</label>
          <div style="position:relative">
            <i class="fas fa-user" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gray-400)"></i>
            <input type="text" name="identifier" class="form-control" style="padding-left:42px"
              placeholder="e.g. user@email.com or 08012345678"
              value="<?= isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : '' ?>" required autofocus>
          </div>
        </div>
        <div style="background:var(--gray-50);border-radius:10px;padding:12px;margin-bottom:20px;font-size:12px;color:var(--gray-500)">
          <i class="fas fa-info-circle" style="color:var(--blue)"></i>
          An OTP will be sent to your registered phone number for verification.
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px">
          <i class="fas fa-paper-plane"></i> Send Reset OTP
        </button>
      </form>

      <!-- STEP 2: OTP Verification -->
      <?php elseif ($step === 2): ?>
      <form method="POST" id="step2Form">
        <input type="hidden" name="action" value="verify_otp">
        <div style="text-align:center;margin-bottom:20px">
          <div style="font-size:14px;color:var(--gray-600)">OTP sent to account for</div>
          <div style="font-size:16px;font-weight:700;color:var(--gray-900);margin-top:4px"><?= htmlspecialchars($_SESSION['reset_name'] ?? 'your account') ?></div>
        </div>

        <!-- Visual OTP boxes -->
        <div class="otp-input-group">
          <?php for ($i = 1; $i <= 6; $i++): ?>
          <input type="text" class="otp-digit" maxlength="1" id="otp<?= $i ?>"
            oninput="handleOtpInput(this, <?= $i ?>)"
            onkeydown="handleOtpKeydown(this, <?= $i ?>, event)"
            inputmode="numeric" pattern="[0-9]">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="otp" id="otpHidden">

        <div style="text-align:center;font-size:12px;color:var(--gray-400);margin-bottom:20px">
          <span id="countdown">Expires in <strong id="timer">15:00</strong></span>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px" id="verifyBtn" disabled>
          <i class="fas fa-check-circle"></i> Verify OTP
        </button>
        <div style="text-align:center;margin-top:14px;font-size:13px">
          <a href="forgot-password.php" style="color:var(--orange);font-weight:600">
            <i class="fas fa-redo" style="font-size:11px"></i> Resend OTP / Change Email
          </a>
        </div>
      </form>

      <!-- STEP 3: New Password -->
      <?php else: ?>
      <form method="POST" id="step3Form">
        <input type="hidden" name="action" value="reset_password">
        <div class="form-group">
          <label class="form-label">New Password</label>
          <div style="position:relative">
            <i class="fas fa-lock" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gray-400)"></i>
            <input type="password" name="password" id="newPass" class="form-control" style="padding-left:42px;padding-right:44px"
              placeholder="Minimum 8 characters" required minlength="8" oninput="checkStrength(this.value)">
            <button type="button" class="pass-toggle" onclick="togglePassVis('newPass','eye1')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--gray-400);cursor:pointer">
              <i class="fas fa-eye" id="eye1"></i>
            </button>
          </div>
          <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
          <div class="strength-label" id="strengthLabel" style="color:var(--gray-400)">Enter a password</div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div style="position:relative">
            <i class="fas fa-lock" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gray-400)"></i>
            <input type="password" name="confirm_password" id="confPass" class="form-control" style="padding-left:42px;padding-right:44px"
              placeholder="Repeat your new password" required oninput="checkMatch()">
            <button type="button" class="pass-toggle" onclick="togglePassVis('confPass','eye2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--gray-400);cursor:pointer">
              <i class="fas fa-eye" id="eye2"></i>
            </button>
          </div>
          <div id="matchLabel" style="font-size:11px;margin-top:4px"></div>
        </div>

        <!-- Password rules checklist -->
        <div style="background:var(--gray-50);border-radius:10px;padding:12px;margin-bottom:20px">
          <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Password Requirements</div>
          <div id="rule-len" class="rule-item" style="font-size:12px;color:var(--gray-400);margin-bottom:4px"><i class="fas fa-circle" style="font-size:6px;margin-right:8px"></i>At least 8 characters</div>
          <div id="rule-upper" class="rule-item" style="font-size:12px;color:var(--gray-400);margin-bottom:4px"><i class="fas fa-circle" style="font-size:6px;margin-right:8px"></i>Uppercase letter</div>
          <div id="rule-num" class="rule-item" style="font-size:12px;color:var(--gray-400);margin-bottom:4px"><i class="fas fa-circle" style="font-size:6px;margin-right:8px"></i>At least one number</div>
          <div id="rule-special" class="rule-item" style="font-size:12px;color:var(--gray-400)"><i class="fas fa-circle" style="font-size:6px;margin-right:8px"></i>Special character (@,#,$,! etc.)</div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px">
          <i class="fas fa-save"></i> Reset Password
        </button>
      </form>
      <?php endif; ?>

      <div style="text-align:center;margin-top:20px;font-size:13px;color:var(--gray-500)">
        Remembered your password? <a href="<?= BASE_URL ?>/pages/login.php" style="color:var(--orange);font-weight:700">Sign In</a>
      </div>
    </div>
  </div>
</div>

<script>
// ── OTP Input handling ──
function handleOtpInput(el, idx) {
  el.value = el.value.replace(/\D/g,'').slice(-1);
  updateHiddenOtp();
  if (el.value && idx < 6) document.getElementById('otp'+(idx+1)).focus();
  checkOtpComplete();
}
function handleOtpKeydown(el, idx, e) {
  if (e.key === 'Backspace' && !el.value && idx > 1) document.getElementById('otp'+(idx-1)).focus();
}
function updateHiddenOtp() {
  let val = '';
  for (let i=1;i<=6;i++) val += document.getElementById('otp'+i)?.value||'';
  const h = document.getElementById('otpHidden');
  if (h) h.value = val;
}
function checkOtpComplete() {
  let full = true;
  for (let i=1;i<=6;i++) if (!(document.getElementById('otp'+i)?.value)) { full=false; break; }
  const btn = document.getElementById('verifyBtn');
  if (btn) btn.disabled = !full;
}
// Auto-focus first OTP box
window.addEventListener('load', () => { const f = document.getElementById('otp1'); if(f) f.focus(); });

// ── Countdown timer ──
<?php if ($step === 2): ?>
let timeLeft = 900;
const timer = setInterval(() => {
  timeLeft--;
  const m = Math.floor(timeLeft/60), s = timeLeft%60;
  const el = document.getElementById('timer');
  if (el) el.textContent = m+':'+(s<10?'0':'')+s;
  if (timeLeft <= 0) { clearInterval(timer); document.getElementById('countdown').innerHTML = '<span style="color:var(--danger)">OTP expired</span>'; }
}, 1000);
<?php endif; ?>

// ── Password strength ──
function checkStrength(val) {
  let score = 0;
  const rules = [
    { id:'rule-len',    pass: val.length >= 8 },
    { id:'rule-upper',  pass: /[A-Z]/.test(val) },
    { id:'rule-num',    pass: /[0-9]/.test(val) },
    { id:'rule-special',pass: /[^A-Za-z0-9]/.test(val) },
  ];
  rules.forEach(r => {
    score += r.pass ? 1 : 0;
    const el = document.getElementById(r.id);
    if (el) {
      el.style.color = r.pass ? 'var(--success)' : 'var(--gray-400)';
      el.querySelector('i').className = r.pass ? 'fas fa-check-circle' : 'fas fa-circle';
      el.querySelector('i').style.fontSize = r.pass ? '11px' : '6px';
    }
  });
  const fill = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  const levels = [
    {pct:'25%', color:'#EF4444', text:'Weak'},
    {pct:'50%', color:'#F97316', text:'Fair'},
    {pct:'75%', color:'#EAB308', text:'Good'},
    {pct:'100%',color:'#22C55E', text:'Strong'},
  ];
  const l = levels[score-1] || {pct:'0%',color:'var(--gray-200)',text:''};
  if (fill) { fill.style.width = l.pct; fill.style.background = l.color; }
  if (label){ label.textContent = l.text; label.style.color = l.color; }
}

function checkMatch() {
  const p = document.getElementById('newPass')?.value;
  const c = document.getElementById('confPass')?.value;
  const el = document.getElementById('matchLabel');
  if (!el || !c) return;
  if (p === c) { el.textContent = '✓ Passwords match'; el.style.color = 'var(--success)'; }
  else         { el.textContent = '✗ Passwords do not match'; el.style.color = 'var(--danger)'; }
}

function togglePassVis(inputId, iconId) {
  const i = document.getElementById(inputId);
  const ic = document.getElementById(iconId);
  if (i.type==='password') { i.type='text'; ic.className='fas fa-eye-slash'; }
  else { i.type='password'; ic.className='fas fa-eye'; }
}
</script>

<?php include '../includes/footer.php'; ?>
