<?php
session_start();
include "../config/koneksi.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_pesanan   = $_POST['id_pesanan'];
    $total        = $_POST['total_harga'];
    $metode_bayar = $_POST['metode_bayar']; // 'qris' atau 'transfer'

    // 1. Proses Upload Bukti
    $filename  = $_FILES['bukti_bayar']['name'];
    $tmp_name  = $_FILES['bukti_bayar']['tmp_name'];
    $ekstensi  = pathinfo($filename, PATHINFO_EXTENSION);
    $new_name  = "BUKTI_" . $id_pesanan . "." . $ekstensi;
    $target_dir = "../assets/bukti_bayar/";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    if (move_uploaded_file($tmp_name, $target_dir . $new_name)) {

        // 2. Update pesanan: simpan bukti, metode, dan status menunggu konfirmasi
        $update = "UPDATE pesanan SET 
                    BUKTI_BAYAR = '$new_name', 
                    METODE_BAYAR = '$metode_bayar',
                    STATUS_BAYAR = 'Menunggu Konfirmasi'
                   WHERE ID_PESANAN = '$id_pesanan'";

        if (mysqli_query($koneksi, $update)) {
            echo "<script>
                alert('Bukti pembayaran berhasil dikirim!\\nTunggu konfirmasi dari admin.\\nStruk akan tersedia setelah dikonfirmasi.');
                window.location='dashboard.php';
            </script>";
        } else {
            echo "Error Database: " . mysqli_error($koneksi);
        }

    } else {
        echo "<script>alert('Gagal upload gambar. Coba lagi.'); window.history.back();</script>";
    }
}
?>