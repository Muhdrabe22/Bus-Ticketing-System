<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Trip Reviews';
$user = isLoggedIn() ? currentUser() : null;
$db = getDB();
$msg = '';

// Submit review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $tripId  = (int)$_POST['trip_id'];
    $bookId  = (int)($_POST['booking_id'] ?? 0);
    $overall = (int)$_POST['overall_rating'];
    $driver  = (int)$_POST['driver_rating'];
    $comfort = (int)$_POST['comfort_rating'];
    $punct   = (int)$_POST['punctuality_rating'];
    $comment = clean($_POST['comment'] ?? '');
    $uid     = (int)$user['id'];

    if ($overall >= 1 && $overall <= 5) {
        // Check not already reviewed
        $existing = $db->query("SELECT id FROM reviews WHERE user_id=$uid AND trip_id=$tripId");
        if ($existing->num_rows === 0) {
            $db->query("INSERT INTO reviews (user_id,trip_id,booking_id,overall_rating,driver_rating,comfort_rating,punctuality_rating,comment)
                VALUES ($uid,$tripId," . ($bookId?:NULL) . ",$overall,$driver,$comfort,$punct,'$comment')");
            $msg = 'success:Thank you for your review!';
        } else {
            $msg = 'error:You have already reviewed this trip.';
        }
    }
}

// Fetch reviews with trip info
$reviews = $db->query("SELECT rv.*, u.full_name, r.origin, r.destination, t.departure_datetime, b.bus_number
  FROM reviews rv 
  JOIN users u ON rv.user_id=u.id 
  JOIN trips t ON rv.trip_id=t.id
  JOIN routes r ON t.route_id=r.id
  JOIN buses b ON t.bus_id=b.id
  WHERE rv.is_approved=1 ORDER BY rv.created_at DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);

$avgRating = $db->query("SELECT AVG(overall_rating) a, COUNT(*) c FROM reviews WHERE is_approved=1")->fetch_assoc();
$ratingDist = [];
for ($i = 5; $i >= 1; $i--) {
    $cnt = $db->query("SELECT COUNT(*) c FROM reviews WHERE overall_rating=$i AND is_approved=1")->fetch_assoc()['c'];
    $ratingDist[$i] = $cnt;
}

