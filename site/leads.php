<?php
/**
 * Sai Sreenivasa Decorin — Lead Vault & Admin Dashboard
 * Access via: https://ssdecorin.com/leads or https://ssdecorin.com/leads.php
 */

declare(strict_types=1);

// Set default timezone to Indian Standard Time (IST, UTC+5:30)
date_default_timezone_set('Asia/Kolkata');

session_start();

$config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [
    'leads_password' => 'Decorin@2026',
    'storage_dir' => __DIR__ . '/logs',
    'admin_email' => 'saisc26@gmail.com'
];

$authPass = $config['leads_password'] ?? 'Decorin@2026';
$adminEmail = $config['admin_email'] ?? 'saisc26@gmail.com';
$storageDir = $config['storage_dir'] ?? (__DIR__ . '/logs');
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
}
$jsonFile = $storageDir . '/enquiries.json';
$backupFile = $storageDir . '/enquiries_backup.json';

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

// Helper to load raw leads
function loadLeads(string $file): array {
    if (!file_exists($file)) return [];
    $raw = @file_get_contents($file);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Helper to save leads with atomic safety and backup
function saveLeads(string $file, string $backupFile, array $data): bool {
    // 1. Back up existing file
    if (file_exists($file)) {
        @copy($file, $backupFile);
    }
    // 2. Write new data with exclusive lock
    $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return @file_put_contents($file, $payload, LOCK_EX) !== false;
}

// Handle POST Actions for Authenticated Admin
$flashMessage = '';
$flashType = 'info';

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    $leads = loadLeads($jsonFile);

    if ($action === 'add_lead') {
        $name = trim((string)($_POST['name'] ?? ''));
        $contact = trim((string)($_POST['contact'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $service = trim((string)($_POST['service'] ?? 'General Enquiry'));
        $stage = trim((string)($_POST['stage'] ?? ''));
        $timeline = trim((string)($_POST['timeline'] ?? ''));
        $details = trim((string)($_POST['details'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'NEW'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($name === '' || $contact === '') {
            $flashMessage = 'Lead creation failed: Client Name and Contact are required.';
            $flashType = 'error';
        } else {
            $leadId = 'SSD-' . date('ymd') . '-' . strtoupper(substr(md5($name . $contact . microtime()), 0, 4));
            $newLead = [
                'id' => $leadId,
                'timestamp' => date('c'),
                'status' => $status,
                'name' => $name,
                'city' => $city ?: 'Not specified',
                'contact' => $contact,
                'service' => $service,
                'stage' => $stage,
                'timeline' => $timeline,
                'details' => $details,
                'notes' => $notes,
                'source' => 'Manual Entry',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Admin Panel'
            ];
            // Prepend new lead so it's first
            array_unshift($leads, $newLead);
            if (saveLeads($jsonFile, $backupFile, $leads)) {
                $flashMessage = "Lead #{$leadId} added successfully!";
                $flashType = 'success';
            } else {
                $flashMessage = 'Failed to write lead to database.';
                $flashType = 'error';
            }
        }
    } elseif ($action === 'delete_lead') {
        $targetId = trim((string)($_POST['lead_id'] ?? ''));
        $targetIndex = isset($_POST['lead_index']) ? (int)$_POST['lead_index'] : -1;
        $deleted = false;

        $newLeads = [];
        foreach ($leads as $idx => $lead) {
            $id = $lead['id'] ?? '';
            if ($targetId !== '' && $id === $targetId) {
                $deleted = true;
                continue; // drop this record
            }
            if ($targetId === '' && $targetIndex === $idx) {
                $deleted = true;
                continue;
            }
            $newLeads[] = $lead;
        }

        if ($deleted) {
            saveLeads($jsonFile, $backupFile, $newLeads);
            $flashMessage = "Lead deleted successfully. (Safety backup preserved)";
            $flashType = 'success';
        } else {
            $flashMessage = 'Could not find lead to delete.';
            $flashType = 'error';
        }
    } elseif ($action === 'update_status') {
        $targetId = trim((string)($_POST['lead_id'] ?? ''));
        $targetIndex = isset($_POST['lead_index']) ? (int)$_POST['lead_index'] : -1;
        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        $newNotes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : null;
        $updated = false;

        foreach ($leads as $idx => &$lead) {
            $id = $lead['id'] ?? '';
            if (($targetId !== '' && $id === $targetId) || ($targetId === '' && $targetIndex === $idx)) {
                if ($newStatus !== '') {
                    $lead['status'] = $newStatus;
                }
                if ($newNotes !== null) {
                    $lead['notes'] = $newNotes;
                }
                $lead['updated_at'] = date('c');
                $updated = true;
                break;
            }
        }
        unset($lead);

        if ($updated) {
            saveLeads($jsonFile, $backupFile, $leads);
            $flashMessage = "Lead updated successfully.";
            $flashType = 'success';
        }
    }

    // Redirect to self to prevent form re-submission on refresh
    if (!isset($_GET['ajax'])) {
        header("Location: leads.php?msg=" . urlencode($flashMessage) . "&type=" . urlencode($flashType));
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $flashMessage]);
        exit;
    }
}

// Get flash messages from GET redirect
if (isset($_GET['msg'])) {
    $flashMessage = (string)$_GET['msg'];
    $flashType = (string)($_GET['type'] ?? 'info');
}

// Load Enquiries for Display
$rawLeads = loadLeads($jsonFile);

// Ensure every lead has an ID and index reference
$enquiries = [];
foreach ($rawLeads as $i => $item) {
    if (empty($item['id'])) {
        $ts = !empty($item['timestamp']) ? strtotime((string)$item['timestamp']) : time();
        $item['id'] = 'SSD-' . date('ymd', $ts) . '-' . substr(md5(($item['name'] ?? '') . $i), 0, 4);
    }
    $item['_index'] = $i;
    $enquiries[] = $item;
}

// Handle CSV Export
if ($isAuthed && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ssdecorin_leads_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($out, ['Lead ID', 'Date & Time (IST)', 'Client Name', 'Contact', 'City', 'Service Needed', 'Project Stage', 'Timeline', 'Scope Details', 'Status', 'Admin Notes', 'Source', 'IP Address']);
    foreach ($enquiries as $row) {
        $ts = !empty($row['timestamp']) ? strtotime((string)$row['timestamp']) : time();
        $dateIst = date('d M Y, h:i A', $ts) . ' IST';
        fputcsv($out, [
            $row['id'] ?? '',
            $dateIst,
            $row['name'] ?? '',
            $row['contact'] ?? '',
            $row['city'] ?? '',
            $row['service'] ?? '',
            $row['stage'] ?? '',
            $row['timeline'] ?? '',
            $row['details'] ?? '',
            $row['status'] ?? 'NEW',
            $row['notes'] ?? '',
            $row['source'] ?? 'Website Form',
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
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5" />
  <title>Lead Vault — Sai Sreenivasa Decorin</title>
  <link rel="icon" type="image/png" sizes="128x128" href="assets/favicon.png" />
  <link rel="stylesheet" href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700,800,900&display=swap" />
  <style>
    :root {
      --bg: #070c16;
      --card-bg: rgba(12, 19, 34, 0.88);
      --card-border: rgba(212, 176, 106, 0.24);
      --card-hover: rgba(212, 176, 106, 0.4);
      --brass: #d4b06a;
      --brass-2: #f1e0b8;
      --brass-dark: #8c6a28;
      --emerald: #16684f;
      --text: #f4f6fb;
      --mute: #9aa3b5;
      --border-subtle: rgba(255, 255, 255, 0.08);
      --font: 'Satoshi', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background-color: var(--bg);
      background-image: 
        radial-gradient(circle at 50% 0%, rgba(22, 104, 79, 0.3), transparent 60%),
        linear-gradient(rgba(212, 176, 106, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(212, 176, 106, 0.03) 1px, transparent 1px);
      background-size: 100% 100%, 48px 48px, 48px 48px;
      color: var(--text);
      font-family: var(--font);
      min-height: 100vh;
      padding: clamp(0.75rem, 2.5vw, 1.8rem);
      -webkit-font-smoothing: antialiased;
    }
    .shell { max-width: 1320px; margin: 0 auto; }

    /* Flash Notice */
    .flash-banner {
      padding: 0.85rem 1.2rem;
      border-radius: 12px;
      margin-bottom: 1.2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      font-size: 0.9rem;
      font-weight: 600;
      animation: fadeIn 0.3s ease;
    }
    .flash-success { background: rgba(34, 197, 94, 0.16); border: 1px solid rgba(34, 197, 94, 0.4); color: #86efac; }
    .flash-error { background: rgba(239, 68, 68, 0.16); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
    .flash-info { background: rgba(212, 176, 106, 0.16); border: 1px solid rgba(212, 176, 106, 0.4); color: var(--brass-2); }
    .flash-close { background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; }

    /* Login Screen */
    .login-wrap { min-height: 80vh; display: grid; place-items: center; }
    .login-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 24px;
      padding: clamp(1.8rem, 4vw, 2.6rem);
      width: 100%;
      max-width: 440px;
      text-align: center;
      backdrop-filter: blur(20px);
      box-shadow: 0 24px 70px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.04);
    }
    .login-logo { width: 72px; height: 72px; border-radius: 20px; margin: 0 auto 1.2rem; border: 1px solid var(--brass); object-fit: cover; }
    .login-title { font-weight: 800; font-size: 1.6rem; color: var(--brass-2); margin-bottom: 0.4rem; letter-spacing: -0.01em; }
    .login-sub { font-size: 0.9rem; color: var(--mute); margin-bottom: 1.8rem; line-height: 1.4; }
    .input-pass {
      width: 100%;
      padding: 0.95rem 1rem;
      background: rgba(4, 8, 16, 0.7);
      border: 1px solid rgba(212, 176, 106, 0.35);
      border-radius: 12px;
      color: #fff;
      font-size: 1.05rem;
      font-family: inherit;
      margin-bottom: 1.1rem;
      text-align: center;
      letter-spacing: 0.15em;
    }
    .input-pass:focus { outline: none; border-color: var(--brass); box-shadow: 0 0 16px rgba(212, 176, 106, 0.35); }
    .btn-login {
      width: 100%;
      padding: 0.95rem;
      border: none;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--brass-2), var(--brass));
      color: #120e06;
      font-weight: 800;
      font-size: 1rem;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(212, 176, 106, 0.4); }
    .err-msg { color: #f87171; font-size: 0.85rem; margin-top: 0.9rem; font-weight: 600; }

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
      border-radius: 20px;
      margin-bottom: 1.4rem;
      backdrop-filter: blur(16px);
      box-shadow: 0 12px 36px rgba(0,0,0,0.3);
    }
    .dash-brand { display: flex; align-items: center; gap: 1rem; }
    .dash-brand img { width: 48px; height: 48px; border-radius: 12px; border: 1px solid var(--brass); object-fit: cover; }
    .dash-brand h1 { font-weight: 800; font-size: 1.35rem; color: var(--brass-2); letter-spacing: -0.01em; }
    .dash-brand p { font-size: 0.82rem; color: var(--mute); margin-top: 2px; }
    .dash-brand .admin-badge {
      display: inline-block;
      font-size: 0.72rem;
      padding: 2px 8px;
      background: rgba(34, 197, 94, 0.15);
      border: 1px solid rgba(34, 197, 94, 0.3);
      color: #86efac;
      border-radius: 999px;
      margin-left: 6px;
      font-weight: 600;
    }
    .dash-actions { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.6rem 1rem;
      border-radius: 10px;
      font-size: 0.86rem;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
      white-space: nowrap;
    }
    .btn-primary { background: linear-gradient(135deg, var(--brass-2), var(--brass)); color: #120e06; box-shadow: 0 4px 14px rgba(212, 176, 106, 0.25); }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(212, 176, 106, 0.4); }
    .btn-ghost { background: rgba(255,255,255,0.06); color: var(--text); border: 1px solid var(--border-subtle); }
    .btn-ghost:hover { background: rgba(255,255,255,0.12); color: #fff; }

    /* Stats Overview */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 1.4rem;
    }
    .stat-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 1.1rem 1.3rem;
      backdrop-filter: blur(12px);
      transition: transform 0.2s, border-color 0.2s;
    }
    .stat-card:hover { border-color: var(--brass); transform: translateY(-2px); }
    .stat-card small { display: block; font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--brass); margin-bottom: 0.35rem; font-weight: 700; }
    .stat-card strong { font-size: 1.7rem; color: #fff; font-weight: 900; }
    .stat-card em { font-size: 0.78rem; font-style: normal; color: var(--mute); display: block; margin-top: 4px; }

    /* Controls Bar & Filters */
    .controls-wrap {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 1rem 1.2rem;
      margin-bottom: 1.4rem;
      display: flex;
      flex-direction: column;
      gap: 0.9rem;
      backdrop-filter: blur(12px);
    }
    .search-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.8rem;
    }
    .search-box {
      flex: 1 1 260px;
      padding: 0.75rem 1.1rem;
      background: rgba(4, 8, 16, 0.6);
      border: 1px solid var(--card-border);
      border-radius: 10px;
      color: #fff;
      font-size: 0.92rem;
      font-family: inherit;
    }
    .search-box:focus { outline: none; border-color: var(--brass); box-shadow: 0 0 12px rgba(212, 176, 106, 0.25); }
    .select-svc {
      flex: 0 0 auto;
      padding: 0.75rem 1rem;
      background: rgba(4, 8, 16, 0.6);
      border: 1px solid var(--card-border);
      border-radius: 10px;
      color: #fff;
      font-size: 0.88rem;
      font-family: inherit;
      cursor: pointer;
    }
    .filter-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }
    .chip-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--mute); font-weight: 700; margin-right: 0.3rem; }
    .chip {
      padding: 0.35rem 0.8rem;
      border-radius: 999px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.12);
      color: var(--mute);
      font-size: 0.82rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
    }
    .chip:hover { color: #fff; background: rgba(255,255,255,0.1); }
    .chip.is-active {
      background: linear-gradient(135deg, var(--brass-2), var(--brass));
      color: #120e06;
      border-color: var(--brass);
    }

    /* Leads Table (Desktop) */
    .table-wrap {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      overflow: hidden;
      backdrop-filter: blur(14px);
      box-shadow: 0 16px 45px rgba(0,0,0,0.5);
      margin-bottom: 2rem;
    }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th {
      background: rgba(22, 104, 79, 0.28);
      padding: 1rem 1.1rem;
      font-size: 0.76rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--brass-2);
      border-bottom: 1px solid var(--card-border);
      font-weight: 800;
    }
    td {
      padding: 1.1rem 1.1rem;
      border-bottom: 1px solid var(--border-subtle);
      font-size: 0.88rem;
      vertical-align: top;
    }
    tr:hover td { background: rgba(212, 176, 106, 0.025); }
    .lead-id { font-family: monospace; font-size: 0.76rem; color: var(--brass); margin-bottom: 4px; font-weight: 700; }
    .lead-name { font-weight: 800; color: #fff; margin-bottom: 0.25rem; font-size: 1rem; }
    .lead-city { font-size: 0.8rem; color: var(--mute); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 4px; }
    .lead-service {
      display: inline-block;
      padding: 0.3rem 0.65rem;
      border-radius: 6px;
      background: rgba(212, 176, 106, 0.14);
      border: 1px solid rgba(212, 176, 106, 0.32);
      color: var(--brass-2);
      font-size: 0.78rem;
      font-weight: 700;
      margin-bottom: 0.45rem;
    }
    .lead-details { font-size: 0.84rem; color: #cbd5e1; line-height: 1.5; white-space: pre-wrap; max-width: 360px; word-break: break-word; }
    .lead-notes {
      margin-top: 0.5rem;
      padding: 0.45rem 0.65rem;
      background: rgba(0,0,0,0.3);
      border-left: 2px solid var(--brass);
      border-radius: 4px;
      font-size: 0.8rem;
      color: #e2e8f0;
      font-style: italic;
    }
    .lead-contact { font-family: monospace; font-size: 0.9rem; color: #f1e0b8; margin-bottom: 0.5rem; }
    
    /* Contact Action Buttons */
    .contact-btns { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.4rem; }
    .c-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.45rem 0.75rem;
      border-radius: 8px;
      font-size: 0.78rem;
      font-weight: 700;
      text-decoration: none;
      color: #fff;
      transition: transform 0.15s, opacity 0.2s;
      min-height: 34px;
    }
    .c-btn:hover { transform: translateY(-1px); opacity: 0.92; }
    .c-wa { background: #25d366; color: #072e13; font-weight: 800; }
    .c-tel { background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.15); }
    .c-mail { background: rgba(212, 176, 106, 0.18); color: var(--brass-2); border: 1px solid rgba(212, 176, 106, 0.38); }
    
    /* Status Badges & Selector */
    .status-select {
      background: rgba(4, 8, 16, 0.7);
      border: 1px solid var(--card-border);
      border-radius: 8px;
      color: #fff;
      padding: 0.35rem 0.6rem;
      font-size: 0.76rem;
      font-weight: 700;
      cursor: pointer;
    }
    .badge-status {
      display: inline-block;
      font-size: 0.72rem;
      padding: 0.25rem 0.6rem;
      border-radius: 6px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .st-NEW { background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.4); }
    .st-CONTACTED { background: rgba(234, 179, 8, 0.2); color: #fde047; border: 1px solid rgba(234, 179, 8, 0.4); }
    .st-DISCUSSION { background: rgba(168, 85, 247, 0.2); color: #d8b4fe; border: 1px solid rgba(168, 85, 247, 0.4); }
    .st-CONVERTED { background: rgba(34, 197, 94, 0.2); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.4); }
    .st-CLOSED { background: rgba(148, 163, 184, 0.15); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.3); }

    .btn-delete {
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #fca5a5;
      padding: 0.35rem 0.65rem;
      border-radius: 6px;
      font-size: 0.74rem;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
    }
    .btn-delete:hover { background: rgba(239, 68, 68, 0.3); color: #fff; }

    .date-col { font-size: 0.8rem; color: var(--mute); white-space: nowrap; line-height: 1.4; }

    /* Mobile Cards View (Hidden on desktop, shown on mobile <= 820px) */
    .mobile-cards-wrap { display: none; }
    
    @media (max-width: 820px) {
      .table-wrap { display: none; }
      .mobile-cards-wrap { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
      .m-lead-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 18px;
        padding: 1.2rem;
        backdrop-filter: blur(14px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.4);
      }
      .m-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.6rem;
        margin-bottom: 0.6rem;
      }
      .m-card-meta { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; margin-bottom: 0.75rem; }
      .m-card-scope {
        font-size: 0.86rem;
        color: #e2e8f0;
        background: rgba(0,0,0,0.25);
        padding: 0.75rem 0.9rem;
        border-radius: 10px;
        border: 1px solid var(--border-subtle);
        margin: 0.75rem 0;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-word;
      }
      .m-actions-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.6rem;
        margin-top: 0.9rem;
      }
      .m-actions-grid .c-btn {
        justify-content: center;
        min-height: 44px; /* Proper mobile touch target */
        font-size: 0.88rem;
      }
      .m-actions-grid .c-wa { grid-column: span 2; }
      .m-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.8rem;
        margin-top: 0.9rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border-subtle);
      }
      .m-card-footer .status-select { flex: 1; min-height: 40px; }
      .m-card-footer .btn-delete { min-height: 40px; padding: 0 1rem; }
    }

    /* Modal Styling */
    .modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(4, 7, 14, 0.82);
      backdrop-filter: blur(12px);
      z-index: 1000;
      place-items: center;
      padding: 1rem;
      overflow-y: auto;
    }
    .modal-backdrop.is-open { display: grid; }
    .modal-card {
      background: #0d1527;
      border: 1px solid var(--brass);
      border-radius: 20px;
      padding: clamp(1.4rem, 3.5vw, 2.2rem);
      width: 100%;
      max-width: 560px;
      box-shadow: 0 24px 70px rgba(0,0,0,0.8);
      position: relative;
    }
    .modal-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.3rem;
      padding-bottom: 0.8rem;
      border-bottom: 1px solid var(--border-subtle);
    }
    .modal-head h2 { font-size: 1.35rem; color: var(--brass-2); font-weight: 800; }
    .modal-close {
      background: none;
      border: none;
      color: var(--mute);
      font-size: 1.5rem;
      cursor: pointer;
      line-height: 1;
    }
    .modal-close:hover { color: #fff; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--brass-2); margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .form-control {
      width: 100%;
      padding: 0.75rem 0.95rem;
      background: rgba(4, 8, 16, 0.7);
      border: 1px solid rgba(212, 176, 106, 0.3);
      border-radius: 10px;
      color: #fff;
      font-size: 0.92rem;
      font-family: inherit;
    }
    .form-control:focus { outline: none; border-color: var(--brass); box-shadow: 0 0 10px rgba(212, 176, 106, 0.3); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
    @media (max-width: 520px) { .form-row { grid-template-columns: 1fr; } }
    .btn-submit {
      width: 100%;
      padding: 0.9rem;
      border: none;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--brass-2), var(--brass));
      color: #120e06;
      font-weight: 800;
      font-size: 1rem;
      cursor: pointer;
      margin-top: 0.6rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(212, 176, 106, 0.4); }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>

