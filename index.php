<?php
require_once('includes/ringcentral-php-functions.inc');

// show_errors();

require('includes/vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/includes')->load();

$client_id = $_ENV['RC_APP_CLIENT_ID'];
$client_secret = $_ENV['RC_APP_CLIENT_SECRET'];
$jwt_key = $_ENV['RC_JWT_KEY'];

$server = 'https://platform.ringcentral.com';

try {
	// Initialize the RingCentral SDK
	$rcsdk = new RingCentral\SDK\SDK($client_id, $client_secret, $server);
	$platform = $rcsdk->platform();

	// Authenticate using JWT
	$platform->login(['jwt' => $jwt_key]);

	$startDate = date('Y-m-d\TH:i:s\Z', strtotime('-2 weeks'));
	$endDate = date('Y-m-d\TH:i:s\Z', strtotime('now'));

	$queryParams = array(
		'dateFrom' => $startDate,
		'dateTo' => $endDate,
		'messageType' => 'VoiceMail',
	);

	// Fetch voicemail messages
	$response = $platform->get('/restapi/v1.0/account/~/extension/~/message-store', $queryParams);

	$messages = $response->json()->records;

//	echo_spaces("Raw message list", $messages);

	if (!empty($messages)) {
		foreach ($messages as $message) {
			if (!empty($message->attachments)) {
				echo_spaces("========== Voice Mail Information =====================","", 1, false);
				echo_spaces("Voicemail from ", $message->from->name);
				echo_spaces("Voicemail # ", $message->from->phoneNumber, 1);
				foreach ($message->attachments as $attachment) {
					if ($attachment->contentType == 'audio/mpeg') {
						$recordingUri = $attachment->uri;
						// Fetch the recording
						$recordingResponse = $platform->get($recordingUri);
						$recordingContent = $recordingResponse->raw();
						// Save the recording to a local file
						$fileName = 'voicemail_' . $message->id . '.mp3';
						file_put_contents($fileName, $recordingContent);
						// Display a playable link
						echo "<audio controls>
                            <source src=\"$fileName\" type=\"audio/mpeg\">                            
                          </audio><br/>";
						echo_spaces("", "", 0, false);
					}
					if ($attachment->contentType == 'text/plain') {
						$recordingUri = $attachment->uri;
						$recordingResponse = $platform->get($recordingUri);
						$recordingContent = $recordingResponse->raw();
						echo $recordingContent . "<br/>" ;
					}
				}
			}
		}
	} else {
		echo "No voicemails found.";
	}
} catch (Exception $e) {
	echo 'Error: ' . $e->getMessage();
}