<?php

$host     = getenv('MYSQLHOST');
$user     = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');
$database = getenv('MYSQLDATABASE');
$port     = getenv('MYSQLPORT');

$koneksi = mysqli_connect(
    $host,
    $user,
    $password,
    $database,
    $port
);

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>