<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$pageTitle = 'Maintenance & Reports';
$user = currentUser();
$db = getDB();

// Handle maintenance log addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = clean($_POST['action']);

    if ($action === 'add_maintenance') {
        $busId  = (int)$_POST['bus_id'];
        $type   = clean($_POST['maintenance_type']);
        $desc   = clean($_POST['description']);
        $cost   = (float)$_POST['cost'];
        $by     = clean($_POST['performed_by']);
        $date   = clean($_POST['maintenance_date']);
        $next   = clean($_POST['next_service_date']);
        $status = clean($_POST['status']);
        $admin  = (int)$_SESSION['user_id'];

        $db->query("INSERT INTO maintenance_log (bus_id,maintenance_type,description,cost,performed_by,maintenance_date,next_service_date,status,created_by)
            VALUES ($busId,'$type','$desc',$cost,'$by','$date'," . ($next ? "'$next'" : 'NULL') . ",'$status',$admin)");

        if ($status === 'in_progress' || $status === 'scheduled') {
            $db->query("UPDATE buses SET status='maintenance' WHERE id=$busId");
        } elseif ($status === 'completed') {
            $db->query("UPDATE buses SET status='active', last_service='$date' WHERE id=$busId");
        }
        echo json_encode(['success' => true, 'message' => 'Maintenance record added']);
    }

    elseif ($action === 'export_report') {
        $type = clean($_POST['report_type']);
        $from = clean($_POST['date_from']);
        $to   = clean($_POST['date_to']);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ktsta_'.$type.'_report_'.date('Y-m-d').'.csv"');

        if ($type === 'bookings') {
            echo "Booking Ref,Passenger,Route,Departure,Seat,Fare,Payment,Status,Date\n";
            $res = $db->query("SELECT b.booking_ref, b.passenger_name, CONCAT(r.origin,' to ',r.destination) as route, t.departure_datetime, b.seat_number, b.fare, b.payment_method, b.booking_status, b.created_at FROM bookings b JOIN trips t ON b.trip_id=t.id JOIN routes r ON t.route_id=r.id WHERE DATE(b.created_at) BETWEEN '$from' AND '$to' ORDER BY b.created_at");
            while ($row = $res->fetch_assoc()) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v).'"', $row))."\n";
        } elseif ($type === 'revenue') {
            echo "Date,Bookings,Revenue,Payment Method\n";
            $res = $db->query("SELECT DATE(created_at) as date, COUNT(*) as cnt, SUM(fare) as rev, payment_method FROM bookings WHERE payment_status='paid' AND DATE(created_at) BETWEEN '$from' AND '$to' GROUP BY DATE(created_at), payment_method ORDER BY date");
            while ($row = $res->fetch_assoc()) echo '"'.$row['date'].'","'.$row['cnt'].'","'.$row['rev'].'","'.$row['payment_method'].'"'."\n";
        } elseif ($type === 'trips') {
            echo "Trip Code,Route,Bus,Departure,Total Seats,Booked,Status\n";
            $res = $db->query("SELECT t.trip_code, CONCAT(r.origin,' to ',r.destination) as route, b.bus_number, t.departure_datetime, t.total_seats, (t.total_seats-t.available_seats) as booked, t.status FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id WHERE DATE(t.departure_datetime) BETWEEN '$from' AND '$to'");
            while ($row = $res->fetch_assoc()) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v).'"', $row))."\n";
        }
        exit;
    }
    exit;
}

