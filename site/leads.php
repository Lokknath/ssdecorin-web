<?php
/**
 * Sai Sreenivasa Decorin — Lead Vault & Admin Dashboard
 * Access via: https://ssdecorin.com/leads or https://ssdecorin.com/leads.php
 */

declare(strict_types=1);

// Prevent browser and proxy caching (ensures instant real-time updates)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

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

// Helper to save leads with atomic safety and auto-backup
function saveLeads(string $file, string $backupFile, array $data): bool {
    if (file_exists($file)) {
        @copy($file, $backupFile);
    }
    $payload = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return @file_put_contents($file, $payload, LOCK_EX) !== false;
}

// Helper to load and normalize raw leads
function loadLeads(string $file, string $backupFile): array {
    if (!file_exists($file)) return [];
    $raw = @file_get_contents($file);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];

    $changed = false;
    foreach ($data as $i => &$item) {
        if (!is_array($item)) continue;
        if (empty($item['id'])) {
            $ts = !empty($item['timestamp']) ? strtotime((string)$item['timestamp']) : time();
            $item['id'] = 'SSD-' . date('ymd', $ts) . '-' . strtoupper(substr(md5(($item['name'] ?? '') . $i . microtime()), 0, 4));
            $changed = true;
        }
        $currStatus = strtoupper(trim((string)($item['status'] ?? '')));
        if ($currStatus === '' || in_array($currStatus, ['SENT', 'LOGGED_ONLY', 'PENDING'])) {
            $item['status'] = 'NEW';
            $changed = true;
        }
        if (!isset($item['notes'])) {
            $item['notes'] = '';
            $changed = true;
        }
    }
    unset($item);

    // Persist permanently if any IDs or fields were added
    if ($changed) {
        saveLeads($file, $backupFile, $data);
    }
    return $data;
}

