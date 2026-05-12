<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('driver');
$pageTitle = 'Driver Panel';
$user = currentUser();
$db = getDB();
$uid = (int)$user['id'];

$myTrips = $db->query("SELECT t.*, r.origin, r.destination, b.bus_number, b.capacity, b.model
  FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id
  WHERE t.driver_id=$uid ORDER BY t.departure_datetime DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

$todayTrip = $db->query("SELECT t.*, r.origin, r.destination, b.bus_number, b.capacity, b.model, b.registration_plate
  FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id
  WHERE t.driver_id=$uid AND DATE(t.departure_datetime)=CURDATE() AND t.status IN ('scheduled','boarding','in_transit')
  ORDER BY t.departure_datetime ASC LIMIT 1")->fetch_assoc();

$stats = [
  'total_trips' => $db->query("SELECT COUNT(*) c FROM trips WHERE driver_id=$uid AND status='completed'")->fetch_assoc()['c'],
  'this_month'  => $db->query("SELECT COUNT(*) c FROM trips WHERE driver_id=$uid AND MONTH(departure_datetime)=MONTH(NOW())")->fetch_assoc()['c'],
];

include '../includes/header.php';
?>
<div class="app-layout">
  <aside class="sidebar">
    <div style="padding:16px 12px;border-bottom:1px solid var(--gray-200);margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,var(--blue),var(--blue-dark));display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:white"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <div><div style="font-weight:700;font-size:14px"><?= htmlspecialchars(explode(' ',$user['full_name'])[0]) ?></div><div style="font-size:11px;color:var(--gray-400)">Driver</div></div>
      </div>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Navigation</div>
      <a class="sidebar-item active" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a class="sidebar-item" href="#"><i class="fas fa-route"></i> My Trips</a>
      <a class="sidebar-item" href="#"><i class="fas fa-map-marker-alt"></i> Live Tracking</a>
      <a class="sidebar-item" href="#"><i class="fas fa-tools"></i> Report Issue</a>
      <a class="sidebar-item" href="<?= BASE_URL ?>/pages/logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>
  <main class="main-content">
    <div class="page-header">
      <h1>Driver Dashboard</h1>
      <p>Welcome, <?= htmlspecialchars($user['full_name']) ?></p>
    </div>
    <div class="stats-grid">
      <div class="stat-card blue"><div class="stat-value"><?= $stats['total_trips'] ?></div><div class="stat-label">Trips Completed</div><div class="stat-icon"><i class="fas fa-flag-checkered"></i></div></div>
      <div class="stat-card orange"><div class="stat-value"><?= $stats['this_month'] ?></div><div class="stat-label">Trips This Month</div><div class="stat-icon"><i class="fas fa-calendar"></i></div></div>
    </div>

    <?php if ($todayTrip): ?>
    <div class="card" style="margin-bottom:24px;border:2px solid var(--orange)">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:16px">
        <div><div style="font-size:12px;color:var(--orange);font-weight:700;text-transform:uppercase;letter-spacing:.5px">Today's Assignment</div><div style="font-size:22px;font-weight:800;margin-top:4px"><?= $todayTrip['origin'] ?> → <?= $todayTrip['destination'] ?></div></div>
        <span class="badge badge-warning"><?= $todayTrip['status'] ?></span>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;font-size:13px">
        <div><div style="color:var(--gray-400)">Departure</div><div style="font-weight:700"><?= date('H:i',strtotime($todayTrip['departure_datetime'])) ?></div></div>
        <div><div style="color:var(--gray-400)">Bus</div><div style="font-weight:700"><?= $todayTrip['bus_number'] ?></div></div>
        <div><div style="color:var(--gray-400)">Plate</div><div style="font-weight:700"><?= $todayTrip['registration_plate'] ?></div></div>
        <div><div style="color:var(--gray-400)">Capacity</div><div style="font-weight:700"><?= $todayTrip['capacity'] ?> seats</div></div>
        <div><div style="color:var(--gray-400)">Model</div><div style="font-weight:700"><?= $todayTrip['model'] ?></div></div>
      </div>
    </div>
    <?php else: ?>
    <div class="card" style="text-align:center;padding:40px;margin-bottom:24px">
      <i class="fas fa-calendar-check" style="font-size:40px;color:var(--gray-300);display:block;margin-bottom:12px"></i>
      <div style="color:var(--gray-500)">No trips assigned for today</div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-title">Trip History</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Code</th><th>Route</th><th>Date</th><th>Bus</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($myTrips as $t): ?>
          <tr>
            <td style="font-family:var(--mono);font-size:11px"><?= $t['trip_code'] ?></td>
            <td><?= $t['origin'] ?> → <?= $t['destination'] ?></td>
            <td style="font-size:12px"><?= date('d M Y H:i',strtotime($t['departure_datetime'])) ?></td>
            <td><?= $t['bus_number'] ?></td>
            <td><span class="badge badge-<?= $t['status']==='completed'?'success':($t['status']==='cancelled'?'danger':'info') ?>"><?= $t['status'] ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php include '../includes/footer.php'; ?>
