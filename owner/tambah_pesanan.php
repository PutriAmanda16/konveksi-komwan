<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;
$aset_rusak  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM aset WHERE KONDISI_ASET IN ('Rusak','Perlu Perbaikan')"))['t'] ?? 0;
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));

$nama_owner = $_SESSION['user'];
$inisial = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

$error   = '';
$success = '';

// ── Generate ID Pesanan otomatis
function generateIdPesanan($koneksi) {
    $last = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT ID_PESANAN FROM pesanan ORDER BY ID_PESANAN DESC LIMIT 1"));
    if ($last) {
        $num = (int) filter_var($last['ID_PESANAN'], FILTER_SANITIZE_NUMBER_INT);
        return 'PSN' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }
    return 'PSN001';
}

// ── PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pelanggan = mysqli_real_escape_string($koneksi, $_POST['id_pelanggan']);
    $metode_bayar = mysqli_real_escape_string($koneksi, $_POST['metode_bayar']);
    $status       = 'Pending';
    $status_bayar = 'Belum Bayar';
    $waktu_pesan  = date('Y-m-d H:i:s');
    $id_owner     = mysqli_real_escape_string($koneksi, $_SESSION['user']);

    $produk_ids = $_POST['produk_id']   ?? [];
    $jumlah_arr = $_POST['jumlah']      ?? [];
    $ukuran_arr = $_POST['ukuran_item'] ?? [];

    if (empty($id_pelanggan)) {
        $error = 'Pelanggan wajib dipilih.';
    } elseif (empty($produk_ids)) {
        $error = 'Tambahkan minimal 1 produk.';
    } else {
        // Hitung total
        $total_harga = 0;
        $detail_rows = [];
        $valid = true;

        foreach ($produk_ids as $i => $id_produk) {
            if (empty($id_produk)) continue;
            $id_produk_esc = mysqli_real_escape_string($koneksi, $id_produk);
            $jumlah        = (int)($jumlah_arr[$i] ?? 1);
            $ukuran_item   = mysqli_real_escape_string($koneksi, $ukuran_arr[$i] ?? '');

            $produk_row = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT HARGA, NAMA_PRODUK FROM produk WHERE ID_PRODUK='$id_produk_esc'"));

            if (!$produk_row) { $valid = false; $error = "Produk tidak ditemukan."; break; }

            $subtotal     = $produk_row['HARGA'] * $jumlah;
            $total_harga += $subtotal;
            $detail_rows[] = [
                'id_produk' => $id_produk_esc,
                'jumlah'    => $jumlah,
                'ukuran'    => $ukuran_item,
                'subtotal'  => $subtotal,
            ];
        }

        if ($valid && !empty($detail_rows)) {
            $id_pesanan = generateIdPesanan($koneksi);

            mysqli_begin_transaction($koneksi);
            try {
                mysqli_query($koneksi, "
                    INSERT INTO pesanan
                        (ID_PESANAN, ID_OWNER, ID_PELANGGAN, WAKTU_PESAN, TOTAL_HARGA, STATUS, METODE_BAYAR, STATUS_BAYAR)
                    VALUES
                        ('$id_pesanan', '$id_owner', '$id_pelanggan', '$waktu_pesan',
                         '$total_harga', '$status', '$metode_bayar', '$status_bayar')
                ");

                foreach ($detail_rows as $d) {
                    mysqli_query($koneksi, "
                        INSERT INTO detail_pesanan
                            (ID_PESANAN, ID_PRODUK, JUMLAH, UKURAN, SUBTOTAL)
                        VALUES
                            ('{$id_pesanan}', '{$d['id_produk']}', '{$d['jumlah']}',
                             '{$d['ukuran']}', '{$d['subtotal']}')
                    ");
                }

                mysqli_commit($koneksi);
                $success = "Pesanan <strong>$id_pesanan</strong> berhasil ditambahkan!";
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $error = 'Gagal menyimpan pesanan. Silakan coba lagi.';
            }
        }
    }
}

// Data untuk dropdown
$pelanggan_list = mysqli_query($koneksi, "SELECT ID_PELANGGAN, NAMA_PELANGGAN FROM pelanggan ORDER BY NAMA_PELANGGAN");
$produk_list    = mysqli_query($koneksi, "SELECT ID_PRODUK, NAMA_PRODUK, HARGA, UKURAN FROM produk ORDER BY NAMA_PRODUK");

$produk_data = [];
$produk_res  = mysqli_query($koneksi, "SELECT ID_PRODUK, NAMA_PRODUK, HARGA, UKURAN FROM produk ORDER BY NAMA_PRODUK");
while ($p = mysqli_fetch_assoc($produk_res)) {
    $produk_data[$p['ID_PRODUK']] = $p;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Pesanan | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --rose:       #f43f5e;
    --rose-hover: #e11d48;
    --rose-deep:  #881337;
    --rose-mid:   #fda4af;
    --rose-light: #fff1f4;
    --blush:      #fdf2f5;
    --cream:      #fef9fb;
    --white:      #ffffff;
    --border:     #f5dde6;
    --border-mid: #f0c8d4;
    --text-main:  #1a0a10;
    --text-muted: #7c5a67;
    --text-hint:  #b896a3;
    --green:      #10b981;
    --green-bg:   #ecfdf5;
    --green-text: #065f46;
    --amber:      #f59e0b;
    --amber-bg:   #fffbeb;
    --blue:       #3b82f6;
    --blue-bg:    #eff6ff;
    --blue-text:  #1d4ed8;
    --red:        #ef4444;
    --red-bg:     #fef2f2;
    --red-text:   #991b1b;
    --sidebar-w:  268px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--cream);
    color: var(--text-main);
    min-height: 100vh;
}
body::before {
    content: '';
    position: fixed; top: 0; right: 0;
    width: 600px; height: 600px;
    background: radial-gradient(ellipse at top right, rgba(244,63,94,0.07) 0%, transparent 65%);
    pointer-events: none; z-index: 0;
}

