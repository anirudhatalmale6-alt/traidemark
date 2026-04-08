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
$sessionId = $input['sessionId'] ?? '';

if (empty($sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing session ID']);
    exit;
}

// Retrieve Checkout Session from Stripe
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERPWD => $stripeKey . ':',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode >= 400 || isset($data['error'])) {
    http_response_code($httpCode ?: 500);
    echo json_encode(['error' => 'Could not verify payment']);
    exit;
}

$paid = ($data['payment_status'] ?? '') === 'paid';

echo json_encode([
    'paid' => $paid,
    'status' => $data['payment_status'] ?? 'unknown',
    'service' => $data['metadata']['service'] ?? '',
    'email' => $data['customer_email'] ?? '',
    'amount' => ($data['amount_total'] ?? 0) / 100
]);