// Handle POST Actions for Authenticated Admin
$flashMessage = '';
$flashType = 'info';

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    $leads = loadLeads($jsonFile, $backupFile);

    if ($action === 'add_lead') {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $contact = trim((string)($_POST['contact'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $service = trim((string)($_POST['service'] ?? 'General Enquiry'));
        $stage = trim((string)($_POST['stage'] ?? ''));
        $timeline = trim((string)($_POST['timeline'] ?? ''));
        $details = trim((string)($_POST['details'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'NEW'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($phone === '' && $contact !== '') {
            if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                $email = $contact;
            } else {
                $phone = $contact;
            }
        }
        if ($contact === '') {
            $contact = ($email !== '') ? "{$phone} · {$email}" : $phone;
        }

        if ($name === '' || $phone === '') {
            $flashMessage = 'Lead creation failed: Client name and phone number are required.';
            $flashType = 'error';
        } else {
            $leadId = 'SSD-' . date('ymd') . '-' . strtoupper(substr(md5($name . $phone . microtime()), 0, 4));
            $newLead = [
                'id' => $leadId,
                'timestamp' => date('c'),
                'status' => $status,
                'name' => $name,
                'city' => $city ?: 'Not specified',
                'phone' => $phone,
                'email' => $email,
                'contact' => $contact,
                'service' => $service,
                'stage' => $stage,
                'timeline' => $timeline,
                'details' => $details,
                'notes' => $notes,
                'source' => 'Manual Entry',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Admin Panel'
            ];
            array_unshift($leads, $newLead);
            if (saveLeads($jsonFile, $backupFile, $leads)) {
                $flashMessage = "Lead #{$leadId} added successfully.";
                $flashType = 'success';
            } else {
                $flashMessage = 'Failed to save lead to database.';
                $flashType = 'error';
            }
        }
    } elseif ($action === 'delete_lead') {
        $targetId = trim((string)($_POST['lead_id'] ?? ''));
        $targetIndex = isset($_POST['lead_index']) ? (int)$_POST['lead_index'] : -1;
        $deleted = false;

        $newLeads = [];
        foreach ($leads as $idx => $lead) {
            $id = trim((string)($lead['id'] ?? ''));
            // Match by ID or by Index
            if ($targetId !== '' && strcasecmp($id, $targetId) === 0) {
                $deleted = true;
                continue;
            }
            if ($targetIndex >= 0 && $targetIndex === $idx) {
                $deleted = true;
                continue;
            }
            $newLeads[] = $lead;
        }

        if ($deleted) {
            saveLeads($jsonFile, $backupFile, $newLeads);
            $flashMessage = 'Lead deleted successfully.';
            $flashType = 'success';
        } else {
            $flashMessage = 'Could not find lead to delete.';
            $flashType = 'error';
        }
    } elseif ($action === 'update_status') {
        $targetId = trim((string)($_POST['lead_id'] ?? ''));
        $targetIndex = isset($_POST['lead_index']) ? (int)$_POST['lead_index'] : -1;
        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        $updated = false;

        foreach ($leads as $idx => &$lead) {
            $id = trim((string)($lead['id'] ?? ''));
            if (($targetId !== '' && strcasecmp($id, $targetId) === 0) || ($targetIndex >= 0 && $targetIndex === $idx)) {
                if ($newStatus !== '') {
                    $lead['status'] = $newStatus;
                }
                $lead['updated_at'] = date('c');
                $updated = true;
                break;
            }
        }
        unset($lead);

        if ($updated) {
            saveLeads($jsonFile, $backupFile, $leads);
            $statusLabel = ($newStatus === 'DISCUSSION') ? 'In Discussion' : ucfirst(strtolower($newStatus));
            $flashMessage = "Lead status updated to {$statusLabel}.";
            $flashType = 'success';
        } else {
            $flashMessage = 'Could not find lead to update status.';
            $flashType = 'error';
        }
    } elseif ($action === 'update_notes') {
        $targetId = trim((string)($_POST['lead_id'] ?? ''));
        $targetIndex = isset($_POST['lead_index']) ? (int)$_POST['lead_index'] : -1;
        $newNotes = trim((string)($_POST['notes'] ?? ''));
        $updated = false;

        foreach ($leads as $idx => &$lead) {
            $id = trim((string)($lead['id'] ?? ''));
            if (($targetId !== '' && strcasecmp($id, $targetId) === 0) || ($targetIndex >= 0 && $targetIndex === $idx)) {
                $lead['notes'] = $newNotes;
                $lead['updated_at'] = date('c');
                $updated = true;
                break;
            }
        }
        unset($lead);

        if ($updated) {
            saveLeads($jsonFile, $backupFile, $leads);
            $flashMessage = 'Notes saved successfully.';
            $flashType = 'success';
        } else {
            $flashMessage = 'Could not find lead to update notes.';
            $flashType = 'error';
        }
    } elseif ($action === 'import_backup') {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $flashMessage = 'Backup upload failed: Please choose a valid JSON backup file.';
            $flashType = 'error';
        } else {
            $tmpPath = (string)$_FILES['backup_file']['tmp_name'];
            $origName = (string)$_FILES['backup_file']['name'];
            $fileExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if ($fileExt !== 'json') {
                $flashMessage = 'Invalid file type: Please upload a .json backup file.';
                $flashType = 'error';
            } else {
                $rawContent = @file_get_contents($tmpPath);
                $importedData = $rawContent ? json_decode($rawContent, true) : null;

                if (!is_array($importedData)) {
                    $flashMessage = 'Import error: The uploaded file is not a valid JSON array of leads.';
                    $flashType = 'error';
                } else {
                    // Pre-import safety snapshot before merging
                    $preImportSnapshot = $storageDir . '/enquiries_pre_import_' . date('Ymd_His') . '.json';
                    if (file_exists($jsonFile)) {
                        @copy($jsonFile, $preImportSnapshot);
                    }

                    // Existing leads indexed by ID and contact+name
                    $existingIds = [];
                    $existingContacts = [];
                    foreach ($leads as $idx => $lead) {
                        $lid = strtoupper(trim((string)($lead['id'] ?? '')));
                        if ($lid !== '') $existingIds[$lid] = $idx;
                        $cContact = trim((string)($lead['phone'] ?? $lead['contact'] ?? ''));
                        $cKey = strtolower($cContact) . '|' . strtolower(trim((string)($lead['name'] ?? '')));
                        if ($cKey !== '|') $existingContacts[$cKey] = $idx;
                    }

                    $addedCount = 0;
                    $mergedNotesCount = 0;
                    $preservedCount = 0;

                    foreach ($importedData as $item) {
                        if (!is_array($item)) continue;
                        $itemId = strtoupper(trim((string)($item['id'] ?? '')));
                        $itemContact = trim((string)($item['phone'] ?? $item['contact'] ?? ''));
                        $itemName = trim((string)($item['name'] ?? ''));
                        $cKey = strtolower($itemContact) . '|' . strtolower($itemName);

                        $isExisting = false;
                        $existingIdx = -1;

                        if ($itemId !== '' && isset($existingIds[$itemId])) {
                            $isExisting = true;
                            $existingIdx = $existingIds[$itemId];
                        } elseif ($cKey !== '|' && isset($existingContacts[$cKey])) {
                            $isExisting = true;
                            $existingIdx = $existingContacts[$cKey];
                        }

                        if ($isExisting && $existingIdx >= 0) {
                            // Merge notes if current lead notes are blank and backup has notes
                            $curNotes = trim((string)($leads[$existingIdx]['notes'] ?? ''));
                            $newNotes = trim((string)($item['notes'] ?? ''));
                            if ($curNotes === '' && $newNotes !== '') {
                                $leads[$existingIdx]['notes'] = $newNotes;
                                $mergedNotesCount++;
                            }
                            $preservedCount++;
                        } else {
                            // Append new lead
                            if (empty($item['id'])) {
                                $ts = !empty($item['timestamp']) ? strtotime((string)$item['timestamp']) : time();
                                $item['id'] = 'SSD-' . date('ymd', $ts) . '-' . strtoupper(substr(md5($itemName . $itemContact . microtime()), 0, 4));
                            }
                            if (empty($item['status'])) {
                                $item['status'] = 'NEW';
                            }
                            if (!isset($item['notes'])) {
                                $item['notes'] = '';
                            }
                            if (empty($item['timestamp'])) {
                                $item['timestamp'] = date('c');
                            }
                            if (empty($item['source'])) {
                                $item['source'] = 'Backup Import';
                            }

                            $leads[] = $item;
                            $newIdx = count($leads) - 1;
                            $existingIds[strtoupper((string)$item['id'])] = $newIdx;
                            if ($cKey !== '|') $existingContacts[$cKey] = $newIdx;
                            $addedCount++;
                        }
                    }

                    if (saveLeads($jsonFile, $backupFile, $leads)) {
                        $flashMessage = "Backup merged successfully! Appended {$addedCount} new lead(s). All {$preservedCount} existing lead(s) preserved intact. Zero data lost.";
                        $flashType = 'success';
                    } else {
                        $flashMessage = 'Failed to write merged backup to disk. Restoring original database snapshot.';
                        if (file_exists($preImportSnapshot)) {
                            @copy($preImportSnapshot, $jsonFile);
                        }
                        $flashType = 'error';
                    }
                }
            }
        }
    }

    if (!isset($_GET['ajax'])) {
        header('Location: leads.php?msg=' . urlencode($flashMessage) . '&type=' . urlencode($flashType));
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $flashMessage]);
        exit;
    }
}

// Flash messages from redirect
if (isset($_GET['msg'])) {
    $flashMessage = (string)$_GET['msg'];
    $flashType = (string)($_GET['type'] ?? 'info');
}

// Load leads for display
$rawLeads = loadLeads($jsonFile, $backupFile);
$enquiries = [];
foreach ($rawLeads as $i => $item) {
    $item['_index'] = $i;
    $enquiries[] = $item;
}

// ALWAYS SORT NEWEST LEADS TO THE VERY TOP (Descending timestamp)
usort($enquiries, function($a, $b) {
    $tsA = !empty($a['timestamp']) ? strtotime((string)$a['timestamp']) : 0;
    $tsB = !empty($b['timestamp']) ? strtotime((string)$b['timestamp']) : 0;
    return $tsB <=> $tsA;
});

// Live Background Poll Endpoint (Checks for new leads instantly)
if ($isAuthed && isset($_GET['api']) && $_GET['api'] === 'poll') {
    header('Content-Type: application/json; charset=UTF-8');
    $first = !empty($enquiries) ? $enquiries[0] : null;
    echo json_encode([
        'total' => count($enquiries),
        'latest_id' => $first['id'] ?? null,
        'latest_name' => $first['name'] ?? null,
        'latest_timestamp' => $first['timestamp'] ?? null,
        'server_time' => date('d M Y, h:i:s A') . ' IST'
    ]);
    exit;
}

// Handle CSV Export
if ($isAuthed && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ssdecorin_leads_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Lead ID', 'Date & Time (IST)', 'Client Name', 'Phone', 'Email', 'Contact (Combined)', 'City', 'Service Needed', 'Project Stage', 'Timeline', 'Scope Details', 'Status', 'Admin Notes', 'Source', 'IP Address']);
    foreach ($enquiries as $row) {
        $ts = !empty($row['timestamp']) ? strtotime((string)$row['timestamp']) : time();
        $dateIst = date('d M Y, h:i A', $ts) . ' IST';
        $rPhone = $row['phone'] ?? '';
        $rEmail = $row['email'] ?? '';
        $rContact = $row['contact'] ?? '';
        if ($rPhone === '' && $rEmail === '' && $rContact !== '') {
            if (filter_var($rContact, FILTER_VALIDATE_EMAIL)) {
                $rEmail = $rContact;
            } else {
                $rPhone = $rContact;
            }
        }
        fputcsv($out, [
            $row['id'] ?? '',
            $dateIst,
            $row['name'] ?? '',
            $rPhone,
            $rEmail,
            $rContact,
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

// Handle JSON Database Backup Download
if ($isAuthed && isset($_GET['export']) && $_GET['export'] === 'backup') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ssdecorin_leads_backup_' . date('Y-m-d_His') . '.json"');
    echo json_encode($enquiries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
      backdrop-filter: blur(16px);
      box-shadow: 0 16px 40px rgba(0,0,0,0.5);
      margin-bottom: 1.5rem;
    }
    .dash-brand { display: flex; align-items: center; gap: 1rem; }
    .dash-brand img { width: 48px; height: 48px; border-radius: 12px; border: 1px solid var(--brass); }
    .dash-brand h1 { font-size: 1.25rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 0.6rem; }
    .dash-brand p { font-size: 0.82rem; color: var(--mute); margin-top: 2px; }
    .admin-badge {
      display: inline-block;
      font-size: 0.7rem;
      padding: 0.2rem 0.55rem;
      background: rgba(22, 104, 79, 0.4);
      border: 1px solid rgba(22, 104, 79, 0.8);
      border-radius: 999px;
      color: #a7f3d0;
      font-weight: 700;
      letter-spacing: 0.04em;
    }
    .dash-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; }
    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.55rem 1rem;
      border-radius: 10px;
      font-size: 0.85rem;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s ease;
      border: none;
      font-family: inherit;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--brass-2), var(--brass));
      color: #120e06;
      box-shadow: 0 4px 14px rgba(212, 176, 106, 0.35);
    }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(212, 176, 106, 0.5); }
    .btn-ghost {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid var(--card-border);
      color: var(--text);
    }
    .btn-ghost:hover { background: rgba(212, 176, 106, 0.15); border-color: var(--brass); color: #fff; }

    /* Live Polling Sync Indicator */
    .live-sync-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.45rem 0.85rem;
      border-radius: 999px;
      background: rgba(34, 197, 94, 0.12);
      border: 1px solid rgba(34, 197, 94, 0.35);
      color: #86efac;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.02em;
    }
    .live-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 10px #22c55e;
      animation: livePulse 2s infinite ease-in-out;
    }
    @keyframes livePulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.4; transform: scale(0.85); }
    }
    .live-toast {
      position: fixed;
      top: 1.5rem;
      right: 1.5rem;
      z-index: 9999;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.9rem 1.4rem;
      border-radius: 14px;
      background: #0f1c35;
      border: 1px solid #22c55e;
      color: #fff;
      font-size: 0.92rem;
      font-weight: 700;
      box-shadow: 0 16px 45px rgba(0, 0, 0, 0.7), 0 0 30px rgba(34, 197, 94, 0.35);
      animation: toastIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes toastIn {
      from { opacity: 0; transform: translateY(-20px) scale(0.95); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Metric Cards */
    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .metric-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 1.1rem 1.3rem;
      backdrop-filter: blur(12px);
    }
    .metric-card small { display: block; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--mute); font-weight: 700; margin-bottom: 0.3rem; }
    .metric-card strong { display: block; font-size: 1.8rem; font-weight: 800; color: var(--brass-2); line-height: 1.1; }
    .metric-card em { display: block; font-style: normal; font-size: 0.78rem; color: var(--mute); margin-top: 0.35rem; }

    /* Search & Filter Bar */
    .controls-wrap {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 1rem 1.2rem;
      margin-bottom: 1.5rem;
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
      backdrop-filter: blur(14px);
    }
    .search-row { display: flex; flex-wrap: wrap; gap: 0.8rem; }
    .search-box {
      flex: 1;
      min-width: 220px;
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
    
    /* Dedicated Summary Notes Column & Card */
    .summary-note-cell {
      display: flex;
      flex-direction: column;
      gap: 0.45rem;
      min-width: 220px;
      max-width: 320px;
    }
    .summary-note-text {
      background: rgba(10, 16, 30, 0.65);
      border-left: 3px solid var(--brass);
      border-radius: 6px;
      padding: 0.55rem 0.75rem;
      font-size: 0.82rem;
      color: #e2e8f0;
      line-height: 1.5;
      word-break: break-word;
      max-height: 120px;
      overflow-y: auto;
    }
    .summary-note-cell.empty {
      background: rgba(255, 255, 255, 0.02);
      border: 1px dashed rgba(255, 255, 255, 0.12);
      border-radius: 8px;
      padding: 0.6rem 0.8rem;
      text-align: center;
      align-items: center;
    }
    .note-hint {
      font-size: 0.76rem;
      color: var(--mute);
      display: block;
      margin-bottom: 0.25rem;
    }
    .btn-note-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      background: rgba(212, 176, 106, 0.14);
      border: 1px solid rgba(212, 176, 106, 0.38);
      color: var(--brass-2);
      padding: 0.3rem 0.75rem;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 700;
      cursor: pointer;
      width: fit-content;
      transition: all 0.2s ease;
    }
    .btn-note-pill:hover {
      background: var(--brass);
      color: #0c1322;
      border-color: var(--brass);
      transform: translateY(-1px);
    }
    .btn-note-add {
      background: rgba(22, 104, 79, 0.25);
      border-color: rgba(34, 197, 94, 0.4);
      color: #86efac;
    }
    .btn-note-add:hover {
      background: #16684f;
      color: #fff;
      border-color: #22c55e;
    }
    
    /* Mobile Note Block */
    .m-card-note-block {
      margin: 0.8rem 0;
      padding: 0.75rem 0.85rem;
      background: rgba(8, 14, 26, 0.8);
      border: 1px solid rgba(212, 176, 106, 0.22);
      border-radius: 10px;
    }
    .m-note-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.45rem;
    }
    .m-note-title {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.78rem;
      color: var(--brass-2);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .m-note-text {
      font-size: 0.84rem;
      color: #e2e8f0;
      line-height: 1.5;
      word-break: break-word;
    }
    .m-note-empty {
      font-size: 0.8rem;
      color: var(--mute);
      font-style: italic;
    }

    /* Note Modal Chips */
    .note-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      margin-bottom: 0.75rem;
    }
    .note-chip-btn {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.14);
      color: #cbd5e1;
      padding: 0.25rem 0.55rem;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s;
    }
    .note-chip-btn:hover {
      background: rgba(212, 176, 106, 0.2);
      border-color: var(--brass);
      color: #fff;
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
      background: rgba(4, 8, 16, 0.85);
      border: 1px solid var(--card-border);
      border-radius: 8px;
      color: #fff;
      padding: 0.45rem 0.75rem;
      font-size: 0.82rem;
      font-weight: 700;
      cursor: pointer;
      transition: border-color 0.2s;
    }
    .status-select:focus { outline: none; border-color: var(--brass); }
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
      padding: 0.45rem 0.8rem;
      border-radius: 8px;
      font-size: 0.78rem;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
    }
    .btn-delete:hover { background: rgba(239, 68, 68, 0.3); color: #fff; }

    .date-col { font-size: 0.8rem; color: var(--mute); white-space: nowrap; line-height: 1.4; }

    /* Mobile Cards View (<= 820px) */
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
        min-height: 44px;
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
      .m-card-footer .status-select { flex: 1; min-height: 42px; }
      .m-card-footer .btn-delete { min-height: 42px; padding: 0 1rem; }
    }

    /* Modal Styling */
    .modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(4, 7, 14, 0.85);
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
      box-shadow: 0 24px 70px rgba(0,0,0,0.85);
      position: relative;
      animation: fadeIn 0.25s ease;
    }
    .modal-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.3rem;
      padding-bottom: 0.8rem;
      border-bottom: 1px solid var(--border-subtle);
    }
    .modal-head h2 { font-size: 1.3rem; color: var(--brass-2); font-weight: 800; }
    .modal-close {
      background: none;
      border: none;
      color: var(--mute);
      font-size: 1.6rem;
      cursor: pointer;
      line-height: 1;
    }
    .modal-close:hover { color: #fff; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--brass-2); margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .form-control {
      width: 100%;
      padding: 0.75rem 0.95rem;
      background: rgba(4, 8, 16, 0.75);
      border: 1px solid rgba(212, 176, 106, 0.3);
      border-radius: 10px;
      color: #fff;
      font-size: 0.92rem;
      font-family: inherit;
    }
    .form-control:focus { outline: none; border-color: var(--brass); box-shadow: 0 0 10px rgba(212, 176, 106, 0.35); }
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
        <div class="live-sync-badge" id="liveSyncBadge" title="Real-time listener active (auto-checks every 8 seconds)">
          <span class="live-dot"></span>
          <span id="liveSyncText">Live Sync: Active</span>
        </div>
        <button class="btn-action btn-primary" type="button" onclick="openAddModal()">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          + Add Lead
        </button>
        <button class="btn-action btn-ghost" type="button" onclick="openImportModal()" title="Upload a JSON backup file to append leads safely">
          <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/></svg>
          Upload Backup (JSON)
        </button>
        <a class="btn-action btn-ghost" href="?export=backup" title="Download complete database JSON backup">
          <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
          Download Backup (JSON)
        </a>
        <a class="btn-action btn-ghost" href="?export=csv" title="Export leads as spreadsheet CSV">
          <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
          Export CSV
        </a>
        <button class="btn-action btn-ghost" type="button" onclick="window.location.reload();" title="Refresh leads view">
          <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
          Refresh
        </button>
        <a class="btn-action btn-ghost" href="?logout=1">Lock</a>
      </div>
    </header>

    <?php
      $totalLeads = count($enquiries);
      $todayCount = 0;
      $todayDate = date('Y-m-d');
      $newCount = 0;
      $contactedCount = 0;
      $discussionCount = 0;
      $convertedCount = 0;
      $closedCount = 0;

      foreach ($enquiries as $e) {
        $st = strtoupper((string)($e['status'] ?? 'NEW'));
        if (in_array($st, ['NEW', 'PENDING', 'RECORDED', 'SENT'])) {
          $newCount++;
        } elseif ($st === 'CONTACTED') {
          $contactedCount++;
        } elseif ($st === 'DISCUSSION' || $st === 'IN DISCUSSION' || $st === 'IN_DISCUSSION') {
          $discussionCount++;
        } elseif ($st === 'CONVERTED') {
          $convertedCount++;
        } elseif ($st === 'CLOSED') {
          $closedCount++;
        }

        if (!empty($e['timestamp']) && strpos((string)$e['timestamp'], $todayDate) === 0) {
          $todayCount++;
        }
      }
      $latestTime = !empty($enquiries[0]['timestamp']) ? date('d M, h:i A', strtotime((string)$enquiries[0]['timestamp'])) . ' IST' : 'None yet';
      $conversionRate = $totalLeads > 0 ? round(($convertedCount / $totalLeads) * 100) : 0;
    ?>

    <!-- Metrics Overview -->
    <div class="metrics-grid">
      <div class="metric-card">
        <small>Total Leads</small>
        <strong><?= $totalLeads ?></strong>
        <em>All active records</em>
      </div>
      <div class="metric-card">
        <small>New Enquiries</small>
        <strong><?= $newCount ?></strong>
        <em>Awaiting first response</em>
      </div>
      <div class="metric-card">
        <small>In Discussion</small>
        <strong><?= $discussionCount ?></strong>
        <em>Quotes &amp; site audits ongoing</em>
      </div>
      <div class="metric-card">
        <small>Conversion Rate</small>
        <strong><?= $conversionRate ?>%</strong>
        <em><?= $convertedCount ?> converted of <?= $totalLeads ?></em>
      </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="controls-wrap">
      <div class="search-row">
        <input class="search-box" id="searchBox" type="text" placeholder="Search by name, phone, city, notes, service, scope..." />
        <select class="select-svc" id="serviceFilter">
          <option value="">All Services</option>
          <option value="Site Measurement">Site Measurement</option>
          <option value="Interio Check">Interio Check</option>
          <option value="Modular Drafting">Modular Drafting</option>
          <option value="Interior Training">Interior Training</option>
          <option value="Vendor Directory">Vendor Directory</option>
          <option value="Home Owner Consultant">Home Owner Consultant</option>
          <option value="General Enquiry">General Enquiry</option>
        </select>
      </div>
      <div class="filter-chips">
        <span class="chip-label">Filter:</span>
        <button class="chip is-active" type="button" data-status-filter="">All (<?= $totalLeads ?>)</button>
        <button class="chip" type="button" data-status-filter="NEW">New (<?= $newCount ?>)</button>
        <button class="chip" type="button" data-status-filter="CONTACTED">Contacted (<?= $contactedCount ?>)</button>
        <button class="chip" type="button" data-status-filter="DISCUSSION">In Discussion (<?= $discussionCount ?>)</button>
        <button class="chip" type="button" data-status-filter="CONVERTED">Converted (<?= $convertedCount ?>)</button>
        <button class="chip" type="button" data-status-filter="CLOSED">Closed (<?= $closedCount ?>)</button>
      </div>
    </div>

    <!-- Desktop Data Table -->
    <div class="table-wrap">
      <table id="leadsTable">
        <thead>
          <tr>
            <th style="width: 130px;">Date &amp; ID</th>
            <th style="width: 200px;">Client &amp; Contact</th>
            <th style="width: 240px;">Service &amp; Scope</th>
            <th style="min-width: 240px;">Summary Note</th>
            <th style="width: 140px;">Status</th>
            <th style="width: 80px; text-align: right;">Action</th>
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
                $phone = htmlspecialchars((string)($row['phone'] ?? ''));
                $email = htmlspecialchars((string)($row['email'] ?? ''));
                $contact = htmlspecialchars((string)($row['contact'] ?? ''));
                if ($phone === '' && $email === '' && $contact !== '') {
                  if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                    $email = $contact;
                  } else {
                    $phone = $contact;
                  }
                }
                $service = htmlspecialchars((string)($row['service'] ?? 'General Enquiry'));
                $stage = htmlspecialchars((string)($row['stage'] ?? ''));
                $timeline = htmlspecialchars((string)($row['timeline'] ?? ''));
                $details = htmlspecialchars((string)($row['details'] ?? ''));
                $notes = htmlspecialchars((string)($row['notes'] ?? ''));
                
                $rawStatus = strtoupper((string)($row['status'] ?? 'NEW'));
                if (in_array($rawStatus, ['PENDING', 'SENT', 'RECORDED', 'NEW'])) {
                  $displayStatus = 'NEW';
                } elseif (in_array($rawStatus, ['CONTACTED', 'CONVERTED', 'CLOSED'])) {
                  $displayStatus = $rawStatus;
                } elseif (in_array($rawStatus, ['DISCUSSION', 'IN DISCUSSION', 'IN_DISCUSSION'])) {
                  $displayStatus = 'DISCUSSION';
                } else {
                  $displayStatus = 'NEW';
                }

                $ts = !empty($row['timestamp']) ? strtotime((string)$row['timestamp']) : time();
                $dateFormatted = date('d M Y', $ts);
                $timeFormatted = date('h:i A', $ts) . ' IST';

                $cleanDigits = preg_replace('/[^0-9]/', '', $phone ?: $contact);
                $hasPhone = (strlen($cleanDigits) >= 8);
                $waNum = (strlen($cleanDigits) === 10) ? ('91' . $cleanDigits) : $cleanDigits;
                $isEmail = filter_var($email ?: $contact, FILTER_VALIDATE_EMAIL);
                $validEmail = $isEmail ? ($email ?: $contact) : '';
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
                  <?php if ($phone): ?>
                    <div class="lead-contact">📞 <?= $phone ?></div>
                  <?php elseif ($contact && !$isEmail): ?>
                    <div class="lead-contact"><?= $contact ?></div>
                  <?php endif; ?>
                  <?php if ($email): ?>
                    <div class="lead-contact" style="font-size:0.82rem; opacity:0.85; margin-top:2px;">✉️ <?= $email ?></div>
                  <?php elseif ($contact && $isEmail): ?>
                    <div class="lead-contact" style="font-size:0.82rem; opacity:0.85; margin-top:2px;">✉️ <?= $contact ?></div>
                  <?php endif; ?>
                  <div class="contact-btns">
                    <?php if ($hasPhone): ?>
                      <a class="c-btn c-wa" href="https://wa.me/<?= $waNum ?>?text=<?= $waText ?>" target="_blank" rel="noopener noreferrer" title="Chat on WhatsApp">
                        <span>WhatsApp</span>
                      </a>
                      <a class="c-btn c-tel" href="tel:+<?= $waNum ?>" title="Call client">
                        <span>Call</span>
                      </a>
                    <?php endif; ?>
                    <?php if ($validEmail): ?>
                      <a class="c-btn c-mail" href="mailto:<?= $validEmail ?>" title="Send email">
                        <span>Email</span>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <span class="lead-service"><?= $service ?></span>
                  <?php if ($stage || $timeline): ?>
                    <div style="font-size:0.78rem; color:var(--brass-2); margin-bottom:0.35rem;">
                      <?= $stage ? htmlspecialchars($stage) : '' ?>
                      <?= ($stage && $timeline) ? ' &middot; ' : '' ?>
                      <?= $timeline ? 'Timeline: ' . htmlspecialchars($timeline) : '' ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($details): ?>
                    <div class="lead-details"><?= $details ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($notes): ?>
                    <div class="summary-note-cell">
                      <div class="summary-note-text"><?= nl2br($notes) ?></div>
                      <button class="btn-note-pill" type="button" onclick="openNoteModal('<?= $id ?>', <?= $idx ?>, '<?= addslashes($name) ?>', <?= htmlspecialchars(json_encode($row['notes'] ?? ''), ENT_QUOTES) ?>)">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        Edit Summary Note
                      </button>
                    </div>
                  <?php else: ?>
                    <div class="summary-note-cell empty">
                      <span class="note-hint">No notes recorded</span>
                      <button class="btn-note-pill btn-note-add" type="button" onclick="openNoteModal('<?= $id ?>', <?= $idx ?>, '<?= addslashes($name) ?>', '')">
                        + Add Summary Note
                      </button>
                    </div>
                  <?php endif; ?>
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
                  <form method="POST" onsubmit="return confirm('Are you sure you want to delete the lead for <?= addslashes($name) ?>?');">
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

    <!-- Mobile Cards View (<= 820px) -->
    <div class="mobile-cards-wrap" id="mobileCards">
      <?php if (empty($enquiries)): ?>
        <div style="text-align: center; padding: 3rem 1rem; color: var(--mute); background: var(--card-bg); border-radius: 16px;">
          No enquiries recorded yet.
        </div>
      <?php else: ?>
        <?php foreach ($enquiries as $row): ?>
          <?php
            $id = htmlspecialchars((string)($row['id'] ?? ''));
            $idx = (int)($row['_index'] ?? 0);
            $name = htmlspecialchars((string)($row['name'] ?? 'Anonymous'));
            $city = htmlspecialchars((string)($row['city'] ?? 'Not specified'));
            $phone = htmlspecialchars((string)($row['phone'] ?? ''));
            $email = htmlspecialchars((string)($row['email'] ?? ''));
            $contact = htmlspecialchars((string)($row['contact'] ?? ''));
            if ($phone === '' && $email === '' && $contact !== '') {
              if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                $email = $contact;
              } else {
                $phone = $contact;
              }
            }
            $service = htmlspecialchars((string)($row['service'] ?? 'General Enquiry'));
            $stage = htmlspecialchars((string)($row['stage'] ?? ''));
            $timeline = htmlspecialchars((string)($row['timeline'] ?? ''));
            $details = htmlspecialchars((string)($row['details'] ?? ''));
            $notes = htmlspecialchars((string)($row['notes'] ?? ''));

            $rawStatus = strtoupper((string)($row['status'] ?? 'NEW'));
            if (in_array($rawStatus, ['PENDING', 'SENT', 'RECORDED', 'NEW'])) {
              $displayStatus = 'NEW';
            } elseif (in_array($rawStatus, ['CONTACTED', 'CONVERTED', 'CLOSED'])) {
              $displayStatus = $rawStatus;
            } elseif (in_array($rawStatus, ['DISCUSSION', 'IN DISCUSSION', 'IN_DISCUSSION'])) {
              $displayStatus = 'DISCUSSION';
            } else {
              $displayStatus = 'NEW';
            }

            $ts = !empty($row['timestamp']) ? strtotime((string)$row['timestamp']) : time();
            $dateFormatted = date('d M Y, h:i A', $ts) . ' IST';

            $cleanDigits = preg_replace('/[^0-9]/', '', $phone ?: $contact);
            $hasPhone = (strlen($cleanDigits) >= 8);
            $waNum = (strlen($cleanDigits) === 10) ? ('91' . $cleanDigits) : $cleanDigits;
            $isEmail = filter_var($email ?: $contact, FILTER_VALIDATE_EMAIL);
            $validEmail = $isEmail ? ($email ?: $contact) : '';
            $waText = rawurlencode("Hello {$name}, thank you for contacting Sai Sreenivasa Decorin regarding your {$service} project. We would be happy to assist you!");
          ?>
          <div class="m-lead-card lead-row" data-status="<?= $displayStatus ?>" data-service="<?= htmlspecialchars($service) ?>" data-lead-id="<?= $id ?>" data-lead-index="<?= $idx ?>">
            <div class="m-card-top">
              <div>
                <div class="lead-id">#<?= $id ?></div>
                <div class="lead-name"><?= $name ?></div>
                <div class="lead-city">&#x1F4CD; <?= $city ?></div>
              </div>
              <span class="badge-status st-<?= $displayStatus ?>">
                <?= $displayStatus === 'DISCUSSION' ? 'IN DISCUSSION' : $displayStatus ?>
              </span>
            </div>

            <div class="m-card-meta">
              <span class="lead-service"><?= $service ?></span>
              <?php if ($stage): ?><span class="chip" style="font-size:0.74rem"><?= $stage ?></span><?php endif; ?>
              <?php if ($timeline): ?><span class="chip" style="font-size:0.74rem"><?= $timeline ?></span><?php endif; ?>
            </div>

            <?php if ($phone): ?>
              <div class="lead-contact">📞 <?= $phone ?></div>
            <?php elseif ($contact && !$isEmail): ?>
              <div class="lead-contact"><?= $contact ?></div>
            <?php endif; ?>
            <?php if ($email): ?>
              <div class="lead-contact" style="font-size:0.82rem; opacity:0.85; margin-top:2px;">✉️ <?= $email ?></div>
            <?php elseif ($contact && $isEmail): ?>
              <div class="lead-contact" style="font-size:0.82rem; opacity:0.85; margin-top:2px;">✉️ <?= $contact ?></div>
            <?php endif; ?>

            <?php if ($details): ?>
              <div class="m-card-scope"><?= $details ?></div>
            <?php endif; ?>

            <!-- Dedicated Summary Notes Block for Mobile -->
            <div class="m-card-note-block">
              <div class="m-note-header">
                <strong class="m-note-title">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                  Summary Note
                </strong>
                <button class="btn-note-pill <?= $notes ? '' : 'btn-note-add' ?>" type="button" onclick="openNoteModal('<?= $id ?>', <?= $idx ?>, '<?= addslashes($name) ?>', <?= htmlspecialchars(json_encode($row['notes'] ?? ''), ENT_QUOTES) ?>)">
                  <?= $notes ? 'Edit Note' : '+ Add Note' ?>
                </button>
              </div>
              <?php if ($notes): ?>
                <div class="m-note-text"><?= nl2br($notes) ?></div>
              <?php else: ?>
                <div class="m-note-empty">No summary notes yet. Tap "+ Add Note" to add internal remarks.</div>
              <?php endif; ?>
            </div>

            <div style="font-size:0.76rem; color:var(--mute); margin-bottom: 0.6rem;">Received: <?= $dateFormatted ?></div>

            <div class="m-actions-grid">
              <?php if ($hasPhone): ?>
                <a class="c-btn c-wa" href="https://wa.me/<?= $waNum ?>?text=<?= $waText ?>" target="_blank" rel="noopener noreferrer">
                  <span>Chat on WhatsApp</span>
                </a>
                <a class="c-btn c-tel" href="tel:+<?= $waNum ?>">
                  <span>Call Client</span>
                </a>
              <?php endif; ?>
              <?php if ($validEmail): ?>
                <a class="c-btn c-mail" href="mailto:<?= $validEmail ?>">
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
                  <option value="NEW" <?= $displayStatus === 'NEW' ? 'selected' : '' ?>>New</option>
                  <option value="CONTACTED" <?= $displayStatus === 'CONTACTED' ? 'selected' : '' ?>>Contacted</option>
                  <option value="DISCUSSION" <?= $displayStatus === 'DISCUSSION' ? 'selected' : '' ?>>In Discussion</option>
                  <option value="CONVERTED" <?= $displayStatus === 'CONVERTED' ? 'selected' : '' ?>>Converted</option>
                  <option value="CLOSED" <?= $displayStatus === 'CLOSED' ? 'selected' : '' ?>>Closed</option>
                </select>
              </form>
              <form method="POST" onsubmit="return confirm('Are you sure you want to delete the lead for <?= addslashes($name) ?>?');">
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

  <!-- Note Editor Modal -->
  <div class="modal-backdrop" id="noteModal">
    <div class="modal-card">
      <div class="modal-head">
        <div>
          <h2 id="noteModalTitle" style="font-size:1.15rem; color:#fff;">Admin Summary Note</h2>
          <p style="font-size:0.8rem; color:var(--mute); margin-top:2px;">Private internal remarks visible only to admins</p>
        </div>
        <button class="modal-close" type="button" onclick="closeNoteModal()">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="update_notes" />
        <input type="hidden" name="lead_id" id="noteModalLeadId" value="" />
        <input type="hidden" name="lead_index" id="noteModalLeadIndex" value="" />
        
        <div class="form-group" style="margin-bottom:0.6rem;">
          <label style="font-size:0.78rem; color:var(--brass-2); margin-bottom:0.35rem; display:block;">Quick Status Inserts</label>
          <div class="note-chips">
            <button type="button" class="note-chip-btn" onclick="insertNoteChip('📞 Called client — discussion ongoing.')">+ Called</button>
            <button type="button" class="note-chip-btn" onclick="insertNoteChip('📅 Site visit scheduled.')">+ Site Visit</button>
            <button type="button" class="note-chip-btn" onclick="insertNoteChip('💰 Quotation &amp; BOQ estimate sent.')">+ Quote Sent</button>
            <button type="button" class="note-chip-btn" onclick="insertNoteChip('📐 CAD drawings in progress.')">+ In Progress</button>
            <button type="button" class="note-chip-btn" onclick="insertNoteChip('⏳ Awaiting client feedback.')">+ Awaiting</button>
            <button type="button" class="note-chip-btn" onclick="insertNoteChip('✅ Snag audit verified.')">+ Verified</button>
          </div>
        </div>

        <div class="form-group">
          <label>Summary Note Details</label>
          <textarea class="form-control" name="notes" id="noteModalText" rows="4" placeholder="Enter private summary notes (e.g., site visit scheduled for Sunday, estimate shared with client, waiting for BOQ)..."></textarea>
        </div>
        <div style="display:flex; gap:0.8rem; margin-top:1rem;">
          <button class="btn-submit" type="submit" style="flex:2;">Save Summary Note</button>
          <button class="btn-submit" type="button" onclick="closeNoteModal()" style="flex:1; background:rgba(255,255,255,0.08); color:#fff;">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Lead Modal -->
  <div class="modal-backdrop" id="addLeadModal">
    <div class="modal-card">
      <div class="modal-head">
        <h2>+ Add Lead Manually</h2>
        <button class="modal-close" type="button" onclick="closeAddModal()">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_lead" />
        <div class="form-row">
          <div class="form-group">
            <label>Client Name *</label>
            <input class="form-control" type="text" name="name" placeholder="e.g. Rahul Sharma" required />
          </div>
          <div class="form-group">
            <label>Project City *</label>
            <input class="form-control" type="text" name="city" placeholder="e.g. Hyderabad" required />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Phone Number *</label>
            <input class="form-control" type="tel" name="phone" placeholder="e.g. 9876543210" required />
          </div>
          <div class="form-group">
            <label>Email Address (Optional)</label>
            <input class="form-control" type="email" name="email" placeholder="e.g. client@example.com" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Project Stage</label>
            <input class="form-control" type="text" name="stage" placeholder="e.g. Design / Execution" />
          </div>
          <div class="form-group">
            <label>Timeline</label>
            <input class="form-control" type="text" name="timeline" placeholder="e.g. Within 2 weeks" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Service Required</label>
            <select class="form-control" name="service">
              <option value="Site Measurement">Site Measurement</option>
              <option value="Interio Check">Interio Check</option>
              <option value="Modular Drafting">Modular Drafting</option>
              <option value="Interior Training">Interior Training</option>
              <option value="Vendor Directory">Vendor Directory</option>
              <option value="Home Owner Consultant">Home Owner Consultant</option>
              <option value="General Enquiry">General Enquiry</option>
            </select>
          </div>
          <div class="form-group">
            <label>Initial Status</label>
            <select class="form-control" name="status">
              <option value="NEW">New</option>
              <option value="CONTACTED">Contacted</option>
              <option value="DISCUSSION">In Discussion</option>
              <option value="CONVERTED">Converted</option>
              <option value="CLOSED">Closed</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Scope / Project Details</label>
          <textarea class="form-control" name="details" rows="2" placeholder="e.g. 3BHK flat 1600 sq.ft at Kondapur, needs quote audit..."></textarea>
        </div>
        <div class="form-group">
          <label>Internal Admin Notes</label>
          <textarea class="form-control" name="notes" rows="2" placeholder="Private internal note..."></textarea>
        </div>
        <button class="btn-submit" type="submit">Save Lead to Vault</button>
      </form>
    </div>
  </div>

  <!-- Upload Backup (JSON) Modal -->
  <div class="modal-backdrop" id="importModal">
    <div class="modal-card">
      <div class="modal-head">
        <h2>Upload &amp; Append Leads Backup</h2>
        <button class="modal-close" type="button" onclick="closeImportModal()">&times;</button>
      </div>
      <div style="background:rgba(22, 104, 79, 0.22); border:1px solid rgba(22, 104, 79, 0.5); border-radius:12px; padding:0.9rem 1rem; margin-bottom:1.2rem; font-size:0.84rem; color:#bbf7d0; line-height:1.45;">
        <strong>&#128737; Zero Data Loss Guarantee:</strong> Uploading a JSON backup will <strong>only append</strong> new records. Existing leads will never be deleted or overwritten. A pre-import safety snapshot is automatically created before any data is merged.
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_backup" />
        <div class="form-group">
          <label for="backupFile">Select JSON Backup File (.json)</label>
          <input class="form-control" type="file" id="backupFile" name="backup_file" accept=".json,application/json" required style="padding:0.6rem; cursor:pointer;" />
          <small style="display:block; margin-top:0.4rem; color:var(--mute); font-size:0.76rem;">Accepts backups downloaded via "Download Backup (JSON)"</small>
        </div>
        <button class="btn-submit" type="submit">Upload &amp; Append Leads</button>
        <button class="btn-action btn-ghost" type="button" onclick="closeImportModal()" style="width:100%; margin-top:0.6rem; justify-content:center;">Cancel</button>
      </form>
    </div>
  </div>

  <script>
    // Import Modal Helpers
    function openImportModal() {
      document.getElementById('importModal').classList.add('is-open');
    }
    function closeImportModal() {
      document.getElementById('importModal').classList.remove('is-open');
    }

    // Add Lead Modal Helpers
    function openAddModal() {
      document.getElementById('addLeadModal').classList.add('is-open');
    }
    function closeAddModal() {
      document.getElementById('addLeadModal').classList.remove('is-open');
    }

    // Note Modal Helpers
    function openNoteModal(leadId, leadIndex, clientName, existingNote) {
      document.getElementById('noteModalLeadId').value = leadId;
      document.getElementById('noteModalLeadIndex').value = leadIndex;
      document.getElementById('noteModalTitle').textContent = 'Summary Note — ' + clientName;
      document.getElementById('noteModalText').value = existingNote || '';
      document.getElementById('noteModal').classList.add('is-open');
      document.getElementById('noteModalText').focus();
    }
    function closeNoteModal() {
      document.getElementById('noteModal').classList.remove('is-open');
    }
    function insertNoteChip(text) {
      const ta = document.getElementById('noteModalText');
      if (ta.value.trim()) {
        ta.value = ta.value.trim() + ' ' + text;
      } else {
        ta.value = text;
      }
      ta.focus();
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeAddModal();
        closeNoteModal();
      }
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
        
        let matchesStatus = true;
        if (activeStatus) {
          if (activeStatus === 'NEW') {
            matchesStatus = ['NEW', 'PENDING', 'RECORDED', 'SENT'].includes(rowStatus);
          } else if (activeStatus === 'DISCUSSION') {
            matchesStatus = ['DISCUSSION', 'IN DISCUSSION', 'IN_DISCUSSION'].includes(rowStatus);
          } else {
            matchesStatus = (rowStatus === activeStatus);
          }
        }

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

    // Live Real-Time Background Polling (Checks every 8 seconds)
    const currentLatestLeadId = <?= json_encode(!empty($enquiries[0]['id']) ? $enquiries[0]['id'] : '') ?>;
    let isPollingActive = false;

    function showLiveToast(msg) {
      const existing = document.querySelector('.live-toast');
      if (existing) existing.remove();
      const toast = document.createElement('div');
      toast.className = 'live-toast';
      toast.innerHTML = `<span class="live-dot"></span><span>${msg}</span>`;
      document.body.appendChild(toast);
    }

    async function pollForNewLeads() {
      if (isPollingActive) return;
      isPollingActive = true;
      try {
        const res = await fetch('leads.php?api=poll&_t=' + Date.now(), { cache: 'no-store' });
        if (res.ok) {
          const data = await res.json();
          if (data && data.latest_id && currentLatestLeadId && data.latest_id !== currentLatestLeadId) {
            showLiveToast('🟢 New Lead Received: ' + (data.latest_name || 'Client') + '! Refreshing dashboard...');
            setTimeout(() => { window.location.reload(); }, 1200);
          }
        }
      } catch (_) {}
      finally {
        isPollingActive = false;
      }
    }

    // Check for updates every 8 seconds
    setInterval(pollForNewLeads, 8000);
  </script>
<?php endif; ?>

</body>
</html>
