<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Reset Password';
if (isLoggedIn()) { header('Location: ' . BASE_URL); exit; }

$token = clean($_GET['token'] ?? '');
$error = ''; $success = '';
$db    = getDB();
$reset = null;

if ($token) {
    $reset = $db->query("SELECT pr.*, u.full_name, u.email FROM password_resets pr 
        JOIN users u ON pr.user_id=u.id 
        WHERE pr.token='$token' AND pr.used=0 AND pr.expires_at > NOW()")->fetch_assoc();
    if (!$reset) $error = 'This reset link is invalid or has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $pass   = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $uid  = (int)$reset['user_id'];
        $db->query("UPDATE users SET password='$hash', login_attempts=0, locked_until=NULL WHERE id=$uid");
        $db->query("UPDATE password_resets SET used=1 WHERE token='$token'");
        addNotification($uid, 'Password Reset Successful', 'Your password was reset via a secure link. Contact support if this was not you.', 'alert');
        header('Location: ' . BASE_URL . '/pages/login.php?reset=1'); exit;
    }
}

include '../includes/header.php';
?>
<style>
.reset-page { min-height:calc(100vh - 64px); background:linear-gradient(135deg,#0F1419,#1B2A3A); display:flex; align-items:center; justify-content:center; padding:40px 20px; }
.reset-card { background:white; border-radius:28px; width:100%; max-width:440px; overflow:hidden; box-shadow:0 30px 80px rgba(0,0,0,.4); }
.reset-header { background:linear-gradient(135deg,var(--blue),var(--blue-dark)); padding:32px; text-align:center; color:white; }
.reset-header .icon { width:68px; height:68px; border-radius:50%; background:rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-size:26px; }
</style>
<div class="reset-page">
  <div class="reset-card">
    <div class="reset-header">
      <div class="icon"><i class="fas fa-<?= $error?'times':'key' ?>"></i></div>
      <h2 style="font-size:20px;font-weight:800"><?= $error?'Link Expired':'Set New Password' ?></h2>
      <?php if ($reset): ?>
      <p style="font-size:13px;opacity:.8;margin-top:4px">For <?= htmlspecialchars($reset['full_name']) ?></p>
      <?php endif; ?>
    </div>
    <div style="padding:32px">
      <?php if ($error): ?>
        <div style="background:#FEF2F2;border:1px solid #FCA5A5;border-radius:10px;padding:14px;color:var(--danger);font-size:13px;margin-bottom:20px;display:flex;gap:8px"><i class="fas fa-exclamation-circle"></i><?= $error ?></div>
        <a href="forgot-password.php" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-redo"></i> Request New Reset Link</a>
      <?php else: ?>
        <form method="POST">
          <div class="form-group">
            <label class="form-label">New Password</label>
            <div style="position:relative">
              <input type="password" name="password" id="np" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
              <button type="button" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);border:none;background:none;cursor:pointer;color:var(--gray-400)" onclick="const e=document.getElementById('np');e.type=e.type==='password'?'text':'password'"><i class="fas fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm" class="form-control" placeholder="Repeat new password" required>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px"><i class="fas fa-lock"></i> Save New Password</button>
        </form>
      <?php endif; ?>
      <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--gray-400)"><a href="login.php" style="color:var(--orange);font-weight:600">← Back to Login</a></div>
    </div>
  </div>
</div>
