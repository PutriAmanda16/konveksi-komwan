<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

// Proses konfirmasi / tolak
if (isset($_GET['aksi']) && isset($_GET['id'])) {
    $id_pesanan = mysqli_real_escape_string($koneksi, $_GET['id']);
    $aksi = $_GET['aksi'];
    if ($aksi == 'konfirmasi') {
        mysqli_query($koneksi, "UPDATE pesanan SET STATUS_BAYAR='Dikonfirmasi', STATUS='Proses' WHERE ID_PESANAN='$id_pesanan'");
        echo "<script>alert('Pembayaran berhasil dikonfirmasi!'); window.location='konfirmasi_pembayaran.php';</script>";
    } elseif ($aksi == 'tolak') {
        mysqli_query($koneksi, "UPDATE pesanan SET STATUS_BAYAR='Ditolak' WHERE ID_PESANAN='$id_pesanan'");
        echo "<script>alert('Pembayaran ditolak.'); window.location='konfirmasi_pembayaran.php';</script>";
    }
    exit;
}

// Badge notifikasi
$notif       = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$aset_rusak  = 0;
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;

$nama_owner = $_SESSION['user'];
$inisial = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts   = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

$stat_menunggu     = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$stat_dikonfirmasi = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Dikonfirmasi'"));
$stat_ditolak      = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Ditolak'"));
$stat_total        = $stat_menunggu + $stat_dikonfirmasi + $stat_ditolak;

$total_notif = $notif + $notif_chat + $stok_kritis + $aset_rusak;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Konfirmasi Pembayaran 💳 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --p50:  #fff0f5;
    --p100: #ffd6e7;
    --p200: #ffadd0;
    --p300: #ff80b8;
    --p400: #f950a0;
    --p500: #e8328a;
    --p600: #cc1a73;
    --p700: #a8105d;
    --v100: #f3e8ff;
    --v300: #d8b4fe;
    --v500: #a855f7;
    --g100: #dcfce7;
    --g500: #22c55e;
    --g700: #15803d;
    --a100: #fef9c3;
    --a500: #eab308;
    --a700: #854d0e;
    --b100: #dbeafe;
    --b500: #3b82f6;
    --b700: #1d4ed8;
    --r100: #fee2e2;
    --r500: #ef4444;
    --r700: #991b1b;
    --o100: #ffedd5;
    --o500: #f97316;
    --o700: #9a3412;
    --white:   #ffffff;
    --bg:      #fff5f9;
    --bg2:     #ffeef5;
    --text:    #3d1a28;
    --text2:   #7d4460;
    --text3:   #b07090;
    --border:  rgba(232,50,138,0.13);
    --border2: rgba(232,50,138,0.24);
    --sidebar-w: 256px;
    --topbar-h:  64px;
    --r-sm: 10px;
    --r-md: 16px;
    --r-lg: 22px;
    --r-xl: 28px;
    --ease: 0.2s cubic-bezier(0.34,1.56,0.64,1);
    --ease-plain: 0.17s ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Nunito', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    font-size: 14.5px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}
body::before {
    content: '';
    position: fixed; inset: 0;
    background-image: radial-gradient(circle, rgba(232,50,138,0.055) 1.5px, transparent 1.5px);
    background-size: 28px 28px;
    pointer-events: none; z-index: 0;
}
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: var(--p50); }
::-webkit-scrollbar-thumb { background: var(--p200); border-radius: 99px; }

/* ══ SIDEBAR ══ */
.sidebar {
    position: fixed; top: 0; left: 0;
    width: var(--sidebar-w); height: 100vh;
    background: var(--white);
    border-right: 1.5px solid var(--border);
    display: flex; flex-direction: column;
    z-index: 300; overflow: hidden;
}
.sidebar::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--p400), var(--v500), var(--p300), var(--p500));
    background-size: 200%; z-index: 1;
    animation: shimmer 3s linear infinite;
}
@keyframes shimmer { 0%{background-position:0%} 100%{background-position:200%} }

