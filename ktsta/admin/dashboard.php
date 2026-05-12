<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$pageTitle = 'Admin Dashboard';
$user = currentUser();
$db = getDB();

// Stats overview
$stats = [
    'users'    => $db->query("SELECT COUNT(*) c FROM users WHERE role='passenger'")->fetch_assoc()['c'],
    'trips'    => $db->query("SELECT COUNT(*) c FROM trips WHERE DATE(departure_datetime)=CURDATE()")->fetch_assoc()['c'],
    'bookings' => $db->query("SELECT COUNT(*) c FROM bookings WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'],
    'revenue'  => $db->query("SELECT COALESCE(SUM(fare),0) c FROM bookings WHERE payment_status='paid' AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'],
    'buses'    => $db->query("SELECT COUNT(*) c FROM buses WHERE status='active'")->fetch_assoc()['c'],
    'routes'   => $db->query("SELECT COUNT(*) c FROM routes WHERE is_active=1")->fetch_assoc()['c'],
    'total_rev'=> $db->query("SELECT COALESCE(SUM(fare),0) c FROM bookings WHERE payment_status='paid'")->fetch_assoc()['c'],
    'pending'  => $db->query("SELECT COUNT(*) c FROM bookings WHERE payment_status='pending'")->fetch_assoc()['c'],
];

// Recent data
$recentBookings = $db->query("SELECT b.*, u.full_name, r.origin, r.destination, t.departure_datetime
  FROM bookings b JOIN users u ON b.passenger_id=u.id JOIN trips t ON b.trip_id=t.id JOIN routes r ON t.route_id=r.id
  ORDER BY b.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$allRoutes = $db->query("SELECT * FROM routes ORDER BY origin")->fetch_all(MYSQLI_ASSOC);
$allBuses = $db->query("SELECT b.*, u.full_name as driver FROM buses b LEFT JOIN users u ON b.driver_id=u.id ORDER BY b.bus_number")->fetch_all(MYSQLI_ASSOC);
$allTrips = $db->query("SELECT t.*, r.origin, r.destination, b2.bus_number, u.full_name as driver
  FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b2 ON t.bus_id=b2.id LEFT JOIN users u ON t.driver_id=u.id
  ORDER BY t.departure_datetime DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$allUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$allDrivers = $db->query("SELECT id, full_name FROM users WHERE role='driver' AND status='active'")->fetch_all(MYSQLI_ASSOC);
$allOfficers = $db->query("SELECT id, full_name FROM users WHERE role IN ('officer','admin') AND status='active'")->fetch_all(MYSQLI_ASSOC);
$feedback = $db->query("SELECT f.*, u.full_name FROM feedback f LEFT JOIN users u ON f.user_id=u.id ORDER BY f.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$announcements = $db->query("SELECT a.*, u.full_name as author FROM announcements a LEFT JOIN users u ON a.created_by=u.id ORDER BY a.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Chart data - revenue last 7 days
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $rev = $db->query("SELECT COALESCE(SUM(fare),0) r FROM bookings WHERE payment_status='paid' AND DATE(created_at)='$d'")->fetch_assoc()['r'];
    $cnt = $db->query("SELECT COUNT(*) c FROM bookings WHERE DATE(created_at)='$d'")->fetch_assoc()['c'];
    $chartData[] = ['date' => date('D', strtotime($d)), 'revenue' => (float)$rev, 'bookings' => (int)$cnt];
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = clean($_POST['action']);

    if ($action === 'add_route') {
        $code = clean($_POST['route_code']);
        $origin = clean($_POST['origin']);
        $dest = clean($_POST['destination']);
        $dist = (float)$_POST['distance'];
        $fare = (float)$_POST['fare'];
        $dur = (int)$_POST['duration'];
        $db->query("INSERT INTO routes (route_code, origin, destination, distance_km, base_fare, duration_minutes) VALUES ('$code','$origin','$dest',$dist,$fare,$dur)");
        echo json_encode(['success' => true, 'message' => 'Route added successfully']);

    } elseif ($action === 'add_bus') {
        $num = clean($_POST['bus_number']); $plate = clean($_POST['plate']);
        $cap = (int)$_POST['capacity']; $type = clean($_POST['bus_type']);
        $model = clean($_POST['model']); $year = (int)$_POST['year'];
        $driver = (int)($_POST['driver_id'] ?? 0);
        $db->query("INSERT INTO buses (bus_number, registration_plate, capacity, bus_type, model, year, driver_id) VALUES ('$num','$plate',$cap,'$type','$model',$year," . ($driver?:NULL) . ")");
        echo json_encode(['success' => true, 'message' => 'Bus added successfully']);

    } elseif ($action === 'add_trip') {
        $route = (int)$_POST['route_id']; $bus = (int)$_POST['bus_id'];
        $driver = (int)$_POST['driver_id']; $officer = (int)$_POST['officer_id'];
        $dep = clean($_POST['departure']); $arr = clean($_POST['arrival']);
        $fare = (float)$_POST['fare'];
        $busData = $db->query("SELECT capacity FROM buses WHERE id=$bus")->fetch_assoc();
        $seats = $busData ? $busData['capacity'] : 14;
        $code = 'TRP-' . date('Ymd') . '-' . rand(100,999);
        $db->query("INSERT INTO trips (trip_code, route_id, bus_id, driver_id, officer_id, departure_datetime, arrival_datetime, fare, available_seats, total_seats) 
          VALUES ('$code',$route,$bus," . ($driver?:NULL) . "," . ($officer?:NULL) . ",'$dep','$arr',$fare,$seats,$seats)");
        echo json_encode(['success' => true, 'message' => 'Trip scheduled successfully']);

    } elseif ($action === 'update_trip_status') {
        $id = (int)$_POST['trip_id']; $status = clean($_POST['status']);
        $db->query("UPDATE trips SET status='$status' WHERE id=$id");
        echo json_encode(['success' => true]);

    } elseif ($action === 'toggle_user') {
        $id = (int)$_POST['user_id']; $status = clean($_POST['status']);
        $db->query("UPDATE users SET status='$status' WHERE id=$id");
        echo json_encode(['success' => true]);

    } elseif ($action === 'add_announcement') {
        $title = clean($_POST['title']); $content = clean($_POST['content']);
        $type = clean($_POST['type']); $admin = (int)$_SESSION['user_id'];
        $db->query("INSERT INTO announcements (title, content, type, created_by) VALUES ('$title','$content','$type',$admin)");
        echo json_encode(['success' => true, 'message' => 'Announcement published']);

    } elseif ($action === 'respond_feedback') {
        $id = (int)$_POST['feedback_id']; $resp = clean($_POST['response']);
        $db->query("UPDATE feedback SET response='$resp', status='resolved' WHERE id=$id");
        echo json_encode(['success' => true]);
    }
    exit;
}

include '../includes/header.php';
?>
<style>
.admin-layout { display:grid; grid-template-columns:260px 1fr; min-height:calc(100vh - 64px); }
.admin-sidebar { background:var(--gray-900); padding:16px 10px; position:sticky; top:64px; height:calc(100vh - 64px); overflow-y:auto; }
.admin-sidebar .sidebar-label { color:rgba(255,255,255,.3); }
.admin-sidebar .sidebar-item { color:rgba(255,255,255,.6); }
.admin-sidebar .sidebar-item:hover { background:rgba(255,255,255,.08); color:white; }
.admin-sidebar .sidebar-item.active { background:rgba(232,82,10,.2); color:var(--orange); }
.admin-main { padding:28px; background:var(--gray-50); }
.chart-bar { background:linear-gradient(to top,var(--orange),var(--orange-light)); border-radius:4px 4px 0 0; min-width:36px; transition:all .3s; cursor:pointer; }
.chart-bar:hover { opacity:.85; }
.quick-action { display:flex; flex-direction:column; align-items:center; gap:8px; padding:16px; border-radius:14px; border:2px solid var(--gray-200); background:white; cursor:pointer; transition:all .2s; font-size:12px; font-weight:600; color:var(--gray-600); text-align:center; }
.quick-action:hover { border-color:var(--orange); color:var(--orange); transform:translateY(-2px); box-shadow:var(--shadow); }
.quick-action i { font-size:24px; }
</style>

<div class="admin-layout">
  <!-- SIDEBAR -->
  <aside class="admin-sidebar">
    <div style="padding:8px 12px 16px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:12px">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:40px;height:40px;border-radius:10px;background:var(--orange);display:flex;align-items:center;justify-content:center;font-size:16px;color:white"><i class="fas fa-shield-alt"></i></div>
        <div style="color:white;font-weight:800;font-size:14px">Admin Panel<br><span style="font-size:10px;color:rgba(255,255,255,.4);font-weight:400">KTSTA System</span></div>
      </div>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Overview</div>
      <a class="sidebar-item active" href="#" onclick="showTab('overview')"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Operations</div>
      <a class="sidebar-item" href="#" onclick="showTab('trips')"><i class="fas fa-route"></i> Trips</a>
      <a class="sidebar-item" href="#" onclick="showTab('bookings')"><i class="fas fa-ticket-alt"></i> Bookings</a>
      <a class="sidebar-item" href="#" onclick="showTab('routes')"><i class="fas fa-map-signs"></i> Routes</a>
      <a class="sidebar-item" href="#" onclick="showTab('buses')"><i class="fas fa-bus"></i> Buses</a>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">People</div>
      <a class="sidebar-item" href="#" onclick="showTab('users')"><i class="fas fa-users"></i> Users</a>
      <a class="sidebar-item" href="#" onclick="showTab('feedback')"><i class="fas fa-comments"></i> Feedback</a>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Content</div>
      <a class="sidebar-item" href="#" onclick="showTab('announcements')"><i class="fas fa-bullhorn"></i> Announcements</a>
      <a class="sidebar-item" href="#" onclick="showTab('reports')"><i class="fas fa-chart-bar"></i> Reports</a>
      <a class="sidebar-item" href="#" onclick="showTab('settings')"><i class="fas fa-cog"></i> Settings</a>
    </div>
    <div class="sidebar-section" style="margin-top:20px">
      <a class="sidebar-item" style="color:rgba(239,68,68,.8)" href="<?= BASE_URL ?>/pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>

  <main class="admin-main">

    <!-- OVERVIEW TAB -->
    <div id="tab-overview" class="tab-section">
      <div class="page-header">
        <h1>Control Dashboard</h1>
        <p>KTSTA System Overview — <?= date('l, d F Y') ?></p>
      </div>

      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
        <?php $cards=[
          ['Today Revenue','₦'.number_format($stats['revenue']),'fas fa-naira-sign','orange'],
          ['Today Bookings',$stats['bookings'],'fas fa-ticket-alt','blue'],
          ['Active Buses',$stats['buses'],'fas fa-bus','green'],
          ['Pending Payments',$stats['pending'],'fas fa-clock','red'],
        ]; foreach($cards as [$lbl,$val,$icon,$cls]): ?>
        <div class="stat-card <?= $cls ?>">
          <div class="stat-value"><?= $val ?></div>
          <div class="stat-label"><?= $lbl ?></div>
          <div class="stat-icon"><i class="<?= $icon ?>"></i></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
        <!-- Revenue Chart -->
        <div class="card">
          <div class="card-title">Revenue (Last 7 Days)</div>
          <div style="display:flex;align-items:flex-end;gap:8px;height:160px;padding:0 0 8px">
            <?php $maxRev = max(array_column($chartData,'revenue')) ?: 1; ?>
            <?php foreach($chartData as $c): ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end">
              <div style="font-size:9px;color:var(--gray-400)"><?= $c['revenue'] > 0 ? '₦'.number_format($c['revenue']/1000,1).'k' : '' ?></div>
              <div class="chart-bar" style="height:<?= max(8,$c['revenue']/$maxRev*120) ?>px" title="₦<?= number_format($c['revenue']) ?>"></div>
              <div style="font-size:10px;color:var(--gray-500);font-weight:600"><?= $c['date'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:12px;color:var(--gray-400)">
            <span>Total this week: <strong style="color:var(--orange)">₦<?= number_format(array_sum(array_column($chartData,'revenue'))) ?></strong></span>
            <span><?= array_sum(array_column($chartData,'bookings')) ?> bookings</span>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
          <div class="card-title">Quick Actions</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="quick-action" onclick="showTab('trips');openModal('addTripModal')"><i class="fas fa-plus-circle" style="color:var(--orange)"></i>Add Trip</div>
            <div class="quick-action" onclick="showTab('buses');openModal('addBusModal')"><i class="fas fa-bus" style="color:var(--blue)"></i>Add Bus</div>
            <div class="quick-action" onclick="showTab('routes');openModal('addRouteModal')"><i class="fas fa-route" style="color:var(--success)"></i>Add Route</div>
            <div class="quick-action" onclick="showTab('announcements');openModal('addAnnoModal')"><i class="fas fa-bullhorn" style="color:var(--warning)"></i>Announce</div>
          </div>
          <div style="margin-top:12px">
            <div style="background:var(--gray-50);border-radius:10px;padding:12px">
              <div style="font-size:11px;color:var(--gray-400);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">System Health</div>
              <?php foreach([['Active Routes',$stats['routes']],['Active Buses',$stats['buses']],['Active Trips',$stats['trips']]] as [$lbl,$val]): ?>
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px">
                <span style="color:var(--gray-600)"><?= $lbl ?></span>
                <strong style="color:var(--orange)"><?= $val ?></strong>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Bookings -->
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <div class="card-title" style="margin:0">Recent Bookings</div>
          <button class="btn btn-ghost btn-sm" onclick="showTab('bookings')">View All</button>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Ref</th><th>Passenger</th><th>Route</th><th>Departure</th><th>Seat</th><th>Fare</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach(array_slice($recentBookings,0,6) as $b): ?>
            <?php $sm=['confirmed'=>'success','cancelled'=>'danger','used'=>'info','no_show'=>'warning']; ?>
            <tr>
              <td><span style="font-family:var(--mono);font-size:12px"><?= $b['booking_ref'] ?></span></td>
              <td><?= htmlspecialchars($b['full_name']) ?></td>
              <td><?= htmlspecialchars($b['origin']) ?> → <?= htmlspecialchars($b['destination']) ?></td>
              <td style="font-size:12px"><?= date('d M H:i', strtotime($b['departure_datetime'])) ?></td>
              <td><?= $b['seat_number'] ?></td>
              <td><?= formatMoney($b['fare']) ?></td>
              <td><span class="badge badge-<?= $sm[$b['booking_status']] ?? 'gray' ?>"><?= $b['booking_status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TRIPS TAB -->
    <div id="tab-trips" class="tab-section" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div><h1 style="font-size:22px;font-weight:800">Manage Trips</h1><p style="color:var(--gray-500);font-size:13px">Schedule and manage all bus trips</p></div>
        <button class="btn btn-primary" onclick="openModal('addTripModal')"><i class="fas fa-plus"></i> Schedule Trip</button>
      </div>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Code</th><th>Route</th><th>Bus</th><th>Departure</th><th>Seats</th><th>Fare</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($allTrips as $t): ?>
            <?php $sm=['scheduled'=>'info','boarding'=>'warning','in_transit'=>'orange','completed'=>'success','cancelled'=>'danger']; ?>
            <tr>
              <td><span style="font-family:var(--mono);font-size:11px"><?= $t['trip_code'] ?></span></td>
              <td><strong><?= htmlspecialchars($t['origin']) ?></strong> → <?= htmlspecialchars($t['destination']) ?></td>
              <td><?= $t['bus_number'] ?></td>
              <td style="font-size:12px"><?= date('d M Y H:i', strtotime($t['departure_datetime'])) ?></td>
              <td><span style="color:<?= $t['available_seats']<=3?'var(--danger)':'var(--success)' ?>;font-weight:700"><?= $t['available_seats'] ?></span>/<?= $t['total_seats'] ?></td>
              <td><?= formatMoney($t['fare']) ?></td>
              <td><span class="badge badge-<?= $sm[$t['status']] ?? 'gray' ?>"><?= $t['status'] ?></span></td>
              <td>
                <select class="form-control" style="padding:4px 8px;font-size:11px;width:auto" onchange="updateTripStatus(<?= $t['id'] ?>,this.value)">
                  <?php foreach(['scheduled','boarding','in_transit','completed','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $t['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- BOOKINGS TAB -->
    <div id="tab-bookings" class="tab-section" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div><h1 style="font-size:22px;font-weight:800">All Bookings</h1><p style="color:var(--gray-500);font-size:13px">View and manage all passenger bookings</p></div>
      </div>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Ref</th><th>Passenger</th><th>Route</th><th>Departure</th><th>Seat</th><th>Fare</th><th>Payment</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($recentBookings as $b): ?>
            <?php $sm=['confirmed'=>'success','cancelled'=>'danger','used'=>'info','no_show'=>'warning']; $pm=['paid'=>'success','pending'=>'warning','refunded'=>'info','failed'=>'danger']; ?>
            <tr>
              <td><span style="font-family:var(--mono);font-size:11px"><?= $b['booking_ref'] ?></span></td>
              <td><?= htmlspecialchars($b['full_name']) ?></td>
              <td><?= htmlspecialchars($b['origin']) ?> → <?= htmlspecialchars($b['destination']) ?></td>
              <td style="font-size:12px"><?= date('d M H:i', strtotime($b['departure_datetime'])) ?></td>
              <td><?= $b['seat_number'] ?></td>
              <td><?= formatMoney($b['fare']) ?></td>
              <td><span class="badge badge-<?= $pm[$b['payment_status']] ?? 'gray' ?>"><?= $b['payment_status'] ?></span></td>
              <td><span class="badge badge-<?= $sm[$b['booking_status']] ?? 'gray' ?>"><?= $b['booking_status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ROUTES TAB -->
    <div id="tab-routes" class="tab-section" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div><h1 style="font-size:22px;font-weight:800">Routes</h1><p style="color:var(--gray-500);font-size:13px">Manage all active routes</p></div>
        <button class="btn btn-primary" onclick="openModal('addRouteModal')"><i class="fas fa-plus"></i> Add Route</button>
      </div>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Code</th><th>Origin</th><th>Destination</th><th>Distance</th><th>Fare</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($allRoutes as $r): ?>
            <tr>
              <td><span style="font-family:var(--mono);font-size:12px;color:var(--blue);font-weight:700"><?= $r['route_code'] ?></span></td>
              <td><strong><?= htmlspecialchars($r['origin']) ?></strong></td>
              <td><?= htmlspecialchars($r['destination']) ?></td>
              <td><?= $r['distance_km'] ?> km</td>
              <td><?= formatMoney($r['base_fare']) ?></td>
              <td><?= $r['duration_minutes'] ?> min</td>
              <td><span class="badge badge-<?= $r['is_active']?'success':'gray' ?>"><?= $r['is_active']?'Active':'Inactive' ?></span></td>
              <td><button class="btn btn-ghost btn-sm">Edit</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- BUSES TAB -->
    <div id="tab-buses" class="tab-section" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div><h1 style="font-size:22px;font-weight:800">Fleet Management</h1><p style="color:var(--gray-500);font-size:13px"><?= count($allBuses) ?> buses in fleet</p></div>
        <button class="btn btn-primary" onclick="openModal('addBusModal')"><i class="fas fa-plus"></i> Add Bus</button>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
        <?php foreach($allBuses as $b): ?>
        <?php $colors=['active'=>'#16A34A','maintenance'=>'#D97706','inactive'=>'#6B7280']; ?>
        <div class="card" style="padding:0;overflow:hidden">
          <div style="background:linear-gradient(135deg,var(--blue-dark),var(--blue));padding:16px 20px;color:white">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="font-size:22px"><i class="fas fa-bus"></i></div>
              <span class="badge" style="background:rgba(255,255,255,.2);color:white"><?= $b['status'] ?></span>
            </div>
            <div style="font-size:20px;font-weight:800;margin-top:8px"><?= $b['bus_number'] ?></div>
            <div style="font-size:12px;opacity:.8"><?= $b['registration_plate'] ?></div>
          </div>
          <div style="padding:16px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px">
              <div><span style="color:var(--gray-400)">Type</span><div style="font-weight:600"><?= ucfirst($b['bus_type']) ?></div></div>
              <div><span style="color:var(--gray-400)">Capacity</span><div style="font-weight:600"><?= $b['capacity'] ?> seats</div></div>
              <div><span style="color:var(--gray-400)">Model</span><div style="font-weight:600"><?= $b['model'] ?></div></div>
              <div><span style="color:var(--gray-400)">Year</span><div style="font-weight:600"><?= $b['year'] ?></div></div>
            </div>
            <?php if ($b['driver']): ?><div style="margin-top:8px;font-size:12px;color:var(--gray-500)"><i class="fas fa-user-tie" style="color:var(--orange)"></i> <?= htmlspecialchars($b['driver']) ?></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- USERS TAB -->
    <div id="tab-users" class="tab-section" style="display:none">
      <div style="margin-bottom:20px">
        <h1 style="font-size:22px;font-weight:800">User Management</h1>
        <p style="color:var(--gray-500);font-size:13px"><?= $stats['users'] ?> registered passengers</p>
      </div>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Wallet</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($allUsers as $u): ?>
            <tr>
              <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
              <td style="font-size:12px"><?= $u['email'] ?></td>
              <td style="font-size:12px"><?= $u['phone'] ?></td>
              <td><span class="badge badge-info"><?= $u['role'] ?></span></td>
              <td><?= formatMoney($u['wallet_balance']) ?></td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td><span class="badge badge-<?= $u['status']==='active'?'success':'danger' ?>"><?= $u['status'] ?></span></td>
              <td>
                <?php if ($u['status']==='active'): ?>
                <button class="btn btn-danger btn-sm" onclick="toggleUser(<?= $u['id'] ?>,'suspended')">Suspend</button>
                <?php else: ?>
                <button class="btn btn-success btn-sm" onclick="toggleUser(<?= $u['id'] ?>,'active')">Activate</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- FEEDBACK TAB -->
    <div id="tab-feedback" class="tab-section" style="display:none">
      <div style="margin-bottom:20px"><h1 style="font-size:22px;font-weight:800">Feedback & Complaints</h1></div>
      <?php foreach($feedback as $f): ?>
      <div class="card" style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
          <div>
            <div style="display:flex;gap:8px;align-items:center">
              <span class="badge badge-<?= $f['type']==='complaint'?'danger':($f['type']==='compliment'?'success':'info') ?>"><?= $f['type'] ?></span>
              <strong style="font-size:15px"><?= htmlspecialchars($f['subject'] ?: 'No Subject') ?></strong>
            </div>
            <div style="font-size:12px;color:var(--gray-400);margin-top:4px">From: <?= htmlspecialchars($f['full_name'] ?? 'Anonymous') ?> &bull; <?= date('d M Y H:i', strtotime($f['created_at'])) ?></div>
          </div>
          <span class="badge badge-<?= $f['status']==='resolved'?'success':'warning' ?>"><?= $f['status'] ?></span>
        </div>
        <p style="font-size:13px;color:var(--gray-600);margin-bottom:12px"><?= htmlspecialchars($f['message']) ?></p>
        <?php if ($f['response']): ?>
        <div style="background:#F0FDF4;border-radius:8px;padding:10px;font-size:13px;color:var(--success)"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($f['response']) ?></div>
        <?php else: ?>
        <div style="display:flex;gap:8px">
          <input type="text" id="resp_<?= $f['id'] ?>" class="form-control" placeholder="Type your response...">
          <button class="btn btn-primary btn-sm" onclick="respondFeedback(<?= $f['id'] ?>)">Respond</button>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ANNOUNCEMENTS TAB -->
    <div id="tab-announcements" class="tab-section" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h1 style="font-size:22px;font-weight:800">Announcements</h1>
        <button class="btn btn-primary" onclick="openModal('addAnnoModal')"><i class="fas fa-plus"></i> New Announcement</button>
      </div>
      <?php foreach($announcements as $a): ?>
      <?php $ac=['info'=>'blue','warning'=>'warning','success'=>'success','danger'=>'danger']; ?>
      <div class="card" style="margin-bottom:12px;border-left:4px solid var(--<?= $ac[$a['type']] ?? 'blue' ?>)">
        <div style="display:flex;justify-content:space-between">
          <strong><?= htmlspecialchars($a['title']) ?></strong>
          <span class="badge badge-<?= $a['is_active']?'success':'gray' ?>"><?= $a['is_active']?'Active':'Inactive' ?></span>
        </div>
        <p style="font-size:13px;color:var(--gray-500);margin-top:6px"><?= htmlspecialchars($a['content']) ?></p>
        <div style="font-size:11px;color:var(--gray-400);margin-top:6px">By <?= htmlspecialchars($a['full_name'] ?? 'System') ?> &bull; <?= date('d M Y', strtotime($a['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- REPORTS TAB -->
    <div id="tab-reports" class="tab-section" style="display:none">
      <div style="margin-bottom:20px"><h1 style="font-size:22px;font-weight:800">Reports & Analytics</h1></div>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px">
        <?php $reportStats = [
          ['Total Revenue','₦'.number_format($stats['total_rev']),'fas fa-chart-line','#16A34A'],
          ['Total Bookings',$db->query("SELECT COUNT(*) c FROM bookings")->fetch_assoc()['c'],'fas fa-ticket-alt','var(--orange)'],
          ['Total Users',$db->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'],'fas fa-users','var(--blue)'],
          ['Fleet Size',count($allBuses),'fas fa-bus','var(--warning)'],
        ]; foreach($reportStats as [$lbl,$val,$icon,$color]): ?>
        <div class="card" style="display:flex;align-items:center;gap:16px">
          <div style="width:56px;height:56px;border-radius:14px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;font-size:22px;color:<?= $color ?>"><i class="<?= $icon ?>"></i></div>
          <div><div style="font-size:28px;font-weight:800;font-family:var(--mono)"><?= $val ?></div><div style="font-size:13px;color:var(--gray-500)"><?= $lbl ?></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- SETTINGS TAB -->
    <div id="tab-settings" class="tab-section" style="display:none">
      <div style="margin-bottom:20px"><h1 style="font-size:22px;font-weight:800">System Settings</h1></div>
      <div class="card" style="max-width:600px">
        <div class="card-title">General Settings</div>
        <div class="form-group"><label class="form-label">Site Name</label><input class="form-control" value="<?= getSetting('site_name') ?>"></div>
        <div class="form-group"><label class="form-label">Contact Email</label><input class="form-control" value="<?= getSetting('contact_email') ?>"></div>
        <div class="form-group"><label class="form-label">Contact Phone</label><input class="form-control" value="<?= getSetting('contact_phone') ?>"></div>
        <div class="form-group"><label class="form-label">Booking Fee (₦)</label><input type="number" class="form-control" value="<?= getSetting('booking_fee') ?>"></div>
        <div class="form-group"><label class="form-label">Cancellation Window (hours)</label><input type="number" class="form-control" value="<?= getSetting('cancellation_hours') ?>"></div>
        <button class="btn btn-primary">Save Settings</button>
      </div>
    </div>

  </main>
</div>

<!-- MODALS -->
<!-- Add Route Modal -->
<div class="modal-overlay" id="addRouteModal">
  <div class="modal-box">
    <div class="modal-header"><h3><i class="fas fa-plus" style="color:var(--orange)"></i> Add New Route</h3><button class="modal-close" onclick="closeModal('addRouteModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Route Code</label><input id="r_code" class="form-control" placeholder="e.g. KTS-LAG"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Origin</label><input id="r_origin" class="form-control" placeholder="e.g. Katsina"></div>
        <div class="form-group"><label class="form-label">Destination</label><input id="r_dest" class="form-control" placeholder="e.g. Lagos"></div>
        <div class="form-group"><label class="form-label">Distance (km)</label><input id="r_dist" type="number" class="form-control"></div>
        <div class="form-group"><label class="form-label">Base Fare (₦)</label><input id="r_fare" type="number" class="form-control"></div>
        <div class="form-group"><label class="form-label">Duration (mins)</label><input id="r_dur" type="number" class="form-control"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('addRouteModal')">Cancel</button><button class="btn btn-primary" onclick="saveRoute()"><i class="fas fa-save"></i> Save Route</button></div>
  </div>
</div>

<!-- Add Bus Modal -->
<div class="modal-overlay" id="addBusModal">
  <div class="modal-box">
    <div class="modal-header"><h3><i class="fas fa-bus" style="color:var(--orange)"></i> Add New Bus</h3><button class="modal-close" onclick="closeModal('addBusModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Bus Number</label><input id="b_num" class="form-control" placeholder="KTSTA-007"></div>
        <div class="form-group"><label class="form-label">Plate Number</label><input id="b_plate" class="form-control" placeholder="KT 105/23"></div>
        <div class="form-group"><label class="form-label">Capacity</label><input id="b_cap" type="number" class="form-control" value="14"></div>
        <div class="form-group"><label class="form-label">Type</label><select id="b_type" class="form-control"><option value="minibus">Minibus</option><option value="coaster">Coaster</option><option value="luxury">Luxury</option></select></div>
        <div class="form-group"><label class="form-label">Model</label><input id="b_model" class="form-control" placeholder="Toyota HiAce"></div>
        <div class="form-group"><label class="form-label">Year</label><input id="b_year" type="number" class="form-control" value="<?= date('Y') ?>"></div>
        <div class="form-group" style="grid-column:span 2"><label class="form-label">Assign Driver</label>
          <select id="b_driver" class="form-control"><option value="">Unassigned</option>
          <?php foreach($allDrivers as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('addBusModal')">Cancel</button><button class="btn btn-primary" onclick="saveBus()"><i class="fas fa-save"></i> Add Bus</button></div>
  </div>
</div>

<!-- Add Trip Modal -->
<div class="modal-overlay" id="addTripModal">
  <div class="modal-box" style="max-width:640px">
    <div class="modal-header"><h3><i class="fas fa-route" style="color:var(--orange)"></i> Schedule New Trip</h3><button class="modal-close" onclick="closeModal('addTripModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label class="form-label">Route</label>
          <select id="t_route" class="form-control">
            <?php foreach($allRoutes as $r): ?><option value="<?= $r['id'] ?>"><?= $r['origin'] ?> → <?= $r['destination'] ?> (<?= formatMoney($r['base_fare']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Bus</label>
          <select id="t_bus" class="form-control">
            <?php foreach($allBuses as $b): if ($b['status']==='active'): ?><option value="<?= $b['id'] ?>"><?= $b['bus_number'] ?> (<?= $b['capacity'] ?> seats)</option><?php endif; endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Driver</label>
          <select id="t_driver" class="form-control"><option value="">Unassigned</option>
          <?php foreach($allDrivers as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Officer</label>
          <select id="t_officer" class="form-control"><option value="">Unassigned</option>
          <?php foreach($allOfficers as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Departure</label><input id="t_dep" type="datetime-local" class="form-control" min="<?= date('Y-m-d\TH:i') ?>"></div>
        <div class="form-group"><label class="form-label">Arrival (Est.)</label><input id="t_arr" type="datetime-local" class="form-control"></div>
        <div class="form-group" style="grid-column:span 2"><label class="form-label">Fare Override (₦) <small style="color:var(--gray-400)">Leave blank to use route fare</small></label><input id="t_fare" type="number" class="form-control" placeholder="Leave blank for route default"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('addTripModal')">Cancel</button><button class="btn btn-primary" onclick="saveTrip()"><i class="fas fa-calendar-plus"></i> Schedule Trip</button></div>
  </div>
</div>

<!-- Announcement Modal -->
<div class="modal-overlay" id="addAnnoModal">
  <div class="modal-box">
    <div class="modal-header"><h3><i class="fas fa-bullhorn" style="color:var(--orange)"></i> New Announcement</h3><button class="modal-close" onclick="closeModal('addAnnoModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Title</label><input id="a_title" class="form-control" placeholder="Announcement title"></div>
      <div class="form-group"><label class="form-label">Message</label><textarea id="a_content" class="form-control" rows="4" placeholder="Write your announcement..."></textarea></div>
      <div class="form-group"><label class="form-label">Type</label><select id="a_type" class="form-control"><option value="info">Info</option><option value="warning">Warning</option><option value="success">Success</option><option value="danger">Alert</option></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('addAnnoModal')">Cancel</button><button class="btn btn-primary" onclick="saveAnnouncement()"><i class="fas fa-paper-plane"></i> Publish</button></div>
  </div>
</div>

<script>
function showTab(name) {
  document.querySelectorAll('.tab-section').forEach(t=>t.style.display='none');
  document.querySelectorAll('.admin-sidebar .sidebar-item').forEach(i=>i.classList.remove('active'));
  document.getElementById('tab-'+name).style.display='block';
  event && event.target && event.target.classList.add('active');
}

async function postAction(data) {
  const res = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(data)});
  return res.json();
}

async function saveRoute() {
  const d = await postAction({action:'add_route',route_code:document.getElementById('r_code').value,origin:document.getElementById('r_origin').value,destination:document.getElementById('r_dest').value,distance:document.getElementById('r_dist').value,fare:document.getElementById('r_fare').value,duration:document.getElementById('r_dur').value});
  if (d.success) { showToast(d.message,'success'); closeModal('addRouteModal'); setTimeout(()=>location.reload(),1500); }
  else showToast(d.error||'Error','error');
}

async function saveBus() {
  const d = await postAction({action:'add_bus',bus_number:document.getElementById('b_num').value,plate:document.getElementById('b_plate').value,capacity:document.getElementById('b_cap').value,bus_type:document.getElementById('b_type').value,model:document.getElementById('b_model').value,year:document.getElementById('b_year').value,driver_id:document.getElementById('b_driver').value});
  if (d.success) { showToast(d.message,'success'); closeModal('addBusModal'); setTimeout(()=>location.reload(),1500); }
  else showToast(d.error||'Error','error');
}

async function saveTrip() {
  const fareEl = document.getElementById('t_fare');
  let fare = fareEl.value;
  if (!fare) {
    const routeText = document.getElementById('t_route').selectedOptions[0].text;
    const match = routeText.match(/₦([\d,]+)/);
    fare = match ? match[1].replace(/,/g,'') : '1000';
  }
  const d = await postAction({action:'add_trip',route_id:document.getElementById('t_route').value,bus_id:document.getElementById('t_bus').value,driver_id:document.getElementById('t_driver').value,officer_id:document.getElementById('t_officer').value,departure:document.getElementById('t_dep').value,arrival:document.getElementById('t_arr').value,fare});
  if (d.success) { showToast(d.message,'success'); closeModal('addTripModal'); setTimeout(()=>location.reload(),1500); }
  else showToast(d.error||'Error','error');
}

async function updateTripStatus(id, status) {
  const d = await postAction({action:'update_trip_status',trip_id:id,status});
  if (d.success) showToast('Trip status updated','success');
  else showToast('Failed to update','error');
}

async function toggleUser(id, status) {
  const d = await postAction({action:'toggle_user',user_id:id,status});
  if (d.success) { showToast('User status updated','success'); setTimeout(()=>location.reload(),1200); }
}

async function saveAnnouncement() {
  const d = await postAction({action:'add_announcement',title:document.getElementById('a_title').value,content:document.getElementById('a_content').value,type:document.getElementById('a_type').value});
  if (d.success) { showToast(d.message,'success'); closeModal('addAnnoModal'); setTimeout(()=>location.reload(),1500); }
}

async function respondFeedback(id) {
  const resp = document.getElementById('resp_'+id).value;
  if (!resp.trim()) return;
  const d = await postAction({action:'respond_feedback',feedback_id:id,response:resp});
  if (d.success) { showToast('Response sent','success'); setTimeout(()=>location.reload(),1200); }
}
</script>

<?php include '../includes/footer.php'; ?>
