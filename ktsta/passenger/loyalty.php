<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('passenger');
$pageTitle = 'Loyalty Rewards';
$user = currentUser();
$db = getDB();
$uid = (int)$user['id'];

// Calculate points from bookings (10 points per ₦1000 spent)
$totalSpent = (float)$db->query("SELECT COALESCE(SUM(fare),0) s FROM bookings WHERE passenger_id=$uid AND payment_status='paid'")->fetch_assoc()['s'];
$totalPoints = (int)($totalSpent / 100); // 1 point per ₦100

$tier = 'Bronze';
$tierColor = '#CD7F32';
$tierNext = 500;
$tierBenefits = ['Priority boarding','₦50 booking fee waiver'];

if ($totalPoints >= 5000) {
    $tier = 'Platinum'; $tierColor = '#E5E4E2'; $tierNext = 0;
    $tierBenefits = ['Free seat upgrade','10% fare discount','Priority boarding','Free cancellation','Dedicated support'];
} elseif ($totalPoints >= 2000) {
    $tier = 'Gold'; $tierColor = '#FFD700'; $tierNext = 5000 - $totalPoints;
    $tierBenefits = ['5% fare discount','Priority boarding','Free cancellation','Free seat upgrade'];
} elseif ($totalPoints >= 500) {
    $tier = 'Silver'; $tierColor = '#C0C0C0'; $tierNext = 2000 - $totalPoints;
    $tierBenefits = ['2% fare discount','Priority boarding','Advance booking'];
} else {
    $tierNext = 500 - $totalPoints;
}

$tierProgress = $tierNext > 0 ? min(100, ($totalPoints / ($totalPoints + $tierNext)) * 100) : 100;

