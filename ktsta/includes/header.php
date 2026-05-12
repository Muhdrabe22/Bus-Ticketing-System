<?php
require_once __DIR__ . '/config.php';
$user = isLoggedIn() ? currentUser() : null;
$unread = $user ? getUnreadNotifications($user['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>KTSTA Bus Ticketing</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --orange: #E8520A;
  --orange-light: #F26C28;
  --orange-dark: #C44208;
  --blue: #1B4F9B;
  --blue-light: #2563CC;
  --blue-dark: #0F3272;
  --white: #FFFFFF;
  --gray-50: #F8F9FA;
  --gray-100: #F0F1F3;
  --gray-200: #E2E5E9;
  --gray-300: #CBD0D8;
  --gray-400: #9CA5B3;
  --gray-500: #6B7583;
  --gray-600: #4A5568;
  --gray-700: #2D3748;
  --gray-800: #1A202C;
  --gray-900: #0F1419;
  --success: #16A34A;
  --danger: #DC2626;
  --warning: #D97706;
  --info: #0369A1;
  --font: 'Sora', sans-serif;
  --mono: 'JetBrains Mono', monospace;
  --shadow-sm: 0 1px 3px rgba(0,0,0,.08);
  --shadow: 0 4px 16px rgba(0,0,0,.10);
  --shadow-lg: 0 10px 40px rgba(0,0,0,.15);
  --radius: 12px;
  --radius-lg: 20px;
  --transition: all .2s ease;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: var(--font); background: var(--gray-50); color: var(--gray-800); line-height: 1.6; }
a { text-decoration: none; color: inherit; }
img { max-width: 100%; }

/* NAVBAR */
.navbar {
  position: sticky; top: 0; z-index: 1000;
  background: var(--white);
  border-bottom: 2px solid var(--orange);
  padding: 0 2rem;
  display: flex; align-items: center; justify-content: space-between;
  height: 64px;
  box-shadow: 0 2px 20px rgba(232,82,10,.15);
}
.navbar-brand { display: flex; align-items: center; gap: 12px; }
.navbar-brand .logo-badge {
  width: 42px; height: 42px; border-radius: 10px;
  background: linear-gradient(135deg, var(--orange), var(--orange-dark));
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; color: white; font-weight: 800;
  box-shadow: 0 4px 12px rgba(232,82,10,.4);
}
.navbar-brand .brand-text { font-weight: 800; font-size: 18px; color: var(--blue-dark); line-height: 1.1; }
.navbar-brand .brand-text span { display: block; font-size: 10px; font-weight: 500; color: var(--gray-400); letter-spacing: .5px; text-transform: uppercase; }
.nav-links { display: flex; align-items: center; gap: 8px; }
.nav-link { padding: 8px 14px; border-radius: 8px; font-size: 14px; font-weight: 500; color: var(--gray-600); transition: var(--transition); }
.nav-link:hover, .nav-link.active { background: var(--gray-100); color: var(--orange); }
.nav-actions { display: flex; align-items: center; gap: 10px; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: var(--font); cursor: pointer; border: none; transition: var(--transition); text-decoration: none; }
.btn-primary { background: var(--orange); color: white; }
.btn-primary:hover { background: var(--orange-dark); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(232,82,10,.35); }
.btn-secondary { background: var(--blue); color: white; }
.btn-secondary:hover { background: var(--blue-dark); }
.btn-outline { background: transparent; border: 2px solid var(--orange); color: var(--orange); }
.btn-outline:hover { background: var(--orange); color: white; }
.btn-sm { padding: 6px 14px; font-size: 12px; }
.btn-lg { padding: 14px 28px; font-size: 16px; }
.btn-success { background: var(--success); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-warning { background: var(--warning); color: white; }
.btn-ghost { background: var(--gray-100); color: var(--gray-700); }
.btn-ghost:hover { background: var(--gray-200); }

.notif-btn { position: relative; width: 40px; height: 40px; border-radius: 10px; background: var(--gray-100); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--gray-600); transition: var(--transition); }
.notif-btn:hover { background: var(--gray-200); }
.notif-badge { position: absolute; top: 4px; right: 4px; width: 18px; height: 18px; border-radius: 50%; background: var(--danger); color: white; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; }

.user-avatar { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--blue), var(--blue-dark)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; cursor: pointer; border: 2px solid var(--gray-200); }
.user-dropdown { position: relative; }
.user-dropdown-menu { position: absolute; right: 0; top: calc(100% + 8px); background: white; border-radius: 12px; box-shadow: var(--shadow-lg); border: 1px solid var(--gray-200); min-width: 220px; padding: 8px; display: none; z-index: 999; }
.user-dropdown:hover .user-dropdown-menu, .user-dropdown-menu.show { display: block; }
.dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; font-size: 13px; color: var(--gray-700); transition: var(--transition); cursor: pointer; }
.dropdown-item:hover { background: var(--gray-100); color: var(--orange); }
.dropdown-item i { width: 16px; text-align: center; }
.dropdown-divider { height: 1px; background: var(--gray-100); margin: 6px 0; }

