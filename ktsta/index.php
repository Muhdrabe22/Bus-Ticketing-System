<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Home';

// Get stats
$db = getDB();
$stats = [];
$stats['routes'] = $db->query("SELECT COUNT(*) as c FROM routes WHERE is_active=1")->fetch_assoc()['c'];
$stats['trips'] = $db->query("SELECT COUNT(*) as c FROM trips WHERE status IN ('scheduled','boarding')")->fetch_assoc()['c'];
$stats['buses'] = $db->query("SELECT COUNT(*) as c FROM buses WHERE status='active'")->fetch_assoc()['c'];
$stats['passengers'] = $db->query("SELECT COUNT(*) as c FROM users WHERE role='passenger'")->fetch_assoc()['c'];

// Popular routes
$routes = $db->query("SELECT * FROM routes WHERE is_active=1 ORDER BY base_fare ASC LIMIT 6");
$popularRoutes = $routes->fetch_all(MYSQLI_ASSOC);

// Next departures
$trips = $db->query("SELECT t.*, r.origin, r.destination, b.bus_number, b.bus_type 
  FROM trips t 
  JOIN routes r ON t.route_id=r.id 
  JOIN buses b ON t.bus_id=b.id 
  WHERE t.departure_datetime > NOW() AND t.status='scheduled' 
  ORDER BY t.departure_datetime ASC LIMIT 5");
$nextTrips = $trips->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>
<style>
.hero {
  position: relative; min-height: 88vh; display: flex; align-items: center;
  background: linear-gradient(135deg, #0F1419 0%, #1B2A3A 40%, #0F2038 100%);
  overflow: hidden;
}
.hero-bg {
  position: absolute; inset: 0;
  background: url('https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=1600') center/cover no-repeat;
  opacity: .12;
}
.hero-pattern {
  position: absolute; inset: 0;
  background-image: radial-gradient(circle at 25% 50%, rgba(232,82,10,.15) 0%, transparent 50%),
                    radial-gradient(circle at 75% 50%, rgba(27,79,155,.15) 0%, transparent 50%);
}
.hero-stripe {
  position: absolute; bottom: 0; left: 0; right: 0; height: 8px;
  background: linear-gradient(90deg, var(--orange) 0%, var(--orange) 45%, white 45%, white 55%, var(--blue) 55%);
}
.hero-content { position: relative; z-index: 2; max-width: 1200px; margin: 0 auto; padding: 80px 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; width: 100%; }
.hero-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(232,82,10,.15); border: 1px solid rgba(232,82,10,.3); border-radius: 30px; padding: 6px 14px; font-size: 12px; color: var(--orange); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 20px; }
.hero h1 { font-size: 52px; font-weight: 800; color: white; line-height: 1.1; margin-bottom: 16px; }
.hero h1 span { color: var(--orange); }
.hero p { font-size: 16px; color: rgba(255,255,255,.65); line-height: 1.7; margin-bottom: 32px; }
.hero-actions { display: flex; gap: 14px; flex-wrap: wrap; }

.search-card {
  background: rgba(255,255,255,.05); backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,.1); border-radius: 24px; padding: 32px;
}
.search-card h3 { color: white; font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.search-card .form-control { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.15); color: white; }
.search-card .form-control::placeholder { color: rgba(255,255,255,.4); }
.search-card .form-control:focus { border-color: var(--orange); background: rgba(255,255,255,.12); }
.search-card .form-label { color: rgba(255,255,255,.7); }

.stats-bar { background: white; border-bottom: 1px solid var(--gray-200); }
.stats-bar-inner { max-width: 1200px; margin: 0 auto; padding: 24px 2rem; display: grid; grid-template-columns: repeat(4,1fr); }
.stat-item { text-align: center; padding: 16px; border-right: 1px solid var(--gray-200); }
.stat-item:last-child { border-right: none; }
.stat-num { font-size: 36px; font-weight: 800; color: var(--orange); font-family: var(--mono); }
.stat-txt { font-size: 13px; color: var(--gray-500); font-weight: 500; }

.section { padding: 80px 2rem; max-width: 1200px; margin: 0 auto; }
.section-header { text-align: center; margin-bottom: 48px; }
.section-header h2 { font-size: 36px; font-weight: 800; color: var(--gray-900); }
.section-header p { font-size: 16px; color: var(--gray-500); margin-top: 8px; }
.section-header .accent { display: inline-block; width: 40px; height: 4px; background: var(--orange); border-radius: 2px; margin: 12px auto; }

