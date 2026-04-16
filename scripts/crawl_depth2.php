<?php

declare(strict_types=1);

// Lightweight depth-2 crawler for CI/debug inside container.
// Usage: php scripts/crawl_depth2.php http://127.0.0.1

$base = $argv[1] ?? 'http://127.0.0.1';
$visited = [];
$results = [];

function fetch(string $url): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'KomorebiCrawler/1.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    // curl_close is deprecated as of PHP 8.5; avoid calling it to prevent
    // deprecation warnings inside the container environment.

    return ['code' => $code, 'body' => $body, 'error' => $err, 'content_type' => $ctype];
}

function normalize(string $base, string $href): ?string
{
    if ($href === '' || str_starts_with($href, '#')) {
        return null;
    }
    if (preg_match('#^(mailto:|tel:|javascript:)#i', $href)) {
        return null;
    }
    if (preg_match('#^https?://#i', $href)) {
        return $href;
    }
    if (str_starts_with($href, '/')) {
        return rtrim($base, '/') . $href;
    }

    return rtrim($base, '/') . '/' . ltrim($href, './');
}

function extractLinks(string $html): array
{
    $links = [];
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (@$dom->loadHTML($html) === false) {
        return [];
    }
    foreach ($dom->getElementsByTagName('a') as $a) {
        $href = trim($a->getAttribute('href'));
        if ($href !== '') {
            $links[] = $href;
        }
    }

    return array_values(array_unique($links));
}

$toCrawl = [[$base, 0]];

while (!empty($toCrawl)) {
    [$url, $depth] = array_shift($toCrawl);
    if (isset($visited[$url])) {
        continue;
    }
    $visited[$url] = true;

    $r = fetch($url);
    $results[$url] = ['code' => $r['code'], 'content_type' => $r['content_type'], 'error' => $r['error']];

    if ($r['code'] >= 200 && $r['code'] < 300 && $depth < 2 && is_string($r['body'])) {
        $links = extractLinks($r['body']);
        foreach ($links as $href) {
            $abs = normalize($base, $href);
            if ($abs === null) {
                continue;
            }
            $u1 = parse_url($base, PHP_URL_HOST);
            $u2 = parse_url($abs, PHP_URL_HOST);
            if ($u2 !== null && $u2 !== $u1) {
                continue;
            }
            if (!isset($visited[$abs])) {
                $toCrawl[] = [$abs, $depth + 1];
            }
        }
    }
}

echo json_encode(['base' => $base, 'summary' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
