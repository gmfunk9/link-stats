<?php

header('Content-Type: application/json');

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

    // Debug output
    // file_put_contents('debug_sitemap.xml', $content);

    $sitemap_urls = parse_xml($content, 'sitemap');
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

function handle_request() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($sitemap_index_url = $_POST['url'] ?? '') && filter_var($sitemap_index_url, FILTER_VALIDATE_URL)) {
        $urls = get_sitemap_urls($sitemap_index_url);
        echo json_encode($urls ? $urls : ["error" => "no_urls_found"]);
    } else {
        echo json_encode(["error" => "invalid_request"]);
    }
}

handle_request();
?>
