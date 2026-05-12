<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Search Trips';

$db = getDB();
$from = clean($_GET['from'] ?? '');
$to = clean($_GET['to'] ?? '');
$date = clean($_GET['date'] ?? date('Y-m-d'));
$type = clean($_GET['type'] ?? '');

// Get all origins/destinations
$origins = $db->query("SELECT DISTINCT origin FROM routes WHERE is_active=1 ORDER BY origin")->fetch_all(MYSQLI_ASSOC);
$destinations = $db->query("SELECT DISTINCT destination FROM routes WHERE is_active=1 ORDER BY destination")->fetch_all(MYSQLI_ASSOC);

// Search trips
$trips = [];
$where = ["t.departure_datetime >= NOW()", "t.status IN ('scheduled','boarding')"];
if ($from) $where[] = "r.origin='$from'";
if ($to) $where[] = "r.destination='$to'";
if ($date) $where[] = "DATE(t.departure_datetime)='$date'";
if ($type) $where[] = "b.bus_type='$type'";
$whereSQL = implode(' AND ', $where);

$res = $db->query("SELECT t.*, r.origin, r.destination, r.distance_km, r.duration_minutes, 
  b.bus_number, b.capacity, b.bus_type, b.model,
  u.full_name as driver_name
  FROM trips t 
  JOIN routes r ON t.route_id=r.id 
  JOIN buses b ON t.bus_id=b.id
  LEFT JOIN users u ON t.driver_id=u.id
  WHERE $whereSQL ORDER BY t.departure_datetime ASC");
if ($res) $trips = $res->fetch_all(MYSQLI_ASSOC);

// Get booked seats for a trip (for seat map AJAX)
if (isset($_GET['seats']) && is_numeric($_GET['seats'])) {
    $tid = (int)$_GET['seats'];
    $sres = $db->query("SELECT seat_number FROM bookings WHERE trip_id=$tid AND booking_status NOT IN ('cancelled')");
    $booked = [];
    while ($r = $sres->fetch_assoc()) $booked[] = $r['seat_number'];
    header('Content-Type: application/json');
    echo json_encode(['booked' => $booked]);
    exit;
}

include '../includes/header.php';
?>
<style>
.search-page { max-width:1200px; margin:0 auto; padding:32px 20px; }
.search-bar { background:white; border-radius:20px; padding:24px; box-shadow:var(--shadow); margin-bottom:28px; border:1px solid var(--gray-200); }
.search-grid { display:grid; grid-template-columns:1fr 1fr 1fr 140px 120px; gap:12px; align-items:end; }
.trip-card { background:white; border-radius:20px; border:2px solid var(--gray-200); padding:0; overflow:hidden; margin-bottom:16px; transition:all .3s; }
.trip-card:hover { border-color:var(--orange); box-shadow:0 8px 30px rgba(232,82,10,.12); }
.trip-card-header { padding:20px 24px; background:var(--gray-50); display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--gray-200); }
.trip-route { display:flex; align-items:center; gap:16px; }
.trip-city { font-size:22px; font-weight:800; color:var(--gray-900); }
.trip-arrow { display:flex; flex-direction:column; align-items:center; gap:4px; }
.trip-arrow-line { width:80px; height:2px; background:linear-gradient(90deg,var(--orange),var(--blue)); }
.trip-arrow-icon { font-size:12px; color:var(--orange); margin-top:-12px; }
.trip-times { display:flex; align-items:center; gap:20px; }
.trip-time-block { text-align:center; }
.trip-time { font-size:28px; font-weight:800; font-family:var(--mono); color:var(--gray-900); }
.trip-time-label { font-size:11px; color:var(--gray-400); font-weight:600; text-transform:uppercase; }
.trip-duration { text-align:center; }
.trip-duration-time { font-size:13px; font-weight:700; color:var(--gray-600); }
.trip-card-body { padding:16px 24px; display:flex; justify-content:space-between; align-items:center; }
.trip-details { display:flex; gap:20px; flex-wrap:wrap; }
.trip-detail { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--gray-500); }
.trip-booking { display:flex; align-items:center; gap:16px; }
.trip-price { font-size:28px; font-weight:800; color:var(--orange); font-family:var(--mono); }
.trip-seats { font-size:12px; color:var(--gray-400); text-align:center; }
.trip-seats strong { display:block; font-size:16px; color:var(--gray-700); }

