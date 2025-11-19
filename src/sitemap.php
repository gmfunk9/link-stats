<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/response.php';

const SITEMAP_SERVICE_BASE = 'http://getsitemap.funkpd.com/json?url=';
const DAILY_URL_LIMIT = 3;

if (isSitemapHttpRequest()) {
    handleSitemapRequest();
}

function handleSitemapRequest(): void
{
    header('Content-Type: application/json');
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($requestMethod !== 'POST') {
        sendJsonResponse(buildErrorPayload('Invalid method; use POST.'));
        return;
    }

    $requestedUrl = $_POST['url'] ?? '';

    if ($requestedUrl === '') {
        sendJsonResponse(buildErrorPayload('Missing field url; add to body.'));
        return;
    }

    $validatedUrl = filter_var($requestedUrl, FILTER_VALIDATE_URL);

    if ($validatedUrl === false) {
        sendJsonResponse(buildErrorPayload('Invalid sitemap URL provided.'));
        return;
    }

    $normalizedSitemapUrl = normalizeSitemapUrl($validatedUrl);
    $resumeIndex = null;

    if (array_key_exists('resumeIndex', $_POST)) {
        $candidateIndex = filter_var(
            $_POST['resumeIndex'],
            FILTER_VALIDATE_INT
        );

        if ($candidateIndex !== false && $candidateIndex >= 0) {
            $resumeIndex = $candidateIndex;
        }
    }
    try {
        $urls = fetchSitemapUrls($normalizedSitemapUrl);
    } catch (RuntimeException $exception) {
        sendJsonResponse(buildErrorPayload($exception->getMessage()));
        return;
    }

    if (count($urls) === 0) {
        sendJsonResponse(buildErrorPayload('No URLs found in sitemap.'));
        return;
    }

    $rateLimited = rateLimitUrls(
        $urls,
        $normalizedSitemapUrl,
        DAILY_URL_LIMIT,
        $resumeIndex
    );
    sendJsonResponse($rateLimited);
}

function isSitemapHttpRequest(): bool
{
    $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';

    if ($scriptPath === '') {
        return false;
    }

    return realpath($scriptPath) === __FILE__;
}

function fetchSitemapUrls(string $sitemapUrl): array
{
    $serviceUrl = buildSitemapServiceUrl($sitemapUrl);
    $contentResult = fetchSimpleContent($serviceUrl, 20);

    if (isset($contentResult['error'])) {
        $errorMessage = $contentResult['error'];
        error_log('Failed to fetch sitemap service: ' . $errorMessage);
        throw new RuntimeException('Failed to reach sitemap service: ' . $errorMessage);
    }

    $body = $contentResult['body'] ?? '';

    if ($body === '') {
        error_log('Empty sitemap service response from ' . $serviceUrl);
        throw new RuntimeException('Empty sitemap service response from ' . $serviceUrl);
    }

    $payload = decodeSitemapServiceResponse($body);
    $urls = extractSitemapUrls($payload);

    return $urls;
}

function buildSitemapServiceUrl(string $sitemapUrl): string
{
    $encodedUrl = rawurlencode($sitemapUrl);
    return SITEMAP_SERVICE_BASE . $encodedUrl;
}

function normalizeSitemapUrl(string $inputUrl): string
{
    $parsedUrl = parse_url($inputUrl);

    if ($parsedUrl === false) {
        return $inputUrl;
    }

    $scheme = $parsedUrl['scheme'] ?? '';

    if ($scheme === '') {
        return $inputUrl;
    }

    $host = $parsedUrl['host'] ?? '';

    if ($host === '') {
        return $inputUrl;
    }

    $normalizedUrl = $scheme . '://' . $host;
    $port = $parsedUrl['port'] ?? null;

    if ($port !== null) {
        $normalizedUrl .= ':' . $port;
    }

    $path = $parsedUrl['path'] ?? '';

    if ($path === '') {
        return $normalizedUrl;
    }

    $lastSegment = basename($path);

    if ($lastSegment !== '') {
        $segmentLower = strtolower($lastSegment);
        $segmentContainsSitemap = strpos($segmentLower, 'sitemap') !== false;

        if ($segmentContainsSitemap) {
            return $normalizedUrl;
        }
    }

    $normalizedUrl .= $path;
    $query = $parsedUrl['query'] ?? '';

    if ($query !== '') {
        $normalizedUrl .= '?' . $query;
    }

    $fragment = $parsedUrl['fragment'] ?? '';

    if ($fragment !== '') {
        $normalizedUrl .= '#' . $fragment;
    }

    return $normalizedUrl;
}

function decodeSitemapServiceResponse(string $body): array
{
    $decoded = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $message = json_last_error_msg();
        error_log('Invalid JSON returned by sitemap service: ' . $message);
        throw new RuntimeException('Invalid JSON returned by sitemap service: ' . $message);
    }

    if (!is_array($decoded)) {
        error_log('Unexpected JSON root returned by sitemap service.');
        throw new RuntimeException('Unexpected JSON root returned by sitemap service.');
    }

    return $decoded;
}

function extractSitemapUrls(array $payload): array
{
    $sitemapEntries = readSitemapEntries($payload);

    if (count($sitemapEntries) === 0) {
        throw new RuntimeException('Sitemap service returned an empty sitemap list.');
    }

    $filtered = filterValidUrls($sitemapEntries);

    if (count($filtered) === 0) {
        throw new RuntimeException('Sitemap service payload did not contain any valid URLs.');
    }

    return array_values(array_unique($filtered));
}

function readSitemapEntries(array $payload): array
{
    if (!array_key_exists('success', $payload)) {
        error_log('Sitemap payload missing success flag.');
        throw new RuntimeException('Sitemap payload missing success flag.');
    }

    $serviceSuccess = $payload['success'];

    if ($serviceSuccess !== true) {
        $message = $payload['message'] ?? 'Unknown sitemap service failure.';
        error_log('Sitemap service reported failure: ' . $message);
        throw new RuntimeException('Sitemap service reported failure: ' . $message);
    }

    if (!array_key_exists('sitemap', $payload)) {
        error_log('Sitemap payload missing sitemap list.');
        throw new RuntimeException('Sitemap payload missing sitemap list.');
    }

    $sitemapEntries = $payload['sitemap'];

    if (!is_array($sitemapEntries)) {
        error_log('Sitemap list is not an array.');
        throw new RuntimeException('Sitemap list is not an array.');
    }

    return $sitemapEntries;
}

function filterValidUrls(array $values): array
{
    $validUrls = [];

    foreach ($values as $value) {
        if (!is_string($value)) {
            continue;
        }

        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            continue;
        }

        $validatedUrl = filter_var($trimmedValue, FILTER_VALIDATE_URL);

        if ($validatedUrl === false) {
            continue;
        }

        $validUrls[] = $validatedUrl;
    }

    return $validUrls;
}
