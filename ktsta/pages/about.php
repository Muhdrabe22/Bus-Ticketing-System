<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'About KTSTA';
include '../includes/header.php';
?>
<style>
.about-hero { background:linear-gradient(160deg,var(--blue-dark),var(--blue)); padding:80px 2rem; color:white; text-align:center; }
.about-section { max-width:1100px; margin:0 auto; padding:60px 2rem; }
.team-card { background:white; border-radius:20px; padding:24px; text-align:center; border:1px solid var(--gray-200); }
</style>
<div class="about-hero">
  <div style="font-size:14px;opacity:.7;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px">About Us</div>
  <h1 style="font-size:44px;font-weight:800;margin-bottom:16px">Katsina State Transport Authority</h1>
  <p style="font-size:16px;opacity:.8;max-width:600px;margin:0 auto;line-height:1.7">Providing safe, reliable, and affordable public transportation services to the people of Katsina State since 1987.</p>
</div>

<div class="about-section">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;margin-bottom:60px">
    <div>
      <h2 style="font-size:32px;font-weight:800;margin-bottom:16px">Our Mission</h2>
      <p style="color:var(--gray-500);line-height:1.8;margin-bottom:16px">KTSTA is dedicated to providing world-class public transportation services to residents and visitors of Katsina State. We connect communities, enable commerce, and empower people to travel with confidence.</p>
      <p style="color:var(--gray-500);line-height:1.8">Our modern fleet of Toyota HiAce minibuses and Coaster buses serve all major routes across the state, with trained and licensed drivers ensuring passenger safety at all times.</p>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <?php foreach([['10+','Routes','fas fa-route'],['50+','Buses','fas fa-bus'],['1000+','Daily Passengers','fas fa-users'],['Since 1987','Operating','fas fa-calendar']] as [$val,$lbl,$icon]): ?>
      <div class="card" style="text-align:center;padding:24px">
        <i class="<?= $icon ?>" style="font-size:28px;color:var(--orange);margin-bottom:8px;display:block"></i>
        <div style="font-size:28px;font-weight:800;color:var(--gray-900)"><?= $val ?></div>
        <div style="font-size:12px;color:var(--gray-400)"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="background:linear-gradient(135deg,var(--orange),var(--orange-dark));border-radius:24px;padding:40px;color:white;text-align:center;margin-bottom:60px">
    <h2 style="font-size:28px;font-weight:800;margin-bottom:12px">KTSTA Sub-Stations</h2>
    <p style="opacity:.85;margin-bottom:24px">We operate sub-stations across Katsina State for your convenience</p>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px">
      <?php foreach(['Katsina HQ','Mashi','Daura','Jibia','Funtua','Zango','Dutsin-Ma','Malumfashi'] as $s): ?>
      <div style="background:rgba(255,255,255,.15);border-radius:12px;padding:12px;font-size:14px;font-weight:600"><?= $s ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="text-align:center">
    <h2 style="font-size:28px;font-weight:800;margin-bottom:8px">Contact Us</h2>
    <p style="color:var(--gray-500);margin-bottom:32px">We're here to help you</p>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:700px;margin:0 auto">
      <?php foreach([['fas fa-map-marker-alt','Address','KTSTA HQ, Katsina State'],['fas fa-phone','Phone','0800-KTSTA-01'],['fas fa-envelope','Email','info@ktsta.gov.ng']] as [$icon,$lbl,$val]): ?>
      <div class="card" style="text-align:center;padding:24px">
        <i class="<?= $icon ?>" style="font-size:24px;color:var(--orange);display:block;margin-bottom:8px"></i>
        <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.5px"><?= $lbl ?></div>
        <div style="font-size:14px;font-weight:600;margin-top:4px"><?= $val ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