.sb-brand {
    display: flex; align-items: center; gap: 12px;
    padding: 0 18px; height: var(--topbar-h);
    border-bottom: 1.5px solid var(--border);
    text-decoration: none; flex-shrink: 0;
    transition: background var(--ease-plain);
    margin-top: 4px;
}
.sb-brand:hover { background: var(--p50); }
.brand-mark {
    width: 38px; height: 38px; border-radius: 13px;
    background: linear-gradient(135deg, var(--p500) 0%, var(--p400) 50%, var(--v500) 100%);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(232,50,138,0.4), 0 0 0 3px rgba(232,50,138,0.12);
    transition: transform var(--ease), box-shadow var(--ease);
}
.sb-brand:hover .brand-mark { transform: rotate(-8deg) scale(1.08); }
.brand-mark i { font-size: 18px; color: #fff; }
.brand-name { font-family: 'Quicksand', sans-serif; font-size: 16px; font-weight: 700; color: var(--text); white-space: nowrap; }
.brand-sub { font-size: 10px; font-weight: 600; color: var(--p500); letter-spacing: 0.8px; text-transform: uppercase; margin-top: 1px; }

.sb-owner {
    margin: 12px 12px 6px; padding: 12px 14px;
    background: linear-gradient(135deg, var(--p50), var(--v100));
    border: 1.5px solid var(--border); border-radius: var(--r-lg);
    display: flex; align-items: center; gap: 10px; flex-shrink: 0;
}
.owner-av {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, var(--p500), var(--v500));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Quicksand', sans-serif;
    font-size: 13px; font-weight: 700; color: #fff;
    flex-shrink: 0; position: relative;
    box-shadow: 0 3px 10px rgba(232,50,138,0.35);
}
.owner-av::after { content:''; position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:var(--g500); border:2px solid var(--white); }
.owner-name { font-size: 13.5px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.owner-role { font-size: 11px; color: var(--p500); font-weight: 600; }

.sb-nav { flex: 1; overflow-y: auto; padding: 6px 10px 10px; }
.nav-group-label { font-size: 9.5px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: var(--text3); padding: 14px 10px 4px; display: flex; align-items: center; gap: 6px; }
.nav-group-label::after { content:'✦'; font-size:7px; color:var(--p300); }

.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 11px; border-radius: var(--r-sm);
    text-decoration: none; color: var(--text2);
    font-size: 14px; font-weight: 600;
    transition: background var(--ease-plain), color var(--ease-plain), transform var(--ease-plain);
    margin-bottom: 2px; position: relative; white-space: nowrap;
}
.nav-item i { font-size: 17px; width: 19px; text-align: center; flex-shrink: 0; color: var(--text3); transition: color var(--ease-plain); }
.nav-item:hover { background: var(--p50); color: var(--p500); transform: translateX(2px); }
.nav-item:hover i { color: var(--p400); }
.nav-item.active { background: linear-gradient(135deg, var(--p500), var(--p400)); color: #fff; font-weight: 700; box-shadow: 0 4px 16px rgba(232,50,138,0.35); }
.nav-item.active i { color: rgba(255,255,255,0.9); }
.nav-item.active::after { content:''; position:absolute; right:10px; width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,0.6); }

.nav-pill { margin-left:auto; min-width:20px; height:20px; padding:0 6px; border-radius:99px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; color:#fff; flex-shrink:0; }
.pill-red    { background: var(--r500); }
.pill-orange { background: var(--o500); }
.pill-pink   { background: var(--p500); }
.nav-item.active .nav-pill { background: rgba(255,255,255,0.3); }

.sb-footer { padding: 10px 10px 14px; border-top: 1.5px solid var(--border); flex-shrink: 0; }
.nav-item.logout { color: var(--r700); }
.nav-item.logout i { color: var(--r500); }
.nav-item.logout:hover { background: var(--r100); color: var(--r700); transform: none; }

/* ══ TOPBAR ══ */
.topbar {
    position: fixed; top: 0; left: var(--sidebar-w); right: 0;
    height: var(--topbar-h);
    background: rgba(255,255,255,0.94);
    backdrop-filter: blur(12px);
    border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center;
    padding: 0 26px; z-index: 200; gap: 12px;
}
.topbar::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500)); background-size:200%; animation:shimmer 3s linear infinite; }

