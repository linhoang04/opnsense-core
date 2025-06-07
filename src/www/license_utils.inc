<?php
// Lưu file JSON
define('LICENSE_FILE', '/usr/local/www/license.json'); 

// Check License
function is_license_valid() {
    if (!file_exists(LICENSE_FILE)) {
        return false;
    }

    $license_data = json_decode(file_get_contents(LICENSE_FILE), true);

    if (empty($license_data['key']) || empty($license_data['expires_at'])) {
        return false;
    }

    // Kiểm tra ngày hết hạn
    $now = time();
    $expires_at = strtotime($license_data['expires_at']);

    if ($now > $expires_at) {
        return false;
    }

    // Check định dạng 
    if (!is_string($license_data['key']) || strlen($license_data['key']) < 5) {
        return false;
    }

    return true;
}

// Xác thực License
function validate_license($key) {
    $machine_id = php_uname(); 
    $api_url = 'http://172.29.168.74:3001/check-license';

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode([
                'key' => $key,
                'machine_id' => $machine_id
            ]),
            'timeout' => 5
        ]
    ];

    $context  = stream_context_create($opts);
    $response = @file_get_contents($api_url, false, $context);

    if ($response === FALSE) {
        return false;
    }

    $result = json_decode($response, true);

    if (!empty($result['valid'])) {
        $data = [
            'key' => $key,
            'expires_at' => $result['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+1 year'))
        ];
        file_put_contents(LICENSE_FILE, json_encode($data, JSON_PRETTY_PRINT));
        return true;
    }

    return false;
}

?>
