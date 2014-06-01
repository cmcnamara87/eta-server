<?php
date_default_timezone_set("Australia/Brisbane");
define('PING_NEARBY_DISTANCE_METERS', 500);
define('PING_TIMEOUT_MINUTES', 30);
define('PING_PUSH_TIMEOUT_MINUTES', 1);
define('LOCATION_TIMEOUT_MINUTES', 10);

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
		echo json_encode($deviceData, JSON_NUMERIC_CHECK);
		// echo json_encode($user->export());
	});

	$app->get('/contacts', function() use ($app) {
		$dbContacts = R::find( 'user', ' id != :user_id ', array(':user_id' => $_SESSION['userId']));

		$contacts = array();
		foreach($dbContacts as $dbContact) {
			$contact = new stdClass();
			foreach($dbContact as $key => $value) {
				if($key != 'password') {
					$contact->{$key} = $value;	
				}
			}
			$contact->image = "http://www.gravatar.com/avatar/" . md5(strtolower(trim($contact->email)));
			$contacts[] = $contact;
		}

		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));

		if($meLocation && $meLocation->id != 0) {
			foreach($contacts as $contact) {
				// See if we have any pings from them that are recent
				$ping = R::findOne('ping', ' from_contact_id = :from_contact_id AND to_contact_id = :to_contact_id AND created > :time ORDER BY created DESC LIMIT 1 ', 
					array(
						':from_contact_id' => $contact->id,
						':to_contact_id' => $_SESSION['userId'],
						'time' => time() - 60 * PING_TIMEOUT_MINUTES
					)
				);
				if($ping && $ping->id != 0) {
					$location = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $contact->id));
					if($location && $location->id !== 0) {
						$distance = haversineGreatCircleDistance($ping->latitude, $ping->longitude, $location->latitude, $location->longitude);	

						// Calculate the distance
						if($distance < PING_NEARBY_DISTANCE_METERS) {
							$exportedPing = $ping->export();
							$exportedPing['contactId'] = $contact->id;
							$contact->ping = $exportedPing;
						}	
					}
				}
			}	
		}
		echo json_encode($contacts,  JSON_NUMERIC_CHECK);
	});

	$app->get('/contacts/etas', function() use ($app) {
		$contacts = R::find( 'user', ' id != :user_id ', array(':user_id' => $_SESSION['userId']));

		$etas = calculateEtas($contacts);
		// $etas = array();
		// foreach($contacts as $contact) {
		// 	$eta = calculateEta($contact->id);
		// 	$etas[] = $eta;
		// }
		echo json_encode($etas, JSON_NUMERIC_CHECK);
	});
	$app->get('/contacts/pings', function() {
		$contacts = R::find( 'user', ' id != :user_id ', array(':user_id' => $_SESSION['userId']));
		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));

		$pings = array();
		
		foreach($contacts as $contact) {
			// echo '<br/>looking at contact ' . $contact->id;
			$ping = R::findOne('ping', ' from_contact_id = :from_contact_id AND to_contact_id = :to_contact_id AND created > :time ORDER BY created DESC LIMIT 1 ', 
				array(
					':from_contact_id' => $contact->id,
					':to_contact_id' => $_SESSION['userId'],
					'time' => time() - 60 * PING_TIMEOUT_MINUTES
				)
			);
			if($ping && $ping->id != 0) {
				// echo '<br/>found a ping ' . $ping->id;
				$location = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $contact->id));
				if($location && $location->id !== 0) {
					$distance = haversineGreatCircleDistance($ping->latitude, $ping->longitude, $location->latitude, $location->longitude);	
					// echo '<br/>distance is ' . $distance;
					// Calculate the distance
					if($distance < PING_NEARBY_DISTANCE_METERS) {
						$exportedPing = $ping->export();
						$exportedPing['contactId'] = $contact->id;
						$pings[] = $exportedPing;
					}	
				}
			}
		}
		
		echo json_encode($pings, JSON_NUMERIC_CHECK);			
	});

	$app->get('/contacts/:contactId/eta', function($contactId) use ($app) {
		$contact = R::load('user', $contactId);

		$etas = calculateEtas(array($contact));

		// Send push notification if we know everyones device id
		// and its not an update request
		// if($contact->deviceId) {
		// 	pwCall('createMessage', 
		// 		array(
		// 	    	'application' => PW_APPLICATION,
		// 	    	'auth' => PW_AUTH,
		// 	    	'notifications' => array(
		// 		        array(
		// 		                'send_date' => 'now',
		// 		                'content' => $me->name . ' checked your ETA.',
		// 		                'devices' => array(
	 //              					 $contact->deviceId
	 //            				),
		// 		        )
		// 		    )
		// 	    )
		// 	);
		// } 
		echo json_encode($etas[0], JSON_NUMERIC_CHECK);
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

	    echo json_encode($location->export(), JSON_NUMERIC_CHECK);
	});


	$app->get('/contacts/:contactId/ping', function($contactId) {
		$contact = R::load('contact', $contactId);

		$ping = R::findOne('ping', ' from_contact_id = :from_contact_id AND to_contact_id = :to_contact_id AND created > :time ORDER BY created DESC LIMIT 1 ', 
			array(
				':from_contact_id' => $contact->id,
				':to_contact_id' => $_SESSION['userId'],
				'time' => time() - 60 * PING_TIMEOUT_MINUTES
			)
		);
		if($ping && $ping->id != 0) {
			$distance = haversineGreatCircleDistance($ping->latitude, $ping->longitude, $meLocation->latitude, $meLocation->longitude);	
			// Calculate the distance
			if($distance < PING_NEARBY_DISTANCE_METERS) {
				echo json_encode($ping->export(), JSON_NUMERIC_CHECK);
				return;
			}
		}
		$app->halt(404);
	});
	$app->post('/contacts/:contactId/ping', function($contactId) use ($app) {
		$contact = R::load('user', $contactId);

		$me = R::load('user', $_SESSION['userId']);
		$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));
		$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$meLocation->latitude},{$meLocation->longitude}&sensor=false";
		$addressLookup = json_decode(file_get_contents($url));
		$address = $addressLookup->results[0]->formatted_address;

		if($contact->deviceId) {
			// Check the existing pins
			$ping = R::findOne('ping', ' from_contact_id = :from_contact_id AND to_contact_id = :to_contact_id AND created > :time ORDER BY created DESC LIMIT 1 ', 
				array(
					':from_contact_id' => $_SESSION['userId'],
					':to_contact_id' => $contact->id,
					'time' => time() - 60 * PING_PUSH_TIMEOUT_MINUTES
				)
			);
			if(!$ping || $ping->id == 0) {
				pwCall('createMessage', 
					array(
				    	'application' => PW_APPLICATION,
				    	'auth' => PW_AUTH,
				    	'notifications' => array(
					        array(
					                'send_date' => 'now',
					                'content' => $me->name . ' pinged you from ' . $address,
					                'devices' => array(
		              					 $contact->deviceId
		            				),
					        )
					    )
				    )
				);
			}
		}

		$ping = R::dispense('ping');
		$ping->fromContactId = $me->id;
		$ping->toContactId = $contactId;
		$ping->address = $address;
		$ping->latitude = $meLocation->latitude;
		$ping->longitude = $meLocation->longitude;
		$ping->created = time();
		R::store($ping);
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
 * Works out the status of a contact
 * 
 * @param  [type] $contact [description]
 * @return [type]          
 */
