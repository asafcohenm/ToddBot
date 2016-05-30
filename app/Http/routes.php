<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::any('/', function() {
    return view('welcome');
});

Route::any('/flush', function() {
	Cache::flush();
});

Route::any('/auth', function() {
	$request 	= Request::instance();
	$agent 		= $request->server('HTTP_USER_AGENT');
	if (strpos(strtolower($agent), 'facebook') !== false) {
		return 'OK';
	}

	$client_id 	= env('TODOIST_CLIENT_ID');
	$scope 		= 'data:read_write';

	$state 		= str_random(16);
	$request->session()->put('state', $state);

	$chatId 	= Request::get('chatid');
	$request->session()->put('chatid', $chatId);

	$fbId 		= Cache::get($chatId);
	error_log('Bounce ' . $chatId . '<->' . $fbId);

	return
	'<html><body><script>window.location="' .
	sprintf('https://todoist.com/oauth/authorize?client_id=%s&scope=%s&state=%s', $client_id, $scope, $state) .
	'";</script></body></html>';
});

Route::any('/auth_redirect', function() {
	$request 		= Request::instance();

	$code 			= Request::get('code');
	$state 			= Request::get('state');

	$client_id 		= env('TODOIST_CLIENT_ID');
	$client_secret 	= env('TODOIST_CLIENT_SECRET');

	// todo: check state param

	$client = new GuzzleHttp\Client();
	$response = $client->post('https://todoist.com/oauth/access_token', [
		'form_params' => [
			'client_id' 		=> $client_id,
			'client_secret'		=> $client_secret,
			'code'				=> $code
		]
	]);

	if ($response->getStatusCode() == 200) {

			$body 		= json_decode($response->getBody());
			$token 		= $body->access_token;

			$user 		= \App\Library\Toddbot::getUser($token);
			$username 	= $user->full_name;

			$chatId 	= $request->session()->get('chatid');
			$fbId 		= Cache::get($chatId);
			error_log('Bounce 2 ' . $chatId . '<->' . $fbId);

			if (strlen($fbId) < 2) return;

			$userData = (object) [
				'name'					=> $username,
				'itemsProcessed'		=> 0,
				'fbId' 					=> $fbId,
				'todoistToken'			=> $token,
				'lastInteraction'		=> time(),
				'itemsSnoozed'			=> []
			];

			Cache::forever('fb_' . $fbId, json_encode($userData));

			$toddBot = new \App\Library\Toddbot();

			$toddBot->hello($fbId);
			$toddBot->say(sprintf('Alright %s, nice to meet you!', $username));
			$toddBot->ask('Want to review a todo-item?', [
				[
					'type' 				=> 'postback',
					'title' 			=> 'Yes, let\'s go!',
					'payload'			=> 'ITEM_REVIEW'
				],
				[
					'type' 				=> 'postback',
					'title' 			=> 'Naw, thanks.',
					'payload'			=> 'SNOOZE'
				]
			]);


			return '<html><body>OK, all set! You can close this window. <script>window.close();</script></body></html>';

	} else {
		echo 'Whoops, todoist returned...';
		var_dump($response->getBody());
	}

	return 'Failure!';
});

Route::any('/hook', function () {
	$challenge = Request::get('hub_challenge');
	$verify_token = Request::get('hub_verify_token');

	if ($verify_token === 'my_verify_token') {
		echo $challenge;
	    die();
	}

	$request = Request::instance();
	$body = json_decode($request->getContent());
	error_log(json_encode($body));

	$toddBot = new \App\Library\Toddbot();

	if ($body && !empty($body)) {
		foreach ($body->entry as $entry) {
			$messagingEvents = $entry->messaging;
			foreach ($messagingEvents as $event) {
				if (
					(isset($event->message) && isset($event->message->text)) ||
					(isset($event->postback) && isset($event->postback->payload))
					) {

					if (isset($event->sender) && $event->sender->id) {

						$senderId = $event->sender->id;

						$toddBot->hello($senderId);
						$toddBot->process($event);
					}

				}
			}
		}
	}

	return 'OK, bye';
});
