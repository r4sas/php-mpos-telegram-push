<?php
$supress_master = 1;

if (!$user->isAuthenticated() || !$user->isAdmin($_SESSION['USERDATA']['id'])) {
  header("HTTP/1.1 404 Page not found");
  die("404 Page not found");
}

define('TGAPI_URL', 'https://api.telegram.org/bot' . $config['push']['telegram']['api_key']);

$pushto = $_SERVER['SCRIPT_NAME'].'?page=tghook';
$hook_url = 'https://' . $_SERVER['HTTP_HOST'] . $pushto;

curl_setopt_array($ch = curl_init(), array(
    CURLOPT_URL => TGAPI_URL . "/setWebhook",
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => http_build_query($data = array(
        "url" => $hook_url,
    )),
));
echo curl_exec($ch);
curl_close($ch);

