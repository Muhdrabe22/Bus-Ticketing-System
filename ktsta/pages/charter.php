<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Charter a Bus';
$user = isLoggedIn() ? currentUser() : null;
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ref    = generateRef('CHT');
    $uid    = $user ? (int)$user['id'] : 'NULL';
    $name   = clean($_POST['contact_name'] ?? '');
    $phone  = clean($_POST['contact_phone'] ?? '');
    $email  = clean($_POST['contact_email'] ?? '');
    $event  = clean($_POST['event_type'] ?? '');
    $pickup = clean($_POST['pickup_location'] ?? '');
    $dest   = clean($_POST['destination'] ?? '');
    $date   = clean($_POST['travel_date'] ?? '');
    $ret    = clean($_POST['return_date'] ?? '');
    $pax    = (int)$_POST['num_passengers'];
    $btype  = clean($_POST['bus_type'] ?? 'minibus');
    $days   = (int)($_POST['duration_days'] ?? 1);
    $reqs   = clean($_POST['special_requirements'] ?? '');

    if ($name && $phone && $pickup && $dest && $date && $pax) {
        $db->query("INSERT INTO charter_requests
            (request_ref,user_id,contact_name,contact_phone,contact_email,event_type,pickup_location,destination,travel_date,return_date,num_passengers,bus_type,duration_days,special_requirements)
            VALUES ('$ref',$uid,'$name','$phone','$email','$event','$pickup','$dest','$date'," . ($ret?"'$ret'":'NULL') . ",$pax,'$btype',$days,'$reqs')");
        if ($user) addNotification($user['id'],'Charter Request Received',"Your charter request $ref has been received. We will contact you within 2 hours.",'booking');
        $msg = "success:Charter request <strong>$ref</strong> submitted! We'll contact you within 2 business hours with a quote.";
    } else {
        $msg = 'error:Please fill in all required fields.';
    }
}

include '../includes/header.php';
?>
<style>
.charter-page { max-width:1100px; margin:0 auto; padding:40px 20px; }
.charter-hero { background:linear-gradient(135deg,var(--blue-dark),var(--blue)); border-radius:24px; padding:48px; color:white; margin-bottom:32px; position:relative; overflow:hidden; }
.charter-hero::after { content:''; position:absolute; right:-60px; top:-60px; width:280px; height:280px; border-radius:50%; background:rgba(255,255,255,.05); }
.charter-hero::before { content:''; position:absolute; left:-40px; bottom:-40px; width:200px; height:200px; border-radius:50%; background:rgba(232,82,10,.1); }
.bus-type-select { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-top:8px; }
.bus-option input { display:none; }
.bus-option-card { border:2px solid var(--gray-200); border-radius:14px; padding:16px; cursor:pointer; text-align:center; transition:all .2s; background:white; }
.bus-option-card:hover { border-color:var(--orange); }
.bus-option input:checked + .bus-option-card { border-color:var(--orange); background:#FFF0E8; }
.bus-option-card .icon { font-size:28px; color:var(--gray-400); margin-bottom:8px; }
.bus-option input:checked + .bus-option-card .icon { color:var(--orange); }
.benefits-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:32px; }
.benefit-card { background:white; border-radius:16px; padding:20px; text-align:center; border:1px solid var(--gray-200); }
.benefit-card i { font-size:24px; color:var(--orange); display:block; margin-bottom:8px; }
</style>

