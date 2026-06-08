<?php
session_start();
include "../config/koneksi.php";

// Ambil semua tugas produksi yang belum ada di tabel penggajian atau belum dikonfirmasi
$query = mysqli_query($koneksi, "SELECT p.*, pr.NAMA_PRODUK, pj.NAMA_PENJAHIT, pj.UPAH_PER_UNIT 
                                FROM produksi p 
                                JOIN produk pr ON p.ID_PRODUK = pr.ID_PRODUK 
                                JOIN penjahit pj ON p.ID_PENJAHIT = pj.ID_PENJAHIT 
                                LEFT JOIN penggajian g ON p.ID_PRODUKSI = g.ID_PRODUKSI 
                                WHERE g.ID_GAJI IS NULL OR g.STATUS_TERIMA = 'Belum'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bayar Gaji | Owner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
    <div class="container bg-white p-4 rounded shadow-sm">
        <h4 class="fw-bold mb-4">Daftar Gaji yang Harus Dibayar</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Penjahit</th>
                    <th>Produk</th>
                    <th>Qty</th>
                    <th>Total Upah</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($query)) : 
                    $total_upah = $row['JUMLAH_DIPRODUKSI'] * $row['UPAH_PER_UNIT'];
                ?>
                <tr>
                    <td><?= $row['NAMA_PENJAHIT'] ?></td>
                    <td><?= $row['NAMA_PRODUK'] ?></td>
                    <td><?= $row['JUMLAH_DIPRODUKSI'] ?> Pcs</td>
                    <td class="fw-bold text-success">Rp <?= number_format($total_upah) ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalBayar<?= $row['ID_PRODUKSI'] ?>">
                            Input Bukti Bayar
                        </button>
                    </td>
                </tr>

                <div class="modal fade" id="modalBayar<?= $row['ID_PRODUKSI'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <form action="proses_simpan_gaji.php" method="POST" enctype="multipart/form-data" class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Bayar Gaji - <?= $row['NAMA_PENJAHIT'] ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="id_pjt" value="<?= $row['ID_PENJAHIT'] ?>">
                                <input type="hidden" name="id_prod" value="<?= $row['ID_PRODUKSI'] ?>">
                                <input type="hidden" name="total" value="<?= $total_upah ?>">
                                
                                <label class="small fw-bold">Upload Nota/Bukti Transfer</label>
                                <input type="file" name="bukti_gaji" class="form-control mb-3" required>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-success">Kirim Gaji</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>