// Points history
$pointsHistory = $db->query("SELECT b.booking_ref, b.created_at, b.fare, r.origin, r.destination 
  FROM bookings b JOIN trips t ON b.trip_id=t.id JOIN routes r ON t.route_id=r.id 
  WHERE b.passenger_id=$uid AND b.payment_status='paid' ORDER BY b.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Rewards catalog
$rewards = [
  ['₦500 Voucher', 500, 'fas fa-tag', '#16A34A', 'Redeem for ₦500 off your next booking'],
  ['₦1000 Voucher', 1000, 'fas fa-ticket-alt', 'var(--orange)', 'Redeem for ₦1000 off any booking'],
  ['Free Seat Upgrade', 300, 'fas fa-chair', 'var(--blue)', 'Upgrade to a better seat on your next trip'],
  ['Priority Boarding', 150, 'fas fa-running', '#8B5CF6', 'Board first on your next trip'],
  ['Free Booking', 2500, 'fas fa-bus', '#DC2626', 'Get a free booking up to ₦3000'],
  ['Charter Discount', 1500, 'fas fa-star', '#D97706', '15% off your next charter booking'],
];

include '../includes/header.php';
?>
<style>
.tier-card { border-radius:24px; padding:32px; color:white; position:relative; overflow:hidden; }
.tier-badge { display:inline-flex; align-items:center; gap:8px; padding:6px 16px; border-radius:20px; font-size:14px; font-weight:800; text-transform:uppercase; letter-spacing:1px; }
.reward-card { background:white; border:2px solid var(--gray-200); border-radius:16px; padding:20px; transition:all .3s; cursor:pointer; }
.reward-card:hover { border-color:var(--orange); transform:translateY(-3px); box-shadow:var(--shadow); }
.points-bar { height:10px; background:var(--gray-200); border-radius:5px; overflow:hidden; }
.points-fill { height:100%; border-radius:5px; transition:width 1s ease; }
</style>

<div class="app-layout">
  <aside class="sidebar">
    <div style="padding:16px 12px;border-bottom:1px solid var(--gray-200);margin-bottom:16px">
      <div style="font-weight:700"><?= htmlspecialchars($user['full_name']) ?></div>
      <div style="font-size:12px;color:var(--gray-400)">Passenger</div>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Navigation</div>
      <a class="sidebar-item" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a class="sidebar-item" href="tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a>
      <a class="sidebar-item" href="wallet.php"><i class="fas fa-wallet"></i> Wallet</a>
      <a class="sidebar-item active" href="loyalty.php"><i class="fas fa-star"></i> Rewards</a>
      <a class="sidebar-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
      <a class="sidebar-item" href="<?= BASE_URL ?>/pages/logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="page-header"><h1>Loyalty Rewards</h1><p>Earn points every time you travel with KTSTA</p></div>

    <!-- Tier Card -->
    <div class="tier-card" style="background:linear-gradient(135deg,<?= $tierColor == '#CD7F32' ? '#92400E,#B45309' : ($tierColor == '#C0C0C0' ? '#374151,#6B7280' : ($tierColor == '#FFD700' ? '#92400E,#D97706' : '#1F2937,#374151')) ?>);margin-bottom:24px">
      <div style="position:absolute;right:-30px;top:-30px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.07)"></div>
      <div style="position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center">
        <div>
          <div style="font-size:13px;opacity:.7;margin-bottom:8px">Your Tier</div>
          <div class="tier-badge" style="background:rgba(255,255,255,.2);color:white;margin-bottom:12px">
            <i class="fas fa-<?= $tier==='Platinum'?'gem':($tier==='Gold'?'crown':($tier==='Silver'?'medal':'award')) ?>"></i>
            <?= $tier ?>
          </div>
          <div style="font-size:44px;font-weight:800;font-family:var(--mono)"><?= number_format($totalPoints) ?></div>
          <div style="font-size:14px;opacity:.8;margin-top:2px">Loyalty Points</div>
          <?php if ($tierNext > 0): ?>
          <div style="margin-top:16px">
            <div style="font-size:12px;opacity:.7;margin-bottom:6px"><?= number_format($tierNext) ?> points to next tier</div>
            <div class="points-bar"><div class="points-fill" style="width:<?= $tierProgress ?>%;background:rgba(255,255,255,.7)"></div></div>
          </div>
          <?php else: ?>
          <div style="font-size:13px;opacity:.8;margin-top:12px"><i class="fas fa-crown"></i> Maximum tier reached!</div>
          <?php endif; ?>
        </div>
        <div>
          <div style="font-size:13px;opacity:.7;margin-bottom:12px">Your <?= $tier ?> Benefits</div>
          <?php foreach($tierBenefits as $b): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px">
            <i class="fas fa-check-circle" style="color:rgba(255,255,255,.8)"></i><?= $b ?>
          </div>
          <?php endforeach; ?>
          <div style="margin-top:16px;font-size:12px;opacity:.7">Earned from ₦<?= number_format($totalSpent) ?> total spend</div>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:24px">
      <div class="stat-card orange"><div class="stat-value"><?= number_format($totalPoints) ?></div><div class="stat-label">Total Points</div><div class="stat-icon"><i class="fas fa-star"></i></div></div>
      <div class="stat-card blue"><div class="stat-value"><?= number_format($totalPoints) ?></div><div class="stat-label">Available to Redeem</div><div class="stat-icon"><i class="fas fa-gift"></i></div></div>
      <div class="stat-card green"><div class="stat-value"><?= $tier ?></div><div class="stat-label">Current Tier</div><div class="stat-icon"><i class="fas fa-medal"></i></div></div>
    </div>

    <!-- Rewards Catalog -->
    <div class="card" style="margin-bottom:24px">
      <div class="card-title">Rewards Catalog</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">
        <?php foreach($rewards as [$name,$pts,$icon,$color,$desc]): ?>
        <div class="reward-card" onclick="redeemReward('<?= $name ?>', <?= $pts ?>, <?= $totalPoints ?>)">
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
            <div style="width:48px;height:48px;border-radius:14px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;font-size:20px;color:<?= $color ?>"><i class="<?= $icon ?>"></i></div>
            <?php if ($totalPoints >= $pts): ?>
            <span class="badge badge-success">Available</span>
            <?php else: ?>
            <span class="badge badge-gray">Need <?= number_format($pts-$totalPoints) ?> more</span>
            <?php endif; ?>
          </div>
          <div style="font-weight:700;font-size:14px;margin-bottom:4px"><?= $name ?></div>
          <div style="font-size:12px;color:var(--gray-400);margin-bottom:10px"><?= $desc ?></div>
          <div style="display:flex;align-items:center;gap:6px">
            <i class="fas fa-star" style="color:var(--orange);font-size:12px"></i>
            <span style="font-weight:800;font-family:var(--mono);color:var(--orange)"><?= number_format($pts) ?> pts</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- How to Earn -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div class="card">
        <div class="card-title">How to Earn Points</div>
        <?php foreach([
          ['fas fa-ticket-alt','1 point per ₦100 spent','Book any trip and earn automatically'],
          ['fas fa-star','50 bonus points','Complete your first booking'],
          ['fas fa-user-plus','100 points','Refer a friend who books a trip'],
          ['fas fa-comment-alt','20 points','Leave a trip review'],
          ['fas fa-birthday-cake','200 points','Birthday bonus (once a year)'],
        ] as [$icon,$title,$desc]): ?>
        <div style="display:flex;gap:12px;align-items:start;margin-bottom:12px">
          <div style="width:36px;height:36px;border-radius:10px;background:#FFF0E8;color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0"><i class="<?= $icon ?>"></i></div>
          <div><div style="font-weight:600;font-size:13px"><?= $title ?></div><div style="font-size:12px;color:var(--gray-400)"><?= $desc ?></div></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="card">
        <div class="card-title">Points History</div>
        <?php if(empty($pointsHistory)): ?>
        <div style="text-align:center;padding:30px;color:var(--gray-400)">No trips yet. Start traveling to earn points!</div>
        <?php else: ?>
        <?php foreach($pointsHistory as $h):
          $pts = (int)($h['fare']/100);
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--gray-100)">
          <div>
            <div style="font-size:13px;font-weight:600"><?= $h['origin'] ?> → <?= $h['destination'] ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= date('d M Y',strtotime($h['created_at'])) ?> &bull; <?= $h['booking_ref'] ?></div>
          </div>
          <div style="font-weight:800;color:var(--success);font-family:var(--mono)">+<?= $pts ?> pts</div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Redeem Modal -->