<div class="charter-page">
  <!-- Hero -->
  <div class="charter-hero">
    <div style="position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center">
      <div>
        <div style="font-size:13px;opacity:.7;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px"><i class="fas fa-bus"></i> Charter Services</div>
        <h1 style="font-size:38px;font-weight:800;line-height:1.2;margin-bottom:16px">Charter a KTSTA Bus<br>for Your Event</h1>
        <p style="font-size:15px;opacity:.8;line-height:1.7">Perfect for weddings, corporate events, school trips, pilgrimages, and group travel. Our professional drivers and modern fleet available 24/7.</p>
        <div style="display:flex;gap:16px;margin-top:20px;flex-wrap:wrap">
          <?php foreach(['Weddings','Corporate Events','School Trips','Hajj/Umrah','Conferences','Tourism'] as $tag): ?>
          <span style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:20px;padding:5px 14px;font-size:12px"><?= $tag ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <?php foreach([['fas fa-users','Any Group Size','14–100 passengers'],['fas fa-clock','24/7 Available','Book anytime'],['fas fa-shield-alt','Insured Fleet','Full coverage'],['fas fa-headset','Dedicated Support','Personal coordinator']] as [$icon,$title,$sub]): ?>
        <div style="background:rgba(255,255,255,.1);border-radius:12px;padding:16px">
          <i class="<?= $icon ?>" style="font-size:20px;color:var(--orange);margin-bottom:8px;display:block"></i>
          <div style="font-weight:700;font-size:13px"><?= $title ?></div>
          <div style="font-size:11px;opacity:.7;margin-top:2px"><?= $sub ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Alert -->
  <?php if ($msg): ?>
  <?php [$type,$text] = explode(':',$msg,2); ?>
  <div style="background:<?= $type==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $type==='success'?'#86EFAC':'#FCA5A5' ?>;border-radius:12px;padding:16px;margin-bottom:24px;display:flex;align-items:center;gap:10px;color:<?= $type==='success'?'var(--success)':'var(--danger)' ?>">
    <i class="fas fa-<?= $type==='success'?'check':'exclamation' ?>-circle" style="font-size:20px"></i><?= $text ?>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
    <!-- Form -->
    <div class="card">
      <div class="card-title"><i class="fas fa-clipboard-list" style="color:var(--orange)"></i> Charter Request Form</div>
      <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label class="form-label">Contact Name <span style="color:var(--danger)">*</span></label>
            <input type="text" name="contact_name" class="form-control" placeholder="Your full name" required value="<?= $user ? htmlspecialchars($user['full_name']) : '' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number <span style="color:var(--danger)">*</span></label>
            <input type="tel" name="contact_phone" class="form-control" placeholder="08012345678" required value="<?= $user ? htmlspecialchars($user['phone']) : '' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="contact_email" class="form-control" placeholder="optional" value="<?= $user ? htmlspecialchars($user['email']) : '' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Event Type</label>
            <select name="event_type" class="form-control">
              <option value="">Select event type...</option>
              <?php foreach(['Wedding','Corporate Event','School Trip','Hajj/Umrah','Conference','Tourism','Funeral','Sports Event','Other'] as $e): ?>
              <option value="<?= $e ?>"><?= $e ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:span 2">
            <label class="form-label">Pickup Location <span style="color:var(--danger)">*</span></label>
            <input type="text" name="pickup_location" class="form-control" placeholder="e.g. KTSTA Terminal, Katsina" required>
          </div>
          <div class="form-group" style="grid-column:span 2">
            <label class="form-label">Destination <span style="color:var(--danger)">*</span></label>
            <input type="text" name="destination" class="form-control" placeholder="e.g. Kano Airport" required>
          </div>
          <div class="form-group">
            <label class="form-label">Travel Date <span style="color:var(--danger)">*</span></label>
            <input type="date" name="travel_date" class="form-control" min="<?= date('Y-m-d',strtotime('+1 day')) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Return Date (if round trip)</label>
            <input type="date" name="return_date" class="form-control" min="<?= date('Y-m-d',strtotime('+1 day')) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Number of Passengers <span style="color:var(--danger)">*</span></label>
            <input type="number" name="num_passengers" class="form-control" min="1" max="200" placeholder="e.g. 25" required>
          </div>
          <div class="form-group">
            <label class="form-label">Duration (Days)</label>
            <input type="number" name="duration_days" class="form-control" min="1" max="30" value="1">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Preferred Bus Type</label>
          <div class="bus-type-select">
            <?php $busTypes=[['minibus','fas fa-bus','Minibus','14 seats — Most affordable'],['coaster','fas fa-bus-alt','Coaster','30 seats — Best value'],['luxury','fas fa-star','Luxury','Comfortable & Premium']]; ?>
            <?php foreach($busTypes as [$val,$icon,$name,$desc]): ?>
            <label class="bus-option">
              <input type="radio" name="bus_type" value="<?= $val ?>" <?= $val==='minibus'?'checked':'' ?>>
              <div class="bus-option-card">
                <div class="icon"><i class="<?= $icon ?>"></i></div>
                <div style="font-weight:700;font-size:13px"><?= $name ?></div>
                <div style="font-size:11px;color:var(--gray-400);margin-top:2px"><?= $desc ?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Special Requirements</label>
          <textarea name="special_requirements" class="form-control" rows="3" placeholder="e.g. Air conditioning, PA system, wheelchair access, specific pickup time..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:15px">
          <i class="fas fa-paper-plane"></i> Submit Charter Request
        </button>
        <div style="font-size:12px;color:var(--gray-400);text-align:center;margin-top:10px">We will respond with a quote within 2 business hours</div>
      </form>
    </div>

    <!-- Sidebar info -->
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title"><i class="fas fa-info-circle" style="color:var(--blue)"></i> How it Works</div>
        <?php foreach([['1','Submit Request','Fill this form with your event details'],['2','Receive Quote','Our team contacts you with pricing within 2hrs'],['3','Confirm Booking','Pay a deposit to secure your booking'],['4','Travel Day','Bus arrives ready at your pickup location']] as [$num,$title,$desc]): ?>
        <div style="display:flex;gap:12px;margin-bottom:14px;align-items:start">
          <div style="width:28px;height:28px;border-radius:50%;background:var(--orange);color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0"><?= $num ?></div>
          <div><div style="font-weight:700;font-size:13px"><?= $title ?></div><div style="font-size:12px;color:var(--gray-400)"><?= $desc ?></div></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title"><i class="fas fa-tags" style="color:var(--orange)"></i> Pricing Guide</div>
        <?php foreach([['Minibus (14 seats)','From ₦30,000/day'],['Coaster (30 seats)','From ₦60,000/day'],['Luxury Bus','From ₦100,000/day']] as [$type,$price]): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gray-100);font-size:13px">
          <span style="color:var(--gray-600)"><?= $type ?></span>
          <strong style="color:var(--orange)"><?= $price ?></strong>
        </div>
        <?php endforeach; ?>
        <div style="font-size:11px;color:var(--gray-400);margin-top:8px">*Prices vary by distance, duration & availability</div>
      </div>
      <div class="card" style="background:linear-gradient(135deg,var(--orange),var(--orange-dark));border:none;color:white">
        <div style="font-weight:800;font-size:16px;margin-bottom:8px"><i class="fas fa-phone"></i> Talk to Us Directly</div>
        <div style="font-size:14px;opacity:.9;margin-bottom:12px">For urgent charter needs, call our operations team</div>
        <a href="tel:+2340800KTSTA01" style="display:block;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:10px;padding:12px;text-align:center;font-weight:700;font-size:15px;color:white">0800-KTSTA-01</a>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
