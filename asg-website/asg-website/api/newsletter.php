<?php
/**
 * ASG — Newsletter API
 */
define('ASG_BOOT', 1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!feature('newsletter_enabled')) json_err('Newsletter is currently disabled', 503);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
CSRF::verifyOrFail();

$email = trim($_POST['email'] ?? '');
if (!validate_email($email)) json_err('Invalid email address');

$exists = DB::one("SELECT id FROM newsletter WHERE email=?", [$email]);
if ($exists) {
    if (!$exists['is_active']) {
        DB::query("UPDATE newsletter SET is_active=1 WHERE email=?", [$email]);
        json_ok(['message' => 'Welcome back! You\'ve been re-subscribed.']);
    }
    json_ok(['message' => 'You\'re already subscribed!']);
}

DB::insert("INSERT INTO newsletter (email) VALUES (?)", [$email]);
audit('newsletter.subscribe', null, ['email' => $email]);
json_ok(['message' => 'Subscribed successfully! Welcome to ASG.']);
