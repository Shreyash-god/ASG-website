<?php
/**
 * ASG — Donations API
 * Supports: PayPal, Card (Razorpay), UPI, Bank Transfer
 */
define('ASG_BOOT', 1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!feature('donations_enabled')) json_err('Donations are currently disabled', 503);

$action = $_GET['action'] ?? '';

match($action) {
    'create'         => createDonation(),
    'verify_paypal'  => verifyPaypal(),
    'verify_razorpay'=> verifyRazorpay(),
    'bank_info'      => bankInfo(),
    default          => json_err('Unknown action', 404)
};

function createDonation(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    CSRF::verifyOrFail();

    $amount  = (float)($_POST['amount'] ?? 0);
    $currency = strtoupper(sanitize($_POST['currency'] ?? 'USD'));
    $method  = sanitize($_POST['method'] ?? '');
    $name    = sanitize($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $anon    = isset($_POST['anonymous']) ? 1 : 0;

    if ($amount < 1)         json_err('Minimum donation is 1');
    if ($amount > 100000)    json_err('Maximum donation is 100,000');
    $allowed = ['paypal','card','upi','bank_transfer'];
    if (!in_array($method, $allowed)) json_err('Invalid payment method');
    if ($email && !validate_email($email)) json_err('Invalid email');

    $id = DB::insert(
        "INSERT INTO donations (donor_name, donor_email, amount, currency, method, message, is_anonymous) VALUES (?,?,?,?,?,?,?)",
        [$anon ? null : $name, $anon ? null : $email, $amount, $currency, $method, $message, $anon]
    );

    audit('donation.create', "donation:$id", ['amount' => $amount, 'method' => $method]);

    if ($method === 'paypal') {
        json_ok(['donation_id' => $id, 'paypal_email' => setting('paypal_email')]);
    }

    if ($method === 'card') {
        // Razorpay order creation
        $order = razorpayCreateOrder((int)($amount * 100), $currency, "donation_{$id}");
        json_ok(['donation_id' => $id, 'razorpay_key' => RAZORPAY_KEY_ID, 'order' => $order]);
    }

    if ($method === 'upi') {
        json_ok(['donation_id' => $id, 'upi_id' => setting('upi_id'), 'amount' => $amount]);
    }

    if ($method === 'bank_transfer') {
        json_ok(['donation_id' => $id, 'bank_details' => setting('bank_account')]);
    }

    json_ok(['donation_id' => $id]);
}

function verifyPaypal(): never {
    // Verify PayPal IPN / webhook
    $payment_id = sanitize($_POST['payment_id'] ?? '');
    $donation_id = (int)($_POST['donation_id'] ?? 0);

    if (!$payment_id || !$donation_id) json_err('Missing parameters');

    DB::query("UPDATE donations SET status='completed', payment_id=? WHERE id=? AND status='pending'",
              [$payment_id, $donation_id]);
    audit('donation.completed', "donation:$donation_id", ['method' => 'paypal']);
    json_ok(['message' => 'Thank you for your donation!']);
}

function verifyRazorpay(): never {
    $payment_id  = sanitize($_POST['razorpay_payment_id'] ?? '');
    $order_id    = sanitize($_POST['razorpay_order_id'] ?? '');
    $signature   = sanitize($_POST['razorpay_signature'] ?? '');
    $donation_id = (int)($_POST['donation_id'] ?? 0);

    if (!$payment_id || !$order_id || !$signature) json_err('Missing parameters');

    $expected = hash_hmac('sha256', $order_id . '|' . $payment_id, RAZORPAY_KEY_SECRET);
    if (!hash_equals($expected, $signature)) json_err('Signature mismatch', 400);

    DB::query("UPDATE donations SET status='completed', payment_id=? WHERE id=? AND status='pending'",
              [$payment_id, $donation_id]);
    audit('donation.completed', "donation:$donation_id", ['method' => 'card']);
    json_ok(['message' => 'Thank you for your donation!']);
}

function bankInfo(): never {
    json_ok(['bank_details' => setting('bank_account'), 'upi_id' => setting('upi_id')]);
}

function razorpayCreateOrder(int $amountPaisa, string $currency, string $receipt): array {
    $payload = json_encode(['amount' => $amountPaisa, 'currency' => $currency, 'receipt' => $receipt]);
    $auth    = base64_encode(RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
    $ctx     = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Authorization: Basic $auth\r\nContent-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 15,
    ]]);
    $resp = file_get_contents('https://api.razorpay.com/v1/orders', false, $ctx);
    return json_decode($resp ?: '{}', true);
}
