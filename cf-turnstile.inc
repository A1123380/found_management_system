<?php
function verifyTurnstile($captcha, $secretKey, $ip) {
    if (empty($captcha)) {
        return ['success' => false, 'error' => '請完成 CAPTCHA 驗證。'];
    }

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $secretKey,
        'response' => $captcha,
        'remoteip' => $ip
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'content' => http_build_query($data),
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        return ['success' => false, 'error' => '無法連繫 CAPTCHA 服務，請稍後再試。'];
    }

    $response = json_decode($result, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'CAPTCHA 回應格式錯誤。'];
    }

    return [
        'success' => isset($response['success']) && $response['success'] === true,
        'error' => isset($response['error-codes']) ? implode(', ', $response['error-codes']) : '驗證失敗'
    ];
}

$secretKey = defined('TURNSTILE_SECRET_KEY') ? TURNSTILE_SECRET_KEY : 'your turnstile key';
$captcha = isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '';
$ip = $_SERVER['REMOTE_ADDR'];

$turnstileResult = verifyTurnstile($captcha, $secretKey, $ip);
?>