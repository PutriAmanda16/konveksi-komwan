<?php
session_start();
include "../config/koneksi.php";
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') { header("Location: ../index.php"); exit; }


// Proses Upload Bukti Bayar
if(isset($_POST['bayar'])) {
    $id_prod   = mysqli_real_escape_string($koneksi, $_POST['id_produksi']);
    $nama_file = basename($_FILES['bukti']['name']);
    $tmp_file  = $_FILES['bukti']['tmp_name'];
    $tgl_bayar = date('Y-m-d H:i:s');

    $folder = "../assets/bukti_gaji/";
    if (!is_dir($folder)) mkdir($folder, 0755, true);
    move_uploaded_file($tmp_file, $folder . $nama_file);
    $id_gaji = 'GAJ-' . time() . rand(10,99);
    mysqli_query($koneksi, "INSERT INTO penggajian (ID_GAJI, ID_PRODUKSI, BUKTI_BAYAR, STATUS_TERIMA, TANGGAL_BAYAR) 
                        VALUES ('$id_gaji', '$id_prod', '$nama_file', 'Belum', '$tgl_bayar') 
                        ON DUPLICATE KEY UPDATE 
                            BUKTI_BAYAR    = '$nama_file',
                            STATUS_TERIMA  = 'Belum',
                            TANGGAL_BAYAR  = '$tgl_bayar'");

    echo "<script>alert('✅ Gaji berhasil dikirim! Penjahit akan menerima notifikasi untuk konfirmasi.'); window.location='penggajian.php';</script>";
}

// Stats
$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;
$aset_rusak  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM aset WHERE KONDISI_ASET IN ('Rusak','Perlu Perbaikan')"))['t'] ?? 0;
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$total_notif = $notif_bayar + $notif_chat + $stok_kritis + $aset_rusak;

$nama_owner = $_SESSION['user'];
$inisial    = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts   = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

// Summary penggajian
$total_lunas    = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM penggajian WHERE STATUS_TERIMA='Diterima'"))['t'] ?? 0;
$total_pending  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM penggajian WHERE STATUS_TERIMA='Belum' OR STATUS_TERIMA IS NULL"))['t'] ?? 0;
$total_nominal  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(TOTAL_UPAH) as t FROM penggajian WHERE STATUS_TERIMA='Diterima'"))['t'] ?? 0;
$ada_komplain   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM penggajian WHERE STATUS_KOMPLAIN='Menunggu'"))['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Penggajian 💸 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --p50:  #fff0f5; --p100: #ffd6e7; --p200: #ffadd0; --p300: #ff80b8;
    --p400: #f950a0; --p500: #e8328a; --p600: #cc1a73; --p700: #a8105d;
    --v100: #f3e8ff; --v300: #d8b4fe; --v500: #a855f7;
    --g100: #dcfce7; --g500: #22c55e; --g700: #15803d;
    --a100: #fef9c3; --a500: #eab308; --a700: #854d0e;
    --b100: #dbeafe; --b500: #3b82f6; --b700: #1d4ed8;
    --r100: #fee2e2; --r500: #ef4444; --r700: #991b1b;
    --o100: #ffedd5; --o500: #f97316; --o700: #9a3412;
    --white: #ffffff; --bg: #fff5f9; --bg2: #ffeef5;
    --text: #3d1a28; --text2: #7d4460; --text3: #b07090;
    --border: rgba(232,50,138,0.13); --border2: rgba(232,50,138,0.24);
    --sidebar-w: 256px; --topbar-h: 64px;
    --r-sm: 10px; --r-md: 16px; --r-lg: 22px; --r-xl: 28px;
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
.sb-brand:hover .brand-mark { transform: rotate(-8deg) scale(1.08); box-shadow: 0 8px 20px rgba(232,50,138,0.5), 0 0 0 4px rgba(232,50,138,0.15); }
.brand-mark i { font-size: 18px; color: #fff; }
.brand-name { font-family: 'Quicksand', sans-serif; font-size: 16px; font-weight: 700; color: var(--text); white-space: nowrap; letter-spacing: -0.2px; }
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
.anim { animation:fadeUp 0.35s ease both; }

/* ══ PAGE HEADER ══ */
.page-hd {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.page-title-wrap { display: flex; align-items: center; gap: 14px; }
.page-icon {
    width: 48px; height: 48px; border-radius: 16px;
    background: linear-gradient(135deg, var(--g500), #4ade80);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff;
    box-shadow: 0 6px 20px rgba(34,197,94,0.35);
}
.page-title { font-family: 'Quicksand', sans-serif; font-size: 22px; font-weight: 700; color: var(--text); }
.page-subtitle { font-size: 12.5px; color: var(--text3); font-weight: 500; margin-top: 1px; }

/* ══ SUMMARY CARDS ══ */
.summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
.sum-card {
    background: var(--white); border: 1.5px solid var(--border);
    border-radius: var(--r-lg); padding: 18px 20px;
    position: relative; overflow: hidden;
    transition: transform var(--ease), box-shadow var(--ease);
}
.sum-card:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(232,50,138,0.12); }
.sum-stripe { position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: var(--r-lg) var(--r-lg) 0 0; }
.sum-blob { position: absolute; right: -12px; bottom: -12px; width: 60px; height: 60px; border-radius: 50%; opacity: 0.07; }
.sum-ico { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 17px; margin-bottom: 10px; }
.sum-lbl { font-size: 11.5px; font-weight: 600; color: var(--text3); margin-bottom: 3px; }
.sum-val { font-family: 'Quicksand', sans-serif; font-size: 26px; font-weight: 700; line-height: 1.1; }
.sum-note { font-size: 11px; color: var(--text3); margin-top: 2px; }

.sv-green .sum-stripe { background: linear-gradient(90deg,var(--g500),#86efac); }
.sv-green .sum-blob   { background: var(--g500); }
.sv-green .sum-ico    { background: var(--g100); color: var(--g500); }
.sv-green .sum-val    { color: var(--g700); }
.sv-amber .sum-stripe { background: linear-gradient(90deg,var(--a500),#fcd34d); }
.sv-amber .sum-blob   { background: var(--a500); }
.sv-amber .sum-ico    { background: var(--a100); color: var(--a500); }
.sv-amber .sum-val    { color: var(--a700); }
.sv-pink .sum-stripe  { background: linear-gradient(90deg,var(--p500),var(--p300)); }
.sv-pink .sum-blob    { background: var(--p500); }
.sv-pink .sum-ico     { background: var(--p50); color: var(--p500); }
.sv-pink .sum-val     { color: var(--p600); }
.sv-red .sum-stripe   { background: linear-gradient(90deg,var(--r500),#fca5a5); }
.sv-red .sum-blob     { background: var(--r500); }
.sv-red .sum-ico      { background: var(--r100); color: var(--r500); }
.sv-red .sum-val      { color: var(--r700); }

/* ══ TABLE CARD ══ */
.tbl-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--r-xl); overflow: hidden; }
.tbl-hd {
    padding: 18px 24px; border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    background: linear-gradient(135deg, var(--p50), var(--white));
}
.tbl-title { font-family:'Quicksand',sans-serif; font-size:15px; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
.tbl-title i { color:var(--p500); }
.tbl-legend { display: flex; gap: 12px; }
.legend-dot { display: flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 600; color: var(--text3); }
.legend-dot::before { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
.ld-green::before  { background: var(--g500); }
.ld-blue::before   { background: var(--b500); }
.ld-amber::before  { background: var(--a500); }
.ld-red::before    { background: var(--r500); }

.data-table { width:100%; border-collapse:collapse; }
.data-table thead th {
    padding: 11px 16px; font-size: 11px; font-weight: 700;
    color: var(--text3); text-align: left;
    background: var(--p50); border-bottom: 1.5px solid var(--border);
    text-transform: uppercase; letter-spacing: 0.5px;
}
.data-table tbody td { padding: 13px 16px; border-bottom: 1px solid rgba(232,50,138,0.06); font-size: 14px; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr { transition: background var(--ease-plain); }
.data-table tbody tr:hover td { background: var(--p50); }

/* Row highlight for komplain */
.data-table tbody tr.row-komplain td { background: #fff5f5; }
.data-table tbody tr.row-komplain:hover td { background: #fee2e2; }

/* Badges */
.badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 99px;
    font-size: 12px; font-weight: 700; border: 1.5px solid transparent;
    white-space: nowrap;
}
.badge i { font-size: 11px; }
.badge-lunas   { background: var(--g100); color: var(--g700); border-color: rgba(34,197,94,0.25); }
.badge-bayar   { background: var(--b100); color: var(--b700); border-color: rgba(59,130,246,0.25); }
.badge-pending { background: var(--a100); color: var(--a700); border-color: rgba(234,179,8,0.25); }
.badge-tunggu  { background: var(--o100); color: var(--o700); border-color: rgba(249,115,22,0.25); }
.badge-komp    { background: var(--r100); color: var(--r700); border-color: rgba(239,68,68,0.25); }

.id-tag { display: inline-flex; align-items: center; background: var(--p50); color: var(--p600); border: 1px solid var(--border2); border-radius: 7px; padding: 3px 9px; font-size: 12px; font-weight: 700; }

.ts { font-size: 11px; color: var(--text3); display: block; margin-top: 3px; white-space: nowrap; }
.fw7 { font-weight: 700; }

/* Komplain box */
.komp-box {
    background: var(--r100); border: 1px solid rgba(239,68,68,0.2);
    border-radius: 8px; padding: 8px 10px; margin-top: 6px;
    font-size: 12px; color: #7f1d1d; max-width: 200px; line-height: 1.4;
}
.komp-link {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 99px;
    background: var(--r100); color: var(--r700);
    border: 1.5px solid rgba(239,68,68,0.25);
    font-size: 11.5px; font-weight: 700; text-decoration: none;
    margin-top: 5px; transition: all var(--ease-plain);
}
.komp-link:hover { background: var(--r500); color: #fff; }

/* Upload form */
.upload-form { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.file-inp {
    background: var(--p50); border: 1.5px dashed var(--border2);
    border-radius: var(--r-sm); padding: 5px 10px;
    font-size: 12px; font-family: 'Nunito', sans-serif;
    color: var(--text2); cursor: pointer;
    transition: border-color var(--ease-plain), background var(--ease-plain);
    max-width: 160px;
}
.file-inp:hover { border-color: var(--p400); background: var(--p100); }
.file-inp:focus { outline: none; border-color: var(--p500); }

.btn-bayar {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; border-radius: 99px;
    background: linear-gradient(135deg, var(--p500), var(--p400));
    color: #fff; border: none; font-family: 'Nunito', sans-serif;
    font-size: 13px; font-weight: 700; cursor: pointer;
    box-shadow: 0 4px 14px rgba(232,50,138,0.35);
    transition: all var(--ease); white-space: nowrap;
}
.btn-bayar:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(232,50,138,0.5); }
.btn-bayar:active { transform: none; }

.btn-nota {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 13px; border-radius: 99px;
    background: var(--v100); color: var(--v500);
    border: 1.5px solid rgba(168,85,247,0.25);
    font-size: 12px; font-weight: 700; text-decoration: none;
    transition: all var(--ease-plain); white-space: nowrap;
}
.btn-nota:hover { background: var(--v500); color: #fff; }

.selesai-badge { display: flex; align-items: center; gap: 6px; }

/* Penjahit name */
.penjahit-name { font-weight: 700; color: var(--text); }
.penjahit-id   { font-size: 11px; color: var(--text3); margin-top: 1px; }

/* Upah */
.upah-val { font-weight: 700; color: var(--g700); font-size: 14.5px; }

/* Empty state */
.empty-state { text-align: center; padding: 56px 20px; }
.empty-ico { font-size: 40px; color: var(--p200); margin-bottom: 10px; }
.empty-text { color: var(--text3); font-size: 14px; font-weight: 500; }

/* Section header */
.sec-hd { display:flex; align-items:center; justify-content:space-between; margin:0 0 16px; }
.sec-title { font-family:'Quicksand',sans-serif; font-size:15px; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
.sec-dot { width:8px; height:8px; border-radius:50%; background:linear-gradient(135deg,var(--p500),var(--v500)); display:inline-block; box-shadow:0 0 0 3px rgba(232,50,138,0.15); flex-shrink:0; }

@keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(0.85);opacity:0.6} }
.pulse { animation:pulse 1.8s ease-in-out infinite; }

@media (max-width:1280px) { .summary-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width:900px) {
    .sidebar { transform:translateX(-100%); }
    .topbar  { left:0; }
    .main    { margin-left:0; }
    .summary-grid { grid-template-columns: repeat(2,1fr); }
    .data-table { font-size: 13px; }
    .data-table thead th, .data-table tbody td { padding: 10px 11px; }
}
</style>
</head>
<body>

<!-- ════ SIDEBAR 🌸 ════ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
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
        <a class="nav-item active" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="nav-item" href="konfirmasi_pembayaran.php">
            <i class="bi bi-credit-card-2-front"></i> Konfirmasi Bayar
            <?php if ($notif_bayar > 0): ?><span class="nav-pill pill-pink pulse"><?= $notif_bayar ?></span><?php endif; ?>
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

<!-- ════ TOPBAR 🎀 ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Penggajian Penjahit 💸</div>
        <div class="tb-sub">Kelola upah & pantau konfirmasi penjahit</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi</a>
        <a class="tb-nav-item active" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="tb-nav-item" href="laporan.php"><i class="bi bi-bar-chart-line"></i> Laporan</a>
    </nav>
    <div class="tb-divider"></div>
    <div class="tb-actions">
        <a href="<?= $total_notif > 0 ? 'konfirmasi_pembayaran.php' : '#' ?>" class="icon-btn">
            <i class="bi bi-bell-fill"></i>
            <?php if ($total_notif > 0): ?><span class="dot"></span><?php endif; ?>
        </a>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-hd anim">
        <div class="page-title-wrap">
            <div class="page-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="page-title">Sistem Penggajian 💸</div>
                <div class="page-subtitle">Bayar upah penjahit & pantau konfirmasi penerimaan</div>
            </div>
        </div>
        <div class="date-pill"><i class="bi bi-clock"></i> <?= date('d F Y, H:i') ?> WIB</div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-grid anim" style="animation-delay:0.05s">
        <div class="sum-card sv-green">
            <div class="sum-stripe"></div>
            <div class="sum-ico"><i class="bi bi-check-circle-fill"></i></div>
            <div class="sum-lbl">Total Lunas</div>
            <div class="sum-val"><?= $total_lunas ?></div>
            <div class="sum-note">Dikonfirmasi penjahit</div>
            <div class="sum-blob" style="background:var(--g500)"></div>
        </div>
        <div class="sum-card sv-amber">
            <div class="sum-stripe"></div>
            <div class="sum-ico"><i class="bi bi-hourglass-split"></i></div>
            <div class="sum-lbl">Menunggu Konfirmasi</div>
            <div class="sum-val"><?= $total_pending ?></div>
            <div class="sum-note">Belum dikonfirmasi</div>
            <div class="sum-blob" style="background:var(--a500)"></div>
        </div>
        <div class="sum-card sv-pink">
            <div class="sum-stripe"></div>
            <div class="sum-ico"><i class="bi bi-wallet2"></i></div>
            <div class="sum-lbl">Total Terbayar</div>
            <div class="sum-val" style="font-size:19px">Rp <?= number_format($total_nominal) ?></div>
            <div class="sum-note">Upah yang sudah lunas</div>
            <div class="sum-blob" style="background:var(--p500)"></div>
        </div>
        <div class="sum-card sv-red">
            <div class="sum-stripe"></div>
            <div class="sum-ico"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="sum-lbl">Komplain Aktif</div>
            <div class="sum-val"><?= $ada_komplain ?></div>
            <div class="sum-note">Perlu ditangani segera</div>
            <?php if(($lewat_deadline ?? 0) > 0): ?>
            <div style="margin-top:8px;padding:5px 8px;background:var(--r100);border-radius:8px;font-size:11.5px;font-weight:700;color:var(--r700);display:flex;align-items:center;gap:5px">
                <i class="bi bi-alarm-fill"></i> <?= $lewat_deadline ?> melewati deadline!
            </div>
            <?php endif; ?>
            <div class="sum-blob" style="background:var(--r500)"></div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="sec-hd anim" style="animation-delay:0.1s">
        <div class="sec-title"><span class="sec-dot"></span> Daftar Penggajian Penjahit ✂️</div>
        <div class="tbl-legend">
            <span class="legend-dot ld-green">Lunas</span>
            <span class="legend-dot ld-blue">Sudah Dibayar</span>
            <span class="legend-dot ld-amber">Menunggu</span>
            <span class="legend-dot ld-red">Komplain</span>
        </div>
    </div>

    <div class="tbl-card anim" style="animation-delay:0.15s">
        <div class="tbl-hd">
            <div class="tbl-title"><i class="bi bi-scissors"></i> Data Upah Penjahit</div>
        </div>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Produksi</th>
                    <th>Penjahit</th>
                    <th>Deadline & Kualitas</th>
                    <th>Total Upah</th>
                    <th>Status Pembayaran</th>
                    <th>Tgl Dibayar</th>
                    <th>Konfirmasi Penjahit</th>
                    <th>Komplain</th>
                    <th>Aksi / Upload Bukti</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $q = mysqli_query($koneksi, "SELECT p.*, pjt.NAMA_PENJAHIT, pjt.UPAH_PER_UNIT,
                    p.DEADLINE, p.TANGGAL_SELESAI, p.STATUS_KUALITAS,
                    p.BONUS, p.PENALTI,
                    g.BUKTI_BAYAR, g.STATUS_TERIMA, g.TANGGAL_BAYAR, g.TANGGAL_KONFIRMASI,
                    g.CATATAN_KOMPLAIN, g.BUKTI_KOMPLAIN, g.TANGGAL_KOMPLAIN, g.STATUS_KOMPLAIN
                FROM produksi p
                JOIN penjahit pjt ON p.ID_PENJAHIT = pjt.ID_PENJAHIT
                LEFT JOIN penggajian g ON p.ID_PRODUKSI = g.ID_PRODUKSI
                ORDER BY p.ID_PRODUKSI DESC");

            if (mysqli_num_rows($q) > 0):
            while($d = mysqli_fetch_assoc($q)):
                $total        = $d['JUMLAH_DIPRODUKSI'] * $d['UPAH_PER_UNIT'];
                $ada_bukti    = !empty($d['BUKTI_BAYAR']);
                $dikonfirm    = ($d['STATUS_TERIMA'] ?? '') == 'Diterima';
                $tgl_bayar    = $d['TANGGAL_BAYAR']      ? date('d M Y, H:i', strtotime($d['TANGGAL_BAYAR']))      . ' WIB' : '-';
                $tgl_konfirm  = $d['TANGGAL_KONFIRMASI'] ? date('d M Y, H:i', strtotime($d['TANGGAL_KONFIRMASI'])) . ' WIB' : '-';
                $ada_komplain = ($d['STATUS_KOMPLAIN'] ?? '') == 'Menunggu';
                // Ringkasan deadline untuk stat card
                $lewat_deadline = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT COUNT(*) as t FROM produksi
                WHERE DEADLINE IS NOT NULL
                AND TANGGAL_SELESAI IS NULL AND DEADLINE < CURDATE()"))['t'] ?? 0;
                // ── Kalkulasi Bonus / Penalti ──
                $gaji_pokok   = $d['JUMLAH_DIPRODUKSI'] * $d['UPAH_PER_UNIT'];
                $bonus_hitung   = $d['BONUS']   ?? 0;
                $penalti_hitung = $d['PENALTI'] ?? 0;
                $info_bonus     = '';

                if (!empty($d['DEADLINE']) && !empty($d['TANGGAL_SELESAI'])) {
                    $selisih_hari = (int)ceil(
                       (strtotime($d['DEADLINE']) - strtotime($d['TANGGAL_SELESAI'])) / 86400
                    );
                    $tbl_check = mysqli_query($koneksi, "SHOW TABLES LIKE 'aturan_deadline'");
                    $aturan = (mysqli_num_rows($tbl_check) > 0)
                        ? mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM aturan_deadline LIMIT 1"))
                        : null;
                    if ($aturan) {
                        if ($selisih_hari >= $aturan['hari_lebih_cepat'] && $d['STATUS_KUALITAS'] === 'baik') {
                            $bonus_hitung = $gaji_pokok * ($aturan['bonus_persen'] / 100);
                            $info_bonus   = '🎁 Selesai ' . $selisih_hari . ' hari lebih cepat + kualitas baik';
                        } elseif ($selisih_hari < 0) {
                            $hari_terlambat = abs($selisih_hari);
                            if ($hari_terlambat >= $aturan['hari_terlambat']) {
                                $penalti_hitung = $gaji_pokok * ($aturan['penalti_persen'] / 100);
                                $info_bonus     = '⚠️ Terlambat ' . $hari_terlambat . ' hari';
                            }
                        }
                        // Simpan ke DB jika belum tersimpan atau berubah
                        if ($bonus_hitung != $d['BONUS'] || $penalti_hitung != $d['PENALTI']) {
                            mysqli_query($koneksi,
                                "UPDATE produksi SET BONUS='$bonus_hitung', PENALTI='$penalti_hitung'
                                WHERE ID_PRODUKSI='" . mysqli_real_escape_string($koneksi, $d['ID_PRODUKSI']) . "'");
                        }
                    }
                } elseif (!empty($d['DEADLINE']) && empty($d['TANGGAL_SELESAI'])) {
                    // Belum selesai, cek apakah sudah lewat deadline
                    $sisa = (int)ceil((strtotime($d['DEADLINE']) - strtotime('today')) / 86400);
                    if ($sisa < 0) {
                        $info_bonus = '🚨 Sudah melewati deadline ' . abs($sisa) . ' hari';
                    } else {
                        $info_bonus = '📅 Deadline: sisa ' . $sisa . ' hari';
                    }
                }
                $total_final = $gaji_pokok + $bonus_hitung - $penalti_hitung;
                $catatan_komp = htmlspecialchars($d['CATATAN_KOMPLAIN'] ?? '');
                $bukti_komp   = $d['BUKTI_KOMPLAIN'] ?? '';
                $tgl_komplain = $d['TANGGAL_KOMPLAIN'] ? date('d M Y, H:i', strtotime($d['TANGGAL_KOMPLAIN'])) . ' WIB' : '';
                $row_class    = $ada_komplain ? 'row-komplain' : '';
            ?>
            <tr class="<?= $row_class ?>">
                <!-- ID -->
                <td><span class="id-tag"><?= htmlspecialchars($d['ID_PRODUKSI']) ?></span></td>

                <!-- Penjahit -->
                <td>
                    <div class="penjahit-name"><?= htmlspecialchars($d['NAMA_PENJAHIT']) ?></div>
                    <div class="penjahit-id"><?= htmlspecialchars($d['ID_PENJAHIT']) ?></div>
                </td>

                <!-- Deadline & Kualitas -->
                <td>
                    <?php if(!empty($d['DEADLINE'])): ?>
                    <div style="font-size:12.5px;font-weight:700;color:var(--text2)">
                        <i class="bi bi-calendar-check" style="color:var(--p500)"></i>
                        <?= date('d M Y', strtotime($d['DEADLINE'])) ?>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($d['TANGGAL_SELESAI'])): ?>
                    <div style="font-size:11px;color:var(--g700);margin-top:2px">
                        ✓ Selesai: <?= date('d M Y', strtotime($d['TANGGAL_SELESAI'])) ?>
                    </div>
                    <?php endif; ?>
                    <?php
                    $kualitas = $d['STATUS_KUALITAS'] ?? 'baik';
                    $kls_kual = $kualitas === 'baik' ? 'var(--g700)' : 'var(--o700)';
                    $ico_kual = $kualitas === 'baik' ? '✅' : '⚠️';
                    ?>
                    <div style="font-size:11px;font-weight:700;color:<?= $kls_kual ?>;margin-top:2px">
                        <?= $ico_kual ?> <?= $kualitas === 'baik' ? 'Kualitas Baik' : 'Ada Kesalahan' ?>
                    </div>
                </td>

                <!-- Upah + Bonus/Penalti -->
                <td>
                    <span class="upah-val">Rp <?= number_format($total_final) ?></span>
                    <?php if($bonus_hitung > 0): ?>
                    <div style="font-size:11px;color:var(--g700);font-weight:700;margin-top:2px">
                        + Bonus Rp <?= number_format($bonus_hitung) ?>
                    </div>
                    <?php elseif($penalti_hitung > 0): ?>
                    <div style="font-size:11px;color:var(--r700);font-weight:700;margin-top:2px">
                        − Penalti Rp <?= number_format($penalti_hitung) ?>
                    </div>
                    <?php endif; ?>
                    <?php if($info_bonus): ?>
                    <div style="font-size:10.5px;color:var(--text3);margin-top:2px"><?= $info_bonus ?></div>
                    <?php endif; ?>
                </td>

                <!-- Status Pembayaran -->
                <td>
                    <?php if($dikonfirm): ?>
                        <span class="badge badge-lunas"><i class="bi bi-check-all"></i> Lunas</span>
                    <?php elseif($ada_bukti): ?>
                        <span class="badge badge-bayar"><i class="bi bi-send-check"></i> Sudah Dibayar</span>
                    <?php else: ?>
                        <span class="badge badge-pending"><i class="bi bi-hourglass-split"></i> Menunggu</span>
                    <?php endif; ?>
                </td>

                <!-- Tgl Dibayar -->
                <td style="color:var(--text2); font-size:12.5px"><?= $tgl_bayar ?></td>

                <!-- Konfirmasi Penjahit -->
                <td>
                    <?php if($dikonfirm): ?>
                        <span class="badge badge-lunas"><i class="bi bi-patch-check-fill"></i> Dikonfirmasi</span>
                        <span class="ts"><?= $tgl_konfirm ?></span>
                    <?php elseif($ada_bukti): ?>
                        <span class="badge badge-tunggu"><i class="bi bi-clock-history"></i> Menunggu Konfirmasi</span>
                    <?php else: ?>
                        <span style="color:var(--text3); font-size:12px">—</span>
                    <?php endif; ?>
                </td>

                <!-- Komplain -->
                <td>
                    <?php if($ada_komplain): ?>
                        <span class="badge badge-komp pulse"><i class="bi bi-exclamation-triangle-fill"></i> Ada Komplain</span>
                        <?php if($tgl_komplain): ?><span class="ts"><?= $tgl_komplain ?></span><?php endif; ?>
                        <?php if($catatan_komp): ?>
                            <div class="komp-box"><?= $catatan_komp ?></div>
                        <?php endif; ?>
                        <?php if($bukti_komp): ?>
                            <a href="../assets/bukti_gaji/komplain/<?= $bukti_komp ?>" target="_blank" class="komp-link">
                                <i class="bi bi-file-image"></i> Lihat Bukti
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--text3); font-size:12px">—</span>
                    <?php endif; ?>
                </td>

                <!-- Aksi / Upload -->
                <td>
                    <?php if($dikonfirm): ?>
                        <div class="selesai-badge">
                            <span class="badge badge-lunas"><i class="bi bi-check-circle-fill"></i> Selesai</span>
                            <?php if($ada_bukti): ?>
                                <a href="../assets/bukti_gaji/<?= $d['BUKTI_BAYAR'] ?>" target="_blank" class="btn-nota ms-1">
                                    <i class="bi bi-file-image"></i> Nota
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="id_produksi" value="<?= $d['ID_PRODUKSI'] ?>">
                            <input type="file" name="bukti" class="file-inp" required accept="image/*,.pdf">
                            <button type="submit" name="bayar" class="btn-bayar">
                                <i class="bi bi-send-fill"></i> Bayar
                            </button>
                        </form>
                        <?php if($ada_bukti): ?>
                            <a href="../assets/bukti_gaji/<?= $d['BUKTI_BAYAR'] ?>" target="_blank" class="btn-nota mt-1">
                                <i class="bi bi-file-image"></i> Nota Lama
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile;
            else: ?>
            <tr><td colspan="8">
                <div class="empty-state">
                    <div class="empty-ico"><i class="bi bi-scissors"></i></div>
                    <div class="empty-text">Belum ada data produksi / penjahit 🌸</div>
                </div>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- BACK LINK -->
    <div style="margin-top:24px">
        <a href="dashboard.php" class="nav-item" style="display:inline-flex; width:auto; color:var(--text3); font-size:13px; padding:8px 16px; border-radius:99px; background:var(--white); border:1.5px solid var(--border);">
            <i class="bi bi-arrow-left" style="color:var(--text3)"></i> Kembali ke Dashboard
        </a>
    </div>

</div>
</main>

</body>
</html>