<?php

$app->get('/hello', function() use ($app) {
	echo '{"test_thing": "go now 2222"}';
});

/**
 * Logs in
 */
$app->post("/login", function () use ($app) {

	$loginData = json_decode($app->request->getBody());

    $user  = R::findOne( 'user', ' email = :email ', array(':email' => $loginData->email));

    // print_r($loginData);
    // echo hash('md5', $loginData->password);
    if($user && $user->password == hash('md5', $loginData->password)) {
    	$_SESSION['userId'] = $user->id;
    } else {
    	$app->halt('400', 'Incorrect email or password.');
    }
    echo json_encode($user, JSON_NUMERIC_CHECK);
});

$app->post('/logout', function() use ($app) {
	unset($_SESSION['userId']);
});
$app->get('/logout', function() use ($app) {
	unset($_SESSION['userId']);
});

/**
 * Creates a new user
 */
$app->post('/register', function() use ($app) {

	$sampleUserData = array(
		'firstName' 	=> 'Craig',
		'lastName'		=> 'McNamara',
		'email'			=> 'cmcnamara87@gmail.com'
	);

	// $userData = json_decode($app->request->getBody());
	$userData = $sampleUserData;

	$user = R::dispense('user');
	$user->import($userData);
	$user->processed = mktime(0,0,0);
	R::store($user);

	echo json_encode($user->export(), JSON_NUMERIC_CHECK);
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