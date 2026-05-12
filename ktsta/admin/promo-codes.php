<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$pageTitle = 'Promo Codes';
$user = currentUser();
$db   = getDB();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');
    if ($action === 'add') {
        $code     = strtoupper(clean($_POST['code']));
        $desc     = clean($_POST['description']);
        $type     = clean($_POST['discount_type']);
        $val      = (float)$_POST['discount_value'];
        $minFare  = (float)$_POST['min_fare'];
        $maxDisc  = (float)$_POST['max_discount'];
        $limit    = (int)$_POST['usage_limit'];
        $from     = clean($_POST['valid_from']);
        $until    = clean($_POST['valid_until']);
        $adminId  = (int)$_SESSION['user_id'];
        $check    = $db->query("SELECT id FROM promo_codes WHERE code='$code'")->num_rows;
        if ($check > 0) { $msg = 'error:Promo code already exists.'; }
        else {
            $db->query("INSERT INTO promo_codes (code,description,discount_type,discount_value,min_fare,max_discount,usage_limit,valid_from,valid_until,created_by) VALUES ('$code','$desc','$type',$val,$minFare,$maxDisc,$limit,'$from','$until',$adminId)");
            $msg = 'success:Promo code created successfully!';
        }
    } elseif ($action === 'toggle') {
        $id  = (int)$_POST['id'];
        $val = (int)$_POST['value'];
        $db->query("UPDATE promo_codes SET is_active=$val WHERE id=$id");
        $msg = 'success:Promo code ' . ($val ? 'activated' : 'deactivated') . '.';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM promo_codes WHERE id=$id");
        $msg = 'success:Promo code deleted.';
    }
}

