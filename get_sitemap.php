<?php

header('Content-Type: application/json');

const RATE_LIMIT = 10;
const RATE_LIMIT_STORE = __DIR__ . '/rate_limit.json';

function setup_curl($url) {
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_ENCODING => ''  // This enables curl to handle any encoding like gzip automatically
    ];
    curl_setopt_array($ch, $options);
    return $ch;
}

function fetch_content($url) {
    $ch = setup_curl($url);
    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        error_log("CURL error on $url: " . curl_error($ch));
    }
    curl_close($ch);
    if ($http_code !== 200) {
        error_log("HTTP request failed for $url with status $http_code");
        return false;
    }
    return $content;
}

function parse_xml($xml_content, $tag) {
    try {
        if (!$xml_content || trim($xml_content) === '') throw new Exception("Empty or invalid XML content");
        libxml_use_internal_errors(true);
        $xml = new SimpleXMLElement($xml_content);
        $urls = [];
        foreach ($xml->$tag as $element) {
            $urls[] = (string)$element->loc;
        }
        libxml_clear_errors();
        return $urls;
    } catch (Exception $e) {
        error_log("Failed to parse XML: " . $e->getMessage());
        return [];
    }
}

function get_sitemap_urls($sitemap_index_url) {
    $content = fetch_content($sitemap_index_url);
    if (!$content) {
        return [];
    }

    libxml_use_internal_errors(true);
    try {
        $xml = new SimpleXMLElement($content);
    } catch (Exception $e) {
        error_log("Invalid XML returned from $sitemap_index_url: " . $e->getMessage());
        return [];
    }

    $root_name = $xml->getName();

    if ($root_name === 'sitemapindex') {
        $sitemap_urls = parse_xml($content, 'sitemap');
        if (empty($sitemap_urls)) {
            return [];
        }

        $mh = curl_multi_init();
        $curl_array = [];

        foreach ($sitemap_urls as $url) {
            $ch = setup_curl($url);
            curl_multi_add_handle($mh, $ch);
            $curl_array[] = $ch;
        }

        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running);

        $all_page_urls = [];
        foreach ($curl_array as $ch) {
            $content = curl_multi_getcontent($ch);
            if ($content) {
                $all_page_urls = array_merge($all_page_urls, parse_xml($content, 'url'));
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $all_page_urls;
    }

    if ($root_name === 'urlset') {
        return parse_xml($content, 'url');
    }

    error_log("Unsupported sitemap root '$root_name' returned from $sitemap_index_url");
    return [];
}

function get_client_id() {
    $client = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$client) {
        return 'unknown';
    }
    return $client;
}

function load_rate_limit_store() {
    if (!file_exists(RATE_LIMIT_STORE)) {
        return [];
    }
    $raw = file_get_contents(RATE_LIMIT_STORE);
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    return $decoded;
}

function save_rate_limit_store($store) {
    $json = json_encode($store, JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log('Failed to encode rate limit store');
        return;
    }
    file_put_contents(RATE_LIMIT_STORE, $json, LOCK_EX);
}

function init_rate_limit_entry() {
    return [
        'index' => 0,
        'processed_today' => 0,
        'date' => date('Y-m-d')
    ];
}

function apply_rate_limit($urls, $sitemap_index_url) {
    $total = count($urls);
    if ($total === 0) {
        return [
            'urls' => [],
            'message' => 'No URLs found in sitemap.'
        ];
    }
    $store = load_rate_limit_store();
    $client = get_client_id();
    if (!isset($store[$sitemap_index_url])) {
        $store[$sitemap_index_url] = [];
    }
    if (!isset($store[$sitemap_index_url][$client])) {
        $store[$sitemap_index_url][$client] = init_rate_limit_entry();
    }
    $entry = $store[$sitemap_index_url][$client];
    $today = date('Y-m-d');
    if ($entry['date'] !== $today) {
        $entry['date'] = $today;
        $entry['processed_today'] = 0;
    }
    if ($entry['index'] >= $total) {
        $store[$sitemap_index_url][$client] = $entry;
        save_rate_limit_store($store);
        return [
            'urls' => [],
            'message' => 'All URLs processed. Nothing left to crawl.'
        ];
    }
    $remaining_today = RATE_LIMIT - $entry['processed_today'];
    if ($remaining_today <= 0) {
        $store[$sitemap_index_url][$client] = $entry;
        save_rate_limit_store($store);
        return [
            'urls' => [],
            'message' => 'Daily page limit reached. Continue tomorrow.'
        ];
    }
    $pending = $total - $entry['index'];
    $allowed = min($remaining_today, $pending);
    $batch = array_slice($urls, $entry['index'], $allowed);
    $entry['index'] = $entry['index'] + $allowed;
    $entry['processed_today'] = $entry['processed_today'] + $allowed;
    $store[$sitemap_index_url][$client] = $entry;
    save_rate_limit_store($store);
    $message = 'Checking ' . $allowed . ' URLs (' . $entry['index'] . '/' . $total . ')';
    return [
        'urls' => $batch,
        'message' => $message
    ];
}

function handle_request() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["error" => "Unsupported request method; use POST."]);
        return;
    }
    $sitemap_index_url = $_POST['url'] ?? '';
    if (!$sitemap_index_url) {
        echo json_encode(["error" => "Missing field url; add to body."]);
        return;
    }
    if (!filter_var($sitemap_index_url, FILTER_VALIDATE_URL)) {
        echo json_encode(["error" => "Invalid sitemap URL provided."]);
        return;
    }
    $urls = get_sitemap_urls($sitemap_index_url);
    if (!$urls) {
        echo json_encode(["error" => "no_urls_found"]);
        return;
    }
    $limited = apply_rate_limit($urls, $sitemap_index_url);
    echo json_encode($limited);
}

handle_request();
?>
