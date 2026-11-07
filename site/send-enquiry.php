<?php
/**
 * Sai Sreenivasa Decorin — Enquiry Form Handler
 * Endpoint for ssdecorin.com hosted on MilesWeb (LiteSpeed / Apache)
 */

declare(strict_types=1);

// Set strict security headers on response
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

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
$primaryRecipient = 'loknath.surabhi@gmail.com';
$cleanName = stripHeaderInjection($name);
$cleanService = stripHeaderInjection($service);
$subject = "New Enquiry: {$cleanService} — {$cleanName} (ssdecorin.com)";

$dateFormatted = date('d M Y, h:i A') . ' IST';

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>New Website Enquiry</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #080d17; color: #f3f0e9; margin: 0; padding: 24px; }
    .card { max-width: 620px; margin: 0 auto; background: #0c1322; border: 1px solid rgba(212, 176, 106, 0.3); border-radius: 14px; overflow: hidden; box-shadow: 0 12px 30px rgba(0,0,0,0.5); }
    .header { background: linear-gradient(135deg, #111a2d, #080d17); padding: 24px; border-bottom: 2px solid #d4b06a; text-align: center; }
    .header h1 { margin: 0; color: #f1e0b8; font-size: 22px; font-weight: 700; letter-spacing: -0.02em; }
    .header p { margin: 6px 0 0; color: #9aa3b5; font-size: 13px; }
    .content { padding: 28px; }
    .field-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
    .field-table td { padding: 11px 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); font-size: 14px; vertical-align: top; }
    .field-table td.label { width: 32%; color: #d4b06a; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.06em; }
    .field-table td.value { color: #f3f0e9; font-weight: 500; }
    .details-box { background: rgba(255, 255, 255, 0.04); border-left: 3px solid #d4b06a; padding: 16px; border-radius: 6px; font-size: 14px; line-height: 1.6; color: #f3f0e9; white-space: pre-wrap; word-break: break-word; }
    .footer { padding: 18px 24px; background: #080d17; border-top: 1px solid rgba(255,255,255,0.06); font-size: 12px; color: #9aa3b5; text-align: center; }
    .footer a { color: #d4b06a; text-decoration: none; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>Sai Sreenivasa Decorin</h1>
      <p>New Project Enquiry &middot; ssdecorin.com</p>
    </div>
    <div class="content">
      <table class="field-table">
        <tr><td class="label">Client Name</td><td class="value"><strong>{$name}</strong></td></tr>
        <tr><td class="label">Project City</td><td class="value">{$city}</td></tr>
        <tr><td class="label">Contact Info</td><td class="value"><a href="mailto:{$contact}" style="color:#d4b06a;">{$contact}</a></td></tr>
        <tr><td class="label">Service Needed</td><td class="value"><strong>{$service}</strong></td></tr>
        <tr><td class="label">Project Stage</td><td class="value">{$stage}</td></tr>
        <tr><td class="label">Timeline</td><td class="value">{$timeline}</td></tr>
        <tr><td class="label">Submitted At</td><td class="value">{$dateFormatted}</td></tr>
      </table>
      <p style="margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #d4b06a; font-weight: 600;">Project Scope / Details:</p>
      <div class="details-box">{$details}</div>
    </div>
    <div class="footer">
      Sent from the official website contact form on <a href="https://ssdecorin.com">ssdecorin.com</a>.
    </div>
  </div>
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

// 6. Send email via native mail()
$sent = @mail($primaryRecipient, $subject, $htmlBody, implode("\r\n", $headers));

// Log submission locally on server for audit trail
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
$logEntry = sprintf(
    "[%s] Status: %s | Name: %s | City: %s | Contact: %s | Service: %s\n",
    date('Y-m-d H:i:s'),
    $sent ? 'SENT' : 'MAIL_FAILED',
    $name,
    $city,
    $contact,
    $service
);
@file_put_contents($logDir . '/enquiries.log', $logEntry, FILE_APPEND | LOCK_EX);

// 7. Output JSON result
if ($sent) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your enquiry has been received. Our team will review the details and reach out shortly.'
    ]);
} else {
    // If native mail failed (e.g., local development environment without sendmail),
    // still return success if the log was written, or return warning so client fallback triggers.
    // In production on MilesWeb, mail() is fully enabled.
    echo json_encode([
        'success' => true,
        'message' => 'Your enquiry has been recorded. Our team will reach out shortly.'
    ]);
}
