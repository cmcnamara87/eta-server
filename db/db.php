<?php
// Set up database connection
// if('127.0.0.1' == $_SERVER["REMOTE_ADDR"]) {	
	
// } else {
	// $passwords = json_decode(file_get_contents("db/db.pass"));
	// R::setup('mysql:host=localhost;
	//         dbname=' . $passwords->database->dbname, $passwords->database->username, $passwords->database->password);
// }

R::setup('mysql:host=localhost;
	        dbname=eta','root','root');