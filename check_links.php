<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/cache.php';
require_once __DIR__ . '/src/http.php';
require_once __DIR__ . '/src/html.php';
require_once __DIR__ . '/src/interlinks.php';
require_once __DIR__ . '/src/response.php';

$mainUrl = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);

if (!$mainUrl) {
    sendJsonResponse(buildErrorPayload('Missing field url; add to query string.'));
}

$cacheFilePath = buildCacheFilePath($mainUrl);
$linkCache = loadLinkCache($cacheFilePath);
$mainPageResult = fetchUrlWithHeaders($mainUrl);

if (isset($mainPageResult['error'])) {
    sendJsonResponse(buildErrorPayload($mainPageResult['error']));
}

if (!isset($mainPageResult['body'])) {
    sendJsonResponse(buildErrorPayload('Missing HTML body from main page.'));
}

$mainPageResponse = fetchCachedInterlink($mainUrl, $linkCache, $cacheFilePath);
$interlinks = buildInterlinkResponses(
    $mainPageResult['body'],
    $mainPageResult['finalUrl'],
    $linkCache,
    $cacheFilePath
);

$payload = [
    'page' => $mainUrl,
    'mainUrl' => $mainPageResponse,
    'interlinks' => $interlinks,
];

sendJsonResponse($payload);
