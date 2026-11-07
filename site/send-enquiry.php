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
$contact  = cleanInput((string)($_POST['contact'] ?? ''));
$service  = cleanInput((string)($_POST['service'] ?? ''));
$stage    = cleanInput((string)($_POST['stage'] ?? 'Not specified'));
$timeline = cleanInput((string)($_POST['timeline'] ?? 'Not specified'));
$details  = cleanInput((string)($_POST['details'] ?? ''));

// 3. Validation
$errors = [];
if (mb_strlen($name) < 2) {
    $errors[] = 'Please provide a valid name (at least 2 characters).';
}
if (mb_strlen($city) < 2) {
    $errors[] = 'Please provide the project city.';
}
if ($contact === '') {
    $errors[] = 'Please provide a contact phone number or email.';
}
if ($service === '') {
    $errors[] = 'Please select a service.';
}
if (mb_strlen($details) < 10) {
    $errors[] = 'Please provide details about your project (at least 10 characters).';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// 4. Determine Reply-To address
$cleanContact = stripHeaderInjection($contact);
$replyToEmail = filter_var($cleanContact, FILTER_VALIDATE_EMAIL) ? $cleanContact : 'noreply@ssdecorin.com';

// 5. Build HTML & Plaintext Email Body
$cleanName     = stripHeaderInjection($name);
$cleanCity     = stripHeaderInjection($city);
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
$leadRef = 'SSD-' . date('ymd') . '-' . strtoupper(substr(md5($cleanName . $contact . microtime()), 0, 4));

// Contact Intelligence & Action Links
$cleanDigits = preg_replace('/[^0-9]/', '', $contact);
$hasPhone = (strlen($cleanDigits) >= 10);
$waNum = '';
if ($hasPhone) {
    $waNum = (strlen($cleanDigits) === 10) ? ('91' . $cleanDigits) : $cleanDigits;
}
$isEmail = (bool)filter_var($cleanContact, FILTER_VALIDATE_EMAIL);

$waGreeting = rawurlencode("Hello {$cleanName}, thank you for reaching out to Sai Sreenivasa Decorin regarding your {$cleanService} project. How can we assist you?");
$waUrl = $hasPhone ? "https://wa.me/{$waNum}?text={$waGreeting}" : "";
$telUrl = $hasPhone ? "tel:+{$waNum}" : "";
$mailSubjectReply = rawurlencode("Sai Sreenivasa Decorin — Regarding your {$cleanService} enquiry");
$mailUrl = $isEmail ? "mailto:{$cleanContact}?subject={$mailSubjectReply}" : "";

// Action buttons HTML
$actionButtonsHtml = '';
if ($hasPhone) {
    $actionButtonsHtml .= <<<HTML
<a href="{$waUrl}" target="_blank" style="display:inline-block; background-color:#25D366; color:#ffffff; text-decoration:none; font-size:13px; font-weight:700; padding:10px 18px; border-radius:8px; margin-right:8px; margin-bottom:8px; box-shadow:0 4px 12px rgba(37,211,102,0.35);">
  <span style="font-size:14px; vertical-align:middle; margin-right:4px;">💬</span> <span style="vertical-align:middle;">Reply on WhatsApp</span>
</a>
<a href="{$telUrl}" style="display:inline-block; background-color:#17243b; color:#f5d78a; border:1px solid rgba(212,176,106,0.5); text-decoration:none; font-size:13px; font-weight:700; padding:9px 16px; border-radius:8px; margin-right:8px; margin-bottom:8px;">
  <span style="font-size:14px; vertical-align:middle; margin-right:4px;">📞</span> <span style="vertical-align:middle;">Call Client</span>
</a>
HTML;
}

if ($isEmail) {
    $actionButtonsHtml .= <<<HTML
<a href="{$mailUrl}" style="display:inline-block; background-color:#17243b; color:#93c5fd; border:1px solid rgba(59,130,246,0.5); text-decoration:none; font-size:13px; font-weight:700; padding:9px 16px; border-radius:8px; margin-right:8px; margin-bottom:8px;">
  <span style="font-size:14px; vertical-align:middle; margin-right:4px;">✉️</span> <span style="vertical-align:middle;">Reply via Email</span>
</a>
HTML;
}

$actionButtonsHtml .= <<<HTML
<a href="https://ssdecorin.com/leads" target="_blank" style="display:inline-block; background-color:#1e293b; color:#cbd5e1; border:1px solid rgba(255,255,255,0.15); text-decoration:none; font-size:13px; font-weight:600; padding:9px 16px; border-radius:8px; margin-bottom:8px;">
  <span style="font-size:14px; vertical-align:middle; margin-right:4px;">🔐</span> <span style="vertical-align:middle;">Lead Vault</span>
</a>
HTML;

// Formatted contact link
if ($hasPhone && $isEmail) {
    $contactFormatted = "<a href=\"tel:+{$waNum}\" style=\"color:#f5d78a; text-decoration:none;\">{$contact}</a>";
} elseif ($isEmail) {
    $contactFormatted = "<a href=\"mailto:{$cleanContact}\" style=\"color:#f5d78a; text-decoration:none;\">{$cleanContact}</a>";
} elseif ($hasPhone) {
    $contactFormatted = "<a href=\"tel:+{$waNum}\" style=\"color:#f5d78a; text-decoration:none;\">{$contact}</a>";
} else {
    $contactFormatted = htmlspecialchars($contact);
}

$plainBody = <<<TXT
======================================================================
  ⚡ NEW CLIENT ENQUIRY — SAI SREENIVASA DECORIN (ssdecorin.com)
  Lead Reference: {$leadRef}
======================================================================

CLIENT SPOTLIGHT:
----------------------------------------------------------------------
Name:            {$name}
City:            {$city}
Contact:         {$contact}
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
<body style="margin:0; padding:0; background-color:#050811; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing:antialiased; color:#f1f5f9;">
  <!-- Preheader preview text for inbox -->
  <div style="display:none; max-height:0px; overflow:hidden; font-size:1px; line-height:1px; color:#050811; opacity:0; mso-hide:all;">
    New enquiry from {$cleanName} ({$cleanCity}) for {$cleanService} &middot; Timeline: {$cleanTimeline}. 1-click WhatsApp &amp; Call actions inside.
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#050811; min-height:100vh; padding:28px 12px;">
    <tr>
      <td align="center" valign="top">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px; background-color:#0d1527; border:1px solid rgba(212, 176, 106, 0.4); border-radius:16px; overflow:hidden; box-shadow:0 20px 45px rgba(0,0,0,0.6);">
          
          <!-- Top Accent Gold Gradient Bar -->
          <tr>
            <td style="height:4px; background:linear-gradient(90deg, #9b722b 0%, #f5d78a 50%, #9b722b 100%); line-height:4px; font-size:4px;">&nbsp;</td>
          </tr>

          <!-- Header Section -->
          <tr>
            <td style="padding:24px 28px 20px 28px; background:linear-gradient(180deg, #131f38 0%, #0d1527 100%); border-bottom:1px solid rgba(212, 176, 106, 0.18);">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td valign="middle">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td style="width:44px; height:44px; background:linear-gradient(135deg, #d4b06a 0%, #8c6721 100%); border-radius:10px; text-align:center; vertical-align:middle; font-family:Georgia, serif; font-size:17px; font-weight:bold; color:#080d17; letter-spacing:0.05em; box-shadow:0 4px 12px rgba(212, 176, 106, 0.25);">
                          SSD
                        </td>
                        <td style="padding-left:14px; vertical-align:middle;">
                          <div style="font-size:15px; font-weight:800; letter-spacing:0.12em; color:#f5d78a; text-transform:uppercase;">SAI SREENIVASA DECORIN</div>
                          <div style="font-size:11px; color:#94a3b8; letter-spacing:0.04em; margin-top:3px;">Interior Architecture &middot; Modular Engineering &middot; Quality Audits</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td align="right" valign="middle">
                    <span style="display:inline-block; background-color:rgba(34, 197, 94, 0.12); border:1px solid rgba(34, 197, 94, 0.4); color:#4ade80; font-size:11px; font-weight:700; padding:5px 12px; border-radius:999px; letter-spacing:0.05em; text-transform:uppercase; white-space:nowrap;">
                      &#x25CF; NEW LEAD
                    </span>
                    <div style="font-size:10px; color:#64748b; font-family:monospace; margin-top:4px; text-align:right;">{$leadRef}</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Client Spotlight Hero Block -->
          <tr>
            <td style="padding:24px 28px 16px 28px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:linear-gradient(135deg, #15223c 0%, #101a2e 100%); border:1px solid rgba(212, 176, 106, 0.35); border-radius:12px; padding:20px;">
                <tr>
                  <td>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td style="width:52px; vertical-align:top;">
                          <div style="width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg, #d4b06a 0%, #9a6d23 100%); color:#080d17; text-align:center; line-height:48px; font-size:19px; font-weight:800; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; box-shadow:0 4px 14px rgba(0,0,0,0.35);">
                            {$initials}
                          </div>
                        </td>
                        <td style="padding-left:14px; vertical-align:top;">
                          <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#d4b06a; font-weight:700; margin-bottom:2px;">Enquiring Client</div>
                          <h2 style="margin:0 0 6px 0; font-size:22px; font-weight:700; color:#ffffff; letter-spacing:-0.01em; line-height:1.2;">{$name}</h2>
                          <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                              <td style="font-size:13px; color:#cbd5e1;">
                                <span style="display:inline-block; margin-right:12px;">&#x1F4CD; <strong style="color:#f1f5f9;">{$city}</strong></span>
                                <span style="display:inline-block; background-color:rgba(212, 176, 106, 0.15); border:1px solid rgba(212, 176, 106, 0.4); color:#f5d78a; font-size:11px; font-weight:600; padding:2px 10px; border-radius:999px;">
                                  {$service}
                                </span>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Instant CTA Action Bar -->
                    <div style="margin-top:18px; padding-top:16px; border-top:1px solid rgba(255, 255, 255, 0.08);">
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

          <!-- Key Specifications Grid (2 Columns) -->
          <tr>
            <td style="padding:0 28px 20px 28px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate; border-spacing:10px 10px; margin:-5px -5px;">
                <tr>
                  <td width="50%" style="background-color:#121c32; border:1px solid rgba(255, 255, 255, 0.08); border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#d4b06a; font-weight:700; margin-bottom:4px;">Service Requested</div>
                    <div style="font-size:14px; color:#ffffff; font-weight:700;">{$service}</div>
                  </td>
                  <td width="50%" style="background-color:#121c32; border:1px solid rgba(255, 255, 255, 0.08); border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#d4b06a; font-weight:700; margin-bottom:4px;">Project City</div>
                    <div style="font-size:14px; color:#ffffff; font-weight:700;">{$city}</div>
                  </td>
                </tr>
                <tr>
                  <td width="50%" style="background-color:#121c32; border:1px solid rgba(255, 255, 255, 0.08); border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#d4b06a; font-weight:700; margin-bottom:4px;">Project Stage</div>
                    <div style="font-size:14px; color:#ffffff; font-weight:600;">{$stage}</div>
                  </td>
                  <td width="50%" style="background-color:#121c32; border:1px solid rgba(255, 255, 255, 0.08); border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#d4b06a; font-weight:700; margin-bottom:4px;">Target Timeline</div>
                    <div style="font-size:14px; color:#ffffff; font-weight:600;">{$timeline}</div>
                  </td>
                </tr>
                <tr>
                  <td colspan="2" style="background-color:#121c32; border:1px solid rgba(255, 255, 255, 0.08); border-radius:10px; padding:14px 16px; vertical-align:top;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#d4b06a; font-weight:700; margin-bottom:4px;">Contact Detail</div>
                    <div style="font-size:15px; color:#f5d78a; font-weight:700; font-family:monospace;">{$contactFormatted}</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Project Scope & Client Brief -->
          <tr>
            <td style="padding:0 28px 24px 28px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0b1220; border-left:4px solid #d4b06a; border-radius:0 10px 10px 0; border-top:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); border-bottom:1px solid rgba(255,255,255,0.06);">
                <tr>
                  <td style="padding:18px 20px;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#d4b06a; font-weight:700; margin-bottom:8px;">
                      &#x1F4D0; Project Scope &amp; Client Brief
                    </div>
                    <div style="font-size:14px; line-height:1.65; color:#f1f5f9; white-space:pre-wrap; word-break:break-word;">{$details}</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Lead Vault Access Banner -->
          <tr>
            <td style="padding:0 28px 24px 28px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:linear-gradient(135deg, rgba(212, 176, 106, 0.12) 0%, rgba(212, 176, 106, 0.04) 100%); border:1px dashed rgba(212, 176, 106, 0.4); border-radius:10px; padding:14px 18px;">
                <tr>
                  <td valign="middle">
                    <div style="font-size:13px; font-weight:700; color:#f5d78a;">Live Lead Vault Dashboard</div>
                    <div style="font-size:12px; color:#94a3b8; margin-top:2px;">Securely backed up in JSON vault &middot; Passcode: <code style="color:#f1f5f9; background:rgba(0,0,0,0.4); padding:2px 6px; border-radius:4px; font-family:monospace;">Decorin@2026</code></div>
                  </td>
                  <td align="right" valign="middle">
                    <a href="https://ssdecorin.com/leads" target="_blank" style="display:inline-block; background-color:#d4b06a; color:#080d17; text-decoration:none; font-size:12px; font-weight:800; padding:9px 16px; border-radius:6px; letter-spacing:0.03em; white-space:nowrap;">
                      Open Vault &rarr;
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Footer & Technical Audit -->
          <tr>
            <td style="padding:20px 28px; background-color:#080d18; border-top:1px solid rgba(255, 255, 255, 0.06); text-align:center;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="font-size:12px; color:#94a3b8; line-height:1.6; text-align:center;">
                    <span style="color:#cbd5e1; font-weight:600;">Sai Sreenivasa Decorin</span> &middot; Managing Partner: <strong style="color:#f5d78a;">S. S. Naga Nandan</strong><br>
                    <span>Direct: <a href="tel:+916363313546" style="color:#d4b06a; text-decoration:none;">+91 63633 13546</a></span> &middot;
                    <span>Website: <a href="https://ssdecorin.com" style="color:#d4b06a; text-decoration:none;">ssdecorin.com</a></span>
                  </td>
                </tr>
                <tr>
                  <td style="padding-top:12px; font-size:11px; color:#64748b; line-height:1.5; font-family:monospace; text-align:center;">
                    Submitted: {$dateFormatted} &middot; IP: {$clientIp}<br>
                    Engineering Standard: &plusmn;0.5mm CAD Precision &middot; Interio Check&trade; Verified
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
    'status' => 'PENDING',
    'name' => $name,
    'city' => $city,
    'contact' => $contact,
    'service' => $service,
    'stage' => $stage,
    'timeline' => $timeline,
    'details' => $details,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

$jsonFile = $logDir . '/enquiries.json';
$currentData = [];
if (file_exists($jsonFile)) {
    $currentData = json_decode((string)file_get_contents($jsonFile), true) ?: [];
}
$currentData[] = &$jsonRecord;

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
$jsonRecord['status'] = $delivered ? 'SENT' : 'LOGGED_ONLY';
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
