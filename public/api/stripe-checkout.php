<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$configFile = __DIR__ . '/../config.json';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration missing']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
$stripeKey = $config['STRIPE_SECRET_KEY'] ?? '';

if (empty($stripeKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe not configured']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = intval($input['amount'] ?? 0); // amount in cents
$description = $input['description'] ?? 'trAIdemark Service';
$email = $input['email'] ?? '';
$service = $input['service'] ?? 'unknown';
$sessionData = $input['sessionData'] ?? '{}'; // JSON string with form data to restore after payment

if ($amount < 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

// Create Stripe Checkout Session via API
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERPWD => $stripeKey . ':',
    CURLOPT_POSTFIELDS => http_build_query([
        'payment_method_types[]' => 'card',
        'mode' => 'payment',
        'line_items[0][quantity]' => 1,
        'line_items[0][price_data][currency]' => 'eur',
        'line_items[0][price_data][unit_amount]' => $amount,
        'line_items[0][price_data][product_data][name]' => $description,
        'customer_email' => $email ?: null,
        'success_url' => $origin . '/?payment=success&session_id={CHECKOUT_SESSION_ID}&service=' . urlencode($service),
        'cancel_url' => $origin . '/?payment=cancelled&service=' . urlencode($service),
        'metadata[service]' => $service,
        'metadata[sessionData]' => substr($sessionData, 0, 500),
    ])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode >= 400 || isset($data['error'])) {
    http_response_code($httpCode ?: 500);
    echo json_encode(['error' => $data['error']['message'] ?? 'Stripe error']);
    exit;
}

echo json_encode([
    'url' => $data['url'],
    'sessionId' => $data['id']
]);