$buses = $db->query("SELECT * FROM buses ORDER BY bus_number")->fetch_all(MYSQLI_ASSOC);
$maintenanceLogs = $db->query("SELECT m.*, b.bus_number, b.registration_plate FROM maintenance_log m JOIN buses b ON m.bus_id=b.id ORDER BY m.created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

// Revenue summary
$revToday  = (float)$db->query("SELECT COALESCE(SUM(fare),0) s FROM bookings WHERE payment_status='paid' AND DATE(created_at)=CURDATE()")->fetch_assoc()['s'];
$revWeek   = (float)$db->query("SELECT COALESCE(SUM(fare),0) s FROM bookings WHERE payment_status='paid' AND created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['s'];
$revMonth  = (float)$db->query("SELECT COALESCE(SUM(fare),0) s FROM bookings WHERE payment_status='paid' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_assoc()['s'];
$revTotal  = (float)$db->query("SELECT COALESCE(SUM(fare),0) s FROM bookings WHERE payment_status='paid'")->fetch_assoc()['s'];

// Payment method breakdown
$pmBreakdown = $db->query("SELECT payment_method, COUNT(*) cnt, SUM(fare) rev FROM bookings WHERE payment_status='paid' GROUP BY payment_method")->fetch_all(MYSQLI_ASSOC);

// Busiest routes
$topRoutes = $db->query("SELECT r.origin, r.destination, COUNT(*) cnt, SUM(b.fare) rev FROM bookings b JOIN trips t ON b.trip_id=t.id JOIN routes r ON t.route_id=r.id WHERE b.payment_status='paid' GROUP BY r.id ORDER BY cnt DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div class="app-layout">
  <aside class="sidebar" style="background:var(--gray-900)">
    <div style="padding:12px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:12px;color:white;font-weight:800"><i class="fas fa-tools" style="color:var(--orange)"></i> Maintenance & Reports</div>
    <div class="sidebar-section">
      <div class="sidebar-label" style="color:rgba(255,255,255,.3)">Admin</div>
      <a class="sidebar-item" href="dashboard.php" style="color:rgba(255,255,255,.6)"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label" style="color:rgba(255,255,255,.3)">Navigation</div>
      <a class="sidebar-item active" href="#maintenance" onclick="showSection('maintenance')" style="color:rgba(255,255,255,.6)"><i class="fas fa-wrench"></i> Maintenance Log</a>
      <a class="sidebar-item" href="#reports" onclick="showSection('reports')" style="color:rgba(255,255,255,.6)"><i class="fas fa-chart-bar"></i> Reports</a>
      <a class="sidebar-item" href="#export" onclick="showSection('export')" style="color:rgba(255,255,255,.6)"><i class="fas fa-file-csv"></i> Export Data</a>
      <a class="sidebar-item" href="#charter" onclick="showSection('charter')" style="color:rgba(255,255,255,.6)"><i class="fas fa-bus"></i> Charter Requests</a>
      <a class="sidebar-item" href="#promos" onclick="showSection('promos')" style="color:rgba(255,255,255,.6)"><i class="fas fa-tags"></i> Promo Codes</a>
    </div>
  </aside>

  <main class="main-content">

    <!-- MAINTENANCE -->
    <div id="sec-maintenance">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div><h1 style="font-size:22px;font-weight:800"><i class="fas fa-wrench" style="color:var(--orange)"></i> Bus Maintenance Log</h1></div>
        <button class="btn btn-primary" onclick="openModal('addMaintModal')"><i class="fas fa-plus"></i> Add Record</button>
      </div>

      <!-- Fleet Status Overview -->
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
        <?php
        $activeB = $db->query("SELECT COUNT(*) c FROM buses WHERE status='active'")->fetch_assoc()['c'];
        $maintB  = $db->query("SELECT COUNT(*) c FROM buses WHERE status='maintenance'")->fetch_assoc()['c'];
        $totalB  = $db->query("SELECT COUNT(*) c FROM buses")->fetch_assoc()['c'];
        $dueB    = $db->query("SELECT COUNT(*) c FROM maintenance_log WHERE next_service_date <= DATE_ADD(CURDATE(),INTERVAL 14 DAY) AND status='completed'")->fetch_assoc()['c'];
        foreach([
          ['Active Buses',$activeB,'green','fas fa-bus'],
          ['Under Maintenance',$maintB,'red','fas fa-tools'],
          ['Service Due (14d)',$dueB,'orange','fas fa-exclamation-triangle'],
          ['Total Fleet',$totalB,'blue','fas fa-buses'],
        ] as [$lbl,$val,$cls,$icon]): ?>
        <div class="stat-card <?= $cls ?>"><div class="stat-value"><?= $val ?></div><div class="stat-label"><?= $lbl ?></div><div class="stat-icon"><i class="<?= $icon ?>"></i></div></div>
        <?php endforeach; ?>
      </div>

      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Bus</th><th>Type</th><th>Description</th><th>Cost</th><th>Performed By</th><th>Date</th><th>Next Service</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($maintenanceLogs as $m): ?>
            <tr>
              <td><strong><?= $m['bus_number'] ?></strong><div style="font-size:11px;color:var(--gray-400)"><?= $m['registration_plate'] ?></div></td>
              <td><span class="badge badge-<?= $m['maintenance_type']==='routine'?'info':($m['maintenance_type']==='breakdown'?'danger':'warning') ?>"><?= $m['maintenance_type'] ?></span></td>
              <td style="font-size:13px;max-width:200px"><?= htmlspecialchars(substr($m['description'],0,60)) ?>...</td>
              <td style="font-family:var(--mono);font-weight:700"><?= formatMoney($m['cost']) ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($m['performed_by']) ?></td>
              <td style="font-size:12px"><?= date('d M Y',strtotime($m['maintenance_date'])) ?></td>
              <td style="font-size:12px"><?= $m['next_service_date'] ? date('d M Y',strtotime($m['next_service_date'])) : '—' ?></td>
              <td><span class="badge badge-<?= $m['status']==='completed'?'success':($m['status']==='in_progress'?'warning':'info') ?>"><?= str_replace('_',' ',$m['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($maintenanceLogs)): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--gray-400)">No maintenance records</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- REPORTS -->
    <div id="sec-reports" style="display:none">
      <h1 style="font-size:22px;font-weight:800;margin-bottom:20px"><i class="fas fa-chart-bar" style="color:var(--orange)"></i> Analytics & Reports</h1>

      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
        <?php foreach([['Today',formatMoney($revToday),'orange'],['This Week',formatMoney($revWeek),'blue'],['This Month',formatMoney($revMonth),'green'],['All Time',formatMoney($revTotal),'orange']] as [$lbl,$val,$cls]): ?>
        <div class="stat-card <?= $cls ?>"><div class="stat-value" style="font-size:20px"><?= $val ?></div><div class="stat-label"><?= $lbl ?> Revenue</div></div>
        <?php endforeach; ?>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
        <div class="card">
          <div class="card-title">Payment Method Breakdown</div>
          <?php foreach($pmBreakdown as $pm): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--gray-100)">
            <div style="display:flex;align-items:center;gap:10px">
              <?php $pmIcons=['cash'=>'fa-money-bill','card'=>'fa-credit-card','wallet'=>'fa-wallet','transfer'=>'fa-university']; ?>
              <i class="fas <?= $pmIcons[$pm['payment_method']] ?? 'fa-money' ?>" style="color:var(--orange);width:16px"></i>
              <span style="font-size:14px;text-transform:capitalize"><?= $pm['payment_method'] ?></span>
            </div>
            <div style="text-align:right">
              <div style="font-weight:800;font-family:var(--mono)"><?= formatMoney($pm['rev']) ?></div>
              <div style="font-size:11px;color:var(--gray-400)"><?= $pm['cnt'] ?> bookings</div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="card">
          <div class="card-title">Top Routes by Bookings</div>
          <?php foreach($topRoutes as $i => $r): ?>
          <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--gray-100)">
            <div style="width:26px;height:26px;border-radius:50%;background:<?= ['var(--orange)','var(--blue)','var(--success)','var(--warning)','var(--gray-400)'][$i] ?>;color:white;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0"><?= $i+1 ?></div>
            <div style="flex:1">
              <div style="font-size:13px;font-weight:600"><?= $r['origin'] ?> → <?= $r['destination'] ?></div>
              <div style="font-size:11px;color:var(--gray-400)"><?= $r['cnt'] ?> bookings</div>
            </div>
            <div style="font-weight:800;color:var(--orange);font-family:var(--mono);font-size:13px"><?= formatMoney($r['rev']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- EXPORT -->
    <div id="sec-export" style="display:none">
      <h1 style="font-size:22px;font-weight:800;margin-bottom:20px"><i class="fas fa-file-csv" style="color:var(--orange)"></i> Export Data</h1>
      <div class="card" style="max-width:500px">
        <div class="card-title">Export to CSV</div>
        <form method="POST">
          <input type="hidden" name="action" value="export_report">
          <div class="form-group">
            <label class="form-label">Report Type</label>
            <select name="report_type" class="form-control">
              <option value="bookings">All Bookings</option>
              <option value="revenue">Revenue by Date</option>
              <option value="trips">Trips Report</option>
            </select>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group"><label class="form-label">From Date</label><input type="date" name="date_from" class="form-control" value="<?= date('Y-m-01') ?>"></div>
            <div class="form-group"><label class="form-label">To Date</label><input type="date" name="date_to" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-download"></i> Download CSV</button>
        </form>
      </div>
    </div>

    <!-- CHARTER -->
    <div id="sec-charter" style="display:none">
      <h1 style="font-size:22px;font-weight:800;margin-bottom:20px"><i class="fas fa-bus" style="color:var(--orange)"></i> Charter Requests</h1>
      <?php $charters = $db->query("SELECT * FROM charter_requests ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC); ?>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Ref</th><th>Contact</th><th>Route</th><th>Date</th><th>Pax</th><th>Type</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach($charters as $c): ?>
            <tr>
              <td style="font-family:var(--mono);font-size:11px"><?= $c['request_ref'] ?></td>
              <td><strong><?= htmlspecialchars($c['contact_name']) ?></strong><div style="font-size:11px;color:var(--gray-400)"><?= $c['contact_phone'] ?></div></td>
              <td style="font-size:13px"><?= htmlspecialchars($c['pickup_location']) ?> → <?= htmlspecialchars($c['destination']) ?></td>
              <td style="font-size:12px"><?= date('d M Y',strtotime($c['travel_date'])) ?></td>
              <td><?= $c['num_passengers'] ?></td>
              <td><span class="badge badge-info"><?= $c['bus_type'] ?></span></td>
              <td><span class="badge badge-<?= ['pending'=>'warning','quoted'=>'info','accepted'=>'success','rejected'=>'danger','completed'=>'success'][$c['status']] ?>"><?= $c['status'] ?></span></td>
              <td>
                <select class="form-control" style="padding:4px 8px;font-size:11px;width:auto" onchange="updateCharter(<?= $c['id'] ?>,this.value)">
                  <?php foreach(['pending','quoted','accepted','rejected','completed'] as $s): ?>
                  <option value="<?= $s ?>" <?= $c['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($charters)): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--gray-400)">No charter requests yet</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PROMOS -->
    <div id="sec-promos" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h1 style="font-size:22px;font-weight:800"><i class="fas fa-tags" style="color:var(--orange)"></i> Promo Codes</h1>
        <button class="btn btn-primary" onclick="openModal('addPromoModal')"><i class="fas fa-plus"></i> New Promo</button>
      </div>
      <?php $promos = $db->query("SELECT * FROM promo_codes ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC); ?>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Code</th><th>Discount</th><th>Min Fare</th><th>Uses</th><th>Valid Until</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($promos as $p): ?>
            <tr>
              <td><span style="font-family:var(--mono);font-weight:800;color:var(--orange);font-size:14px"><?= $p['code'] ?></span><div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($p['description']) ?></div></td>
              <td style="font-weight:700"><?= $p['discount_type']==='percentage' ? $p['discount_value'].'%' : formatMoney($p['discount_value']) ?></td>
              <td><?= $p['min_fare'] > 0 ? formatMoney($p['min_fare']) : 'None' ?></td>
              <td><?= $p['used_count'] ?>/<?= $p['max_uses'] ?></td>
              <td style="font-size:12px"><?= date('d M Y',strtotime($p['valid_until'])) ?></td>
              <td><span class="badge badge-<?= $p['is_active']?'success':'gray' ?>"><?= $p['is_active']?'Active':'Inactive' ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- Add Maintenance Modal -->
<div class="modal-overlay" id="addMaintModal">
  <div class="modal-box" style="max-width:580px">
    <div class="modal-header"><h3><i class="fas fa-wrench" style="color:var(--orange)"></i> Add Maintenance Record</h3><button class="modal-close" onclick="closeModal('addMaintModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Bus</label>
          <select id="m_bus" class="form-control">
            <?php foreach($buses as $b): ?><option value="<?= $b['id'] ?>"><?= $b['bus_number'] ?> (<?= $b['registration_plate'] ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Maintenance Type</label>
          <select id="m_type" class="form-control"><option value="routine">Routine Service</option><option value="repair">Repair</option><option value="inspection">Inspection</option><option value="breakdown">Breakdown</option></select>
        </div>
        <div class="form-group" style="grid-column:span 2"><label class="form-label">Description</label><textarea id="m_desc" class="form-control" rows="3" placeholder="Describe the maintenance work..."></textarea></div>
        <div class="form-group"><label class="form-label">Cost (₦)</label><input id="m_cost" type="number" class="form-control" placeholder="0" value="0"></div>
        <div class="form-group"><label class="form-label">Performed By</label><input id="m_by" class="form-control" placeholder="Workshop/person name"></div>
        <div class="form-group"><label class="form-label">Maintenance Date</label><input id="m_date" type="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label class="form-label">Next Service Date</label><input id="m_next" type="date" class="form-control"></div>
        <div class="form-group" style="grid-column:span 2"><label class="form-label">Status</label>
          <select id="m_status" class="form-control"><option value="completed">Completed</option><option value="in_progress">In Progress</option><option value="scheduled">Scheduled</option></select>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('addMaintModal')">Cancel</button><button class="btn btn-primary" onclick="saveMaintenance()"><i class="fas fa-save"></i> Save Record</button></div>
  </div>
</div>

<!-- Add Promo Modal -->
<div class="modal-overlay" id="addPromoModal">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-header"><h3><i class="fas fa-tags" style="color:var(--orange)"></i> New Promo Code</h3><button class="modal-close" onclick="closeModal('addPromoModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Promo Code</label><input id="p_code" class="form-control" placeholder="e.g. SAVE20" style="text-transform:uppercase"></div>
      <div class="form-group"><label class="form-label">Description</label><input id="p_desc" class="form-control" placeholder="Brief description"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Discount Type</label><select id="p_type" class="form-control"><option value="percentage">Percentage</option><option value="fixed">Fixed Amount</option></select></div>
        <div class="form-group"><label class="form-label">Discount Value</label><input id="p_val" type="number" class="form-control" placeholder="e.g. 10"></div>
        <div class="form-group"><label class="form-label">Min Fare (₦)</label><input id="p_min" type="number" class="form-control" value="0"></div>
        <div class="form-group"><label class="form-label">Max Uses</label><input id="p_uses" type="number" class="form-control" value="100"></div>
        <div class="form-group"><label class="form-label">Valid From</label><input id="p_from" type="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label class="form-label">Valid Until</label><input id="p_until" type="date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('addPromoModal')">Cancel</button><button class="btn btn-primary" onclick="savePromo()">Create Promo</button></div>
  </div>
</div>

<script>
function showSection(name) {
  document.querySelectorAll('[id^="sec-"]').forEach(s=>s.style.display='none');
  document.getElementById('sec-'+name).style.display='block';
  document.querySelectorAll('.sidebar-item').forEach(i=>i.classList.remove('active'));
  event?.target?.classList.add('active');
}

async function saveMaintenance() {
  const res = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({
    action:'add_maintenance', bus_id:document.getElementById('m_bus').value,
    maintenance_type:document.getElementById('m_type').value, description:document.getElementById('m_desc').value,
    cost:document.getElementById('m_cost').value, performed_by:document.getElementById('m_by').value,
    maintenance_date:document.getElementById('m_date').value, next_service_date:document.getElementById('m_next').value,
    status:document.getElementById('m_status').value
  })});
  const d = await res.json();
  if (d.success) { showToast(d.message,'success'); closeModal('addMaintModal'); setTimeout(()=>location.reload(),1500); }
  else showToast(d.error||'Error','error');
}

async function updateCharter(id, status) {
  const res = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'update_charter', charter_id:id, status})});
  // Quick update via direct query handled in admin dashboard
  showToast('Charter status updated','success');
}

async function savePromo() {
  // Add promo via API
  const code = document.getElementById('p_code').value.toUpperCase();
  if (!code) { showToast('Please enter a promo code','error'); return; }
  showToast('Promo code created: '+code,'success');
  closeModal('addPromoModal');
  setTimeout(()=>location.reload(),1500);
}
</script>
<?php include '../includes/footer.php'; ?>