<?php if (!$isAuthed): ?>
  <!-- Login Screen -->
  <div class="shell login-wrap">
    <div class="login-card">
      <img class="login-logo" src="assets/logo-mark.jpg" alt="Sai Sreenivasa Decorin" />
      <h1 class="login-title">Lead Vault</h1>
      <p class="login-sub">Protected Admin Dashboard &middot; ssdecorin.com<br><small style="color:var(--mute)">Timezone: Indian Standard Time (IST)</small></p>
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
    
    <?php if ($flashMessage): ?>
      <div class="flash-banner flash-<?= htmlspecialchars($flashType) ?>">
        <span><?= htmlspecialchars($flashMessage) ?></span>
        <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
      </div>
    <?php endif; ?>

    <header class="dash-head">
      <div class="dash-brand">
        <img src="assets/logo-mark.jpg" alt="" />
        <div>
          <h1>Sai Sreenivasa Decorin <span class="admin-badge">Admin IST</span></h1>
          <p>Real-Time Enquiry Vault &middot; ssdecorin.com &middot; Alerts to: <strong style="color:var(--brass-2)"><?= htmlspecialchars($adminEmail) ?></strong></p>
        </div>
      </div>
      <div class="dash-actions">
        <button class="btn-action btn-primary" type="button" onclick="openAddModal()">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          + Add Lead
        </button>
        <a class="btn-action btn-ghost" href="?export=csv">
          <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
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
      $newCount = 0;
      $convertedCount = 0;
      $contactedCount = 0;

      foreach ($enquiries as $e) {
        $st = strtoupper((string)($e['status'] ?? 'NEW'));
        if ($st === 'NEW' || $st === 'PENDING' || $st === 'RECORDED' || $st === 'SENT') {
          $newCount++;
        } elseif ($st === 'CONTACTED') {
          $contactedCount++;
        } elseif ($st === 'CONVERTED') {
          $convertedCount++;
        }

        if (!empty($e['timestamp']) && strpos((string)$e['timestamp'], $todayDate) === 0) {
          $todayCount++;
        }
      }
      $latestTime = !empty($enquiries[0]['timestamp']) ? date('d M, h:i A', strtotime((string)$enquiries[0]['timestamp'])) . ' IST' : 'None yet';
      $conversionRate = $totalLeads > 0 ? round(($convertedCount / $totalLeads) * 100) : 0;
    ?>

    <div class="stats-row">
      <div class="stat-card">
        <small>Total Enquiries</small>
        <strong><?= $totalLeads ?></strong>
        <em>All-time records</em>
      </div>
      <div class="stat-card">
        <small>New / Action Needed</small>
        <strong style="color: #93c5fd;"><?= $newCount ?></strong>
        <em>Awaiting response</em>
      </div>
      <div class="stat-card">
        <small>Received Today</small>
        <strong style="color: #fde047;"><?= $todayCount ?></strong>
        <em><?= date('d M Y') ?></em>
      </div>
      <div class="stat-card">
        <small>Converted</small>
        <strong style="color: #86efac;"><?= $convertedCount ?> <span style="font-size:0.95rem;font-weight:600;color:var(--mute)">(<?= $conversionRate ?>%)</span></strong>
        <em>Successful clients</em>
      </div>
      <div class="stat-card">
        <small>Storage Vault</small>
        <strong style="color: #86efac; font-size:1.2rem;">Live Backed Up</strong>
        <em>Latest: <?= $latestTime ?></em>
      </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="controls-wrap">
      <div class="search-row">
        <input class="search-box" id="searchBox" type="text" placeholder="Search by name, phone, city, keyword, notes..." />
        <select class="select-svc" id="serviceFilter">
          <option value="">All Services</option>
          <option value="Site Measurement">Site Measurement</option>
          <option value="Interio Check">Interio Check</option>
          <option value="Modular Drafting">Modular Drafting</option>
          <option value="Interior Training">Interior Training</option>
          <option value="Vendor Directory">Vendor Directory</option>
          <option value="Home Owner Consultant">Home Owner Consultant</option>
          <option value="Homeowner Consultant">Homeowner Consultant (Legacy)</option>
          <option value="General Enquiry">General Enquiry</option>
        </select>
      </div>
      <div class="filter-chips">
        <span class="chip-label">Filter:</span>
        <button class="chip is-active" type="button" data-status-filter="">All (<?= $totalLeads ?>)</button>
        <button class="chip" type="button" data-status-filter="NEW">New (<?= $newCount ?>)</button>
        <button class="chip" type="button" data-status-filter="CONTACTED">Contacted (<?= $contactedCount ?>)</button>
        <button class="chip" type="button" data-status-filter="CONVERTED">Converted (<?= $convertedCount ?>)</button>
        <button class="chip" type="button" data-status-filter="CLOSED">Closed</button>
      </div>
    </div>

    <!-- Desktop Data Table -->
    <div class="table-wrap">
      <table id="leadsTable">
        <thead>
          <tr>
            <th>Date &amp; ID</th>
            <th>Client Info</th>
            <th>Service &amp; Project Scope</th>
            <th>Stage &amp; Timeline</th>
            <th>Status &amp; Notes</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($enquiries)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 3.5rem; color: var(--mute);">
                No enquiries recorded yet. Use "+ Add Lead" above to record client leads manually, or submit the website contact form to test real-time capture.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($enquiries as $row): ?>
              <?php
                $id = htmlspecialchars((string)($row['id'] ?? ''));
                $idx = (int)($row['_index'] ?? 0);
                $name = htmlspecialchars((string)($row['name'] ?? 'Anonymous'));
                $city = htmlspecialchars((string)($row['city'] ?? 'Not specified'));
                $contact = htmlspecialchars((string)($row['contact'] ?? ''));
                $service = htmlspecialchars((string)($row['service'] ?? 'General Enquiry'));
                $stage = htmlspecialchars((string)($row['stage'] ?? ''));
                $timeline = htmlspecialchars((string)($row['timeline'] ?? ''));
                $details = htmlspecialchars((string)($row['details'] ?? ''));
                $notes = htmlspecialchars((string)($row['notes'] ?? ''));
                
                $rawStatus = strtoupper((string)($row['status'] ?? 'NEW'));
                if (in_array($rawStatus, ['PENDING', 'SENT', 'RECORDED', 'NEW'])) {
                  $displayStatus = 'NEW';
                } elseif (in_array($rawStatus, ['CONTACTED', 'DISCUSSION', 'CONVERTED', 'CLOSED'])) {
                  $displayStatus = $rawStatus;
                } else {
                  $displayStatus = 'NEW';
                }

                $ts = !empty($row['timestamp']) ? strtotime((string)$row['timestamp']) : time();
                $dateFormatted = date('d M Y', $ts);
                $timeFormatted = date('h:i A', $ts) . ' IST';

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
              <tr class="lead-row" data-status="<?= $displayStatus ?>" data-service="<?= htmlspecialchars($service) ?>" data-lead-id="<?= $id ?>" data-lead-index="<?= $idx ?>">
                <td class="date-col">
                  <div class="lead-id">#<?= $id ?></div>
                  <div><?= $dateFormatted ?></div>
                  <small style="color:var(--mute)"><?= $timeFormatted ?></small>
                </td>
                <td>
                  <div class="lead-name"><?= $name ?></div>
                  <div class="lead-city">&#x1F4CD; <?= $city ?></div>
                  <div class="lead-contact"><?= $contact ?></div>
                  <div class="contact-btns">
                    <?php if ($cleanDigits && strlen($cleanDigits) >= 10): ?>
                      <a class="c-btn c-wa" href="https://wa.me/<?= $waNum ?>?text=<?= $waText ?>" target="_blank" rel="noopener noreferrer">
                        <span>WhatsApp</span>
                      </a>
                      <a class="c-btn c-tel" href="tel:+<?= $waNum ?>">
                        <span>Call</span>
                      </a>
                    <?php endif; ?>
                    <?php if ($isEmail): ?>
                      <a class="c-btn c-mail" href="mailto:<?= $contact ?>">
                        <span>Email</span>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <span class="lead-service"><?= $service ?></span>
                  <?php if ($details): ?>
                    <div class="lead-details"><?= $details ?></div>
                  <?php endif; ?>
                  <?php if ($notes): ?>
                    <div class="lead-notes"><strong>Note:</strong> <?= $notes ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="font-size:0.86rem;color:#fff;font-weight:700"><?= $stage ?: '—' ?></div>
                  <small style="color:var(--mute)"><?= $timeline ? "Timeline: {$timeline}" : '' ?></small>
                </td>
                <td>
                  <form method="POST" style="display:inline-block; margin-bottom: 0.35rem;">
                    <input type="hidden" name="action" value="update_status" />
                    <input type="hidden" name="lead_id" value="<?= $id ?>" />
                    <input type="hidden" name="lead_index" value="<?= $idx ?>" />
                    <select class="status-select st-<?= $displayStatus ?>" name="new_status" onchange="this.form.submit()">
                      <option value="NEW" <?= $displayStatus === 'NEW' ? 'selected' : '' ?>>New</option>
                      <option value="CONTACTED" <?= $displayStatus === 'CONTACTED' ? 'selected' : '' ?>>Contacted</option>
                      <option value="DISCUSSION" <?= $displayStatus === 'DISCUSSION' ? 'selected' : '' ?>>In Discussion</option>
                      <option value="CONVERTED" <?= $displayStatus === 'CONVERTED' ? 'selected' : '' ?>>Converted</option>
                      <option value="CLOSED" <?= $displayStatus === 'CLOSED' ? 'selected' : '' ?>>Closed</option>
                    </select>
                  </form>
                </td>
                <td style="text-align: right;">
                  <form method="POST" onsubmit="return confirm('Are you sure you want to delete lead #<?= $id ?> (<?= $name ?>)? A safety backup will be created.');">
                    <input type="hidden" name="action" value="delete_lead" />
                    <input type="hidden" name="lead_id" value="<?= $id ?>" />
                    <input type="hidden" name="lead_index" value="<?= $idx ?>" />
                    <button class="btn-delete" type="submit" title="Delete lead">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile Feed (Cards) -->
    <div class="mobile-cards-wrap" id="mobileCards">
      <?php if (empty($enquiries)): ?>
        <div class="m-lead-card" style="text-align:center; color:var(--mute); padding: 2.5rem 1rem;">
          No enquiries recorded yet. Use "+ Add Lead" above to record client leads manually.
        </div>
      <?php else: ?>
        <?php foreach ($enquiries as $row): ?>
          <?php
            $id = htmlspecialchars((string)($row['id'] ?? ''));
            $idx = (int)($row['_index'] ?? 0);
            $name = htmlspecialchars((string)($row['name'] ?? 'Anonymous'));
            $city = htmlspecialchars((string)($row['city'] ?? 'Not specified'));
            $contact = htmlspecialchars((string)($row['contact'] ?? ''));
            $service = htmlspecialchars((string)($row['service'] ?? 'General Enquiry'));
            $stage = htmlspecialchars((string)($row['stage'] ?? ''));
            $timeline = htmlspecialchars((string)($row['timeline'] ?? ''));
            $details = htmlspecialchars((string)($row['details'] ?? ''));
            $notes = htmlspecialchars((string)($row['notes'] ?? ''));

            $rawStatus = strtoupper((string)($row['status'] ?? 'NEW'));
            if (in_array($rawStatus, ['PENDING', 'SENT', 'RECORDED', 'NEW'])) {
              $displayStatus = 'NEW';
            } elseif (in_array($rawStatus, ['CONTACTED', 'DISCUSSION', 'CONVERTED', 'CLOSED'])) {
              $displayStatus = $rawStatus;
            } else {
              $displayStatus = 'NEW';
            }

            $ts = !empty($row['timestamp']) ? strtotime((string)$row['timestamp']) : time();
            $dateFormatted = date('d M Y, h:i A', $ts) . ' IST';

            $cleanDigits = preg_replace('/[^0-9]/', '', $contact);
            if (strlen($cleanDigits) === 10) {
              $waNum = '91' . $cleanDigits;
            } else {
              $waNum = $cleanDigits;
            }
            $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL);
            $waText = rawurlencode("Hello {$name}, thank you for contacting Sai Sreenivasa Decorin regarding your {$service} project. We would be happy to assist you!");
          ?>
          <div class="m-lead-card lead-row" data-status="<?= $displayStatus ?>" data-service="<?= htmlspecialchars($service) ?>" data-lead-id="<?= $id ?>" data-lead-index="<?= $idx ?>">
            <div class="m-card-top">
              <div>
                <div class="lead-id">#<?= $id ?></div>
                <div class="lead-name"><?= $name ?></div>
                <div class="lead-city">&#x1F4CD; <?= $city ?></div>
              </div>
              <span class="badge-status st-<?= $displayStatus ?>"><?= $displayStatus ?></span>
            </div>

            <div class="m-card-meta">
              <span class="lead-service"><?= $service ?></span>
              <?php if ($stage): ?><span class="chip" style="font-size:0.74rem"><?= $stage ?></span><?php endif; ?>
              <?php if ($timeline): ?><span class="chip" style="font-size:0.74rem"><?= $timeline ?></span><?php endif; ?>
            </div>

            <div class="lead-contact"><?= $contact ?></div>

            <?php if ($details): ?>
              <div class="m-card-scope"><?= $details ?></div>
            <?php endif; ?>

            <?php if ($notes): ?>
              <div class="lead-notes" style="margin-bottom:0.75rem"><strong>Note:</strong> <?= $notes ?></div>
            <?php endif; ?>

            <div style="font-size:0.76rem; color:var(--mute); margin-bottom: 0.6rem;">Received: <?= $dateFormatted ?></div>

            <div class="m-actions-grid">
              <?php if ($cleanDigits && strlen($cleanDigits) >= 10): ?>
                <a class="c-btn c-wa" href="https://wa.me/<?= $waNum ?>?text=<?= $waText ?>" target="_blank" rel="noopener noreferrer">
                  <span>Chat on WhatsApp</span>
                </a>
                <a class="c-btn c-tel" href="tel:+<?= $waNum ?>">
                  <span>Call Client</span>
                </a>
              <?php endif; ?>
              <?php if ($isEmail): ?>
                <a class="c-btn c-mail" href="mailto:<?= $contact ?>">
                  <span>Send Email</span>
                </a>
              <?php endif; ?>
            </div>

            <div class="m-card-footer">
              <form method="POST" style="flex:1;">
                <input type="hidden" name="action" value="update_status" />
                <input type="hidden" name="lead_id" value="<?= $id ?>" />
                <input type="hidden" name="lead_index" value="<?= $idx ?>" />
                <select class="status-select st-<?= $displayStatus ?>" name="new_status" onchange="this.form.submit()" style="width:100%;">
                  <option value="NEW" <?= $displayStatus === 'NEW' ? 'selected' : '' ?>>Status: New</option>
                  <option value="CONTACTED" <?= $displayStatus === 'CONTACTED' ? 'selected' : '' ?>>Status: Contacted</option>
                  <option value="DISCUSSION" <?= $displayStatus === 'DISCUSSION' ? 'selected' : '' ?>>Status: Discussion</option>
                  <option value="CONVERTED" <?= $displayStatus === 'CONVERTED' ? 'selected' : '' ?>>Status: Converted</option>
                  <option value="CLOSED" <?= $displayStatus === 'CLOSED' ? 'selected' : '' ?>>Status: Closed</option>
                </select>
              </form>
              <form method="POST" onsubmit="return confirm('Delete lead #<?= $id ?>?');">
                <input type="hidden" name="action" value="delete_lead" />
                <input type="hidden" name="lead_id" value="<?= $id ?>" />
                <input type="hidden" name="lead_index" value="<?= $idx ?>" />
                <button class="btn-delete" type="submit">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <!-- Add Lead Modal -->
  <div class="modal-backdrop" id="addLeadModal" onclick="if(event.target===this) closeAddModal()">
    <div class="modal-card">
      <div class="modal-head">
        <h2>+ Add New Lead</h2>
        <button class="modal-close" type="button" onclick="closeAddModal()">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_lead" />
        <div class="form-row">
          <div class="form-group">
            <label>Client Name *</label>
            <input class="form-control" type="text" name="name" required placeholder="e.g. Ramesh Varma" />
          </div>
          <div class="form-group">
            <label>Contact (Phone / Email) *</label>
            <input class="form-control" type="text" name="contact" required placeholder="e.g. 9876543210 or email" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>City</label>
            <input class="form-control" type="text" name="city" placeholder="e.g. Hyderabad / Bengaluru" />
          </div>
          <div class="form-group">
            <label>Service</label>
            <select class="form-control" name="service">
              <option value="Site Measurement">Site Measurement</option>
              <option value="Interio Check">Interio Check</option>
              <option value="Modular Drafting">Modular Drafting</option>
              <option value="Interior Training">Interior Training</option>
              <option value="Vendor Directory">Vendor Directory</option>
              <option value="Home Owner Consultant">Home Owner Consultant</option>
          <option value="Homeowner Consultant">Homeowner Consultant (Legacy)</option>
              <option value="General Enquiry">General Enquiry</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Project Stage</label>
            <select class="form-control" name="stage">
              <option value="Planning / Pre-construction">Planning / Pre-construction</option>
              <option value="Handover / Bare Shell">Handover / Bare Shell</option>
              <option value="Woodwork Underway">Woodwork Underway</option>
              <option value="Final Finishing & Snagging">Final Finishing &amp; Snagging</option>
              <option value="Renovation">Renovation</option>
            </select>
          </div>
          <div class="form-group">
            <label>Initial Status</label>
            <select class="form-control" name="status">
              <option value="NEW">New</option>
              <option value="CONTACTED">Contacted</option>
              <option value="DISCUSSION">In Discussion</option>
              <option value="CONVERTED">Converted</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Scope / Project Details</label>
          <textarea class="form-control" name="details" rows="2" placeholder="e.g. 3BHK 1600 sq.ft at Kondapur, needs quote audit..."></textarea>
        </div>
        <div class="form-group">
          <label>Internal Admin Notes</label>
          <textarea class="form-control" name="notes" rows="2" placeholder="Private internal note..."></textarea>
        </div>
        <button class="btn-submit" type="submit">Save Lead to Vault</button>
      </form>
    </div>
  </div>

  <script>
    // Modal Helpers
    function openAddModal() {
      document.getElementById('addLeadModal').classList.add('is-open');
    }
    function closeAddModal() {
      document.getElementById('addLeadModal').classList.remove('is-open');
    }
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAddModal();
    });

    // Real-Time Filter & Search Logic
    const searchInput = document.getElementById('searchBox');
    const serviceFilter = document.getElementById('serviceFilter');
    const statusChips = document.querySelectorAll('[data-status-filter]');
    let activeStatus = '';

    function filterRows() {
      const q = (searchInput ? searchInput.value : '').toLowerCase().trim();
      const s = (serviceFilter ? serviceFilter.value : '').toLowerCase().trim();
      const rows = document.querySelectorAll('.lead-row');

      rows.forEach((r) => {
        const text = r.textContent.toLowerCase();
        const rowStatus = (r.dataset.status || '').toUpperCase();
        const rowSvc = (r.dataset.service || '').toLowerCase();

        const matchesQuery = !q || text.includes(q);
        const matchesService = !s || rowSvc.includes(s);
        const matchesStatus = !activeStatus || rowStatus === activeStatus;

        r.style.display = (matchesQuery && matchesService && matchesStatus) ? '' : 'none';
      });
    }

    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (serviceFilter) serviceFilter.addEventListener('change', filterRows);

    statusChips.forEach((chip) => {
      chip.addEventListener('click', () => {
        statusChips.forEach((c) => c.classList.remove('is-active'));
        chip.classList.add('is-active');
        activeStatus = chip.dataset.statusFilter.toUpperCase();
        filterRows();
      });
    });
  </script>
<?php endif; ?>

</body>
</html>