function calculateStatus($contact) {
	// online, offline, movement
}

function getPing($contact) {

}

function calculateEtas($contacts) {
	$origins = array();
	$contactsWithLocations = array();
	$etas = array();
	
	foreach($contacts as $contact) {
		$location = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $contact->id));
		
		// All the contacts with a location < 1 day old
		if($location && $location->id !== 0 && time() - (24 * 60 * 60) < $location->created) {
			$contact->location = $location;
			$contactsWithLocations[] = $contact;

			$origins[] = "{$location->latitude},{$location->longitude}";
		} else {
			// Contact is online
			$eta = new stdClass();
			// @todo: add in actual location
			$eta->status = 'offline';
			$eta->lastSeen = $location->created;
			$eta->contactId = $contact->id;
			// Make the ETA object
			$etas[] = $eta;
		}
	}

	// Work out the etas
	$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));
	$departureTime = time() + 60;
	$url = "http://maps.googleapis.com/maps/api/distancematrix/json?origins=" . implode("|", $origins) . "&destinations={$meLocation->latitude},{$meLocation->longitude}&mode=driving&sensor=false&departure_time=$departureTime";
	$distanceMatrix = json_decode(file_get_contents($url));

	// Go throguh each row, and pull out the time
	
	foreach($distanceMatrix->rows as $index => $row) {
		$duration = $row->elements[0]->duration->value;
		$contact = $contactsWithLocations[$index];
		

		$eta = new stdClass();
		// @todo: add in actual location
		$eta->status = 'online';
		$eta->duration = $duration;
		$eta->lastSeen = $contact->location->created;
		$eta->now = time();
		$eta->movement = calculateMovement($contact);
		$eta->contactId = $contact->id;
		// Make the ETA object
		$etas[] = $eta;
	}

	return $etas;
}

