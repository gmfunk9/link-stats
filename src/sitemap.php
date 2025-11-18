<?php

declare(strict_types=1);

require_once __DIR__ . '/http.php';

const SITEMAP_SERVICE_BASE = 'https://getsitemap.funkpd.com/json?url=';

function fetchSitemapUrls(string $sitemapUrl): array
{
    $serviceUrl = buildSitemapServiceUrl($sitemapUrl);
    $contentResult = fetchSimpleContent($serviceUrl, 20);

    if (isset($contentResult['error'])) {
        error_log('Failed to fetch sitemap service: ' . $contentResult['error']);
        return [];
    }

    $body = $contentResult['body'] ?? '';

    if ($body === '') {
        error_log('Empty sitemap service response from ' . $serviceUrl);
        return [];
    }

    $payload = decodeSitemapServiceResponse($body);

    if (count($payload) === 0) {
        return [];
    }

    $urls = extractSitemapUrls($payload);

    if (count($urls) === 0) {
        error_log('No URLs returned by sitemap service for ' . $sitemapUrl);
        return [];
    }

    return $urls;
}

function buildSitemapServiceUrl(string $sitemapUrl): string
{
    $encodedUrl = rawurlencode($sitemapUrl);
    return SITEMAP_SERVICE_BASE . $encodedUrl;
}

function decodeSitemapServiceResponse(string $body): array
{
    $decoded = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $message = json_last_error_msg();
        error_log('Invalid JSON returned by sitemap service: ' . $message);
        return [];
    }

    if (!is_array($decoded)) {
        error_log('Unexpected JSON root returned by sitemap service.');
        return [];
    }

    return $decoded;
}

function extractSitemapUrls(array $payload): array
{
    $urls = findUrlsInPayload($payload);

    if (count($urls) === 0) {
        return [];
    }

    return array_values(array_unique($urls));
}

function findUrlsInPayload(array $payload): array
{
    $urls = [];

    if (array_key_exists('urls', $payload)) {
        $urlsValue = $payload['urls'];

        if (is_array($urlsValue)) {
            $urls = filterValidUrls($urlsValue);
        }
    }

    if (count($urls) > 0) {
        return $urls;
    }

    $collected = [];

    foreach ($payload as $value) {
        if (!is_array($value)) {
            continue;
        }

        $nestedUrls = findUrlsInPayload($value);

        if (count($nestedUrls) === 0) {
            continue;
        }

        foreach ($nestedUrls as $nestedUrl) {
            $collected[] = $nestedUrl;
        }
    }

    return $collected;
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

        if (!$validatedUrl) {
            continue;
        }

        $validUrls[] = $validatedUrl;
    }

    return $validUrls;
}
