<?php

declare(strict_types=1);

function extractAnchorLinks(string $html, string $baseUrl): array
{
    $pattern = '/<a\s+[^>]*href="([^"]+)"[^>]*>/i';
    $matches = [];
    preg_match_all($pattern, $html, $matches);

    if (count($matches) < 2) {
        return [];
    }

    $rawLinks = $matches[1];
    $cleanLinks = [];

    foreach ($rawLinks as $rawLink) {
        $trimmedLink = trim($rawLink);

        if ($trimmedLink === '') {
            continue;
        }

        if ($trimmedLink === '0') {
            continue;
        }

        $linkWithoutFragment = explode('#', $trimmedLink)[0];
        $absoluteUrl = normalizeLink($linkWithoutFragment, $baseUrl);

        if (!$absoluteUrl) {
            continue;
        }

        $cleanLinks[] = $absoluteUrl;
    }

    return array_values(array_unique($cleanLinks));
}

function normalizeLink(string $link, string $baseUrl): ?string
{
    if (filter_var($link, FILTER_VALIDATE_URL)) {
        return $link;
    }

    if (str_starts_with($link, '/')) {
        $parts = parse_url($baseUrl);

        if (!$parts) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';

        if ($host === '') {
            return null;
        }

        $path = ltrim($link, '/');

        return $scheme . '://' . $host . '/' . $path;
    }

    return null;
}

function parseHtmlMetadata(string $html): array
{
    $metadata = [];
    $title = parseHtmlTitle($html);

    if ($title) {
        $metadata['title'] = $title;
    }

    $contentLength = strlen($html);

    if ($contentLength > 0) {
        $metadata['contentLength'] = $contentLength;
    }

    $canonicalUrl = parseCanonicalUrl($html);

    if ($canonicalUrl) {
        $metadata['finalUrl'] = $canonicalUrl;
    }

    return $metadata;
}

function parseHtmlTitle(string $html): ?string
{
    $pattern = '/<title>(.*)<\/title>/i';
    $matches = [];
    preg_match($pattern, $html, $matches);

    if (count($matches) < 2) {
        return null;
    }

    $title = trim($matches[1]);

    if ($title === '') {
        return null;
    }

    return $title;
}

function parseCanonicalUrl(string $html): ?string
{
    $pattern = '/<link\s+rel="canonical"\s+href="([^"]+)"/i';
    $matches = [];
    preg_match($pattern, $html, $matches);

    if (count($matches) < 2) {
        return null;
    }

    $canonicalUrl = trim($matches[1]);

    if ($canonicalUrl === '') {
        return null;
    }

    return $canonicalUrl;
}
