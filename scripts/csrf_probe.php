<?php

declare(strict_types=1);

// Simple probe to GET the forgot-password page (saving cookies), extract CSRF token,
// then POST the form with that token. Outputs responses and tails php-error.log.

$cookie = sys_get_temp_dir() . '/komorebi_csrf_cookies.txt';
@unlink($cookie);

function runCurl(string $url, array $opts = []): array
{
    global $cookie;
    $ch = curl_init($url);
    $default = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 10,
    ];

    foreach ($opts as $k => $v) {
        $default[$k] = $v;
    }

    curl_setopt_array($ch, $default);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);

    return [$info, $body, $err];
}

echo "Running CSRF probe\n";

// 1) GET page
[$info, $body, $err] = runCurl('http://localhost/auth/forgot-password');
echo "GET -> HTTP: " . ($info['http_code'] ?? 'N/A') . "\n";
if ($err) {
    echo "GET curl error: $err\n";
}
echo "--- page head ---\n";
echo substr((string)$body, 0, 800) . "\n";

// 2) Extract token
$token = '';
if (preg_match('/<meta name="csrf-token" content="([^"]+)"/s', (string)$body, $m)) {
    $token = $m[1];
}
echo "token: " . ($token === '' ? '<empty>' : substr($token, 0, 6) . '...') . " len=" . strlen($token) . "\n";

// 3) POST
[$info2, $body2, $err2] = runCurl('http://localhost/forgot-password', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['email' => 'test@example.com', 'csrf_token' => $token]),
]);

echo "POST -> HTTP: " . ($info2['http_code'] ?? 'N/A') . "\n";
if ($err2) {
    echo "POST curl error: $err2\n";
}
echo "--- post head/body ---\n";
echo substr((string)$body2, 0, 800) . "\n";

echo "--- tail php-error.log ---\n";
passthru('tail -n 200 storage/logs/php-error.log 2>/dev/null || true');

echo "Done.\n";