/* Seat map in modal */
.bus-layout { max-width:300px; margin:0 auto; }
.bus-top { background:var(--blue); border-radius:12px 12px 0 0; padding:12px; display:flex; justify-content:space-between; align-items:center; color:white; font-size:13px; font-weight:700; }
.bus-body { background:white; border:2px solid var(--gray-200); border-radius:0 0 12px 12px; padding:16px; }
.seat-row { display:flex; gap:8px; margin-bottom:8px; justify-content:center; align-items:center; }
.seat-gap { width:44px; }
.seat-legend { display:flex; gap:16px; justify-content:center; margin:12px 0; flex-wrap:wrap; }
.legend-item { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--gray-500); }
.legend-box { width:18px; height:18px; border-radius:4px; border:2px solid; }

.filters-strip { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.filter-chip { display:flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; border:2px solid var(--gray-200); font-size:12px; font-weight:600; color:var(--gray-500); cursor:pointer; transition:all .2s; background:white; }
.filter-chip.active { border-color:var(--orange); background:#FFF0E8; color:var(--orange); }

@media (max-width:900px) {
  .search-grid { grid-template-columns:1fr 1fr; }
  .trip-card-header { flex-direction:column; gap:12px; }
}
</style>

<div class="search-page">
  <!-- SEARCH BAR -->
  <div class="search-bar">
    <div style="font-size:18px;font-weight:800;margin-bottom:16px"><i class="fas fa-search" style="color:var(--orange)"></i> Search Available Trips</div>
    <form method="GET">
      <div class="search-grid">
        <div class="form-group" style="margin:0">
          <label class="form-label"><i class="fas fa-map-marker-alt" style="color:var(--orange)"></i> From</label>
          <select name="from" class="form-control">
            <option value="">All Origins</option>
            <?php foreach($origins as $o): ?>
            <option value="<?= $o['origin'] ?>" <?= $from===$o['origin']?'selected':'' ?>><?= $o['origin'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label"><i class="fas fa-flag" style="color:var(--blue)"></i> To</label>
          <select name="to" class="form-control">
            <option value="">All Destinations</option>
            <?php foreach($destinations as $d): ?>
            <option value="<?= $d['destination'] ?>" <?= $to===$d['destination']?'selected':'' ?>><?= $d['destination'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label"><i class="fas fa-calendar"></i> Travel Date</label>
          <input type="date" name="date" class="form-control" value="<?= $date ?>" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Bus Type</label>
          <select name="type" class="form-control">
            <option value="">Any Type</option>
            <option value="minibus" <?= $type==='minibus'?'selected':'' ?>>Minibus</option>
            <option value="coaster" <?= $type==='coaster'?'selected':'' ?>>Coaster</option>
            <option value="luxury" <?= $type==='luxury'?'selected':'' ?>>Luxury</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height:44px;justify-content:center"><i class="fas fa-search"></i> Search</button>
      </div>
    </form>
  </div>

  <!-- RESULTS -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
      <span style="font-weight:700;font-size:16px"><?= count($trips) ?> trips found</span>
      <?php if ($from || $to): ?>
      <span style="color:var(--gray-400);font-size:14px"> for <?= htmlspecialchars($from ?: 'All') ?> → <?= htmlspecialchars($to ?: 'All') ?></span>
      <?php endif; ?>
    </div>
    <div style="font-size:13px;color:var(--gray-400)"><?= $date ? date('D, d M Y', strtotime($date)) : 'All dates' ?></div>
  </div>

  <?php if (empty($trips)): ?>
  <div style="text-align:center;padding:80px 20px;background:white;border-radius:20px;border:1px solid var(--gray-200)">
    <i class="fas fa-bus-alt" style="font-size:48px;color:var(--gray-300);display:block;margin-bottom:16px"></i>
    <h3 style="color:var(--gray-600)">No trips found</h3>
    <p style="color:var(--gray-400);margin-top:8px">Try different dates or routes</p>
    <a href="search.php" class="btn btn-outline" style="margin-top:20px">Clear Filters</a>
  </div>
  <?php else: ?>
  <?php foreach($trips as $t): ?>
  <div class="trip-card">
    <div class="trip-card-header">
      <div class="trip-times">
        <div class="trip-time-block">
          <div class="trip-time"><?= date('H:i', strtotime($t['departure_datetime'])) ?></div>
          <div class="trip-time-label"><?= htmlspecialchars($t['origin']) ?></div>
        </div>
        <div class="trip-duration">
          <div style="font-size:11px;color:var(--gray-400);text-align:center;margin-bottom:4px"><?= $t['duration_minutes'] ?> min</div>
          <div style="display:flex;align-items:center;gap:0">
            <div style="width:4px;height:4px;border-radius:50%;background:var(--orange)"></div>
            <div style="width:100px;height:2px;background:linear-gradient(90deg,var(--orange),var(--blue))"></div>
            <div style="width:4px;height:4px;border-radius:50%;background:var(--blue)"></div>
          </div>
          <div style="font-size:10px;color:var(--gray-400);text-align:center;margin-top:2px">Direct</div>
        </div>
        <div class="trip-time-block">
          <div class="trip-time"><?= $t['arrival_datetime'] ? date('H:i', strtotime($t['arrival_datetime'])) : '—' ?></div>
          <div class="trip-time-label"><?= htmlspecialchars($t['destination']) ?></div>
        </div>
      </div>
      <div style="text-align:right">
        <div class="trip-price">₦<?= number_format($t['fare']) ?></div>
        <div style="font-size:12px;color:var(--gray-400)">per seat</div>
      </div>
    </div>
    <div class="trip-card-body">
      <div class="trip-details">
        <div class="trip-detail"><i class="fas fa-bus" style="color:var(--orange)"></i><?= htmlspecialchars($t['bus_number']) ?></div>
        <div class="trip-detail"><i class="fas fa-road" style="color:var(--blue)"></i><?= $t['distance_km'] ?> km</div>
        <div class="trip-detail"><i class="fas fa-users"></i><?= $t['available_seats'] ?> seats left</div>
        <div class="trip-detail"><i class="fas fa-bus-alt"></i><?= ucfirst($t['bus_type']) ?></div>
        <?php if ($t['driver_name']): ?>
        <div class="trip-detail"><i class="fas fa-user-tie"></i><?= htmlspecialchars($t['driver_name']) ?></div>
        <?php endif; ?>
        <div class="trip-detail">
          <i class="fas fa-calendar"></i>
          <?= date('D d M', strtotime($t['departure_datetime'])) ?>
        </div>
      </div>
      <div class="trip-booking">
        <?php if ($t['available_seats'] <= 3 && $t['available_seats'] > 0): ?>
        <span class="badge badge-warning"><i class="fas fa-fire"></i> <?= $t['available_seats'] ?> left!</span>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="openBookingModal(<?= $t['id'] ?>, '<?= htmlspecialchars($t['origin']) ?>', '<?= htmlspecialchars($t['destination']) ?>', <?= $t['fare'] ?>, <?= $t['total_seats'] ?>)">
          <i class="fas fa-chair"></i> Select Seat
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- BOOKING MODAL -->
<div class="modal-overlay" id="bookingModal">
  <div class="modal-box" style="max-width:640px">
    <div class="modal-header">
      <h3><i class="fas fa-chair" style="color:var(--orange)"></i> Select Your Seat</h3>
      <button class="modal-close" onclick="closeModal('bookingModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div id="tripSummary" style="background:var(--gray-50);border-radius:12px;padding:16px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center">
        <div>
          <div id="modalRoute" style="font-size:16px;font-weight:800"></div>
          <div id="modalFare" style="font-size:13px;color:var(--orange);font-weight:700;margin-top:2px"></div>
        </div>
        <div id="selectedSeatInfo" style="text-align:right;display:none">
          <div style="font-size:12px;color:var(--gray-400)">Selected Seat</div>
          <div id="selectedSeatNum" style="font-size:28px;font-weight:800;color:var(--orange);font-family:var(--mono)"></div>
        </div>
      </div>

      <div class="seat-legend">
        <div class="legend-item"><div class="legend-box" style="background:white;border-color:var(--gray-300)"></div> Available</div>
        <div class="legend-item"><div class="legend-box" style="background:var(--orange);border-color:var(--orange)"></div> Your Selection</div>
        <div class="legend-item"><div class="legend-box" style="background:var(--gray-200);border-color:var(--gray-300)"></div> Booked</div>
        <div class="legend-item"><div class="legend-box" style="background:var(--blue);border-color:var(--blue)"></div> Driver</div>
      </div>

      <div class="bus-layout">
        <div class="bus-top">
          <div><i class="fas fa-bus"></i> Front of Bus</div>
          <div>🚦 Direction →</div>
        </div>
        <div class="bus-body" id="seatMap">
          <div style="text-align:center;padding:20px;color:var(--gray-400)"><i class="loading-spinner" style="border-color:var(--orange);border-top-color:transparent;margin:0 auto;display:block;width:30px;height:30px"></i></div>
        </div>
      </div>

      <div id="passengerDetails" style="display:none;margin-top:20px">
        <div style="font-weight:700;margin-bottom:12px">Passenger Details</div>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" id="paxName" class="form-control" placeholder="Passenger's full name" value="<?= $user ? htmlspecialchars($user['full_name']) : '' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="tel" id="paxPhone" class="form-control" placeholder="08012345678" value="<?= $user ? htmlspecialchars($user['phone']) : '' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Payment Method</label>
          <select id="payMethod" class="form-control">
            <option value="cash">Cash (Pay at terminal)</option>
            <?php if ($user && $user['role'] === 'passenger'): ?>
            <option value="wallet">Wallet (Balance: <?= formatMoney($user['wallet_balance'] ?? 0) ?>)</option>
            <?php endif; ?>
            <option value="card">Debit/Credit Card</option>
            <option value="transfer">Bank Transfer</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('bookingModal')">Cancel</button>
      <button class="btn btn-primary" id="confirmBookBtn" disabled onclick="confirmBooking()">
        <i class="fas fa-check"></i> Confirm Booking
      </button>
    </div>
  </div>
</div>

<!-- BOOKING CONFIRM MODAL -->
<div class="modal-overlay" id="ticketModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fas fa-check-circle" style="color:var(--success)"></i> Booking Confirmed!</h3>
      <button class="modal-close" onclick="closeModal('ticketModal');location.reload()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="ticketContent"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
      <a href="<?= BASE_URL ?>/passenger/tickets.php" class="btn btn-primary"><i class="fas fa-ticket-alt"></i> View All Tickets</a>
    </div>
  </div>
</div>

<script>
let selectedTrip = null, selectedSeat = null;

function openBookingModal(tripId, origin, dest, fare, totalSeats) {
  selectedTrip = {id: tripId, origin, dest, fare, totalSeats};
  selectedSeat = null;
  document.getElementById('modalRoute').textContent = origin + ' → ' + dest;
  document.getElementById('modalFare').textContent = '₦' + fare.toLocaleString() + ' per seat';
  document.getElementById('selectedSeatInfo').style.display = 'none';
  document.getElementById('passengerDetails').style.display = 'none';
  document.getElementById('confirmBookBtn').disabled = true;
  openModal('bookingModal');
  loadSeatMap(tripId, totalSeats);
}

async function loadSeatMap(tripId, totalSeats) {
  const res = await fetch(`search.php?seats=${tripId}`);
  const data = await res.json();
  renderSeatMap(data.booked, totalSeats);
}

function renderSeatMap(booked, total) {
  const map = document.getElementById('seatMap');
  let html = '';
  // Driver seat
  html += '<div class="seat-row"><div class="seat driver" title="Driver"><i class="fas fa-steering-wheel" style="font-size:14px"></i></div><div class="seat-gap"></div></div>';
  // Seats in rows of 4 (2+aisle+2)
  for (let i = 1; i <= total; i += 4) {
    html += '<div class="seat-row">';
    for (let j = i; j < i+4 && j <= total; j++) {
      if (j === i+2) html += '<div style="width:20px"></div>';
      const isBooked = booked.includes(j);
      html += `<div class="seat ${isBooked?'booked':'available'}" onclick="selectSeat(this,${j})" title="Seat ${j}">${j}</div>`;
    }
    html += '</div>';
  }
  map.innerHTML = html;
}

function selectSeat(el, num) {
  if (el.classList.contains('booked')) return;
  document.querySelectorAll('.seat.selected').forEach(s=>s.classList.remove('selected'));
  el.classList.add('selected');
  selectedSeat = num;
  document.getElementById('selectedSeatNum').textContent = num;
  document.getElementById('selectedSeatInfo').style.display = 'block';
  document.getElementById('passengerDetails').style.display = 'block';
  document.getElementById('confirmBookBtn').disabled = false;
}

async function confirmBooking() {
  if (!selectedSeat) return;
  const btn = document.getElementById('confirmBookBtn');
  btn.innerHTML = '<span class="loading-spinner"></span> Processing...';
  btn.disabled = true;

  const payload = {
    trip_id: selectedTrip.id,
    seat: selectedSeat,
    name: document.getElementById('paxName').value,
    phone: document.getElementById('paxPhone').value,
    method: document.getElementById('payMethod').value
  };

  const res = await fetch('<?= BASE_URL ?>/api/book.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  });
  const data = await res.json();

  if (data.success) {
    closeModal('bookingModal');
    document.getElementById('ticketContent').innerHTML = renderTicket(data);
    openModal('ticketModal');
  } else {
    showToast(data.error || 'Booking failed. Please try again.', 'error');
    btn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking';
    btn.disabled = false;
  }
}

function renderTicket(d) {
  return `<div class="ticket">
    <div class="ticket-header">
      <div style="display:flex;justify-content:space-between;align-items:start">
        <div>
          <div style="font-size:13px;opacity:.8;text-transform:uppercase;letter-spacing:1px">KTSTA E-Ticket</div>
          <div style="font-size:22px;font-weight:800;margin-top:4px">${d.origin} → ${d.destination}</div>
        </div>
        <div style="text-align:right">
          <div style="font-size:10px;opacity:.7">Booking Ref</div>
          <div style="font-family:var(--mono);font-size:14px;font-weight:700">${d.booking_ref}</div>
        </div>
      </div>
    </div>
    <div class="ticket-body">
      <div class="ticket-field"><div class="label">Departure</div><div class="value">${d.departure}</div></div>
      <div class="ticket-field"><div class="label">Seat</div><div class="value">${d.seat}</div></div>
      <div class="ticket-field"><div class="label">Passenger</div><div class="value">${d.passenger}</div></div>
      <div class="ticket-field"><div class="label">Fare Paid</div><div class="value">₦${Number(d.fare).toLocaleString()}</div></div>
      <div class="ticket-field"><div class="label">Bus</div><div class="value">${d.bus}</div></div>
      <div class="ticket-field"><div class="label">Status</div><div class="value"><span class="badge badge-success">Confirmed</span></div></div>
    </div>
    <div class="ticket-footer">
      <div>
        <div style="font-size:10px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.5px">Scan QR at terminal</div>
        <div style="font-size:11px;color:var(--gray-500);margin-top:2px">Present this ticket to the officer</div>
      </div>
      <img src="${d.qr}" alt="QR Code" style="width:80px;height:80px;border-radius:8px">
    </div>
  </div>`;
}
</script>
