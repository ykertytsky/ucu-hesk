<?php
define('IN_SCRIPT', 1);
define('HESK_PATH', './');

require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/customer_accounts.inc.php');

hesk_load_database_functions();
hesk_dbConnect();
hesk_session_start('CUSTOMER');

$action = hesk_REQUEST('action');

$client_id = isset($hesk_settings['google_client_id']) ? $hesk_settings['google_client_id'] : '';
$client_secret = isset($hesk_settings['google_client_secret']) ? $hesk_settings['google_client_secret'] : '';
$allowed_domain = isset($hesk_settings['google_allowed_domain']) ? $hesk_settings['google_allowed_domain'] : 'ucu.edu.ua';
$redirect_uri = $hesk_settings['hesk_url'] . '/google_auth.php?action=callback';

if ($action === 'login') {
    // Redirect to Google
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'hd' => $allowed_domain
    ]);
    header('Location: ' . $auth_url);
    exit();
} elseif ($action === 'callback') {
    $code = hesk_REQUEST('code');
    if (!$code) {
        hesk_process_messages('Authorization code not provided.', 'login.php');
    }

    // Exchange code for token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirect_uri
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        hesk_process_messages('cUrl error: ' . $error, 'login.php');
    }

    $token_data = json_decode($response, true);
    if (isset($token_data['error'])) {
        hesk_process_messages('Google API Error: ' . hesk_htmlspecialchars($token_data['error_description'] ?? $token_data['error']), 'login.php');
    }

    $access_token = $token_data['access_token'];

    // Get user info
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $userinfo_response = curl_exec($ch);
    curl_close($ch);

    $userinfo = json_decode($userinfo_response, true);
    if (!$userinfo || !isset($userinfo['email'])) {
        hesk_process_messages('Failed to retrieve user information from Google.', 'login.php');
    }

    // Validate corporate domain
    $hd = isset($userinfo['hd']) ? $userinfo['hd'] : '';
    if (!empty($allowed_domain)) {
        if (strtolower($hd) !== strtolower($allowed_domain) && !str_ends_with(strtolower($userinfo['email']), '@' . strtolower($allowed_domain))) {
             hesk_process_messages('Будь ласка, увійдіть використовуючи корпоративний обліковий запис (' . $allowed_domain . ').', 'login.php');
        }
    }

    $email = $userinfo['email'];
    $name = isset($userinfo['name']) ? $userinfo['name'] : current(explode('@', $email));

    // Get or create customer auto-linking
    $customer_id = hesk_get_or_create_customer($name, $email, true);
    
    // Fetch the customer record to pass to login function
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id` = " . intval($customer_id) . " LIMIT 1");
    if (hesk_dbNumRows($res) !== 1) {
        hesk_process_messages('Failed to log in the customer.', 'login.php');
    }
    $user = hesk_dbFetchAssoc($res);

    // If an existing customer is not verified yet, verify them since Google authenticated them
    if (intval($user['verified']) === 0 && !empty($user['verification_token'])) {
        hesk_verify_customer_account($user['email'], $user['verification_token']);
        $user['verified'] = 1;
    } elseif (intval($user['verified']) === 0) {
        // If they had 0 but no token, just set them to verified since they authenticated with OAuth
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` SET `verified` = 1 WHERE `id` = " . intval($user['id']));
        $user['verified'] = 1;
    }

    // Login successful
    unset($_SESSION['login_email']);
    hesk_process_successful_customer_login($user);
    exit();
}

// Redirect by default
header('Location: ' . $hesk_settings['hesk_url'] . '/index.php');
exit();
