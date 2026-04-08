<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$to = filter_var($input['to'] ?? '', FILTER_VALIDATE_EMAIL);
$service = $input['service'] ?? '';
$resultHtml = $input['resultHtml'] ?? '';
$markName = $input['markName'] ?? '';
$isAdmin = $input['isAdmin'] ?? false;

if (!$to || !$resultHtml) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing email or result']);
    exit;
}

$from = 'resultados@traidemark.es';
$fromName = 'trAIdemark';

if ($isAdmin) {
    $subject = "NUEVO ENCARGO — Revisión Profesional — $markName";
} elseif ($service === 'distinctiveness') {
    $subject = "Análisis de Distintividad — $markName — trAIdemark";
} else {
    $subject = "Escrito de Oposición/Defensa — $markName — trAIdemark";
}

$boundary = md5(uniqid(time()));

$headers = implode("\r\n", [
    "From: $fromName <$from>",
    "Reply-To: $from",
    "MIME-Version: 1.0",
    "Content-Type: multipart/alternative; boundary=\"$boundary\"",
    "X-Mailer: trAIdemark/1.0"
]);

// Plain text version
$textBody = strip_tags(str_replace(['<br/>', '<br>', '</p>', '</h2>', '</h1>', '</li>'], "\n", $resultHtml));
$textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');

// HTML version
$htmlBody = "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body style='font-family:Calibri,Arial,sans-serif;font-size:14px;line-height:1.7;color:#333;max-width:700px;margin:0 auto;padding:20px;'>
<div style='text-align:center;padding:20px 0;border-bottom:2px solid #E8845C;margin-bottom:24px;'>
<h1 style='font-family:Georgia,serif;color:#1a365d;font-size:22px;margin:0;'>trAIdemark</h1>
<p style='color:#888;font-size:12px;margin:4px 0 0;'>Propiedad intelectual asistida por IA</p>
</div>
<h2 style='font-family:Georgia,serif;color:#1a365d;font-size:18px;border-bottom:1px solid #ddd;padding-bottom:8px;'>$subject</h2>
$resultHtml
<div style='margin-top:32px;padding-top:16px;border-top:1px solid #ddd;text-align:center;'>
<p style='font-size:11px;color:#999;'>Este documento ha sido generado por trAIdemark. Los resultados son orientativos y no sustituyen el asesoramiento de un profesional habilitado en propiedad industrial.</p>
<p style='font-size:11px;color:#999;'>© " . date('Y') . " trAIdemark — <a href='https://traidemark.es' style='color:#E8845C;'>traidemark.es</a></p>
</div>
</body></html>";

$body = "--$boundary\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$body .= $textBody . "\r\n\r\n";
$body .= "--$boundary\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$body .= $htmlBody . "\r\n\r\n";
$body .= "--$boundary--\r\n";

$encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
error_log("trAIdemark email: sending to=$to subject=$subject isAdmin=" . ($isAdmin ? "yes" : "no"));
$sent = mail($to, $encodedSubject, $body, $headers);
error_log("trAIdemark email: mail() returned " . ($sent ? "true" : "false") . " to=$to");

if ($sent) {
    echo json_encode(['success' => true, 'to' => $to, 'isAdmin' => $isAdmin]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email. Server mail configuration may be required.']);
}
