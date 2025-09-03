<?php
$hostname = "localhost"; 
$username = "root";
$password = "";
$dbname = "ictmu";

$conn = new mysqli($hostname, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['message' => 'Database connection failed: ' . $conn->connect_error]));
}
?>