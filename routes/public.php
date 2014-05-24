<?php

$app->get('/hello', function() use ($app) {
	echo '{"test_thing": "go now 2222"}';
});

$app->get('/setup', function() {
	R::nuke();

	// $user = R::dispense('user');
	// $user->email = 'test@example.com';
	// $user->name = 'Test';
	// $user->password = md5('test');
	// R::store($user);

	$user = R::dispense('user');
	$user->email = 'cmcnamara87@gmail.com';
	$user->name = 'Craig';
	$user->password = md5('test');
	R::store($user);

});