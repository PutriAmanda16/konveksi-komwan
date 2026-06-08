<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'penjahit') {
    header("Location: ../index.php");
    exit;
}

if (isset($_GET['id'])) {
    $id_produksi = mysqli_real_escape_string($koneksi, $_GET['id']);
    $waktu_konfirmasi = date('Y-m-d H:i:s'); // Timestamp saat konfirmasi

    $query = "UPDATE penggajian 
              SET STATUS_TERIMA = 'Diterima', 
                  TANGGAL_KONFIRMASI = '$waktu_konfirmasi'
              WHERE ID_PRODUKSI = '$id_produksi'";

    if (mysqli_query($koneksi, $query)) {
        echo "<script>
                alert('✅ Terima kasih! Upah telah dikonfirmasi pada " . date('d/m/Y H:i') . " WIB.');
                window.location='dashboard.php';
              </script>";
    } else {
        echo "Gagal mengonfirmasi: " . mysqli_error($koneksi);
    }
} else {
    header("Location: dashboard.php");
}
?>