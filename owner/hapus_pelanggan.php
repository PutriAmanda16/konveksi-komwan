<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    mysqli_query($koneksi, "DELETE FROM pelanggan WHERE ID_PELANGGAN='$id'");
}

header("Location: pelanggan.php?deleted=1");
exit;
?>