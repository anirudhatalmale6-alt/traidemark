<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Load API key from config file (outside web root ideally, or .htaccess protected)
$configFile = __DIR__ . '/../config.json';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration missing']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
$apiKey = $config['ANTHROPIC_API_KEY'] ?? '';

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$systemPrompt = $input['systemPrompt'] ?? '';
$userContent = $input['userContent'] ?? '';

if (empty($systemPrompt) || empty($userContent)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing systemPrompt or userContent']);
    exit;
}

$payload = json_encode([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 3000,
    'system' => $systemPrompt,
    'messages' => [['role' => 'user', 'content' => $userContent]]
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Error connecting to Claude API: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);

if (isset($data['error'])) {
    http_response_code($httpCode);
    echo json_encode(['error' => $data['error']['message'] ?? 'Claude API error']);
    exit;
}

$text = '';
if (isset($data['content']) && is_array($data['content'])) {
    foreach ($data['content'] as $block) {
        $text .= $block['text'] ?? '';
    }
}

echo json_encode(['text' => $text]);
