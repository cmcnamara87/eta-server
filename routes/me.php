<?php

// Restricted to logged in current user
// $app->group('/me', $authenticate($app), function () use ($app) {
$app->group('/me', function () use ($app) {
	$app->get('/hello', function() use ($app) {
		echo '{"test_thing": "go now"}';
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