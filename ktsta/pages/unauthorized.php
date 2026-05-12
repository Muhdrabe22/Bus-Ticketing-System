<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Access Denied';
include '../includes/header.php';
?>
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:40px">
  <div>
    <div style="font-size:80px;margin-bottom:16px">🚫</div>
    <h1 style="font-size:32px;font-weight:800;color:var(--gray-900);margin-bottom:8px">Access Denied</h1>
    <p style="color:var(--gray-500);font-size:15px;margin-bottom:28px">You don't have permission to access this page.</p>
    <div style="display:flex;gap:12px;justify-content:center">
      <a href="<?= BASE_URL ?>" class="btn btn-ghost"><i class="fas fa-home"></i> Go Home</a>
      <a href="javascript:history.back()" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Go Back</a>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
