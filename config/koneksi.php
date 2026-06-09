<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host     = getenv('DB_HOST'); 
$user     = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname   = getenv('DB_NAME');
$port     = getenv('DB_PORT');

$conn = new mysqli($host, $user, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$koneksi = $conn; // Tambahkan baris ini
?>