<?php
/**
 * Sai Sreenivasa Decorin — Lead Vault & Admin Dashboard
 * Access via: https://ssdecorin.com/leads or https://ssdecorin.com/leads.php
 */

declare(strict_types=1);

session_start();

$config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [
    'leads_password' => 'Decorin@2026',
    'storage_dir' => __DIR__ . '/logs'
];

$authPass = $config['leads_password'] ?? 'Decorin@2026';
$storageDir = $config['storage_dir'] ?? (__DIR__ . '/logs');
$jsonFile = $storageDir . '/enquiries.json';

// Handle Logout
if (isset($_GET['logout'])) {
    $_SESSION['decorin_auth'] = false;
    session_destroy();
    header('Location: leads.php');
    exit;
}

// Handle Login
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ((string)$_POST['password'] === $authPass) {
        $_SESSION['decorin_auth'] = true;
        header('Location: leads.php');
        exit;
    } else {
        $loginError = 'Invalid passcode. Please try again.';
    }
}

// Authentication Check
$isAuthed = !empty($_SESSION['decorin_auth']);

// Load Enquiries
$enquiries = [];
if (file_exists($jsonFile)) {
    $raw = (string)file_get_contents($jsonFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $enquiries = array_reverse($decoded); // newest first
    }
}

// Handle CSV Export
if ($isAuthed && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ssdecorin_leads_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Timestamp (ISO)', 'Date (IST)', 'Client Name', 'City', 'Contact Info', 'Service Needed', 'Project Stage', 'Timeline', 'Project Scope', 'Delivery Status', 'IP Address']);
    foreach ($enquiries as $row) {
        $ts = !empty($row['timestamp']) ? strtotime((string)$row['timestamp']) : time();
        $dateIst = date('d M Y, h:i A', $ts) . ' IST';
        fputcsv($out, [
            $row['timestamp'] ?? '',
            $dateIst,
            $row['name'] ?? '',
            $row['city'] ?? '',
            $row['contact'] ?? '',
            $row['service'] ?? '',
            $row['stage'] ?? '',
            $row['timeline'] ?? '',
            $row['details'] ?? '',
            $row['status'] ?? 'RECORDED',
            $row['ip'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Lead Vault — Sai Sreenivasa Decorin</title>
  <link rel="icon" type="image/png" sizes="128x128" href="assets/favicon.png" />
  <link rel="stylesheet" href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700,800,900&display=swap" />
  <style>
    :root {
      --bg: #070c16;
      --card-bg: rgba(12, 19, 34, 0.82);
      --card-border: rgba(212, 176, 106, 0.22);
      --brass: #d4b06a;
      --brass-2: #f1e0b8;
      --emerald: #16684f;
      --text: #f4f6fb;
      --mute: #9aa3b5;
      --font: 'Manrope', -apple-system, sans-serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background-color: var(--bg);
      background-image: radial-gradient(circle at 50% 0%, rgba(22, 104, 79, 0.25), transparent 60%), linear-gradient(rgba(212, 176, 106, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(212, 176, 106, 0.03) 1px, transparent 1px);
      background-size: 100% 100%, 48px 48px, 48px 48px;
      color: var(--text);
      font-family: var(--font);
      min-height: 100vh;
      padding: 1.5rem;
    }
    .shell { max-width: 1200px; margin: 0 auto; }
    
    /* Login Screen */
    .login-wrap { min-height: 80vh; display: grid; place-items: center; }
    .login-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 2.4rem;
      width: 100%;
      max-width: 420px;
      text-align: center;
      backdrop-filter: blur(16px);
      box-shadow: 0 24px 60px rgba(0,0,0,0.6);
    }
    .login-logo { width: 64px; height: 64px; border-radius: 16px; margin: 0 auto 1.2rem; border: 1px solid var(--brass); }
    .login-title { font-family: 'Satoshi', sans-serif; font-weight: 700; font-size: 1.45rem; color: var(--brass-2); margin-bottom: 0.4rem; }
    .login-sub { font-size: 0.88rem; color: var(--mute); margin-bottom: 1.6rem; }
    .input-pass {
      width: 100%;
      padding: 0.85rem 1rem;
      background: rgba(4, 8, 16, 0.6);
      border: 1px solid rgba(212, 176, 106, 0.35);
      border-radius: 10px;
      color: #fff;
      font-size: 1rem;
      font-family: var(--font);
      margin-bottom: 1rem;
      text-align: center;
      letter-spacing: 0.15em;
    }
    .input-pass:focus { outline: none; border-color: var(--brass); box-shadow: 0 0 12px rgba(212, 176, 106, 0.35); }
    .btn-login {
      width: 100%;
      padding: 0.85rem;
      border: none;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--brass-2), var(--brass));
      color: #120e06;
      font-weight: 700;
      font-size: 0.95rem;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(212, 176, 106, 0.4); }
    .err-msg { color: #f87171; font-size: 0.82rem; margin-top: 0.8rem; }

    /* Dashboard Header */
    .dash-head {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 1.2rem;
      padding: 1.2rem 1.6rem;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      margin-bottom: 1.5rem;
      backdrop-filter: blur(12px);
    }
    .dash-brand { display: flex; align-items: center; gap: 1rem; }
    .dash-brand img { width: 44px; height: 44px; border-radius: 10px; border: 1px solid var(--brass); }
    .dash-brand h1 { font-family: 'Satoshi', sans-serif; font-weight: 700; font-size: 1.3rem; color: var(--brass-2); }
    .dash-brand p { font-size: 0.82rem; color: var(--mute); }
    .dash-actions { display: flex; align-items: center; gap: 0.6rem; }
    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.55rem 0.95rem;
      border-radius: 8px;
      font-size: 0.84rem;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-csv { background: linear-gradient(135deg, var(--brass-2), var(--brass)); color: #120e06; border: none; }
    .btn-csv:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(212, 176, 106, 0.35); }
    .btn-ghost { background: rgba(255,255,255,0.06); color: var(--mute); border: 1px solid rgba(255,255,255,0.1); }
    .btn-ghost:hover { background: rgba(255,255,255,0.1); color: #fff; }

    /* Stats Overview */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .stat-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 14px;
      padding: 1.2rem;
      backdrop-filter: blur(10px);
    }
    .stat-card small { display: block; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--brass); margin-bottom: 0.35rem; }
    .stat-card strong { font-size: 1.6rem; color: #fff; font-weight: 800; }

    /* Controls Bar */
    .controls {
      display: flex;
      flex-wrap: wrap;
      gap: 0.8rem;
      margin-bottom: 1.2rem;
    }
    .search-box {
      flex: 1 1 280px;
      padding: 0.7rem 1rem;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 10px;
      color: #fff;
      font-size: 0.9rem;
      font-family: var(--font);
    }
    .search-box:focus { outline: none; border-color: var(--brass); }

    /* Leads Table */
    .table-wrap {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      overflow: hidden;
      backdrop-filter: blur(12px);
      box-shadow: 0 16px 40px rgba(0,0,0,0.4);
    }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th {
      background: rgba(22, 104, 79, 0.2);
      padding: 0.85rem 1rem;
      font-size: 0.76rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--brass);
      border-bottom: 1px solid var(--card-border);
    }
    td {
      padding: 1rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      font-size: 0.88rem;
      vertical-align: top;
    }
    tr:hover td { background: rgba(255, 255, 255, 0.02); }
    .lead-name { font-weight: 700; color: #fff; margin-bottom: 0.2rem; font-size: 0.95rem; }
    .lead-city { font-size: 0.78rem; color: var(--mute); }
    .lead-service { display: inline-block; padding: 0.25rem 0.55rem; border-radius: 6px; background: rgba(212, 176, 106, 0.15); border: 1px solid rgba(212, 176, 106, 0.3); color: var(--brass-2); font-size: 0.78rem; font-weight: 600; margin-bottom: 0.35rem; }
    .lead-details { font-size: 0.82rem; color: #cbd5e1; line-height: 1.5; white-space: pre-wrap; max-width: 380px; word-break: break-word; }
    .lead-contact { font-family: monospace; font-size: 0.86rem; color: #f1e0b8; margin-bottom: 0.4rem; }
    .contact-btns { display: flex; gap: 0.4rem; flex-wrap: wrap; }
    .c-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.3rem 0.6rem;
      border-radius: 6px;
      font-size: 0.76rem;
      font-weight: 600;
      text-decoration: none;
      color: #fff;
      transition: opacity 0.2s;
    }
    .c-wa { background: #25d366; color: #083316; }
    .c-tel { background: rgba(255,255,255,0.12); color: #fff; }
    .c-mail { background: rgba(212, 176, 106, 0.2); color: var(--brass-2); border: 1px solid rgba(212, 176, 106, 0.4); }
    .c-btn:hover { opacity: 0.85; }
    .badge-status { font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; text-transform: uppercase; }
    .badge-sent { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
    .badge-failed { background: rgba(248, 113, 113, 0.15); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3); }
    .date-col { font-size: 0.8rem; color: var(--mute); white-space: nowrap; }

    @media (max-width: 860px) {
      body { padding: 0.8rem; }
      th:nth-child(4), td:nth-child(4) { display: none; }
    }
    @media (max-width: 600px) {
      .table-wrap { overflow-x: auto; }
      .dash-head { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>

<?php if (!$isAuthed): ?>
  <!-- Login Screen -->
  <div class="shell login-wrap">
    <div class="login-card">
      <img class="login-logo" src="assets/logo-mark.jpg" alt="Sai Sreenivasa Decorin" />
      <h1 class="login-title">Lead Vault</h1>
      <p class="login-sub">Enter passcode to view ssdecorin.com customer enquiries</p>
      <form method="POST">
        <input class="input-pass" type="password" name="password" placeholder="Passcode" autofocus required />
        <button class="btn-login" type="submit">Unlock Dashboard</button>
      </form>
      <?php if ($loginError): ?>
        <p class="err-msg"><?= htmlspecialchars($loginError) ?></p>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <!-- Authenticated Dashboard -->
  <div class="shell">
    <header class="dash-head">
      <div class="dash-brand">
        <img src="assets/logo-mark.jpg" alt="" />
        <div>
          <h1>Sai Sreenivasa Decorin</h1>
          <p>Real-Time Enquiry Vault &middot; ssdecorin.com</p>
        </div>
      </div>
      <div class="dash-actions">
        <a class="btn-action btn-csv" href="?export=csv">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
          Export CSV
        </a>
        <a class="btn-action btn-ghost" href="leads.php">Refresh</a>
        <a class="btn-action btn-ghost" href="?logout=1">Lock</a>
      </div>
    </header>

    <?php
      $totalLeads = count($enquiries);
      $todayCount = 0;
      $todayDate = date('Y-m-d');
      foreach ($enquiries as $e) {
        if (!empty($e['timestamp']) && strpos((string)$e['timestamp'], $todayDate) === 0) {
          $todayCount++;
        }
      }
      $latestTime = !empty($enquiries[0]['timestamp']) ? date('h:i A', strtotime((string)$enquiries[0]['timestamp'])) . ' IST' : 'None yet';
    ?>

    <div class="stats-row">
      <div class="stat-card">
        <small>Total Enquiries</small>
        <strong><?= $totalLeads ?></strong>
      </div>
      <div class="stat-card">
        <small>Enquiries Today</small>
        <strong><?= $todayCount ?></strong>
      </div>
      <div class="stat-card">
        <small>Latest Submission</small>
        <strong><?= $latestTime ?></strong>
      </div>
      <div class="stat-card">
        <small>Storage Vault</small>
        <strong style="color: #4ade80;">100% Backed Up</strong>
      </div>
    </div>

    <div class="controls">
      <input class="search-box" id="searchBox" type="text" placeholder="Search by name, phone, city, or keyword..." />
    </div>

    <div class="table-wrap">
      <table id="leadsTable">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Client Info</th>
            <th>Service &amp; Project Scope</th>
            <th>Stage &amp; Timeline</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($enquiries)): ?>
            <tr>
              <td colspan="5" style="text-align: center; padding: 3rem; color: var(--mute);">
                No enquiries recorded yet. Once clients submit the website contact form, they will appear here instantaneously.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($enquiries as $row): ?>
              <?php
                $name = htmlspecialchars((string)($row['name'] ?? 'Anonymous'));
                $city = htmlspecialchars((string)($row['city'] ?? 'Not specified'));
                $contact = htmlspecialchars((string)($row['contact'] ?? ''));
                $service = htmlspecialchars((string)($row['service'] ?? 'General Enquiry'));
                $stage = htmlspecialchars((string)($row['stage'] ?? ''));
                $timeline = htmlspecialchars((string)($row['timeline'] ?? ''));
                $details = htmlspecialchars((string)($row['details'] ?? ''));
                $status = ($row['status'] ?? 'SENT') === 'SENT' ? 'SENT' : 'LOGGED';

                $ts = !empty($row['timestamp']) ? strtotime((string)$row['timestamp']) : time();
                $dateFormatted = date('d M Y, h:i A', $ts);

                // Phone clean for WA / Tel
                $cleanDigits = preg_replace('/[^0-9]/', '', $contact);
                if (strlen($cleanDigits) === 10) {
                  $waNum = '91' . $cleanDigits;
                } else {
                  $waNum = $cleanDigits;
                }
                $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL);
                $waText = rawurlencode("Hello {$name}, thank you for contacting Sai Sreenivasa Decorin regarding your {$service} project. We would be happy to assist you!");
              ?>
              <tr class="lead-row">
                <td class="date-col"><?= $dateFormatted ?><br><small style="color:var(--mute)">IST</small></td>
                <td>
                  <div class="lead-name"><?= $name ?></div>
                  <div class="lead-city">&#x1F4CD; <?= $city ?></div>
                  <div class="lead-contact"><?= $contact ?></div>
                  <div class="contact-btns">
                    <?php if ($cleanDigits && strlen($cleanDigits) >= 10): ?>
                      <a class="c-btn c-wa" href="https://wa.me/<?= $waNum ?>?text=<?= $waText ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                      <a class="c-btn c-tel" href="tel:+<?= $waNum ?>">Call</a>
                    <?php endif; ?>
                    <?php if ($isEmail): ?>
                      <a class="c-btn c-mail" href="mailto:<?= $contact ?>">Email</a>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <span class="lead-service"><?= $service ?></span>
                  <div class="lead-details"><?= $details ?></div>
                </td>
                <td>
                  <div style="font-size:0.84rem;color:#fff;font-weight:600"><?= $stage ?: '—' ?></div>
                  <small style="color:var(--mute)"><?= $timeline ? "Timeline: {$timeline}" : '' ?></small>
                </td>
                <td>
                  <span class="badge-status <?= $status === 'SENT' ? 'badge-sent' : 'badge-failed' ?>">
                    <?= $status === 'SENT' ? 'Dispatched' : 'Recorded' ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    // Live Search Filter
    const search = document.getElementById('searchBox');
    const table = document.getElementById('leadsTable');
    if (search && table) {
      search.addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase().trim();
        const rows = table.querySelectorAll('.lead-row');
        rows.forEach((r) => {
          const text = r.textContent.toLowerCase();
          r.style.display = text.includes(q) ? '' : 'none';
        });
      });
    }
  </script>
<?php endif; ?>

</body>
</html>
