<?php
session_start();
include "../config/koneksi.php";
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'pelanggan') {
    header("Location: ../index.php"); exit;
}
$id_pelanggan = $_SESSION['id'];
$nama_user    = $_SESSION['user'];
$initials     = strtoupper(substr($nama_user, 0, 2));

$total_pesan   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan'"))['n'];
$total_pending = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Pending'"))['n'];
$total_proses  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Proses'"))['n'];
$total_selesai = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Selesai'"))['n'];

$q_status = mysqli_query($koneksi, "SELECT p.*, pr.NAMA_PRODUK, dp.JUMLAH 
    FROM pesanan p 
    JOIN detail_pesanan dp ON p.ID_PESANAN = dp.ID_PESANAN 
    JOIN produk pr ON dp.ID_PRODUK = pr.ID_PRODUK 
    WHERE p.ID_PELANGGAN = '$id_pelanggan'
    ORDER BY p.WAKTU_PESAN DESC");
$pesanan_list = [];
while ($s = mysqli_fetch_assoc($q_status)) $pesanan_list[] = $s;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Status Pesanan 🛍️ | Konveksi Apps</title>
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
    --text:    #3d1a28;
    --text2:   #7d4460;
    --text3:   #b07090;
    --border:  rgba(232,50,138,0.13);
    --border2: rgba(232,50,138,0.24);
    --sidebar-w: 240px;
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
.brand-name { font-family: 'Quicksand', sans-serif; font-size: 15px; font-weight: 700; color: var(--text); }
.brand-sub { font-size: 10px; font-weight: 600; color: var(--p500); letter-spacing: 0.8px; text-transform: uppercase; margin-top: 1px; }

/* Owner card */
.sb-user {
    margin: 12px 12px 6px; padding: 12px 14px;
    background: linear-gradient(135deg, var(--p50), var(--v100));
    border: 1.5px solid var(--border); border-radius: var(--r-lg);
    display: flex; align-items: center; gap: 10px; flex-shrink: 0;
}
.user-av {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, var(--p500), var(--v500));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Quicksand', sans-serif;
    font-size: 13px; font-weight: 700; color: #fff;
    flex-shrink: 0; position: relative;
    box-shadow: 0 3px 10px rgba(232,50,138,0.35);
}
.user-av::after { content:''; position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:var(--g500); border:2px solid var(--white); }
.user-name { font-size: 13px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role { font-size: 11px; color: var(--p500); font-weight: 600; }

/* Mini stats in sidebar */
.sb-mini-stats {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 6px; margin: 0 12px 6px; flex-shrink: 0;
}
.sb-mini-stat {
    background: var(--white); border: 1.5px solid var(--border);
    border-radius: var(--r-sm); padding: 8px 10px; text-align: center;
}
.sb-mini-n { font-family: 'Quicksand', sans-serif; font-size: 20px; font-weight: 700; color: var(--text); line-height: 1; }
.sb-mini-l { font-size: 10px; color: var(--text3); font-weight: 600; margin-top: 2px; }

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
.pill-new  { background: var(--v500); }
.pill-pink { background: var(--p500); }
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
.tb-nav-item:hover { background:var(--p50); color:var(--p500); }
.tb-nav-item.active { background:var(--p50); color:var(--p500); border-color:var(--border2); }

.tb-divider { width:1px; height:24px; background:var(--border2); margin:0 4px; }
.tb-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }

.user-chip {
    display:flex; align-items:center; gap:8px;
    background:var(--p50); border:1.5px solid var(--border);
    border-radius:99px; padding:5px 14px 5px 5px;
}
.chip-av {
    width:30px; height:30px; border-radius:50%;
    background:linear-gradient(135deg,var(--p500),var(--v500));
    display:flex; align-items:center; justify-content:center;
    font-size:11px; font-weight:700; color:#fff;
}
.chip-name { font-size:12.5px; font-weight:700; color:var(--text); }

.date-pill { display:flex; align-items:center; gap:6px; background:var(--p50); border:1.5px solid var(--border); border-radius:99px; padding:7px 16px; font-size:12.5px; font-weight:600; color:var(--text2); }
.date-pill i { color:var(--p500); }

/* ══ MAIN ══ */
.main { margin-left:var(--sidebar-w); padding-top:var(--topbar-h); min-height:100vh; position:relative; z-index:1; }
.content { padding:28px 28px 100px; max-width:1000px; }

@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }

