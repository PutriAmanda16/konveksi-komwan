<?php
ob_start();
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['id']) || $_SESSION['role'] != 'pelanggan') {
    header("Location: ../index.php");
    exit;
}

$id_pelanggan = $_SESSION['id'];
$id_produk    = mysqli_real_escape_string($koneksi, $_POST['id_produk']);
$catatan      = mysqli_real_escape_string($koneksi, $_POST['catatan'] ?? '');
$ukuran_data  = json_decode($_POST['ukuran_data'] ?? '{}', true); // {"S":2,"M":3,"L":0}

// 1. Ambil harga produk
$p_query = mysqli_query($koneksi, "SELECT HARGA FROM produk WHERE ID_PRODUK='$id_produk'");
$p_data  = mysqli_fetch_assoc($p_query);
$harga   = (int)($p_data['HARGA'] ?? 0);

// 2. Hitung total dari ukuran_data
$total_harga = 0;
$total_qty   = 0;
$items       = []; // array item per ukuran yang qty > 0

foreach ($ukuran_data as $ukuran => $qty) {
    $qty = (int)$qty;
    if ($qty > 0) {
        $subtotal      = $harga * $qty;
        $total_harga  += $subtotal;
        $total_qty    += $qty;
        $items[]       = [
            'ukuran'   => $ukuran,
            'jumlah'   => $qty,
            'subtotal' => $subtotal,
        ];
    }
}

// Kalau tidak ada ukuran (produk tanpa pilihan ukuran), pakai jumlah langsung
if (empty($items)) {
    $jumlah      = (int)($_POST['jumlah'] ?? 1);
    $subtotal    = $harga * $jumlah;
    $total_harga = $subtotal;
    $items[]     = [
        'ukuran'   => '-',
        'jumlah'   => $jumlah,
        'subtotal' => $subtotal,
    ];
}

// 3. Generate ID_PESANAN
$id_q = mysqli_query($koneksi, "SELECT ID_PESANAN FROM pesanan ORDER BY ID_PESANAN DESC LIMIT 1");
$id_d = mysqli_fetch_assoc($id_q);
$num  = $id_d ? (int)substr($id_d['ID_PESANAN'], 3) + 1 : 1;
$id_p = "PSN" . sprintf("%02d", $num);

// 4. Simpan ke tabel pesanan (dengan KETERANGAN)
mysqli_query($koneksi,
    "INSERT INTO pesanan (ID_PESANAN, ID_OWNER, ID_PELANGGAN, WAKTU_PESAN, TOTAL_HARGA, STATUS, KETERANGAN)
     VALUES ('$id_p', 'OWN1', '$id_pelanggan', NOW(), '$total_harga', 'Pending', '$catatan')");

// 5. Simpan detail per ukuran ke detail_pesanan
foreach ($items as $item) {
    $ukuran   = mysqli_real_escape_string($koneksi, $item['ukuran']);
    $jumlah   = (int)$item['jumlah'];
    $subtotal = (int)$item['subtotal'];

    mysqli_query($koneksi,
        "INSERT INTO detail_pesanan (ID_PESANAN, ID_PRODUK, JUMLAH, UKURAN, SUBTOTAL)
         VALUES ('$id_p', '$id_produk', '$jumlah', '$ukuran', '$subtotal')");
}

header("Location: dashboard.php?pesan=berhasil");
exit;