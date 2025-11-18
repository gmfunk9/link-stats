<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/cache.php';
require_once __DIR__ . '/src/response.php';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

if ($requestMethod !== 'POST') {
    sendJsonResponse(buildErrorPayload('Invalid method; use POST.'));
}

clearCacheStorage();

sendJsonResponse([
    'message' => 'Cache cleared.',
]);
