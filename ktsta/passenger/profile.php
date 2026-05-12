<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'My Profile';
$user = currentUser();
$db = getDB();
$uid = (int)$user['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['full_name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $nin = clean($_POST['nin'] ?? '');
    if ($name && $phone) {
        $db->query("UPDATE users SET full_name='$name', phone='$phone', nin='$nin' WHERE id=$uid");
        $msg = 'success:Profile updated successfully!';
        $user = currentUser();
    }
    if (!empty($_POST['current_password'])) {
        $cur = $_POST['current_password'];
        $new = $_POST['new_password'];
        $conf = $_POST['confirm_password'];
        if (!password_verify($cur, $user['password'])) {
            $msg = 'error:Current password is incorrect.';
        } elseif ($new !== $conf) {
            $msg = 'error:New passwords do not match.';
        } elseif (strlen($new) < 8) {
            $msg = 'error:Password must be at least 8 characters.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password='$hash' WHERE id=$uid");
            $msg = 'success:Password changed successfully!';
        }
    }
}

$dashboards = ['passenger'=>'/passenger/dashboard.php','admin'=>'/admin/dashboard.php','officer'=>'/officer/dashboard.php','driver'=>'/driver/dashboard.php'];
$dashLink = BASE_URL . ($dashboards[$user['role']] ?? '/');
include '../includes/header.php';
?>
<div class="app-layout">
  <aside class="sidebar">
    <div style="padding:16px 12px;border-bottom:1px solid var(--gray-200);margin-bottom:16px">
      <div style="font-weight:700"><?= htmlspecialchars($user['full_name']) ?></div>
      <div style="font-size:12px;color:var(--gray-400)"><?= ucfirst($user['role']) ?></div>
    </div>
    <div class="sidebar-section">
      <a class="sidebar-item" href="<?= $dashLink ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a class="sidebar-item active" href="profile.php"><i class="fas fa-user"></i> Profile</a>
      <a class="sidebar-item" href="<?= BASE_URL ?>/pages/logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>
  <main class="main-content">
    <div class="page-header"><h1>My Profile</h1><p>Manage your account information</p></div>
    <?php if ($msg): ?>
    <?php [$type,$text] = explode(':',$msg,2); ?>
    <div style="background:<?= $type==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $type==='success'?'#86EFAC':'#FCA5A5' ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:<?= $type==='success'?'var(--success)':'var(--danger)' ?>;display:flex;align-items:center;gap:8px">
      <i class="fas fa-<?= $type==='success'?'check':'exclamation' ?>-circle"></i> <?= htmlspecialchars($text) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <!-- Profile Info -->
      <div class="card">
        <div class="card-title">Personal Information</div>
        <form method="POST">
          <div style="text-align:center;margin-bottom:24px">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--orange),var(--orange-dark));display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;color:white;margin:0 auto"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
            <div style="font-size:18px;font-weight:700;margin-top:10px"><?= htmlspecialchars($user['full_name']) ?></div>
            <span class="badge badge-info" style="margin-top:4px"><?= $user['role'] ?></span>
          </div>
          <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required></div>
          <div class="form-group"><label class="form-label">Email Address</label><input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:var(--gray-50)"><div style="font-size:11px;color:var(--gray-400);margin-top:4px">Email cannot be changed</div></div>
          <div class="form-group"><label class="form-label">Phone Number</label><input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>"></div>
          <div class="form-group"><label class="form-label">NIN (National ID)</label><input type="text" name="nin" class="form-control" value="<?= htmlspecialchars($user['nin'] ?? '') ?>" placeholder="Optional"></div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
        </form>
      </div>

      <!-- Change Password -->
      <div class="card">
        <div class="card-title">Change Password</div>
        <form method="POST">
          <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" placeholder="Enter current password"></div>
          <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters"></div>
          <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password"></div>
          <button type="submit" class="btn btn-secondary"><i class="fas fa-key"></i> Change Password</button>
        </form>

        <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200)">
          <div class="card-title" style="font-size:14px">Account Information</div>
          <div style="font-size:13px;color:var(--gray-500)">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span>Member since</span><strong><?= date('d M Y',strtotime($user['created_at'])) ?></strong></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span>Account Status</span><span class="badge badge-<?= $user['status']==='active'?'success':'danger' ?>"><?= $user['status'] ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>Verified</span><span class="badge badge-<?= $user['is_verified']?'success':'warning' ?>"><?= $user['is_verified']?'Yes':'No' ?></span></div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<?php include '../includes/footer.php'; ?>
