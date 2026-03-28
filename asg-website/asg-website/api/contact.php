<?php
/**
 * ASG — Contact Form API
 */
define('ASG_BOOT', 1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
CSRF::verifyOrFail();

$name    = sanitize($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = sanitize($_POST['subject'] ?? '');
$message = sanitize($_POST['message'] ?? '');

if (!$name || !$email || !$message) json_err('Name, email and message are required');
if (!validate_email($email))        json_err('Invalid email address');
if (strlen($message) < 10)         json_err('Message too short');

if (!RateLimit::check('contact', 3, 3600)) json_err('Too many contact requests. Please wait an hour.', 429);

DB::insert(
    "INSERT INTO contact_messages (name, email, subject, message, ip_address) VALUES (?,?,?,?,?)",
    [$name, $email, $subject, $message, client_ip()]
);

audit('contact.submit', null, ['email' => $email]);
json_ok(['message' => 'Thank you! We\'ll be in touch soon.']);
