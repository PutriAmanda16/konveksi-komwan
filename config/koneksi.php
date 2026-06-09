<?php
error_reporting(0);
ini_set('display_errors', 0);
// Mengambil variabel yang sudah kita set di tab 'Variables' Railway
$host     = getenv('DB_HOST'); 
$user     = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname   = getenv('DB_NAME');
$port     = getenv('DB_PORT');

// Mengkoneksikan ke MySQL memakai Port dinamis
$conn = new mysqli($host, $user, $password, $dbname, $port);

if ($conn->connect_error) {
    // Jangan biarkan script mati total tanpa pesan, cek errornya
    die("Koneksi gagal: " . $conn->connect_error);
}
echo "Koneksi sukses!";
?>