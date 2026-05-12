<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$pageTitle = 'Analytics & Reports';
$db   = getDB();

// Date range
$from = clean($_GET['from'] ?? date('Y-m-01'));
$to   = clean($_GET['to']   ?? date('Y-m-d'));

// Core metrics
$revenue       = $db->query("SELECT COALESCE(SUM(fare),0) r FROM bookings WHERE payment_status='paid' AND DATE(created_at) BETWEEN '$from' AND '$to'")->fetch_assoc()['r'];
$bookings      = $db->query("SELECT COUNT(*) c FROM bookings WHERE DATE(created_at) BETWEEN '$from' AND '$to'")->fetch_assoc()['c'];
$passengers    = $db->query("SELECT COUNT(DISTINCT passenger_id) c FROM bookings WHERE DATE(created_at) BETWEEN '$from' AND '$to'")->fetch_assoc()['c'];
$cancellations = $db->query("SELECT COUNT(*) c FROM bookings WHERE booking_status='cancelled' AND DATE(created_at) BETWEEN '$from' AND '$to'")->fetch_assoc()['c'];
$occupancyRes  = $db->query("SELECT AVG((total_seats - available_seats)/total_seats*100) r FROM trips WHERE DATE(departure_datetime) BETWEEN '$from' AND '$to' AND status IN ('completed','in_transit')")->fetch_assoc();
$occupancy     = round($occupancyRes['r'] ?? 0, 1);