<div class="modal-overlay" id="redeemModal">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header"><h3><i class="fas fa-gift" style="color:var(--orange)"></i> Redeem Reward</h3><button class="modal-close" onclick="closeModal('redeemModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" id="redeemContent"></div>
  </div>
</div>

<script>
function redeemReward(name, pts, available) {
  const canRedeem = available >= pts;
  document.getElementById('redeemContent').innerHTML = canRedeem ?
    `<div style="text-align:center;padding:16px">
      <i class="fas fa-gift" style="font-size:40px;color:var(--orange);display:block;margin-bottom:12px"></i>
      <div style="font-size:20px;font-weight:800;margin-bottom:8px">${name}</div>
      <div style="font-size:14px;color:var(--gray-500);margin-bottom:16px">This will use <strong>${pts.toLocaleString()} points</strong> from your balance.</div>
      <div style="font-size:13px;color:var(--gray-400);margin-bottom:20px">Remaining: ${(available-pts).toLocaleString()} points</div>
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="showToast('Reward redeemed! A voucher code will be sent to your phone.','success');closeModal('redeemModal')">
        <i class="fas fa-check"></i> Confirm Redemption
      </button>
    </div>` :
    `<div style="text-align:center;padding:16px">
      <i class="fas fa-lock" style="font-size:40px;color:var(--gray-300);display:block;margin-bottom:12px"></i>
      <div style="font-size:16px;font-weight:700;margin-bottom:8px">Not enough points</div>
      <div style="font-size:13px;color:var(--gray-500)">You need <strong>${(pts-available).toLocaleString()} more points</strong> to redeem this reward.</div>
      <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-primary" style="margin-top:16px;justify-content:center"><i class="fas fa-bus"></i> Book a Trip to Earn Points</a>
    </div>`;
  openModal('redeemModal');
}
</script>
<?php include '../includes/footer.php'; ?>
