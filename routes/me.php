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

	$app->get('/contacts', function() use ($app) {
		$users = R::findAll('user');
		$usersExported = R::exportAll($users);
		foreach($usersExported as &$user) {
			unset($user['ownLocation']);
		}
		echo json_encode($usersExported);
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
	    // @todo: put in session user
	    $user = R::load('user', 1);
		$location->user = $user;
		$location->created = time();
	    R::store($location);

	    echo json_encode($location->export());
	});

	$app->get('/contacts/:contactId/eta', function($contactId) use ($app) {

		// Get the users location
		// @todo: change user id
		$contactLocation = new stdClass();
		$contactLocation->latitude = -27.49610500195277;
		$contactLocation->longitude = 153.00207000109367;

		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => 1));
		// queen st mall
		// $meLocation = new stdClass();
		// $meLocation->latitude = -27.4673045983608;
		// $meLocation->longitude = 153.0282677023206;
	
		$url = "http://maps.googleapis.com/maps/api/distancematrix/json?origins={$contactLocation->latitude},{$contactLocation->longitude}&destinations={$meLocation->latitude},{$meLocation->longitude}&mode=driving&sensor=false";
		$distanceMatrix = json_decode(file_get_contents($url));

		$timeSeconds = $distanceMatrix->rows[0]->elements[0]->duration->value;
		
		$eta = new stdClass();
		// @todo: add in actual location
		$eta->suburb = "St Lucia";
		$eta->time = $timeSeconds;

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