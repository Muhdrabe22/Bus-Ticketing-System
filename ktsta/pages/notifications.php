<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Notifications';
$user = currentUser();
$db = getDB();
$uid = (int)$user['id'];

$db->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
$notifications = $db->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

$dashboards = ['passenger'=>'/passenger/dashboard.php','admin'=>'/admin/dashboard.php','officer'=>'/officer/dashboard.php','driver'=>'/driver/dashboard.php'];
$dashLink = BASE_URL . ($dashboards[$user['role']] ?? '/');
include '../includes/header.php';
?>
<div style="max-width:700px;margin:40px auto;padding:0 20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800">Notifications</h1>
    <a href="<?= $dashLink ?>" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
  <?php if (empty($notifications)): ?>
  <div style="text-align:center;background:white;border-radius:20px;padding:60px;border:1px solid var(--gray-200)">
    <i class="fas fa-bell-slash" style="font-size:48px;color:var(--gray-300);display:block;margin-bottom:16px"></i>
    <div style="color:var(--gray-500)">No notifications yet</div>
  </div>
  <?php else: ?>
  <?php $icons=['booking'=>'fa-ticket-alt #FF6B35','payment'=>'fa-money-bill #16A34A','trip'=>'fa-bus #1B4F9B','system'=>'fa-bell #6B7583','alert'=>'fa-exclamation-triangle #D97706']; ?>
  <?php foreach($notifications as $n): ?>
  <?php [$icon,$color] = explode(' ',$icons[$n['type']]??'fa-bell #6B7583'); ?>
  <div style="background:white;border-radius:16px;border:1px solid var(--gray-200);padding:16px 20px;margin-bottom:10px;display:flex;gap:14px;align-items:start">
    <div style="width:42px;height:42px;border-radius:12px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;color:<?= $color ?>;font-size:16px;flex-shrink:0"><i class="fas <?= $icon ?>"></i></div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($n['title']) ?></div>
      <div style="font-size:13px;color:var(--gray-500);margin-top:3px"><?= htmlspecialchars($n['message']) ?></div>
      <div style="font-size:11px;color:var(--gray-300);margin-top:6px"><?= date('D d M Y, H:i',strtotime($n['created_at'])) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
