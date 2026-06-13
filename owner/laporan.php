<?php
include '../koneksi.php';
session_start();

if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true || $_SESSION['role'] !== 'owner') {
    echo "<script>alert('Anda harus login sebagai Owner terlebih dahulu!'); window.location.href='../login.php';</script>";
    exit;
}

// Ambil data untuk ringkasan dari database
$q_pesanan = mysqli_query($koneksi, "SELECT SUM(TOTAL_HARGA) AS total FROM pesanan WHERE STATUS_PESANAN='Selesai'");
$total_pesanan = mysqli_fetch_assoc($q_pesanan)['total'] ?? 0;

$q_bahan = mysqli_query($koneksi, "SELECT SUM(TOTAL_HARGA) AS total FROM pembelian_bahan");
$total_bahan = mysqli_fetch_assoc($q_bahan)['total'] ?? 0;

$q_gaji = mysqli_query($koneksi, "SELECT SUM(JUMLAH_GAJI) AS total FROM penggajian WHERE STATUS_BAYAR='Selesai'");
$total_gaji = mysqli_fetch_assoc($q_gaji)['total'] ?? 0;

$q_aset = mysqli_query($koneksi, "SELECT SUM(NILAI_ASET) AS total FROM aset");
$total_aset = mysqli_fetch_assoc($q_aset)['total'] ?? 0;

$q_servis = mysqli_query($koneksi, "SELECT SUM(BIAYA) AS total FROM servis_aset");
$biaya_servis = mysqli_fetch_assoc($q_servis)['total'] ?? 0;

