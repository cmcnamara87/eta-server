<?php

$app->get('/hello', function() use ($app) {
	echo '{"test_thing": "go now"}';
});

$app->get('/setup', function() {
	R::nuke();

	$user = R::dispense('user');
	$user->email = 'test@example.com';
	$user->firstName = 'Test';
	$user->lastName = 'Test';
	$user->password = md5('test');
	R::store($user);

	$user = R::dispense('user');
	$user->email = 'cmcnamara87@gmail.com';
	$user->firstName = 'Craig';
	$user->lastName = 'McNamara';
	$user->password = md5('test');
	R::store($user);

	$user = R::dispense('user');
	$user->email = 'khoa.tran.nano@gmail.com';
	$user->firstName = 'Khoa';
	$user->lastName = 'Tran';
	$user->password = md5('test');
	R::store($user);

	$user = R::dispense('user');
	$user->email = 'nicksmells@gmail.com';
	$user->firstName = 'Nick';
	$user->lastName = 'Georgiou';
	$user->password = md5('test');
	R::store($user);

	$user = R::dispense('user');
	$user->email = 'ankith.konda@gmail.com';
	$user->firstName = 'Ankith';
	$user->lastName = 'Konda';
	$user->password = md5('test');
	R::store($user);

});