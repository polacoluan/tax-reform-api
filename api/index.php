<?php

declare(strict_types=1);

require_once __DIR__ . '/calculateTaxReform.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

function jsonResponse(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    jsonResponse(405, [
        'error'   => 'Method not allowed',
        'allowed' => ['POST'],
    ]);
}

// Lê corpo JSON
$rawInput = file_get_contents('php://input') ?: '';
$payload  = $rawInput !== '' ? json_decode($rawInput, true) : null;

if ($rawInput !== '' && json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(400, [
        'error'   => 'Dados enviados inválidos',
        'details' => json_last_error_msg(),
    ]);
}

// Se veio como form-data / x-www-form-urlencoded
if ($payload === null && !empty($_POST)) {
    $payload = $_POST;
}

$calculateTaxReform = new CalculateTaxReform();
$result             = $calculateTaxReform->calculate($payload ?? []);

jsonResponse(200, [
    'status' => 'ok',
    'data'   => $result,
]);
