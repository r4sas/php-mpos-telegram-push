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

$prepareResponse = function ($message) use ($chatID, $api, $bitcoin, $block, $setting, $statistics, $transaction, $user, $worker, $mysqli){
	// Get userID in MPOS by chatID
	$stmt = $mysqli->prepare("SELECT account_id as user_id FROM user_settings WHERE value LIKE '%".$chatID."%' LIMIT 1");
	if($stmt && $stmt->execute() && $result = $stmt->get_result())
		$user_id = $result->fetch_object()->user_id;
	if ( ! $interval = $setting->getValue('statistics_ajax_data_interval')) $interval = 300;

	$response = '';

        switch($message){
                case "/start":
                        $response = "Hi there! I'm ".($setting->getValue('website_title')?:"PHP-MPOS")." notifications bot.".PHP_EOL."Type /help to see all commands!";
                        break;
                case "/help":
                        $response = "/api - Get API state of pool frontend".PHP_EOL."/getid - Show your id, required for pool notifications".PHP_EOL."/help - Print this text".PHP_EOL.PHP_EOL.
				"*Commands for registered users:*".PHP_EOL."/hashrate - Show your hashrate".PHP_EOL."/status - Show information about network and pool".PHP_EOL."/workers - Show information about your workers";
                        break;
		case "/api":
			$response = "API state: ".($api->isActive()?"enabled":"disabled");
			break;
                case "/getid":
                        $response = "Your ID is ".$chatID.PHP_EOL."Use that ID on pool notifications settings page.";
                        break;
		// That is example of command, which will send information to user about his workers.
		case "/hashrate":
			if($user_id){
				$username = $user->getUsername($user_id);
				$stats = $statistics->getUserMiningStats($username, $user_id, $interval);
				$response = sprintf("Your workers hashrate is %.4f KH/s", $stats['hashrate']);
			}
			break;
		case "/status":
			if($user_id){
				$poolHashrate = $statistics->getCurrentHashrate();
				$poolWorkers = $worker->getCountAllActiveWorkers();
				$poolLastBlock = $block->getLast();

				$now = new DateTime( "now" );
				if (!empty($poolLastBlock)) {
					$poolTimeSinceLast = ($now->getTimestamp() - $poolLastBlock['time']);
				} else {
					$poolTimeSinceLast = 0;
				}

				if ($bitcoin->can_connect() === true){
					$netDiff = $bitcoin->getdifficulty();
					$netBlock = $bitcoin->getblockcount();
					$netHashrate = $bitcoin->getnetworkhashps();
				} else {
					$netDiff = 0;
					$netBlock = unknown;
					$netHashrate = 0;
				}

				$response = sprintf("*Network*\nBlock: %s\nDiff: %.4f\nHashrate: %.4f MH/s\n\n*Pool*\nHashrate: %.4f MH/s\nWorkers: %d\nLast found block: %d\nTime since block: %d min",
						$netBlock, $netDiff, $netHashrate/1000000, $poolHashrate/1000, $poolWorkers, $poolLastBlock['height'], $poolTimeSinceLast/60);
			}
			break;
		case "/workers":
			if($user_id){
				$workers = $worker->getWorkers($user_id, $interval);
				foreach ($workers as $worker)
					$response .= sprintf("*Username: %s*\nShares: %.4f\nHashrate: %.4f\nDifficulty: %.4f\n\n", $worker['username'], $worker['shares'], $worker['hashrate'], $worker['difficulty']);
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
