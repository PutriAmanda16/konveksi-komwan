<?php
session_start();
include "../config/koneksi.php";

$id_pelanggan = $_SESSION['id'];
$id_produk    = $_POST['id_produk'];
$jumlah       = (int)$_POST['jumlah'];
$catatan      = mysqli_real_escape_string($koneksi, $_POST['catatan'] ?? '');
$ukuran_data  = $_POST['ukuran_data'] ?? '{}'; // JSON: {"M":30,"L":50,"XL":0}

// 1. Ambil harga produk
$p_data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT HARGA FROM produk WHERE ID_PRODUK = '$id_produk'"));
$harga  = $p_data['HARGA'];
$total  = $harga * $jumlah;

// 2. Generate ID_PESANAN
$id_q = mysqli_query($koneksi, "SELECT ID_PESANAN FROM pesanan ORDER BY ID_PESANAN DESC LIMIT 1");
$id_d = mysqli_fetch_assoc($id_q);
$num  = (int) substr($id_d['ID_PESANAN'] ?? 'PSN00', 3) + 1;
$id_p = "PSN" . sprintf("%02s", $num);

// 3. Simpan pesanan
mysqli_query($koneksi, "INSERT INTO pesanan (ID_PESANAN, ID_OWNER, ID_PELANGGAN, WAKTU_PESAN, TOTAL_HARGA, STATUS)
                        VALUES ('$id_p', 'OWN1', '$id_pelanggan', NOW(), '$total', 'Pending')");

// 4. Simpan detail per ukuran
$ukuran_arr = json_decode($ukuran_data, true) ?? [];
$id_detail  = 1;
$ada_detail = false;

foreach ($ukuran_arr as $ukuran => $qty) {
    $qty = (int)$qty;
    if ($qty <= 0) continue;
    $subtotal  = $harga * $qty;
    $ukuran_esc = mysqli_real_escape_string($koneksi, $ukuran);
    $id_det    = "DT" . $id_p . $id_detail;
    mysqli_query($koneksi, "INSERT INTO detail_pesanan (ID_PESANAN, ID_PRODUK, JUMLAH, UKURAN, SUBTOTAL)
                            VALUES ('$id_p', '$id_produk', '$qty', '$ukuran_esc', '$subtotal')");
    $id_detail++;
    $ada_detail = true;
}

// Fallback jika tidak ada ukuran (produk tanpa pilihan ukuran)
if (!$ada_detail) {
    mysqli_query($koneksi, "INSERT INTO detail_pesanan (ID_PESANAN, ID_PRODUK, JUMLAH, SUBTOTAL)
                            VALUES ('$id_p', '$id_produk', '$jumlah', '$total')");
}

header("Location: dashboard.php?pesan=berhasil");