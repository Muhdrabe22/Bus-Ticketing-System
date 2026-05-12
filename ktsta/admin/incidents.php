<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$pageTitle = 'Trip Incidents';
$user = currentUser();
$db   = getDB();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');
    if ($action === 'resolve') {
        $id   = (int)$_POST['incident_id'];
        $res  = clean($_POST['resolution']);
        $db->query("UPDATE trip_incidents SET status='resolved', resolution='$res' WHERE id=$id");
        $msg  = 'success:Incident resolved.';
    } elseif ($action === 'add') {
        $tid  = (int)$_POST['trip_id'];
        $type = clean($_POST['incident_type']);
        $desc = clean($_POST['description']);
        $loc  = clean($_POST['location']);
        $sev  = clean($_POST['severity']);
        $by   = (int)$_SESSION['user_id'];
        $db->query("INSERT INTO trip_incidents (trip_id,reported_by,incident_type,description,location,severity) VALUES ($tid,'$by','$type','$desc','$loc','$sev')");
        $msg  = 'success:Incident reported.';
    }
}

$incidents = $db->query("SELECT ti.*, t.trip_code, r.origin, r.destination, t.departure_datetime, u.full_name as reporter
    FROM trip_incidents ti JOIN trips t ON ti.trip_id=t.id JOIN routes r ON t.route_id=r.id LEFT JOIN users u ON ti.reported_by=u.id
    ORDER BY ti.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

$trips = $db->query("SELECT t.id, t.trip_code, r.origin, r.destination, t.departure_datetime FROM trips t JOIN routes r ON t.route_id=r.id ORDER BY t.departure_datetime DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);

$counts = [
    'open'         => count(array_filter($incidents, fn($i) => $i['status']==='reported')),
    'investigating'=> count(array_filter($incidents, fn($i) => $i['status']==='investigating')),
    'resolved'     => count(array_filter($incidents, fn($i) => $i['status']==='resolved')),
    'critical'     => count(array_filter($incidents, fn($i) => $i['severity']==='critical')),
];

include '../includes/header.php';
?>
<style>
.admin-layout{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 64px)}
.admin-sidebar{background:var(--gray-900);padding:16px 10px;position:sticky;top:64px;height:calc(100vh - 64px);overflow-y:auto}
.admin-sidebar .sidebar-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);font-size:13.5px;transition:all .2s;margin-bottom:2px}
.admin-sidebar .sidebar-item:hover,.admin-sidebar .sidebar-item.active{background:rgba(232,82,10,.2);color:var(--orange)}
.admin-sidebar .sidebar-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.3);padding:0 12px;margin:16px 0 8px}
.sev-dot{width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0}
</style>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div style="padding:8px 12px 16px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:8px"><div style="color:white;font-weight:800;font-size:14px">Admin Panel</div></div>
    <div class="sidebar-label">Navigation</div>
    <a class="sidebar-item" href="dashboard.php"><i class="fas fa-tachometer-alt" style="width:18px"></i> Dashboard</a>
    <a class="sidebar-item" href="promo-codes.php"><i class="fas fa-tag" style="width:18px"></i> Promo Codes</a>
    <a class="sidebar-item" href="maintenance.php"><i class="fas fa-tools" style="width:18px"></i> Maintenance</a>
    <a class="sidebar-item" href="staff.php"><i class="fas fa-id-card" style="width:18px"></i> Staff Records</a>
    <a class="sidebar-item active" href="incidents.php"><i class="fas fa-exclamation-triangle" style="width:18px"></i> Incidents</a>
    <a class="sidebar-item" href="subscriptions.php"><i class="fas fa-sync" style="width:18px"></i> Subscriptions</a>
  </aside>

  <main style="padding:28px;background:var(--gray-50)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
      <div><h1 style="font-size:24px;font-weight:800">Trip Incidents</h1><p style="color:var(--gray-500);font-size:13px">Track and resolve reported incidents</p></div>
      <button class="btn btn-primary" onclick="openModal('addIncModal')"><i class="fas fa-plus"></i> Report Incident</button>
    </div>

    <?php if ($msg): ?>
    <?php [$t,$m]=explode(':',$msg,2); ?>
    <div style="background:<?= $t==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $t==='success'?'#86EFAC':'#FCA5A5' ?>;border-radius:10px;padding:12px 16px;margin-bottom:20px;color:<?= $t==='success'?'var(--success)':'var(--danger)' ?>;display:flex;gap:8px;font-size:13px">
      <i class="fas fa-<?= $t==='success'?'check':'exclamation' ?>-circle"></i><?= htmlspecialchars($m) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
      <div class="stat-card red"><div class="stat-value"><?= $counts['open'] ?></div><div class="stat-label">Open Cases</div><div class="stat-icon"><i class="fas fa-flag"></i></div></div>
      <div class="stat-card orange"><div class="stat-value"><?= $counts['investigating'] ?></div><div class="stat-label">Investigating</div><div class="stat-icon"><i class="fas fa-search"></i></div></div>
      <div class="stat-card green"><div class="stat-value"><?= $counts['resolved'] ?></div><div class="stat-label">Resolved</div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
      <div class="stat-card red"><div class="stat-value"><?= $counts['critical'] ?></div><div class="stat-label">Critical</div><div class="stat-icon"><i class="fas fa-radiation-alt"></i></div></div>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Trip</th><th>Type</th><th>Description</th><th>Location</th><th>Severity</th><th>Reported By</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach($incidents as $inc): ?>
          <?php
            $sevColors=['low'=>'#16A34A','medium'=>'#D97706','high'=>'#DC2626','critical'=>'#7C3AED'];
            $stColors=['reported'=>'danger','investigating'=>'warning','resolved'=>'success','closed'=>'gray'];
          ?>
          <tr>
            <td>
              <div style="font-size:12px;font-weight:700"><?= $inc['trip_code'] ?></div>
              <div style="font-size:11px;color:var(--gray-400)"><?= $inc['origin'] ?> → <?= $inc['destination'] ?></div>
              <div style="font-size:10px;color:var(--gray-300)"><?= date('d M H:i',strtotime($inc['created_at'])) ?></div>
            </td>
            <td><span class="badge badge-info"><?= str_replace('_',' ',$inc['incident_type']) ?></span></td>
            <td style="max-width:180px;font-size:12px"><?= htmlspecialchars(substr($inc['description'],0,80)) ?><?= strlen($inc['description'])>80?'…':'' ?></td>
            <td style="font-size:12px"><?= htmlspecialchars($inc['location'] ?: '—') ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <div class="sev-dot" style="background:<?= $sevColors[$inc['severity']] ?? '#ccc' ?>"></div>
                <span style="font-size:12px;font-weight:600;color:<?= $sevColors[$inc['severity']] ?? '#ccc' ?>"><?= ucfirst($inc['severity']) ?></span>
              </div>
            </td>
            <td style="font-size:12px"><?= htmlspecialchars($inc['reporter'] ?? 'System') ?></td>
            <td><span class="badge badge-<?= $stColors[$inc['status']] ?? 'gray' ?>"><?= $inc['status'] ?></span></td>
            <td>
              <?php if ($inc['status'] !== 'resolved' && $inc['status'] !== 'closed'): ?>
              <button class="btn btn-success btn-sm" onclick="resolveIncident(<?= $inc['id'] ?>)"><i class="fas fa-check"></i> Resolve</button>
              <?php else: ?>
              <span style="font-size:11px;color:var(--success)"><i class="fas fa-check-circle"></i> Done</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($incidents)): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--gray-400)">No incidents reported</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Add Incident Modal -->
