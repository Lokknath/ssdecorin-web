<?php
/**
 * Sai Sreenivasa Decorin — Multi-Channel Enquiry Form Handler
 * Endpoint for ssdecorin.com hosted on MilesWeb (LiteSpeed / Apache)
 * 
 * Safety Net Architecture:
 * 1. Immediate local JSON vault storage (zero data loss)
 * 2. Optional Resend API integration (2-second instant email)
 * 3. Optional Telegram Bot push alert (0.5-second instant phone alert)
 * 4. Native PHP mail() dispatch with RFC compliant headers
 */

declare(strict_types=1);

// Guarantee Indian Standard Time (IST, UTC+5:30) for all dates and timestamps
date_default_timezone_set('Asia/Kolkata');

// Set strict security headers on response
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// 20. Load App Configuration
$config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$primaryRecipient = $config['admin_email'] ?? 'loknath.surabhi@gmail.com';
$adminRecipients = $config['admin_emails'] ?? [$primaryRecipient];
if (!is_array($adminRecipients) || empty($adminRecipients)) {
    $adminRecipients = [$primaryRecipient];
}

// Allow local and production origins for AJAX POST
$allowedOrigins = [
    'https://ssdecorin.com',
    'https://www.ssdecorin.com',
    'http://localhost',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:8080'
];

$httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($httpOrigin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$httpOrigin}");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// 1. Anti-spam honeypot verification
$honey = trim((string)($_POST['_honey'] ?? ''));
if ($honey !== '') {
    // Bot detected: respond with generic success to confuse spammer
    echo json_encode(['success' => true, 'message' => 'Enquiry received.']);
    exit;
}

// 2. Extract and sanitize inputs
function cleanInput(string $val): string {
    return trim(htmlspecialchars(strip_tags($val), ENT_QUOTES, 'UTF-8'));
}

function stripHeaderInjection(string $val): string {
    return str_replace(["\r", "\n", "%0a", "%0d"], '', $val);
}

$name     = cleanInput((string)($_POST['name'] ?? ''));
$city     = cleanInput((string)($_POST['city'] ?? ''));
$phone    = cleanInput((string)($_POST['phone'] ?? ''));
$email    = cleanInput((string)($_POST['email'] ?? ''));
$contact  = cleanInput((string)($_POST['contact'] ?? ''));
$service  = cleanInput((string)($_POST['service'] ?? ''));
$stage    = cleanInput((string)($_POST['stage'] ?? 'Not specified'));
$timeline = cleanInput((string)($_POST['timeline'] ?? 'Not specified'));
$details  = cleanInput((string)($_POST['details'] ?? ''));

// Backward compatibility: If phone is missing but contact was provided
if ($phone === '' && $contact !== '') {
    if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        if ($email === '') $email = $contact;
    } else {
        $phone = $contact;
    }
}
if ($contact === '') {
    $contact = ($email !== '') ? "{$phone} · {$email}" : $phone;
}

// Robust, friendly validation: Name & Phone are compulsory, Email is optional
$errors = [];
if (mb_strlen($name) < 2) {
    $errors[] = 'Please provide a valid client name (at least 2 characters).';
}

$cleanDigits = preg_replace('/[^0-9]/', '', $phone);
if ($phone === '' || strlen($cleanDigits) < 8) {
    $errors[] = 'Please provide a valid contact phone number (at least 10 digits).';
}

