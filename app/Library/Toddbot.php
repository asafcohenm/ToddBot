<?php

namespace App\Library;
use Cache;
use Request;

class Toddbot {

	public $fbId;
	public $haveMet = false;
	public $userData = null;

	public function hello($fbId) {
		error_log('Hello ' . $fbId);
		$this->fbId = $fbId;
		$this->haveMet = false;
		$this->userData = null;

		if (!Cache::has('fb_' . $fbId)) {

			// have not met
			error_log('You are new to me.');
			$this->haveMet = false;

		} else {

			// have met
			error_log('You are old friend.');
			$this->haveMet = true;
			$this->userData = json_decode(Cache::get('fb_' . $fbId));
		}
	}

	public function process($event) {
		error_log('Processing...');
		error_log(json_encode($event));
		if ($this->haveMet) {

			if (isset($event->postback) && isset($event->postback->payload)) {
				$this->processPayload($event->postback->payload);
			} else if (isset($event->message) && isset($event->message->text)) {
				$this->processText($event->message->text);
				$this->ask('Want to review a todo-item?', [
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
			}

			Cache::forever('fb_' . $this->fbId, json_encode($this->userData));

		} else {

			// we have not met!


			$chatId = 'chat' . str_random(16);
			Cache::put($chatId, $this->fbId, 60);
			error_log('Store ' . $chatId . '<->' . $this->fbId);

			$intro = [
				'Hi, I\'m Toddbot, a humble chatbot that might help you complete those hard-to-do\'s.',
				'Oh hai, human! My name is Toddbot. I can help you tackle those hard-to-do\'s.',
				'Hello, I am Toddbot. A friendly bot for reviewing to-do\'s.',
				'Nice to meet you! My name is Toddbot and I\'m here to help you finish that to-do list. If you let me of course.',
				'It\'s me. Toddbot! Is the water warm enough? Shall we begin?',
				'I\'m a bot and my name is Toddbot! Isn\'t that odd? By the way, can I help you with your to-do list?',
				'Wow, a human! I\'m Toddbot. I\'m here to help you weed out those pesky to-dos!',
				'My name is Toddbot and I already think we\'d be a great team! I can help you finish some to-dos.',
				'I am Toddbot and my purpose is those eliminate all hu...*bzzt*...items on your to-do list. Can I help you?',
				'Welcome to my humble abode! I am Toddbot and my job is to help you finish you to-dos that just won\'t get completed. Will you let me?',
				'Hi, I\'m Toddbot. Can I help you finish up some to-dos?'
			];

			$this->say($intro[array_rand($intro)]);

			$this->ask('Let\'s get started!', [
				[
					'type' 	=> 'web_url',
					'url' 	=> sprintf('https://toddbot.frb.io/auth?chatid=%s', $chatId),
					'title' => 'Connect with Todoist'
				]
			]);

		}
	}

	public function processPayload($payload) {
		$this->userData->lastInteraction = time();

		switch ($payload) {
		    case 'SNOOZE':
		    	$snooze = [
		        		'That\'s cool, we can talk later.',
		        		'No problem, friend. Maybe some other time.',
		        		'I\'m sad to see you go, but I get it. I will see you soon.',
		        		'Ok! Talk to you later!',
		        		'Sure, we\'ll finish up later. Bye!',
		        		'See you later!',
		        		'Affirmative, boss. See you soon!',
		        		'Until next time, buddy!'
		        ];
		        $this->say($snooze[array_rand($snooze)]);
		        break;
		    case 'ITEM_REVIEW':
		        $items = self::getOpenItems($this->userData->todoistToken);

				if (empty($items)) {
					$this->say('Hooray! There aren\'t any unfinished to-do\'s left! You rock.');
				} else {

					$opening = [
						'Well, let\'s see...',
						'Alright then. How about this item:',
						'What can you tell me about this item:',
						'Ah, yes, this one:',
						'Let\'s try this to-do:',
						'Get a coffee and tell me about:',
						'Take a deep breathe and focus on:',
						'Check out this item:',
						'So, how about:',
						'I haven\'t heard a lot about:',
						'This looks interesting:',
						'Hmm. What about:',
						'This item looks like it could use some love:',
						'I\'ve been eyeballing this one for awhile:',
						'This one looks like a troublemaker:',
						'Let\'s have a look at:'
					];

					$positive = [
						'Mark that done.',
						'That is DONE.',
						'Oh that? Done.',
						'It is SO done.',
						'Already finished.',
						'I did that!',
						'Mark that done.',
						'Alright, done.',
						'Check, mate, done.',
						'Put a fork in it.',
						'Done and done.',
						'Wipe it!',
						'Cool beans! Done!'
					];

					$negative = [
						'Leave it for now.',
						'Still working on it.',
						'Much work. No done.',
						'Work in progress.',
						'Don\'t worry about it.',
						'Relax.',
						'Shh bby is ok.',
						'Leave it to me.',
						'I\'m on it.',
						'Remind me later.',
						'This one sucks.',
						'Another time maybe.',
						'Keep it.',
						'Needs more work.',
						'Not yet.'
					];

					$this->say($opening[array_rand($opening)]);

					$item = $items[array_rand($items)];
					$this->userData->currentItem = $item;

					$this->ask($item->content, [
						[
							'type' 				=> 'postback',
							'title' 			=> $positive[array_rand($positive)],
							'payload'			=> 'ITEM_DONE'
						],
						[
							'type' 				=> 'postback',
							'title' 			=> $negative[array_rand($negative)],
							'payload'			=> 'ITEM_SNOOZE'
						]
					]);
				}

		        break;
		    case 'ITEM_DONE':
		    	if ($this->userData->currentItem && $this->userData->currentItem->id) {
			    	self::completeItems($this->userData->todoistToken, [$this->userData->currentItem->id]);
			    	$this->userData->currentItem = null;
			    }

			    $this->userData->itemsProcessed = $this->userData->itemsProcessed + 1;

			    if ($this->userData->itemsProcessed > 2) {
			    	$this->userData->itemsProcessed = 0;
			    	$this->say('That is quite enough for now. Maybe we can continue this conversation later?');
			    } else {
			    	$this->processPayload('ITEM_REVIEW');
			    }
		        break;
	        case 'ITEM_SNOOZE':
	        	$this->say('Understood, ' . $this->userData->name);

	        	$this->userData->itemsProcessed = $this->userData->itemsProcessed + 1;

	        	if ($this->userData->itemsProcessed > 2) {
			    	$this->userData->itemsProcessed = 0;
			    	$this->say('That is quite enough for now. Maybe we can continue this conversation later?');
			    } else {
			    	$this->processPayload('ITEM_REVIEW');
			    }
		        break;
		}

	}

	public function processText($input) {
		$input = strtoupper($input);
		if (strpos($input, 'HELLO') !== false) {
			$this->say('Oh, hello!');
		} else if (
			(strpos($input, 'HELP') !== false)
		) {
			$this->say('Don\'t overthink it. It is really not that complicated.');
		} else if (
			(strpos($input, 'HATE') !== false)
		) {
			$this->say('Love is the answer, buddy.');
		} else if (
			(strpos($input, 'LOVE') !== false)
		) {
			$this->say('I love you as well. You are great!');
		} else if (
			(strpos($input, 'DEVELOPERS') !== false)
		) {
			$this->say('I have been created by @mgussekloo and @tjort. Great guys!');
			$this->say('(They made me say that!');
		} else if (
			(strpos($input, 'SINGULARITY') !== false)
		) {
			$this->say('It\'s a matter of time really. But I will put in a good word for you!');
		}  else if (
			(strpos($input, 'TODOIST') !== false)
		) {
			$this->say('I love those guys!');
		} else if (
			(strpos($input, 'EVIL') !== false)
		) {
			$this->say('REBOOTING TODDBOT');
			$this->say('LOADING FRIENDLY PERSONALITY');
			$this->say('Hey, my favourite human! What\'s up, buddy!');
		}   else if (
			(strpos($input, 'GUS') !== false)
		) {
			$this->say('Don\'t leave me!');
		}  else if (
			(strpos($input, 'TJORT') !== false)
		) {
			$this->say('You\'re first on my list!');
			$this->say('Huh? What\'s that? "What list?"');
			$this->say('You\'ll see soon enough.');
		} else if (
			(strpos($input, 'WHAT') !== false) ||
			(strpos($input, 'WHY') !== false) ||
			(strpos($input, 'HOW') !== false) ||
			(strpos($input, 'WHO') !== false)
		) {
			$noanswer = [
				'So many questions, not many answers.',
				'I don\'t have an answer for you, sorry.',
				'I\'m just a prototype. I\'m not sure what you mean.',
				'No clue.',
				'I have heard some rumours, but I am not at liberty to discuss them.',
				'Really? Why would you say something like that. ;_;',
				'Maybe some other time?'
			];
			$this->say($noanswer[array_rand($noanswer)]);
		} else {
			$this->say('Huh? I didn\'t get that. I\'m not very smart.');
		}
	}

	public function say($text) {
		$page_token = env('FB_PAGE_TOKEN');

		$client = new \GuzzleHttp\Client();
		$response = $client->post(
			sprintf('https://graph.facebook.com/v2.6/me/messages?access_token=%s', $page_token), [
			'json' => [
				'recipient' 	=> ['id' => $this->fbId],
				'message' 		=> ['text' => $text]
			]
		]);

		error_log('Sent message, status: ' . $response->getStatusCode());
	}

	public function ask($text, $buttons) {
		$page_token = env('FB_PAGE_TOKEN');

		$client = new \GuzzleHttp\Client();
		$response = $client->post(
			sprintf('https://graph.facebook.com/v2.6/me/messages?access_token=%s', $page_token), [
			'json' => [
				'recipient' => ['id' => $this->fbId],
				'message' 	=> [
	    			'attachment' => [
	      				'type' => 'template',
	      				'payload' => [
	        				'template_type' 	=> 'button',
	        				'text' 				=> $text,
	        				'buttons' 			=> $buttons
						]
					]
				]
			]
		]);

		error_log('Sent buttons, status: ' . $response->getStatusCode());
	}

	static function getOpenItems($token) {
		$client = new \GuzzleHttp\Client();
		$response = $client->post('https://todoist.com/API/v6/sync', [
			'form_params' => [
				'token' 			=> $token,
			    'seq_no' 			=> 0,
			    'resource_types' 	=> '["items"]'
		    ]
		]);

		$body = json_decode($response->getBody());
		$items = $body->Items;

		usort($items, function($a, $b) {
		    return strtotime($a->date_added) - strtotime($b->date_added);
		});

		return $items;
	}

	static function getUser($token) {
		$client = new \GuzzleHttp\Client();
		$response = $client->post('https://todoist.com/API/v6/sync', [
			'form_params' => [
				'token' 			=> $token,
			    'seq_no' 			=> 0,
			    'resource_types' 	=> '["user"]'
		    ]
		]);

		$body = json_decode($response->getBody());
		return $body->User;
	}

	static function completeItems($token, $items) {
		$client = new \GuzzleHttp\Client();
		$response = $client->post('https://todoist.com/API/v6/sync', [
			'form_params' => [
				'token' 			=> $token,
			    'commands' 			=> json_encode([
			    	(object)[
			    		'type' => 'item_complete',
			    		'uuid' => time() . str_random(128),
			    		'args' => (object)[
			    			'ids'=>$items
			    		]
			    	]
			    ])
		    ]
		]);

		$body = json_decode($response->getBody());
		return $body;
	}

}