// Revenue by route
$byRoute = $db->query("SELECT r.origin, r.destination, COUNT(b.id) as cnt, COALESCE(SUM(b.fare),0) as rev
    FROM bookings b JOIN trips t ON b.trip_id=t.id JOIN routes r ON t.route_id=r.id
    WHERE b.payment_status='paid' AND DATE(b.created_at) BETWEEN '$from' AND '$to'
    GROUP BY r.id ORDER BY rev DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// Daily revenue (last 30 days)
$dailyRev = [];
for ($i=29;$i>=0;$i--) {
    $d   = date('Y-m-d', strtotime("-$i days"));
    $rev = $db->query("SELECT COALESCE(SUM(fare),0) r FROM bookings WHERE payment_status='paid' AND DATE(created_at)='$d'")->fetch_assoc()['r'];
    $cnt = $db->query("SELECT COUNT(*) c FROM bookings WHERE DATE(created_at)='$d'")->fetch_assoc()['c'];
    $dailyRev[] = ['date'=>date('d M',strtotime($d)),'rev'=>(float)$rev,'cnt'=>(int)$cnt,'day'=>date('D',strtotime($d))];
}

// Payment method breakdown
$byPayment = $db->query("SELECT payment_method, COUNT(*) cnt, COALESCE(SUM(fare),0) rev FROM bookings WHERE payment_status='paid' AND DATE(created_at) BETWEEN '$from' AND '$to' GROUP BY payment_method")->fetch_all(MYSQLI_ASSOC);

// Bus utilisation
$busStat = $db->query("SELECT b.bus_number, b.bus_type, COUNT(t.id) as trip_cnt, COALESCE(SUM(t.total_seats - t.available_seats),0) as passengers
    FROM buses b LEFT JOIN trips t ON b.id=t.bus_id AND t.status='completed' AND DATE(t.departure_datetime) BETWEEN '$from' AND '$to'
    GROUP BY b.id ORDER BY passengers DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

// Top passengers
$topPax = $db->query("SELECT u.full_name, u.phone, COUNT(b.id) trips, COALESCE(SUM(b.fare),0) spent
    FROM bookings b JOIN users u ON b.passenger_id=u.id WHERE b.payment_status='paid' AND DATE(b.created_at) BETWEEN '$from' AND '$to'
    GROUP BY u.id ORDER BY trips DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// New users per day (last 7)
$newUsers = $db->query("SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE role='passenger' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetch_all(MYSQLI_ASSOC);

$maxRev = max(array_column($dailyRev,'rev') ?: [1]);
$maxRoutRev = max(array_column($byRoute,'rev') ?: [1]);

include '../includes/header.php';
?>
<style>
.admin-layout{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 64px)}
.admin-sidebar{background:var(--gray-900);padding:16px 10px;position:sticky;top:64px;height:calc(100vh - 64px);overflow-y:auto}
.admin-sidebar .sidebar-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);font-size:13.5px;transition:all .2s;margin-bottom:2px}
.admin-sidebar .sidebar-item:hover,.admin-sidebar .sidebar-item.active{background:rgba(232,82,10,.2);color:var(--orange)}
.admin-sidebar .sidebar-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.3);padding:0 12px;margin:16px 0 8px}
.metric-card{background:white;border-radius:16px;padding:20px;border:1px solid var(--gray-200);position:relative;overflow:hidden}
.chart-col{display:flex;flex-direction:column;align-items:center;gap:3px;flex:1;min-width:0}
.chart-col .bar{border-radius:4px 4px 0 0;transition:all .3s;width:100%}
.chart-col .label{font-size:8px;color:var(--gray-400);font-weight:600;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:100%}
</style>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div style="padding:8px 12px 16px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:8px"><div style="color:white;font-weight:800;font-size:14px">Admin Panel</div></div>
    <div class="sidebar-label">Navigation</div>
    <a class="sidebar-item" href="dashboard.php"><i class="fas fa-tachometer-alt" style="width:18px"></i> Dashboard</a>
    <a class="sidebar-item active" href="analytics.php"><i class="fas fa-chart-bar" style="width:18px"></i> Analytics</a>
    <a class="sidebar-item" href="promo-codes.php"><i class="fas fa-tag" style="width:18px"></i> Promo Codes</a>
    <a class="sidebar-item" href="maintenance.php"><i class="fas fa-tools" style="width:18px"></i> Maintenance</a>
    <a class="sidebar-item" href="staff.php"><i class="fas fa-id-card" style="width:18px"></i> Staff Records</a>
    <a class="sidebar-item" href="incidents.php"><i class="fas fa-exclamation-triangle" style="width:18px"></i> Incidents</a>
    <a class="sidebar-item" href="subscriptions.php"><i class="fas fa-sync" style="width:18px"></i> Subscriptions</a>
  </aside>

  <main style="padding:28px;background:var(--gray-50)">
    <!-- Header + date filter -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
      <div><h1 style="font-size:24px;font-weight:800">Analytics & Reports</h1><p style="color:var(--gray-500);font-size:13px">Performance overview for your selected period</p></div>
      <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:6px">
          <label style="font-size:12px;font-weight:600;color:var(--gray-500)">From</label>
          <input type="date" name="from" class="form-control" style="width:auto" value="<?= $from ?>">
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <label style="font-size:12px;font-weight:600;color:var(--gray-500)">To</label>
          <input type="date" name="to" class="form-control" style="width:auto" value="<?= $to ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
        <?php foreach([['This Week',date('Y-m-d',strtotime('monday this week')),date('Y-m-d')],['This Month',date('Y-m-01'),date('Y-m-d')],['Last Month',date('Y-m-01',strtotime('first day of last month')),date('Y-m-t',strtotime('first day of last month'))]] as [$lbl,$f,$t2]): ?>
        <a href="?from=<?= $f ?>&to=<?= $t2 ?>" class="btn btn-ghost btn-sm"><?= $lbl ?></a>
        <?php endforeach; ?>
      </form>
    </div>

    <!-- KPI Row -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px">
      <?php foreach([
        ['Total Revenue', formatMoney($revenue), 'fas fa-naira-sign', 'orange'],
        ['Bookings', number_format($bookings), 'fas fa-ticket-alt', 'blue'],
        ['Passengers', number_format($passengers), 'fas fa-users', 'green'],
        ['Cancellations', number_format($cancellations), 'fas fa-times-circle', 'red'],
        ['Occupancy Rate', $occupancy.'%', 'fas fa-chair', 'blue'],
      ] as [$lbl,$val,$icon,$cls]): ?>
      <div class="stat-card <?= $cls ?>">
        <div class="stat-value" style="font-size:22px"><?= $val ?></div>
        <div class="stat-label"><?= $lbl ?></div>
        <div class="stat-icon"><i class="<?= $icon ?>"></i></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Daily Revenue Chart (30 days) -->
    <div class="card" style="margin-bottom:20px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div class="card-title" style="margin:0">Daily Revenue — Last 30 Days</div>
        <div style="font-size:13px;color:var(--gray-400)">Total: <strong style="color:var(--orange)"><?= formatMoney(array_sum(array_column($dailyRev,'rev'))) ?></strong></div>
      </div>
      <div style="display:flex;align-items:flex-end;gap:4px;height:160px;padding-bottom:4px">
        <?php foreach($dailyRev as $d): ?>
        <div class="chart-col" title="<?= $d['date'] ?>: <?= formatMoney($d['rev']) ?>">
          <div class="bar" style="height:<?= $maxRev>0?max(4,$d['rev']/$maxRev*130).'px':'4px' ?>;background:<?= $d['day']==='Sat'||$d['day']==='Sun'?'var(--blue)':'var(--orange)' ?>;opacity:<?= $d['rev']>0?1:.25 ?>"></div>
          <div class="label"><?= $d['day'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:16px;font-size:11px;color:var(--gray-400);margin-top:6px">
        <span><span style="display:inline-block;width:10px;height:10px;background:var(--orange);border-radius:2px;margin-right:4px"></span>Weekday</span>
        <span><span style="display:inline-block;width:10px;height:10px;background:var(--blue);border-radius:2px;margin-right:4px"></span>Weekend</span>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
      <!-- Revenue by Route -->
      <div class="card">
        <div class="card-title">Top Routes by Revenue</div>
        <?php foreach($byRoute as $r): ?>
        <div style="margin-bottom:12px">
          <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
            <span><?= $r['origin'] ?> → <?= $r['destination'] ?></span>
            <span style="font-weight:700;color:var(--orange)"><?= formatMoney($r['rev']) ?></span>
          </div>
          <div style="background:var(--gray-100);border-radius:4px;height:6px">
            <div style="background:linear-gradient(90deg,var(--orange),var(--orange-light));height:100%;width:<?= $maxRoutRev>0?($r['rev']/$maxRoutRev*100).'%':'0%' ?>;border-radius:4px"></div>
          </div>
          <div style="font-size:11px;color:var(--gray-400);margin-top:2px"><?= $r['cnt'] ?> bookings</div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($byRoute)): ?><div style="color:var(--gray-400);font-size:13px;text-align:center;padding:20px">No data for this period</div><?php endif; ?>
      </div>

      <!-- Payment Methods -->
      <div class="card">
        <div class="card-title">Payment Methods</div>
        <?php $pmColors=['cash'=>'var(--orange)','wallet'=>'var(--blue)','card'=>'var(--success)','transfer'=>'#7C3AED']; ?>
        <?php $totalPm = array_sum(array_column($byPayment,'cnt')); ?>
        <?php foreach($byPayment as $pm): ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
          <div style="width:44px;height:44px;border-radius:12px;background:<?= ($pmColors[$pm['payment_method']]??'var(--gray-400)') ?>22;display:flex;align-items:center;justify-content:center;font-size:18px;color:<?= $pmColors[$pm['payment_method']] ?? 'var(--gray-400)' ?>">
            <i class="fas fa-<?= $pm['payment_method']==='cash'?'money-bill-wave':($pm['payment_method']==='wallet'?'wallet':($pm['payment_method']==='card'?'credit-card':'university')) ?>"></i>
          </div>
          <div style="flex:1">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px">
              <span style="font-weight:600;text-transform:capitalize"><?= $pm['payment_method'] ?></span>
              <span><?= $pm['cnt'] ?> (<?= $totalPm>0?round($pm['cnt']/$totalPm*100).'%':'0%' ?>)</span>
            </div>
            <div style="background:var(--gray-100);border-radius:4px;height:5px">
              <div style="background:<?= $pmColors[$pm['payment_method']] ?? 'var(--gray-400)' ?>;height:100%;width:<?= $totalPm>0?($pm['cnt']/$totalPm*100).'%':'0%' ?>;border-radius:4px"></div>
            </div>
            <div style="font-size:11px;color:var(--gray-400);margin-top:2px"><?= formatMoney($pm['rev']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($byPayment)): ?><div style="color:var(--gray-400);font-size:13px;text-align:center;padding:20px">No data</div><?php endif; ?>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <!-- Bus Utilisation -->
      <div class="card">
        <div class="card-title">Bus Utilisation</div>
        <?php foreach($busStat as $b): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
          <div style="width:40px;height:40px;border-radius:10px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:16px"><i class="fas fa-bus"></i></div>
          <div style="flex:1">
            <div style="display:flex;justify-content:space-between;font-size:13px">
              <strong><?= $b['bus_number'] ?></strong>
              <span style="color:var(--gray-400)"><?= $b['passengers'] ?> pax / <?= $b['trip_cnt'] ?> trips</span>
            </div>
            <div style="background:var(--gray-100);border-radius:4px;height:5px;margin-top:4px">
              <div style="background:var(--blue);height:100%;width:<?= $busStat[0]['passengers']>0?($b['passengers']/$busStat[0]['passengers']*100).'%':'0%' ?>;border-radius:4px"></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($busStat)): ?><div style="color:var(--gray-400);font-size:13px;text-align:center;padding:20px">No trips in range</div><?php endif; ?>
      </div>

      <!-- Top Passengers -->
      <div class="card">
        <div class="card-title">Top Passengers</div>
        <?php foreach($topPax as $i=>$p): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--gray-100)">
          <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--orange),var(--orange-dark));color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0"><?= strtoupper(substr($p['full_name'],0,1)) ?></div>
          <div style="flex:1">
            <div style="font-weight:700;font-size:13px"><?= htmlspecialchars($p['full_name']) ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= $p['phone'] ?></div>
          </div>
          <div style="text-align:right">
            <div style="font-weight:700;color:var(--orange)"><?= $p['trips'] ?> trips</div>
            <div style="font-size:11px;color:var(--gray-400)"><?= formatMoney($p['spent']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($topPax)): ?><div style="color:var(--gray-400);font-size:13px;text-align:center;padding:20px">No data</div><?php endif; ?>
      </div>
    </div>

    <!-- Export Button -->
    <div style="margin-top:20px;display:flex;gap:10px">
      <button class="btn btn-ghost" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
      <a href="?from=<?= $from ?>&to=<?= $to ?>&export=csv" class="btn btn-secondary"><i class="fas fa-download"></i> Export CSV</a>
    </div>
  </main>
</div>

<?php
if (isset($_GET['export']) && $_GET['export']==='csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ktsta_report_'.$from.'_'.$to.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['KTSTA Analytics Report', 'Period: '.$from.' to '.$to]);
    fputcsv($out,['Metric','Value']);
    fputcsv($out,['Total Revenue', '₦'.number_format($revenue,2)]);
    fputcsv($out,['Total Bookings', $bookings]);
    fputcsv($out,['Unique Passengers', $passengers]);
    fputcsv($out,['Cancellations', $cancellations]);
    fputcsv($out,['Avg Occupancy Rate', $occupancy.'%']);
    fputcsv($out,[]);
    fputcsv($out,['Route','Bookings','Revenue']);
    foreach($byRoute as $r) fputcsv($out,[$r['origin'].' → '.$r['destination'],$r['cnt'],'₦'.number_format($r['rev'],2)]);
    fclose($out); exit;
}
?>
<?php include '../includes/footer.php'; ?>
