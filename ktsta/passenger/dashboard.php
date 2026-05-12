<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('passenger');
$pageTitle = 'My Dashboard';
$user = currentUser();

$db = getDB();
$uid = (int)$user['id'];

// Stats
$totalBookings = $db->query("SELECT COUNT(*) as c FROM bookings WHERE passenger_id=$uid")->fetch_assoc()['c'];
$upcomingTrips = $db->query("SELECT COUNT(*) as c FROM bookings b JOIN trips t ON b.trip_id=t.id WHERE b.passenger_id=$uid AND b.booking_status='confirmed' AND t.departure_datetime > NOW()")->fetch_assoc()['c'];
$totalSpent = $db->query("SELECT COALESCE(SUM(fare),0) as s FROM bookings WHERE passenger_id=$uid AND payment_status='paid'")->fetch_assoc()['s'];

// Recent bookings
$recentBookings = $db->query("SELECT b.*, r.origin, r.destination, t.departure_datetime, b2.bus_number
  FROM bookings b 
  JOIN trips t ON b.trip_id=t.id 
  JOIN routes r ON t.route_id=r.id 
  JOIN buses b2 ON t.bus_id=b2.id
  WHERE b.passenger_id=$uid 
  ORDER BY b.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Upcoming
$upcomingBookings = $db->query("SELECT b.*, r.origin, r.destination, t.departure_datetime, b2.bus_number
  FROM bookings b 
  JOIN trips t ON b.trip_id=t.id 
  JOIN routes r ON t.route_id=r.id 
  JOIN buses b2 ON t.bus_id=b2.id
  WHERE b.passenger_id=$uid AND b.booking_status='confirmed' AND t.departure_datetime > NOW()
  ORDER BY t.departure_datetime ASC LIMIT 3")->fetch_all(MYSQLI_ASSOC);

// Notifications
$notifications = $db->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$db->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");

// Wallet transactions
$walletTx = $db->query("SELECT * FROM wallet_transactions WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div class="app-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div style="padding:16px 12px;border-bottom:1px solid var(--gray-200);margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,var(--orange),var(--orange-dark));display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:white"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <div>
          <div style="font-weight:700;font-size:14px"><?= htmlspecialchars(explode(' ',$user['full_name'])[0]) ?></div>
          <div style="font-size:11px;color:var(--gray-400)">Passenger</div>
        </div>
      </div>
      <div style="margin-top:12px;background:var(--gray-50);border-radius:10px;padding:10px;text-align:center">
        <div style="font-size:10px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.5px">Wallet Balance</div>
        <div style="font-size:20px;font-weight:800;color:var(--orange);font-family:var(--mono)"><?= formatMoney($user['wallet_balance']) ?></div>
        <a href="wallet.php" class="btn btn-primary btn-sm" style="margin-top:6px;width:100%;justify-content:center">+ Top Up</a>
      </div>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Navigation</div>
      <a class="sidebar-item" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a class="sidebar-item" href="tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a>
      <a class="sidebar-item" href="wallet.php"><i class="fas fa-wallet"></i> Wallet</a>
      <a class="sidebar-item" href="loyalty.php"><i class="fas fa-star"></i> Rewards</a>
      <a class="sidebar-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
      <a class="sidebar-item" href="feedback.php"><i class="fas fa-comment-alt"></i> Feedback</a>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Quick Actions</div>
      <a class="sidebar-item" href="<?= BASE_URL ?>/pages/search.php"><i class="fas fa-search"></i> Book a Trip</a>
      <a class="sidebar-item" href="<?= BASE_URL ?>/pages/routes.php"><i class="fas fa-route"></i> View Routes</a>
    </div>
    <div class="sidebar-section" style="margin-top:auto">
      <a class="sidebar-item" style="color:var(--danger)" href="<?= BASE_URL ?>/pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="page-header">
      <h1>Welcome back, <?= htmlspecialchars(explode(' ',$user['full_name'])[0]) ?>! 👋</h1>
      <p>Here's an overview of your travel activity</p>
      <div class="header-actions">
        <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-primary"><i class="fas fa-plus"></i> Book New Trip</a>
      </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card orange">
        <div class="stat-value"><?= $totalBookings ?></div>
        <div class="stat-label">Total Bookings</div>
        <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
      </div>
      <div class="stat-card blue">
        <div class="stat-value"><?= $upcomingTrips ?></div>
        <div class="stat-label">Upcoming Trips</div>
        <div class="stat-icon"><i class="fas fa-bus"></i></div>
      </div>
      <div class="stat-card green">
        <div class="stat-value"><?= formatMoney($totalSpent) ?></div>
        <div class="stat-label">Total Spent</div>
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
      </div>
      <div class="stat-card orange">
        <div class="stat-value"><?= formatMoney($user['wallet_balance']) ?></div>
        <div class="stat-label">Wallet Balance</div>
        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <!-- UPCOMING TRIPS -->
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <div class="card-title" style="margin:0">✈️ Upcoming Trips</div>
          <a href="tickets.php" style="font-size:12px;color:var(--orange);font-weight:600">View All</a>
        </div>
        <?php if(empty($upcomingBookings)): ?>
        <div style="text-align:center;padding:30px;color:var(--gray-400)">
          <i class="fas fa-bus" style="font-size:28px;display:block;margin-bottom:8px"></i>
          No upcoming trips
          <div style="margin-top:10px"><a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-primary btn-sm">Book Now</a></div>
        </div>
        <?php else: ?>
        <?php foreach($upcomingBookings as $b): ?>
        <div style="border:1px solid var(--gray-200);border-radius:12px;padding:14px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center">
          <div>
            <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($b['origin']) ?> → <?= htmlspecialchars($b['destination']) ?></div>
            <div style="font-size:12px;color:var(--gray-400);margin-top:2px">
              <?= formatDateTime($b['departure_datetime']) ?> &bull; Seat <?= $b['seat_number'] ?>
            </div>
            <div style="font-size:11px;color:var(--gray-500);margin-top:2px;font-family:var(--mono)"><?= $b['booking_ref'] ?></div>
          </div>
          <div style="text-align:right">
            <span class="badge badge-success">Confirmed</span>
            <div style="font-size:11px;color:var(--gray-400);margin-top:4px"><?= formatMoney($b['fare']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- NOTIFICATIONS -->
      <div class="card">
        <div class="card-title">🔔 Recent Notifications</div>
        <?php if(empty($notifications)): ?>
        <div style="text-align:center;padding:30px;color:var(--gray-400)"><i class="fas fa-bell-slash" style="font-size:28px;display:block;margin-bottom:8px"></i>No notifications</div>
        <?php else: ?>
        <?php foreach($notifications as $n): ?>
        <div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--gray-100)">
          <?php $icons=['booking'=>'fa-ticket-alt','payment'=>'fa-money-bill','trip'=>'fa-bus','system'=>'fa-bell','alert'=>'fa-exclamation-triangle']; ?>
          <div style="width:36px;height:36px;border-radius:10px;background:<?= $n['is_read'] ? 'var(--gray-100)' : '#FFF0E8' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--orange)">
            <i class="fas <?= $icons[$n['type']] ?? 'fa-bell' ?>"></i>
          </div>
          <div>
            <div style="font-size:13px;font-weight:<?= $n['is_read'] ? '500' : '700' ?>"><?= htmlspecialchars($n['title']) ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($n['message']) ?></div>
            <div style="font-size:10px;color:var(--gray-300);margin-top:2px"><?= date('d M H:i', strtotime($n['created_at'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- RECENT BOOKINGS TABLE -->
    <div class="card" style="margin-top:24px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div class="card-title" style="margin:0">🎫 Recent Bookings</div>
        <a href="tickets.php" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <?php if(empty($recentBookings)): ?>
      <div style="text-align:center;padding:40px;color:var(--gray-400)">No bookings yet. <a href="<?= BASE_URL ?>/pages/search.php" style="color:var(--orange)">Book your first trip!</a></div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Ref</th><th>Route</th><th>Departure</th><th>Seat</th><th>Fare</th><th>Status</th><th>QR</th></tr></thead>
          <tbody>
          <?php foreach($recentBookings as $b): ?>
          <?php $statusMap=['confirmed'=>'badge-success','cancelled'=>'badge-danger','used'=>'badge-info','no_show'=>'badge-warning']; ?>
          <tr>
            <td><span style="font-family:var(--mono);font-size:12px;font-weight:700"><?= $b['booking_ref'] ?></span></td>
            <td><strong><?= htmlspecialchars($b['origin']) ?></strong> → <?= htmlspecialchars($b['destination']) ?></td>
            <td style="font-size:12px"><?= date('d M Y H:i', strtotime($b['departure_datetime'])) ?></td>
            <td><?= $b['seat_number'] ?></td>
            <td><?= formatMoney($b['fare']) ?></td>
            <td><span class="badge <?= $statusMap[$b['booking_status']] ?? 'badge-gray' ?>"><?= ucfirst($b['booking_status']) ?></span></td>
            <td><?php if ($b['qr_code']): ?><a href="#" onclick="showQR('<?= htmlspecialchars($b['booking_ref']) ?>','<?= $b['qr_code'] ?>')" style="color:var(--orange)"><i class="fas fa-qrcode"></i></a><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- WALLET ACTIVITY -->
    <div class="card" style="margin-top:24px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div class="card-title" style="margin:0">💳 Wallet Activity</div>
        <a href="wallet.php" class="btn btn-ghost btn-sm">Full History</a>
      </div>
      <?php if(empty($walletTx)): ?>
      <div style="text-align:center;padding:24px;color:var(--gray-400)">No wallet transactions yet</div>
      <?php else: ?>
      <?php foreach($walletTx as $w): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--gray-100)">
        <div style="display:flex;gap:10px;align-items:center">
          <div style="width:36px;height:36px;border-radius:10px;background:<?= $w['type']==='credit'?'#F0FDF4':'#FEF2F2' ?>;display:flex;align-items:center;justify-content:center;color:<?= $w['type']==='credit'?'var(--success)':'var(--danger)' ?>">
            <i class="fas fa-<?= $w['type']==='credit'?'arrow-down':'arrow-up' ?>"></i>
          </div>
          <div>
            <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($w['description'] ?? '-') ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= date('d M Y H:i', strtotime($w['created_at'])) ?></div>
          </div>
        </div>
        <div style="font-weight:800;font-family:var(--mono);color:<?= $w['type']==='credit'?'var(--success)':'var(--danger)' ?>">
          <?= $w['type']==='credit'?'+':'-' ?><?= formatMoney($w['amount']) ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- QR Modal -->
<div class="modal-overlay" id="qrModal">
  <div class="modal-box" style="max-width:340px">
    <div class="modal-header"><h3 id="qrTitle">Ticket QR Code</h3><button class="modal-close" onclick="closeModal('qrModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" style="text-align:center">
      <img id="qrImage" src="" style="width:200px;height:200px;border-radius:12px" alt="QR Code">
      <div id="qrRef" style="font-family:var(--mono);font-size:14px;font-weight:700;margin-top:12px;color:var(--gray-700)"></div>
      <div style="font-size:12px;color:var(--gray-400);margin-top:4px">Show this QR code to the ticket officer</div>
    </div>
  </div>
</div>

<script>
function showQR(ref, url) {
  document.getElementById('qrTitle').textContent = 'QR Code — ' + ref;
  document.getElementById('qrImage').src = url;
  document.getElementById('qrRef').textContent = ref;
  openModal('qrModal');
}
</script>

<?php include '../includes/footer.php'; ?>
