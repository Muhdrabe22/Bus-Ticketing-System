<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$pageTitle = 'Subscriptions';
$user = currentUser();
$db   = getDB();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');
    if ($action === 'add') {
        $uid       = (int)$_POST['user_id'];
        $plan      = clean($_POST['plan_name']);
        $routeId   = (int)$_POST['route_id'] ?: 'NULL';
        $trips     = (int)$_POST['trips_per_month'];
        $price     = (float)$_POST['price_paid'];
        $start     = clean($_POST['start_date']);
        $end       = clean($_POST['end_date']);
        $db->query("INSERT INTO subscriptions (user_id,plan_name,route_id,trips_per_month,price_paid,start_date,end_date) VALUES ($uid,'$plan'," . ($routeId==='NULL'?'NULL':(int)$routeId) . ",$trips,$price,'$start','$end')");
        // Add wallet notification
        addNotification($uid, 'Subscription Activated', "Your $plan subscription is now active until " . date('d M Y', strtotime($end)), 'payment');
        $msg = 'success:Subscription created.';
    } elseif ($action === 'cancel') {
        $id = (int)$_POST['sub_id'];
        $db->query("UPDATE subscriptions SET status='cancelled' WHERE id=$id");
        $msg = 'success:Subscription cancelled.';
    }
}

$subs = $db->query("SELECT s.*, u.full_name, u.phone, u.email, r.origin, r.destination 
    FROM subscriptions s JOIN users u ON s.user_id=u.id LEFT JOIN routes r ON s.route_id=r.id
    ORDER BY s.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$passengers = $db->query("SELECT id, full_name, email FROM users WHERE role='passenger' AND status='active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$routes     = $db->query("SELECT id, origin, destination FROM routes WHERE is_active=1 ORDER BY origin")->fetch_all(MYSQLI_ASSOC);

$plans = [
    'Economy Monthly'  => ['trips'=>20,'price'=>30000],
    'Standard Monthly' => ['trips'=>30,'price'=>42000],
    'Premium Monthly'  => ['trips'=>60,'price'=>75000],
    'Economy Weekly'   => ['trips'=>5,'price'=>8500],
];

$stats = [
    'active'   => count(array_filter($subs, fn($s) => $s['status']==='active')),
    'revenue'  => array_sum(array_column($subs, 'price_paid')),
    'trips'    => array_sum(array_column($subs, 'trips_used')),
];

include '../includes/header.php';
?>
<style>
.admin-layout{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 64px)}
.admin-sidebar{background:var(--gray-900);padding:16px 10px;position:sticky;top:64px;height:calc(100vh - 64px);overflow-y:auto}
.admin-sidebar .sidebar-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);font-size:13.5px;transition:all .2s;margin-bottom:2px}
.admin-sidebar .sidebar-item:hover,.admin-sidebar .sidebar-item.active{background:rgba(232,82,10,.2);color:var(--orange)}
.admin-sidebar .sidebar-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.3);padding:0 12px;margin:16px 0 8px}
.plan-card{border:2px solid var(--gray-200);border-radius:16px;padding:20px;cursor:pointer;transition:all .2s;background:white}
.plan-card:hover,.plan-card.selected{border-color:var(--orange);background:#FFF0E8}
</style>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div style="padding:8px 12px 16px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:8px"><div style="color:white;font-weight:800;font-size:14px">Admin Panel</div></div>
    <div class="sidebar-label">Navigation</div>
    <a class="sidebar-item" href="dashboard.php"><i class="fas fa-tachometer-alt" style="width:18px"></i> Dashboard</a>
    <a class="sidebar-item" href="promo-codes.php"><i class="fas fa-tag" style="width:18px"></i> Promo Codes</a>
    <a class="sidebar-item" href="maintenance.php"><i class="fas fa-tools" style="width:18px"></i> Maintenance</a>
    <a class="sidebar-item" href="staff.php"><i class="fas fa-id-card" style="width:18px"></i> Staff Records</a>
    <a class="sidebar-item" href="incidents.php"><i class="fas fa-exclamation-triangle" style="width:18px"></i> Incidents</a>
    <a class="sidebar-item active" href="subscriptions.php"><i class="fas fa-sync" style="width:18px"></i> Subscriptions</a>
  </aside>

  <main style="padding:28px;background:var(--gray-50)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
      <div><h1 style="font-size:24px;font-weight:800">Season Pass / Subscriptions</h1><p style="color:var(--gray-500);font-size:13px">Manage passenger monthly & weekly travel passes</p></div>
      <button class="btn btn-primary" onclick="openModal('addSubModal')"><i class="fas fa-plus"></i> Issue Subscription</button>
    </div>

    <?php if ($msg): ?>
    <?php [$t,$m]=explode(':',$msg,2); ?>
    <div style="background:<?= $t==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $t==='success'?'#86EFAC':'#FCA5A5' ?>;border-radius:10px;padding:12px 16px;margin-bottom:20px;color:<?= $t==='success'?'var(--success)':'var(--danger)' ?>;display:flex;gap:8px;font-size:13px">
      <i class="fas fa-<?= $t==='success'?'check':'exclamation' ?>-circle"></i><?= htmlspecialchars($m) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
      <div class="stat-card blue"><div class="stat-value"><?= $stats['active'] ?></div><div class="stat-label">Active Subscriptions</div><div class="stat-icon"><i class="fas fa-sync"></i></div></div>
      <div class="stat-card green"><div class="stat-value"><?= formatMoney($stats['revenue']) ?></div><div class="stat-label">Total Revenue</div><div class="stat-icon"><i class="fas fa-money-bill"></i></div></div>
      <div class="stat-card orange"><div class="stat-value"><?= $stats['trips'] ?></div><div class="stat-label">Trips Used</div><div class="stat-icon"><i class="fas fa-route"></i></div></div>
    </div>

    <!-- Plans Preview -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-title">Available Plans</div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
        <?php foreach($plans as $name=>[$tripsCount,$price]): ?>
        <div style="background:var(--gray-50);border-radius:12px;padding:16px;text-align:center;border:1px solid var(--gray-200)">
          <div style="font-size:13px;font-weight:700;color:var(--gray-800);margin-bottom:4px"><?= $name ?></div>
          <div style="font-size:24px;font-weight:800;color:var(--orange);font-family:var(--mono)">₦<?= number_format($price) ?></div>
          <div style="font-size:12px;color:var(--gray-400);margin-top:2px"><?= $tripsCount ?> trips/period</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Subscriptions Table -->
    <div class="card">
      <div class="card-title">All Subscriptions</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Passenger</th><th>Plan</th><th>Route</th><th>Trips Used</th><th>Valid Until</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach($subs as $s): ?>
          <?php $expired = strtotime($s['end_date']) < time(); ?>
          <tr>
            <td>
              <div style="font-weight:700"><?= htmlspecialchars($s['full_name']) ?></div>
              <div style="font-size:11px;color:var(--gray-400)"><?= $s['phone'] ?></div>
            </td>
            <td style="font-weight:600"><?= htmlspecialchars($s['plan_name']) ?></td>
            <td style="font-size:12px"><?= $s['origin'] ? "{$s['origin']} → {$s['destination']}" : 'All Routes' ?></td>
            <td>
              <div style="font-weight:700"><?= $s['trips_used'] ?>/<?= $s['trips_per_month'] ?></div>
              <div style="background:var(--gray-200);height:4px;border-radius:2px;margin-top:3px;width:80px">
                <div style="background:var(--orange);height:100%;width:<?= min(100,$s['trips_used']/$s['trips_per_month']*100) ?>%;border-radius:2px"></div>
              </div>
            </td>
            <td style="font-size:12px">
              <?= date('d M Y', strtotime($s['end_date'])) ?>
              <?php if ($expired && $s['status']==='active'): ?><div style="color:var(--danger);font-size:10px">EXPIRED</div><?php endif; ?>
            </td>
            <td><?= formatMoney($s['price_paid']) ?></td>
            <td><span class="badge badge-<?= $s['status']==='active'?($expired?'warning':'success'):($s['status']==='expired'?'gray':'danger') ?>"><?= $expired&&$s['status']==='active'?'expired':$s['status'] ?></span></td>
            <td>
              <?php if ($s['status']==='active'): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this subscription?')">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
                <button class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Cancel</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($subs)): ?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--gray-400)">No subscriptions yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Add Subscription Modal -->
