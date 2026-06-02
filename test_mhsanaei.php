<?php
$base_url = 'https://tr.vipvirtualnet.eu:2053/l91KugYhhUMJyjqfsC';
$username = 'AdminWexort';
$password = 'AdminWexort123';

$csrf_token = '';
$curl_csrf = curl_init();
curl_setopt_array($curl_csrf, array(
    CURLOPT_URL => $base_url . '/csrf-token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT_MS => 10000,
    CURLOPT_HTTPHEADER => array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
    ),
    CURLOPT_COOKIEJAR => 'cookie.txt',
    CURLOPT_COOKIEFILE => 'cookie.txt',
));
$csrf_resp = curl_exec($curl_csrf);
$http_code = curl_getinfo($curl_csrf, CURLINFO_HTTP_CODE);
if ($http_code == 200) {
    $csrf_dec = json_decode($csrf_resp, true);
    if (isset($csrf_dec['success']) && $csrf_dec['success'] && isset($csrf_dec['obj'])) {
        $csrf_token = $csrf_dec['obj'];
    }
}
curl_close($curl_csrf);

echo "CSRF Token: $csrf_token\n";

$headers_json = array(
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
);
if ($csrf_token) {
    $headers_json[] = 'X-CSRF-Token: ' . $csrf_token;
}

$payload_json = json_encode(array(
    'username' => $username,
    'password' => $password,
    'twoFactorCode' => ''
));

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $base_url . '/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT_MS => 10000,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $payload_json,
    CURLOPT_HTTPHEADER => $headers_json,
    CURLOPT_COOKIEJAR => 'cookie.txt',
    CURLOPT_COOKIEFILE => 'cookie.txt',
));
$response = curl_exec($curl);
$http_code2 = curl_getinfo($curl, CURLINFO_HTTP_CODE);
echo "POST HTTP Code: $http_code2\n";
echo "Response: $response\n";
echo "Curl Error: " . curl_error($curl) . "\n";
