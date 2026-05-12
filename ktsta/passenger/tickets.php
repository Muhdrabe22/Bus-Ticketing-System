<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('passenger');
$pageTitle = 'My Tickets';
$user = currentUser();
$db = getDB();
$uid = (int)$user['id'];

$filter = clean($_GET['filter'] ?? 'all');

$where = "WHERE b.passenger_id=$uid";
if ($filter === 'upcoming') $where .= " AND b.booking_status='confirmed' AND t.departure_datetime > NOW()";
elseif ($filter === 'past') $where .= " AND (b.booking_status='used' OR t.departure_datetime < NOW())";
elseif ($filter === 'cancelled') $where .= " AND b.booking_status='cancelled'";

$bookings = $db->query("SELECT b.*, r.origin, r.destination, t.departure_datetime, t.arrival_datetime, 
  b2.bus_number, b2.bus_type, b2.model
  FROM bookings b JOIN trips t ON b.trip_id=t.id JOIN routes r ON t.route_id=r.id JOIN buses b2 ON t.bus_id=b2.id
  $where ORDER BY b.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Handle cancel
if ($_POST['action'] ?? '' === 'cancel') {
  $bid = (int)$_POST['booking_id'];
  $bk = $db->query("SELECT b.*, t.departure_datetime, t.fare FROM bookings b JOIN trips t ON b.trip_id=t.id WHERE b.id=$bid AND b.passenger_id=$uid")->fetch_assoc();
  if ($bk && $bk['booking_status'] === 'confirmed') {
    $hours = (strtotime($bk['departure_datetime']) - time()) / 3600;
    $cancelHours = (int)getSetting('cancellation_hours') ?: 2;
    if ($hours >= $cancelHours) {
      $db->query("UPDATE bookings SET booking_status='cancelled' WHERE id=$bid");
      $db->query("UPDATE trips SET available_seats=available_seats+1 WHERE id={$bk['trip_id']}");
      if ($bk['payment_method'] === 'wallet' && $bk['payment_status'] === 'paid') {
        $refund = $bk['fare'];
        $db->query("UPDATE users SET wallet_balance=wallet_balance+$refund WHERE id=$uid");
        addNotification($uid,'Booking Cancelled & Refunded',"Your booking {$bk['booking_ref']} has been cancelled. ₦".number_format($refund)." refunded to wallet.",'booking');
      }
      header('Location: tickets.php?msg=cancelled'); exit;
    }
  }
}

include '../includes/header.php';
?>
<div class="app-layout">
  <aside class="sidebar">
    <div style="padding:16px 12px;border-bottom:1px solid var(--gray-200);margin-bottom:16px">
      <div style="font-weight:700"><?= htmlspecialchars($user['full_name']) ?></div>
      <div style="font-size:12px;color:var(--gray-400)">Passenger</div>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Navigation</div>
      <a class="sidebar-item" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a class="sidebar-item active" href="tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a>
      <a class="sidebar-item" href="wallet.php"><i class="fas fa-wallet"></i> Wallet</a>
      <a class="sidebar-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
      <a class="sidebar-item" href="<?= BASE_URL ?>/pages/logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>
  <main class="main-content">
    <div class="page-header">
      <h1>My Tickets</h1>
      <p>View and manage all your bookings</p>
      <div class="header-actions"><a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-primary"><i class="fas fa-plus"></i> Book New Trip</a></div>
    </div>
    <?php if (isset($_GET['msg'])): ?>
    <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;color:var(--success)"><i class="fas fa-check-circle"></i> Booking cancelled successfully.</div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
      <?php foreach(['all'=>'All','upcoming'=>'Upcoming','past'=>'Completed','cancelled'=>'Cancelled'] as $k=>$lbl): ?>
      <a href="?filter=<?= $k ?>" class="btn <?= $filter===$k?'btn-primary':'btn-ghost' ?> btn-sm"><?= $lbl ?></a>
      <?php endforeach; ?>
      <span style="margin-left:auto;font-size:14px;color:var(--gray-400);align-self:center"><?= count($bookings) ?> tickets</span>
    </div>

    <?php if (empty($bookings)): ?>
    <div style="text-align:center;background:white;border-radius:20px;padding:60px;border:1px solid var(--gray-200)">
      <i class="fas fa-ticket-alt" style="font-size:48px;color:var(--gray-300);display:block;margin-bottom:16px"></i>
      <h3 style="color:var(--gray-500)">No tickets found</h3>
      <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-primary" style="margin-top:16px">Book a Trip</a>
    </div>
    <?php else: ?>
    <?php foreach($bookings as $b): ?>
    <?php $isPast = strtotime($b['departure_datetime']) < time() || $b['booking_status']==='used'; ?>
    <div style="background:white;border-radius:20px;border:2px solid <?= $b['booking_status']==='cancelled'?'var(--gray-200)':($isPast?'var(--gray-200)':'var(--orange)') ?>;margin-bottom:16px;overflow:hidden;<?= $b['booking_status']==='cancelled'||$isPast?'opacity:.7':'' ?>">
      <!-- Ticket Header -->
      <div style="background:<?= $b['booking_status']==='cancelled'?'var(--gray-700)':($isPast?'var(--gray-600)':'linear-gradient(135deg,var(--orange),var(--orange-dark))') ?>;color:white;padding:16px 24px;display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-size:20px;font-weight:800"><?= htmlspecialchars($b['origin']) ?> → <?= htmlspecialchars($b['destination']) ?></div>
          <div style="font-size:13px;opacity:.8"><?= date('D, d M Y \a\t H:i',strtotime($b['departure_datetime'])) ?></div>
        </div>
        <div style="text-align:right">
          <div style="font-size:10px;opacity:.7;letter-spacing:.5px">BOOKING REF</div>
          <div style="font-family:var(--mono);font-size:14px;font-weight:700"><?= $b['booking_ref'] ?></div>
        </div>
      </div>
      <!-- Ticket Body -->
      <div style="padding:20px 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:16px;border-bottom:2px dashed var(--gray-200)">
        <?php foreach([['Passenger',$b['passenger_name']],['Seat',$b['seat_number']],['Bus',$b['bus_number']],['Fare',formatMoney($b['fare'])]] as [$lbl,$val]): ?>
        <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--gray-400);font-weight:600"><?= $lbl ?></div><div style="font-size:15px;font-weight:700;margin-top:3px"><?= htmlspecialchars($val) ?></div></div>
        <?php endforeach; ?>
      </div>
      <!-- Ticket Footer -->
      <div style="padding:12px 24px;display:flex;align-items:center;justify-content:space-between;background:var(--gray-50)">
        <div style="display:flex;align-items:center;gap:12px">
          <?php $sm=['confirmed'=>'badge-success','cancelled'=>'badge-danger','used'=>'badge-info','no_show'=>'badge-warning']; ?>
          <span class="badge <?= $sm[$b['booking_status']]??'badge-gray' ?>"><?= $b['booking_status'] ?></span>
          <span class="badge badge-<?= $b['payment_status']==='paid'?'success':'warning' ?>"><?= $b['payment_status'] ?></span>
          <span style="font-size:12px;color:var(--gray-400)"><?= ucfirst($b['bus_type']) ?> &bull; <?= $b['model'] ?></span>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <?php if ($b['qr_code']): ?>
          <button class="btn btn-ghost btn-sm" onclick="showTicketQR('<?= $b['booking_ref'] ?>','<?= $b['qr_code'] ?>','<?= htmlspecialchars($b['origin']) ?>','<?= htmlspecialchars($b['destination']) ?>','<?= date('d M Y H:i',strtotime($b['departure_datetime'])) ?>','<?= $b['seat_number'] ?>')">
            <i class="fas fa-qrcode"></i> View QR
          </button>
          <?php endif; ?>
          <?php if ($b['booking_status']==='confirmed' && !$isPast): ?>
          <form method="POST" onsubmit="return confirm('Cancel this booking?')">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Cancel</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>

