#<?php
#function is_license_valid() {
#    // Kiểm tra license từ session hoặc tệp lưu trữ
 #   return isset($_SESSION['license_key']) && $_SESSION['license_key'] === 'ABC-123-XYZ';
#}
#function validate_license($key) {
 #   // Có thể thêm logic kiểm tra nâng cao nếu cần
  #  return $key === 'ABC-123-XYZ'; // thay bằng license thực tế
#}
#?>

<?php

define('LICENSE_FILE', '/usr/local/www/license.json'); // Đường dẫn lưu license JSON

/**
 * Kiểm tra license có hợp lệ và chưa hết hạn không
 */
function is_license_valid() {
    if (!file_exists(LICENSE_FILE)) {
        return false;
    }

    $license_data = json_decode(file_get_contents(LICENSE_FILE), true);

    if (empty($license_data['key']) || empty($license_data['expires_at'])) {
        return false;
    }

    // Kiểm tra key và thời gian hết hạn
    $valid_key = 'ABC-123-XYZ';  // Thay bằng key thực tế
    $now = time();

    return $license_data['key'] === $valid_key && $now < strtotime($license_data['expires_at']);
}

/**
 * Xác thực license và lưu vào file nếu hợp lệ
 */
function validate_license($key) {
    $valid_key = 'ABC-123-XYZ';  // Key hợp lệ cần kiểm tra

    if ($key === $valid_key) {
        $data = [
            'key' => $key,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')) // Key có hiệu lực 30 ngày
        ];
        file_put_contents(LICENSE_FILE, json_encode($data, JSON_PRETTY_PRINT));
        return true;
    }

    return false;
}

?>
