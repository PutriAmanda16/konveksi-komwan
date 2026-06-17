<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'penjahit') {
    header("Location: ../index.php"); exit;
}

$id_produksi = mysqli_real_escape_string($koneksi, $_GET['id'] ?? '');
if (!$id_produksi) { echo "ID tidak valid."; exit; }

$row = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT g.BUKTI_BAYAR FROM penggajian g
     JOIN produksi p ON g.ID_PRODUKSI = p.ID_PRODUKSI
     WHERE g.ID_PRODUKSI = '$id_produksi' AND p.ID_PENJAHIT = '{$_SESSION['id']}'"
));

if (!$row || empty($row['BUKTI_BAYAR'])) {
    echo "Bukti tidak ditemukan."; exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Bukti Gaji</title>
<style>
  body { margin: 0; background: #111; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
  img { max-width: 100%; max-height: 100vh; object-fit: contain; border-radius: 8px; }
</style>
</head>
<body>
  <img src="<?= htmlspecialchars($row['BUKTI_BAYAR']) ?>" alt="Bukti Gaji">
</body>
</html>