$promos = $db->query("SELECT p.*, u.full_name as creator,
    (SELECT COUNT(*) FROM promo_usage WHERE promo_id=p.id) as total_used,
    (SELECT COALESCE(SUM(discount_applied),0) FROM promo_usage WHERE promo_id=p.id) as total_savings
    FROM promo_codes p LEFT JOIN users u ON p.created_by=u.id ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$stats = [
    'total'    => count($promos),
    'active'   => count(array_filter($promos, fn($p) => $p['is_active'])),
    'uses'     => array_sum(array_column($promos, 'total_used')),
    'savings'  => array_sum(array_column($promos, 'total_savings')),
];

include '../includes/header.php';
?>
<style>
.admin-layout { display:grid; grid-template-columns:260px 1fr; min-height:calc(100vh - 64px); }
.admin-sidebar { background:var(--gray-900); padding:16px 10px; position:sticky; top:64px; height:calc(100vh - 64px); overflow-y:auto; }
.admin-sidebar .sidebar-item { color:rgba(255,255,255,.6); }
.admin-sidebar .sidebar-item:hover,.admin-sidebar .sidebar-item.active { background:rgba(232,82,10,.2); color:var(--orange); }
.admin-sidebar .sidebar-label { color:rgba(255,255,255,.3); }
.promo-card { background:white; border-radius:16px; border:2px solid var(--gray-200); padding:20px; transition:all .2s; }
.promo-card:hover { border-color:var(--orange); box-shadow:var(--shadow); }
.promo-code-badge { font-family:var(--mono); font-size:20px; font-weight:800; color:var(--orange); letter-spacing:2px; background:#FFF0E8; border:2px dashed var(--orange); border-radius:10px; padding:8px 16px; display:inline-block; }
</style>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div style="padding:8px 12px 16px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:12px">
      <div style="color:white;font-weight:800;font-size:14px">Admin Panel</div>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Navigation</div>
      <a class="sidebar-item" href="dashboard.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);font-size:13.5px"><i class="fas fa-tachometer-alt" style="width:18px"></i> Dashboard</a>
      <a class="sidebar-item active" href="promo-codes.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:13.5px;background:rgba(232,82,10,.2);color:var(--orange)"><i class="fas fa-tag" style="width:18px"></i> Promo Codes</a>
      <a class="sidebar-item" href="maintenance.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);font-size:13.5px"><i class="fas fa-tools" style="width:18px"></i> Maintenance</a>
      <a class="sidebar-item" href="staff.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);font-size:13.5px"><i class="fas fa-id-card" style="width:18px"></i> Staff Records</a>
      <a class="sidebar-item" href="incidents.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);font-size:13.5px"><i class="fas fa-exclamation-triangle" style="width:18px"></i> Incidents</a>
    </div>
  </aside>

  <main style="padding:28px;background:var(--gray-50)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
      <div>
        <h1 style="font-size:24px;font-weight:800">Promo Codes</h1>
        <p style="color:var(--gray-500);font-size:13px">Manage discount codes and promotional offers</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('addPromoModal')"><i class="fas fa-plus"></i> Create Promo Code</button>
    </div>

    <?php if ($msg): ?>
    <?php [$t,$m]=explode(':',$msg,2); ?>
    <div style="background:<?= $t==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $t==='success'?'#86EFAC':'#FCA5A5' ?>;border-radius:10px;padding:12px 16px;margin-bottom:20px;color:<?= $t==='success'?'var(--success)':'var(--danger)' ?>;display:flex;align-items:center;gap:8px;font-size:13px">
      <i class="fas fa-<?= $t==='success'?'check':'exclamation' ?>-circle"></i><?= htmlspecialchars($m) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
      <?php foreach([['Total Codes',$stats['total'],'fas fa-tags','orange'],['Active Codes',$stats['active'],'fas fa-check-circle','green'],['Total Uses',$stats['uses'],'fas fa-users','blue'],['Total Savings','₦'.number_format($stats['savings']),'fas fa-percentage','red']] as [$l,$v,$i,$c]): ?>
      <div class="stat-card <?= $c ?>"><div class="stat-value"><?= $v ?></div><div class="stat-label"><?= $l ?></div><div class="stat-icon"><i class="<?= $i ?>"></i></div></div>
      <?php endforeach; ?>
    </div>

    <!-- Promo Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px">
      <?php foreach($promos as $p): ?>
      <div class="promo-card">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:14px">
          <div class="promo-code-badge"><?= $p['code'] ?></div>
          <div style="display:flex;gap:6px;align-items:center">
            <span class="badge badge-<?= $p['is_active']?'success':'gray' ?>"><?= $p['is_active']?'Active':'Paused' ?></span>
          </div>
        </div>
        <div style="font-size:13px;color:var(--gray-500);margin-bottom:12px"><?= htmlspecialchars($p['description']) ?></div>
        <div style="display:flex;gap:16px;margin-bottom:14px;font-size:13px">
          <div><span style="color:var(--gray-400)">Discount: </span><strong style="color:var(--orange)"><?= $p['discount_type']==='percent' ? $p['discount_value'].'%' : '₦'.number_format($p['discount_value']) ?></strong></div>
          <div><span style="color:var(--gray-400)">Used: </span><strong><?= $p['total_used'] ?>/<?= $p['usage_limit'] ?></strong></div>
        </div>
        <div style="background:var(--gray-50);border-radius:8px;padding:8px 12px;font-size:12px;color:var(--gray-400);margin-bottom:14px">
          Valid: <?= $p['valid_from'] ? date('d M Y',strtotime($p['valid_from'])) : 'Always' ?> – <?= $p['valid_until'] ? date('d M Y',strtotime($p['valid_until'])) : 'No Expiry' ?>
          <?php if ($p['min_fare'] > 0): ?> &bull; Min fare: ₦<?= number_format($p['min_fare']) ?><?php endif; ?>
        </div>
        <!-- Progress bar -->
        <div style="background:var(--gray-200);border-radius:4px;height:4px;margin-bottom:14px;overflow:hidden">
          <div style="background:var(--orange);height:100%;width:<?= min(100, $p['used_count']/$p['usage_limit']*100) ?>%;border-radius:4px"></div>
        </div>
        <div style="display:flex;gap:8px">
          <form method="POST" style="flex:1">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="value" value="<?= $p['is_active']?0:1 ?>">
            <button type="submit" class="btn <?= $p['is_active']?'btn-ghost':'btn-success' ?> btn-sm" style="width:100%">
              <i class="fas fa-<?= $p['is_active']?'pause':'play' ?>"></i> <?= $p['is_active']?'Pause':'Activate' ?>
            </button>
          </form>
          <form method="POST" onsubmit="return confirm('Delete this promo code?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($promos)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:60px;background:white;border-radius:20px;color:var(--gray-400)">
        <i class="fas fa-tag" style="font-size:40px;display:block;margin-bottom:12px;opacity:.3"></i>
        No promo codes yet. Create your first one!
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Add Promo Modal -->
<div class="modal-overlay" id="addPromoModal">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header">
      <h3><i class="fas fa-tag" style="color:var(--orange)"></i> Create Promo Code</h3>
      <button class="modal-close" onclick="closeModal('addPromoModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST" id="promoForm">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group" style="grid-column:span 2">
            <label class="form-label">Promo Code <small style="color:var(--gray-400)">(uppercase, no spaces)</small></label>
            <input name="code" class="form-control" placeholder="e.g. SAVE20" style="text-transform:uppercase;font-family:var(--mono);font-weight:700;font-size:16px;letter-spacing:2px" oninput="this.value=this.value.toUpperCase().replace(/\s/g,'')" required>
          </div>
          <div class="form-group" style="grid-column:span 2">
            <label class="form-label">Description</label>
            <input name="description" class="form-control" placeholder="e.g. 10% off for new passengers" required>
          </div>
          <div class="form-group">
            <label class="form-label">Discount Type</label>
            <select name="discount_type" class="form-control">
              <option value="percent">Percentage (%)</option>
              <option value="fixed">Fixed Amount (₦)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Discount Value</label>
            <input type="number" name="discount_value" class="form-control" placeholder="e.g. 10 for 10%" min="1" step="0.01" required>
          </div>
          <div class="form-group">
            <label class="form-label">Minimum Fare (₦)</label>
            <input type="number" name="min_fare" class="form-control" value="0" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Max Discount (₦)</label>
            <input type="number" name="max_discount" class="form-control" placeholder="Leave blank = no limit" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Usage Limit</label>
            <input type="number" name="usage_limit" class="form-control" value="100" min="1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Valid From</label>
            <input type="date" name="valid_from" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Valid Until</label>
            <input type="date" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('addPromoModal')">Cancel</button>
      <button class="btn btn-primary" onclick="document.getElementById('promoForm').submit()"><i class="fas fa-save"></i> Create Code</button>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
