<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Fare Calculator';
$db = getDB();
$routes = $db->query("SELECT * FROM routes WHERE is_active=1 ORDER BY origin, destination")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<style>
.calc-page { max-width:900px; margin:0 auto; padding:40px 20px; }
.calc-card { background:white; border-radius:24px; box-shadow:var(--shadow-lg); overflow:hidden; }
.calc-header { background:linear-gradient(135deg,var(--orange),var(--orange-dark)); padding:32px; color:white; text-align:center; }
.calc-body { padding:32px; }
.result-box { background:linear-gradient(135deg,#F0FDF4,#DCFCE7); border:2px solid #86EFAC; border-radius:16px; padding:24px; text-align:center; margin-top:24px; display:none; }
.result-box.show { display:block; animation:fadeIn .4s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
.fare-breakdown { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-top:20px; }
.fare-item { background:white; border-radius:12px; padding:16px; text-align:center; border:1px solid var(--gray-200); }
.fare-item .amount { font-size:22px; font-weight:800; font-family:var(--mono); color:var(--orange); }
.fare-item .lbl { font-size:11px; color:var(--gray-400); text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
.promo-section { margin-top:20px; }
.route-comparison { margin-top:32px; }
</style>

<div class="calc-page">
  <div class="page-header" style="text-align:center">
    <h1 style="font-size:32px">Fare Calculator</h1>
    <p>Estimate your travel cost before booking</p>
  </div>

  <div class="calc-card">
    <div class="calc-header">
      <i class="fas fa-calculator" style="font-size:36px;opacity:.9;margin-bottom:12px;display:block"></i>
      <h2 style="font-size:22px;font-weight:800">Calculate Your Fare</h2>
      <p style="opacity:.8;margin-top:4px">Get instant fare estimates for any KTSTA route</p>
    </div>
    <div class="calc-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="form-group">
          <label class="form-label"><i class="fas fa-map-marker-alt" style="color:var(--orange)"></i> From</label>
          <select id="calcFrom" class="form-control" onchange="updateDestinations()">
            <option value="">Select origin...</option>
            <?php $origins = array_unique(array_column($routes,'origin')); sort($origins); foreach($origins as $o): ?>
            <option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars($o) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label"><i class="fas fa-flag" style="color:var(--blue)"></i> To</label>
          <select id="calcTo" class="form-control" onchange="calculateFare()">
            <option value="">Select destination...</option>
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
        <div class="form-group">
          <label class="form-label">Number of Passengers</label>
          <select id="numPassengers" class="form-control" onchange="calculateFare()">
            <?php for($i=1;$i<=14;$i++): ?><option value="<?= $i ?>"><?= $i ?> passenger<?= $i>1?'s':'' ?></option><?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Bus Type</label>
          <select id="busType" class="form-control" onchange="calculateFare()">
            <option value="standard">Standard (Minibus)</option>
            <option value="coaster">Coaster (+10%)</option>
            <option value="luxury">Luxury (+25%)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Promo Code (Optional)</label>
          <div style="display:flex;gap:6px">
            <input type="text" id="promoInput" class="form-control" placeholder="e.g. KTSTA10" style="text-transform:uppercase">
            <button class="btn btn-ghost btn-sm" onclick="applyPromo()" style="white-space:nowrap">Apply</button>
          </div>
          <div id="promoMsg" style="font-size:11px;margin-top:4px"></div>
        </div>
      </div>

      <!-- Result -->
      <div class="result-box" id="resultBox">
        <div style="font-size:13px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Estimated Total Fare</div>
        <div style="font-size:52px;font-weight:800;font-family:var(--mono);color:var(--success)" id="totalFare">₦0</div>
        <div style="font-size:14px;color:var(--gray-500);margin-top:4px" id="fareSubtitle"></div>

        <div class="fare-breakdown" id="fareBreakdown"></div>

        <div style="margin-top:20px;padding-top:20px;border-top:2px dashed var(--gray-200)">
          <div style="display:flex;gap:20px;justify-content:center;font-size:13px;color:var(--gray-500)">
            <div><i class="fas fa-road" style="color:var(--orange)"></i> <span id="routeDistance"></span></div>
            <div><i class="fas fa-clock" style="color:var(--blue)"></i> <span id="routeDuration"></span></div>
          </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:center;margin-top:20px">
          <a id="bookBtn" href="#" class="btn btn-primary"><i class="fas fa-ticket-alt"></i> Book This Trip</a>
          <button class="btn btn-ghost" onclick="document.getElementById('resultBox').classList.remove('show')"><i class="fas fa-redo"></i> Reset</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Route Comparison Table -->
  <div class="route-comparison card" style="margin-top:28px">
    <div class="card-title">All Route Fares</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Route</th><th>Distance</th><th>Duration</th><th>Std Fare</th><th>Coaster</th><th>Luxury</th><th>Book</th></tr></thead>
        <tbody>
        <?php foreach($routes as $r): ?>
        <tr>
          <td><strong><?= $r['origin'] ?></strong> → <?= $r['destination'] ?></td>
          <td><?= $r['distance_km'] ?> km</td>
          <td><?= round($r['duration_minutes']/60,1) ?> hrs</td>
          <td style="color:var(--orange);font-weight:700;font-family:var(--mono)"><?= formatMoney($r['base_fare']) ?></td>
          <td style="font-family:var(--mono)"><?= formatMoney($r['base_fare']*1.1) ?></td>
          <td style="font-family:var(--mono)"><?= formatMoney($r['base_fare']*1.25) ?></td>
          <td><a href="<?= BASE_URL ?>/pages/search.php?from=<?= urlencode($r['origin']) ?>&to=<?= urlencode($r['destination']) ?>" class="btn btn-primary btn-sm">Book</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const routes = <?= json_encode($routes) ?>;
const promos = {
  'KTSTA10':  { type:'percentage', value:10, desc:'10% discount' },
  'WELCOME500':{ type:'fixed',     value:500, min:2000, desc:'₦500 off' },
  'FESTIVE20': { type:'percentage', value:20, desc:'20% festive discount' },
};
let activePromo = null, baseFare = 0;

function updateDestinations() {
  const from = document.getElementById('calcFrom').value;
  const toSel = document.getElementById('calcTo');
  toSel.innerHTML = '<option value="">Select destination...</option>';
  const dests = routes.filter(r => r.origin === from);
  dests.forEach(r => {
    toSel.innerHTML += `<option value="${r.destination}" data-fare="${r.base_fare}" data-dist="${r.distance_km}" data-dur="${r.duration_minutes}">${r.destination}</option>`;
  });
  document.getElementById('resultBox').classList.remove('show');
}

function calculateFare() {
  const toSel = document.getElementById('calcTo');
  const opt = toSel.selectedOptions[0];
  if (!opt || !opt.dataset.fare) { document.getElementById('resultBox').classList.remove('show'); return; }

  baseFare = parseFloat(opt.dataset.fare);
  const pax = parseInt(document.getElementById('numPassengers').value);
  const type = document.getElementById('busType').value;
  const multiplier = type==='coaster' ? 1.1 : type==='luxury' ? 1.25 : 1;

  let perSeat = baseFare * multiplier;
  let total = perSeat * pax;
  let discount = 0;

  if (activePromo) {
    if (activePromo.min && total < activePromo.min) {
      document.getElementById('promoMsg').innerHTML = `<span style="color:var(--danger)">Minimum fare ₦${activePromo.min.toLocaleString()} required</span>`;
      activePromo = null;
    } else {
      if (activePromo.type === 'percentage') discount = total * (activePromo.value/100);
      else discount = Math.min(activePromo.value, total);
      total -= discount;
    }
  }

  const from = document.getElementById('calcFrom').value;
  const dist = opt.dataset.dist;
  const dur  = opt.dataset.dur;

  document.getElementById('totalFare').textContent = '₦' + total.toLocaleString('en-NG', {minimumFractionDigits:2});
  document.getElementById('fareSubtitle').textContent = `${pax} passenger${pax>1?'s':''} × ₦${perSeat.toLocaleString()} per seat`;
  document.getElementById('routeDistance').textContent = `${dist} km`;
  document.getElementById('routeDuration').textContent = `${Math.round(dur/60*10)/10} hours`;

  let breakdown = `
    <div class="fare-item"><div class="amount">₦${baseFare.toLocaleString()}</div><div class="lbl">Base Fare</div></div>
    <div class="fare-item"><div class="amount">${pax}</div><div class="lbl">Passengers</div></div>`;
  if (discount > 0) {
    breakdown += `<div class="fare-item" style="background:#FEF9C3"><div class="amount" style="color:var(--warning)">-₦${discount.toFixed(2)}</div><div class="lbl">Discount</div></div>`;
  } else {
    breakdown += `<div class="fare-item"><div class="amount">₦50</div><div class="lbl">Booking Fee</div></div>`;
  }
  document.getElementById('fareBreakdown').innerHTML = breakdown;

  document.getElementById('bookBtn').href = `<?= BASE_URL ?>/pages/search.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(opt.value)}`;
  document.getElementById('resultBox').classList.add('show');
}

function applyPromo() {
  const code = document.getElementById('promoInput').value.trim().toUpperCase();
  const msgEl = document.getElementById('promoMsg');
  if (promos[code]) {
    activePromo = promos[code];
    msgEl.innerHTML = `<span style="color:var(--success)">✓ ${activePromo.desc} applied!</span>`;
    calculateFare();
  } else {
    msgEl.innerHTML = `<span style="color:var(--danger)">Invalid promo code</span>`;
    activePromo = null;
    calculateFare();
  }
}
</script>
<?php include '../includes/footer.php'; ?>
