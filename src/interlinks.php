<?php

declare(strict_types=1);

require_once __DIR__ . '/html.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/cache.php';

function buildInterlinkResponses(
    string $html,
    string $baseUrl,
    array &$linkCache,
    string $cacheFilePath
): array {
    $links = extractAnchorLinks($html, $baseUrl);
    $interlinks = [];

    foreach ($links as $linkUrl) {
        $interlinks[$linkUrl] = fetchCachedInterlink($linkUrl, $linkCache, $cacheFilePath);
    }

    return $interlinks;
}

function fetchCachedInterlink(string $url, array &$linkCache, string $cacheFilePath): array
{
    if (isset($linkCache[$url])) {
        return $linkCache[$url];
    }

    $fetchResult = fetchUrlWithHeaders($url);

    if (isset($fetchResult['error'])) {
        $errorMessage = (string) $fetchResult['error'];
        error_log('Failed to fetch interlink ' . $url . ': ' . $errorMessage);
        $linkCache[$url] = ['error' => $errorMessage];
        saveLinkCache($cacheFilePath, $linkCache);
        return $linkCache[$url];
    }

    $response = [
        'status' => $fetchResult['status'],
        'loadTime' => $fetchResult['loadTime'],
        'finalUrl' => $fetchResult['finalUrl'],
    ];

    if (isset($fetchResult['body'])) {
        $metadata = parseHtmlMetadata($fetchResult['body']);
        $response = array_merge($response, $metadata);
    }

    $linkCache[$url] = $response;
    saveLinkCache($cacheFilePath, $linkCache);

    return $response;
}
