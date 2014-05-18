<?php

// Restricted to logged in current user
// $app->group('/me', $authenticate($app), function () use ($app) {
$app->group('/me', function () use ($app) {
	$app->get('/hello', function() use ($app) {
		echo '{"test_thing": "go now"}';
	});

	$app->get('/contacts', function() use ($app) {
		$users = R::findAll('user');
		echo json_encode(R::exportAll($users));
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

	    // {latitude: -27, longitude: 153}; 
	    $location->import($locationData);
	    // @todo: put in session user
	    $user = R::load('user', 3);
		$location->user = $user;
	    R::store($location);
	});

	$app->get('/contacts/:contactId/eta', function($contactId) use ($app) {
		// $user = R::load('user', $contactId);
		// echo json_encode($user->export());
		$eta = new stdClass();
		$eta->suburb = "Brisbane CBD";
		$eta->time = 20;

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