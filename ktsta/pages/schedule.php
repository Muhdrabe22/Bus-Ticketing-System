<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Trip Schedule Calendar';
$db = getDB();

$month = (int)($_GET['month'] ?? date('m'));
$year  = (int)($_GET['year']  ?? date('Y'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$firstDay   = mktime(0,0,0,$month,1,$year);
$daysInMonth = date('t',$firstDay);
$startDow    = (int)date('w',$firstDay); // 0=Sun

// Fetch trips for this month
$monthStr  = sprintf('%04d-%02d',$year,$month);
$tripsRaw  = $db->query("SELECT t.id, t.trip_code, t.departure_datetime, t.status, t.available_seats, t.total_seats, t.fare,
  r.origin, r.destination, b.bus_number, b.bus_type
  FROM trips t JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id
  WHERE DATE_FORMAT(t.departure_datetime,'%Y-%m') = '$monthStr'
  ORDER BY t.departure_datetime ASC")->fetch_all(MYSQLI_ASSOC);

// Group by day
$tripsByDay = [];
foreach ($tripsRaw as $t) {
    $day = (int)date('j', strtotime($t['departure_datetime']));
    $tripsByDay[$day][] = $t;
}

$statusColors = [
    'scheduled'  => '#3B82F6',
    'boarding'   => '#F59E0B',
    'in_transit' => '#8B5CF6',
    'completed'  => '#10B981',
    'cancelled'  => '#EF4444',
];

$monthName = date('F Y', $firstDay);
$prevMonth = $month - 1 ?: 12;
$prevYear  = $month - 1 ? $year : $year - 1;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear  = $month == 12 ? $year + 1 : $year;

include '../includes/header.php';
?>
<style>
.calendar-page { max-width:1200px; margin:0 auto; padding:32px 20px; }
.cal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); border:1px solid var(--gray-200); border-radius:16px; overflow:hidden; background:white; box-shadow:var(--shadow); }
.cal-dow { background:var(--gray-800); color:white; text-align:center; padding:12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
.cal-cell { border-right:1px solid var(--gray-100); border-bottom:1px solid var(--gray-100); min-height:120px; padding:8px; position:relative; transition:background .15s; }
.cal-cell:hover { background:var(--gray-50); }
.cal-cell.today { background:#FFF8F5; border:2px solid var(--orange); }
.cal-cell.empty { background:var(--gray-50); opacity:.5; }
.cal-cell.past { opacity:.65; }
.cal-day-num { font-size:13px; font-weight:700; color:var(--gray-600); margin-bottom:4px; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
.cal-cell.today .cal-day-num { background:var(--orange); color:white; }
.cal-trip-pill { border-radius:5px; padding:2px 7px; font-size:10px; font-weight:700; color:white; margin-bottom:3px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; transition:opacity .15s; display:block; }
.cal-trip-pill:hover { opacity:.85; }
.cal-more { font-size:10px; color:var(--orange); font-weight:700; cursor:pointer; margin-top:2px; }

/* Legend */
.legend { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
.legend-item { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--gray-600); }
.legend-dot { width:12px; height:12px; border-radius:3px; }

/* Detail panel */
.day-detail { background:white; border-radius:20px; border:1px solid var(--gray-200); padding:24px; margin-top:24px; display:none; }
.day-detail.show { display:block; }

@media(max-width:768px) {
  .cal-cell { min-height:70px; padding:4px; }
  .cal-trip-pill { display:none; }
  .cal-cell[data-has-trips="1"]::after { content:'•'; color:var(--orange); font-size:18px; display:block; text-align:center; }
}
</style>

<div class="calendar-page">
  <!-- Header -->
  <div class="cal-header">
    <div>
      <h1 style="font-size:28px;font-weight:800"><?= $monthName ?></h1>
      <p style="color:var(--gray-400);font-size:13px">Trip schedule for <?= $monthName ?></p>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-ghost"><i class="fas fa-chevron-left"></i> <?= date('M', mktime(0,0,0,$prevMonth,1,$prevYear)) ?></a>
      <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn btn-outline btn-sm">Today</a>
      <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-ghost"><?= date('M', mktime(0,0,0,$nextMonth,1,$nextYear)) ?> <i class="fas fa-chevron-right"></i></a>
      <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-primary btn-sm"><i class="fas fa-ticket-alt"></i> Book Now</a>
    </div>
  </div>

  <!-- Legend -->
  <div class="legend">
    <?php foreach($statusColors as $s=>$c): ?>
    <div class="legend-item"><div class="legend-dot" style="background:<?= $c ?>"></div><?= ucfirst(str_replace('_',' ',$s)) ?></div>
    <?php endforeach; ?>
    <div style="margin-left:auto;font-size:13px;color:var(--gray-400)"><strong><?= count($tripsRaw) ?></strong> trips this month</div>
  </div>

  <!-- Calendar Grid -->
  <div class="cal-grid">
    <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
    <div class="cal-dow"><?= $d ?></div>
    <?php endforeach; ?>

    <?php
    $today    = date('j');
    $todayMon = date('n');
    $todayYr  = date('Y');
    $cell = 0;

    // Empty cells before month start
    for ($i = 0; $i < $startDow; $i++, $cell++): ?>
    <div class="cal-cell empty"></div>
    <?php endfor;

    // Day cells
    for ($day = 1; $day <= $daysInMonth; $day++, $cell++):
      $isToday = ($day == $today && $month == $todayMon && $year == $todayYr);
      $isPast  = mktime(0,0,0,$month,$day,$year) < mktime(0,0,0,$todayMon,$today,$todayYr);
      $dayTrips = $tripsByDay[$day] ?? [];
      $hasTrips = !empty($dayTrips);
    ?>
    <div class="cal-cell <?= $isToday?'today':'' ?> <?= $isPast?'past':'' ?>"
         data-has-trips="<?= $hasTrips?1:0 ?>"
         <?= $hasTrips ? "onclick=\"showDayDetail($day, '".date('D d M Y',mktime(0,0,0,$month,$day,$year))."')\"" : '' ?>
         style="<?= $hasTrips?'cursor:pointer':'' ?>">
      <div class="cal-day-num"><?= $day ?></div>
      <?php
        $shown = 0;
        foreach ($dayTrips as $t):
          $color = $statusColors[$t['status']] ?? '#6B7583';
          if ($shown < 3): $shown++;
      ?>
      <span class="cal-trip-pill" style="background:<?= $color ?>"
            onclick="event.stopPropagation();showTripDetail(<?= $t['id'] ?>)"
            title="<?= htmlspecialchars($t['origin'].' → '.$t['destination']) ?> at <?= date('H:i',strtotime($t['departure_datetime'])) ?>">
        <?= date('H:i',strtotime($t['departure_datetime'])) ?> <?= htmlspecialchars($t['origin']) ?>→<?= htmlspecialchars($t['destination']) ?>
      </span>
      <?php endif; endforeach;
      $extra = count($dayTrips) - 3;
      if ($extra > 0): ?>
      <div class="cal-more" onclick="showDayDetail(<?= $day ?>, '<?= date('D d M',mktime(0,0,0,$month,$day,$year)) ?>')">+<?= $extra ?> more</div>
      <?php endif; ?>
    </div>
    <?php endfor;

    // Fill remaining cells
    $remaining = 7 - ($cell % 7);
    if ($remaining < 7) for ($i = 0; $i < $remaining; $i++): ?>
    <div class="cal-cell empty"></div>
    <?php endfor; ?>
  </div>

  <!-- Day Detail Panel -->
  <div class="day-detail" id="dayDetail">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 id="dayDetailTitle" style="font-size:18px;font-weight:800"></h3>
      <button class="btn btn-ghost btn-sm" onclick="document.getElementById('dayDetail').classList.remove('show')"><i class="fas fa-times"></i></button>
    </div>
    <div id="dayDetailContent"></div>
  </div>

  <!-- Monthly Summary -->
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-top:24px">
    <?php
    $total   = count($tripsRaw);
    $byStatus = array_count_values(array_column($tripsRaw,'status'));
    foreach([
      ['Total Trips',$total,'fas fa-route','orange'],
      ['Scheduled',$byStatus['scheduled']??0,'fas fa-calendar','blue'],
      ['Completed',$byStatus['completed']??0,'fas fa-check-circle','green'],
      ['Cancelled',$byStatus['cancelled']??0,'fas fa-times-circle','red'],
      ['Total Seats',array_sum(array_column($tripsRaw,'total_seats')),'fas fa-users','blue'],
    ] as [$lbl,$val,$icon,$cls]):
    ?>
    <div class="stat-card <?= $cls ?>">
      <div class="stat-value"><?= number_format($val) ?></div>
      <div class="stat-label"><?= $lbl ?></div>
      <div class="stat-icon"><i class="<?= $icon ?>"></i></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Trip Detail Modal -->
<div class="modal-overlay" id="tripDetailModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h3 id="tripDetailTitle">Trip Details</h3>
      <button class="modal-close" onclick="closeModal('tripDetailModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="tripDetailBody"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('tripDetailModal')">Close</button>
      <a id="tripBookBtn" href="#" class="btn btn-primary"><i class="fas fa-ticket-alt"></i> Book Seat</a>
    </div>
  </div>
</div>

<script>
const allTrips = <?= json_encode($tripsRaw) ?>;
const statusColors = <?= json_encode($statusColors) ?>;

function showDayDetail(day, dateStr) {
  const panel = document.getElementById('dayDetail');
  const trips = allTrips.filter(t => new Date(t.departure_datetime).getDate() === day);
  document.getElementById('dayDetailTitle').textContent = dateStr + ' — ' + trips.length + ' trip' + (trips.length!==1?'s':'');

  if (!trips.length) {
    document.getElementById('dayDetailContent').innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:20px">No trips scheduled for this day.</p>';
  } else {
    document.getElementById('dayDetailContent').innerHTML = trips.map(t => `
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;border:1px solid var(--gray-200);border-radius:12px;margin-bottom:8px;cursor:pointer" onclick="showTripDetail(${t.id})">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:44px;text-align:center">
            <div style="font-size:18px;font-weight:800;font-family:var(--mono);color:var(--gray-900)">${new Date(t.departure_datetime).toTimeString().slice(0,5)}</div>
          </div>
          <div>
            <div style="font-weight:700">${t.origin} → ${t.destination}</div>
            <div style="font-size:12px;color:var(--gray-400)">${t.bus_number} · ${t.available_seats}/${t.total_seats} seats</div>
          </div>
        </div>
        <div style="text-align:right">
          <span style="background:${statusColors[t.status]};color:white;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700">${t.status.replace('_',' ')}</span>
          <div style="font-size:14px;font-weight:800;color:var(--orange);margin-top:4px">₦${Number(t.fare).toLocaleString()}</div>
        </div>
      </div>`).join('');
  }
  panel.classList.add('show');
  panel.scrollIntoView({behavior:'smooth', block:'nearest'});
}

function showTripDetail(id) {
  const t = allTrips.find(x => x.id == id);
  if (!t) return;
  document.getElementById('tripDetailTitle').textContent = t.origin + ' → ' + t.destination;
  document.getElementById('tripDetailBody').innerHTML = `
    <div style="background:linear-gradient(135deg,var(--orange),var(--orange-dark));border-radius:14px;padding:20px;color:white;margin-bottom:16px">
      <div style="font-size:28px;font-weight:800">${t.origin} → ${t.destination}</div>
      <div style="opacity:.8;margin-top:4px">${new Date(t.departure_datetime).toLocaleString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit'})}</div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      ${[['Bus',t.bus_number],['Type',t.bus_type],['Available Seats',t.available_seats+'/'+t.total_seats],['Fare','₦'+Number(t.fare).toLocaleString()],['Status',t.status.replace('_',' ')],['Trip Code',t.trip_code]].map(([l,v])=>`
        <div style="background:var(--gray-50);border-radius:10px;padding:12px">
          <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.5px">${l}</div>
          <div style="font-weight:700;margin-top:2px">${v}</div>
        </div>`).join('')}
    </div>`;
  document.getElementById('tripBookBtn').href = '<?= BASE_URL ?>/pages/search.php?from='+encodeURIComponent(t.origin)+'&to='+encodeURIComponent(t.destination);
  openModal('tripDetailModal');
}
</script>
<?php include '../includes/footer.php'; ?>