function calculateMovement($contact) {
	// Get the users location
	$contactId = $contact->id;
	$contactLocations = R::find('location', ' user_id = :user_id ORDER BY created DESC LIMIT 2 ', array(':user_id' => $contactId));

	if(count($contactLocations) == 0) {
		return false;		
	}

	$contactLocations = array_values($contactLocations);
	$contactLocation = $contactLocations[0];

	$meLocation = R::findOne('location', ' user_id = :user_id ORDER BY created DESC LIMIT 1 ', array(':user_id' => $_SESSION['userId']));

	// Get the contacts movement
	// check if stationary
	if($contactLocation->created < time() - (60 * LOCATION_TIMEOUT_MINUTES) || count($contactLocations) == 1) {
		// the user has been stationary for 10 mins
		// mark them as stationary
		$contactMovement = 'stationary';
	} else {
		// they are moving, are they moving towards us
		$newLocation = $contactLocations[0];
		$oldLocation = $contactLocations[1];
		$newLocationDistance = haversineGreatCircleDistance($newLocation->latitude, $newLocation->longitude, $meLocation->latitude, $meLocation->longitude);
		$oldLocationDistance = haversineGreatCircleDistance($oldLocation->latitude, $oldLocation->longitude, $meLocation->latitude, $meLocation->longitude);

		// if(abs($newLocationDistance - $oldLocationDistance) < 30) {
		// 	// they havent moved very far, all it stationary
		// 	$contactMovement = 'stationary';
		// } else {
			if($newLocationDistance < $oldLocationDistance) {
				// moving closer
				$contactMovement = 'towards';
			} else {
				$contactMovement = 'away';
			}	
		// }
		
	}
	return $contactMovement;
}

// function calculateEta($contactId) {
	


// 	// gold coast
// 	// $contactLocation = new stdClass();
// 	// $contactLocation->latitude = -28.0167;
// 	// $contactLocation->longitude = 153.4000;

// 	// queen st mall
// 	// $meLocation = new stdClass();
// 	// $meLocation->latitude = -27.4673045983608;
// 	// $meLocation->longitude = 153.0282677023206;

// 	$departureTime = time() + 5;
// 	$url = "http://maps.googleapis.com/maps/api/distancematrix/json?origins={$contactLocation->latitude},{$contactLocation->longitude}&destinations={$meLocation->latitude},{$meLocation->longitude}&mode=driving&sensor=false&departure_time=$departureTime";
// 	$distanceMatrix = json_decode(file_get_contents($url));

// 	$timeSeconds = $distanceMatrix->rows[0]->elements[0]->duration->value;
	
// 	$eta = new stdClass();
// 	// @todo: add in actual location
// 	$eta->suburb = "St Lucia";
// 	$eta->time = $timeSeconds;
// 	$eta->lastSeenAt = $contactLocation->created;
// 	$eta->serverTime = time();
// 	$eta->movement = $contactMovement;
// 	$eta->contactId = $contactId;

// 	return $eta;
// }
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