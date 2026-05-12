 
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KTSTA — Ticket Validator</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --green: #00E676; --green-dim: #00C853;
    --red: #FF1744;   --yellow: #FFD600;
    --bg: #0A0E0A;    --surface: #111711;
    --surface2: #182018; --border: #1E2E1E;
    --text: #E8F5E9;  --muted: #4CAF50;
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    font-family:'DM Sans',sans-serif; background:var(--bg);
    color:var(--text); min-height:100vh; display:flex;
    flex-direction:column; overflow-x:hidden;
  }
  body::before {
    content:''; position:fixed; inset:0; pointer-events:none; z-index:999;
    background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,0.03) 2px,rgba(0,0,0,0.03) 4px);
  }

  /* ── HEADER ── */
  header {
    display:flex; align-items:center; justify-content:space-between;
    padding:14px 24px; background:var(--surface);
    border-bottom:1px solid var(--border); position:sticky; top:0; z-index:100;
  }
  .logo-block { display:flex; align-items:center; gap:12px; }
  .logo-icon {
    width:40px; height:40px; background:var(--green); border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    font-family:'Bebas Neue',cursive; font-size:18px; color:#000; letter-spacing:1px;
  }
  .logo-text { display:flex; flex-direction:column; }
  .logo-text strong { font-family:'Bebas Neue',cursive; font-size:20px; letter-spacing:2px; color:var(--green); line-height:1; }
  .logo-text span { font-size:10px; color:#4CAF50; letter-spacing:.5px; text-transform:uppercase; }
  .header-right { display:flex; align-items:center; gap:16px; }
  .officer-badge {
    display:flex; align-items:center; gap:8px; background:var(--surface2);
    border:1px solid var(--border); padding:6px 12px; border-radius:20px;
  }
  .officer-avatar {
    width:28px; height:28px; background:linear-gradient(135deg,#2E7D32,#00E676);
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:12px; font-weight:700; color:#000;
  }
  .officer-info { display:flex; flex-direction:column; }
  .officer-name { font-size:12px; font-weight:600; color:var(--text); line-height:1; }
  .officer-role { font-size:10px; color:var(--muted); }
  .live-dot { width:8px; height:8px; background:var(--green); border-radius:50%; animation:pulse 1.5s infinite; }
  @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.8)} }

  /* ── MAIN ── */
  main {
    flex:1; display:grid; grid-template-columns:1fr 380px;
    max-width:1100px; margin:0 auto; width:100%; padding:24px 20px; gap:20px;
  }
  .scanner-panel { display:flex; flex-direction:column; gap:16px; }
  .panel-label {
    font-family:'Bebas Neue',cursive; font-size:13px; letter-spacing:3px;
    color:var(--muted); display:flex; align-items:center; gap:8px;
  }
  .panel-label::after { content:''; flex:1; height:1px; background:var(--border); }

  /* ── QR VIEWPORT ── */
  .qr-viewport {
    position:relative; background:#000; border-radius:16px; overflow:hidden;
    aspect-ratio:1; border:1px solid var(--border); cursor:pointer;
  }
  .qr-viewport::before {
    content:''; position:absolute; inset:0; z-index:1;
    background-image:linear-gradient(rgba(0,230,118,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,230,118,.04) 1px,transparent 1px);
    background-size:40px 40px;
  }
  .scan-beam {
    position:absolute; left:0; right:0; height:2px;
    background:linear-gradient(90deg,transparent,var(--green),transparent);
    box-shadow:0 0 20px var(--green),0 0 40px rgba(0,230,118,.3);
    z-index:4; animation:beam 2s ease-in-out infinite; opacity:0; transition:opacity .3s;
  }
  .scanning .scan-beam { opacity:1; }
  @keyframes beam { 0%{top:10%} 100%{top:90%} }
  .corners { position:absolute; inset:20%; z-index:3; transition:all .4s ease; }
  .corners span {
    position:absolute; width:24px; height:24px;
    border-color:var(--green); border-style:solid; transition:all .3s;
  }
  .corners span:nth-child(1){top:0;left:0;border-width:3px 0 0 3px;border-radius:4px 0 0 0}
  .corners span:nth-child(2){top:0;right:0;border-width:3px 3px 0 0;border-radius:0 4px 0 0}
  .corners span:nth-child(3){bottom:0;left:0;border-width:0 0 3px 3px;border-radius:0 0 0 4px}
  .corners span:nth-child(4){bottom:0;right:0;border-width:0 3px 3px 0;border-radius:0 0 4px 0}
  .scanning .corners{inset:15%}
  .invalid .corners span{border-color:var(--red)}
  .cam-icon {
    position:absolute; inset:0; display:flex; flex-direction:column;
    align-items:center; justify-content:center; gap:12px;
    z-index:2; color:rgba(0,230,118,.3); transition:opacity .3s;
  }
  .cam-icon svg{width:64px;height:64px}
  .cam-icon p{font-size:13px;letter-spacing:1px;text-transform:uppercase}
  .scanning .cam-icon{opacity:.2}
  .viewport-overlay {
    position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    z-index:5; opacity:0; transition:opacity .3s; pointer-events:none;
  }
  .viewport-overlay.show{opacity:1}
  .viewport-result {
    font-family:'Bebas Neue',cursive; font-size:48px;
    letter-spacing:4px; text-shadow:0 0 40px currentColor;
  }
  .viewport-overlay.valid .viewport-result{color:var(--green)}
  .viewport-overlay.invalid .viewport-result{color:var(--red)}

  /* Live camera feed */
  #cameraFeed {
    position:absolute; inset:0; width:100%; height:100%;
    object-fit:cover; z-index:2; display:none; border-radius:16px;
  }
  .scanning #cameraFeed { display:block; }

  /* ── STATUS BAR ── */
  .status-bar {
    background:var(--surface); border:1px solid var(--border);
    border-radius:10px; padding:12px 16px; display:flex; align-items:center; gap:10px;
  }
  .status-indicator{width:10px;height:10px;border-radius:50%;background:#333;transition:background .3s;flex-shrink:0}
  .status-indicator.ready{background:var(--yellow);box-shadow:0 0 8px var(--yellow)}
  .status-indicator.scanning{background:var(--green);box-shadow:0 0 8px var(--green);animation:pulse 1s infinite}
  .status-indicator.valid{background:var(--green);box-shadow:0 0 12px var(--green)}
  .status-indicator.invalid{background:var(--red);box-shadow:0 0 12px var(--red)}
  .status-text{font-family:'DM Mono',monospace;font-size:13px;color:var(--text);flex:1}

  /* ── CONTROLS ── */
  .controls{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .btn {
    padding:13px 20px; border-radius:10px; border:none; cursor:pointer;
    font-family:'DM Sans',sans-serif; font-weight:600; font-size:14px;
    letter-spacing:.5px; transition:all .2s; display:flex;
    align-items:center; justify-content:center; gap:8px;
  }
  .btn-primary{background:var(--green);color:#000}
  .btn-primary:hover{background:#00C853;transform:translateY(-1px)}
  .btn-secondary{background:var(--surface2);color:var(--text);border:1px solid var(--border)}
  .btn-secondary:hover{border-color:var(--green);color:var(--green)}

  /* ── MANUAL INPUT ── */
  .manual-input{display:flex;gap:8px}
  .ticket-input {
    flex:1; background:var(--surface2); border:1px solid var(--border);
    border-radius:10px; padding:11px 14px; font-family:'DM Mono',monospace;
    font-size:13px; color:var(--text); outline:none; transition:border-color .2s;
    letter-spacing:1px; text-transform:uppercase;
  }
  .ticket-input::placeholder{color:#2E4A2E;text-transform:none;letter-spacing:0}
  .ticket-input:focus{border-color:var(--green)}
  .btn-validate {
    padding:11px 18px; background:var(--green); color:#000; border:none;
    border-radius:10px; cursor:pointer; font-weight:700; font-size:13px;
    transition:all .2s; white-space:nowrap;
  }
  .btn-validate:hover{background:var(--green-dim)}

  /* ── STATS ── */
  .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
  .stat-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:12px; padding:14px; display:flex; flex-direction:column; gap:4px;
  }
  .stat-number{font-family:'Bebas Neue',cursive;font-size:28px;letter-spacing:1px;line-height:1}
  .stat-number.green{color:var(--green)}
  .stat-number.red{color:var(--red)}
  .stat-number.yellow{color:var(--yellow)}
  .stat-label{font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#3A5C3A}

  /* ── INFO PANEL ── */
  .info-panel{display:flex;flex-direction:column;gap:16px}
  .result-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:16px; overflow:hidden; transition:border-color .4s;
  }
  .result-card.valid{border-color:var(--green)}
  .result-card.invalid{border-color:var(--red)}
  .result-header {
    padding:14px 18px; background:var(--surface2); display:flex;
    align-items:center; gap:10px; border-bottom:1px solid var(--border);
  }
  .result-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;background:var(--border);transition:background .3s}
  .result-card.valid .result-icon{background:rgba(0,230,118,.15)}
  .result-card.invalid .result-icon{background:rgba(255,23,68,.15)}
  .result-title{font-family:'Bebas Neue',cursive;font-size:18px;letter-spacing:1px;color:var(--muted);transition:color .3s}
  .result-card.valid .result-title{color:var(--green)}
  .result-card.invalid .result-title{color:var(--red)}
  .result-body{padding:18px;display:flex;flex-direction:column;gap:14px}
  .field{display:flex;flex-direction:column;gap:3px}
  .field-label{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#3A5C3A;font-family:'DM Mono',monospace}
  .field-value{font-size:15px;font-weight:500;color:var(--text)}
  .field-value.mono{font-family:'DM Mono',monospace;font-size:13px;color:var(--green);letter-spacing:1px}
  .divider{height:1px;background:var(--border)}
  .field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .route-badge {
    display:flex;align-items:center;gap:8px;background:var(--surface2);
    border:1px solid var(--border);padding:10px 14px;border-radius:10px;font-size:13px;
  }
  .route-from,.route-to{font-weight:600;font-size:14px}
  .route-arrow{color:var(--green);font-size:16px}
  .seat-badge {
    display:inline-flex;align-items:center;justify-content:center;
    background:rgba(0,230,118,.1);border:1px solid rgba(0,230,118,.3);
    color:var(--green);font-family:'Bebas Neue',cursive;font-size:22px;
    letter-spacing:2px;padding:6px 14px;border-radius:8px;
  }
  .empty-state {
    padding:32px 18px;display:flex;flex-direction:column;
    align-items:center;gap:10px;color:#2E4A2E;text-align:center;
  }
  .empty-state svg{width:48px;height:48px;opacity:.4}
  .empty-state p{font-size:13px;line-height:1.6}

  /* ── RECENT SCANS ── */
  .recent-list{display:flex;flex-direction:column;gap:6px}
  .recent-item {
    display:flex;align-items:center;gap:10px;padding:10px 12px;
    background:var(--surface);border:1px solid var(--border);
    border-radius:8px;font-size:12px;animation:slideIn .3s ease;
  }
  @keyframes slideIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}
  .recent-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
  .recent-dot.valid{background:var(--green)}
  .recent-dot.invalid{background:var(--red)}
  .recent-dot.expired,.recent-dot.used{background:var(--yellow)}
  .recent-id{font-family:'DM Mono',monospace;color:var(--text);flex:1;font-size:11px}
  .recent-time{color:#3A5C3A;font-size:10px}
  .recent-status{font-size:10px;padding:2px 7px;border-radius:10px;font-weight:600}
  .recent-status.valid{background:rgba(0,230,118,.1);color:var(--green)}
  .recent-status.invalid{background:rgba(255,23,68,.1);color:var(--red)}
  .recent-status.expired,.recent-status.used{background:rgba(255,214,0,.1);color:var(--yellow)}

  /* ── FLASH ── */
  .flash{position:fixed;inset:0;pointer-events:none;z-index:500;opacity:0;transition:opacity .1s}
  .flash.green{background:rgba(0,230,118,.08)}
  .flash.red{background:rgba(255,23,68,.08)}
  .flash.show{opacity:1}

  /* ── LOADING SPINNER ── */
  .spinner {
    display:none; width:16px; height:16px; border:2px solid rgba(0,0,0,.2);
    border-top-color:#000; border-radius:50%; animation:spin .6s linear infinite;
  }
  @keyframes spin{to{transform:rotate(360deg)}}
  .loading .spinner{display:block}
  .loading .btn-text{display:none}

  @media (max-width:768px) {
    main{grid-template-columns:1fr;padding:16px}
    .qr-viewport{aspect-ratio:auto;height:280px}
  }
</style>
</head>
<body>

<div class="flash" id="flash"></div>


<!-- ── MAIN ── -->
<main>

  <!-- LEFT: Scanner Panel -->
  <div class="scanner-panel">
    <div class="panel-label">Ticket Scanner</div>

    <div class="qr-viewport" id="qrViewport">
      <video id="cameraFeed" autoplay playsinline muted></video>
      <canvas id="scanCanvas" style="display:none"></canvas>
      <div class="cam-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M15 3h6v6M9 3H3v6M15 21h6v-6M9 21H3v-6"/>
          <rect x="7" y="7" width="4" height="4" rx=".5"/>
          <rect x="13" y="7" width="4" height="4" rx=".5"/>
          <rect x="7" y="13" width="4" height="4" rx=".5"/>
          <rect x="13" y="13" width="4" height="4" rx=".5"/>
        </svg>
        <p>Tap Start Scanning</p>
      </div>
      <div class="scan-beam" id="scanBeam"></div>
      <div class="corners"><span></span><span></span><span></span><span></span></div>
      <div class="viewport-overlay" id="viewportOverlay">
        <div class="viewport-result" id="viewportResult"></div>
      </div>
    </div>

    <div class="status-bar">
      <div class="status-indicator ready" id="statusDot"></div>
      <div class="status-text" id="statusText">Ready — Tap Start Scanning or enter ticket ID below</div>
    </div>

    <div class="controls">
      <button class="btn btn-primary" id="btnScan" onclick="toggleScan()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <span class="btn-text">Start Scanning</span>
        <span class="spinner"></span>
      </button>
      <button class="btn btn-secondary" onclick="refreshStats()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
        Refresh Stats
      </button>
    </div>

    <div class="panel-label">Manual Entry</div>
    <div class="manual-input">
      <input class="ticket-input" id="ticketInput" type="text"
             placeholder="Enter ticket ID e.g. KT20240001" maxlength="20" />
      <button class="btn-validate" id="btnValidate" onclick="validateManual()">Validate</button>
    </div>

    <div class="panel-label" style="margin-top:4px">Today's Stats</div>
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-number green" id="statValid">–</div>
        <div class="stat-label">Valid</div>
      </div>
      <div class="stat-card">
        <div class="stat-number red" id="statInvalid">–</div>
        <div class="stat-label">Invalid</div>
      </div>
      <div class="stat-card">
        <div class="stat-number yellow" id="statTotal">–</div>
        <div class="stat-label">Total</div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Info Panel -->
  <div class="info-panel">
    <div class="panel-label">Passenger Info</div>

    <div class="result-card" id="resultCard">
      <div class="result-header">
        <div class="result-icon" id="resultIcon">🎫</div>
        <div class="result-title" id="resultTitle">Awaiting Scan</div>
      </div>
      <div id="resultBody">
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="M7 15h.01M12 15h.01M17 15h.01M7 11h10M7 7h10"/>
          </svg>
          <p>Scan or enter a ticket ID<br/>to view passenger details</p>
        </div>
      </div>
    </div>

    <div class="panel-label">Recent Scans</div>
    <div class="recent-list" id="recentList">
      <div style="color:#2E4A2E;font-size:12px;text-align:center;padding:16px;">Loading…</div>
    </div>
  </div>

</main>

<!-- jsQR for QR scanning from camera -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

<script>
  // ── STATE ──
  let isScanning = false;
  let cameraStream = null;
  let scanLoop = null;

  // ── ON LOAD: fetch stats + recent ──
  window.addEventListener('DOMContentLoaded', () => {
    refreshStats();
    loadRecent();
  });

  // ── TOGGLE CAMERA SCAN ──
  async function toggleScan() {
    if (isScanning) { stopScan(); return; }

    const btn = document.getElementById('btnScan');
    btn.classList.add('loading');

    try {
      cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      const video = document.getElementById('cameraFeed');
      video.srcObject = cameraStream;
      await video.play();

      isScanning = true;
      document.getElementById('qrViewport').classList.add('scanning');
      document.getElementById('statusDot').className = 'status-indicator scanning';
      document.getElementById('statusText').textContent = 'Scanning… Point camera at passenger QR code';
      btn.classList.remove('loading');
      btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg> Stop Scanning`;
      btn.style.background = '#FF1744';

      startQRLoop();

    } catch (err) {
      btn.classList.remove('loading');
      document.getElementById('statusText').textContent = '⚠ Camera access denied — use manual entry below';
      document.getElementById('statusDot').className = 'status-indicator invalid';
    }
  }

  function stopScan() {
    isScanning = false;
    clearInterval(scanLoop);
    if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
    document.getElementById('cameraFeed').srcObject = null;
    document.getElementById('qrViewport').classList.remove('scanning');
    document.getElementById('statusDot').className = 'status-indicator ready';
    document.getElementById('statusText').textContent = 'Camera stopped — Ready for next scan';
    const btn = document.getElementById('btnScan');
    btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><span class="btn-text">Start Scanning</span><span class="spinner"></span>`;
    btn.style.background = '';
  }

  // ── QR DECODE LOOP ──
  let lastScanned = '';
  let lastScannedTime = 0;

  function startQRLoop() {
    const video = document.getElementById('cameraFeed');
    const canvas = document.getElementById('scanCanvas');
    const ctx = canvas.getContext('2d');

    scanLoop = setInterval(() => {
      if (!isScanning || video.readyState !== video.HAVE_ENOUGH_DATA) return;
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height);

      if (code) {
        const now = Date.now();
        // Debounce — don't re-scan same code within 3 seconds
        if (code.data !== lastScanned || now - lastScannedTime > 3000) {
          lastScanned = code.data;
          lastScannedTime = now;
          stopScan();
          sendValidation(code.data);
        }
      }
    }, 200);
  }

  // ── MANUAL VALIDATE ──
  function validateManual() {
    const val = document.getElementById('ticketInput').value.trim().replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    if (!val) return;
    document.getElementById('ticketInput').value = '';
    sendValidation(val);
  }

  document.getElementById('ticketInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') validateManual();
  });

  // ── AJAX: VALIDATE TICKET ──
  async function sendValidation(ticketId) {
    document.getElementById('statusDot').className = 'status-indicator scanning';
    document.getElementById('statusText').textContent = `Checking ${ticketId}…`;

    const formData = new FormData();
    formData.append('ticket_id', ticketId);

    try {
      const res = await fetch('ajax/validate_ticket.php', { method: 'POST', body: formData });
      const data = await res.json();
      handleResult(data, ticketId);
    } catch (err) {
      document.getElementById('statusText').textContent = '⚠ Server error — check connection';
      document.getElementById('statusDot').className = 'status-indicator invalid';
    }
  }

  // ── HANDLE RESULT ──
  function handleResult(data, rawId) {
    const result = data.scan_result ?? (data.success ? 'valid' : 'invalid');
    const isValid = result === 'valid';
    const isExpired = result === 'expired';
    const isUsed = result === 'used';
    const cssClass = isValid ? 'valid' : 'invalid';

    // Viewport flash
    const overlayText = isValid ? '✓ VALID' : isExpired ? '⚠ EXPIRED' : isUsed ? '⚠ USED' : '✗ INVALID';
    showViewportResult(overlayText, cssClass);
    showFlash(isValid ? 'green' : 'red');

    // Status bar
    document.getElementById('statusDot').className = `status-indicator ${cssClass}`;
    document.getElementById('statusText').textContent = data.message ?? (isValid ? '✓ Valid ticket' : '✗ Invalid ticket');

    // Result card
    const ticket = data.ticket ?? null;
    updateResultCard(ticket, rawId, result);

    // Refresh stats + recent from server
    refreshStats();
    loadRecent();
  }

  // ── UPDATE RESULT CARD ──
  function updateResultCard(ticket, id, result) {
    const isValid = result === 'valid';
    const card = document.getElementById('resultCard');
    const icon = document.getElementById('resultIcon');
    const title = document.getElementById('resultTitle');
    const body = document.getElementById('resultBody');

    const cssClass = isValid ? 'valid' : 'invalid';
    card.className = `result-card ${cssClass}`;
    icon.textContent = isValid ? '✅' : result === 'expired' ? '⚠️' : result === 'used' ? '🔄' : '❌';
    title.textContent = isValid ? 'TICKET VALID' : result === 'expired' ? 'TICKET EXPIRED' : result === 'used' ? 'ALREADY USED' : 'INVALID TICKET';

    if (!ticket) {
      body.innerHTML = `
        <div class="result-body">
          <div class="field"><div class="field-label">Scanned ID</div><div class="field-value mono">${id}</div></div>
          <div class="divider"></div>
          <div class="field"><div class="field-label">Reason</div>
          <div class="field-value" style="color:var(--red);font-size:13px;">Ticket not found in system. Please verify with supervisor.</div></div>
        </div>`;
      return;
    }

    const warningHtml = !isValid
      ? `<div style="background:rgba(255,214,0,.08);border:1px solid rgba(255,214,0,.2);border-radius:8px;padding:10px 12px;font-size:12px;color:var(--yellow);">⚠ ${result === 'used' ? 'Ticket already used — do not allow boarding.' : result === 'expired' ? 'Ticket is for a past journey — do not allow boarding.' : 'Do not allow boarding.'}</div>`
      : '';

    body.innerHTML = `
      <div class="result-body">
        <div class="field"><div class="field-label">Passenger Name</div><div class="field-value">${esc(ticket.name)}</div></div>
        <div class="field"><div class="field-label">Ticket ID</div><div class="field-value mono">${esc(ticket.ticket_id)}</div></div>
        <div class="divider"></div>
        <div class="field"><div class="field-label">Route</div>
          <div class="route-badge">
            <span class="route-from">${esc(ticket.from)}</span>
            <span class="route-arrow">→</span>
            <span class="route-to">${esc(ticket.to)}</span>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><div class="field-label">Date</div><div class="field-value" style="font-size:13px">${esc(ticket.date)}</div></div>
          <div class="field"><div class="field-label">Departure</div><div class="field-value" style="font-size:13px">${esc(ticket.time)}</div></div>
        </div>
        <div class="field-row">
          <div class="field"><div class="field-label">Seat</div><div class="seat-badge">${esc(ticket.seat)}</div></div>
          <div class="field"><div class="field-label">Class</div><div class="field-value">${esc(ticket.class)}</div></div>
        </div>
        <div class="divider"></div>
        <div class="field-row">
          <div class="field"><div class="field-label">Bus</div><div class="field-value mono" style="font-size:12px">${esc(ticket.bus)}</div></div>
          <div class="field"><div class="field-label">Phone</div><div class="field-value" style="font-size:12px">${esc(ticket.phone)}</div></div>
        </div>
        ${warningHtml}
      </div>`;
  }

  // ── AJAX: STATS ──
  async function refreshStats() {
    try {
      const res = await fetch('ajax/get_stats.php');
      const d = await res.json();
      document.getElementById('statValid').textContent   = d.valid   ?? '0';
      document.getElementById('statInvalid').textContent = d.invalid ?? '0';
      document.getElementById('statTotal').textContent   = d.total   ?? '0';
    } catch(e) {}
  }

  // ── AJAX: RECENT SCANS ──
  async function loadRecent() {
    try {
      const res = await fetch('ajax/get_recent.php');
      const items = await res.json();
      const list = document.getElementById('recentList');

      if (!items.length) {
        list.innerHTML = `<div style="color:#2E4A2E;font-size:12px;text-align:center;padding:16px;">No scans yet today</div>`;
        return;
      }

      list.innerHTML = items.map(s => {
        const cls = s.scan_result === 'valid' ? 'valid' : (s.scan_result === 'expired' || s.scan_result === 'used') ? 'expired' : 'invalid';
        const label = s.scan_result.toUpperCase();
        return `
          <div class="recent-item">
            <div class="recent-dot ${cls}"></div>
            <div class="recent-id">${esc(s.ticket_id)}</div>
            <div class="recent-time">${esc(s.time)}</div>
            <div class="recent-status ${cls}">${label}</div>
          </div>`;
      }).join('');
    } catch(e) {}
  }

  // ── HELPERS ──
  function showViewportResult(text, type) {
    const overlay = document.getElementById('viewportOverlay');
    const result  = document.getElementById('viewportResult');
    overlay.className = `viewport-overlay ${type} show`;
    result.textContent = text;
    setTimeout(() => overlay.classList.remove('show'), 2500);
  }

  function showFlash(color) {
    const flash = document.getElementById('flash');
    flash.className = `flash ${color} show`;
    setTimeout(() => flash.classList.remove('show'), 200);
  }

  function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
</script>
</body>
</html>