<div class="modal-overlay" id="addSubModal">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header"><h3><i class="fas fa-sync" style="color:var(--orange)"></i> Issue Subscription</h3><button class="modal-close" onclick="closeModal('addSubModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form method="POST" id="subForm">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label class="form-label">Passenger</label>
          <select name="user_id" class="form-control" required>
            <option value="">Select passenger</option>
            <?php foreach($passengers as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?> — <?= $p['email'] ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Plan</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <?php foreach($plans as $name=>[$tripsCount,$price]): ?>
            <label style="cursor:pointer">
              <input type="radio" name="plan_name" value="<?= $name ?>" style="display:none" onchange="document.querySelector('[name=trips_per_month]').value=<?= $tripsCount ?>;document.querySelector('[name=price_paid]').value=<?= $price ?>">
              <div class="plan-card">
                <div style="font-weight:700;font-size:12px"><?= $name ?></div>
                <div style="font-size:18px;font-weight:800;color:var(--orange)">₦<?= number_format($price) ?></div>
                <div style="font-size:11px;color:var(--gray-400)"><?= $tripsCount ?> trips</div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group"><label class="form-label">Trips Per Period</label><input type="number" name="trips_per_month" class="form-control" value="20" required></div>
          <div class="form-group"><label class="form-label">Price (₦)</label><input type="number" name="price_paid" class="form-control" value="0" required></div>
          <div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 month')) ?>" required></div>
          <div class="form-group" style="grid-column:span 2"><label class="form-label">Route Restriction (Optional)</label>
            <select name="route_id" class="form-control">
              <option value="">All Routes</option>
              <?php foreach($routes as $r): ?><option value="<?= $r['id'] ?>"><?= $r['origin'] ?> → <?= $r['destination'] ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('addSubModal')">Cancel</button>
      <button class="btn btn-primary" onclick="document.getElementById('subForm').submit()"><i class="fas fa-check"></i> Issue Subscription</button>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.plan-card').forEach(c => {
  c.closest('label').querySelector('input').addEventListener('change', () => {
    document.querySelectorAll('.plan-card').forEach(x => x.classList.remove('selected'));
    c.classList.add('selected');
  });
});
</script>
<?php include '../includes/footer.php'; ?>