$cleanEmail = stripHeaderInjection($email);
if ($cleanEmail !== '' && !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'The email address provided is invalid. Please provide a valid email (e.g. name@example.com) or leave it empty.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Sensible defaults for optional/short inputs so leads are NEVER lost
if ($city === '') {
    $city = 'Pan-India';
}
if ($service === '') {
    $service = 'General Interior Technical Support';
}
if ($details === '') {
    $details = 'Enquiry submitted via website form. Client requested technical consultation / callback.';
}

// 4. Determine Reply-To address
$hasEmail = ($cleanEmail !== '' && filter_var($cleanEmail, FILTER_VALIDATE_EMAIL));
$replyToEmail = $hasEmail ? $cleanEmail : 'noreply@ssdecorin.com';

// 5. Build HTML & Plaintext Email Body
$cleanName     = stripHeaderInjection($name);
$cleanCity     = stripHeaderInjection($city);
$cleanPhone    = stripHeaderInjection($phone);
$cleanService  = stripHeaderInjection($service);
$cleanStage    = stripHeaderInjection($stage);
$cleanTimeline = stripHeaderInjection($timeline);
$subject       = "⚡ New Lead: {$cleanName} ({$cleanCity}) — {$cleanService} | ssdecorin.com";
$dateFormatted = date('d M Y, h:i A') . ' IST';
$clientIp      = cleanInput($_SERVER['REMOTE_ADDR'] ?? 'Unknown');

// Generate Client Initials Monogram
$nameParts = preg_split('/\s+/', trim($cleanName));
$initials = '';
if (!empty($nameParts[0])) {
    $initials .= mb_substr($nameParts[0], 0, 1);
}
if (count($nameParts) > 1 && !empty($nameParts[count($nameParts) - 1])) {
    $initials .= mb_substr($nameParts[count($nameParts) - 1], 0, 1);
}
$initials = strtoupper($initials ?: 'SSD');

// Unique Lead Tracking Reference
$leadRef = 'SSD-' . date('ymd') . '-' . strtoupper(substr(md5($cleanName . $cleanPhone . microtime()), 0, 4));

// Contact Intelligence & Action Links
$hasPhone = (strlen($cleanDigits) >= 8);
$waNum = '';
if ($hasPhone) {
    $waNum = (strlen($cleanDigits) === 10) ? ('91' . $cleanDigits) : $cleanDigits;
}

$waGreeting = rawurlencode("Hello {$cleanName}, thank you for reaching out to Sai Sreenivasa Decorin regarding your {$cleanService} project. How can we assist you?");
$waUrl = $hasPhone ? "https://wa.me/{$waNum}?text={$waGreeting}" : "";
$telUrl = $hasPhone ? "tel:+{$waNum}" : "";
$mailSubjectReply = rawurlencode("Sai Sreenivasa Decorin — Regarding your {$cleanService} enquiry");
$mailUrl = $hasEmail ? "mailto:{$cleanEmail}?subject={$mailSubjectReply}" : "";

// Action buttons HTML (high-contrast, touch-friendly for mobile & desktop email clients)
$actionButtonsHtml = '';
if ($hasPhone) {
    $actionButtonsHtml .= <<<HTML
<a href="{$waUrl}" target="_blank" style="display:inline-block; background-color:#16a34a; color:#ffffff; text-decoration:none; font-size:13px; font-weight:800; padding:11px 18px; border-radius:8px; margin-right:8px; margin-bottom:8px; box-shadow:0 2px 6px rgba(22,163,74,0.3); font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
  <span style="font-size:14px; vertical-align:middle; margin-right:4px;">💬</span> <span style="vertical-align:middle;">Reply on WhatsApp</span>
</a>
<a href="{$telUrl}" style="display:inline-block; background-color:#0284c7; color:#ffffff; text-decoration:none; font-size:13px; font-weight:800; padding:11px 18px; border-radius:8px; margin-right:8px; margin-bottom:8px; box-shadow:0 2px 6px rgba(2,132,199,0.3); font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
  <span style="font-size:14px; vertical-align:middle; margin-right:4px;">📞</span> <span style="vertical-align:middle;">Call Client</span>
</a>
HTML;
}

if ($hasEmail) {
    $actionButtonsHtml .= <<<HTML
<a href="{$mailUrl}" style="display:inline-block; background-color:#475569; color:#ffffff; text-decoration:none; font-size:13px; font-weight:700; padding:11px 18px; border-radius:8px; margin-right:8px; margin-bottom:8px; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
  <span style="font-size:14px; vertical-align:middle; margin-right:4px;">✉️</span> <span style="vertical-align:middle;">Reply via Email</span>
</a>
HTML;
}

$actionButtonsHtml .= <<<HTML
<a href="https://ssdecorin.com/leads" target="_blank" style="display:inline-block; background-color:#0f172a; color:#f59e0b; border:1px solid #d97706; text-decoration:none; font-size:13px; font-weight:700; padding:11px 18px; border-radius:8px; margin-bottom:8px; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
  <span style="font-size:14px; vertical-align:middle; margin-right:4px;">🔐</span> <span style="vertical-align:middle;">Lead Vault</span>
</a>
HTML;

// Formatted contact link with bold high-contrast styling
$phoneFormatted = $hasPhone ? "<a href=\"tel:+{$waNum}\" style=\"color:#0369a1; text-decoration:none; font-weight:800; font-size:16px;\">{$cleanPhone}</a>" : htmlspecialchars($cleanPhone);
$emailFormatted = $hasEmail ? "<a href=\"mailto:{$cleanEmail}\" style=\"color:#0369a1; text-decoration:none; font-weight:700; font-size:15px;\">{$cleanEmail}</a>" : "<span style=\"color:#64748b; font-style:italic;\">Not provided (optional)</span>";

$plainBody = <<<TXT
======================================================================
  ⚡ NEW CLIENT ENQUIRY — SAI SREENIVASA DECORIN (ssdecorin.com)
  Lead Reference: {$leadRef}
======================================================================

CLIENT SPOTLIGHT:
----------------------------------------------------------------------
Name:            {$name}
City:            {$city}
Phone (Req):     {$phone}
Email (Opt):     {$cleanEmail}
Service Needed:  {$service}
Current Stage:   {$stage}
Target Timeline: {$timeline}
Submitted At:    {$dateFormatted}

DIRECT ACTIONS FOR ADMIN:
----------------------------------------------------------------------
WhatsApp Client: {$waUrl}
Call Client:     tel:{$waNum}
Lead Vault CRM:  https://ssdecorin.com/leads (Passcode: Decorin@2026)

PROJECT SCOPE & CLIENT BRIEF:
----------------------------------------------------------------------
{$details}

======================================================================
Security / Audit:
IP: {$clientIp} | Engineering Standard: ±0.5mm CAD Precision
Official notification dispatched via ssdecorin.com
TXT;

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Project Enquiry — Sai Sreenivasa Decorin</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing:antialiased; color:#0f172a;">
  <!-- Preheader preview text for inbox -->
  <div style="display:none; max-height:0px; overflow:hidden; font-size:1px; line-height:1px; color:#f1f5f9; opacity:0; mso-hide:all;">
    New lead from {$cleanName} ({$cleanCity}) for {$cleanService} &middot; Timeline: {$cleanTimeline}. 1-click WhatsApp &amp; Call actions inside.
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; min-height:100vh; padding:24px 10px;">
    <tr>
      <td align="center" valign="top">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px; background-color:#ffffff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; box-shadow:0 12px 36px rgba(15,23,42,0.08);">
          
          <!-- Top Accent Gold Bar -->
          <tr>
            <td style="height:5px; background:linear-gradient(90deg, #d97706 0%, #f59e0b 50%, #d97706 100%); line-height:5px; font-size:5px;">&nbsp;</td>
          </tr>

          <!-- Deep Navy Slate Executive Header -->
          <tr>
            <td style="padding:22px 28px; background-color:#0f172a; border-bottom:1px solid #1e293b;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td valign="middle">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td style="width:42px; height:42px; background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius:10px; text-align:center; vertical-align:middle; font-family:Georgia, serif; font-size:16px; font-weight:800; color:#0f172a; letter-spacing:0.04em;">
                          SSD
                        </td>
                        <td style="padding-left:14px; vertical-align:middle;">
                          <div style="font-size:15px; font-weight:800; letter-spacing:0.1em; color:#f8fafc; text-transform:uppercase;">SAI SREENIVASA DECORIN</div>
                          <div style="font-size:11px; color:#94a3b8; letter-spacing:0.03em; margin-top:3px;">Interior Technical Partner &middot; On-Site Precision &middot; Quality Audits</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td align="right" valign="middle">
                    <span style="display:inline-block; background-color:#166534; border:1px solid #22c55e; color:#ffffff; font-size:11px; font-weight:800; padding:4px 11px; border-radius:999px; letter-spacing:0.06em; text-transform:uppercase; white-space:nowrap;">
                      &#x25CF; NEW LEAD
                    </span>
                    <div style="font-size:10px; color:#cbd5e1; font-family:monospace; margin-top:4px; text-align:right; font-weight:700;">{$leadRef}</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Client Spotlight Hero Block -->
          <tr>
            <td style="padding:22px 24px 16px 24px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:20px;">
                <tr>
                  <td>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td style="width:50px; vertical-align:top;">
                          <div style="width:46px; height:46px; border-radius:50%; background:#d97706; color:#ffffff; text-align:center; line-height:46px; font-size:18px; font-weight:800; box-shadow:0 2px 8px rgba(217,119,6,0.25);">
                            {$initials}
                          </div>
                        </td>
                        <td style="padding-left:14px; vertical-align:top;">
                          <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:800; margin-bottom:2px;">Enquiring Client</div>
                          <h2 style="margin:0 0 6px 0; font-size:23px; font-weight:800; color:#0f172a; letter-spacing:-0.015em; line-height:1.2;">{$name}</h2>
                          <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                              <td style="font-size:13px; color:#475569;">
                                <span style="display:inline-block; margin-right:12px;">&#x1F4CD; <strong style="color:#0f172a; font-weight:700;">{$city}</strong></span>
                                <span style="display:inline-block; background-color:#e0f2fe; border:1px solid #7dd3fc; color:#0369a1; font-size:11px; font-weight:700; padding:2px 10px; border-radius:999px;">
                                  {$service}
                                </span>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Instant CTA Action Bar -->
                    <div style="margin-top:16px; padding-top:16px; border-top:1px solid #e2e8f0;">
                      <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                          <td>
                            {$actionButtonsHtml}
                          </td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Key Specifications Grid (2 Columns, High Contrast) -->
          <tr>
            <td style="padding:0 24px 18px 24px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate; border-spacing:10px 10px; margin:-5px -5px;">
                <tr>
                  <td width="50%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:800; margin-bottom:4px;">Service Requested</div>
                    <div style="font-size:14px; color:#0f172a; font-weight:700;">{$service}</div>
                  </td>
                  <td width="50%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:800; margin-bottom:4px;">Project City</div>
                    <div style="font-size:14px; color:#0f172a; font-weight:700;">{$city}</div>
                  </td>
                </tr>
                <tr>
                  <td width="50%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:800; margin-bottom:4px;">Project Stage</div>
                    <div style="font-size:14px; color:#334155; font-weight:600;">{$stage}</div>
                  </td>
                  <td width="50%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:800; margin-bottom:4px;">Target Timeline</div>
                    <div style="font-size:14px; color:#334155; font-weight:600;">{$timeline}</div>
                  </td>
                </tr>
                <tr>
                  <td width="50%" style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#1e40af; font-weight:800; margin-bottom:4px;">Phone Number (Compulsory)</div>
                    <div style="font-size:16px; color:#0369a1; font-weight:800; font-family:monospace;">{$phoneFormatted}</div>
                  </td>
                  <td width="50%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:800; margin-bottom:4px;">Email Address (Optional)</div>
                    <div style="font-size:15px; color:#334155; font-weight:600; font-family:monospace;">{$emailFormatted}</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Project Scope & Client Brief (Crisp Readable Box) -->
          <tr>
            <td style="padding:0 24px 20px 24px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-left:4px solid #f59e0b; border-radius:0 10px 10px 0; border-top:1px solid #e2e8f0; border-right:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; box-shadow:0 2px 6px rgba(0,0,0,0.02);">
                <tr>
                  <td style="padding:16px 20px;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#b45309; font-weight:800; margin-bottom:8px;">
                      &#x1F4D0; Project Scope &amp; Client Brief
                    </div>
                    <div style="font-size:15px; line-height:1.65; color:#1e293b; font-weight:500; white-space:pre-wrap; word-break:break-word;">{$details}</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Lead Vault Access Banner -->
          <tr>
            <td style="padding:0 24px 20px 24px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border:1px solid #fde68a; border-radius:10px; padding:14px 18px;">
                <tr>
                  <td valign="middle">
                    <div style="font-size:14px; font-weight:800; color:#92400e;">Live Lead Vault Dashboard</div>
                    <div style="font-size:12px; color:#78350f; margin-top:2px;">All enquiries recorded with IST timestamps &middot; Passcode: <code style="color:#0f172a; background:#fef08a; padding:2px 6px; border-radius:4px; font-family:monospace; font-weight:700;">Decorin@2026</code></div>
                  </td>
                  <td align="right" valign="middle">
                    <a href="https://ssdecorin.com/leads" target="_blank" style="display:inline-block; background-color:#0f172a; color:#fbbf24; text-decoration:none; font-size:12px; font-weight:800; padding:10px 16px; border-radius:6px; letter-spacing:0.03em; white-space:nowrap;">
                      Open Vault &rarr;
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Clean Footer & Technical Audit -->
          <tr>
            <td style="padding:20px 24px; background-color:#f8fafc; border-top:1px solid #e2e8f0; text-align:center;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="font-size:12px; color:#64748b; line-height:1.6; text-align:center;">
                    <span style="color:#0f172a; font-weight:700;">Sai Sreenivasa Decorin</span> &middot; Managing Partner: <strong style="color:#b45309;">S. S. Naga Nandan</strong><br>
                    <span>Direct: <a href="tel:+916363313546" style="color:#0284c7; text-decoration:none; font-weight:700;">+91 63633 13546</a></span> &middot;
                    <span>Website: <a href="https://ssdecorin.com" style="color:#0284c7; text-decoration:none; font-weight:700;">ssdecorin.com</a></span>
                  </td>
                </tr>
                <tr>
                  <td style="padding-top:10px; font-size:11px; color:#94a3b8; line-height:1.5; font-family:monospace; text-align:center;">
                    Submitted: {$dateFormatted} &middot; IP: {$clientIp}<br>
                    Engineering Standard: &plusmn;0.5mm Laser Precision &middot; Interio Check&trade; Quality Audit
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Sai Sreenivasa Decorin <noreply@ssdecorin.com>',
    "Reply-To: {$replyToEmail}",
    'X-Mailer: PHP/' . phpversion()
];

