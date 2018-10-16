<?php
$supress_master = 1;

if (!$user->isAuthenticated() || !$user->isAdmin($_SESSION['USERDATA']['id'])) {
  header("HTTP/1.1 404 Page not found");
  die("404 Page not found");
}

define('TGAPI_URL', 'https://api.telegram.org/bot' . $config['push']['telegram']['api_key']);

curl_setopt_array($ch = curl_init(), array(
    CURLOPT_URL => TGAPI_URL . "/deleteWebhook",
    CURLOPT_RETURNTRANSFER => true,
));
echo curl_exec($ch);
curl_close($ch);
