<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$pageTitle = 'Staff Management';
$user = currentUser();
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = clean($_POST['action']);

    if ($action === 'add_staff') {
        $name  = clean($_POST['full_name']);
        $email = clean($_POST['email']);
        $phone = clean($_POST['phone']);
        $role  = clean($_POST['role']);
        $rawPw = !empty($_POST['password']) ? $_POST['password'] : 'KTSTA@2026';
        $pass  = password_hash($rawPw, PASSWORD_DEFAULT);
        $chk   = $db->query("SELECT id FROM users WHERE email='$email' OR phone='$phone'");
        if ($chk->num_rows > 0) { echo json_encode(['success'=>false,'error'=>'Email or phone already exists']); exit; }
        $db->query("INSERT INTO users (full_name,email,phone,password,role,is_verified,status) VALUES ('$name','$email','$phone','$pass','$role',1,'active')");
        $newId = $db->insert_id;
        addNotification($newId,'Welcome to KTSTA Staff Portal',"Your $role account has been created. Welcome aboard!",'system');
        echo json_encode(['success'=>true,'message'=>"$role account created for $name"]);

    } elseif ($action === 'update_staff') {
        $id     = (int)$_POST['staff_id'];
        $name   = clean($_POST['full_name']);
        $phone  = clean($_POST['phone']);
        $role   = clean($_POST['role']);
        $status = clean($_POST['status']);
        $db->query("UPDATE users SET full_name='$name',phone='$phone',role='$role',status='$status' WHERE id=$id");
        echo json_encode(['success'=>true,'message'=>'Staff updated']);

    } elseif ($action === 'reset_staff_password') {
        $id   = (int)$_POST['staff_id'];
        $hash = password_hash('KTSTA@2026', PASSWORD_DEFAULT);
        $db->query("UPDATE users SET password='$hash' WHERE id=$id");
        echo json_encode(['success'=>true,'message'=>'Password reset to KTSTA@2026']);

    } elseif ($action === 'assign_bus') {
        $driverId = (int)$_POST['driver_id'];
        $busId    = (int)$_POST['bus_id'];
        $db->query("UPDATE buses SET driver_id=NULL WHERE driver_id=$driverId");
        if ($busId) $db->query("UPDATE buses SET driver_id=$driverId WHERE id=$busId");
        echo json_encode(['success'=>true,'message'=>'Bus assignment updated']);

    } elseif ($action === 'assign_trip_officer') {
        $officerId = (int)$_POST['officer_id'];
        $tripId    = (int)$_POST['trip_id'];
        $db->query("UPDATE trips SET officer_id=$officerId WHERE id=$tripId");
        echo json_encode(['success'=>true,'message'=>'Officer assigned to trip']);
    }
    exit;
}

