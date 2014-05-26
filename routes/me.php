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
		$contacts = R::find( 'user', ' id != :user_id ', array(':user_id' => $_SESSION['userId']));

		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));

		foreach($contacts as &$contact) {
			$contactLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $contact->id));
			if($contactLocation->id != 0) {
				$distance = haversineGreatCircleDistance($contactLocation->latitude, $contactLocation->longitude, $meLocation->latitude, $meLocation->longitude);	
				if($distance < 200) {
					$contact->nearby = true;
					// $contact['distance'] = $distance;
				}
			}
			

			unset($contact['ownLocation']);
			unset($contact['password']);

			// if($user->id != $_SESSION['userId']) {
			// 	$contact = $user->export();
			// 	unset($contact['ownLocation']);
			// 	unset($contact['password']);
			// 	$contacts[] = $contact;

			// 	// Calculate if they are in the same space
			// 	
		}
		$contactsArray = R::exportAll($contacts);
		foreach($contactsArray as &$contact) {
			unset($contact['ownLocation']);
			unset($contact['password']);
		}
		echo json_encode($contactsArray,  JSON_NUMERIC_CHECK);
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
		$contactLocations = R::find('location', ' user_id = :user_id ORDER BY created DESC LIMIT 2 ', array(':user_id' => $contactId));

		if(count($contactLocations) == 0) {
			// we dont know where they are
			// kill it
			die();
		}
		$contactLocations = array_values($contactLocations);
		$contactLocation = $contactLocations[0];


		$me = R::load('user', $_SESSION['userId']);
		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));


		// Get the contacts movement
		// check if stationary
		if($contactLocation->created < time() - (60 * 10) || count($contactLocations) == 1) {
			// the user has been stationary for 10 mins
			// mark them as stationary
			$contactMovement = 'stationary';
		} else {
			// they are moving, are they moving towards us
			$newLocation = $contactLocations[0];
			$oldLocation = $contactLocations[1];
			$newLocationDistance = haversineGreatCircleDistance($newLocation->latitude, $newLocation->longitude, $meLocation->latitude, $meLocation->longitude);
			$oldLocationDistance = haversineGreatCircleDistance($oldLocation->latitude, $oldLocation->longitude, $meLocation->latitude, $meLocation->longitude);

			if($newLocationDistance < $oldLocationDistance) {
				// moving closer
				$contactMovement = 'towards';
			} else {
				$contactMovement = 'away';
			}
		}


		// gold coast
		// $contactLocation = new stdClass();
		// $contactLocation->latitude = -28.0167;
		// $contactLocation->longitude = 153.4000;

		// queen st mall
		// $meLocation = new stdClass();
		// $meLocation->latitude = -27.4673045983608;
		// $meLocation->longitude = 153.0282677023206;
	
		$departureTime = time() + 5;
		$url = "http://maps.googleapis.com/maps/api/distancematrix/json?origins={$contactLocation->latitude},{$contactLocation->longitude}&destinations={$meLocation->latitude},{$meLocation->longitude}&mode=driving&sensor=false&departure_time=$departureTime";
		$distanceMatrix = json_decode(file_get_contents($url));

		$timeSeconds = $distanceMatrix->rows[0]->elements[0]->duration->value;
		
		$eta = new stdClass();
		// @todo: add in actual location
		$eta->suburb = "St Lucia";
		$eta->time = $timeSeconds;
		$eta->lastSeenAt = $contactLocation->created;
		$eta->serverTime = time();
		$eta->movement = $contactMovement;

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


/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function haversineGreatCircleDistance(
  $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
{
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return $angle * $earthRadius;
}