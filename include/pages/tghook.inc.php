<?php
$supress_master = 1;

define('TGAPI_URL', 'https://api.telegram.org/bot' . $config['push']['telegram']['api_key']);

// https://gist.github.com/theMiddleBlue/6d5e9082e0c3c378bfb037795b2570b8
if(!preg_match('/^149\.154\.167\.(19[7-9]|20[0-9]|21[0-9]|22[0-9]|23[0-3])$/', $_SERVER['REMOTE_ADDR'])) {
    die('IP Address not allowed.');
}
if($_SERVER['REQUEST_METHOD'] != 'POST') {
    die('Request method not allowed.');
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);
$chatID = $update["message"]["chat"]["id"];
$message = $update["message"]["text"];

$prepareResponse = function ($message) use ($chatID, $api, $setting, $block, $worker, $mysqli){
        switch($message){
                case "/start":
                        $response = "Hi there! I'm ".($setting->getValue('website_title')?:"PHP-MPOS")." notifications bot.".PHP_EOL."Type /help to see all commands!";
                        break;
                case "/help":
                        $response = "/api - Get API state of pool frontend".PHP_EOL."/getid - Show your id, required for pool notifications".PHP_EOL."/workers - Show information about your workers";
                        break;
		case "/api":
			$response = "API state: ".($api->isActive()?"enabled":"disabled");
			break;
                case "/getid":
                        $response = "Your ID is ".$chatID.PHP_EOL."Use that ID on pool notifications settings page.";
                        break;
		// That is example of command, which will send information to user about his workers.
		case "/workers":
			// Get userID in MPOS by chatID
			$stmt = $mysqli->prepare("SELECT account_id as user_id FROM user_settings WHERE value LIKE '%".$chatID."%' LIMIT 1");
			if($stmt && $stmt->execute() && $result = $stmt->get_result())
			if($user_id = $result->fetch_object()){ // If user with chatID found
				if ( ! $interval = $setting->getValue('statistics_ajax_data_interval')) $interval = 300;
				$workers = $worker->getWorkers($user_id, $interval); // Get all workers and prepare message
				foreach ($workers as $worker)
					$response .= sprintf("*Username: %s*\nShares: %s\nHashrate: %s\nDifficulty: %s\n\n", $worker[username], $worker[shares], $worker[hashrate], $worker[difficulty]);
			} else { // Else write about requirement to provide chatID in notification settings
				$response = "We coudn't find you in our database.".PHP_EOL."Make sure that you set ID in notifications settings on pool.";
			}
			break;
                default:
                        $response = "mumble-mumble...";
                        break;
        }
        return $response;
};

function sendMessage($chatID, $reply){
        curl_setopt_array($ch = curl_init(), array(
             CURLOPT_URL => TGAPI_URL . "/sendMessage",
             CURLOPT_POST => true,
             CURLOPT_RETURNTRANSFER => true,
             CURLOPT_POSTFIELDS => http_build_query($data = array(
                 "chat_id" => $chatID,
                 "text" => $reply,
                 "parse_mode" => "Markdown",
             )),
        ));
        curl_exec($ch);
        curl_close($ch);
}

$reply = $prepareResponse($message);
sendMessage($chatID, $reply);

