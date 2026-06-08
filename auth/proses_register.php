<?php
include "../config/koneksi.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama     = mysqli_real_escape_string($koneksi, $_POST['nama_pelanggan']);
    $alamat   = mysqli_real_escape_string($koneksi, $_POST['alamat_pelanggan']);
    $no_hp    = mysqli_real_escape_string($koneksi, $_POST['no_hp']);
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);

    // Generate ID_PELANGGAN otomatis (Contoh: PLG06)
    $query_id = mysqli_query($koneksi, "SELECT ID_PELANGGAN FROM pelanggan ORDER BY ID_PELANGGAN DESC LIMIT 1");
    $data_id  = mysqli_fetch_assoc($query_id);
    
    if ($data_id) {
        $last_id = $data_id['ID_PELANGGAN'];
        $no      = (int) substr($last_id, 3) + 1;
        $id_baru = "PLG" . sprintf("%02s", $no);
    } else {
        $id_baru = "PLG01";
    }

    // Insert data
    $query = "INSERT INTO pelanggan (ID_PELANGGAN, NAMA_PELANGGAN, ALAMAT_PELANGGAN, NO_HP, username, password) 
              VALUES ('$id_baru', '$nama', '$alamat', '$no_hp', '$username', '$password')";

    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Berhasil Daftar! Silakan Login.'); window.location='../index.php';</script>";
    } else {
        echo "Error: " . mysqli_error($koneksi);
    }
}
?>