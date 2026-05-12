<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Lost & Found';
$user = isLoggedIn() ? currentUser() : null;
$db = getDB();
$msg = '';
$tab = $_GET['tab'] ?? 'report';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ref    = generateRef('LF');
    $type   = clean($_POST['type'] ?? 'lost');
    $name   = clean($_POST['reporter_name'] ?? '');
    $phone  = clean($_POST['reporter_phone'] ?? '');
    $desc   = clean($_POST['item_description'] ?? '');
    $cat    = clean($_POST['item_category'] ?? 'other');
    $loc    = clean($_POST['location_found'] ?? '');
    $tripId = (int)($_POST['trip_id'] ?? 0);
    $busId  = (int)($_POST['bus_id'] ?? 0);
    $uid    = $user ? (int)$user['id'] : 'NULL';
    $today  = date('Y-m-d');

    if ($name && $phone && $desc) {
        $db->query("INSERT INTO lost_found (report_ref,type,user_id,reporter_name,reporter_phone,item_description,item_category,trip_id,bus_id,location_found,date_reported)
            VALUES ('$ref','$type',$uid,'$name','$phone','$desc','$cat'," . ($tripId?:NULL) . "," . ($busId?:NULL) . ",'$loc','$today')");
        if ($user) addNotification($user['id'],'Lost & Found Report','Your report '.$ref.' has been submitted. We will notify you if a match is found.','system');
        $msg = "success:Report <strong>$ref</strong> submitted. We will contact you if a match is found.";
    } else {
        $msg = 'error:Please fill all required fields.';
    }
}

