<?php
require 'bootstrap.php';

$statement = "INSERT INTO `intervals`
    (`id`, `date_start`, `date_end`, `price`) 
    VALUES 
    (1, '2019-04-01', '2019-04-01', 15),
    (2, '2019-04-02', '2019-04-10', 45)";

try {
    $createTable = $dbConnection->exec($statement);
    echo "Success!\n  " . (int) $createTable . "\n";
} catch (\PDOException $e) {
    exit($e->getMessage());
}