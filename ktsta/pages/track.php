<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Live Bus Tracking';
$db = getDB();

$activeTrips = $db->query("SELECT t.*, r.origin, r.destination, b.bus_number, b.bus_type, b.model, b.registration_plate,
  u.full_name as driver_name,
  (t.total_seats - t.available_seats) as boarded
  FROM trips t 
  JOIN routes r ON t.route_id=r.id 
  JOIN buses b ON t.bus_id=b.id
  LEFT JOIN users u ON t.driver_id=u.id
  WHERE t.status IN ('boarding','in_transit','scheduled') AND t.departure_datetime BETWEEN DATE_SUB(NOW(),INTERVAL 1 HOUR) AND DATE_ADD(NOW(),INTERVAL 8 HOUR)
  ORDER BY t.departure_datetime ASC")->fetch_all(MYSQLI_ASSOC);

// Cities with approximate lat/lng in Katsina State area
$cityCoords = [
  'Katsina'  => [12.9908, 7.6017],
  'Kano'     => [12.0022, 8.5919],
  'Daura'    => [13.0352, 8.3019],
  'Mashi'    => [13.0048, 7.5638],
  'Funtua'   => [11.5231, 7.3131],
  'Jibia'    => [13.3410, 7.2152],
  'Zango'    => [12.9044, 7.4521],
  'Abuja'    => [9.0579, 7.4951],
  'Dutsin-Ma'=> [12.4554, 7.5003],
];

include '../includes/header.php';
?>
<style>
.tracking-page { display:grid; grid-template-columns:360px 1fr; min-height:calc(100vh - 64px); }
.tracking-sidebar { background:white; border-right:1px solid var(--gray-200); overflow-y:auto; padding:20px; }
.map-container { background:#E8EDF2; position:relative; display:flex; flex-direction:column; }
#leafletMap { flex:1; min-height:600px; }
.trip-track-card { border:2px solid var(--gray-200); border-radius:14px; padding:14px; margin-bottom:10px; cursor:pointer; transition:all .2s; }
.trip-track-card:hover, .trip-track-card.active { border-color:var(--orange); background:#FFF8F5; }
.trip-track-card .route { font-size:16px; font-weight:800; color:var(--gray-900); }
.trip-track-card .meta { font-size:12px; color:var(--gray-400); margin-top:3px; }
.progress-track { height:6px; background:var(--gray-200); border-radius:3px; margin-top:10px; overflow:hidden; }
.progress-fill { height:100%; border-radius:3px; background:linear-gradient(90deg,var(--orange),var(--blue)); transition:width .5s; }

.info-panel { position:absolute; bottom:20px; right:20px; background:white; border-radius:16px; padding:16px 20px; box-shadow:var(--shadow-lg); min-width:220px; z-index:1000; }
.bus-marker { width:36px; height:36px; background:var(--orange); border-radius:50%; border:3px solid white; box-shadow:0 3px 10px rgba(0,0,0,.3); display:flex; align-items:center; justify-content:center; color:white; font-size:14px; }

@media(max-width:900px) { .tracking-page { grid-template-columns:1fr; } }
</style>

<div class="tracking-page">
  <aside class="tracking-sidebar">
    <div style="font-size:18px;font-weight:800;margin-bottom:4px"><i class="fas fa-satellite-dish" style="color:var(--orange)"></i> Live Tracking</div>
    <div style="font-size:12px;color:var(--gray-400);margin-bottom:16px">Real-time bus locations</div>

    <!-- Search -->
    <div class="form-group" style="margin-bottom:16px">
      <input type="text" id="trackSearch" class="form-control" placeholder="Search by route or bus..." oninput="filterTrips(this.value)">
    </div>

    <!-- Status badges -->
    <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <span class="badge badge-warning" style="cursor:pointer" onclick="filterStatus('boarding')">Boarding</span>
      <span class="badge badge-info" style="cursor:pointer" onclick="filterStatus('in_transit')">In Transit</span>
      <span class="badge badge-gray" style="cursor:pointer" onclick="filterStatus('scheduled')">Scheduled</span>
      <span class="badge badge-orange" onclick="filterStatus('')" style="cursor:pointer;background:#FFF0E8;color:var(--orange)">All</span>
    </div>

    <div id="tripList">
    <?php if (empty($activeTrips)): ?>
    <div style="text-align:center;padding:40px;color:var(--gray-400)">
      <i class="fas fa-bus" style="font-size:32px;display:block;margin-bottom:10px"></i>
      No active trips at the moment
    </div>
    <?php endif; ?>
    <?php foreach($activeTrips as $i => $t): ?>
    <?php
      $progress = $t['status']==='in_transit' ? rand(20,85) : ($t['status']==='boarding' ? rand(5,20) : 0);
      $statusColor = ['boarding'=>'warning','in_transit'=>'info','scheduled'=>'gray'][$t['status']] ?? 'gray';
    ?>
    <div class="trip-track-card" id="tripCard<?= $t['id'] ?>" onclick="focusBus(<?= $t['id'] ?>, '<?= $t['origin'] ?>', '<?= $t['destination'] ?>', '<?= $t['bus_number'] ?>')">
      <div style="display:flex;justify-content:space-between;align-items:start">
        <div class="route"><?= htmlspecialchars($t['origin']) ?> → <?= htmlspecialchars($t['destination']) ?></div>
        <span class="badge badge-<?= $statusColor ?>"><?= str_replace('_',' ',$t['status']) ?></span>
      </div>
      <div class="meta">
        <i class="fas fa-bus" style="color:var(--orange)"></i> <?= $t['bus_number'] ?> &bull;
        <?= date('H:i', strtotime($t['departure_datetime'])) ?> departure &bull;
        <?= $t['boarded'] ?>/<?= $t['total_seats'] ?> passengers
      </div>
      <?php if ($t['driver_name']): ?>
      <div class="meta"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($t['driver_name']) ?></div>
      <?php endif; ?>
      <div class="progress-track">
        <div class="progress-fill" style="width:<?= $progress ?>%"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--gray-400);margin-top:4px">
        <span><?= htmlspecialchars($t['origin']) ?></span>
        <span><?= $progress ?>% complete</span>
        <span><?= htmlspecialchars($t['destination']) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
  </aside>

  <!-- MAP -->
  <div class="map-container">
    <div id="leafletMap"></div>
    <div class="info-panel" id="infoPanel" style="display:none">
      <div style="font-size:12px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Selected Bus</div>
      <div id="infoBusNum" style="font-size:18px;font-weight:800;color:var(--blue)"></div>
      <div id="infoRoute" style="font-size:13px;color:var(--gray-600);margin-top:2px"></div>
      <div style="margin-top:8px;display:flex;gap:8px">
        <div style="flex:1;background:var(--gray-50);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:11px;color:var(--gray-400)">Speed</div>
          <div style="font-weight:700;font-family:var(--mono)" id="infoSpeed">—</div>
        </div>
        <div style="flex:1;background:var(--gray-50);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:11px;color:var(--gray-400)">ETA</div>
          <div style="font-weight:700;font-family:var(--mono)" id="infoETA">—</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Katsina State centered map
const map = L.map('leafletMap').setView([12.5, 7.8], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors', maxZoom:18
}).addTo(map);

const cityCoords = <?= json_encode($cityCoords) ?>;
const trips = <?= json_encode($activeTrips) ?>;
const markers = {};

// Add city markers
Object.entries(cityCoords).forEach(([city,[lat,lng]]) => {
  L.circleMarker([lat,lng], {radius:6, color:'#1B4F9B', fillColor:'#1B4F9B', fillOpacity:0.8, weight:2})
   .bindTooltip(city, {permanent:false, direction:'top'})
   .addTo(map);
});

// Bus icon
function busIcon(status) {
  const colors = {boarding:'#D97706', in_transit:'#1B4F9B', scheduled:'#6B7583'};
  const c = colors[status] || '#6B7583';
  return L.divIcon({
    html: `<div style="width:36px;height:36px;background:${c};border-radius:50%;border:3px solid white;box-shadow:0 3px 10px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;color:white;font-size:14px"><i class="fas fa-bus"></i></div>`,
    className: '', iconSize:[36,36], iconAnchor:[18,18]
  });
}

// Plot buses (simulated positions along route)
trips.forEach(t => {
  const oCoords = cityCoords[t.origin];
  const dCoords = cityCoords[t.destination];
  if (!oCoords || !dCoords) return;

  const pct = t.status === 'in_transit' ? (Math.random() * 0.7 + 0.1) : (t.status === 'boarding' ? 0.02 : 0);
  const lat  = oCoords[0] + (dCoords[0] - oCoords[0]) * pct;
  const lng  = oCoords[1] + (dCoords[1] - oCoords[1]) * pct;

  // Draw route line
  L.polyline([oCoords, dCoords], {color:'#E8520A', weight:2, opacity:0.4, dashArray:'6,6'}).addTo(map);

  const marker = L.marker([lat, lng], {icon: busIcon(t.status)})
    .bindPopup(`<strong>${t.bus_number}</strong><br>${t.origin} → ${t.destination}<br>Status: ${t.status.replace('_',' ')}<br>${t.total_seats - t.available_seats}/${t.total_seats} passengers`)
    .addTo(map);

  markers[t.id] = {marker, trip: t, lat, lng};
});

function focusBus(id, origin, dest, busNum) {
  document.querySelectorAll('.trip-track-card').forEach(c => c.classList.remove('active'));
  document.getElementById('tripCard'+id)?.classList.add('active');

  if (markers[id]) {
    map.setView([markers[id].lat, markers[id].lng], 11);
    markers[id].marker.openPopup();
  }

  const status = markers[id]?.trip.status || 'scheduled';
  const speed = status === 'in_transit' ? Math.floor(Math.random()*30+60)+' km/h' : status === 'boarding' ? 'Stationary' : 'Waiting';
  const eta = status === 'in_transit' ? Math.floor(Math.random()*60+30)+'m' : '—';

  document.getElementById('infoBusNum').textContent = busNum;
  document.getElementById('infoRoute').textContent = origin + ' → ' + dest;
  document.getElementById('infoSpeed').textContent = speed;
  document.getElementById('infoETA').textContent = eta;
  document.getElementById('infoPanel').style.display = 'block';
}

function filterTrips(query) {
  document.querySelectorAll('.trip-track-card').forEach(card => {
    const text = card.textContent.toLowerCase();
    card.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
  });
}

function filterStatus(status) {
  trips.forEach(t => {
    const card = document.getElementById('tripCard'+t.id);
    if (!card) return;
    card.style.display = (!status || t.status === status) ? '' : 'none';
  });
}

// Simulate bus movement
setInterval(() => {
  Object.keys(markers).forEach(id => {
    const m = markers[id];
    if (m.trip.status !== 'in_transit') return;
    const oCoords = cityCoords[m.trip.origin];
    const dCoords = cityCoords[m.trip.destination];
    if (!oCoords || !dCoords) return;
    const jitter = (Math.random()-0.5)*0.002;
    const dirLat = (dCoords[0]-oCoords[0]) * 0.0003;
    const dirLng = (dCoords[1]-oCoords[1]) * 0.0003;
    m.lat = Math.max(Math.min(m.lat+dirLat+jitter, Math.max(oCoords[0],dCoords[0])), Math.min(oCoords[0],dCoords[0]));
    m.lng = Math.max(Math.min(m.lng+dirLng+jitter, Math.max(oCoords[1],dCoords[1])), Math.min(oCoords[1],dCoords[1]));
    m.marker.setLatLng([m.lat, m.lng]);
  });
}, 3000);
</script>
<?php include '../includes/footer.php'; ?>
