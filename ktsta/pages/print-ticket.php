<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Print Ticket';

$ref = clean($_GET['ref'] ?? '');
if (!$ref) { header('Location: '.BASE_URL); exit; }

$db = getDB();
$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Allow admin/officer to print any ticket
$userClause = ($role === 'admin' || $role === 'officer') ? '' : "AND b.passenger_id=$uid";

$booking = $db->query("SELECT b.*, r.origin, r.destination, r.route_code, r.distance_km, r.duration_minutes,
  t.departure_datetime, t.arrival_datetime, t.trip_code, t.fare as trip_fare,
  bus.bus_number, bus.registration_plate, bus.bus_type, bus.model, bus.capacity,
  u.full_name as passenger_reg_name, u.phone as passenger_reg_phone,
  d.full_name as driver_name
  FROM bookings b
  JOIN trips t ON b.trip_id=t.id
  JOIN routes r ON t.route_id=r.id
  JOIN buses bus ON t.bus_id=bus.id
  LEFT JOIN users u ON b.passenger_id=u.id
  LEFT JOIN users d ON t.driver_id=d.id
  WHERE b.booking_ref='$ref' $userClause LIMIT 1")->fetch_assoc();

if (!$booking) { echo '<p style="text-align:center;padding:40px;color:red">Ticket not found or access denied.</p>'; exit; }

$qrData = "KTSTA|{$booking['booking_ref']}|{$booking['passenger_name']}|{$booking['origin']}|{$booking['destination']}|SEAT:{$booking['seat_number']}";
$qrUrl  = generateQR($qrData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Print Ticket — <?= $booking['booking_ref'] ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Sora',sans-serif; background:#F0F0F0; padding:20px; }
.controls { max-width:680px; margin:0 auto 20px; display:flex; gap:10px; justify-content:flex-end; }
.btn { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none; font-family:'Sora',sans-serif; text-decoration:none; }
.btn-primary { background:#E8520A; color:white; }
.btn-secondary { background:#1B4F9B; color:white; }
.btn-ghost { background:#E2E5E9; color:#4A5568; }

.ticket-page { max-width:680px; margin:0 auto; }
.ticket { background:white; border-radius:0; box-shadow:0 8px 40px rgba(0,0,0,.2); }

/* Ticket Header */
.t-header { background:linear-gradient(135deg,#E8520A,#C44208); color:white; padding:24px 28px; display:flex; justify-content:space-between; align-items:start; }
.t-logo { display:flex; align-items:center; gap:10px; }
.t-logo-icon { width:44px; height:44px; border-radius:10px; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; font-size:20px; }
.t-logo-text { font-weight:800; font-size:18px; line-height:1.2; }
.t-logo-sub { font-size:10px; opacity:.7; font-weight:400; }
.t-status { text-align:right; }
.t-status .status-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.3); padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }

/* Route Display */
.t-route { padding:28px; background:white; border-bottom:2px dashed #E2E5E9; }
.route-cities { display:flex; align-items:center; gap:0; margin-bottom:16px; }
.city-block { }
.city-name { font-size:30px; font-weight:800; color:#1A202C; }
.city-label { font-size:11px; color:#9CA5B3; text-transform:uppercase; letter-spacing:.5px; font-weight:600; margin-top:2px; }
.city-time { font-size:22px; font-weight:700; color:#E8520A; font-family:'JetBrains Mono',monospace; margin-top:4px; }
.route-arrow { flex:1; display:flex; flex-direction:column; align-items:center; margin:0 16px; }
.route-line-wrap { display:flex; align-items:center; gap:4px; width:100%; }
.route-dot { width:10px; height:10px; border-radius:50%; background:#E8520A; flex-shrink:0; }
.route-line { flex:1; height:3px; background:linear-gradient(90deg,#E8520A,#1B4F9B); }
.route-plane { font-size:20px; color:#E8520A; margin:6px 0; }
.route-meta { font-size:11px; color:#9CA5B3; margin-top:4px; }

/* Ticket Details Grid */
.t-details { display:grid; grid-template-columns:1fr 1fr 1fr; padding:0; }
.t-field { padding:16px 20px; border-right:1px solid #F0F1F3; border-bottom:1px solid #F0F1F3; }
.t-field:nth-child(3n) { border-right:none; }
.t-field-label { font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:#9CA5B3; font-weight:700; margin-bottom:4px; }
.t-field-value { font-size:15px; font-weight:700; color:#1A202C; }
.t-field-value.mono { font-family:'JetBrains Mono',monospace; }
.t-field-value.orange { color:#E8520A; }

/* Blue stripe */
.t-stripe { height:8px; background:linear-gradient(90deg,#E8520A 0%,#E8520A 45%,white 45%,white 55%,#1B4F9B 55%); }

/* QR Footer */
.t-footer { padding:24px 28px; display:flex; align-items:center; justify-content:space-between; gap:24px; border-top:2px dashed #E2E5E9; }
.t-qr img { width:130px; height:130px; border-radius:12px; border:3px solid #E2E5E9; }
.t-instructions { flex:1; }
.t-instructions h3 { font-size:15px; font-weight:800; margin-bottom:10px; color:#1A202C; }
.instruction-item { display:flex; align-items:start; gap:8px; margin-bottom:8px; font-size:13px; color:#6B7583; }
.instruction-num { width:22px; height:22px; border-radius:50%; background:#E8520A; color:white; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; flex-shrink:0; }
.t-ref-big { font-family:'JetBrains Mono',monospace; font-size:22px; font-weight:800; color:#1B4F9B; letter-spacing:2px; }

/* Bottom info */
.t-bottom { background:#F8F9FA; padding:16px 28px; display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#9CA5B3; }

/* PRINT STYLES */
@media print {
  body { background:white; padding:0; }
  .controls { display:none; }
  .ticket { box-shadow:none; border:1px solid #ddd; }
  .copy-label { display:block !important; }
}
</style>
</head>
<body>

<div class="controls">
  <a href="<?= BASE_URL ?>/passenger/tickets.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
  <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print Ticket</button>
  <button class="btn btn-primary" onclick="downloadTicket()"><i class="fas fa-download"></i> Save PDF</button>
</div>

<div class="ticket-page" id="ticketEl">
  <!-- Print twice on one page -->
  <?php for ($copy = 1; $copy <= 2; $copy++): ?>
  <div class="ticket" style="<?= $copy === 2 ? 'margin-top:20px;page-break-before:auto' : '' ?>">

    <!-- Header -->
    <div class="t-header">
      <div class="t-logo">
        <div class="t-logo-icon"><i class="fas fa-bus"></i></div>
        <div>
          <div class="t-logo-text">KTSTA</div>
          <div class="t-logo-sub">Katsina State Transport Authority</div>
        </div>
      </div>
      <div class="t-status">
        <div style="font-size:11px;opacity:.7;margin-bottom:4px"><?= $copy === 1 ? 'PASSENGER COPY' : 'OFFICER COPY' ?></div>
        <div class="status-badge">
          <?php if ($booking['booking_status'] === 'confirmed'): ?>
          <i class="fas fa-check-circle"></i> CONFIRMED
          <?php elseif ($booking['booking_status'] === 'used'): ?>
          <i class="fas fa-check-double"></i> USED
          <?php else: ?>
          <i class="fas fa-times-circle"></i> <?= strtoupper($booking['booking_status']) ?>
          <?php endif; ?>
        </div>
        <div style="font-family:'JetBrains Mono',monospace;font-size:13px;opacity:.85;margin-top:6px"><?= $booking['booking_ref'] ?></div>
      </div>
    </div>

    <!-- KTSTA Stripe -->
    <div class="t-stripe"></div>

    <!-- Route -->
    <div class="t-route">
      <div class="route-cities">
        <div class="city-block">
          <div class="city-label">From</div>
          <div class="city-name"><?= htmlspecialchars($booking['origin']) ?></div>
          <div class="city-time"><?= date('H:i', strtotime($booking['departure_datetime'])) ?></div>
        </div>
        <div class="route-arrow">
          <div class="route-line-wrap">
            <div class="route-dot"></div>
            <div class="route-line"></div>
            <div class="route-dot" style="background:#1B4F9B"></div>
          </div>
          <div class="route-meta"><?= $booking['distance_km'] ?> km &bull; ~<?= round($booking['duration_minutes']/60,1) ?>hrs</div>
        </div>
        <div class="city-block" style="text-align:right">
          <div class="city-label">To</div>
          <div class="city-name"><?= htmlspecialchars($booking['destination']) ?></div>
          <div class="city-time"><?= $booking['arrival_datetime'] ? date('H:i',strtotime($booking['arrival_datetime'])) : '—' ?></div>
        </div>
      </div>
      <div style="font-size:12px;color:#9CA5B3;text-align:center">
        <?= date('l, d F Y', strtotime($booking['departure_datetime'])) ?>
        &bull; Route: <?= $booking['route_code'] ?> &bull; Trip: <?= $booking['trip_code'] ?>
      </div>
    </div>

    <!-- Details Grid -->
    <div class="t-details">
      <div class="t-field"><div class="t-field-label">Passenger</div><div class="t-field-value"><?= htmlspecialchars($booking['passenger_name']) ?></div></div>
      <div class="t-field"><div class="t-field-label">Seat Number</div><div class="t-field-value orange" style="font-size:28px"><?= $booking['seat_number'] ?></div></div>
      <div class="t-field"><div class="t-field-label">Fare Paid</div><div class="t-field-value mono orange"><?= formatMoney($booking['fare']) ?></div></div>
      <div class="t-field"><div class="t-field-label">Bus Number</div><div class="t-field-value mono"><?= $booking['bus_number'] ?></div></div>
      <div class="t-field"><div class="t-field-label">Plate</div><div class="t-field-value mono"><?= $booking['registration_plate'] ?></div></div>
      <div class="t-field"><div class="t-field-label">Bus Type</div><div class="t-field-value"><?= ucfirst($booking['bus_type']) ?> — <?= $booking['model'] ?></div></div>
      <?php if ($booking['driver_name']): ?>
      <div class="t-field"><div class="t-field-label">Driver</div><div class="t-field-value"><?= htmlspecialchars($booking['driver_name']) ?></div></div>
      <?php endif; ?>
      <div class="t-field"><div class="t-field-label">Payment</div><div class="t-field-value"><?= ucfirst($booking['payment_method']) ?> — <span style="color:<?= $booking['payment_status']==='paid'?'#16A34A':'#D97706' ?>"><?= $booking['payment_status'] ?></span></div></div>
      <div class="t-field"><div class="t-field-label">Issued On</div><div class="t-field-value" style="font-size:12px"><?= date('d M Y H:i', strtotime($booking['created_at'])) ?></div></div>
    </div>

    <!-- QR Footer -->
    <div class="t-footer">
      <div>
        <div style="font-size:11px;color:#9CA5B3;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Booking Reference</div>
        <div class="t-ref-big"><?= $booking['booking_ref'] ?></div>
        <div style="font-size:11px;color:#9CA5B3;margin-top:8px">Present this ticket at boarding</div>
      </div>
      <div class="t-instructions">
        <h3>Boarding Instructions</h3>
        <?php foreach(['Arrive at the terminal 20 minutes before departure','Present this ticket or scan QR code at gate','Baggage limit: 20kg per passenger','No refund within 2 hours of departure'] as $i=>$inst): ?>
        <div class="instruction-item"><div class="instruction-num"><?= $i+1 ?></div><span><?= $inst ?></span></div>
        <?php endforeach; ?>
      </div>
      <div class="t-qr">
        <img src="<?= $qrUrl ?>" alt="QR Code" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=<?= urlencode($booking['booking_ref']) ?>'">
        <div style="text-align:center;font-size:10px;color:#9CA5B3;margin-top:4px">Scan to verify</div>
      </div>
    </div>

    <div class="t-bottom">
      <span>KTSTA — Katsina State Transport Authority</span>
      <span>Helpline: 0800-KTSTA-01 &bull; info@ktsta.gov.ng</span>
      <span>ktsta.gov.ng</span>
    </div>
  </div>
  <?php endfor; ?>
</div>

<script>
function downloadTicket() {
  window.print();
}
// Auto-print if query param
<?php if (isset($_GET['autoprint'])): ?>
window.addEventListener('load', () => setTimeout(() => window.print(), 800));
<?php endif; ?>
</script>
</body>
</html>
