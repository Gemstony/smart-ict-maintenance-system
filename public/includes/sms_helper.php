<?php
// includes/sms_helper.php - SMS sending functions for Beem API

/**
 * Clean phone number to ensure it has 255 prefix
 * @param string $phone Raw phone number
 * @return string Cleaned phone number with 255 prefix
 */
function cleanPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Remove leading zeros
    $phone = ltrim($phone, '0');
    
    // Add 255 prefix if not present
    if (substr($phone, 0, 3) !== '255') {
        $phone = '255' . $phone;
    }
    
    return $phone;
}

/**
 * Send SMS using Beem API
 * @param string $phone Recipient phone number (with 255 prefix)
 * @param string $message SMS message (max 160 chars)
 * @return array Response data
 */
function sendSMS($phone, $message) {
    // Beem API credentials
    $api_key = "386bdc07eae64a5";
    $secret_key = "NWJmNmZkYTdhODRkYmFhNDY1YjQ4Mzg2NzBiNjEzNzYzMDU0OGE4MWUzOWM5Yjc2OTI5ZDAwNDZiYmQ1ZDY4NA==";
    $sender_id = "TZONE";
    
    // Clean phone number
    $phone = cleanPhoneNumber($phone);
    
    // Validate phone (should be 12 digits starting with 255)
    if (strlen($phone) !== 12 || substr($phone, 0, 3) !== '255') {
        return ['success' => false, 'message' => 'Invalid phone number'];
    }
    
    // Limit message to 160 characters
    if (strlen($message) > 160) {
        $message = substr($message, 0, 160); 
    }
    
    // Prepare data for Beem API
    $postData = [
        'source_addr' => $sender_id,
        'encoding' => 0,
        'message' => $message,
        'recipients' => [
            [
                'recipient_id' => 1,
                'dest_addr' => $phone
            ]
        ]
    ];
    
    // Send to Beem API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://apisms.beem.africa/v1/send',
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode("$api_key:$secret_key"),
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['successful']) && $result['successful']) {
            return ['success' => true, 'message' => 'SMS sent successfully'];
        } else {
            return ['success' => false, 'message' => $result['message'] ?? 'Unknown error'];
        }
    } else {
        return ['success' => false, 'message' => 'HTTP ' . $http_code . ($curl_error ? ' - ' . $curl_error : '')];
    }
}

/**
 * Send SMS to all administrators
 * @param string $message SMS message
 * @return array Results
 */
function sendSMSToAdmins($message) {
    $db = getDB();
    
    // Get all admin phone numbers
    $stmt = $db->prepare("SELECT phone FROM users WHERE role = 'System Administrator' AND status = 'active' AND is_approved = 1");
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    $results = [];
    foreach ($admins as $admin) {
        if (!empty($admin['phone'])) {
            $result = sendSMS($admin['phone'], $message);
            $results[] = $result;
        }
    }
    
    return $results;
}

/**
 * Check SMS balance from Beem API
 * @return array Balance data
 */
function checkSMSBalance() {
    $api_key = "386bdc07eae64a5";
    $secret_key = "NWJmNmZkYTdhODRkYmFhNDY1YjQ4Mzg2NzBiNjEzNzYzMDU0OGE4MWUzOWM5Yjc2OTI5ZDAwNDZiYmQ1ZDY4NA==";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://apisms.beem.africa/public/v1/vendors/balance',
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode("$api_key:$secret_key"),
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10
    ]);
    
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        return ['success' => true, 'balance' => $data['data']['credit_balance'] ?? 0];
    } else {
        return ['success' => false, 'balance' => 0];
    }
}
?>