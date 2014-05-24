<?php
date_default_timezone_set("Australia/Brisbane");

// Restricted to logged in current user
$app->group('/me', $authenticate($app), function () use ($app) {
// $app->group('/me', function () use ($app) {
	$app->get('/hello', function() use ($app) {
		print_r($_SESSION);
		die();
		// echo '{"test_thing": "go now TEST"}';
	});

	$app->get('/profile', function() {
		$user = R::load('user', $_SESSION['userId']);
		echo json_encode($user->export());
	});

	$app->post('/device', function() use ($app) {
		$deviceData = json_decode($app->request->getBody());
		$user = R::load('user', $_SESSION['userId']);
		$user->deviceId = $deviceData->id;
		R::store($user);
		echo json_encode($deviceData);
		// echo json_encode($user->export());
	});

	$app->get('/contacts', function() use ($app) {
		$users = R::findAll('user');
		$contacts = array();
		foreach($users as &$user) {
			if($user->id != $_SESSION['userId']) {
				$contact = $user->export();
				unset($contact['ownLocation']);
				$contacts[] = $contact;
			}
		}
		echo json_encode($contacts);
	});

	$app->get('/contacts/:contactId', function($contactId) use ($app) {
		$user = R::load('user', $contactId);
		echo json_encode($user->export());
	});

	$app->post('/locations', function() use ($app) {
		// Get the post data
		$locationData = json_decode($app->request->getBody());

	    //Create location
	    $location = R::dispense('location');

	    $location->import($locationData);
	    $user = R::load('user', $_SESSION['userId']);
		$location->user = $user;
		$location->created = time();
	    R::store($location);

	    echo json_encode($location->export());
	});

	$app->get('/contacts/:contactId/eta', function($contactId) use ($app) {

		// Get the users location
		$contact = R::load('user', $contactId);
		$contactLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $contactId));
		// gold coast
		$contactLocation = new stdClass();
		$contactLocation->latitude = -28.0167;
		$contactLocation->longitude = 153.4000;

		$me = R::load('user', $_SESSION['userId']);
		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));
		// queen st mall
		$meLocation = new stdClass();
		$meLocation->latitude = -27.4673045983608;
		$meLocation->longitude = 153.0282677023206;
	
		$url = "http://maps.googleapis.com/maps/api/distancematrix/json?origins={$contactLocation->latitude},{$contactLocation->longitude}&destinations={$meLocation->latitude},{$meLocation->longitude}&mode=driving&sensor=false";
		$distanceMatrix = json_decode(file_get_contents($url));

		$timeSeconds = $distanceMatrix->rows[0]->elements[0]->duration->value;
		
		$eta = new stdClass();
		// @todo: add in actual location
		$eta->suburb = "St Lucia";
		$eta->time = $timeSeconds;

		// Send push notification if we know everyones device id
		// and its not an update request
		if($contact->deviceId && $me->deviceId && !$app->request()->get('update')) {
			pwCall('createMessage', 
				array(
			    	'application' => PW_APPLICATION,
			    	'auth' => PW_AUTH,
			    	'notifications' => array(
				        array(
				                'send_date' => 'now',
				                'content' => $me->name . ' checked your ETA.',
				                'devices' => array(
	              					 $contact->deviceId
	            				),
				        )
				    )
			    )
			);
		} 

		echo json_encode($eta);

	});

	

	$app->get('/users', function() {
		$users = R::findAll('user');
		echo json_encode(R::exportAll($users));
	});

	$app->post('/locations', function() {
		// {latitude: 1, longitude: 1}
		$location = R::dispense('location');
		
	});
});