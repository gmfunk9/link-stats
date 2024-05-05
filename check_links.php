<?php
ini_set('error_log', './errlog.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
function getCacheFileName($mainUrl) {
	$parsedUrl = parse_url($mainUrl);
	$domain = isset($parsedUrl['host']) ? preg_replace('/[^a-zA-Z0-9\-\.]+/', '', $parsedUrl['host']) : 'default';
	return "./cache_{$domain}.json";
}
function saveCache($cacheFileName) {
	global $linkCache;
	if (file_put_contents($cacheFileName, json_encode($linkCache, JSON_PRETTY_PRINT)) === false) {
	    error_log('Failed to write cache file: ' . $cacheFileName);
	}
}
function extractLinks($html, $baseUrl) {
    preg_match_all('/<a\s+[^>]*href="([^"]+)"[^>]*>/i', $html, $matches);
    return array_unique(array_filter(array_map(function ($link) use ($baseUrl) {
        $link = explode('#', $link)[0];
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            return $link;
        } elseif (preg_match('/^\//', $link)) {
            $parts = parse_url($baseUrl);
            return $parts['scheme'] . '://' . $parts['host'] . '/' . ltrim($link, '/');
        }
        return null;
    }, array_filter($matches[1], function ($link) {
        return !empty($link) && $link !== '0';
    }))));
}
function fetchUrlContent($url) {
	// sleep(1);
	$startTime = microtime(true);
	$curl = curl_init();
	curl_setopt_array($curl, [
	    CURLOPT_URL => $url,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_FOLLOWLOCATION => false,  // Do not automatically follow redirects
	    CURLOPT_MAXREDIRS => 10,
	    CURLOPT_TIMEOUT => 33,
	    CURLOPT_USERAGENT => 'FunkBot/1.0',
	    CURLOPT_HEADER => true
	]);
	$response = curl_exec($curl);
	$info = curl_getinfo($curl);
	$curlError = curl_error($curl);
	$curlErrorCode = curl_errno($curl);
	if (!$response) {
	    curl_close($curl);
	    error_log("CURLED: $url");
	    if ($curlErrorCode === CURLE_OPERATION_TIMEDOUT) {
	        return ['error' => "Timeout: URL request exceeded 33 seconds"];
	    }
	    return ['error' => "Curl error: $curlError"];
	}
	$headerSize = $info['header_size'];
	$headers = substr($response, 0, $headerSize);
	$body = substr($response, $headerSize);
	$status = $info['http_code'];
	$finalUrl = $info['url'];
	curl_close($curl);
	$result = [
	    'status' => $status,
	    'loadTime' => microtime(true) - $startTime,
	    'finalUrl' => $finalUrl,
	    'headers' => $headers
	];
	// Capture redirects specifically
	if ($status >= 300 && $status < 400) {
	    if (preg_match('/location: (.*)\r\n/', $headers, $matches)) {
	        $redirectUrl = trim($matches[1]);
	        $result['finalUrl'] = $redirectUrl;
	        return $result;  // Return the redirect info here
	    }
	}
	if ($status >= 200 && $status < 300) {
	    $result['body'] = $body;
	}
	return $result;
}
function parseHtmlForMetadata($html) {
    $result = [];
    preg_match('/<title>(.*)<\/title>/i', $html, $matches);
    $title = isset($matches[1]) ? trim($matches[1]) : null;
    $contentLength = strlen($html);
    if ($title) {
        $result['title'] = $title;
    }
    if ($contentLength) {
        $result['contentLength'] = $contentLength;
    }
    // Extract canonical URL if present
    if (preg_match('/<link\s+rel="canonical"\s+href="([^"]+)"/i', $html, $matches)) {
        $result['finalUrl'] = $matches[1];
    }
    return $result;
}
function getInterlinkResponse($url) {
    global $linkCache;
    $mainUrl = $_GET['url'];
    $cacheFileName = getCacheFileName($mainUrl);
    $linkCache = file_exists($cacheFileName) ? json_decode(file_get_contents($cacheFileName), true) : [];
    // Return cached response if available
    if (isset($linkCache[$url])) {
        return $linkCache[$url];
    }
    // Fetch URL content and handle errors
    $fetchResult = fetchUrlContent($url);
    if (isset($fetchResult['error'])) {
        $linkCache[$url] = $fetchResult;
        saveCache($cacheFileName);
        return $fetchResult;
    }
    // Construct response from fetched data
    $response = [
        'status' => $fetchResult['status'],
        'loadTime' => $fetchResult['loadTime'],
        'finalUrl' => $fetchResult['finalUrl']
    ];
    if (!empty($fetchResult['body'])) {
        $html = $fetchResult['body'];
        $response = array_merge($response, parseHtmlForMetadata($html));
    }
    // Cache and return the response
    $linkCache[$url] = $response;
    saveCache($cacheFileName);
    return $response;
}
function processInterlinks($html, $baseUrl) {
	global $linkCache;
	$interlinks = [];
	$links = extractLinks($html, $baseUrl);
	$mainUrl = $_GET['url'];
	$cacheFileName = getCacheFileName($mainUrl);
	foreach ($links as $link) {
	    $interlinks[$link] = getInterlinkResponse($link);  // Use link as the key
	}
	return ['interlinks' => $interlinks];
}
function sendJsonResponse($data, $mainUrl = null) {
	$output = ['page' => $mainUrl];
	foreach ($data['interlinks'] as $originalUrl => $interlink) {
	    $strUrl = $originalUrl;  // Prefix 'url_' to force string keys
	    if (isset($strUrl)) {
	        $output['interlinks'][$strUrl] = $interlink;
	    } else {
	        // Handle error cases or missing finalUrl differently
            $uuid = uniqid();
	        $output['interlinks']['error_' . $mainUrl . $uuid] = $interlink;
	        error_log("LINK ERROR $mainUrl - $uuid");
	    }
	}
	echo json_encode($output, JSON_UNESCAPED_SLASHES) ?: json_encode(['error' => 'Failed to generate JSON: ' . json_last_error_msg()]);
	exit;
}
if (empty($_GET['url']) || !filter_var($_GET['url'], FILTER_VALIDATE_URL)) {
	sendJsonResponse(['error' => 'Invalid or no URL provided']);
} else {
	$mainUrl = $_GET['url'];
	$cacheFileName = getCacheFileName($mainUrl);
}
$linkCache = file_exists($cacheFileName) ? json_decode(file_get_contents($cacheFileName), true) : [];
$fetchResult = fetchUrlContent($mainUrl);
if (isset($fetchResult['error'])) {
	sendJsonResponse(['error' => $fetchResult['error']]);
} else {
	$mainResponse = getInterlinkResponse($mainUrl);  
	$interlinks = processInterlinks($fetchResult['body'], $fetchResult['finalUrl']);
	sendJsonResponse(array_merge(['mainUrl' => $mainResponse], $interlinks), $mainUrl);
}
