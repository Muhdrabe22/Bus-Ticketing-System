<?php
// pages/contact.php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Contact Us';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = clean($_POST['name'] ?? '');
    $email   = clean($_POST['email'] ?? '');
    $subject = clean($_POST['subject'] ?? '');
    $message = clean($_POST['message'] ?? '');
    if ($name && $email && $message) {
        $db = getDB();
        $uid = isLoggedIn() ? (int)$_SESSION['user_id'] : 'NULL';
        $db->query("INSERT INTO feedback (user_id,type,subject,message) VALUES ($uid,'suggestion','$subject','Contact: $name ($email) - $message')");
        $msg = 'success:Thank you! Your message has been received. We will respond within 24 hours.';
    } else {
        $msg = 'error:Please fill in all required fields.';
    }
}
include '../includes/header.php';
?>
<div style="max-width:1000px;margin:0 auto;padding:40px 20px">
  <div style="text-align:center;margin-bottom:40px">
    <h1 style="font-size:32px;font-weight:800">Contact Us</h1>
    <p style="color:var(--gray-500)">We're here to help. Get in touch with the KTSTA team.</p>
  </div>
  <?php if($msg):[$t,$m]=explode(':',$msg,2); ?>
  <div style="background:<?= $t==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $t==='success'?'#86EFAC':'#FCA5A5' ?>;border-radius:12px;padding:14px 20px;margin-bottom:24px;color:<?= $t==='success'?'var(--success)':'var(--danger)' ?>;display:flex;align-items:center;gap:10px">
    <i class="fas fa-<?= $t==='success'?'check':'exclamation' ?>-circle"></i><?= $m ?>
  </div>
  <?php endif; ?>
  <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:32px">
    <div>
      <?php foreach([['fas fa-map-marker-alt','var(--orange)','Address','KTSTA Headquarters\nModern Market Road, Katsina'],['fas fa-phone','var(--blue)','Phone','0800-KTSTA-01\n+234 (65) 431 000'],['fas fa-envelope','var(--success)','Email','info@ktsta.gov.ng\nsupport@ktsta.gov.ng'],['fas fa-clock','var(--warning)','Hours','Mon–Fri: 6:00 AM – 10:00 PM\nWeekends: 6:00 AM – 8:00 PM']] as [$icon,$color,$lbl,$val]): ?>
      <div class="card" style="margin-bottom:14px;display:flex;gap:14px;align-items:start">
        <div style="width:44px;height:44px;border-radius:12px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;font-size:18px;color:<?= $color ?>;flex-shrink:0"><i class="<?= $icon ?>"></i></div>
        <div><div style="font-weight:700;font-size:14px;margin-bottom:4px"><?= $lbl ?></div><div style="font-size:13px;color:var(--gray-500);white-space:pre-line"><?= $val ?></div></div>
      </div>
      <?php endforeach; ?>
      <div class="card" style="background:linear-gradient(135deg,var(--orange),var(--orange-dark));border:none;color:white;text-align:center">
        <div style="font-size:18px;font-weight:800;margin-bottom:8px">Emergency Line</div>
        <div style="font-size:13px;opacity:.85;margin-bottom:12px">For urgent matters during travel</div>
        <div style="font-size:24px;font-weight:800;font-family:var(--mono)">0800-KTSTA-01</div>
        <div style="font-size:12px;opacity:.7;margin-top:4px">Available 24/7</div>
      </div>
    </div>
    <div class="card">
      <div class="card-title"><i class="fas fa-paper-plane" style="color:var(--orange)"></i> Send Us a Message</div>
      <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group"><label class="form-label">Your Name *</label><input type="text" name="name" class="form-control" required value="<?= isLoggedIn() ? htmlspecialchars(currentUser()['full_name']) : '' ?>"></div>
          <div class="form-group"><label class="form-label">Email Address *</label><input type="email" name="email" class="form-control" required value="<?= isLoggedIn() ? htmlspecialchars(currentUser()['email']) : '' ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Subject</label><select name="subject" class="form-control"><option>General Inquiry</option><option>Booking Issue</option><option>Complaint</option><option>Lost Item</option><option>Charter Request</option><option>Other</option></select></div>
        <div class="form-group"><label class="form-label">Message *</label><textarea name="message" class="form-control" rows="5" placeholder="How can we help you?" required></textarea></div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px"><i class="fas fa-paper-plane"></i> Send Message</button>
      </form>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
