<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('passenger');
$pageTitle = 'Feedback';
$user = currentUser();
$db = getDB();
$uid = (int)$user['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = clean($_POST['type'] ?? 'complaint');
    $subject = clean($_POST['subject'] ?? '');
    $message = clean($_POST['message'] ?? '');
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($message) {
        $db->query("INSERT INTO feedback (user_id, booking_id, type, subject, message) VALUES ($uid, ".($bookingId?:NULL).", '$type', '$subject', '$message')");
        $msg = 'success:Your feedback has been submitted. We will respond within 24 hours.';
    }
}

$myFeedback = $db->query("SELECT * FROM feedback WHERE user_id=$uid ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$myBookings = $db->query("SELECT b.id, b.booking_ref, r.origin, r.destination FROM bookings b JOIN trips t ON b.trip_id=t.id JOIN routes r ON t.route_id=r.id WHERE b.passenger_id=$uid ORDER BY b.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div class="app-layout">
  <aside class="sidebar">
    <div style="padding:16px 12px;border-bottom:1px solid var(--gray-200);margin-bottom:16px"><div style="font-weight:700"><?= htmlspecialchars($user['full_name']) ?></div><div style="font-size:12px;color:var(--gray-400)">Passenger</div></div>
    <div class="sidebar-section">
      <a class="sidebar-item" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a class="sidebar-item" href="tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a>
      <a class="sidebar-item" href="wallet.php"><i class="fas fa-wallet"></i> Wallet</a>
      <a class="sidebar-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
      <a class="sidebar-item active" href="feedback.php"><i class="fas fa-comment-alt"></i> Feedback</a>
      <a class="sidebar-item" href="<?= BASE_URL ?>/pages/logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>
  <main class="main-content">
    <div class="page-header"><h1>Feedback & Complaints</h1><p>Help us improve our service</p></div>
    <?php if ($msg): ?>
    <?php [$type,$text] = explode(':',$msg,2); ?>
    <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:var(--success);display:flex;align-items:center;gap:8px"><i class="fas fa-check-circle"></i><?= htmlspecialchars($text) ?></div>
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <div class="card">
        <div class="card-title">Submit Feedback</div>
        <form method="POST">
          <div class="form-group"><label class="form-label">Type</label>
            <select name="type" class="form-control">
              <option value="complaint">Complaint</option>
              <option value="suggestion">Suggestion</option>
              <option value="compliment">Compliment</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Related Booking (Optional)</label>
            <select name="booking_id" class="form-control"><option value="">Not related to a booking</option>
            <?php foreach($myBookings as $b): ?><option value="<?= $b['id'] ?>"><?= $b['booking_ref'] ?> — <?= $b['origin'] ?> → <?= $b['destination'] ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Subject</label><input type="text" name="subject" class="form-control" placeholder="Brief subject"></div>
          <div class="form-group"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="5" placeholder="Describe your feedback in detail..." required></textarea></div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Feedback</button>
        </form>
      </div>
      <div class="card">
        <div class="card-title">My Feedback History</div>
        <?php if(empty($myFeedback)): ?><div style="text-align:center;padding:30px;color:var(--gray-400)">No feedback submitted yet</div><?php endif; ?>
        <?php foreach($myFeedback as $f): ?>
        <div style="border:1px solid var(--gray-200);border-radius:10px;padding:12px;margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;align-items:start">
            <span class="badge badge-<?= $f['type']==='complaint'?'danger':($f['type']==='compliment'?'success':'info') ?>"><?= $f['type'] ?></span>
            <span class="badge badge-<?= $f['status']==='resolved'?'success':'warning' ?>"><?= $f['status'] ?></span>
          </div>
          <div style="font-weight:600;font-size:14px;margin-top:8px"><?= htmlspecialchars($f['subject'] ?: 'No subject') ?></div>
          <div style="font-size:12px;color:var(--gray-400);margin-top:4px"><?= date('d M Y',strtotime($f['created_at'])) ?></div>
          <?php if ($f['response']): ?>
          <div style="background:#F0FDF4;border-radius:6px;padding:8px;margin-top:8px;font-size:12px;color:var(--success)"><strong>Response:</strong> <?= htmlspecialchars($f['response']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>
<?php include '../includes/footer.php'; ?>
