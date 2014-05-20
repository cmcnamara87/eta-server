<?php

$app->get('/hello2', function() use ($app) {
    echo '{"test_thing": "go now"}';
});

$authenticate = function ($app) {
    return function () use ($app) {
    	// Check there is a user id set
        if (!isset($_SESSION['userId'])) {
        	$app->halt(401, 'Login Required.');
        }
    };
};

/**
 * Logs in
 */
$app->post("/login", function () use ($app) {

	$loginData = json_decode($app->request->getBody());

    $user  = R::findOne( 'user', ' email = :email ', array(':email' => $loginData->email));

    if($user && $user->password == hash('md5', $loginData->password)) {
    	$_SESSION['userId'] = $user->id;
    } else {
    	$app->halt('400', 'Incorrect email or password.');
    }
    echo json_encode($user, JSON_NUMERIC_CHECK);
});


/**
 * Creates a new user
 */
$app->post('/register', function() use ($app) {

	$sampleUserData = array(
		'firstName' 	=> 'Craig',
		'lastName'		=> 'McNamara',
		'email'			=> 'cmcnamara87@gmail.com',
	);

	$userData = json_decode($app->request->getBody());
	// $userData = $sampleUserData;

	$user = R::dispense('user');
	$user->import($userData);
	$user->processed = mktime(0,0,0);
	R::store($user);

	echo json_encode($user->export(), JSON_NUMERIC_CHECK);
});