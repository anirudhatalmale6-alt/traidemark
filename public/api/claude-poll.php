<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$jobId = $_GET['id'] ?? '';
if (empty($jobId) || !preg_match('/^[a-f0-9]{32}$/', $jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid job ID']);
    exit;
}

$jobDir = sys_get_temp_dir() . '/traidemark_jobs';
$resultFile = "$jobDir/$jobId.json";

if (!file_exists($resultFile)) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

$data = json_decode(file_get_contents($resultFile), true);

// Clean up completed/errored jobs (older than 5 minutes)
if (($data['status'] ?? '') !== 'processing') {
    // Return the result then clean up
    echo json_encode($data);
    // Delete after serving (give a grace period for retries)
    if (isset($data['started']) && time() - $data['started'] > 300) {
        @unlink($resultFile);
    }
    exit;
}

// Still processing - check if it's been too long (5 min timeout)
$started = $data['started'] ?? 0;
if ($started > 0 && time() - $started > 300) {
    file_put_contents($resultFile, json_encode(['status' => 'error', 'error' => 'Generation timed out. Please try again.']));
    echo json_encode(['status' => 'error', 'error' => 'Generation timed out. Please try again.']);
    exit;
}

echo json_encode(['status' => 'processing']);
