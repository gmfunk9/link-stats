<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/sitemap.php';
require_once __DIR__ . '/src/response.php';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
$requestedUrl = $_POST['url'] ?? '';

if ($requestMethod !== 'POST') {
    sendJsonResponse(buildErrorPayload('Invalid method; use POST.'));
}

if ($requestedUrl === '') {
    sendJsonResponse(buildErrorPayload('Missing field url; add to body.'));
}

$validatedUrl = filter_var($requestedUrl, FILTER_VALIDATE_URL);

if (!$validatedUrl) {
    sendJsonResponse(buildErrorPayload('Invalid sitemap URL provided.'));
}

$urls = fetchSitemapUrls($validatedUrl);

if (count($urls) === 0) {
    sendJsonResponse(buildErrorPayload('No URLs found in sitemap.'));
}

sendJsonResponse($urls);
