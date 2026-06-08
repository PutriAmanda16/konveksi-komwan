<?php
session_start();
include "../config/koneksi.php";
if ($_SESSION['role'] != 'owner') { header("Location: ../index.php"); exit; }

$query = mysqli_query($koneksi, "SELECT * FROM bahan_baku");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Stok Bahan | Konveksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar { width: 260px; height: 100vh; position: fixed; background: white; border-right: 1px solid #edf2f7; padding: 30px; }
        .main-content { margin-left: 260px; padding: 40px; background: #f8fafc; min-height: 100vh; }
        .card-table { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-primary fw-bold mb-5">Konveksi Apps</h4>
        <a class="text-dark text-decoration-none d-block mb-3" href="../owner/dashboard.php">← Kembali</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between mb-4">
            <h3 class="fw-bold">Manajemen Bahan Baku</h3>
            <button class="btn btn-primary btn-sm rounded-3">+ Tambah Bahan</button>
        </div>
        
        <div class="card-table">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Bahan</th>
                        <th>Stok</th>
                        <th>Harga Satuan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($b = mysqli_fetch_assoc($query)) : ?>
                    <tr>
                        <td><b><?= $b['ID_BAHAN']; ?></b></td>
                        <td><?= $b['NAMA_BAHAN']; ?></td>
                        <td><?= $b['JUMLAH_STOK']; ?></td>
                        <td>Rp <?= number_format($b['HARGA_SATUAN']); ?></td>
                        <td>
                            <button class="btn btn-outline-secondary btn-sm">Edit</button>
                            <button class="btn btn-outline-danger btn-sm">Hapus</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>