// Public listings
$foundItems = $db->query("SELECT * FROM lost_found WHERE type='found' AND status='open' ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$lostItems  = $db->query("SELECT lf.*, b.bus_number FROM lost_found lf LEFT JOIN buses b ON lf.bus_id=b.id WHERE lf.type='lost' AND lf.status='open' ORDER BY lf.created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$buses      = $db->query("SELECT id, bus_number FROM buses WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
$trips      = $db->query("SELECT t.id, r.origin, r.destination, t.departure_datetime FROM trips t JOIN routes r ON t.route_id=r.id WHERE t.departure_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY t.departure_datetime DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

$catIcons = ['bag'=>'fas fa-shopping-bag','phone'=>'fas fa-mobile-alt','document'=>'fas fa-file-alt','wallet'=>'fas fa-wallet','clothing'=>'fas fa-tshirt','electronics'=>'fas fa-laptop','other'=>'fas fa-box'];
$catColors = ['bag'=>'var(--orange)','phone'=>'var(--blue)','document'=>'#8B5CF6','wallet'=>'var(--success)','clothing'=>'#EC4899','electronics'=>'var(--info)','other'=>'var(--gray-500)'];

include '../includes/header.php';
?>
<div style="max-width:1100px;margin:0 auto;padding:40px 20px">
  <div style="text-align:center;margin-bottom:32px">
    <h1 style="font-size:32px;font-weight:800">Lost & Found</h1>
    <p style="color:var(--gray-500)">Report a lost item or check if your item has been found on KTSTA buses</p>
  </div>

  <?php if ($msg): ?>
  <?php [$t,$m] = explode(':',$msg,2); ?>
  <div style="background:<?= $t==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $t==='success'?'#86EFAC':'#FCA5A5' ?>;border-radius:12px;padding:14px 20px;margin-bottom:24px;display:flex;align-items:center;gap:10px;color:<?= $t==='success'?'var(--success)':'var(--danger)' ?>">
    <i class="fas fa-<?= $t==='success'?'check':'exclamation' ?>-circle"></i><?= $m ?>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="tabs" style="margin-bottom:24px">
    <button class="tab-btn <?= $tab==='report'?'active':'' ?>" onclick="location.href='?tab=report'"><i class="fas fa-edit"></i> Report Item</button>
    <button class="tab-btn <?= $tab==='found'?'active':'' ?>" onclick="location.href='?tab=found'"><i class="fas fa-search"></i> Found Items (<?= count($foundItems) ?>)</button>
    <button class="tab-btn <?= $tab==='lost'?'active':'' ?>" onclick="location.href='?tab=lost'"><i class="fas fa-question-circle"></i> Lost Reports (<?= count($lostItems) ?>)</button>
  </div>

  <!-- REPORT TAB -->
  <?php if ($tab === 'report'): ?>
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
    <div class="card">
      <div class="card-title"><i class="fas fa-edit" style="color:var(--orange)"></i> Submit a Report</div>
      <form method="POST">
        <div style="display:flex;gap:12px;margin-bottom:20px">
          <?php foreach(['lost'=>['fa-search-minus','I Lost Something','var(--danger)'],'found'=>['fa-search-plus','I Found Something','var(--success)']] as $val=>[$icon,$lbl,$color]): ?>
          <label style="flex:1;cursor:pointer">
            <input type="radio" name="type" value="<?= $val ?>" <?= $val==='lost'?'checked':'' ?> style="display:none" id="type_<?= $val ?>">
            <div onclick="selectType('<?= $val ?>')" id="btn_<?= $val ?>" style="border:2px solid <?= $val==='lost'?'var(--danger)':'var(--gray-200)' ?>;background:<?= $val==='lost'?'#FEF2F2':'white' ?>;border-radius:12px;padding:14px;text-align:center;transition:all .2s">
              <i class="fas <?= $icon ?>" style="font-size:20px;color:<?= $color ?>;display:block;margin-bottom:6px"></i>
              <div style="font-weight:700;font-size:13px"><?= $lbl ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group"><label class="form-label">Your Name *</label><input type="text" name="reporter_name" class="form-control" required value="<?= $user ? htmlspecialchars($user['full_name']):'' ?>"></div>
          <div class="form-group"><label class="form-label">Phone Number *</label><input type="tel" name="reporter_phone" class="form-control" required value="<?= $user ? htmlspecialchars($user['phone']):'' ?>"></div>
          <div class="form-group"><label class="form-label">Item Category</label>
            <select name="item_category" class="form-control">
              <?php foreach($catIcons as $k=>$_): ?><option value="<?= $k ?>"><?= ucfirst($k) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Related Bus (if known)</label>
            <select name="bus_id" class="form-control"><option value="">Unknown</option>
              <?php foreach($buses as $b): ?><option value="<?= $b['id'] ?>"><?= $b['bus_number'] ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:span 2"><label class="form-label">Related Trip (if known)</label>
            <select name="trip_id" class="form-control"><option value="">Unknown</option>
              <?php foreach($trips as $t): ?><option value="<?= $t['id'] ?>"><?= $t['origin'] ?> → <?= $t['destination'] ?> (<?= date('d M H:i',strtotime($t['departure_datetime'])) ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:span 2"><label class="form-label">Item Description * <small style="color:var(--gray-400)">(be as detailed as possible)</small></label>
            <textarea name="item_description" class="form-control" rows="4" required placeholder="Describe the item in detail: colour, size, brand, distinguishing features..."></textarea>
          </div>
          <div class="form-group" style="grid-column:span 2" id="locationField"><label class="form-label">Where Found / Where Lost</label>
            <input type="text" name="location_found" class="form-control" placeholder="e.g. Seat 7, Katsina Terminal, etc.">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px"><i class="fas fa-paper-plane"></i> Submit Report</button>
      </form>
    </div>
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title">Tips for Recovery</div>
        <ul style="font-size:13px;color:var(--gray-500);line-height:2;list-style:none;padding:0">
          <?php foreach(['Report as soon as possible','Include unique identifying features','Check the Found Items list first','Keep your phone reachable','Save your report reference number'] as $t): ?>
          <li style="display:flex;gap:8px;align-items:center"><i class="fas fa-check-circle" style="color:var(--success);font-size:12px"></i><?= $t ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="card" style="background:var(--blue-dark);color:white;border:none">
        <div style="font-weight:800;margin-bottom:8px"><i class="fas fa-phone"></i> Lost Something Urgently?</div>
        <div style="font-size:13px;opacity:.8;margin-bottom:12px">Call our operations desk immediately</div>
        <div style="font-family:var(--mono);font-size:18px;font-weight:800;color:var(--orange)">0800-KTSTA-01</div>
      </div>
    </div>
  </div>

  <!-- FOUND ITEMS TAB -->
  <?php elseif ($tab === 'found'): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
    <?php foreach($foundItems as $item): ?>
    <?php $icon = $catIcons[$item['item_category']] ?? 'fas fa-box'; $color = $catColors[$item['item_category']] ?? 'var(--gray-500)'; ?>
    <div class="card" style="border-top:4px solid <?= $color ?>">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
        <div style="width:44px;height:44px;border-radius:12px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;font-size:18px;color:<?= $color ?>"><i class="<?= $icon ?>"></i></div>
        <span class="badge badge-success">Found</span>
      </div>
      <div style="font-weight:700;font-size:15px;margin-bottom:6px"><?= ucfirst($item['item_category']) ?></div>
      <div style="font-size:13px;color:var(--gray-500);margin-bottom:10px;line-height:1.6"><?= htmlspecialchars(substr($item['item_description'],0,100)) ?>...</div>
      <div style="font-size:11px;color:var(--gray-400);margin-bottom:12px">
        <i class="fas fa-calendar" style="color:var(--orange)"></i> <?= date('d M Y', strtotime($item['date_reported'])) ?>
        <?php if ($item['location_found']): ?> &bull; <?= htmlspecialchars($item['location_found']) ?><?php endif; ?>
      </div>
      <div style="font-size:11px;font-family:var(--mono);color:var(--gray-400);margin-bottom:10px"><?= $item['report_ref'] ?></div>
      <a href="tel:0800KTSTA01" class="btn btn-primary btn-sm" style="width:100%;justify-content:center"><i class="fas fa-phone"></i> Claim This Item</a>
    </div>
    <?php endforeach; ?>
    <?php if (empty($foundItems)): ?>
    <div style="grid-column:span 4;text-align:center;padding:60px;color:var(--gray-400)"><i class="fas fa-box-open" style="font-size:40px;display:block;margin-bottom:12px"></i>No found items reported yet</div>
    <?php endif; ?>
  </div>

  <!-- LOST REPORTS TAB -->
  <?php else: ?>
  <div class="table-wrap card">
    <table>
      <thead><tr><th>Ref</th><th>Category</th><th>Description</th><th>Bus</th><th>Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($lostItems as $item): ?>
      <?php $icon = $catIcons[$item['item_category']] ?? 'fas fa-box'; $color = $catColors[$item['item_category']] ?? 'var(--gray-500)'; ?>
      <tr>
        <td style="font-family:var(--mono);font-size:11px"><?= $item['report_ref'] ?></td>
        <td><span style="display:flex;align-items:center;gap:6px"><i class="<?= $icon ?>" style="color:<?= $color ?>"></i><?= ucfirst($item['item_category']) ?></span></td>
        <td style="font-size:13px"><?= htmlspecialchars(substr($item['item_description'],0,60)) ?>...</td>
        <td><?= htmlspecialchars($item['bus_number'] ?? '—') ?></td>
        <td style="font-size:12px"><?= date('d M Y',strtotime($item['date_reported'])) ?></td>
        <td><span class="badge badge-<?= $item['status']==='open'?'warning':($item['status']==='claimed'?'success':'gray') ?>"><?= $item['status'] ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($lostItems)): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:var(--gray-400)">No lost item reports</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<script>
function selectType(type) {
  const lost = document.getElementById('btn_lost'), found = document.getElementById('btn_found');
  if (type === 'lost') {
    lost.style.border='2px solid var(--danger)'; lost.style.background='#FEF2F2';
    found.style.border='2px solid var(--gray-200)'; found.style.background='white';
  } else {
    found.style.border='2px solid var(--success)'; found.style.background='#F0FDF4';
    lost.style.border='2px solid var(--gray-200)'; lost.style.background='white';
  }
  document.getElementById('type_'+type).checked = true;
}
</script>
<?php include '../includes/footer.php'; ?>
