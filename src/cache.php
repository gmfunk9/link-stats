<?php

declare(strict_types=1);

const CACHE_ROOT = __DIR__ . '/../cache';
const LINK_CACHE_DIR = CACHE_ROOT . '/links';
const RATE_LIMIT_DIR = CACHE_ROOT . '/rate-limit';

function ensureDirectory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    mkdir($directory, 0775, true);
}

function buildCacheFilePath(string $url): string
{
    ensureDirectory(LINK_CACHE_DIR);
    $hash = hash('sha1', $url);
    return LINK_CACHE_DIR . '/' . $hash . '.json';
}

function loadLinkCache(string $filePath): array
{
    if (!is_file($filePath)) {
        return [];
    }

    $raw = file_get_contents($filePath);

    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function saveLinkCache(string $filePath, array $linkCache): void
{
    ensureDirectory(dirname($filePath));
    $json = json_encode($linkCache);

    if ($json === false) {
        return;
    }

    file_put_contents($filePath, $json, LOCK_EX);
}

function buildRateLimitFilePath(string $sitemapUrl): string
{
    ensureDirectory(RATE_LIMIT_DIR);
    $hash = hash('sha1', $sitemapUrl);
    return RATE_LIMIT_DIR . '/' . $hash . '.json';
}

function loadRateLimitEntry(string $sitemapUrl): array
{
    $filePath = buildRateLimitFilePath($sitemapUrl);

    if (!is_file($filePath)) {
        return createRateLimitEntry();
    }

    $raw = file_get_contents($filePath);

    if ($raw === false) {
        return createRateLimitEntry();
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        return createRateLimitEntry();
    }

    return array_merge(createRateLimitEntry(), $decoded);
}

function saveRateLimitEntry(string $sitemapUrl, array $entry): void
{
    $filePath = buildRateLimitFilePath($sitemapUrl);
    $json = json_encode($entry);

    if ($json === false) {
        return;
    }

    file_put_contents($filePath, $json, LOCK_EX);
}

function createRateLimitEntry(): array
{
    return [
        'index' => 0,
        'processedToday' => 0,
        'date' => date('Y-m-d'),
    ];
}

function rateLimitUrls(
    array $urls,
    string $sitemapUrl,
    int $dailyLimit
): array {
    $entry = loadRateLimitEntry($sitemapUrl);
    $today = date('Y-m-d');

    if ($entry['date'] !== $today) {
        $entry['date'] = $today;
        $entry['processedToday'] = 0;
    }

    if (!is_int($entry['index'])) {
        $entry['index'] = 0;
    }

    if (!is_int($entry['processedToday'])) {
        $entry['processedToday'] = 0;
    }

    $totalUrls = count($urls);

    if ($entry['index'] >= $totalUrls) {
        saveRateLimitEntry($sitemapUrl, $entry);
        return [
            'urls' => [],
            'message' => 'All URLs processed. Nothing left to crawl.',
            'total' => $totalUrls,
            'processed' => $entry['index'],
        ];
    }

    $remainingToday = $dailyLimit - $entry['processedToday'];

    if ($remainingToday <= 0) {
        saveRateLimitEntry($sitemapUrl, $entry);
        return [
            'urls' => [],
            'message' => 'Daily page limit reached. Continue tomorrow.',
            'total' => $totalUrls,
            'processed' => $entry['index'],
        ];
    }

    $pendingCount = $totalUrls - $entry['index'];
    $allowedCount = min($remainingToday, $pendingCount);
    $batch = array_slice($urls, $entry['index'], $allowedCount);
    $processedCount = count($batch);
    $entry['index'] = $entry['index'] + $processedCount;
    $entry['processedToday'] = $entry['processedToday'] + $processedCount;
    saveRateLimitEntry($sitemapUrl, $entry);
    $message = 'Checking ' . $processedCount . ' URLs (' .
        $entry['index'] . '/' . $totalUrls . ')';

    return [
        'urls' => $batch,
        'message' => $message,
        'total' => $totalUrls,
        'processed' => $entry['index'],
    ];
}

function clearCacheDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.') {
            continue;
        }

        if ($item === '..') {
            continue;
        }

        $path = $directory . '/' . $item;
        deleteCachePath($path);
    }
}

function deleteCachePath(string $path): void
{
    if (is_dir($path)) {
        clearCacheDirectory($path);
        rmdir($path);
        return;
    }

    if (is_file($path)) {
        unlink($path);
    }
}

function clearCacheStorage(): void
{
    clearCacheDirectory(LINK_CACHE_DIR);
    clearCacheDirectory(RATE_LIMIT_DIR);
}
