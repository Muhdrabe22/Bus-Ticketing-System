<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Routes';
$db = getDB();
$routes = $db->query("SELECT * FROM routes WHERE is_active=1 ORDER BY origin, destination")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<div style="max-width:1200px;margin:0 auto;padding:40px 20px">
  <div class="page-header">
    <h1>All Routes</h1>
    <p>Browse all active KTSTA bus routes and fares</p>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px">
    <?php foreach($routes as $r): ?>
    <div style="background:white;border-radius:20px;border:2px solid var(--gray-200);overflow:hidden;transition:all .3s;cursor:pointer" onmouseover="this.style.borderColor='var(--orange)';this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 40px rgba(232,82,10,.12)'" onmouseout="this.style.borderColor='var(--gray-200)';this.style.transform='none';this.style.boxShadow='none'">
      <div style="background:linear-gradient(135deg,var(--blue-dark),var(--blue));padding:20px;color:white">
        <div style="font-size:12px;opacity:.7;font-weight:600;letter-spacing:.5px;text-transform:uppercase"><?= $r['route_code'] ?></div>
        <div style="font-size:22px;font-weight:800;margin-top:4px"><?= htmlspecialchars($r['origin']) ?> → <?= htmlspecialchars($r['destination']) ?></div>
      </div>
      <div style="padding:16px 20px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px;font-size:13px">
          <div><div style="color:var(--gray-400)">Distance</div><div style="font-weight:700"><?= $r['distance_km'] ?> km</div></div>
          <div><div style="color:var(--gray-400)">Duration</div><div style="font-weight:700"><?= round($r['duration_minutes']/60,1) ?> hrs</div></div>
          <div><div style="color:var(--gray-400)">Fare</div><div style="font-weight:700;color:var(--orange)"><?= formatMoney($r['base_fare']) ?></div></div>
        </div>
        <a href="<?= BASE_URL ?>/pages/search.php?from=<?= urlencode($r['origin']) ?>&to=<?= urlencode($r['destination']) ?>" class="btn btn-primary btn-sm" style="width:100%;justify-content:center"><i class="fas fa-search"></i> Find Trips</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