/* Page header */
.page-hd { margin-bottom:24px; animation:fadeUp 0.3s ease both; }
.page-title-row { display:flex; align-items:center; gap:12px; }
.page-title-icon {
    width:44px; height:44px; border-radius:14px;
    background:linear-gradient(135deg,var(--p500),var(--p400));
    display:flex; align-items:center; justify-content:center;
    font-size:20px; color:#fff;
    box-shadow:0 4px 14px rgba(232,50,138,0.35);
}
.page-title { font-family:'Quicksand',sans-serif; font-size:22px; font-weight:700; color:var(--text); }
.page-sub { font-size:13px; color:var(--text3); font-weight:500; margin-top:4px; margin-left:56px; }

/* Stat row */
.stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
.stat-card {
    background:var(--white); border:1.5px solid var(--border);
    border-radius:var(--r-lg); padding:18px 20px;
    display:flex; align-items:center; gap:14px;
    transition:transform var(--ease), box-shadow var(--ease);
    animation:fadeUp 0.32s ease both; position:relative; overflow:hidden;
}
.stat-card::before { content:''; position:absolute; right:-14px; bottom:-14px; width:60px; height:60px; border-radius:50%; opacity:0.07; }
.stat-card:hover { transform:translateY(-4px); box-shadow:0 14px 36px rgba(232,50,138,0.12); }
.stat-card:nth-child(1){animation-delay:0.04s} .stat-card:nth-child(2){animation-delay:0.08s} .stat-card:nth-child(3){animation-delay:0.12s}
.sc-icon { width:44px; height:44px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.sc-num  { font-family:'Quicksand',sans-serif; font-size:28px; font-weight:700; line-height:1; }
.sc-lbl  { font-size:11.5px; color:var(--text3); font-weight:600; margin-top:2px; }
.sc-orange .sc-icon { background:var(--o100); color:var(--o500); } .sc-orange .sc-num { color:var(--o700); } .sc-orange::before { background:var(--o500); }
.sc-blue   .sc-icon { background:var(--b100); color:var(--b500); } .sc-blue   .sc-num { color:var(--b700); } .sc-blue::before   { background:var(--b500); }
.sc-green  .sc-icon { background:var(--g100); color:var(--g500); } .sc-green  .sc-num { color:var(--g700); } .sc-green::before  { background:var(--g500); }

/* Filter pills */
.filter-pills { display:flex; gap:8px; margin-bottom:22px; flex-wrap:wrap; }
.fpill {
    display:flex; align-items:center; gap:6px;
    background:var(--p50); border:1.5px solid var(--border);
    border-radius:99px; padding:7px 18px;
    font-size:12.5px; font-weight:700; color:var(--text2);
    cursor:pointer; transition:all var(--ease-plain); user-select:none;
}
.fpill:hover { border-color:var(--border2); color:var(--p500); }
.fpill.active { background:linear-gradient(135deg,var(--p500),var(--p400)); color:#fff; border-color:var(--p500); box-shadow:0 4px 14px rgba(232,50,138,0.3); }

/* Pesanan card */
.pesanan-card {
    background:var(--white); border:1.5px solid var(--border);
    border-radius:var(--r-xl); padding:22px 24px;
    margin-bottom:16px;
    transition:transform var(--ease), box-shadow var(--ease), border-color var(--ease-plain);
    position:relative; overflow:hidden;
    animation:fadeUp 0.35s ease both;
}
.pesanan-card:hover { transform:translateY(-4px); box-shadow:0 16px 42px rgba(232,50,138,0.12); border-color:var(--border2); }

/* Left accent bar */
.pesanan-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:5px; border-radius:var(--r-xl) 0 0 var(--r-xl); }
.pesanan-card.pending::before { background:linear-gradient(180deg,var(--o500),#fb923c); }
.pesanan-card.proses::before  { background:linear-gradient(180deg,var(--b500),#60a5fa); }
.pesanan-card.selesai::before { background:linear-gradient(180deg,var(--g500),#4ade80); }

.pesanan-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; }
.pesanan-id   { font-size:11px; font-weight:700; color:var(--text3); margin-bottom:3px; letter-spacing:0.3px; }
.pesanan-nama { font-size:16px; font-weight:800; color:var(--text); }
.pesanan-tgl  { font-size:12px; color:var(--text3); font-weight:500; margin-top:3px; }

/* Badges */
.badge { display:inline-flex; align-items:center; gap:5px; padding:5px 14px; border-radius:99px; font-size:12px; font-weight:700; }
.badge i { font-size:11px; }
.badge-pending { background:var(--o100); color:var(--o700); border:1px solid rgba(249,115,22,0.2); }
.badge-proses  { background:var(--b100); color:var(--b700); border:1px solid rgba(59,130,246,0.2); }
.badge-selesai { background:var(--g100); color:var(--g700); border:1px solid rgba(34,197,94,0.2); }

/* Stepper */
.stepper { display:flex; align-items:flex-start; gap:0; margin:0 0 18px; }
.step-item { display:flex; flex-direction:column; align-items:center; flex:1; position:relative; }
.step-item:not(:last-child)::after {
    content:''; position:absolute; top:14px; left:50%; right:-50%;
    height:2px; background:var(--border); z-index:0; transition:background var(--ease-plain);
}
.step-item.done:not(:last-child)::after { background:linear-gradient(90deg,var(--p500),var(--p300)); }
.step-circle {
    width:28px; height:28px; border-radius:50%;
    background:var(--bg); color:var(--text3);
    display:flex; align-items:center; justify-content:center;
    font-size:12px; font-weight:700; z-index:1;
    border:2px solid var(--border); transition:all var(--ease);
}
.step-item.done .step-circle {
    background:linear-gradient(135deg,var(--p500),var(--p400)); color:#fff;
    border-color:var(--p400); box-shadow:0 0 0 3px rgba(232,50,138,0.15);
}
.step-item.active .step-circle {
    background:var(--white); color:var(--p500);
    border-color:var(--p500); border-width:2.5px;
    box-shadow:0 0 0 4px rgba(232,50,138,0.12);
}
.step-label { font-size:10px; font-weight:700; color:var(--text3); margin-top:6px; text-align:center; }
.step-item.done .step-label,
.step-item.active .step-label { color:var(--p600); }

/* Footer */
.pesanan-footer {
    display:flex; align-items:center; justify-content:space-between;
    padding-top:14px; border-top:1.5px solid var(--border);
}
.pesanan-meta { font-size:12.5px; color:var(--text3); font-weight:600; }
.pesanan-meta span { color:var(--text); font-weight:800; }
.pesanan-harga { font-family:'Quicksand',sans-serif; font-size:16px; font-weight:700; color:var(--p600); }

.btn-bayar {
    display:inline-flex; align-items:center; gap:6px;
    background:linear-gradient(135deg,var(--p500),var(--p400)); color:#fff;
    border:none; border-radius:99px; padding:9px 20px;
    font-size:13px; font-weight:700; text-decoration:none;
    transition:all var(--ease); font-family:'Nunito',sans-serif;
    box-shadow:0 4px 14px rgba(232,50,138,0.35);
}
.btn-bayar:hover { transform:translateY(-2px) scale(1.03); box-shadow:0 8px 22px rgba(232,50,138,0.45); color:#fff; }
.btn-nota {
    display:inline-flex; align-items:center; gap:6px;
    background:var(--p50); color:var(--p600);
    border:1.5px solid var(--border2); border-radius:99px; padding:8px 18px;
    font-size:13px; font-weight:700; text-decoration:none;
    transition:all var(--ease); font-family:'Nunito',sans-serif;
}
.btn-nota:hover { background:var(--p500); color:#fff; border-color:var(--p500); transform:translateY(-2px); }

/* Empty state */
.empty-state { text-align:center; padding:60px 20px; background:var(--white); border:1.5px solid var(--border); border-radius:var(--r-xl); }
.empty-state i { font-size:52px; color:var(--p200); margin-bottom:14px; display:block; }
.empty-state h6 { font-family:'Quicksand',sans-serif; font-size:17px; font-weight:700; color:var(--text); margin-bottom:6px; }
.empty-state p { font-size:13.5px; color:var(--text3); margin-bottom:20px; }
.btn-empty {
    display:inline-flex; align-items:center; gap:8px;
    background:linear-gradient(135deg,var(--p500),var(--p400)); color:#fff;
    border:none; border-radius:99px; padding:12px 28px;
    font-size:14px; font-weight:700; text-decoration:none;
    box-shadow:0 6px 20px rgba(232,50,138,0.4); transition:all var(--ease);
    font-family:'Nunito',sans-serif;
}
.btn-empty:hover { transform:translateY(-2px) scale(1.04); color:#fff; box-shadow:0 10px 28px rgba(232,50,138,0.5); }

/* Floating buttons */
.float-chat {
    position:fixed; bottom:92px; right:28px; z-index:9999;
    width:52px; height:52px; border-radius:50%;
    background:linear-gradient(135deg,var(--p500),var(--p400));
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 6px 20px rgba(232,50,138,0.45);
    text-decoration:none; transition:all var(--ease);
}
.float-chat:hover { transform:scale(1.1) translateY(-2px); box-shadow:0 10px 28px rgba(232,50,138,0.55); }
.float-chat i { font-size:22px; color:#fff; }
.float-wa {
    position:fixed; bottom:28px; right:28px; z-index:9999;
    width:52px; height:52px; border-radius:50%;
    background:#25D366;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 6px 20px rgba(37,211,102,0.45);
    text-decoration:none; transition:all var(--ease);
}
.float-wa:hover { transform:scale(1.1) translateY(-2px); }
.float-wa i { font-size:26px; color:#fff; }

@keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(0.85);opacity:0.6} }
.pulse { animation:pulse 1.8s ease-in-out infinite; }

@media (max-width:900px) {
    .sidebar { transform:translateX(-100%); }
    .topbar { left:0; }
    .main { margin-left:0; }
    .stat-row { grid-template-columns:1fr 1fr; }
}
</style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div>
            <div class="brand-name">Konveksi Apps</div>
            <div class="brand-sub">Portal Pelanggan</div>
        </div>
    </a>

    <div class="sb-user">
        <div class="user-av"><?= $initials ?></div>
        <div style="overflow:hidden;min-width:0">
            <div class="user-name"><?= htmlspecialchars($nama_user) ?></div>
            <div class="user-role">🌸 Pelanggan</div>
        </div>
    </div>

    <div class="sb-mini-stats">
        <div class="sb-mini-stat">
            <div class="sb-mini-n"><?= $total_pesan ?></div>
            <div class="sb-mini-l">Pesanan</div>
        </div>
        <div class="sb-mini-stat">
            <div class="sb-mini-n"><?= $total_selesai ?></div>
            <div class="sb-mini-l">Selesai</div>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="nav-group-label">Menu Utama</div>
        <a class="nav-item" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
        <a class="nav-item" href="pesan.php">
            <i class="bi bi-cart-plus-fill"></i> Pesan Produk
            <span class="nav-pill pill-new">Baru</span>
        </a>
        <a class="nav-item active" href="status_pesanan.php">
            <i class="bi bi-clock-history"></i> Status Pesanan
            <?php if ($total_pending > 0): ?><span class="nav-pill pill-pink pulse"><?= $total_pending ?></span><?php endif; ?>
        </a>

        <div class="nav-group-label">Bantuan</div>
        <a class="nav-item" href="chat.php"><i class="bi bi-chat-dots-fill"></i> Live Chat</a>
    </nav>

    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</aside>

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Status Pesanan 🛍️</div>
        <div class="tb-sub">Pantau semua pesananmu secara real-time di sini</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="pesan.php"><i class="bi bi-cart-plus"></i> Pesan</a>
        <a class="tb-nav-item active" href="status_pesanan.php"><i class="bi bi-clock-history"></i> Status</a>
    </nav>
    <div class="tb-divider"></div>
    <div class="tb-actions">
        <div class="user-chip">
            <div class="chip-av"><?= $initials ?></div>
            <span class="chip-name"><?= htmlspecialchars($nama_user) ?></span>
        </div>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- Page header -->
    <div class="page-hd">
        <div class="page-title-row">
            <div class="page-title-icon"><i class="bi bi-clock-history"></i></div>
            <div class="page-title">Status Pesanan</div>
        </div>
        <div class="page-sub">Pantau perkembangan pesananmu satu per satu ya 🌸</div>
    </div>

    <!-- Stat cards -->
    <div class="stat-row">
        <div class="stat-card sc-orange">
            <div class="sc-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="sc-num"><?= $total_pending ?></div>
                <div class="sc-lbl">Menunggu Bayar</div>
            </div>
        </div>
        <div class="stat-card sc-blue">
            <div class="sc-icon"><i class="bi bi-arrow-repeat"></i></div>
            <div>
                <div class="sc-num"><?= $total_proses ?></div>
                <div class="sc-lbl">Sedang Diproses</div>
            </div>
        </div>
        <div class="stat-card sc-green">
            <div class="sc-icon"><i class="bi bi-patch-check-fill"></i></div>
            <div>
                <div class="sc-num"><?= $total_selesai ?></div>
                <div class="sc-lbl">Selesai</div>
            </div>
        </div>
    </div>

    <!-- Filter pills -->
    <div class="filter-pills">
        <div class="fpill active" onclick="filterPesanan('semua', this)">
            <i class="bi bi-grid-3x3-gap"></i> Semua (<?= count($pesanan_list) ?>)
        </div>
        <div class="fpill" onclick="filterPesanan('pending', this)">
            <i class="bi bi-hourglass-split"></i> Menunggu Bayar (<?= $total_pending ?>)
        </div>
        <div class="fpill" onclick="filterPesanan('proses', this)">
            <i class="bi bi-arrow-repeat"></i> Diproses (<?= $total_proses ?>)
        </div>
        <div class="fpill" onclick="filterPesanan('selesai', this)">
            <i class="bi bi-patch-check-fill"></i> Selesai (<?= $total_selesai ?>)
        </div>
    </div>

    <!-- Daftar pesanan -->
    <div id="pesananContainer">
        <?php if (count($pesanan_list) > 0): ?>
            <?php foreach ($pesanan_list as $idx => $s):
                $st = strtolower($s['STATUS']);
                if ($st == 'selesai')     { $badge_cls = 'badge-selesai'; $badge_icon = 'patch-check-fill'; }
                elseif ($st == 'proses')  { $badge_cls = 'badge-proses';  $badge_icon = 'arrow-repeat'; }
                else                      { $badge_cls = 'badge-pending'; $badge_icon = 'hourglass-split'; }

                $steps   = ['Pesan', 'Bayar', 'Proses', 'Selesai'];
                $step_no = ($st=='selesai') ? 4 : (($st=='proses') ? 3 : 1);
            ?>
            <div class="pesanan-card <?= $st ?>" data-status="<?= $st ?>" style="animation-delay:<?= 0.05 * $idx ?>s">
                <div class="pesanan-top">
                    <div>
                        <div class="pesanan-id"><i class="bi bi-hash"></i><?= htmlspecialchars($s['ID_PESANAN']) ?></div>
                        <div class="pesanan-nama"><?= htmlspecialchars($s['NAMA_PRODUK']) ?></div>
                        <div class="pesanan-tgl"><i class="bi bi-calendar3" style="margin-right:4px"></i><?= date('d M Y, H:i', strtotime($s['WAKTU_PESAN'])) ?></div>
                    </div>
                    <span class="badge <?= $badge_cls ?>"><i class="bi bi-<?= $badge_icon ?>"></i> <?= htmlspecialchars($s['STATUS']) ?></span>
                </div>

                <!-- Stepper -->
                <div class="stepper">
                    <?php foreach ($steps as $i => $label):
                        $sn  = $i + 1;
                        $cls = ($sn < $step_no) ? 'done' : (($sn == $step_no) ? 'active' : '');
                    ?>
                    <div class="step-item <?= $cls ?>">
                        <div class="step-circle">
                            <?php if ($sn < $step_no): ?>
                                <i class="bi bi-check-lg" style="font-size:11px"></i>
                            <?php else: ?>
                                <?= $sn ?>
                            <?php endif; ?>
                        </div>
                        <div class="step-label"><?= $label ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="pesanan-footer">
                    <div>
                        <div class="pesanan-meta">Jumlah: <span><?= htmlspecialchars($s['JUMLAH']) ?> pcs</span></div>
                        <?php if (!empty($s['TOTAL_HARGA'])): ?>
                        <div class="pesanan-harga">Rp <?= number_format($s['TOTAL_HARGA']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($s['STATUS'] == 'Pending'): ?>
                            <a href="bayar.php?id=<?= $s['ID_PESANAN'] ?>" class="btn-bayar">
                                <i class="bi bi-credit-card-fill"></i> Bayar Sekarang
                            </a>
                        <?php else: ?>
                            <a href="nota.php?id=<?= $s['ID_PESANAN'] ?>" class="btn-nota">
                                <i class="bi bi-receipt"></i> Lihat Nota
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h6>Belum Ada Pesanan</h6>
                <p>Kamu belum punya pesanan nih. Yuk buat pesanan pertamamu! 🌸</p>
                <a href="pesan.php" class="btn-empty">
                    <i class="bi bi-cart-plus-fill"></i> Pesan Sekarang
                </a>
            </div>
        <?php endif; ?>
    </div>

</div>
</main>

<!-- Floating buttons -->
<a class="float-chat" href="chat.php" title="Live Chat">
    <i class="bi bi-chat-dots-fill"></i>
</a>
<a class="float-wa" href="https://wa.me/62895414630496" target="_blank" title="WhatsApp">
    <i class="bi bi-whatsapp"></i>
</a>

<script>
function filterPesanan(status, el) {
    document.querySelectorAll('.fpill').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.pesanan-card').forEach(card => {
        card.style.display = (status === 'semua' || card.dataset.status === status) ? 'block' : 'none';
    });
}
</script>
</body>
</html>