.route-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(280px,1fr)); gap: 20px; }
.route-card {
  background: white; border: 2px solid var(--gray-200); border-radius: 20px; padding: 24px;
  transition: all .3s; cursor: pointer; position: relative; overflow: hidden;
}
.route-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background: linear-gradient(90deg,var(--orange),var(--blue)); opacity:0; transition:.3s; }
.route-card:hover { border-color: var(--orange); transform: translateY(-4px); box-shadow: 0 12px 40px rgba(232,82,10,.15); }
.route-card:hover::before { opacity:1; }
.route-arrow { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.route-city { font-size: 18px; font-weight: 800; color: var(--gray-900); }
.route-arrow-icon { width: 32px; height: 32px; border-radius: 50%; background: var(--gray-100); display: flex; align-items: center; justify-content: center; color: var(--orange); font-size: 13px; }
.route-meta { display: flex; justify-content: space-between; align-items: center; }
.route-price { font-size: 22px; font-weight: 800; color: var(--orange); font-family: var(--mono); }
.route-info { font-size: 12px; color: var(--gray-400); }

.features-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 28px; }
.feature-card { padding: 32px 24px; border-radius: 20px; background: white; border: 1px solid var(--gray-200); text-align: center; transition: all .3s; }
.feature-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
.feature-icon { width: 64px; height: 64px; border-radius: 20px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
.feature-card h3 { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
.feature-card p { font-size: 13px; color: var(--gray-500); line-height: 1.7; }

.departures-section { background: var(--gray-900); padding: 60px 2rem; }
.departures-inner { max-width: 1200px; margin: 0 auto; }
.departure-row { display: flex; align-items: center; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 16px 20px; margin-bottom: 10px; gap: 20px; transition: all .2s; }
.departure-row:hover { background: rgba(232,82,10,.1); border-color: rgba(232,82,10,.3); }
.dep-time { font-size: 20px; font-weight: 800; color: white; font-family: var(--mono); min-width: 70px; }
.dep-route { flex: 1; }
.dep-origin { font-size: 15px; font-weight: 700; color: white; }
.dep-dest { font-size: 12px; color: var(--gray-400); }
.dep-seats { text-align: center; }
.dep-seats .num { font-size: 18px; font-weight: 800; color: var(--orange); font-family: var(--mono); }
.dep-seats .lbl { font-size: 10px; color: var(--gray-500); }

.cta-section { background: linear-gradient(135deg, var(--orange), var(--orange-dark)); padding: 80px 2rem; text-align: center; }
.cta-section h2 { font-size: 40px; font-weight: 800; color: white; margin-bottom: 12px; }
.cta-section p { font-size: 16px; color: rgba(255,255,255,.8); margin-bottom: 32px; }

@media (max-width: 900px) {
  .hero-content { grid-template-columns: 1fr; }
  .hero h1 { font-size: 36px; }
  .features-grid { grid-template-columns: 1fr 1fr; }
  .stats-bar-inner { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
  .features-grid { grid-template-columns: 1fr; }
  .stats-bar-inner { grid-template-columns: 1fr 1fr; }
}
</style>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-pattern"></div>
  <div class="hero-stripe"></div>
  <div class="hero-content">
    <div>
      <div class="hero-badge"><i class="fas fa-bus"></i> Katsina State Transport Authority</div>
      <h1>Travel Safe.<br>Travel <span>Smart</span>.<br>Travel KTSTA.</h1>
      <p>Book bus tickets online across Katsina State and major destinations. Safe, affordable, and reliable public transport — right at your fingertips.</p>
      <div class="hero-actions">
        <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-primary btn-lg"><i class="fas fa-search"></i> Search Routes</a>
        <a href="<?= BASE_URL ?>/pages/register.php" class="btn btn-outline btn-lg" style="border-color:white;color:white"><i class="fas fa-user-plus"></i> Sign Up Free</a>
      </div>
    </div>
    <div class="search-card">
      <h3><i class="fas fa-route" style="color:var(--orange)"></i> Quick Search</h3>
      <form action="<?= BASE_URL ?>/pages/search.php" method="GET">
        <div class="form-group">
          <label class="form-label">From</label>
          <select name="from" class="form-control">
            <option value="">Select Origin</option>
            <?php foreach($popularRoutes as $r): ?>
              <option value="<?= htmlspecialchars($r['origin']) ?>"><?= htmlspecialchars($r['origin']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">To</label>
          <select name="to" class="form-control">
            <option value="">Select Destination</option>
            <?php
            $dests = array_unique(array_column($popularRoutes, 'destination'));
            foreach($dests as $d): ?>
              <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Travel Date</label>
          <input type="date" name="date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px">
          <i class="fas fa-search"></i> Search Available Trips
        </button>
      </form>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats-bar">
  <div class="stats-bar-inner">
    <div class="stat-item">
      <div class="stat-num" id="routeCount">0</div>
      <div class="stat-txt">Active Routes</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" id="tripCount">0</div>
      <div class="stat-txt">Upcoming Trips</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" id="busCount">0</div>
      <div class="stat-txt">Fleet Size</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" id="passCount">0</div>
      <div class="stat-txt">Registered Passengers</div>
    </div>
  </div>
</div>

<!-- POPULAR ROUTES -->
<div style="background:var(--gray-50);padding:80px 2rem">
  <div style="max-width:1200px;margin:0 auto">
    <div class="section-header">
      <h2>Popular Routes</h2>
      <div class="accent"></div>
      <p>Frequently traveled routes across Katsina State and beyond</p>
    </div>
    <div class="route-grid">
      <?php foreach($popularRoutes as $r): ?>
      <div class="route-card" onclick="window.location='<?= BASE_URL ?>/pages/search.php?from=<?= urlencode($r['origin']) ?>&to=<?= urlencode($r['destination']) ?>'">
        <div class="route-arrow">
          <div class="route-city"><?= htmlspecialchars($r['origin']) ?></div>
          <div class="route-arrow-icon"><i class="fas fa-arrow-right"></i></div>
          <div class="route-city"><?= htmlspecialchars($r['destination']) ?></div>
        </div>
        <div class="route-meta">
          <div>
            <div class="route-price"><?= formatMoney($r['base_fare']) ?></div>
            <div class="route-info"><i class="fas fa-road"></i> <?= $r['distance_km'] ?> km &bull; <?= round($r['duration_minutes']/60,1) ?> hrs</div>
          </div>
          <span class="badge badge-success">Available</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:32px">
      <a href="<?= BASE_URL ?>/pages/routes.php" class="btn btn-outline">View All Routes <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</div>

<!-- NEXT DEPARTURES -->
<div class="departures-section">
  <div class="departures-inner">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:32px">
      <div>
        <h2 style="color:white;font-size:28px;font-weight:800">Next Departures</h2>
        <p style="color:var(--gray-500);margin-top:4px">Upcoming trips from Katsina</p>
      </div>
      <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-primary">Book Now</a>
    </div>
    <?php foreach($nextTrips as $t): ?>
    <div class="departure-row">
      <div class="dep-time"><?= date('H:i', strtotime($t['departure_datetime'])) ?></div>
      <div class="dep-route">
        <div class="dep-origin"><?= htmlspecialchars($t['origin']) ?> → <?= htmlspecialchars($t['destination']) ?></div>
        <div class="dep-dest"><?= date('D d M', strtotime($t['departure_datetime'])) ?> &bull; <?= ucfirst($t['bus_type']) ?> &bull; <?= $t['bus_number'] ?></div>
      </div>
      <div class="dep-seats">
        <div class="num"><?= $t['available_seats'] ?></div>
        <div class="lbl">seats left</div>
      </div>
      <div style="font-size:18px;font-weight:800;color:var(--orange);font-family:var(--mono)">₦<?= number_format($t['fare']) ?></div>
      <a href="<?= BASE_URL ?>/pages/search.php?trip=<?= $t['id'] ?>" class="btn btn-primary btn-sm">Book</a>
    </div>
    <?php endforeach; ?>
    <?php if(empty($nextTrips)): ?>
    <div style="text-align:center;padding:40px;color:var(--gray-500)"><i class="fas fa-calendar-times" style="font-size:32px;margin-bottom:12px;display:block"></i>No upcoming trips found</div>
    <?php endif; ?>
  </div>
</div>

<!-- FEATURES -->
<div style="padding:80px 2rem;background:white">
  <div style="max-width:1200px;margin:0 auto">
    <div class="section-header">
      <h2>Why Choose KTSTA?</h2>
      <div class="accent"></div>
      <p>Modern, reliable, and affordable transport for everyone</p>
    </div>
    <div class="features-grid">
      <?php $features = [
        ['fas fa-shield-alt','#FFF0E8','var(--orange)','Safe & Secure','All our buses are regularly inspected and maintained to the highest safety standards. Trained and licensed drivers only.'],
        ['fas fa-mobile-alt','#EFF6FF','var(--blue)','Easy Online Booking','Book your seat from anywhere using our website or mobile app. No queues, no hassle.'],
        ['fas fa-ticket-alt','#F0FDF4','var(--success)','Digital Tickets','Get QR-coded e-tickets on your phone. No printing needed — just scan and board.'],
        ['fas fa-map-marker-alt','#FEF9C3','var(--warning)','Real-time Tracking','Know where your bus is at any time. Live GPS tracking for all KTSTA routes.'],
        ['fas fa-wallet','#F5F3FF','#7C3AED','Wallet & Payments','Top up your KTSTA wallet and pay instantly. Accept card, bank transfer & cash.'],
        ['fas fa-headset','#FFF0E8','var(--orange)','24/7 Support','Our dedicated support team is available around the clock to assist you.'],
      ]; ?>
      <?php foreach($features as [$icon,$bg,$color,$title,$desc]): ?>
      <div class="feature-card">
        <div class="feature-icon" style="background:<?= $bg ?>;color:<?= $color ?>"><i class="<?= $icon ?>"></i></div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- HOW IT WORKS -->
<div style="background:var(--gray-50);padding:80px 2rem">
  <div style="max-width:900px;margin:0 auto;text-align:center">
    <div class="section-header">
      <h2>How It Works</h2>
      <div class="accent"></div>
      <p>3 simple steps to your next journey</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:30px;position:relative">
      <div style="position:absolute;top:40px;left:calc(16.67% + 20px);right:calc(16.67% + 20px);height:2px;background:linear-gradient(90deg,var(--orange),var(--blue));z-index:0"></div>
      <?php $steps=[['fas fa-search','Search','Find your route, pick a date and choose from available trips'],['fas fa-chair','Select Seat','Choose your preferred seat from our live seat map'],['fas fa-qrcode','Get Ticket','Pay securely and receive your QR-coded ticket instantly']]; ?>
      <?php foreach($steps as $i=>[$icon,$title,$desc]): ?>
      <div style="position:relative;z-index:1">
        <div style="width:80px;height:80px;border-radius:50%;background:white;border:3px solid var(--orange);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;color:var(--orange);box-shadow:0 8px 24px rgba(232,82,10,.2)">
          <i class="<?= $icon ?>"></i>
        </div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--orange);margin-bottom:6px">Step <?= $i+1 ?></div>
        <h3 style="font-weight:700;margin-bottom:8px"><?= $title ?></h3>
        <p style="font-size:13px;color:var(--gray-500)"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- CTA -->
<div class="cta-section">
  <h2>Ready to Book Your Journey?</h2>
  <p>Join thousands of passengers who travel safely with KTSTA every day</p>
  <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/pages/register.php" class="btn btn-lg" style="background:white;color:var(--orange)"><i class="fas fa-user-plus"></i> Create Account</a>
    <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-lg btn-outline" style="border-color:white;color:white"><i class="fas fa-search"></i> Search Routes</a>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Animated counters
function animateCount(el, target) {
  let cur = 0; const step = Math.ceil(target/60);
  const t = setInterval(()=>{ cur=Math.min(cur+step,target); el.textContent=cur; if(cur>=target) clearInterval(t); }, 20);
}
window.addEventListener('load', ()=>{
  animateCount(document.getElementById('routeCount'), <?= $stats['routes'] ?>);
  animateCount(document.getElementById('tripCount'), <?= $stats['trips'] ?>);
  animateCount(document.getElementById('busCount'), <?= $stats['buses'] ?>);
  animateCount(document.getElementById('passCount'), <?= $stats['passengers'] ?>);
});
</script>
