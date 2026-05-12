<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('passenger');
$pageTitle = 'My Wallet';
$user = currentUser();
$db = getDB();
$uid = (int)$user['id'];

// Handle top-up
$msg = '';
if ($_POST['action'] ?? '' === 'topup') {
  $amount = (float)$_POST['amount'];
  $minTopup = (float)getSetting('wallet_topup_min') ?: 500;
  if ($amount < $minTopup) {
    $msg = "error:Minimum top-up is ₦".number_format($minTopup);
  } else {
    $db->query("UPDATE users SET wallet_balance=wallet_balance+$amount WHERE id=$uid");
    $newBal = $db->query("SELECT wallet_balance FROM users WHERE id=$uid")->fetch_assoc()['wallet_balance'];
    $ref = generateRef('TOP');
    $db->query("INSERT INTO wallet_transactions (user_id,amount,type,description,balance_after,reference) VALUES ($uid,$amount,'credit','Wallet top-up via card',{$newBal},'$ref')");
    addNotification($uid,'Wallet Topped Up',"₦".number_format($amount)." has been added to your wallet.",'payment');
    $user = currentUser(); // Refresh
    $msg = "success:₦".number_format($amount)." added to your wallet!";
  }
}

$transactions = $db->query("SELECT * FROM wallet_transactions WHERE user_id=$uid ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$totalCredit = $db->query("SELECT COALESCE(SUM(amount),0) s FROM wallet_transactions WHERE user_id=$uid AND type='credit'")->fetch_assoc()['s'];
$totalDebit = $db->query("SELECT COALESCE(SUM(amount),0) s FROM wallet_transactions WHERE user_id=$uid AND type='debit'")->fetch_assoc()['s'];

include '../includes/header.php';
?>
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
      <a class="sidebar-item active" href="wallet.php"><i class="fas fa-wallet"></i> Wallet</a>
      <a class="sidebar-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
      <a class="sidebar-item" href="<?= BASE_URL ?>/pages/logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>
  <main class="main-content">
    <div class="page-header"><h1>My Wallet</h1><p>Manage your KTSTA wallet balance</p></div>

    <?php if ($msg): ?>
    <?php [$type,$text] = explode(':',$msg,2); ?>
    <div style="background:<?= $type==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $type==='success'?'#86EFAC':'#FCA5A5' ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:<?= $type==='success'?'var(--success)':'var(--danger)' ?>;display:flex;align-items:center;gap:8px">
      <i class="fas fa-<?= $type==='success'?'check':'exclamation' ?>-circle"></i> <?= htmlspecialchars($text) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px">
      <!-- Balance Card -->
      <div style="background:linear-gradient(135deg,var(--orange),var(--orange-dark));border-radius:24px;padding:32px;color:white;position:relative;overflow:hidden">
        <div style="position:absolute;right:-20px;top:-20px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.1)"></div>
        <div style="position:absolute;right:20px;bottom:-30px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.07)"></div>
        <div style="font-size:14px;opacity:.8;margin-bottom:8px"><i class="fas fa-wallet"></i> Available Balance</div>
        <div style="font-size:44px;font-weight:800;font-family:var(--mono)"><?= formatMoney($user['wallet_balance']) ?></div>
        <div style="font-size:12px;opacity:.7;margin-top:8px">Use for ticket bookings &amp; payments</div>
        <button class="btn" style="background:rgba(255,255,255,.2);color:white;margin-top:16px;border:1px solid rgba(255,255,255,.3)" onclick="openModal('topupModal')">
          <i class="fas fa-plus"></i> Top Up Wallet
        </button>
      </div>

      <!-- Stats -->
      <div style="display:flex;flex-direction:column;gap:12px">
        <div class="card" style="flex:1;display:flex;align-items:center;gap:16px">
          <div style="width:48px;height:48px;border-radius:12px;background:#F0FDF4;display:flex;align-items:center;justify-content:center;color:var(--success);font-size:20px"><i class="fas fa-arrow-down"></i></div>
          <div><div style="font-size:22px;font-weight:800;font-family:var(--mono);color:var(--success)"><?= formatMoney($totalCredit) ?></div><div style="font-size:12px;color:var(--gray-400)">Total Credited</div></div>
        </div>
        <div class="card" style="flex:1;display:flex;align-items:center;gap:16px">
          <div style="width:48px;height:48px;border-radius:12px;background:#FEF2F2;display:flex;align-items:center;justify-content:center;color:var(--danger);font-size:20px"><i class="fas fa-arrow-up"></i></div>
          <div><div style="font-size:22px;font-weight:800;font-family:var(--mono);color:var(--danger)"><?= formatMoney($totalDebit) ?></div><div style="font-size:12px;color:var(--gray-400)">Total Spent</div></div>
        </div>
      </div>
    </div>

    <!-- Transaction History -->
    <div class="card">
      <div class="card-title">Transaction History</div>
      <?php if (empty($transactions)): ?>
      <div style="text-align:center;padding:40px;color:var(--gray-400)"><i class="fas fa-history" style="font-size:32px;display:block;margin-bottom:10px"></i>No transactions yet</div>
      <?php else: ?>
      <?php foreach($transactions as $t): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--gray-100)">
        <div style="display:flex;gap:12px;align-items:center">
          <div style="width:44px;height:44px;border-radius:12px;background:<?= $t['type']==='credit'?'#F0FDF4':'#FEF2F2' ?>;display:flex;align-items:center;justify-content:center;color:<?= $t['type']==='credit'?'var(--success)':'var(--danger)' ?>;font-size:16px">
            <i class="fas fa-<?= $t['type']==='credit'?'arrow-circle-down':'arrow-circle-up' ?>"></i>
          </div>
          <div>
            <div style="font-size:14px;font-weight:600"><?= htmlspecialchars($t['description'] ?? '-') ?></div>
            <div style="font-size:12px;color:var(--gray-400)"><?= date('D d M Y, H:i',strtotime($t['created_at'])) ?></div>
            <?php if ($t['reference']): ?><div style="font-size:11px;color:var(--gray-300);font-family:var(--mono)"><?= $t['reference'] ?></div><?php endif; ?>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-size:16px;font-weight:800;color:<?= $t['type']==='credit'?'var(--success)':'var(--danger)' ?>;font-family:var(--mono)">
            <?= $t['type']==='credit'?'+':'-' ?><?= formatMoney($t['amount']) ?>
          </div>
          <?php if ($t['balance_after'] !== null): ?><div style="font-size:11px;color:var(--gray-400)">Bal: <?= formatMoney($t['balance_after']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Top Up Modal -->
<div class="modal-overlay" id="topupModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header"><h3><i class="fas fa-plus-circle" style="color:var(--orange)"></i> Top Up Wallet</h3><button class="modal-close" onclick="closeModal('topupModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px">
        <?php foreach([500,1000,2000,3000,5000,10000] as $amt): ?>
        <button class="btn btn-ghost" onclick="document.getElementById('topupAmt').value=<?= $amt ?>" style="font-family:var(--mono);font-weight:700">₦<?= number_format($amt) ?></button>
        <?php endforeach; ?>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="topup">
        <div class="form-group"><label class="form-label">Amount (₦)</label><input type="number" id="topupAmt" name="amount" class="form-control" placeholder="Enter amount" min="500" step="100" required></div>
        <div class="form-group"><label class="form-label">Payment Method</label><select name="payment_method" class="form-control"><option value="card">Debit/Credit Card</option><option value="transfer">Bank Transfer</option><option value="ussd">USSD</option></select></div>
        <div style="background:var(--gray-50);border-radius:10px;padding:12px;font-size:13px;color:var(--gray-500);margin-bottom:16px"><i class="fas fa-info-circle" style="color:var(--blue)"></i> For demo purposes, top-ups are applied instantly.</div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px"><i class="fas fa-wallet"></i> Add Funds</button>
      </form>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