<div class="modal-overlay" id="addIncModal">
  <div class="modal-box">
    <div class="modal-header"><h3><i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i> Report Incident</h3><button class="modal-close" onclick="closeModal('addIncModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form method="POST" id="incForm">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label class="form-label">Trip</label>
          <select name="trip_id" class="form-control" required>
            <option value="">Select trip</option>
            <?php foreach($trips as $t): ?><option value="<?= $t['id'] ?>"><?= $t['trip_code'] ?> — <?= $t['origin'] ?> → <?= $t['destination'] ?> (<?= date('d M H:i',strtotime($t['departure_datetime'])) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group"><label class="form-label">Incident Type</label>
            <select name="incident_type" class="form-control">
              <?php foreach(['accident','breakdown','delay','passenger_issue','medical','other'] as $it): ?><option value="<?= $it ?>"><?= ucfirst(str_replace('_',' ',$it)) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Severity</label>
            <select name="severity" class="form-control">
              <?php foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical ⚠️'] as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Location</label><input name="location" class="form-control" placeholder="Where did this occur?"></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" placeholder="Describe what happened..." required></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('addIncModal')">Cancel</button>
      <button class="btn btn-danger" onclick="document.getElementById('incForm').submit()"><i class="fas fa-flag"></i> Report Incident</button>
    </div>
  </div>
</div>

<!-- Resolve Modal -->
<div class="modal-overlay" id="resolveModal">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-header"><h3><i class="fas fa-check-circle" style="color:var(--success)"></i> Resolve Incident</h3><button class="modal-close" onclick="closeModal('resolveModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form method="POST" id="resolveForm">
        <input type="hidden" name="action" value="resolve">
        <input type="hidden" name="incident_id" id="resolveId">
        <div class="form-group"><label class="form-label">Resolution Notes</label><textarea name="resolution" class="form-control" rows="4" placeholder="Describe how this was resolved..." required></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('resolveModal')">Cancel</button>
      <button class="btn btn-success" onclick="document.getElementById('resolveForm').submit()"><i class="fas fa-check"></i> Mark Resolved</button>
    </div>
  </div>
</div>

<script>
function resolveIncident(id) {
  document.getElementById('resolveId').value = id;
  openModal('resolveModal');
}
</script>
<?php include '../includes/footer.php'; ?>
