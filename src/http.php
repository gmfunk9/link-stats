<?php

declare(strict_types=1);

function fetchUrlWithHeaders(string $url): array
{
    $startTime = microtime(true);
    $curlHandle = curl_init();

    if (!$curlHandle) {
        return ['error' => 'Unable to initialize curl'];
    }

    curl_setopt_array($curlHandle, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 33,
        CURLOPT_USERAGENT => 'FunkBot/1.0',
        CURLOPT_HEADER => true,
    ]);

    $response = curl_exec($curlHandle);
    $curlError = curl_error($curlHandle);
    $curlErrorCode = curl_errno($curlHandle);
    $info = curl_getinfo($curlHandle);

    if ($response === false) {
        curl_close($curlHandle);

        if ($curlErrorCode === CURLE_OPERATION_TIMEDOUT) {
            return ['error' => 'Timeout: URL request exceeded 33 seconds'];
        }

        return ['error' => 'Curl error: ' . $curlError];
    }

    $headerSize = $info['header_size'] ?? 0;
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $statusCode = $info['http_code'] ?? 0;
    $finalUrl = $info['url'] ?? $url;

    curl_close($curlHandle);

    $result = [
        'status' => $statusCode,
        'loadTime' => microtime(true) - $startTime,
        'finalUrl' => $finalUrl,
        'headers' => $headers,
    ];

    if ($statusCode >= 300) {
        if ($statusCode < 400) {
            $redirectUrl = parseRedirectLocation($headers);

            if ($redirectUrl) {
                $result['finalUrl'] = $redirectUrl;
                return $result;
            }
        }
    }

    if ($statusCode >= 200) {
        if ($statusCode < 300) {
            $result['body'] = $body;
        }
    }

    return $result;
}

function parseRedirectLocation(string $headers): ?string
{
    $matches = [];
    $pattern = '/location: (.*)\r\n/i';
    preg_match($pattern, $headers, $matches);

    if (count($matches) < 2) {
        return null;
    }

    $redirectUrl = trim($matches[1]);

    if ($redirectUrl === '') {
        return null;
    }

    return $redirectUrl;
}

function fetchSimpleContent(string $url, int $timeoutSeconds = 10): array
{
    $curlHandle = curl_init();

    if (!$curlHandle) {
        return ['error' => 'Unable to initialize curl'];
    }

    curl_setopt_array($curlHandle, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_ENCODING => '',
    ]);

    $body = curl_exec($curlHandle);
    $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curlHandle);

    if ($body === false) {
        curl_close($curlHandle);
        return ['error' => 'Curl error: ' . $curlError];
    }

    curl_close($curlHandle);

    return [
        'statusCode' => $statusCode,
        'body' => $body,
    ];
}
