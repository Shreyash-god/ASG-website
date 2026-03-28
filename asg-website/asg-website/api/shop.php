<?php
/**
 * ASG — Shop API (Products + Orders)
 */
define('ASG_BOOT', 1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!feature('shop_enabled')) json_err('Shop is currently disabled', 503);

$action = $_GET['action'] ?? 'list';

match($action) {
    'list'           => listProducts(),
    'get'            => getProduct(),
    'create'         => createProduct(),
    'update'         => updateProduct(),
    'delete'         => deleteProduct(),
    'checkout'       => checkout(),
    'verify_payment' => verifyPayment(),
    'orders'         => listOrders(),
    default          => json_err('Unknown action', 404)
};

function listProducts(): never {
    $cat    = sanitize($_GET['category'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(24, (int)($_GET['limit'] ?? 12));
    $offset = ($page - 1) * $limit;

    $where  = ['is_active=1'];
    $params = [];
    if ($cat && in_array($cat, ['merchandise','book','digital','other'])) {
        $where[] = 'category=?';
        $params[] = $cat;
    }
    $cond  = 'WHERE ' . implode(' AND ', $where);
    $total = DB::count("SELECT COUNT(*) FROM products $cond", $params);
    $prods = DB::all(
        "SELECT id, uuid, name, slug, description, price, currency, category,
                cover_image, stock, is_featured FROM products $cond
         ORDER BY is_featured DESC, created_at DESC LIMIT $limit OFFSET $offset",
        $params
    );
    json_ok(['products' => $prods, 'total' => $total, 'page' => $page]);
}

function getProduct(): never {
    $slug = sanitize($_GET['slug'] ?? '');
    if (!$slug) json_err('Slug required');
    $p = DB::one("SELECT * FROM products WHERE slug=? AND is_active=1", [$slug]);
    if (!$p) json_err('Product not found', 404);
    $p['images'] = json_decode($p['images'] ?? '[]', true);
    json_ok(['product' => $p]);
}

function createProduct(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    Auth::requireAdmin(); CSRF::verifyOrFail();

    $name     = sanitize($_POST['name'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $cat      = sanitize($_POST['category'] ?? 'merchandise');
    $desc     = sanitize($_POST['description'] ?? '');
    $stock    = (int)($_POST['stock'] ?? -1);
    $featured = isset($_POST['featured']) ? 1 : 0;

    if (!$name || $price <= 0) json_err('Name and price required');

    $slug = slugify($name);
    if (DB::count("SELECT COUNT(*) FROM products WHERE slug=?", [$slug])) $slug .= '-' . time();

    $cover = null;
    if (!empty($_FILES['cover']['name'])) {
        $up    = upload_file($_FILES['cover'], 'uploads/products');
        $cover = $up['url'];
    }

    $id = DB::insert(
        "INSERT INTO products (name, slug, description, price, category, cover_image, stock, is_featured) VALUES (?,?,?,?,?,?,?,?)",
        [$name, $slug, $desc, $price, $cat, $cover, $stock, $featured]
    );
    audit('product.create', "product:$id");
    json_ok(['product_id' => $id, 'message' => 'Product created']);
}

function updateProduct(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    Auth::requireAdmin(); CSRF::verifyOrFail();

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) json_err('ID required');
    $p = DB::one("SELECT * FROM products WHERE id=?", [$id]);
    if (!$p) json_err('Not found', 404);

    $name     = sanitize($_POST['name']     ?? $p['name']);
    $price    = (float)($_POST['price']     ?? $p['price']);
    $desc     = sanitize($_POST['description'] ?? $p['description']);
    $stock    = (int)($_POST['stock']       ?? $p['stock']);
    $active   = isset($_POST['active'])   ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $cover    = $p['cover_image'];
    if (!empty($_FILES['cover']['name'])) {
        $up = upload_file($_FILES['cover'], 'uploads/products');
        $cover = $up['url'];
    }

    DB::query(
        "UPDATE products SET name=?, price=?, description=?, cover_image=?, stock=?, is_active=?, is_featured=? WHERE id=?",
        [$name, $price, $desc, $cover, $stock, $active, $featured, $id]
    );
    audit('product.update', "product:$id");
    json_ok(['message' => 'Product updated']);
}

function deleteProduct(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    Auth::requireAdmin(); CSRF::verifyOrFail();
    $id = (int)($_POST['id'] ?? 0);
    DB::query("UPDATE products SET is_active=0 WHERE id=?", [$id]);
    audit('product.delete', "product:$id");
    json_ok(['message' => 'Product removed']);
}

function checkout(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    CSRF::verifyOrFail();

    $items   = json_decode($_POST['items'] ?? '[]', true);
    $method  = sanitize($_POST['method'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $name    = sanitize($_POST['name'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    if (empty($items) || !is_array($items)) json_err('Cart is empty');
    if (!in_array($method, ['paypal','card','upi','bank_transfer'])) json_err('Invalid payment method');

    $subtotal = 0;
    $lineItems = [];
    foreach ($items as $item) {
        $pid = (int)$item['product_id'];
        $qty = max(1, (int)$item['qty']);
        $p   = DB::one("SELECT * FROM products WHERE id=? AND is_active=1", [$pid]);
        if (!$p) json_err("Product #$pid not found");
        if ($p['stock'] >= 0 && $p['stock'] < $qty) json_err("{$p['name']} — insufficient stock");
        $lineItems[] = ['product' => $p, 'qty' => $qty];
        $subtotal   += $p['price'] * $qty;
    }

    $tax      = round($subtotal * 0.00, 2); // adjust tax rate
    $total    = $subtotal + $tax;
    $currency = 'INR'; // or set per product

    // Create order
    $oid = DB::insert(
        "INSERT INTO orders (user_id, guest_email, status, payment_method, subtotal, tax, total, currency, shipping_name, shipping_addr) VALUES (?,?,?,?,?,?,?,?,?,?)",
        [Auth::id(), $email ?: null, 'pending', $method, $subtotal, $tax, $total, $currency, $name, $address]
    );

    foreach ($lineItems as $li) {
        $p = $li['product'];
        DB::insert(
            "INSERT INTO order_items (order_id, product_id, qty, unit_price, total_price) VALUES (?,?,?,?,?)",
            [$oid, $p['id'], $li['qty'], $p['price'], $p['price'] * $li['qty']]
        );
        if ($p['stock'] >= 0) {
            DB::query("UPDATE products SET stock=stock-? WHERE id=?", [$li['qty'], $p['id']]);
        }
    }

    audit('order.create', "order:$oid", ['total' => $total, 'method' => $method]);

    if ($method === 'card') {
        $order = razorpayOrder((int)($total * 100), $currency, "order_$oid");
        json_ok(['order_id' => $oid, 'razorpay_key' => RAZORPAY_KEY_ID, 'rzp_order' => $order]);
    }
    if ($method === 'paypal') {
        json_ok(['order_id' => $oid, 'paypal_email' => setting('paypal_email'), 'total' => $total]);
    }
    if ($method === 'upi') {
        json_ok(['order_id' => $oid, 'upi_id' => setting('upi_id'), 'total' => $total]);
    }
    if ($method === 'bank_transfer') {
        json_ok(['order_id' => $oid, 'bank_details' => setting('bank_account'), 'total' => $total]);
    }

    json_ok(['order_id' => $oid]);
}

function verifyPayment(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    CSRF::verifyOrFail();

    $oid        = (int)($_POST['order_id'] ?? 0);
    $payment_id = sanitize($_POST['payment_id'] ?? '');
    $order_id   = sanitize($_POST['razorpay_order_id'] ?? '');
    $signature  = sanitize($_POST['razorpay_signature'] ?? '');

    if (!$oid) json_err('Order ID required');
    $order = DB::one("SELECT * FROM orders WHERE id=?", [$oid]);
    if (!$order) json_err('Order not found', 404);

    if ($order['payment_method'] === 'card' && $signature) {
        $expected = hash_hmac('sha256', $order_id . '|' . $payment_id, RAZORPAY_KEY_SECRET);
        if (!hash_equals($expected, $signature)) json_err('Signature mismatch', 400);
    }

    DB::query("UPDATE orders SET status='paid', payment_id=?, paid_at=NOW() WHERE id=?",
              [$payment_id ?: 'manual', $oid]);
    audit('order.paid', "order:$oid");
    json_ok(['message' => 'Payment verified. Thank you for your order!']);
}

function listOrders(): never {
    if (!Auth::check()) json_err('Not authenticated', 401);
    $uid = Auth::isAdmin() && isset($_GET['user_id'])
         ? (int)$_GET['user_id']
         : Auth::id();

    $orders = DB::all(
        "SELECT o.*, GROUP_CONCAT(p.name SEPARATOR ', ') AS product_names
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id=o.id
         LEFT JOIN products p ON p.id=oi.product_id
         WHERE o.user_id=?
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50",
        [$uid]
    );
    json_ok(['orders' => $orders]);
}

function razorpayOrder(int $amountPaisa, string $currency, string $receipt): array {
    $payload = json_encode(['amount' => $amountPaisa, 'currency' => $currency, 'receipt' => $receipt]);
    $auth    = base64_encode(RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
    $ctx     = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Authorization: Basic $auth\r\nContent-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 15,
    ]]);
    $r = file_get_contents('https://api.razorpay.com/v1/orders', false, $ctx);
    return json_decode($r ?: '{}', true);
}
