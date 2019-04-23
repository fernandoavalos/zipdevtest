<?php 
require "../bootstrap.php";
use Src\Controller\IntervalController;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

// all endpoints start with /interval
// everything else results in a 404 Not Found
if ($uri[1] !== 'interval') {

    header("HTTP/1.1 404 Not Found");
    exit();
}

// the interval id is optional and must be a number:
$intervalId = null;
if (isset($uri[2])) {
	if (is_int($uri[2])) {
		$intervalId = (int) $uri[2];
	} else if ($uri[2] == 'update') {
		$_SERVER["REQUEST_METHOD"] = "PUT";
		$intervalId = is_int((int) $uri[3]) ? (int) $uri[3] : null;
	} else if ($uri[2] == 'delete') {
		$_SERVER["REQUEST_METHOD"] = "DELETE";
		$intervalId = is_int((int) $uri[3]) ? (int) $uri[3] : null;
	} else if ($uri[2] == 'purge') {
		$_SERVER["REQUEST_METHOD"] = "OPTIONS";
	}
    
}

$requestMethod = $_SERVER["REQUEST_METHOD"];

// pass the request method and interval ID to the IntervalController and process the HTTP request:
$controller = new IntervalController($dbConnection, $requestMethod, $intervalId);
$controller->processRequest();