/* TOAST NOTIFICATIONS */
.toast-container { position: fixed; top: 80px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
.toast { background: white; border-radius: 12px; padding: 14px 18px; box-shadow: var(--shadow-lg); border-left: 4px solid var(--orange); display: flex; align-items: center; gap: 12px; font-size: 13px; animation: slideIn .3s ease; max-width: 320px; }
.toast.success { border-color: var(--success); }
.toast.error { border-color: var(--danger); }
.toast.info { border-color: var(--info); }
@keyframes slideIn { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }

/* CARDS */
.card { background: white; border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
.card-title { font-size: 16px; font-weight: 700; color: var(--gray-800); margin-bottom: 16px; }

/* FORM ELEMENTS */
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: var(--gray-700); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 14px; border: 2px solid var(--gray-200); border-radius: 10px; font-family: var(--font); font-size: 14px; color: var(--gray-800); background: white; transition: var(--transition); }
.form-control:focus { outline: none; border-color: var(--orange); box-shadow: 0 0 0 3px rgba(232,82,10,.1); }
.form-control::placeholder { color: var(--gray-400); }
select.form-control { cursor: pointer; }

/* BADGES */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }
.badge-success { background: #DCFCE7; color: #15803D; }
.badge-danger { background: #FEE2E2; color: #DC2626; }
.badge-warning { background: #FEF9C3; color: #B45309; }
.badge-info { background: #DBEAFE; color: #1D4ED8; }
.badge-gray { background: var(--gray-100); color: var(--gray-600); }
.badge-orange { background: #FFF0E8; color: var(--orange-dark); }

/* TABLE */
.table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid var(--gray-200); }
table { width: 100%; border-collapse: collapse; }
table th { background: var(--gray-50); padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); }
table td { padding: 14px 16px; font-size: 14px; color: var(--gray-700); border-bottom: 1px solid var(--gray-100); }
table tr:last-child td { border-bottom: none; }
table tr:hover td { background: var(--gray-50); }

/* SIDEBAR LAYOUTS */
.app-layout { display: grid; grid-template-columns: 260px 1fr; min-height: calc(100vh - 64px); }
.sidebar { background: white; border-right: 1px solid var(--gray-200); padding: 20px 12px; position: sticky; top: 64px; height: calc(100vh - 64px); overflow-y: auto; }
.sidebar-section { margin-bottom: 24px; }
.sidebar-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--gray-400); padding: 0 12px; margin-bottom: 8px; }
.sidebar-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; font-size: 13.5px; font-weight: 500; color: var(--gray-600); transition: var(--transition); cursor: pointer; margin-bottom: 2px; }
.sidebar-item:hover { background: var(--gray-100); color: var(--orange); }
.sidebar-item.active { background: linear-gradient(135deg, #FFF0E8, #FFE4D4); color: var(--orange-dark); font-weight: 700; }
.sidebar-item i { width: 18px; text-align: center; font-size: 15px; }
.sidebar-item .badge { margin-left: auto; }
.main-content { padding: 32px; background: var(--gray-50); min-height: calc(100vh - 64px); }

/* PAGE HEADER */
.page-header { margin-bottom: 28px; }
.page-header h1 { font-size: 26px; font-weight: 800; color: var(--gray-900); }
.page-header p { font-size: 14px; color: var(--gray-500); margin-top: 4px; }
.page-header .header-actions { display: flex; gap: 10px; margin-top: 16px; }

/* STAT CARDS */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: white; border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--gray-200); position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
.stat-card.orange::before { background: var(--orange); }
.stat-card.blue::before { background: var(--blue); }
.stat-card.green::before { background: var(--success); }
.stat-card.red::before { background: var(--danger); }
.stat-value { font-size: 28px; font-weight: 800; font-family: var(--mono); color: var(--gray-900); }
.stat-label { font-size: 12px; color: var(--gray-500); font-weight: 500; margin-top: 2px; }
.stat-icon { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.stat-card.orange .stat-icon { background: #FFF0E8; color: var(--orange); }
.stat-card.blue .stat-icon { background: #EFF6FF; color: var(--blue); }
.stat-card.green .stat-icon { background: #F0FDF4; color: var(--success); }
.stat-card.red .stat-icon { background: #FEF2F2; color: var(--danger); }

/* RESPONSIVE */
@media (max-width: 1024px) {
  .app-layout { grid-template-columns: 1fr; }
  .sidebar { display: none; }
  .main-content { padding: 20px; }
}
@media (max-width: 768px) {
  .navbar { padding: 0 1rem; }
  .nav-links { display: none; }
}

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); opacity: 0; pointer-events: none; transition: all .25s; }
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal-box { background: white; border-radius: 20px; width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,.25); transform: scale(.95) translateY(20px); transition: all .25s; }
.modal-overlay.open .modal-box { transform: scale(1) translateY(0); }
.modal-header { padding: 24px 24px 0; display: flex; align-items: center; justify-content: space-between; }
.modal-header h3 { font-size: 18px; font-weight: 700; }
.modal-close { width: 32px; height: 32px; border-radius: 8px; border: none; background: var(--gray-100); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--gray-500); }
.modal-close:hover { background: var(--gray-200); }
.modal-body { padding: 24px; }
.modal-footer { padding: 0 24px 24px; display: flex; justify-content: flex-end; gap: 10px; }

/* LOADING */
.loading-spinner { display: inline-block; width: 20px; height: 20px; border: 2px solid rgba(255,255,255,.3); border-top-color: white; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* SEAT MAP */
.seat-map { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; padding: 16px; }
.seat { width: 44px; height: 44px; border-radius: 8px; border: 2px solid var(--gray-300); background: white; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; cursor: pointer; transition: var(--transition); }
.seat.available:hover { border-color: var(--orange); background: #FFF0E8; color: var(--orange); }
.seat.selected { border-color: var(--orange); background: var(--orange); color: white; }
.seat.booked { background: var(--gray-200); border-color: var(--gray-300); color: var(--gray-400); cursor: not-allowed; }
.seat.driver { background: var(--blue); border-color: var(--blue-dark); color: white; cursor: not-allowed; }

/* TICKET */
.ticket { border: 2px dashed var(--gray-300); border-radius: 16px; overflow: hidden; }
.ticket-header { background: linear-gradient(135deg, var(--orange), var(--orange-dark)); color: white; padding: 20px; }
.ticket-body { padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.ticket-footer { background: var(--gray-50); padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-top: 2px dashed var(--gray-300); }
.ticket-field { }
.ticket-field .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: var(--gray-400); font-weight: 600; }
.ticket-field .value { font-size: 15px; font-weight: 700; color: var(--gray-900); margin-top: 2px; }

/* TABS */
.tabs { display: flex; gap: 4px; background: var(--gray-100); padding: 4px; border-radius: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.tab-btn { padding: 8px 16px; border-radius: 9px; border: none; background: transparent; font-family: var(--font); font-size: 13px; font-weight: 500; color: var(--gray-500); cursor: pointer; transition: var(--transition); }
.tab-btn.active { background: white; color: var(--orange); font-weight: 700; box-shadow: var(--shadow-sm); }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* FOOTER */
footer { background: var(--gray-900); color: var(--gray-400); padding: 60px 2rem 30px; }
.footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; max-width: 1200px; margin: 0 auto 40px; }
.footer-bottom { max-width: 1200px; margin: 0 auto; padding-top: 20px; border-top: 1px solid rgba(255,255,255,.1); display: flex; justify-content: space-between; font-size: 13px; }
@media (max-width: 768px) { .footer-grid { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>
<nav class="navbar">
  <a href="<?= BASE_URL ?>/index.php" class="navbar-brand">
    <div class="logo-badge"><i class="fas fa-bus"></i></div>
    <div class="brand-text">KTSTA <span>Katsina State Transport Authority</span></div>
  </a>
  <div class="nav-links">
    <a href="<?= BASE_URL ?>/index.php" class="nav-link">Home</a>
    <a href="<?= BASE_URL ?>/pages/search.php" class="nav-link">Book Ticket</a>
    <a href="<?= BASE_URL ?>/pages/routes.php" class="nav-link">Routes</a>
    <a href="<?= BASE_URL ?>/pages/fare-calculator.php" class="nav-link">Fares</a>
    <div style="position:relative" class="user-dropdown">
      <a href="#" class="nav-link">More <i class="fas fa-chevron-down" style="font-size:10px"></i></a>
      <div class="user-dropdown-menu">
        <a class="dropdown-item" href="<?= BASE_URL ?>/pages/track.php"><i class="fas fa-satellite-dish"></i> Live Tracking</a>
        <a class="dropdown-item" href="<?= BASE_URL ?>/pages/charter.php"><i class="fas fa-bus"></i> Charter a Bus</a>
        <a class="dropdown-item" href="<?= BASE_URL ?>/pages/lost-found.php"><i class="fas fa-search"></i> Lost & Found</a>
        <a class="dropdown-item" href="<?= BASE_URL ?>/pages/reviews.php"><i class="fas fa-star"></i> Reviews</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?= BASE_URL ?>/pages/about.php"><i class="fas fa-info-circle"></i> About KTSTA</a>
      </div>
    </div>
  </div>
  <div class="nav-actions">
    <?php if ($user): ?>
      <button class="notif-btn" onclick="window.location='<?= BASE_URL ?>/pages/notifications.php'">
        <i class="fas fa-bell"></i>
        <?php if ($unread > 0): ?><span class="notif-badge"><?= $unread ?></span><?php endif; ?>
      </button>
      <div class="user-dropdown">
        <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
        <div class="user-dropdown-menu">
          <div style="padding:10px 12px 6px;font-size:13px;font-weight:700;color:var(--gray-800)"><?= htmlspecialchars($user['full_name']) ?></div>
          <div style="padding:0 12px 10px;font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.5px"><?= $user['role'] ?></div>
          <div class="dropdown-divider"></div>
          <?php if ($user['role'] === 'passenger'): ?>
            <a class="dropdown-item" href="<?= BASE_URL ?>/passenger/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a class="dropdown-item" href="<?= BASE_URL ?>/passenger/tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a>
            <a class="dropdown-item" href="<?= BASE_URL ?>/passenger/wallet.php"><i class="fas fa-wallet"></i> Wallet: <?= formatMoney($user['wallet_balance']) ?></a>
            <a class="dropdown-item" href="<?= BASE_URL ?>/passenger/loyalty.php"><i class="fas fa-star"></i> Rewards</a>
          <?php elseif ($user['role'] === 'admin'): ?>
            <a class="dropdown-item" href="<?= BASE_URL ?>/admin/dashboard.php"><i class="fas fa-cog"></i> Admin Panel</a>
          <?php elseif ($user['role'] === 'officer'): ?>
            <a class="dropdown-item" href="<?= BASE_URL ?>/officer/dashboard.php"><i class="fas fa-id-badge"></i> Officer Panel</a>
          <?php elseif ($user['role'] === 'driver'): ?>
            <a class="dropdown-item" href="<?= BASE_URL ?>/driver/dashboard.php"><i class="fas fa-bus"></i> Driver Panel</a>
          <?php endif; ?>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?= BASE_URL ?>/pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
      </div>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/pages/login.php" class="btn btn-ghost btn-sm">Login</a>
      <a href="<?= BASE_URL ?>/pages/register.php" class="btn btn-primary btn-sm">Register</a>
    <?php endif; ?>
  </div>
</nav>
<div class="toast-container" id="toastContainer"></div>
<script>
function showToast(msg, type='info') {
  const icons = {success:'check-circle',error:'exclamation-circle',info:'info-circle',warning:'exclamation-triangle'};
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}" style="color:var(--${type==='error'?'danger':type})"></i><span>${msg}</span>`;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(()=>t.remove(),4000);
}
// Modal helpers
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
// Tab switch
function switchTab(tabGroup,tab) {
  document.querySelectorAll(`[data-group="${tabGroup}"]`).forEach(el=>el.classList.remove('active'));
  document.querySelectorAll(`[data-tab="${tabGroup}-${tab}"]`).forEach(el=>el.classList.add('active'));
}
</script>