.tb-greeting { flex: 1; }
.tb-hello { font-family:'Quicksand',sans-serif; font-size:16px; font-weight:700; color:var(--text); }
.tb-sub { font-size:12px; color:var(--text3); font-weight:500; margin-top:1px; }

.tb-nav { display:flex; align-items:center; gap:2px; }
.tb-nav-item { display:flex; align-items:center; gap:5px; padding:7px 13px; border-radius:99px; font-size:13px; font-weight:600; color:var(--text2); text-decoration:none; transition:all var(--ease-plain); white-space:nowrap; border:1.5px solid transparent; }
.tb-nav-item i { font-size:14px; }
.tb-nav-item:hover { background:var(--p50); color:var(--p500); }
.tb-nav-item.active { background:var(--p50); color:var(--p500); border-color:var(--border2); }

.tb-divider { width:1px; height:24px; background:var(--border2); margin:0 4px; }
.tb-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }

.icon-btn { width:36px; height:36px; border-radius:10px; background:var(--p50); border:1.5px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none; color:var(--p500); font-size:16px; transition:all var(--ease); position:relative; }
.icon-btn:hover { background:var(--p100); transform:scale(1.08); }
.icon-btn .dot { position:absolute; top:4px; right:4px; width:8px; height:8px; border-radius:50%; background:var(--r500); border:2px solid var(--white); animation:blink 1.6s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.4} }

.date-pill { display:flex; align-items:center; gap:6px; background:var(--p50); border:1.5px solid var(--border); border-radius:99px; padding:7px 16px; font-size:12.5px; font-weight:600; color:var(--text2); }
.date-pill i { color:var(--p500); }

/* ══ MAIN ══ */
.main { margin-left:var(--sidebar-w); padding-top:var(--topbar-h); min-height:100vh; position:relative; z-index:1; }
.content { padding:28px 28px 70px; max-width:1360px; }

@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }

/* ── Page header ── */
.page-hd {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
    animation: fadeUp 0.3s ease both;
}
.page-title-wrap {}
.page-title {
    font-family: 'Quicksand', sans-serif;
    font-size: 22px; font-weight: 700; color: var(--text);
    display: flex; align-items: center; gap: 10px;
}
.page-title-icon {
    width: 42px; height: 42px; border-radius: 13px;
    background: linear-gradient(135deg, var(--p500), var(--p400));
    display: flex; align-items: center; justify-content: center;
    font-size: 19px; color: #fff;
    box-shadow: 0 4px 14px rgba(232,50,138,0.35);
}
.page-sub { font-size: 13px; color: var(--text3); font-weight: 500; margin-top: 4px; margin-left: 52px; }

