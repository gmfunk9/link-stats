<?php

declare(strict_types=1);

require_once __DIR__ . '/http.php';

function fetchSitemapUrls(string $sitemapUrl): array
{
    $contentResult = fetchSimpleContent($sitemapUrl);

    if (isset($contentResult['error'])) {
        error_log('Failed to fetch sitemap: ' . $contentResult['error']);
        return [];
    }

    $body = $contentResult['body'] ?? '';

    if ($body === '') {
        error_log('Empty sitemap content from ' . $sitemapUrl);
        return [];
    }

    $xml = loadXml($body);

    if (!$xml) {
        error_log('Invalid XML returned from ' . $sitemapUrl);
        return [];
    }

    $rootName = $xml->getName();

    if ($rootName === 'sitemapindex') {
        return fetchUrlsFromIndex($xml);
    }

    if ($rootName === 'urlset') {
        return parseUrlset($xml);
    }

    error_log('Unsupported sitemap root ' . $rootName . ' returned from ' . $sitemapUrl);
    return [];
}

function loadXml(string $content): ?SimpleXMLElement
{
    libxml_use_internal_errors(true);

    try {
        $xml = new SimpleXMLElement($content);
        libxml_clear_errors();
        return $xml;
    } catch (Exception $exception) {
        error_log('Failed to parse XML: ' . $exception->getMessage());
        return null;
    }
}

function fetchUrlsFromIndex(SimpleXMLElement $xml): array
{
    $sitemapUrls = [];

    foreach ($xml->sitemap as $sitemapElement) {
        $sitemapUrls[] = (string) $sitemapElement->loc;
    }

    if (count($sitemapUrls) === 0) {
        return [];
    }

    $multiHandle = curl_multi_init();
    $curlHandles = [];

    foreach ($sitemapUrls as $sitemapUrl) {
        $curlHandle = curl_init($sitemapUrl);
        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_ENCODING => '',
        ]);
        curl_multi_add_handle($multiHandle, $curlHandle);
        $curlHandles[] = $curlHandle;
    }

    $running = 0;

    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running);

    $allUrls = [];

    foreach ($curlHandles as $curlHandle) {
        $content = curl_multi_getcontent($curlHandle);

        if ($content !== false) {
            $xmlChild = loadXml($content);

            if ($xmlChild) {
                $childUrls = parseUrlset($xmlChild);
                $allUrls = array_merge($allUrls, $childUrls);
            }
        }

        curl_multi_remove_handle($multiHandle, $curlHandle);
        curl_close($curlHandle);
    }

    curl_multi_close($multiHandle);

    return $allUrls;
}

function parseUrlset(SimpleXMLElement $xml): array
{
    $urls = [];

    foreach ($xml->url as $urlElement) {
        $urls[] = (string) $urlElement->loc;
    }

    return $urls;
}
