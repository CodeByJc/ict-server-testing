<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$hostname = $_ENV['DB_HOST'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$dbname   = $_ENV['DB_NAME'];
$port     = $_ENV['DB_PORT'];

$conn = new mysqli(
    $hostname,
    $username,
    $password,
    $dbname,
    $port
);

mysqli_set_charset($conn, "utf8mb4");
$conn->query("SET collation_connection = utf8mb4_general_ci");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>