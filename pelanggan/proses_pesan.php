<?php
session_start();
include "../config/koneksi.php";

$id_pelanggan = $_SESSION['id'];
$id_produk    = $_POST['id_produk'];
$jumlah       = $_POST['jumlah'];

// 1. Ambil harga produk
$p_query = mysqli_query($koneksi, "SELECT HARGA FROM produk WHERE ID_PRODUK = '$id_produk'");
$p_data  = mysqli_fetch_assoc($p_query);
$total   = $p_data['HARGA'] * $jumlah;

// 2. Generate ID_PESANAN (Contoh: PSN06)
$id_q = mysqli_query($koneksi, "SELECT ID_PESANAN FROM pesanan ORDER BY ID_PESANAN DESC LIMIT 1");
$id_d = mysqli_fetch_assoc($id_q);
$num  = (int) substr($id_d['ID_PESANAN'], 3) + 1;
$id_p = "PSN" . sprintf("%02s", $num);

// 3. Simpan ke tabel pesanan & detail_pesanan
mysqli_query($koneksi, "INSERT INTO pesanan (ID_PESANAN, ID_OWNER, ID_PELANGGAN, WAKTU_PESAN, TOTAL_HARGA, STATUS) 
                        VALUES ('$id_p', 'OWN1', '$id_pelanggan', NOW(), '$total', 'Pending')");

mysqli_query($koneksi, "INSERT INTO detail_pesanan (ID_PESANAN, ID_PRODUK, JUMLAH, SUBTOTAL) 
                        VALUES ('$id_p', '$id_produk', '$jumlah', '$total')");

header("Location: dashboard.php?pesan=berhasil");