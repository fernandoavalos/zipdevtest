<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;

use Src\System\DatabaseConnector;

$dotenv = new DotEnv(__DIR__);
$dotenv->load();

$databaseConnector = new DatabaseConnector();

$dbConnection = (new DatabaseConnector())->getConnection();