/* ── Stat cards ── */
.stat-row {
    display: grid; grid-template-columns: repeat(4,1fr);
    gap: 14px; margin-bottom: 24px;
}
.stat-card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--r-lg);
    padding: 18px 20px;
    display: flex; align-items: center; gap: 14px;
    cursor: pointer;
    transition: transform var(--ease), box-shadow var(--ease), border-color var(--ease-plain);
    animation: fadeUp 0.32s ease both;
    position: relative; overflow: hidden;
}
.stat-card:nth-child(1){animation-delay:0.04s}
.stat-card:nth-child(2){animation-delay:0.08s}
.stat-card:nth-child(3){animation-delay:0.12s}
.stat-card:nth-child(4){animation-delay:0.16s}
.stat-card::before { content:''; position:absolute; right:-14px; bottom:-14px; width:60px; height:60px; border-radius:50%; opacity:0.07; }
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(232,50,138,0.12); }
.stat-card.active { border-color: var(--p400); box-shadow: 0 0 0 3px rgba(232,50,138,0.14), 0 8px 24px rgba(232,50,138,0.15); }
.sc-icon { width: 44px; height: 44px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.sc-num  { font-family:'Quicksand',sans-serif; font-size: 28px; font-weight: 700; line-height: 1; }
.sc-lbl  { font-size: 11.5px; color: var(--text3); font-weight: 600; margin-top: 2px; }

.sc-all    .sc-icon { background:var(--p50);  color:var(--p500); } .sc-all    .sc-num { color:var(--p500); } .sc-all::before    { background:var(--p500); }
.sc-wait   .sc-icon { background:var(--a100); color:var(--a500); } .sc-wait   .sc-num { color:var(--a700); } .sc-wait::before   { background:var(--a500); }
.sc-ok     .sc-icon { background:var(--g100); color:var(--g500); } .sc-ok     .sc-num { color:var(--g700); } .sc-ok::before     { background:var(--g500); }
.sc-reject .sc-icon { background:var(--r100); color:var(--r500); } .sc-reject .sc-num { color:var(--r700); } .sc-reject::before { background:var(--r500); }

/* ── Table card ── */
.tbl-wrap {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--r-xl);
    overflow: hidden;
    animation: fadeUp 0.38s ease 0.18s both;
}
.tbl-header {
    padding: 18px 24px 16px;
    border-bottom: 1.5px solid var(--border);
    background: linear-gradient(135deg, var(--p50), var(--white));
    display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
}
.tbl-title { font-family:'Quicksand',sans-serif; font-size:15px; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
.tbl-title i { color:var(--p500); font-size:17px; }

/* Filter pills */
.filter-pills { display:flex; gap:8px; flex-wrap:wrap; }
.fpill {
    display:flex; align-items:center; gap:6px;
    background:var(--p50); border:1.5px solid var(--border);
    border-radius:99px; padding:6px 16px;
    font-size:12.5px; font-weight:700; color:var(--text2);
    cursor:pointer; transition:all var(--ease-plain); user-select:none;
}
.fpill:hover { border-color:var(--border2); color:var(--p500); background:var(--p50); }
.fpill.active { background:linear-gradient(135deg,var(--p500),var(--p400)); color:#fff; border-color:var(--p500); box-shadow:0 4px 14px rgba(232,50,138,0.3); }
.fpill .cnt { background:rgba(255,255,255,0.28); border-radius:99px; padding:0 6px; font-size:10px; }
.fpill:not(.active) .cnt { background:var(--p500); color:#fff; }

/* Table */
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th {
    padding:12px 18px; font-size:11px; font-weight:800;
    color:var(--text3); text-align:left;
    background:var(--p50); border-bottom:1.5px solid var(--border);
    text-transform:uppercase; letter-spacing:0.5px; white-space:nowrap;
}
.data-table tbody td {
    padding:14px 18px; border-bottom:1px solid rgba(232,50,138,0.06);
    font-size:14px; vertical-align:middle;
}
.data-table tbody tr:last-child td { border-bottom:none; }
.data-table tbody tr:hover td { background:rgba(255,240,245,0.7); }

/* ID tag */
.id-tag { display:inline-flex; align-items:center; background:var(--p50); color:var(--p600); border:1px solid var(--border2); border-radius:8px; padding:3px 10px; font-size:12px; font-weight:800; }

/* Metode tag */
.metode-tag { background:var(--b100); color:var(--b700); border-radius:8px; padding:3px 10px; font-size:12px; font-weight:700; }

/* Badges */
.badge { display:inline-flex; align-items:center; gap:5px; padding:5px 13px; border-radius:99px; font-size:12px; font-weight:700; }
.badge i { font-size:11px; }
.badge-g { background:var(--g100); color:var(--g700); }
.badge-y { background:var(--a100); color:var(--a700); }
.badge-r { background:var(--r100); color:var(--r700); }

/* Bukti thumb */
.bukti-thumb {
    width:56px; height:56px; object-fit:cover;
    border-radius:12px; border:2px solid var(--border);
    cursor:pointer; transition:all var(--ease);
    display:block;
}
.bukti-thumb:hover { border-color:var(--p400); transform:scale(1.08); box-shadow:0 6px 18px rgba(232,50,138,0.25); }
.no-bukti {
    width:56px; height:56px; border-radius:12px;
    background:var(--p50); border:2px dashed var(--border2);
    display:flex; align-items:center; justify-content:center;
    font-size:18px; color:var(--text3);
}

/* Action buttons */
.btn-ok {
    display:inline-flex; align-items:center; gap:5px;
    background:linear-gradient(135deg,var(--g500),#16a34a); color:#fff;
    border:none; border-radius:10px; padding:7px 14px;
    font-size:12.5px; font-weight:700; cursor:pointer;
    text-decoration:none; transition:all var(--ease);
    font-family:'Nunito',sans-serif;
    box-shadow:0 3px 10px rgba(34,197,94,0.3);
}
.btn-ok:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(34,197,94,0.4); color:#fff; }
.btn-reject {
    display:inline-flex; align-items:center; gap:5px;
    background:var(--r100); color:var(--r700);
    border:1.5px solid rgba(239,68,68,0.25); border-radius:10px; padding:7px 14px;
    font-size:12.5px; font-weight:700; cursor:pointer;
    text-decoration:none; transition:all var(--ease);
    font-family:'Nunito',sans-serif;
}
.btn-reject:hover { background:#fecaca; color:#7f1d1d; transform:translateY(-2px); }

/* Empty state */
.empty-state { text-align:center; padding:52px 20px; }
.empty-state i { font-size:48px; color:var(--p200); margin-bottom:12px; display:block; }
.empty-state p { color:var(--text3); font-size:14px; font-weight:600; }

/* ── Modal ── */
.modal-overlay {
    position:fixed; inset:0;
    background:rgba(61,26,40,0.65);
    display:none; align-items:center; justify-content:center;
    z-index:9999; padding:20px;
    backdrop-filter:blur(6px);
}
.modal-overlay.show { display:flex; }
.modal-box {
    background:var(--white);
    border-radius:var(--r-xl); padding:24px;
    max-width:500px; width:100%;
    box-shadow:0 30px 80px rgba(61,26,40,0.25);
    border:1.5px solid var(--border);
    animation:popIn 0.22s ease;
}
@keyframes popIn { from{opacity:0;transform:scale(0.93)} to{opacity:1;transform:scale(1)} }
.modal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.modal-title { font-family:'Quicksand',sans-serif; font-size:15px; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
.modal-title i { color:var(--p500); }
.modal-close { width:32px; height:32px; border-radius:50%; background:var(--p50); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--text2); transition:all var(--ease-plain); }
.modal-close:hover { background:var(--p100); color:var(--p500); }
.modal-img { width:100%; border-radius:var(--r-md); display:block; border:2px solid var(--border); }

@keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(0.85);opacity:0.6} }
.pulse { animation:pulse 1.8s ease-in-out infinite; }

@media (max-width:1200px) { .stat-row { grid-template-columns:repeat(2,1fr); } }
@media (max-width:900px) {
    .sidebar { transform:translateX(-100%); }
    .topbar { left:0; }
    .main { margin-left:0; }
    .stat-row { grid-template-columns:repeat(2,1fr); }
}
</style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand" title="Kembali ke Dashboard">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div>
            <div class="brand-name">Konveksi Apps</div>
            <div class="brand-sub">Panel Owner</div>
        </div>
    </a>

    <div class="sb-owner">
        <div class="owner-av"><?= $inisial ?></div>
        <div style="overflow:hidden;min-width:0">
            <div class="owner-name"><?= htmlspecialchars($nama_owner) ?></div>
            <div class="owner-role">✨ Owner · Administrator</div>
        </div>
    </div>

    <nav class="sb-nav">
        <a class="nav-item" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>

        <div class="nav-group-label">Manajemen Data</div>
        <a class="nav-item" href="kelola_produk.php"><i class="bi bi-box-seam"></i> Produk</a>
        <a class="nav-item" href="kelola_bahan.php">
            <i class="bi bi-basket"></i> Bahan Baku
            <?php if ($stok_kritis > 0): ?><span class="nav-pill pill-orange pulse"><?= $stok_kritis ?></span><?php endif; ?>
        </a>
        <a class="nav-item" href="kelola_aset.php">
            <i class="bi bi-building-gear"></i> Aset &amp; Inventaris
            <?php if ($aset_rusak > 0): ?><span class="nav-pill pill-orange pulse"><?= $aset_rusak ?></span><?php endif; ?>
        </a>
        <a class="nav-item" href="data_penjahit.php"><i class="bi bi-people"></i> Data Penjahit</a>
        <a class="nav-item" href="pelanggan.php"><i class="bi bi-person-badge"></i> Data Pelanggan</a>
        <a class="nav-item" href="supplier.php"><i class="bi bi-truck"></i> Data Supplier</a>

        <div class="nav-group-label">Operasional</div>
        <a class="nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi Aktif</a>
        <a class="nav-item" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="nav-item active" href="konfirmasi_pembayaran.php">
            <i class="bi bi-credit-card-2-front"></i> Konfirmasi Bayar
            <?php if ($notif > 0): ?><span class="nav-pill pill-pink pulse"><?= $notif ?></span><?php endif; ?>
        </a>
        <a class="nav-item" href="chat.php">
            <i class="bi bi-chat-dots-fill"></i> Inbox Chat
            <?php if ($notif_chat > 0): ?><span class="nav-pill pill-red pulse"><?= $notif_chat ?></span><?php endif; ?>
        </a>

        <div class="nav-group-label">Laporan</div>
        <a class="nav-item" href="laporan.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Keuangan</a>
    </nav>

    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Konfirmasi Pembayaran 💳</div>
        <div class="tb-sub">Periksa bukti bayar pelanggan, lalu konfirmasi atau tolak</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi</a>
        <a class="tb-nav-item" href="laporan.php"><i class="bi bi-bar-chart-line"></i> Laporan</a>
    </nav>
    <div class="tb-divider"></div>
    <div class="tb-actions">
        <a href="#" class="icon-btn">
            <i class="bi bi-bell-fill"></i>
            <?php if ($total_notif > 0): ?><span class="dot"></span><?php endif; ?>
        </a>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- Page header -->
    <div class="page-hd">
        <div class="page-title-wrap">
            <div class="page-title">
                <div class="page-title-icon"><i class="bi bi-credit-card-2-front-fill"></i></div>
                Konfirmasi Pembayaran
            </div>
            <div class="page-sub">Cek bukti pembayaran dari pelanggan &amp; verifikasi satu per satu 🌸</div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-row">
        <div class="stat-card sc-all active" onclick="filterTabel('semua', this)" id="card-semua">
            <div class="sc-icon"><i class="bi bi-list-ul"></i></div>
            <div>
                <div class="sc-num"><?= $stat_total ?></div>
                <div class="sc-lbl">Total Masuk</div>
            </div>
        </div>
        <div class="stat-card sc-wait" onclick="filterTabel('Menunggu Konfirmasi', this)" id="card-menunggu">
            <div class="sc-icon"><i class="bi bi-clock-fill"></i></div>
            <div>
                <div class="sc-num"><?= $stat_menunggu ?></div>
                <div class="sc-lbl">Menunggu</div>
            </div>
        </div>
        <div class="stat-card sc-ok" onclick="filterTabel('Dikonfirmasi', this)" id="card-dikonfirmasi">
            <div class="sc-icon"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="sc-num"><?= $stat_dikonfirmasi ?></div>
                <div class="sc-lbl">Dikonfirmasi</div>
            </div>
        </div>
        <div class="stat-card sc-reject" onclick="filterTabel('Ditolak', this)" id="card-ditolak">
            <div class="sc-icon"><i class="bi bi-x-circle-fill"></i></div>
            <div>
                <div class="sc-num"><?= $stat_ditolak ?></div>
                <div class="sc-lbl">Ditolak</div>
            </div>
        </div>
    </div>

    <!-- Table card -->
    <div class="tbl-wrap">
        <div class="tbl-header">
            <div class="tbl-title"><i class="bi bi-bag-heart-fill"></i> Daftar Pembayaran</div>
            <div class="filter-pills">
                <div class="fpill active" onclick="filterTabel('semua', this)">
                    <i class="bi bi-grid-3x3-gap"></i> Semua
                </div>
                <div class="fpill" onclick="filterTabel('Menunggu Konfirmasi', this)">
                    <i class="bi bi-clock"></i> Menunggu
                    <?php if ($stat_menunggu > 0): ?><span class="cnt"><?= $stat_menunggu ?></span><?php endif; ?>
                </div>
                <div class="fpill" onclick="filterTabel('Dikonfirmasi', this)">
                    <i class="bi bi-check-circle"></i> Dikonfirmasi
                </div>
                <div class="fpill" onclick="filterTabel('Ditolak', this)">
                    <i class="bi bi-x-circle"></i> Ditolak
                </div>
            </div>
        </div>

        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Pesanan</th>
                    <th>Pelanggan</th>
                    <th>Total Harga</th>
                    <th>Metode</th>
                    <th>Bukti Bayar</th>
                    <th>Waktu Pesan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tbody-data">
            <?php
            $query = mysqli_query($koneksi, "
                SELECT p.*, pl.NAMA_PELANGGAN
                FROM pesanan p
                JOIN pelanggan pl ON p.ID_PELANGGAN = pl.ID_PELANGGAN
                WHERE p.STATUS_BAYAR IN ('Menunggu Konfirmasi','Dikonfirmasi','Ditolak')
                ORDER BY
                    CASE p.STATUS_BAYAR
                        WHEN 'Menunggu Konfirmasi' THEN 1
                        WHEN 'Ditolak' THEN 2
                        ELSE 3
                    END,
                    p.WAKTU_PESAN DESC
            ");

            $ada_data = false;
            while ($row = mysqli_fetch_assoc($query)):
                $ada_data = true;
                $status_bayar = $row['STATUS_BAYAR'] ?? '-';
                $metode = strtoupper($row['METODE_BAYAR'] ?? '-');

                if ($status_bayar == 'Dikonfirmasi') {
                    $badge_class = 'badge-g'; $badge_icon = 'check-circle-fill';
                } elseif ($status_bayar == 'Ditolak') {
                    $badge_class = 'badge-r'; $badge_icon = 'x-circle-fill';
                } else {
                    $badge_class = 'badge-y'; $badge_icon = 'clock-fill';
                }

                $bukti_path = '../assets/bukti_bayar/' . $row['BUKTI_BAYAR'];
                $ada_bukti  = !empty($row['BUKTI_BAYAR']) && file_exists($bukti_path);
            ?>
            <tr data-status="<?= htmlspecialchars($status_bayar) ?>">
                <td><span class="id-tag"><?= htmlspecialchars($row['ID_PESANAN']) ?></span></td>
                <td style="font-weight:700"><?= htmlspecialchars($row['NAMA_PELANGGAN']) ?></td>
                <td style="font-weight:800;color:var(--p600)">Rp <?= number_format($row['TOTAL_HARGA']) ?></td>
                <td>
                    <?php if ($metode && $metode != '-'): ?>
                        <span class="metode-tag"><?= $metode ?></span>
                    <?php else: ?>
                        <span style="color:var(--text3);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($ada_bukti): ?>
                        <img src="<?= $bukti_path ?>" class="bukti-thumb" alt="Bukti Bayar"
                             onclick="lihatBukti('<?= $bukti_path ?>', '<?= htmlspecialchars($row['ID_PESANAN']) ?>')">
                    <?php elseif (!empty($row['BUKTI_BAYAR'])): ?>
                        <div class="no-bukti" title="File tidak ditemukan"><i class="bi bi-image"></i></div>
                    <?php else: ?>
                        <div class="no-bukti" title="Belum upload bukti"><i class="bi bi-dash-lg"></i></div>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text2);font-size:12.5px;font-weight:600">
                    <?= $row['WAKTU_PESAN'] ? date('d/m/Y H:i', strtotime($row['WAKTU_PESAN'])) : '—' ?>
                </td>
                <td>
                    <span class="badge <?= $badge_class ?>">
                        <i class="bi bi-<?= $badge_icon ?>"></i>
                        <?= htmlspecialchars($status_bayar) ?>
                    </span>
                </td>
                <td>
                    <?php if ($status_bayar == 'Menunggu Konfirmasi'): ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <a href="konfirmasi_pembayaran.php?aksi=konfirmasi&id=<?= urlencode($row['ID_PESANAN']) ?>"
                           class="btn-ok"
                           onclick="return confirm('Konfirmasi pembayaran <?= htmlspecialchars($row['ID_PESANAN']) ?>?')">
                            <i class="bi bi-check-lg"></i> Konfirmasi
                        </a>
                        <a href="konfirmasi_pembayaran.php?aksi=tolak&id=<?= urlencode($row['ID_PESANAN']) ?>"
                           class="btn-reject"
                           onclick="return confirm('Tolak pembayaran ini?')">
                            <i class="bi bi-x-lg"></i> Tolak
                        </a>
                    </div>
                    <?php else: ?>
                        <span style="color:var(--text3);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if (!$ada_data): ?>
            <tr id="row-empty-all">
                <td colspan="8">
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>Belum ada data pembayaran masuk 🌸</p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <div id="empty-filtered" style="display:none">
            <div class="empty-state">
                <i class="bi bi-funnel"></i>
                <p>Tidak ada data untuk filter ini.</p>
            </div>
        </div>
    </div>

</div>
</main>

<!-- Modal bukti -->
<div class="modal-overlay" id="modalOverlay" onclick="tutupModal(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-head">
            <span class="modal-title"><i class="bi bi-image"></i> <span id="modalJudul">Bukti Pembayaran</span></span>
            <button class="modal-close" onclick="tutupModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <img id="modalGambar" src="" class="modal-img" alt="Bukti Bayar">
    </div>
</div>

<script>
function lihatBukti(src, id) {
    document.getElementById('modalGambar').src = src;
    document.getElementById('modalJudul').textContent = 'Bukti Pembayaran — ' + id;
    document.getElementById('modalOverlay').classList.add('show');
}
function tutupModal(e) {
    if (!e || e.target === document.getElementById('modalOverlay')) {
        document.getElementById('modalOverlay').classList.remove('show');
    }
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') tutupModal(); });

function filterTabel(status, el) {
    const rows = document.querySelectorAll('#tbody-data tr[data-status]');
    let visCount = 0;
    rows.forEach(row => {
        const match = status === 'semua' || row.getAttribute('data-status') === status;
        row.style.display = match ? '' : 'none';
        if (match) visCount++;
    });
    document.getElementById('empty-filtered').style.display = visCount === 0 ? 'block' : 'none';

    // Sync pills
    document.querySelectorAll('.fpill').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));

    if (el) {
        if (el.classList.contains('fpill')) {
            el.classList.add('active');
        } else if (el.classList.contains('stat-card')) {
            el.classList.add('active');
            // Sync pill yang sesuai
            const map = { 'semua':'semua','Menunggu Konfirmasi':'Menunggu Konfirmasi','Dikonfirmasi':'Dikonfirmasi','Ditolak':'Ditolak' };
            document.querySelectorAll('.fpill').forEach(p => {
                if (
                    (status === 'semua' && p.textContent.trim().startsWith('Semua')) ||
                    p.textContent.includes(status.split(' ')[0])
                ) p.classList.add('active');
            });
        }
    }
}
</script>
</body>
</html>