// Trips user can review (completed trips they were on)
$reviewableTrips = [];
if ($user) {
    $uid = (int)$user['id'];
    $reviewableTrips = $db->query("SELECT b.id as booking_id, t.id as trip_id, r.origin, r.destination, t.departure_datetime
      FROM bookings b JOIN trips t ON b.trip_id=t.id JOIN routes r ON t.route_id=r.id
      WHERE b.passenger_id=$uid AND b.booking_status IN ('used','confirmed') AND t.departure_datetime < NOW()
      AND t.id NOT IN (SELECT trip_id FROM reviews WHERE user_id=$uid)
      ORDER BY t.departure_datetime DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
}

include '../includes/header.php';
?>
<style>
.reviews-page { max-width:1100px; margin:0 auto; padding:40px 20px; }
.star-input { display:flex; flex-direction:row-reverse; gap:4px; }
.star-input input { display:none; }
.star-input label { font-size:28px; color:#D1D5DB; cursor:pointer; transition:color .15s; }
.star-input label:hover, .star-input label:hover ~ label, .star-input input:checked ~ label { color:#F59E0B; }
.stars-display { color:#F59E0B; letter-spacing:2px; }
.review-card { background:white; border-radius:16px; border:1px solid var(--gray-200); padding:20px; margin-bottom:14px; transition:box-shadow .2s; }
.review-card:hover { box-shadow:var(--shadow); }
.rating-bar { height:8px; background:var(--gray-200); border-radius:4px; overflow:hidden; display:inline-block; width:160px; vertical-align:middle; }
.rating-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#F59E0B,#FCD34D); }
</style>

<div class="reviews-page">
  <div class="page-header" style="text-align:center"><h1>Passenger Reviews</h1><p>Real feedback from KTSTA travelers</p></div>

  <?php if ($msg): ?>
  <?php [$t,$m]=explode(':',$msg,2); ?>
  <div style="background:<?= $t==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $t==='success'?'#86EFAC':'#FCA5A5' ?>;border-radius:10px;padding:12px 16px;margin-bottom:20px;color:<?= $t==='success'?'var(--success)':'var(--danger)' ?>;display:flex;align-items:center;gap:8px">
    <i class="fas fa-<?= $t==='success'?'check':'exclamation' ?>-circle"></i><?= $m ?>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 2fr;gap:28px">

    <!-- Rating Summary + Write Review -->
    <div>
      <!-- Summary -->
      <div class="card" style="margin-bottom:16px;text-align:center">
        <div style="font-size:64px;font-weight:800;color:#1A202C;font-family:var(--mono)"><?= number_format($avgRating['a'],1) ?></div>
        <div class="stars-display" style="font-size:24px">
          <?php for($i=1;$i<=5;$i++) echo $i<=round($avgRating['a'])?'★':'☆'; ?>
        </div>
        <div style="font-size:13px;color:var(--gray-400);margin-top:4px"><?= $avgRating['c'] ?> reviews</div>
        <div style="margin-top:16px">
          <?php foreach($ratingDist as $stars=>$cnt): ?>
          <?php $pct = $avgRating['c'] > 0 ? ($cnt/$avgRating['c'])*100 : 0; ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:12px">
            <span style="width:12px;text-align:right;color:var(--gray-400)"><?= $stars ?></span>
            <i class="fas fa-star" style="color:#F59E0B;font-size:10px"></i>
            <div class="rating-bar"><div class="rating-fill" style="width:<?= $pct ?>%"></div></div>
            <span style="color:var(--gray-400);width:24px"><?= $cnt ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Write Review -->
      <?php if (!empty($reviewableTrips)): ?>
      <div class="card">
        <div class="card-title"><i class="fas fa-star" style="color:#F59E0B"></i> Leave a Review</div>
        <form method="POST">
          <div class="form-group">
            <label class="form-label">Select Trip</label>
            <select name="trip_id" id="reviewTripSel" class="form-control" onchange="setBookingId(this)">
              <?php foreach($reviewableTrips as $rt): ?>
              <option value="<?= $rt['trip_id'] ?>" data-bid="<?= $rt['booking_id'] ?>"><?= $rt['origin'] ?> → <?= $rt['destination'] ?> (<?= date('d M Y',strtotime($rt['departure_datetime'])) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <input type="hidden" name="booking_id" id="reviewBid" value="<?= $reviewableTrips[0]['booking_id'] ?? '' ?>">

          <?php foreach([['overall_rating','Overall Experience'],['driver_rating','Driver'],['comfort_rating','Comfort'],['punctuality_rating','Punctuality']] as [$fname,$label]): ?>
          <div class="form-group">
            <label class="form-label"><?= $label ?></label>
            <div class="star-input">
              <?php for($s=5;$s>=1;$s--): ?>
              <input type="radio" id="<?= $fname.$s ?>" name="<?= $fname ?>" value="<?= $s ?>" <?= $s===4?'checked':'' ?>>
              <label for="<?= $fname.$s ?>">★</label>
              <?php endfor; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <div class="form-group">
            <label class="form-label">Comment (Optional)</label>
            <textarea name="comment" class="form-control" rows="3" placeholder="Share your experience..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-paper-plane"></i> Submit Review</button>
        </form>
      </div>
      <?php elseif ($user): ?>
      <div class="card" style="text-align:center;padding:32px">
        <i class="fas fa-check-circle" style="font-size:32px;color:var(--success);display:block;margin-bottom:10px"></i>
        <div style="font-weight:700">All trips reviewed!</div>
        <div style="font-size:13px;color:var(--gray-400);margin-top:4px">Book more trips to leave reviews</div>
      </div>
      <?php else: ?>
      <div class="card" style="text-align:center;padding:32px">
        <a href="<?= BASE_URL ?>/pages/login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Login to Review</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Reviews List -->
    <div>
      <?php foreach($reviews as $rv): ?>
      <div class="review-card">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:10px">
          <div style="display:flex;gap:10px;align-items:center">
            <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--orange),var(--orange-dark));color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px"><?= strtoupper(substr($rv['full_name'],0,1)) ?></div>
            <div>
              <div style="font-weight:700"><?= htmlspecialchars($rv['full_name']) ?></div>
              <div style="font-size:12px;color:var(--gray-400)"><?= date('d M Y',strtotime($rv['created_at'])) ?></div>
            </div>
          </div>
          <div style="text-align:right">
            <div class="stars-display"><?php for($i=1;$i<=5;$i++) echo $i<=$rv['overall_rating']?'★':'☆'; ?></div>
            <div style="font-size:12px;color:var(--gray-400)"><?= $rv['origin'] ?> → <?= $rv['destination'] ?></div>
          </div>
        </div>

        <?php if ($rv['comment']): ?>
        <p style="font-size:14px;color:var(--gray-600);line-height:1.7;margin-bottom:12px">"<?= htmlspecialchars($rv['comment']) ?>"</p>
        <?php endif; ?>

        <div style="display:flex;gap:16px;flex-wrap:wrap">
          <?php foreach([['Driver',$rv['driver_rating']],['Comfort',$rv['comfort_rating']],['Punctuality',$rv['punctuality_rating']]] as [$lbl,$r]): ?>
          <?php if ($r): ?>
          <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-500)">
            <span style="font-weight:600"><?= $lbl ?>:</span>
            <span style="color:#F59E0B"><?php for($i=1;$i<=5;$i++) echo $i<=$r?'★':'☆'; ?></span>
          </div>
          <?php endif; ?>
          <?php endforeach; ?>
          <div style="margin-left:auto;font-size:11px;color:var(--gray-300)"><?= $rv['bus_number'] ?> &bull; <?= date('d M Y',strtotime($rv['departure_datetime'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($reviews)): ?>
      <div style="text-align:center;padding:60px;background:white;border-radius:20px;border:1px solid var(--gray-200);color:var(--gray-400)">
        <i class="fas fa-star" style="font-size:40px;display:block;margin-bottom:12px;opacity:.3"></i>
        No reviews yet. Be the first to review!
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function setBookingId(sel) {
  document.getElementById('reviewBid').value = sel.selectedOptions[0]?.dataset.bid || '';
}
</script>
<?php include '../includes/footer.php'; ?>
