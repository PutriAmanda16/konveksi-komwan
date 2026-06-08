<?php
session_start();
include "../config/koneksi.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id             = mysqli_real_escape_string($koneksi, $_POST['id']);
    $status         = mysqli_real_escape_string($koneksi, $_POST['status']);
    $keterangan     = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $status_kualitas = mysqli_real_escape_string($koneksi, $_POST['status_kualitas'] ?? 'baik');

    // Cek apakah sudah pernah selesai sebelumnya (jangan overwrite tanggal)
    $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT STATUS_PRODUKSI, TANGGAL_SELESAI FROM produksi WHERE ID_PRODUKSI='$id'"));

    if ($status === 'Selesai' && ($cek['STATUS_PRODUKSI'] !== 'Selesai')) {
        // Baru pertama kali selesai → isi tanggal selesai sekarang
        $tgl_selesai = date('Y-m-d');
        $query = "UPDATE produksi SET
                    STATUS_PRODUKSI  = '$status',
                    KETERANGAN       = '$keterangan',
                    STATUS_KUALITAS  = '$status_kualitas',
                    TANGGAL_SELESAI  = '$tgl_selesai'
                  WHERE ID_PRODUKSI  = '$id'";
    } else {
        // Status selain Selesai, atau sudah pernah selesai → jangan ubah tanggal
        $query = "UPDATE produksi SET
                    STATUS_PRODUKSI  = '$status',
                    KETERANGAN       = '$keterangan',
                    STATUS_KUALITAS  = '$status_kualitas'
                  WHERE ID_PRODUKSI  = '$id'";
    }

    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Laporan progres berhasil disimpan!'); window.location='dashboard.php';</script>";
    } else {
        echo "Error: " . mysqli_error($koneksi);
    }
} else {
    header("Location: dashboard.php");
}
?>