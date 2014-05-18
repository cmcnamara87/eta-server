<?php

$app->get('/hello', function() use ($app) {
	echo '{"test_thing": "go now"}';
});

$app->get('/setup', function() {
	R::nuke();

	$user = R::dispense('user');
	$user->email = 'cmcnamara87@gmail.com';
	$user->password = md5('test');
	R::store($user);

	$user = R::dispense('user');
	$user->email = 'khoa.tran.nano@gmail.com';
	$user->password = md5('test');
	R::store($user);

});