// Kalkulasi Keuangan
$total_pemasukan = $total_pesanan;
$total_pengeluaran = $total_bahan + $total_gaji + $biaya_servis;
$saldo_bersih = $total_pemasukan - $total_pengeluaran;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan &amp; Aset - Konveksi Apps</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <style>
        :root {
            --p50: #fff0f6; --p100: #ffdeeb; --p200: #fcc2d7; --p500: #e64980; --p600: #d6336c; --p700: #c2255c;
            --g50: #f8f9fa; --g100: #f1f3f5; --g200: #e9ecef; --g300: #dee2e6; --g600: #868e96; --g700: #495057; --g900: #212529;
            --bg: #fcf8fa; --card-bg: #ffffff; --text: #2c3e50; --text2: #7f8c8d; --border: #faebee; --border2: #f3d9e2;
            --g500: #40c057; --g700: #2b8a3e; --r500: #fa5252; --r700: #c92a2a;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; overflow-x: hidden; }
        
        /* Layout */
        .main-content { flex: 1; padding: 30px; max-width: 1400px; margin: 0 auto; width: 100%; }
        
        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title h1 { font-size: 24px; font-weight: 800; color: var(--g900); }
        .page-title p { color: var(--text2); font-size: 14px; margin-top: 4px; }
        .print-btn { background: var(--p500); color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
        .print-btn:hover { background: var(--p600); transform: translateY(-1px); }

        /* Summary Cards */
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 35px; }
        .s-card { background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; position: relative; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .s-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .s-icon.in { background: #ebfbee; color: var(--g500); }
        .s-icon.out { background: #fff5f5; color: var(--r500); }
        .s-icon.bal { background: var(--p50); color: var(--p500); }
        .s-info p { font-size: 13px; font-weight: 600; color: var(--text2); text-transform: uppercase; letter-spacing: 0.5px; }
        .s-info h3 { font-size: 24px; font-weight: 800; color: var(--g900); margin-top: 4px; }

        /* Navigation Tabs */
        .tabs-nav { display: flex; gap: 10px; background: var(--card-bg); padding: 8px; border-radius: 16px; border: 1.5px solid var(--border); margin-bottom: 25px; overflow-x: auto; }
        .tab-btn { padding: 12px 20px; border: none; background: none; font-size: 14px; font-weight: 700; color: var(--text2); border-radius: 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; white-space: nowrap; }
        .tab-btn:hover { color: var(--p500); background: var(--p50); }
        .tab-btn.active { background: var(--p500); color: white; }

        /* Tab Content & Tables */
        .tab-pane { display: none; }
        .tab-pane.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .tbl-card { background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 20px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .tbl-hd { padding: 20px 24px; border-bottom: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fffbfd; }
        .tbl-title { font-size: 16px; font-weight: 700; color: var(--g900); display: flex; align-items: center; gap: 8px; }
        .tbl-total { font-size: 15px; font-weight: 700; color: var(--g900); background: var(--g50); padding: 6px 14px; border-radius: 99px; border: 1px solid var(--g200); }
        
        .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        .data-table th { background: #fffbfd; padding: 16px 24px; font-weight: 700; color: var(--g700); border-bottom: 1.5px solid var(--border); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .data-table td { padding: 16px 24px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: var(--g50); }

        /* Badges & Tags */
        .badge { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .badge-g { background: #ebfbee; color: var(--g700); }
        .badge-b { background: #e7f5ff; color: #1c7ed6; }
        .id-tag { font-family: monospace; font-size: 13px; font-weight: 700; padding: 4px 8px; border-radius: 6px; }
        .id-tag.blue { background: #e8f4fd; color: #1d8cf8; }
        
        /* Condition States */
        .kond-baik { color: var(--g700); font-weight:700; display:inline-flex; align-items:center; gap:5px; }
        .kond-service { color: #f59f00; font-weight:700; display:inline-flex; align-items:center; gap:5px; }
        .kond-perlu { color: #f76707; font-weight:700; display:inline-flex; align-items:center; gap:5px; }
        .kond-rusak { color: var(--r700); font-weight:700; display:inline-flex; align-items:center; gap:5px; }

        .empty-cell { padding: 40px !important; text-align: center; color: var(--text2); font-size: 14px; }
        .empty-cell i { display: block; font-size: 32px; margin-bottom: 8px; color: var(--g300); }

        @media print {
            body { background: white; color: black; }
            .sidebar, .print-btn, .tabs-nav, .tbl-hd a { display: none !important; }
            .main-content { padding: 0; max-width: 100%; }
            .s-card { border: 1px solid #ccc; box-shadow: none; }
            .tab-pane { display: block !important; opacity: 1 !important; transform: none !important; margin-bottom: 40px; page-break-inside: avoid; }
            .tbl-card { border: 1px solid #ccc; box-shadow: none; overflow: visible; }
            .data-table th { background: #f5f5f5 !important; border-bottom: 2px solid #ccc; }
            .data-table td { border-bottom: 1px solid #eee; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>Detail Transaksi 📋</h1>
                <p>Pantau arus kas, operasional, gaji, dan inventaris aset konveksi</p>
            </div>
            <button onclick="window.print()" class="print-btn">
                <i class="bi bi-printer-fill"></i> Cetak Laporan
            </button>
        </div>

        <div class="summary-grid">
            <div class="s-card">
                <div class="s-icon in"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="s-info">
                    <p>Total Pendapatan (Pesanan)</p>
                    <h3>Rp <?= number_format($total_pemasukan) ?></h3>
                </div>
            </div>
            <div class="s-card">
                <div class="s-icon out"><i class="bi bi-graph-down-arrow"></i></div>
                <div class="s-info">
                    <p>Total Pengeluaran Operasional</p>
                    <h3>Rp <?= number_format($total_pengeluaran) ?></h3>
                </div>
            </div>
            <div class="s-card">
                <div class="s-icon bal"><i class="bi bi-wallet2"></i></div>
                <div class="s-info">
                    <p>Saldo Bersih (Profit)</p>
                    <h3 style="color: <?= $saldo_bersih >= 0 ? 'var(--g700)' : 'var(--r700)' ?>">
                        Rp <?= number_format($saldo_bersih) ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="tabs-nav">
            <button class="tab-btn active" onclick="switchTab('tab-pesanan', this)"><i class="bi bi-bag-check-fill"></i> Pesanan</button>
            <button class="tab-btn" onclick="switchTab('tab-bahan', this)"><i class="bi bi-basket-fill"></i> Pembelian Bahan</button>
            <button class="tab-btn" onclick="switchTab('tab-gaji', this)"><i class="bi bi-cash-stack"></i> Penggajian</button>
            <button class="tab-btn" onclick="switchTab('tab-aset', this)"><i class="bi bi-building-fill-gear"></i> Aset</button>
            <button class="tab-btn" onclick="switchTab('tab-servis', this)"><i class="bi bi-tools"></i> Servis Aset</button>
        </div>

        <div class="tab-pane active" id="tab-pesanan">
            <div class="tbl-card">
                <div class="tbl-hd">
                    <div class="tbl-title"><i class="bi bi-bag-check"></i> Riwayat Pesanan Selesai (Pemasukan)</div>
                    <span class="tbl-total"><i class="bi bi-arrow-up-circle-fill" style="color:var(--g500)"></i> Rp <?= number_format($total_pesanan) ?></span>
                </div>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Pelanggan</th><th>Tanggal</th><th>Metode</th><th>Total Harga</th></tr></thead>
                    <tbody>
                    <?php
                    $qp = mysqli_query($koneksi, "SELECT p.*, pl.NAMA_PELANGGAN FROM pesanan p JOIN pelanggan pl ON p.ID_PELANGGAN=pl.ID_PELANGGAN WHERE p.STATUS_PESANAN='Selesai' ORDER BY p.TANGGAL_PESANAN DESC");
                    $cnt=0;
                    while ($r = mysqli_fetch_assoc($qp)): $cnt++;
                    ?>
                    <tr>
                        <td><span class="id-tag blue"><?= htmlspecialchars($r['ID_PESANAN']) ?></span></td>
                        <td style="font-weight:700"><?= htmlspecialchars($r['NAMA_PELANGGAN']) ?></td>
                        <td style="color:var(--text2)"><?= date('d/m/Y', strtotime($r['TANGGAL_PESANAN'])) ?></td>
                        <td><span class="badge badge-g"><i class="bi bi-credit-card-2-front-fill"></i> <?= htmlspecialchars($r['METODE_PEMBAYARAN']) ?></span></td>
                        <td style="font-weight:700;color:var(--g700)">Rp <?= number_format($r['TOTAL_HARGA']) ?></td>
                    </tr>
                    <?php endwhile; if (!$cnt): ?><tr><td colspan="5"><div class="empty-cell"><i class="bi bi-bag-x"></i>Belum ada pesanan selesai</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane" id="tab-bahan">
            <div class="tbl-card">
                <div class="tbl-hd">
                    <div class="tbl-title"><i class="bi bi-basket"></i> Log Belanja Bahan Baku &amp; Restock</div>
                    <span class="tbl-total"><i class="bi bi-arrow-down-circle-fill" style="color:var(--r500)"></i> Rp <?= number_format($total_bahan) ?></span>
                </div>
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Bahan Baku</th><th>Supplier</th><th>Qty</th><th>Harga Satuan</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php
                    $qb = mysqli_query($koneksi, "SELECT pb.*, b.NAMA_BAHAN, s.NAMA_SUPPLIER FROM pembelian_bahan pb JOIN bahan_baku b ON pb.ID_BAHAN=b.ID_BAHAN JOIN supplier s ON pb.ID_SUPPLIER=s.ID_SUPPLIER ORDER BY pb.TANGGAL_PEMBELIAN DESC");
                    $cnt=0;
                    while ($b = mysqli_fetch_assoc($qb)): $cnt++;
                    ?>
                    <tr>
                        <td style="color:var(--text2)"><?= date('d/m/Y', strtotime($b['TANGGAL_PEMBELIAN'])) ?></td>
                        <td style="font-weight:700"><?= htmlspecialchars($b['NAMA_BAHAN']) ?></td>
                        <td><span class="badge badge-b"><i class="bi bi-truck"></i> <?= htmlspecialchars($b['NAMA_SUPPLIER']) ?></span></td>
                        <td style="font-weight:600"><?= number_format($b['JUMLAH_BELI']) ?></td>
                        <td style="color:var(--text2)">Rp <?= number_format($b['HARGA_SATUAN']) ?></td>
                        <td style="font-weight:700;color:var(--r700)">Rp <?= number_format($b['TOTAL_HARGA']) ?></td>
                    </tr>
                    <?php endwhile; if (!$cnt): ?><tr><td colspan="6"><div class="empty-cell"><i class="bi bi-cart-x"></i>Belum ada data pengeluaran bahan</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane" id="tab-gaji">
            <div class="tbl-card">
                <div class="tbl-hd">
                    <div class="tbl-title"><i class="bi bi-cash-stack"></i> Distribusi Gaji Penjahit &amp; Pegawai</div>
                    <span class="tbl-total"><i class="bi bi-arrow-down-circle-fill" style="color:var(--r500)"></i> Rp <?= number_format($total_gaji) ?></span>
                </div>
                <table class="data-table">
                    <thead><tr><th>Periode Gaji</th><th>Nama Penjahit</th><th>Gaji Pokok</th><th>Bonus</th><th>Total Dibayar</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php
                    $qp = mysqli_query($koneksi, "SELECT p.*, j.NAMA_PENJAHIT FROM penggajian p JOIN data_penjahit j ON p.ID_PENJAHIT=j.ID_PENJAHIT ORDER BY p.TANGGAL_GAJI DESC");
                    $cnt=0;
                    while ($p = mysqli_fetch_assoc($qp)): $cnt++;
                        $st = $p['STATUS_BAYAR'] ?? 'Selesai';
                        
                        switch($st) {
                            case 'Pending':
                                $sc = 'kond-perlu'; $si = 'exclamation-triangle-fill'; break;
                            case 'Gagal':
                                $sc = 'kond-rusak'; $si = 'x-circle-fill'; break;
                            default:
                                $sc = 'kond-baik'; $si = 'check-circle-fill'; break;
                        }
                    ?>
                    <tr>
                        <td style="color:var(--text2)"><?= date('F Y', strtotime($p['TANGGAL_GAJI'])) ?></td>
                        <td style="font-weight:700"><?= htmlspecialchars($p['NAMA_PENJAHIT']) ?></td>
                        <td>Rp <?= number_format($p['GAJI_POKOK']) ?></td>
                        <td style="color:var(--g500)">+Rp <?= number_format($p['BONUS']) ?></td>
                        <td style="font-weight:700;color:var(--text)">Rp <?= number_format($p['JUMLAH_GAJI']) ?></td>
                        <td><span class="<?= $sc ?>"><i class="bi bi-<?= $si ?>"></i> <?= htmlspecialchars($st) ?></span></td>
                    </tr>
                    <?php endwhile; if (!$cnt): ?><tr><td colspan="6"><div class="empty-cell"><i class="bi bi-person-x"></i>Belum ada data penggajian penjahit</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane" id="tab-aset">
            <div class="tbl-card">
                <div class="tbl-hd">
                    <div class="tbl-title"><i class="bi bi-building-gear"></i> Rekap Kondisi Aset</div>
                    <a href="kelola_aset.php" style="display:inline-flex;align-items:center;gap:5px;font-size:12.5px;font-weight:700;color:var(--p500);text-decoration:none;padding:5px 14px;border:1.5px solid var(--border2);border-radius:99px;background:var(--p50)">Kelola Aset <i class="bi bi-arrow-right"></i></a>
                </div>
                <table class="data-table">
                    <thead><tr><th>ID Aset</th><th>Nama Aset</th><th>Jenis</th><th>Nilai Aset</th><th>Kondisi</th></tr></thead>
                    <tbody>
                    <?php
                    $qa = mysqli_query($koneksi, "SELECT * FROM aset ORDER BY ID_ASET ASC");
                    $cnt=0;
                    while ($a = mysqli_fetch_assoc($qa)): $cnt++;
                        $k = $a['KONDISI'] ?? 'Baik';
                        
                        switch($k) {
                            case 'Perlu Service': $kc = 'kond-service'; $ki = 'wrench'; break;
                            case 'Perlu Perbaikan': $kc = 'kond-perlu'; $ki = 'exclamation-triangle-fill'; break;
                            case 'Rusak': $kc = 'kond-rusak'; $ki = 'x-circle-fill'; break;
                            default: $kc = 'kond-baik'; $ki = 'check-circle-fill'; break;
                        }
                    ?>
                    <tr>
                        <td><span class="id-tag blue"><?= htmlspecialchars($a['ID_ASET']) ?></span></td>
                        <td style="font-weight:700"><?= htmlspecialchars($a['NAMA_ASET']) ?></td>
                        <td><span class="badge badge-b"><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($a['JENIS_ASET']) ?></span></td>
                        <td style="font-weight:700;color:var(--text)">Rp <?= number_format($a['NILAI_ASET']) ?></td>
                        <td><span class="<?= $kc ?>"><i class="bi bi-<?= $ki ?>"></i> <?= htmlspecialchars($k) ?></span></td>
                    </tr>
                    <?php endwhile; if (!$cnt): ?><tr><td colspan="5"><div class="empty-cell"><i class="bi bi-building"></i>Belum ada data aset</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane" id="tab-servis">
            <div class="tbl-card">
                <div class="tbl-hd">
                    <div class="tbl-title"><i class="bi bi-tools"></i> Riwayat Servis &amp; Perbaikan Aset</div>
                    <span class="tbl-total"><i class="bi bi-arrow-down-circle-fill" style="color:var(--r500)"></i> Rp <?= number_format($biaya_servis) ?></span>
                </div>
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Nama Aset</th><th>Jenis</th><th>Keterangan</th><th>Biaya</th><th>Kondisi Setelah</th></tr></thead>
                    <tbody>
                    <?php
                    $qs = mysqli_query($koneksi, "SELECT s.*, a.NAMA_ASET, a.JENIS_ASET FROM servis_aset s JOIN aset a ON s.ID_ASET=a.ID_ASET ORDER BY s.TANGGAL_SERVIS DESC");
                    $cnt=0;
                    while ($s = mysqli_fetch_assoc($qs)): $cnt++;
                        $ks = $s['KONDISI_ASET'] ?? 'Baik';
                        
                        switch($ks) {
                            case 'Perlu Service': $kc2 = 'kond-service'; $ki2 = 'wrench'; break;
                            case 'Perlu Perbaikan': $kc2 = 'kond-perlu'; $ki2 = 'exclamation-triangle-fill'; break;
                            case 'Rusak': $kc2 = 'kond-rusak'; $ki2 = 'x-circle-fill'; break;
                            default: $kc2 = 'kond-baik'; $ki2 = 'check-circle-fill'; break;
                        }
                    ?>
                    <tr>
                        <td style="color:var(--text2)"><?= date('d/m/Y', strtotime($s['TANGGAL_SERVIS'])) ?></td>
                        <td style="font-weight:700"><?= htmlspecialchars($s['NAMA_ASET']) ?></td>
                        <td><span class="badge badge-b" style="font-size:11px"><?= htmlspecialchars($s['JENIS_ASET']) ?></span></td>
                        <td style="color:var(--text2);font-size:13px"><?= htmlspecialchars($s['KETERANGAN']) ?></td>
                        <td style="font-weight:700;color:var(--r700)">Rp <?= number_format($s['BIAYA']) ?></td>                    
                        <td><span class="<?= $kc2 ?>"><i class="bi bi-<?= $ki2 ?>"></i> <?= htmlspecialchars($ks) ?></span></td>
                    </tr>
                    <?php endwhile; if (!$cnt): ?><tr><td colspan="6"><div class="empty-cell"><i class="bi bi-wrench"></i>Belum ada riwayat servis</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function switchTab(tabId, btn) {
            // Sembunyikan seluruh tab pane yang ada
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            // Hilangkan status active pada seluruh tombol navigasi
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
            });
            
            // Aktifkan tab pane dan tombol yang diklik oleh user
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>
</html>