<!-- QR Modal -->
<div class="modal-overlay" id="qrModal">
  <div class="modal-box" style="max-width:360px">
    <div class="modal-header"><h3 id="qrTitle">Your Ticket</h3><button class="modal-close" onclick="closeModal('qrModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" style="text-align:center">
      <div id="qrTicket"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="window.print()"><i class="fas fa-print"></i> Print</button><button class="btn btn-primary" onclick="closeModal('qrModal')">Close</button></div>
  </div>
</div>

<script>
function showTicketQR(ref, qrUrl, origin, dest, dep, seat) {
  document.getElementById('qrTitle').textContent = 'Ticket: '+ref;
  document.getElementById('qrTicket').innerHTML = `
    <div style="background:linear-gradient(135deg,var(--orange),var(--orange-dark));color:white;border-radius:12px;padding:16px;margin-bottom:12px">
      <div style="font-size:18px;font-weight:800">${origin} → ${dest}</div>
      <div style="font-size:12px;opacity:.8;margin-top:4px">${dep} | Seat ${seat}</div>
    </div>
    <img src="${qrUrl}" style="width:180px;height:180px;border-radius:12px;border:3px solid var(--gray-200)">
    <div style="font-family:var(--mono);font-size:15px;font-weight:700;margin-top:10px">${ref}</div>
    <div style="font-size:12px;color:var(--gray-400);margin-top:4px">Show this QR to the ticket officer</div>`;
  openModal('qrModal');
}
</script>
<?php include '../includes/footer.php'; ?>
