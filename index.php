<?php

require_once('includes/ringcentral-php-functions.inc');
show_errors();

require(__DIR__ . '/includes/vendor/autoload.php');

use RingCentral\SDK\SDK;

$dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/includes')->load();

$client_id = $_ENV['RC_APP_CLIENT_ID'];
$client_secret = $_ENV['RC_APP_CLIENT_SECRET'];

$jwt_key = $_ENV['RC_JWT_KEY'];


$server = 'https://platform.ringcentral.com';

$rcsdk = new SDK($client_id, $client_secret, $server);
$platform = $rcsdk->platform();

// Authenticate using JWT
$platform->login(['jwt' => $jwt_key]);

try {
	// Initialize the RingCentral SDK
	$rcsdk = new SDK($client_id, $client_secret, $server);
	$platform = $rcsdk->platform();

	// Authenticate using JWT
	$platform->login(['jwt' => $jwt_key]);

	// Fetch voicemail messages
	$response = $platform->get('/restapi/v1.0/account/~/extension/~/message-store', [
		'messageType' => 'VoiceMail',
	]);

	$messages = $response->json()->records;

//	echo_spaces("Raw message list", $messages);

	if (!empty($messages)) {
		foreach ($messages as $message) {
			if (!empty($message->attachments)) {
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
                            Your browser does not support the audio element.
                          </audio>";
					}
					if ($attachment->contentType == 'text/plain') {
						$recordingUri = $attachment->uri;
						$recordingResponse = $platform->get($recordingUri);
						$recordingContent = $recordingResponse->raw();
						$fileName = 'transcription_' . $message->id . '.txt';
						file_put_contents($fileName, $recordingContent);
						echo_spaces("Transcript", nl2br(htmlspecialchars(file_get_contents($fileName))));
					}
				}
				echo_spaces("===============================","", 1, false);
			}
		}
	} else {
		echo "No voicemails found.";
	}
} catch (Exception $e) {
	echo 'Error: ' . $e->getMessage();
}