$officers        = $db->query("SELECT * FROM users WHERE role='officer' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$drivers         = $db->query("SELECT u.*, b.bus_number FROM users u LEFT JOIN buses b ON u.id=b.driver_id WHERE u.role='driver' ORDER BY u.full_name")->fetch_all(MYSQLI_ASSOC);
$allStaff        = $db->query("SELECT * FROM users WHERE role IN ('officer','driver','admin') ORDER BY role,full_name")->fetch_all(MYSQLI_ASSOC);
$unassignedTrips = $db->query("SELECT t.id,t.trip_code,r.origin,r.destination,t.departure_datetime FROM trips t JOIN routes r ON t.route_id=r.id WHERE t.officer_id IS NULL AND t.status='scheduled' AND t.departure_datetime > NOW() ORDER BY t.departure_datetime ASC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$buses           = $db->query("SELECT b.*,u.full_name as current_driver FROM buses b LEFT JOIN users u ON b.driver_id=u.id WHERE b.status='active' ORDER BY b.bus_number")->fetch_all(MYSQLI_ASSOC);
$activeToday     = $db->query("SELECT COUNT(DISTINCT driver_id) c FROM trips WHERE DATE(departure_datetime)=CURDATE() AND status IN ('boarding','in_transit') AND driver_id IS NOT NULL")->fetch_assoc()['c'];
$unassignedBuses = $db->query("SELECT COUNT(*) c FROM buses WHERE driver_id IS NULL AND status='active'")->fetch_assoc()['c'];

include '../includes/header.php';
?>
<style>
.staff-card { background:white; border-radius:16px; border:2px solid var(--gray-200); padding:18px; display:flex; gap:14px; align-items:center; transition:all .2s; }
.staff-card:hover { border-color:var(--orange); box-shadow:var(--shadow); }
.staff-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; }
</style>

<div class="app-layout">
  <aside class="sidebar" style="background:var(--gray-900)">
    <div style="padding:12px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:12px;display:flex;align-items:center;gap:10px">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--orange);display:flex;align-items:center;justify-content:center;color:white"><i class="fas fa-users"></i></div>
      <div style="color:white;font-weight:800;font-size:14px">Staff Management</div>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label" style="color:rgba(255,255,255,.3)">Navigation</div>
      <a class="sidebar-item" href="dashboard.php" style="color:rgba(255,255,255,.6)"><i class="fas fa-arrow-left"></i> Admin Dashboard</a>
      <a class="sidebar-item active" onclick="showTab('overview',this)" style="color:rgba(255,255,255,.6);cursor:pointer"><i class="fas fa-chart-pie"></i> Overview</a>
      <a class="sidebar-item" onclick="showTab('officers',this)" style="color:rgba(255,255,255,.6);cursor:pointer"><i class="fas fa-id-badge"></i> Officers (<?= count($officers) ?>)</a>
      <a class="sidebar-item" onclick="showTab('drivers',this)" style="color:rgba(255,255,255,.6);cursor:pointer"><i class="fas fa-bus"></i> Drivers (<?= count($drivers) ?>)</a>
      <a class="sidebar-item" onclick="showTab('assignments',this)" style="color:rgba(255,255,255,.6);cursor:pointer"><i class="fas fa-tasks"></i> Assignments</a>
    </div>
  </aside>

  <main class="main-content">

    <!-- OVERVIEW -->
    <div id="stab-overview">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div><h1 style="font-size:22px;font-weight:800">Staff Management</h1><p style="color:var(--gray-400);font-size:13px">Manage officers, drivers and assignments</p></div>
        <button class="btn btn-primary" onclick="openModal('addStaffModal')"><i class="fas fa-user-plus"></i> Add Staff</button>
      </div>
      <div class="stats-grid" style="margin-bottom:24px">
        <?php foreach([['Ticket Officers',count($officers),'blue','fas fa-id-badge'],['Drivers',count($drivers),'orange','fas fa-bus'],['On Duty Today',$activeToday,'green','fas fa-user-check'],['Buses Without Driver',$unassignedBuses,'red','fas fa-exclamation-triangle']] as [$lbl,$val,$cls,$icon]): ?>
        <div class="stat-card <?= $cls ?>"><div class="stat-value"><?= $val ?></div><div class="stat-label"><?= $lbl ?></div><div class="stat-icon"><i class="<?= $icon ?>"></i></div></div>
        <?php endforeach; ?>
      </div>
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <div class="card-title" style="margin:0">All Staff Members</div>
          <input type="text" id="staffSearch" class="form-control" style="max-width:220px" placeholder="Search..." oninput="filterStaff(this.value)">
        </div>
        <div class="table-wrap">
          <table id="staffTable">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($allStaff as $s): ?>
            <tr data-name="<?= strtolower($s['full_name']) ?>">
              <td><div style="display:flex;align-items:center;gap:10px"><div style="width:36px;height:36px;border-radius:10px;background:<?= $s['role']==='driver'?'var(--blue)':'var(--orange)' ?>;color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px"><?= strtoupper(substr($s['full_name'],0,1)) ?></div><strong><?= htmlspecialchars($s['full_name']) ?></strong></div></td>
              <td style="font-size:12px"><?= $s['email'] ?></td>
              <td style="font-size:12px"><?= $s['phone'] ?></td>
              <td><span class="badge badge-<?= $s['role']==='admin'?'danger':($s['role']==='officer'?'info':'blue') ?>"><?= $s['role'] ?></span></td>
              <td><span class="badge badge-<?= $s['status']==='active'?'success':'danger' ?>"><?= $s['status'] ?></span></td>
              <td><div style="display:flex;gap:5px">
                <button class="btn btn-ghost btn-sm" onclick="editStaff(<?= $s['id'] ?>,'<?= htmlspecialchars(addslashes($s['full_name'])) ?>','<?= $s['phone'] ?>','<?= $s['role'] ?>','<?= $s['status'] ?>')"><i class="fas fa-edit"></i></button>
                <button class="btn btn-warning btn-sm" onclick="resetPwd(<?= $s['id'] ?>)" title="Reset password"><i class="fas fa-key"></i></button>
                <?php if($s['role']!=='admin'): ?>
                <button class="btn btn-<?= $s['status']==='active'?'danger':'success' ?> btn-sm" onclick="toggleStaff(<?= $s['id'] ?>,'<?= $s['status']==='active'?'suspended':'active' ?>')"><i class="fas fa-<?= $s['status']==='active'?'ban':'check' ?>"></i></button>
                <?php endif; ?>
              </div></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- OFFICERS -->
    <div id="stab-officers" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h1 style="font-size:22px;font-weight:800">Ticket Officers</h1>
        <button class="btn btn-primary" onclick="openModal('addStaffModal')"><i class="fas fa-plus"></i> Add Officer</button>
      </div>
      <div class="staff-grid">
        <?php foreach($officers as $o): ?>
        <div class="staff-card">
          <div style="width:50px;height:50px;border-radius:14px;background:linear-gradient(135deg,var(--orange),var(--orange-dark));display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:white;flex-shrink:0"><?= strtoupper(substr($o['full_name'],0,1)) ?></div>
          <div style="flex:1"><div style="font-weight:700"><?= htmlspecialchars($o['full_name']) ?></div><div style="font-size:12px;color:var(--gray-400)"><?= $o['phone'] ?></div></div>
          <span class="badge badge-<?= $o['status']==='active'?'success':'danger' ?>"><?= $o['status'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if(empty($officers)): ?><div style="grid-column:span 3;text-align:center;padding:40px;color:var(--gray-400)">No officers yet.</div><?php endif; ?>
      </div>
    </div>

    <!-- DRIVERS -->
    <div id="stab-drivers" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h1 style="font-size:22px;font-weight:800">Drivers</h1>
        <button class="btn btn-primary" onclick="openModal('addStaffModal')"><i class="fas fa-plus"></i> Add Driver</button>
      </div>
      <div class="staff-grid">
        <?php foreach($drivers as $d): ?>
        <div class="staff-card">
          <div style="width:50px;height:50px;border-radius:14px;background:linear-gradient(135deg,var(--blue),var(--blue-dark));display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:white;flex-shrink:0"><?= strtoupper(substr($d['full_name'],0,1)) ?></div>
          <div style="flex:1"><div style="font-weight:700"><?= htmlspecialchars($d['full_name']) ?></div><div style="font-size:12px;color:var(--gray-400)"><?= $d['phone'] ?></div><?php if($d['bus_number']): ?><span style="background:#EFF6FF;color:var(--blue);font-size:10px;padding:2px 8px;border-radius:20px;margin-top:4px;display:inline-block">🚌 <?= $d['bus_number'] ?></span><?php endif; ?></div>
          <span class="badge badge-<?= $d['status']==='active'?'success':'danger' ?>"><?= $d['status'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if(empty($drivers)): ?><div style="grid-column:span 3;text-align:center;padding:40px;color:var(--gray-400)">No drivers yet.</div><?php endif; ?>
      </div>
    </div>

    <!-- ASSIGNMENTS -->
    <div id="stab-assignments" style="display:none">
      <h1 style="font-size:22px;font-weight:800;margin-bottom:20px">Assignments</h1>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
        <div class="card">
          <div class="card-title"><i class="fas fa-bus" style="color:var(--orange)"></i> Assign Driver to Bus</div>
          <div class="form-group"><label class="form-label">Driver</label><select id="assign_driver" class="form-control"><option value="">Select driver...</option><?php foreach($drivers as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?> <?= $d['bus_number']?'(has '.$d['bus_number'].')':'' ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label class="form-label">Bus</label><select id="assign_bus" class="form-control"><option value="">Unassign</option><?php foreach($buses as $b): ?><option value="<?= $b['id'] ?>"><?= $b['bus_number'] ?> <?= $b['current_driver']?'('.$b['current_driver'].')':'(free)' ?></option><?php endforeach; ?></select></div>
          <button class="btn btn-primary" onclick="assignBus()"><i class="fas fa-save"></i> Save</button>
        </div>
        <div class="card">
          <div class="card-title"><i class="fas fa-id-badge" style="color:var(--blue)"></i> Assign Officer to Trip</div>
          <div class="form-group"><label class="form-label">Officer</label><select id="assign_officer" class="form-control"><option value="">Select officer...</option><?php foreach($officers as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label class="form-label">Trip</label><select id="assign_trip" class="form-control"><option value="">Select trip...</option><?php foreach($unassignedTrips as $t): ?><option value="<?= $t['id'] ?>"><?= $t['origin'] ?> → <?= $t['destination'] ?> (<?= date('d M H:i',strtotime($t['departure_datetime'])) ?>)</option><?php endforeach; ?></select></div>
          <button class="btn btn-secondary" onclick="assignOfficer()"><i class="fas fa-save"></i> Assign</button>
        </div>
      </div>
      <div class="card">
        <div class="card-title">Fleet Driver Assignment Status</div>
        <div class="table-wrap"><table><thead><tr><th>Bus</th><th>Plate</th><th>Type</th><th>Driver</th><th>Status</th></tr></thead><tbody>
        <?php foreach($buses as $b): ?><tr><td><strong><?= $b['bus_number'] ?></strong></td><td style="font-family:var(--mono);font-size:12px"><?= $b['registration_plate'] ?></td><td><?= ucfirst($b['bus_type']) ?></td><td><?= $b['current_driver'] ? '<span style="color:var(--success);font-weight:600">'.htmlspecialchars($b['current_driver']).'</span>' : '<span style="color:var(--gray-400)">Unassigned</span>' ?></td><td><span class="badge badge-<?= $b['status']==='active'?'success':'warning' ?>"><?= $b['status'] ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>
    </div>

  </main>
</div>

<!-- Add Staff Modal -->
<div class="modal-overlay" id="addStaffModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header"><h3><i class="fas fa-user-plus" style="color:var(--orange)"></i> Add Staff Member</h3><button class="modal-close" onclick="closeModal('addStaffModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group" style="grid-column:span 2"><label class="form-label">Full Name *</label><input id="ns_name" class="form-control" placeholder="e.g. Musa Aliyu Ibrahim"></div>
        <div class="form-group"><label class="form-label">Email *</label><input id="ns_email" type="email" class="form-control"></div>
        <div class="form-group"><label class="form-label">Phone *</label><input id="ns_phone" class="form-control"></div>
        <div class="form-group"><label class="form-label">Role</label><select id="ns_role" class="form-control"><option value="officer">Ticket Officer</option><option value="driver">Driver</option><option value="admin">Admin</option></select></div>
        <div class="form-group"><label class="form-label">Password</label><input id="ns_pass" type="password" class="form-control" placeholder="Blank = KTSTA@2026"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('addStaffModal')">Cancel</button><button class="btn btn-primary" onclick="addStaff()"><i class="fas fa-save"></i> Create Account</button></div>
  </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal-overlay" id="editStaffModal">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header"><h3>Edit Staff</h3><button class="modal-close" onclick="closeModal('editStaffModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <input type="hidden" id="edit_id">
      <div class="form-group"><label class="form-label">Full Name</label><input id="edit_name" class="form-control"></div>
      <div class="form-group"><label class="form-label">Phone</label><input id="edit_phone" class="form-control"></div>
      <div class="form-group"><label class="form-label">Role</label><select id="edit_role" class="form-control"><option value="officer">Officer</option><option value="driver">Driver</option><option value="admin">Admin</option></select></div>
      <div class="form-group"><label class="form-label">Status</label><select id="edit_status" class="form-control"><option value="active">Active</option><option value="suspended">Suspended</option><option value="inactive">Inactive</option></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('editStaffModal')">Cancel</button><button class="btn btn-primary" onclick="saveEdit()">Save</button></div>
  </div>
</div>

<script>
function showTab(name, el) {
  document.querySelectorAll('[id^="stab-"]').forEach(s=>s.style.display='none');
  document.querySelectorAll('.sidebar-item').forEach(i=>i.classList.remove('active'));
  document.getElementById('stab-'+name).style.display='block';
  if(el) el.classList.add('active');
}
function filterStaff(q) {
  document.querySelectorAll('#staffTable tbody tr').forEach(r=>r.style.display=r.dataset.name.includes(q.toLowerCase())?'':'none');
}
async function post(d) { return (await fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)})).json(); }
async function addStaff() {
  const d=await post({action:'add_staff',full_name:document.getElementById('ns_name').value,email:document.getElementById('ns_email').value,phone:document.getElementById('ns_phone').value,role:document.getElementById('ns_role').value,password:document.getElementById('ns_pass').value});
  d.success?(showToast(d.message,'success'),closeModal('addStaffModal'),setTimeout(()=>location.reload(),1400)):showToast(d.error,'error');
}
function editStaff(id,name,phone,role,status){document.getElementById('edit_id').value=id;document.getElementById('edit_name').value=name;document.getElementById('edit_phone').value=phone;document.getElementById('edit_role').value=role;document.getElementById('edit_status').value=status;openModal('editStaffModal');}
async function saveEdit(){const d=await post({action:'update_staff',staff_id:document.getElementById('edit_id').value,full_name:document.getElementById('edit_name').value,phone:document.getElementById('edit_phone').value,role:document.getElementById('edit_role').value,status:document.getElementById('edit_status').value});d.success?(showToast(d.message,'success'),closeModal('editStaffModal'),setTimeout(()=>location.reload(),1200)):showToast('Failed','error');}
async function resetPwd(id){if(!confirm('Reset password to KTSTA@2026?'))return;const d=await post({action:'reset_staff_password',staff_id:id});showToast(d.message,d.success?'success':'error');}
async function toggleStaff(id,status){const d=await post({action:'update_staff',staff_id:id,full_name:'x',phone:'x',role:'x',status});d.success&&setTimeout(()=>location.reload(),800);}
async function assignBus(){const d=await post({action:'assign_bus',driver_id:document.getElementById('assign_driver').value,bus_id:document.getElementById('assign_bus').value});showToast(d.message,d.success?'success':'error');}
async function assignOfficer(){const d=await post({action:'assign_trip_officer',officer_id:document.getElementById('assign_officer').value,trip_id:document.getElementById('assign_trip').value});showToast(d.message,d.success?'success':'error');}
</script>
<?php include '../includes/footer.php'; ?>