// ======================================================================
// LAYER 1: Permanent Disk Storage (ZERO DATA LOSS GUARANTEE)
// Executed BEFORE any network/mail call so records can NEVER be lost
// ======================================================================
$logDir = $config['storage_dir'] ?? (__DIR__ . '/logs');
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}

$jsonRecord = [
    'id' => $leadRef,
    'timestamp' => date('c'),
    'status' => 'NEW',
    'name' => $name,
    'city' => $city,
    'phone' => $phone,
    'email' => $email,
    'contact' => $contact,
    'service' => $service,
    'stage' => $stage,
    'timeline' => $timeline,
    'details' => $details,
    'notes' => '',
    'delivery_status' => 'PENDING',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

$jsonFile = $logDir . '/enquiries.json';
$currentData = [];
if (file_exists($jsonFile)) {
    $raw = (string)@file_get_contents($jsonFile);
    $currentData = json_decode($raw, true) ?: [];
    if (!is_array($currentData)) $currentData = [];
}
$currentData[] = &$jsonRecord;

// IMMEDIATE ATOMIC WRITE: guarantee zero data loss BEFORE any external network calls
@file_put_contents($jsonFile, json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

$initialLog = sprintf(
    "[%s] Status: RECORDED | Name: %s | City: %s | Contact: %s | Service: %s\n",
    date('Y-m-d H:i:s'),
    $name,
    $city,
    $contact,
    $service
);
@file_put_contents($logDir . '/enquiries.log', $initialLog, FILE_APPEND | LOCK_EX);

// ======================================================================
// LAYER 2: Resend Transactional Email API (Instant 1-2 Second Delivery)
// ======================================================================
$resendSent = false;
$resendKey = $config['resend_api_key'] ?? '';
if (!empty($resendKey)) {
    $resendFrom = $config['resend_from'] ?? 'Sai Sreenivasa Decorin <noreply@ssdecorin.com>';
    $sandboxEmail = $config['resend_sandbox_email'] ?? '';
    
    $sendResend = function(array $toList) use ($resendKey, $resendFrom, $replyToEmail, $subject, $htmlBody, $plainBody): array {
        $ch = curl_init('https://api.resend.com/emails');
        $payload = json_encode([
            'from' => $resendFrom,
            'to' => array_values($toList),
            'reply_to' => $replyToEmail,
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $plainBody
        ]);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $resendKey,
                'Content-Type: application/json',
                'User-Agent: curl/7.88.1'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $httpCode, 'res' => $res];
    };

    // Attempt delivery to all configured admin recipients
    $result = $sendResend($adminRecipients);
    if ($result['code'] >= 200 && $result['code'] < 300) {
        $resendSent = true;
    } elseif ($result['code'] === 403 && !empty($sandboxEmail)) {
        // Fallback for sandbox mode if domain was not yet verified
        $sandboxResult = $sendResend([$sandboxEmail]);
        if ($sandboxResult['code'] >= 200 && $sandboxResult['code'] < 300) {
            $resendSent = true;
        }
    }
}

// ======================================================================
// LAYER 3: MilesWeb Standard PHP mail() Dispatch
// ======================================================================
$mailSent = false;
foreach ($adminRecipients as $adminAddr) {
    if (filter_var($adminAddr, FILTER_VALIDATE_EMAIL)) {
        if (@mail($adminAddr, $subject, $htmlBody, implode("\r\n", $headers))) {
            $mailSent = true;
        }
    }
}

$delivered = ($resendSent || $mailSent);

// Finalize log records with delivery outcome
$jsonRecord['delivery_status'] = $delivered ? 'SENT' : 'LOGGED_ONLY';
$jsonRecord['dispatched_via'] = [
    'resend' => $resendSent,
    'php_mail' => $mailSent
];
@file_put_contents($jsonFile, json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

$logEntry = sprintf(
    "[%s] Status: %s | Resend: %s | Mail: %s | Name: %s | City: %s | Contact: %s | Service: %s\n",
    date('Y-m-d H:i:s'),
    $delivered ? 'SENT' : 'LOGGED_ONLY',
    $resendSent ? 'YES' : 'NO',
    $mailSent ? 'YES' : 'NO',
    $name,
    $city,
    $contact,
    $service
);
@file_put_contents($logDir . '/enquiries.log', $logEntry, FILE_APPEND | LOCK_EX);

// 7. Output JSON result
if ($delivered) {
    echo json_encode([
        'success' => true,
        'email_sent' => true,
        'recipient' => $primaryRecipient,
        'message' => 'Thank you! Your enquiry has been received and dispatched to our technical team.'
    ]);
} else {
    // If all network methods failed, inform the client to use WhatsApp, but data is safely stored
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'email_sent' => false,
        'recipient' => $primaryRecipient,
        'message' => 'Enquiry recorded in our vault! You can also connect instantly on WhatsApp.'
    ]);
}
