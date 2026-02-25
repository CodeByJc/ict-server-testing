<?php
$hostname = "localhost"; 
$username = "jc";
$password = "Jc@25302022";
$dbname = "ictmu";
// $dbname = "ictmu_testing";

$conn = new mysqli($hostname, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['message' => 'Database connection failed: ' . $conn->connect_error]));
}
?>