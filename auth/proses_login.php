<?php
session_start();
include "../config/koneksi.php"; // Pastikan path benar ke koneksi.php

$username = mysqli_real_escape_string($koneksi, $_POST['username']);
$password = $_POST['password']; // Jika di database tidak MD5, biarkan begini. Jika MD5, gunakan md5($_POST['password'])
$role     = $_POST['role'];

// Logika mencari ke tabel masing-masing sesuai ERD
if ($role == 'owner') {
    $sql = "SELECT * FROM owner WHERE username='$username' AND password='$password'";
    $redirect = "../owner/dashboard.php";
} elseif ($role == 'penjahit') {
    $sql = "SELECT * FROM penjahit WHERE username='$username' AND password='$password'";
    $redirect = "../penjahit/dashboard.php";
} else {
    $sql = "SELECT * FROM pelanggan WHERE username='$username' AND password='$password'";
    $redirect = "../pelanggan/dashboard.php";
}

$query = mysqli_query($koneksi, $sql);
$data = mysqli_fetch_assoc($query);

if ($data) {
    $_SESSION['login'] = true;
    $_SESSION['role']  = $role;
    // Ambil nama berdasarkan kolom di tabel masing-masing
    $_SESSION['user']  = ($role == 'owner') ? $data['NAMA_OWNER'] : (($role == 'penjahit') ? $data['NAMA_PENJAHIT'] : $data['NAMA_PELANGGAN']);
    $_SESSION['id']    = ($role == 'owner') ? $data['ID_OWNER'] : (($role == 'penjahit') ? $data['ID_PENJAHIT'] : $data['ID_PELANGGAN']);

    header("Location: $redirect");
} else {
    echo "<script>alert('Login Gagal! Username atau Password salah.'); window.location='../index.php';</script>";
}
