<?php
session_start();
include "../config/koneksi.php";

if (!isset($_GET['id'])) { header("Location: dashboard.php"); exit; }

$id_pesanan = $_GET['id'];

$query = mysqli_query($koneksi, "SELECT p.*, pl.NAMA_PELANGGAN, pr.NAMA_PRODUK, dp.JUMLAH, dp.SUBTOTAL 
    FROM pesanan p 
    JOIN pelanggan pl ON p.ID_PELANGGAN = pl.ID_PELANGGAN
    JOIN detail_pesanan dp ON p.ID_PESANAN = dp.ID_PESANAN
    JOIN produk pr ON dp.ID_PRODUK = pr.ID_PRODUK
    WHERE p.ID_PESANAN = '$id_pesanan'");

$d = mysqli_fetch_assoc($query);

if (!$d) {
    echo "<script>alert('Data nota tidak ditemukan!'); window.location='dashboard.php';</script>";
    exit;
}

$status_bayar = $d['STATUS_BAYAR'] ?? null;
$boleh_lihat_nota = ($status_bayar === 'Dikonfirmasi');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota – <?= htmlspecialchars($id_pesanan) ?> | Konveksi Apps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --pink-50:  #fff0f6;
            --pink-100: #ffd6e7;
            --pink-200: #ffadd2;
            --pink-400: #f06292;
            --pink-500: #e91e8c;
            --pink-600: #c2185b;
            --pink-700: #880e4f;
            --white: #ffffff;
            --gray-50: #fafafa;
            --gray-100: #f5f5f5;
            --gray-200: #eeeeee;
            --gray-400: #bdbdbd;
            --gray-600: #757575;
            --gray-700: #616161;
            --gray-800: #424242;
            --gray-900: #212121;
            --shadow-lg: 0 20px 48px rgba(233,30,140,0.14);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--pink-50);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed; top: -120px; right: -120px;
            width: 480px; height: 480px;
            background: radial-gradient(circle, rgba(240,98,146,0.16) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none; z-index: 0;
        }
        body::after {
            content: '';
            position: fixed; bottom: -100px; left: -100px;
            width: 380px; height: 380px;
            background: radial-gradient(circle, rgba(233,30,140,0.10) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none; z-index: 0;
        }

        .page-wrapper {
            position: relative; z-index: 1;
            min-height: 100vh;
            padding: 36px 16px 56px;
            display: flex; align-items: flex-start; justify-content: center;
        }

        .nota-card {
            background: var(--white);
            border-radius: 28px;
            width: 100%; max-width: 560px;
            box-shadow: var(--shadow-lg);
            border: 1.5px solid var(--pink-100);
            overflow: hidden;
            animation: slideUp 0.45s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes slideUp {
            from { opacity:0; transform: translateY(32px) scale(0.97); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }

        /* ── WAITING STATE ── */
        .waiting-wrap { padding: 48px 36px; text-align: center; }

        .waiting-icon-wrap {
            width: 96px; height: 96px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pink-50), #fff5fb);
            border: 2.5px solid var(--pink-100);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            font-size: 42px;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(233,30,140,0.12); }
            50%       { box-shadow: 0 0 0 14px rgba(233,30,140,0); }
        }

        .waiting-title { font-size: 20px; font-weight: 800; color: var(--gray-900); margin-bottom: 10px; }
        .waiting-sub   { font-size: 13px; color: var(--gray-600); line-height: 1.6; margin-bottom: 24px; }

        .badge-metode {
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg, var(--pink-50), #fff0fb);
            border: 1.5px solid var(--pink-200);
            color: var(--pink-600);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 12px; font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .proof-wrap {
            background: var(--gray-50);
            border: 1.5px solid var(--gray-200);
            border-radius: 16px;
            padding: 16px;
            margin: 20px 0 24px;
            display: inline-block;
        }
        .proof-label { font-size: 11.5px; color: var(--gray-600); margin-bottom: 10px; font-weight: 600; }
        .proof-img {
            max-width: 180px; border-radius: 12px;
            border: 2.5px solid var(--pink-100);
            box-shadow: 0 4px 16px rgba(233,30,140,0.1);
        }

        .btn-back-dash {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, var(--pink-500), var(--pink-600));
            color: white; border: none;
            border-radius: 50px;
            padding: 12px 28px;
            font-size: 13.5px; font-weight: 700;
            text-decoration: none;
            box-shadow: 0 6px 20px rgba(233,30,140,0.32);
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-back-dash:hover { opacity: 0.9; transform: translateY(-1px); color: white; }

        /* ── NOTA HEADER ── */
        .nota-header {
            background: linear-gradient(135deg, var(--pink-500) 0%, var(--pink-700) 100%);
            padding: 36px 36px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .nota-header::before {
            content: '';
            position: absolute; top: -40px; right: -40px;
            width: 180px; height: 180px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }
        .nota-header::after {
            content: '';
            position: absolute; bottom: -50px; left: -30px;
            width: 150px; height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .nota-logo { font-size: 38px; margin-bottom: 6px; }
        .nota-brand { font-size: 22px; font-weight: 800; color: white; letter-spacing: 0.5px; }
        .nota-tagline { font-size: 12px; color: rgba(255,255,255,0.75); margin-top: 2px; }
        .nota-confirmed-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(4px);
            border: 1.5px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 50px;
            padding: 6px 16px;
            font-size: 12px; font-weight: 700;
            margin-top: 14px;
        }
        .nota-confirmed-badge i { color: #a5f3a5; }

        /* Zigzag tear */
        .tear-edge {
            height: 20px;
            background: white;
            position: relative;
            margin-top: -1px;
        }
        .tear-edge::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 20px;
            background: linear-gradient(135deg, var(--pink-700) 0%, var(--pink-500) 100%);
            clip-path: polygon(
                0% 0%, 2.5% 100%, 5% 0%, 7.5% 100%, 10% 0%,
                12.5% 100%, 15% 0%, 17.5% 100%, 20% 0%,
                22.5% 100%, 25% 0%, 27.5% 100%, 30% 0%,
                32.5% 100%, 35% 0%, 37.5% 100%, 40% 0%,
                42.5% 100%, 45% 0%, 47.5% 100%, 50% 0%,
                52.5% 100%, 55% 0%, 57.5% 100%, 60% 0%,
                62.5% 100%, 65% 0%, 67.5% 100%, 70% 0%,
                72.5% 100%, 75% 0%, 77.5% 100%, 80% 0%,
                82.5% 100%, 85% 0%, 87.5% 100%, 90% 0%,
                92.5% 100%, 95% 0%, 97.5% 100%, 100% 0%
            );
        }

        /* ── NOTA BODY ── */
        .nota-body { padding: 28px 36px 32px; }

        /* Meta row */
        .meta-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 12px; margin-bottom: 24px;
        }
        .meta-item {
            background: var(--pink-50);
            border: 1.5px solid var(--pink-100);
            border-radius: 14px;
            padding: 12px 14px;
        }
        .meta-item.full { grid-column: 1 / -1; }
        .meta-key { font-size: 11px; color: var(--gray-600); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .meta-val { font-size: 13.5px; font-weight: 700; color: var(--gray-900); }

        /* Dashed divider */
        .dashed-line {
            border: none;
            border-top: 2px dashed var(--pink-100);
            margin: 20px 0;
        }

        /* Product table */
        .product-table { width: 100%; border-collapse: collapse; }
        .product-table th {
            font-size: 11px; color: var(--gray-600);
            text-transform: uppercase; letter-spacing: 0.5px;
            font-weight: 700; padding: 0 0 10px;
            border-bottom: 1.5px solid var(--pink-100);
        }
        .product-table th:last-child { text-align: right; }
        .product-table th:nth-child(2) { text-align: center; }
        .product-table td {
            padding: 14px 0 0;
            font-size: 13.5px; color: var(--gray-800); font-weight: 500;
            vertical-align: top;
        }
        .product-table td:last-child { text-align: right; font-weight: 700; color: var(--gray-900); }
        .product-table td:nth-child(2) { text-align: center; }
        .product-name { font-weight: 700; color: var(--gray-900); }
        .qty-badge {
            display: inline-block;
            background: var(--pink-50);
            border: 1.5px solid var(--pink-100);
            color: var(--pink-600);
            border-radius: 8px;
            padding: 2px 10px;
            font-size: 12.5px; font-weight: 700;
        }

        /* Total */
        .total-row {
            display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(135deg, var(--pink-50), #fff5fb);
            border: 1.5px solid var(--pink-100);
            border-radius: 16px;
            padding: 16px 20px;
            margin-top: 20px;
        }
        .total-label { font-size: 14px; font-weight: 700; color: var(--gray-800); }
        .total-amount {
            font-size: 22px; font-weight: 800;
            background: linear-gradient(90deg, var(--pink-500), var(--pink-700));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* Status */
        .status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            border-radius: 50px;
            padding: 7px 18px;
            font-size: 12.5px; font-weight: 700;
        }
        .status-pill.diproses { background: #e3f2fd; color: #1565c0; }
        .status-pill.selesai  { background: #e8f5e9; color: #2e7d32; }
        .status-pill.pending  { background: #fff8e1; color: #e65100; }
        .status-pill.default  { background: var(--pink-50); color: var(--pink-600); }

        /* Action buttons */
        .action-row { display: flex; gap: 10px; margin-top: 28px; }

        .btn-print {
            flex: 1;
            background: linear-gradient(135deg, var(--pink-500), var(--pink-600));
            color: white; border: none;
            border-radius: 14px;
            padding: 13px;
            font-size: 13.5px; font-weight: 700;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            box-shadow: 0 6px 20px rgba(233,30,140,0.3);
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-print:hover { opacity: 0.9; transform: translateY(-1px); }

        .btn-back-outline {
            flex: 1;
            background: white;
            color: var(--gray-700);
            border: 1.5px solid var(--gray-200);
            border-radius: 14px;
            padding: 13px;
            font-size: 13.5px; font-weight: 700;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: border-color 0.2s, color 0.2s, background 0.2s;
        }
        .btn-back-outline:hover { border-color: var(--pink-300); color: var(--pink-500); background: var(--pink-50); }

        /* Footer */
        .nota-footer {
            border-top: 1.5px solid var(--pink-50);
            padding: 16px 36px;
            text-align: center;
            background: var(--gray-50);
        }
        .nota-footer p { font-size: 11.5px; color: var(--gray-400); }

        /* ── PRINT ── */
        @media print {
            body { background: white; }
            body::before, body::after { display: none; }
            .page-wrapper { padding: 0; display: block; }
            .nota-card { box-shadow: none; border: none; border-radius: 0; max-width: 100%; animation: none; }
            .no-print { display: none !important; }
            .action-row { display: none !important; }
            .nota-footer { display: none; }
        }

        @media (max-width: 480px) {
            .nota-body { padding: 22px 20px 28px; }
            .nota-header { padding: 28px 20px 22px; }
            .nota-footer { padding: 14px 20px; }
            .meta-grid { grid-template-columns: 1fr; }
            .meta-item.full { grid-column: auto; }
        }
    </style>
</head>
<body>
<div class="page-wrapper">
<div class="nota-card">

<?php if (!$boleh_lihat_nota): ?>
<!-- ── WAITING STATE ── -->
<div class="waiting-wrap">
    <div class="waiting-icon-wrap">⏳</div>
    <div class="waiting-title">Menunggu Konfirmasi Admin</div>
    <p class="waiting-sub">
        Bukti pembayaran kamu sudah kami terima.<br>
        Struk resmi akan muncul setelah admin<br>mengkonfirmasi pembayaran.
    </p>

    <?php if (!empty($d['METODE_BAYAR'])): ?>
    <div class="mb-4">
        <span class="text-muted" style="font-size:12px; font-weight:600;">Metode Pembayaran</span><br>
        <span class="badge-metode mt-2">
            <i class="bi bi-<?= $d['METODE_BAYAR'] === 'qris' ? 'qr-code' : 'bank2' ?>"></i>
            <?= strtoupper(htmlspecialchars($d['METODE_BAYAR'])) ?>
        </span>
    </div>
    <?php endif; ?>

    <?php if (!empty($d['BUKTI_BAYAR'])): ?>
    <div class="proof-wrap">
        <div class="proof-label"><i class="bi bi-image me-1"></i>Bukti yang kamu upload:</div>
        <img src="../assets/bukti_bayar/<?= htmlspecialchars($d['BUKTI_BAYAR']) ?>" class="proof-img" alt="Bukti Bayar">
    </div>
    <?php endif; ?>

    <a href="dashboard.php" class="btn-back-dash">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
    </a>
</div>

<?php else: ?>
<!-- ── CONFIRMED RECEIPT ── -->

<!-- Header -->
<div class="nota-header">
    <div class="nota-logo">🧾</div>
    <div class="nota-brand">KONVEKSI APPS</div>
    <div class="nota-tagline">Nota Pemesanan Resmi</div>
    <div class="nota-confirmed-badge">
        <i class="bi bi-patch-check-fill"></i> Pembayaran Dikonfirmasi
    </div>
</div>
<div class="tear-edge"></div>

<!-- Body -->
<div class="nota-body">

    <!-- Meta Info -->
    <div class="meta-grid">
        <div class="meta-item">
            <div class="meta-key"><i class="bi bi-hash me-1"></i>ID Pesanan</div>
            <div class="meta-val"><?= htmlspecialchars($d['ID_PESANAN']) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-key"><i class="bi bi-calendar3 me-1"></i>Tanggal</div>
            <div class="meta-val"><?= date('d/m/Y', strtotime($d['WAKTU_PESAN'])) ?></div>
        </div>
        <div class="meta-item full">
            <div class="meta-key"><i class="bi bi-person me-1"></i>Pelanggan</div>
            <div class="meta-val"><?= htmlspecialchars($d['NAMA_PELANGGAN']) ?></div>
        </div>
        <?php if (!empty($d['METODE_BAYAR'])): ?>
        <div class="meta-item full">
            <div class="meta-key"><i class="bi bi-credit-card me-1"></i>Metode Bayar</div>
            <div class="meta-val">
                <span class="badge-metode">
                    <i class="bi bi-<?= $d['METODE_BAYAR'] === 'qris' ? 'qr-code' : 'bank2' ?>"></i>
                    <?= strtoupper(htmlspecialchars($d['METODE_BAYAR'])) ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <hr class="dashed-line">

    <!-- Product Table -->
    <table class="product-table">
        <thead>
            <tr>
                <th>Produk</th>
                <th>Qty</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><div class="product-name"><?= htmlspecialchars($d['NAMA_PRODUK']) ?></div></td>
                <td><span class="qty-badge"><?= $d['JUMLAH'] ?> pcs</span></td>
                <td>Rp <?= number_format($d['SUBTOTAL']) ?></td>
            </tr>
        </tbody>
    </table>

    <hr class="dashed-line">

    <!-- Total -->
    <div class="total-row">
        <span class="total-label">TOTAL PEMBAYARAN</span>
        <span class="total-amount">Rp <?= number_format($d['TOTAL_HARGA']) ?></span>
    </div>

    <!-- Status -->
    <div class="text-center mt-4">
        <span class="text-muted" style="font-size:12px; font-weight:600; display:block; margin-bottom:8px;">Status Pesanan</span>
        <?php
        $st = strtolower($d['STATUS'] ?? '');
        $cls = str_contains($st,'selesai') ? 'selesai' : (str_contains($st,'proses') ? 'diproses' : (str_contains($st,'pending') ? 'pending' : 'default'));
        $icon = str_contains($st,'selesai') ? 'check-circle-fill' : (str_contains($st,'proses') ? 'arrow-repeat' : 'clock');
        ?>
        <span class="status-pill <?= $cls ?>">
            <i class="bi bi-<?= $icon ?>"></i>
            <?= htmlspecialchars($d['STATUS']) ?>
        </span>
    </div>

    <!-- Actions -->
    <div class="action-row no-print">
        <button onclick="window.print()" class="btn-print">
            <i class="bi bi-printer-fill"></i> Cetak Nota
        </button>
        <a href="dashboard.php" class="btn-back-outline">
            <i class="bi bi-house"></i> Dashboard
        </a>
    </div>

</div>

<!-- Footer -->
<div class="nota-footer no-print">
    <p>Terima kasih telah berbelanja di Konveksi Apps 🩷</p>
    <p style="margin-top:2px;">Simpan nota ini sebagai bukti pemesanan Anda.</p>
</div>

<?php endif; ?>
</div>
</div>
</body>
</html>