/* ═══ SIDEBAR ═══ */
.sidebar {
    width: var(--sidebar-w);
    height: 100vh; position: fixed; top: 0; left: 0;
    background: var(--white); border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    z-index: 200; overflow-y: auto;
}
.sb-brand { padding: 26px 20px 18px; border-bottom: 1px solid var(--blush); flex-shrink: 0; }
.brand-row { display: flex; align-items: center; gap: 12px; }
.brand-icon {
    width: 44px; height: 44px; border-radius: 14px;
    background: linear-gradient(135deg, var(--rose) 0%, var(--rose-deep) 100%);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(244,63,94,0.3);
}
.brand-name { font-size: 15px; font-weight: 700; color: var(--text-main); }
.brand-role { font-size: 10.5px; color: var(--text-hint); margin-top: 1px; }
.sb-owner {
    margin: 14px 14px 4px;
    background: var(--blush); border: 1px solid var(--border);
    border-radius: 16px; padding: 14px;
    display: flex; align-items: center; gap: 11px;
}
.owner-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, var(--rose) 0%, var(--rose-deep) 100%);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: white;
    flex-shrink: 0; position: relative;
}
.owner-avatar .dot {
    position: absolute; bottom: 0; right: 0;
    width: 10px; height: 10px; background: var(--green);
    border-radius: 50%; border: 2px solid var(--white);
}
.owner-name { font-size: 13px; font-weight: 600; color: var(--text-main); }
.owner-tag  { font-size: 10.5px; color: var(--text-hint); }
.sb-nav { padding: 6px 12px; flex: 1; }
.nav-section {
    font-size: 9.5px; font-weight: 700; color: var(--text-hint);
    text-transform: uppercase; letter-spacing: 0.9px; padding: 16px 10px 5px;
}
.nav-a {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 11px; border-radius: 12px;
    text-decoration: none; font-size: 13px; font-weight: 500;
    color: var(--text-muted); transition: all 0.15s; margin-bottom: 1px;
}
.nav-a i { font-size: 16px; width: 18px; text-align: center; flex-shrink: 0; color: var(--text-hint); }
.nav-a:hover { background: var(--rose-light); color: var(--rose); }
.nav-a:hover i { color: var(--rose); }
.nav-a.active { background: var(--rose); color: white; font-weight: 600; box-shadow: 0 4px 14px rgba(244,63,94,0.28); }
.nav-a.active i { color: white; }
.nav-badge {
    margin-left: auto; min-width: 20px; height: 20px; border-radius: 10px;
    padding: 0 6px; display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; color: white;
}
.nb-red { background: var(--red); } .nb-orange { background: #f97316; } .nb-pink { background: var(--rose); }
.nav-a.active .nav-badge { background: rgba(255,255,255,0.28); }
.sb-footer { padding: 12px; border-top: 1px solid var(--blush); flex-shrink: 0; }
.nav-a.logout { color: var(--red); }
.nav-a.logout i { color: var(--red); }
.nav-a.logout:hover { background: #fef2f2; }

/* ═══ MAIN ═══ */
.main { margin-left: var(--sidebar-w); padding: 34px 38px 64px; min-height: 100vh; position: relative; z-index: 1; }

/* Topbar */
.topbar { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; }
.page-title { font-family: 'DM Serif Display', serif; font-size: 26px; color: var(--text-main); }
.page-title em { color: var(--rose); font-style: normal; }
.page-sub { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.date-chip {
    display: flex; align-items: center; gap: 7px;
    background: var(--white); border: 1px solid var(--border);
    border-radius: 50px; padding: 8px 16px;
    font-size: 12.5px; font-weight: 500; color: var(--text-muted);
}
.date-chip i { color: var(--rose); font-size: 14px; }
.back-btn {
    display: flex; align-items: center; gap: 6px;
    background: var(--blush); border: 1px solid var(--border);
    border-radius: 50px; padding: 8px 16px;
    font-size: 12.5px; font-weight: 600; color: var(--text-muted);
    text-decoration: none; transition: all 0.15s;
}
.back-btn:hover { background: var(--rose-light); color: var(--rose); border-color: var(--rose-mid); }

/* Alert */
.alert {
    display: flex; align-items: flex-start; gap: 12px;
    border-radius: 14px; padding: 14px 18px; margin-bottom: 20px;
    border: 1px solid; animation: slideIn 0.25s ease;
}
@keyframes slideIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
.alert i { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
.alert-success { background: var(--green-bg); border-color: #a7f3d0; }
.alert-success i { color: var(--green); }
.alert-success .alert-text { color: var(--green-text); font-size: 13.5px; }
.alert-error   { background: var(--red-bg);   border-color: #fecaca; }
.alert-error i { color: var(--red); }
.alert-error .alert-text { color: var(--red-text); font-size: 13.5px; }

/* Layout 2 kolom */
.form-layout { display: grid; grid-template-columns: 1fr 360px; gap: 20px; align-items: start; }

/* Card */
.card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 22px; padding: 26px;
    margin-bottom: 18px;
}
.card-title {
    font-size: 15px; font-weight: 700; color: var(--text-main);
    margin-bottom: 20px; padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 9px;
}
.card-title i { color: var(--rose); font-size: 18px; }

/* Form elements */
.form-group { margin-bottom: 18px; }
.form-label {
    display: block; font-size: 12px; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.5px; margin-bottom: 7px;
}
.form-label .req { color: var(--rose); margin-left: 2px; }
.form-control {
    width: 100%; padding: 11px 14px;
    background: var(--blush); border: 1.5px solid var(--border);
    border-radius: 12px; font-size: 13.5px;
    font-family: 'DM Sans', sans-serif; color: var(--text-main);
    transition: all 0.15s; outline: none;
    appearance: none; -webkit-appearance: none;
}
.form-control:focus { border-color: var(--rose); background: var(--white); box-shadow: 0 0 0 3px rgba(244,63,94,0.1); }
.form-control::placeholder { color: var(--text-hint); }
select.form-control { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23b896a3' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }

/* Info pelanggan */
.pelanggan-info {
    background: var(--blush); border: 1px solid var(--border);
    border-radius: 12px; padding: 12px 14px;
    margin-top: 10px; display: none;
    font-size: 13px; color: var(--text-muted);
}
.pelanggan-info.show { display: block; }
.pelanggan-info strong { color: var(--text-main); }

/* ═══ PRODUK ROWS ═══ */
.produk-list { display: flex; flex-direction: column; gap: 12px; }
.produk-row {
    background: var(--blush); border: 1.5px solid var(--border);
    border-radius: 16px; padding: 16px;
    position: relative; transition: border-color 0.15s;
}
.produk-row:hover { border-color: var(--rose-mid); }
.produk-row-grid {
    display: grid;
    grid-template-columns: 1fr 110px 100px;
    gap: 10px; align-items: end;
}
.produk-row-grid2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px; margin-top: 10px;
}
.remove-btn {
    position: absolute; top: 12px; right: 12px;
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--white); border: 1px solid var(--border);
    color: var(--text-hint); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; transition: all 0.15s;
}
.remove-btn:hover { background: var(--red-bg); color: var(--red); border-color: #fecaca; }
.subtotal-display {
    font-size: 13px; font-weight: 700; color: var(--rose);
    margin-top: 10px; text-align: right;
}

/* Tambah produk btn */
.add-produk-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 12px;
    background: var(--white); border: 2px dashed var(--border-mid);
    border-radius: 14px; color: var(--text-hint);
    font-size: 13px; font-weight: 600; cursor: pointer;
    transition: all 0.15s; margin-top: 4px;
    font-family: 'DM Sans', sans-serif;
}
.add-produk-btn:hover { border-color: var(--rose); color: var(--rose); background: var(--rose-light); }

/* ═══ RINGKASAN (sticky right) ═══ */
.ringkasan-wrap { position: sticky; top: 24px; }
.ringkasan-card {
    background: var(--white);
    border: 1px solid var(--border); border-radius: 22px; overflow: hidden;
}
.ringkasan-head {
    background: linear-gradient(135deg, var(--rose) 0%, var(--rose-deep) 100%);
    padding: 20px 24px;
}
.ringkasan-head-title { font-family: 'DM Serif Display', serif; font-size: 18px; color: white; }
.ringkasan-head-sub { font-size: 12px; color: rgba(255,255,255,0.65); margin-top: 2px; }
.ringkasan-body { padding: 20px 24px; }
.ringkasan-rows { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
.ringkasan-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
.ringkasan-row .label { color: var(--text-muted); }
.ringkasan-row .val   { font-weight: 600; color: var(--text-main); }
.ringkasan-divider { border: none; border-top: 1px dashed var(--border-mid); margin: 14px 0; }
.total-row { display: flex; justify-content: space-between; align-items: center; }
.total-label { font-size: 13px; font-weight: 700; color: var(--text-muted); }
.total-amount { font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--rose); }

.submit-btn {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, var(--rose) 0%, var(--rose-deep) 100%);
    color: white; border: none; border-radius: 14px;
    font-size: 14px; font-weight: 700;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    box-shadow: 0 6px 18px rgba(244,63,94,0.3);
    margin-top: 16px;
}
.submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(244,63,94,0.38); }
.submit-btn:active { transform: translateY(0); }

.id-preview {
    background: var(--blush); border: 1px solid var(--border);
    border-radius: 10px; padding: 10px 14px;
    font-size: 12px; color: var(--text-muted);
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 14px;
}
.id-preview span { font-weight: 700; color: var(--rose); font-size: 14px; }

.empty-produk {
    text-align: center; padding: 32px 16px;
    color: var(--text-hint); font-size: 13px;
}
.empty-produk i { font-size: 36px; color: var(--rose-mid); display: block; margin-bottom: 8px; }

@media (max-width: 1100px) { .form-layout { grid-template-columns: 1fr; } .ringkasan-wrap { position: static; } }
@media (max-width: 900px)  { .sidebar { display: none; } .main { margin-left: 0; padding: 20px 16px 48px; } }
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <div class="sb-brand">
        <div class="brand-row">
            <div class="brand-icon">🧵</div>
            <div>
                <div class="brand-name">Konveksi Apps</div>
                <div class="brand-role">Panel Owner</div>
            </div>
        </div>
    </div>
    <div class="sb-owner">
        <div class="owner-avatar">
            <?= $inisial ?>
            <span class="dot"></span>
        </div>
        <div>
            <div class="owner-name"><?= htmlspecialchars($nama_owner) ?></div>
            <div class="owner-tag">Owner &middot; Administrator</div>
        </div>
    </div>
    <nav class="sb-nav">
        <a class="nav-a" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
        <div class="nav-section">Manajemen Data</div>
        <a class="nav-a" href="kelola_produk.php"><i class="bi bi-box-seam"></i> Produk</a>
        <a class="nav-a" href="kelola_bahan.php">
            <i class="bi bi-basket"></i> Bahan Baku
            <?php if ($stok_kritis > 0): ?><span class="nav-badge nb-orange"><?= $stok_kritis ?></span><?php endif; ?>
        </a>
        <a class="nav-a" href="kelola_aset.php">
            <i class="bi bi-building-gear"></i> Aset & Inventaris
            <?php if ($aset_rusak > 0): ?><span class="nav-badge nb-orange"><?= $aset_rusak ?></span><?php endif; ?>
        </a>
        <a class="nav-a" href="data_penjahit.php"><i class="bi bi-people"></i> Data Penjahit</a>
        <a class="nav-a" href="pelanggan.php"><i class="bi bi-person-badge"></i> Data Pelanggan</a>
        <a class="nav-a" href="supplier.php"><i class="bi bi-truck"></i> Data Supplier</a>
        <div class="nav-section">Operasional</div>
        <a class="nav-a active" href="tambah_pesanan.php"><i class="bi bi-plus-circle"></i> Tambah Pesanan</a>
        <a class="nav-a" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi Aktif</a>
        <a class="nav-a" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="nav-a" href="konfirmasi_pembayaran.php">
            <i class="bi bi-credit-card-2-front"></i> Konfirmasi Bayar
            <?php if ($notif_bayar > 0): ?><span class="nav-badge nb-pink"><?= $notif_bayar ?></span><?php endif; ?>
        </a>
        <a class="nav-a" href="chat.php">
            <i class="bi bi-chat-dots-fill"></i> Inbox Chat
            <?php if ($notif_chat > 0): ?><span class="nav-badge nb-red"><?= $notif_chat ?></span><?php endif; ?>
        </a>
        <div class="nav-section">Laporan</div>
        <a class="nav-a" href="laporan.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Keuangan</a>
    </nav>
    <div class="sb-footer">
        <a class="nav-a logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main">
    <div class="topbar">
        <div>
            <div class="page-title">Tambah <em>Pesanan Baru</em></div>
            <div class="page-sub">Buat pesanan baru untuk pelanggan secara manual.</div>
        </div>
        <div class="topbar-right">
            <a href="dashboard.php" class="back-btn"><i class="bi bi-arrow-left"></i> Kembali</a>
            <div class="date-chip"><i class="bi bi-calendar3"></i><?= date('d F Y') ?></div>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <div class="alert-text"><?= $success ?> <a href="tambah_pesanan.php" style="color:var(--green-text);font-weight:700">Tambah lagi?</a></div>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <div class="alert-text"><?= $error ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" id="formPesanan">
    <div class="form-layout">

        <!-- ── KIRI: Form ── -->
        <div>
            <!-- Informasi Pesanan -->
            <div class="card">
                <div class="card-title"><i class="bi bi-person-check"></i> Informasi Pelanggan</div>

                <div class="form-group">
                    <label class="form-label">Pelanggan <span class="req">*</span></label>
                    <select name="id_pelanggan" class="form-control" id="selectPelanggan" required>
                        <option value="">— Pilih pelanggan —</option>
                        <?php
                        mysqli_data_seek($pelanggan_list, 0);
                        while ($p = mysqli_fetch_assoc($pelanggan_list)): ?>
                        <option value="<?= $p['ID_PELANGGAN'] ?>"
                            data-nama="<?= htmlspecialchars($p['NAMA_PELANGGAN']) ?>"
                            data-id="<?= $p['ID_PELANGGAN'] ?>"
                            <?= (isset($_POST['id_pelanggan']) && $_POST['id_pelanggan']==$p['ID_PELANGGAN']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['NAMA_PELANGGAN']) ?> — <?= $p['ID_PELANGGAN'] ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="pelanggan-info" id="pelangganInfo">
                        <strong id="infoPelangganNama">—</strong><br>
                        <span id="infoPelangganId" style="font-size:12px"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Metode Pembayaran</label>
                    <select name="metode_bayar" class="form-control">
                        <option value="">— Pilih metode —</option>
                        <option value="Transfer" <?= (isset($_POST['metode_bayar']) && $_POST['metode_bayar']=='Transfer') ? 'selected':'' ?>>Transfer Bank</option>
                        <option value="COD"      <?= (isset($_POST['metode_bayar']) && $_POST['metode_bayar']=='COD') ? 'selected':'' ?>>COD (Bayar di Tempat)</option>
                        <option value="QRIS"     <?= (isset($_POST['metode_bayar']) && $_POST['metode_bayar']=='QRIS') ? 'selected':'' ?>>QRIS</option>
                        <option value="Tunai"    <?= (isset($_POST['metode_bayar']) && $_POST['metode_bayar']=='Tunai') ? 'selected':'' ?>>Tunai</option>
                    </select>
                </div>
            </div>

            <!-- Daftar Produk -->
            <div class="card">
                <div class="card-title"><i class="bi bi-box-seam"></i> Daftar Produk Dipesan</div>

                <div class="produk-list" id="produkList">
                    <div class="empty-produk" id="emptyProduk">
                        <i class="bi bi-bag-plus"></i>
                        Belum ada produk ditambahkan.<br>Klik tombol di bawah untuk menambah.
                    </div>
                </div>

                <button type="button" class="add-produk-btn" onclick="tambahProduk()">
                    <i class="bi bi-plus-circle-fill"></i> Tambah Produk
                </button>
            </div>
        </div>

        <!-- ── KANAN: Ringkasan ── -->
        <div class="ringkasan-wrap">
            <div class="ringkasan-card">
                <div class="ringkasan-head">
                    <div class="ringkasan-head-title">Ringkasan Pesanan</div>
                    <div class="ringkasan-head-sub">Preview sebelum disimpan</div>
                </div>
                <div class="ringkasan-body">
                    <div class="id-preview">
                        ID Pesanan <span id="previewId"><?= generateIdPesanan($koneksi) ?></span>
                    </div>

                    <div class="ringkasan-rows" id="ringkasanRows">
                        <div style="text-align:center;color:var(--text-hint);font-size:12.5px;padding:12px 0">
                            Belum ada produk dipilih
                        </div>
                    </div>

                    <hr class="ringkasan-divider">
                    <div class="total-row">
                        <span class="total-label">Total Harga</span>
                        <span class="total-amount" id="totalAmount">Rp 0</span>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="bi bi-check-circle-fill"></i> Simpan Pesanan
                    </button>
                    <a href="dashboard.php" style="display:block;text-align:center;margin-top:12px;font-size:12.5px;color:var(--text-hint);text-decoration:none">
                        Batal, kembali ke dashboard
                    </a>
                </div>
            </div>
        </div>

    </div>
    </form>
</main>

<script>
// Data produk dari PHP
const produkData = <?= json_encode($produk_data) ?>;

let rowCount = 0;

function tambahProduk() {
    document.getElementById('emptyProduk').style.display = 'none';

    const idx = rowCount++;
    const div = document.createElement('div');
    div.className = 'produk-row';
    div.id = 'produk-row-' + idx;

    // Buat options produk
    let opts = '<option value="">— Pilih produk —</option>';
    for (const [id, p] of Object.entries(produkData)) {
        opts += `<option value="${id}" data-harga="${p.HARGA}" data-ukuran="${p.UKURAN}">${p.NAMA_PRODUK} — Rp ${parseInt(p.HARGA).toLocaleString('id-ID')}</option>`;
    }

    div.innerHTML = `
        <button type="button" class="remove-btn" onclick="hapusProduk(${idx})" title="Hapus">
            <i class="bi bi-x"></i>
        </button>
        <div class="produk-row-grid">
            <div>
                <div class="form-label">Produk <span class="req">*</span></div>
                <select name="produk_id[]" class="form-control" onchange="updateProdukRow(${idx})" id="produk-select-${idx}" required>
                    ${opts}
                </select>
            </div>
            <div>
                <div class="form-label">Jumlah</div>
                <input type="number" name="jumlah[]" class="form-control" value="1" min="1"
                    id="jumlah-${idx}" onchange="updateSubtotal(${idx})" oninput="updateSubtotal(${idx})">
            </div>
            <div>
                <div class="form-label">Ukuran</div>
                <select name="ukuran_item[]" class="form-control" id="ukuran-${idx}">
                    <option value="">—</option>
                </select>
            </div>
        </div>
        <div class="subtotal-display" id="subtotal-${idx}">Subtotal: Rp 0</div>
    `;

    document.getElementById('produkList').appendChild(div);
    hitungTotal();
}

function hapusProduk(idx) {
    const el = document.getElementById('produk-row-' + idx);
    if (el) el.remove();
    const rows = document.querySelectorAll('.produk-row');
    if (rows.length === 0) document.getElementById('emptyProduk').style.display = 'block';
    hitungTotal();
}

function updateProdukRow(idx) {
    const sel    = document.getElementById('produk-select-' + idx);
    const opt    = sel.options[sel.selectedIndex];
    const ukuran = opt.getAttribute('data-ukuran') || '';

    // Isi dropdown ukuran
    const ukuranSel = document.getElementById('ukuran-' + idx);
    ukuranSel.innerHTML = '<option value="">—</option>';
    if (ukuran) {
        ukuran.split(',').forEach(u => {
            u = u.trim();
            if (u) ukuranSel.innerHTML += `<option value="${u}">${u}</option>`;
        });
    }
    updateSubtotal(idx);
}

function updateSubtotal(idx) {
    const sel    = document.getElementById('produk-select-' + idx);
    const jumlah = parseInt(document.getElementById('jumlah-' + idx).value) || 0;
    const opt    = sel.options[sel.selectedIndex];
    const harga  = parseFloat(opt ? opt.getAttribute('data-harga') : 0) || 0;
    const sub    = harga * jumlah;

    document.getElementById('subtotal-' + idx).textContent =
        'Subtotal: Rp ' + sub.toLocaleString('id-ID');
    hitungTotal();
}

function hitungTotal() {
    let total = 0;
    const rows = [];

    document.querySelectorAll('.produk-row').forEach(row => {
        const id   = row.id.replace('produk-row-', '');
        const sel  = document.getElementById('produk-select-' + id);
        if (!sel) return;
        const opt    = sel.options[sel.selectedIndex];
        const harga  = parseFloat(opt ? opt.getAttribute('data-harga') : 0) || 0;
        const jumlah = parseInt(document.getElementById('jumlah-' + id)?.value) || 0;
        const nama   = opt && opt.value ? opt.text.split(' — ')[0] : null;
        if (!nama) return;
        const sub = harga * jumlah;
        total += sub;
        rows.push({ nama, jumlah, sub });
    });

    document.getElementById('totalAmount').textContent =
        'Rp ' + total.toLocaleString('id-ID');

    // Update ringkasan
    const container = document.getElementById('ringkasanRows');
    if (rows.length === 0) {
        container.innerHTML = '<div style="text-align:center;color:var(--text-hint);font-size:12.5px;padding:12px 0">Belum ada produk dipilih</div>';
    } else {
        container.innerHTML = rows.map(r => `
            <div class="ringkasan-row">
                <span class="label">${r.nama} ×${r.jumlah}</span>
                <span class="val">Rp ${r.sub.toLocaleString('id-ID')}</span>
            </div>
        `).join('');
    }
}

// Info pelanggan
document.getElementById('selectPelanggan').addEventListener('change', function() {
    const opt  = this.options[this.selectedIndex];
    const info = document.getElementById('pelangganInfo');
    if (opt.value) {
        document.getElementById('infoPelangganNama').textContent = opt.getAttribute('data-nama');
        document.getElementById('infoPelangganId').textContent   = 'ID: ' + opt.getAttribute('data-id');
        info.classList.add('show');
    } else {
        info.classList.remove('show');
    }
});

// Validasi sebelum submit
document.getElementById('formPesanan').addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('.produk-row');
    if (rows.length === 0) {
        e.preventDefault();
        alert('Tambahkan minimal 1 produk sebelum menyimpan!');
        return;
    }
    let valid = true;
    rows.forEach(row => {
        const id  = row.id.replace('produk-row-', '');
        const sel = document.getElementById('produk-select-' + id);
        if (!sel || !sel.value) valid = false;
    });
    if (!valid) {
        e.preventDefault();
        alert('Pastikan semua produk sudah dipilih!');
    }
});
</script>
</body>
</html>