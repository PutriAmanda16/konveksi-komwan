<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'penjahit') {
    header("Location: ../index.php");
    exit;
}

$id_penjahit   = $_SESSION['id'];
$nama_penjahit = $_SESSION['user'];

$inisial = strtoupper(substr($nama_penjahit, 0, 1));
if (strpos($nama_penjahit, ' ') !== false) {
    $pp = explode(' ', $nama_penjahit);
    $inisial = strtoupper(substr($pp[0],0,1).substr($pp[1],0,1));
}

$q_pjt       = mysqli_query($koneksi, "SELECT UPAH_PER_UNIT FROM penjahit WHERE ID_PENJAHIT = '$id_penjahit'");
$d_pjt       = mysqli_fetch_assoc($q_pjt);
$upah_satuan = $d_pjt['UPAH_PER_UNIT'] ?? 0;

$q_total             = mysqli_query($koneksi, "SELECT SUM(JUMLAH_DIPRODUKSI) as total_pcs FROM produksi WHERE ID_PENJAHIT = '$id_penjahit'");
$d_total             = mysqli_fetch_assoc($q_total);
$total_upah_akumulasi = ($d_total['total_pcs'] ?? 0) * $upah_satuan;

$q_ambil   = mysqli_query($koneksi, "SELECT SUM(JUMLAH_GAJI) as total_ambil FROM penggajian WHERE ID_PRODUKSI IN (SELECT ID_PRODUKSI FROM produksi WHERE ID_PENJAHIT = '$id_penjahit') AND STATUS_TERIMA = 'Selesai'");
$d_ambil   = mysqli_fetch_assoc($q_ambil);
$upah_telah_diambil = $d_ambil['total_ambil'] ?? 0;

$upah_tersedia = $total_upah_akumulasi - $upah_telah_diambil;
if ($upah_tersedia < 0) $upah_tersedia = 0;

$stat_tugas_baru = mysqli_num_rows(mysqli_query($koneksi, "SELECT ID_PRODUKSI FROM produksi WHERE ID_PENJAHIT = '$id_penjahit' AND STATUS_PRODUKSI = 'Belum Dimulai'"));
$stat_proses     = mysqli_num_rows(mysqli_query($koneksi, "SELECT ID_PRODUKSI FROM produksi WHERE ID_PENJAHIT = '$id_penjahit' AND STATUS_PRODUKSI = 'Sedang Diproses'"));
$stat_selesai    = mysqli_num_rows(mysqli_query($koneksi, "SELECT ID_PRODUKSI FROM produksi WHERE ID_PENJAHIT = '$id_penjahit' AND STATUS_PRODUKSI = 'Selesai'"));
$stat_komplain   = mysqli_num_rows(mysqli_query($koneksi, "SELECT ID_PRODUKSI FROM produksi WHERE ID_PENJAHIT = '$id_penjahit' AND STATUS_PRODUKSI = 'Komplain/Revisi'"));

// Mengubah status produksi jika ada post dari form update status produksi
if (isset($_POST['update_status'])) {
    $id_prod  = mysqli_real_escape_string($koneksi, $_POST['id_produksi']);
    $st_baru  = mysqli_real_escape_string($koneksi, $_POST['status_produksi']);
    $ket_baru = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $kl_baru  = mysqli_real_escape_string($koneksi, $_POST['kualitas_produksi']);
    
    mysqli_query($koneksi, "UPDATE produksi SET STATUS_PRODUKSI = '$st_baru', KETERANGAN = '$ket_baru', KUALITAS_PRODUKSI = '$kl_baru' WHERE ID_PRODUKSI = '$id_prod' AND ID_PENJAHIT = '$id_penjahit'");
    header("Location: dashboard.php");
    exit;
}

// Menambahkan komplain jika ada post dari form komplain
if (isset($_POST['kirim_komplain'])) {
    $id_prod  = mysqli_real_escape_string($koneksi, $_POST['id_produksi']);
    $cat_baru = mysqli_real_escape_string($koneksi, $_POST['catatan_komplain']);
    
    mysqli_query($koneksi, "UPDATE produksi SET STATUS_PRODUKSI = 'Komplain/Revisi', KETERANGAN = '$cat_baru' WHERE ID_PRODUKSI = '$id_prod' AND ID_PENJAHIT = '$id_penjahit'");
    header("Location: dashboard.php?sukses=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Penjahit 🧵 | Konveksi Apps</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ── DEADLINE BADGE ── */
.dl-aman   { background:var(--g100);  color:var(--g700);  border:1px solid rgba(34,197,94,0.25); }
.dl-mepet  { background:var(--a100);  color:var(--a700);  border:1px solid rgba(234,179,8,0.25); }
.dl-lewat  { background:var(--r100);  color:var(--r700);  border:1px solid rgba(239,68,68,0.25); }
.dl-none   { background:var(--p50);   color:var(--text3); border:1px solid var(--border); }
.deadline-pill {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 10px; border-radius:99px;
    font-size:12px; font-weight:700; white-space:nowrap;
}

:root {
    --p50:#fff0f5; --p100:#ffd6e7; --p200:#ffadd0; --p300:#ff80b8;
    --p400:#f950a0; --p500:#e8328a; --p600:#cc1a73; --p700:#a8105d;
    --v100:#f3e8ff; --v300:#d8b4fe; --v500:#a855f7;
    --g100:#dcfce7; --g500:#22c55e; --g700:#15803d;
    --a100:#fef9c3; --a500:#eab308; --a700:#854d0e;
    --b100:#dbeafe; --b500:#3b82f6; --b700:#1d4ed8;
    --r100:#fee2e2; --r500:#ef4444; --r700:#991b1b;
    --o100:#ffedd5; --o500:#f97316; --o700:#9a3412;
    --white:#ffffff; --bg:#fff5f9; --text:#3d1a28;
    --text2:#7d4460; --text3:#b07090;
    --border:rgba(232,50,138,0.13); --border2:rgba(232,50,138,0.24);
    --sidebar-w:256px; --topbar-h:64px;
    --ease:0.2s cubic-bezier(0.34,1.56,0.64,1); --ease-plain:0.17s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;font-size:14.5px;line-height:1.6;-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(232,50,138,0.055) 1.5px,transparent 1.5px);background-size:28px 28px;pointer-events:none;z-index:0}
::-webkit-scrollbar{width:5px} ::-webkit-scrollbar-track{background:var(--p50)} ::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px}
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:none}}
.pulse{animation:blink 1.6s ease-in-out infinite}

/* ── SIDEBAR ── */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--white);border-right:1.5px solid var(--border);display:flex;flex-direction:column;z-index:300;overflow:hidden}
.sidebar::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;z-index:1;animation:shimmer 3s linear infinite}
.sb-brand{display:flex;align-items:center;gap:12px;padding:0 18px;height:var(--topbar-h);border-bottom:1.5px solid var(--border);text-decoration:none;flex-shrink:0;transition:background var(--ease-plain);margin-top:4px}
.sb-brand:hover{background:var(--p50)}
.brand-mark{width:38px;height:38px;border-radius:13px;background:linear-gradient(135deg,var(--p500) 0%,var(--p400) 50%,var(--v500) 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4),0 0 0 3px rgba(232,50,138,0.12);transition:transform var(--ease)}
.sb-brand:hover .brand-mark{transform:rotate(-8deg) scale(1.08)}
.brand-mark i{font-size:18px;color:#fff}
.brand-name{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text);white-space:nowrap}
.brand-sub{font-size:10px;font-weight:600;color:var(--p500);letter-spacing:.8px;text-transform:uppercase;margin-top:1px}

/* upah card in sidebar */
.sb-upah{margin:12px 12px 6px;padding:18px;border-radius:20px;background:linear-gradient(135deg,var(--p500),var(--p400),var(--v500));position:relative;overflow:hidden;flex-shrink:0}
.sb-upah::before{content:'';position:absolute;right:-20px;top:-20px;width:90px;height:90px;border-radius:50%;background:rgba(255,255,255,0.1)}
.sb-upah::after{content:'';position:absolute;left:10px;bottom:-30px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.07)}
.sb-upah-label{font-size:10.5px;font-weight:700;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:.8px;margin-bottom:3px}
.sb-upah-val{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;color:#fff;position:relative;z-index:1}
.sb-upah-divider{border:none;border-top:1px solid rgba(255,255,255,0.2);margin:10px 0}
.sb-upah-note{font-size:9.5px;color:rgba(255,255,255,0.55);font-weight:600;margin-bottom:2px}
.sb-upah-total{font-family:'Quicksand',sans-serif;font-size:20px;font-weight:700;color:#fff;position:relative;z-index:1}

.sb-nav{flex:1;overflow-y:auto;padding:6px 10px 10px}
.nav-group-label{font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--text3);padding:14px 10px 4px;display:flex;align-items:center;gap:6px}
.nav-group-label::after{content:'✦';font-size:7px;color:var(--p300)}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:10px;text-decoration:none;color:var(--text2);font-size:14px;font-weight:600;transition:background var(--ease-plain),color var(--ease-plain),transform var(--ease-plain);margin-bottom:2px;position:relative;white-space:nowrap}
.nav-item i{font-size:17px;width:19px;text-align:center;flex-shrink:0;color:var(--text3);transition:color var(--ease-plain)}
.nav-item:hover{background:var(--p50);color:var(--p500);transform:translateX(2px)}
.nav-item:hover i{color:var(--p400)}
.nav-item.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-weight:700;box-shadow:0 4px 16px rgba(232,50,138,0.35)}
.nav-item.active i{color:rgba(255,255,255,0.9)}
.nav-item.active::after{content:'';position:absolute;right:10px;width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.6)}
.nav-pill{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0}
.pill-red{background:var(--r500)} .pill-orange{background:var(--o500)}
.nav-item.active .nav-pill{background:rgba(255,255,255,0.3)}
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0}
.nav-item.logout{color:var(--r700)} .nav-item.logout i{color:var(--r500)} .nav-item.logout:hover{background:var(--r100);color:var(--r700);transform:none}

/* ── TOPBAR ── */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(12px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px}
.topbar::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;animation:shimmer 3s linear infinite}
.tb-greeting{flex:1}
.tb-hello{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text)}
.tb-sub{font-size:12px;color:var(--text3);font-weight:500;margin-top:1px}
.tb-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.icon-btn{width:36px;height:36px;border-radius:10px;background:var(--p50);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;color:var(--p500);font-size:16px;transition:all var(--ease);position:relative}
.icon-btn:hover{background:var(--p100);transform:scale(1.08)}
.icon-btn .dot{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--r500);border:2px solid var(--white);animation:blink 1.6s ease-in-out infinite}
.date-pill{display:flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:600;color:var(--text2)}
.date-pill i{color:var(--p500)}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1}
.content{padding:28px 28px 70px;max-width:1200px}

/* ── ALERT BANNER ── */
.alert-banner{display:flex;align-items:center;gap:14px;padding:16px 20px;border-radius:16px;margin-bottom:22px;animation:slideDown .35s ease both}
.ab-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ab-text{flex:1;font-size:13.5px;font-weight:500}
.ab-text b{font-weight:800}
.ab-btn{display:flex;align-items:center;gap:5px;padding:8px 16px;border-radius:99px;font-size:12.5px;font-weight:700;text-decoration:none;white-space:nowrap;transition:all var(--ease-plain);border:none;cursor:pointer;font-family:'Nunito',sans-serif}
.a-pink{background:var(--white);border:1.5px solid var(--p200);box-shadow:0 4px 16px rgba(232,50,138,0.12)}
.a-pink .ab-ico{background:var(--p50);color:var(--p500)}
.a-pink .ab-btn{background:var(--p500);color:#fff}
.a-pink .ab-btn:hover{background:var(--p600);color:#fff}

/* ── STAT CARDS ── */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:20px;padding:20px 22px;position:relative;overflow:hidden;transition:transform var(--ease),box-shadow var(--ease);animation:fadeUp .35s ease both}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 14px 36px rgba(232,50,138,0.12)}
.stat-stripe{position:absolute;top:0;left:0;right:0;height:4px;border-radius:20px 20px 0 0}
.stat-blob{position:absolute;right:-16px;bottom:-16px;width:72px;height:72px;border-radius:50%;opacity:.07}
.stat-ico{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px}
.stat-label{font-size:12px;font-weight:600;color:var(--text2);margin-bottom:4px}
.stat-val{font-family:'Quicksand',sans-serif;font-size:22px;font-weight:700;line-height:1.1;margin-bottom:2px}
.stat-note{font-size:11px;color:var(--text3);font-weight:500}

/* ── TABLE ── */
.sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;animation:fadeUp .3s ease both}
.sec-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.sec-dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:inline-block;box-shadow:0 0 0 3px rgba(232,50,138,0.15);flex-shrink:0}
.tbl-card{background:var(--white);border:1.5px solid var(--border);border-radius:22px;overflow:hidden;animation:fadeUp .4s ease both;box-shadow:0 4px 24px rgba(232,50,138,0.06)}
.tbl-hd{padding:16px 24px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;gap:10px}
.tbl-hd-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;flex:1}
.tbl-hd-title i{color:var(--p500)}
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{padding:11px 18px;font-size:11px;font-weight:800;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border);letter-spacing:.4px;text-transform:uppercase}
.data-table tbody td{padding:13px 18px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:13.5px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr:hover td{background:var(--p50)}

.id-tag{display:inline-flex;align-items:center;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:8px;padding:3px 10px;font-size:12px;font-weight:700;font-family:'Quicksand',sans-serif}

/* status badges */
.badge-pill{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:99px;font-size:12px;font-weight:700}
.bp-green{background:var(--g100);color:var(--g700)}
.bp-yellow{background:var(--a100);color:var(--a700)}
.bp-red{background:var(--r100);color:var(--r700)}
.bp-blue{background:var(--b100);color:var(--b700)}
.bp-pink{background:var(--p50);color:var(--p600)}
.bp-purple{background:var(--v100);color:var(--v500)}

.timestamp-sm{font-size:10.5px;color:var(--text3);display:block;margin-top:3px}

/* action buttons */
.btn-aksi{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border-radius:9px;font-size:12.5px;font-weight:700;font-family:'Nunito',sans-serif;cursor:pointer;transition:all var(--ease);text-decoration:none;border:1.5px solid;white-space:nowrap}
.btn-konfirm{background:var(--g100);color:var(--g700);border-color:rgba(34,197,94,0.3)}
.btn-konfirm:hover{background:var(--g500);color:#fff;border-color:var(--g500)}
.btn-nota{background:var(--b100);color:var(--b700);border-color:rgba(59,130,246,0.3)}
.btn-nota:hover{background:var(--b500);color:#fff;border-color:var(--b500)}
.btn-komplain{background:var(--r100);color:var(--r700);border-color:rgba(239,68,68,0.3)}
.btn-komplain:hover{background:var(--r500);color:#fff;border-color:var(--r500)}
.btn-update{background:var(--p50);color:var(--p500);border-color:var(--border2)}
.btn-update:hover{background:var(--p500);color:#fff;border-color:var(--p500)}

/* ── MODAL ── */
.modal-overlay{position:fixed;inset:0;background:rgba(61,26,40,0.45);backdrop-filter:blur(6px);z-index:500;display:none;align-items:center;justify-content:center;padding:20px}
.modal-overlay.show{display:flex;animation:fadeUp .25s ease both}
.modal-box{background:var(--white);border-radius:24px;padding:32px;width:100%;max-width:460px;box-shadow:0 24px 60px rgba(232,50,138,0.2);border:1.5px solid var(--border);position:relative;max-height:90vh;overflow-y:auto}
.modal-box::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;border-radius:24px 24px 0 0;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;animation:shimmer 3s linear infinite}
.modal-title{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;color:var(--text);margin-bottom:20px;display:flex;align-items:center;gap:9px}
.modal-title i{color:var(--p500)}
.modal-close{position:absolute;top:16px;right:16px;width:32px;height:32px;border-radius:50%;background:var(--p50);border:none;display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--text3);cursor:pointer;transition:all var(--ease-plain)}
.modal-close:hover{background:var(--r100);color:var(--r500)}
.form-lbl{font-size:12.5px;font-weight:700;color:var(--text2);margin-bottom:6px;display:block}
.form-inp{width:100%;border:1.5px solid var(--border);border-radius:12px;padding:10px 14px;font-size:13.5px;font-family:'Nunito',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:border-color var(--ease-plain),box-shadow var(--ease-plain)}
.form-inp:focus{border-color:var(--border2);box-shadow:0 0 0 3px rgba(232,50,138,0.1);background:var(--white)}
.form-group{margin-bottom:15px}
.modal-actions{display:flex;gap:10px;margin-top:20px}
.btn-cancel{flex:1;padding:11px;border-radius:12px;background:var(--bg);border:1.5px solid var(--border);color:var(--text2);font-size:14px;font-weight:700;font-family:'Nunito',sans-serif;cursor:pointer;transition:all var(--ease-plain)}
.btn-cancel:hover{background:var(--p50)}
.btn-save{flex:2;padding:11px;border-radius:12px;background:linear-gradient(135deg,var(--p500),var(--p400));border:none;color:#fff;font-size:14px;font-weight:700;font-family:'Nunito',sans-serif;cursor:pointer;box-shadow:0 4px 16px rgba(232,50,138,0.35);transition:all var(--ease)}
.btn-save:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(232,50,138,0.45)}
.btn-save-red{background:linear-gradient(135deg,var(--r500),#f87171)!important;box-shadow:0 4px 16px rgba(239,68,68,0.35)!important}
.btn-save-red:hover{box-shadow:0 8px 22px rgba(239,68,68,0.45)!important}

.info-box{background:var(--a100);border:1.5px solid rgba(234,179,8,0.3);border-radius:10px;padding:10px 14px;font-size:12.5px;color:var(--a700);margin-bottom:14px;display:flex;align-items:flex-start;gap:8px}
.info-box i{font-size:15px;flex-shrink:0;margin-top:1px}

/* ── TOAST ── */
.toast-notif{position:fixed;bottom:28px;right:28px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border-radius:14px;padding:14px 22px;font-size:13.5px;font-weight:700;box-shadow:0 8px 24px rgba(232,50,138,0.4);z-index:999;display:flex;align-items:center;gap:10px;animation:slideDown .4s cubic-bezier(0.34,1.56,0.64,1) both}
.toast-notif i{font-size:18px}
</style>
</head>
<body>

    <aside class="sidebar">
        <a href="dashboard.php" class="sb-brand">
            <div class="brand-mark"><i class="bi bi-scissors"></i></div>
            <div>
                <div class="brand-name">Konveksi Apps</div>
                <div class="brand-sub">Penjahit Hub</div>
            </div>
        </a>
        
        <div class="sb-upah">
            <div class="sb-upah-label">Upah Tersedia</div>
            <div class="sb-upah-val">Rp <?= number_format($upah_tersedia) ?></div>
            <hr class="sb-upah-divider">
            <div class="sb-upah-note">Akumulasi Pendapatan</div>
            <div class="sb-upah-total">Rp <?= number_format($total_upah_akumulasi) ?></div>
        </div>

        <nav class="sb-nav">
            <div class="nav-group-label">Menu Utama</div>
            <a href="dashboard.php" class="nav-item active">
                <i class="bi bi-grid-1x2-fill"></i> Tugas Produksi
                <?php if($stat_tugas_baru > 0): ?>
                    <span class="nav-pill pill-red pulse"><?= $stat_tugas_baru ?></span>
                <?php endif; ?>
            </a>
            <a href="riwayat_gaji.php" class="nav-item">
                <i class="bi bi-cash-stack"></i> Riwayat Gaji
            </a>
            
            <div class="nav-group-label">Sistem</div>
            <a href="../logout.php" class="nav-item logout">
                <i class="bi bi-box-arrow-left"></i> Keluar Akun
            </a>
        </nav>
        
        <div class="sb-footer">
            <div class="nav-item" style="background:var(--p50); border-radius:12px; padding:10px; cursor:default; transform:none">
                <div class="brand-mark" style="width:32px; height:32px; font-size:13px; box-shadow:none"><?= $inisial ?></div>
                <div style="overflow:hidden">
                    <div style="font-weight:700; font-size:13px; text-overflow:ellipsis; white-space:nowrap; color:var(--text)"><?= htmlspecialchars($nama_penjahit) ?></div>
                    <div style="font-size:10px; color:var(--text3); font-weight:700; text-transform:uppercase">Penjahit Aktif</div>
                </div>
            </div>
        </div>
    </aside>

    <header class="topbar">
        <div class="tb-greeting">
            <div class="tb-hello">Semangat Bekerja, <?= explode(' ', $nama_penjahit)[0] ?>! 👋🏻</div>
            <div class="tb-sub">Kelola lembar tugas produksi dan pantau progres jahitanmu hari ini.</div>
        </div>
        <div class="tb-actions">
            <?php if($stat_komplain > 0): ?>
                <a href="dashboard.php" class="icon-btn" title="Ada komplain jahit">
                    <i class="bi bi-exclamation-circle-fill" style="color:var(--r500)"></i>
                    <span class="dot"></span>
                </a>
            <?php endif; ?>
            <div class="date-pill">
                <i class="bi bi-calendar3"></i>
                <span><?= date('d M Y') ?></span>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="content">
            
            <?php if($stat_komplain > 0): ?>
            <div class="alert-banner a-pink">
                <div class="ab-ico"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="ab-text">Perhatian! Anda memiliki <b><?= $stat_komplain ?> pesanan yang membutuhkan revisi/komplain</b> dari owner. Mohon segera diperiksa dan diperbaiki.</div>
            </div>
            <?php endif; ?>

            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-stripe" style="background:var(--p400)"></div>
                    <div class="stat-blob" style="background:var(--p500)"></div>
                    <div class="stat-ico" style="background:var(--p50); color:var(--p500)"><i class="bi bi-folder-plus"></i></div>
                    <div class="stat-label">Tugas Baru</div>
                    <div class="stat-val" style="color:var(--text)"><?= $stat_tugas_baru ?></div>
                    <div class="stat-note">Belum disentuh</div>
                </div>
                <div class="stat-card">
                    <div class="stat-stripe" style="background:var(--b500)"></div>
                    <div class="stat-blob" style="background:var(--b500)"></div>
                    <div class="stat-ico" style="background:var(--b100); color:var(--b700)"><i class="bi bi-cone-striped"></i></div>
                    <div class="stat-label">Sedang Jahit</div>
                    <div class="stat-val" style="color:var(--b700)"><?= $stat_proses ?></div>
                    <div class="stat-note">Progres aktif</div>
                </div>
                <div class="stat-card">
                    <div class="stat-stripe" style="background:var(--r500)"></div>
                    <div class="stat-blob" style="background:var(--r500)"></div>
                    <div class="stat-ico" style="background:var(--r100); color:var(--r700)"><i class="bi bi-patch-exclamation"></i></div>
                    <div class="stat-label">Komplain / Revisi</div>
                    <div class="stat-val" style="color:var(--r700)"><?= $stat_komplain ?></div>
                    <div class="stat-note">Butuh perbaikan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-stripe" style="background:var(--g500)"></div>
                    <div class="stat-blob" style="background:var(--g500)"></div>
                    <div class="stat-ico" style="background:var(--g100); color:var(--g700)"><i class="bi bi-check2-circle"></i></div>
                    <div class="stat-label">Selesai</div>
                    <div class="stat-val" style="color:var(--g700)"><?= $stat_selesai ?></div>
                    <div class="stat-note">Menunggu upah cair</div>
                </div>
            </div>

            <div class="sec-hd">
                <div class="sec-title"><span class="sec-dot"></span> Daftar Pekerjaan Jahit Anda</div>
            </div>

            <div class="tbl-card">
                <div class="tbl-hd">
                    <div class="tbl-hd-title"><i class="bi bi-list-task"></i> Manajemen Progres Produksi</div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID Prod</th>
                            <th>Pesanan / Produk</th>
                            <th>Jumlah</th>
                            <th>Upah / Pcs</th>
                            <th>Estimasi Upah</th>
                            <th>Status Kerja</th>
                            <th>Batas Waktu</th>
                            <th style="text-align:center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $q_list = mysqli_query($koneksi, "
                            SELECT pr.*, ps.NAMA_PESANAN, ps.TANGGAL_SELESAI as deadline
                            FROM produksi pr
                            JOIN pesanan ps ON pr.ID_PESANAN = ps.ID_PESANAN
                            WHERE pr.ID_PENJAHIT = '$id_penjahit'
                            ORDER BY 
                                CASE pr.STATUS_PRODUKSI
                                    WHEN 'Komplain/Revisi' THEN 1
                                    WHEN 'Belum Dimulai' THEN 2
                                    WHEN 'Sedang Diproses' THEN 3
                                    ELSE 4
                                END ASC,
                                ps.TANGGAL_SELESAI ASC
                        ");
                        
                        if (mysqli_num_rows($q_list) > 0):
                            while ($r = mysqli_fetch_assoc($q_list)):
                                $est_upah = $r['JUMLAH_DIPRODUKSI'] * $upah_satuan;
                                
                                // Color map status produksi
                                $st = $r['STATUS_PRODUKSI'];
                                if ($st == 'Belum Dimulai') { $s_cls = 'bp-pink'; }
                                elseif ($st == 'Sedang Diproses') { $s_cls = 'bp-blue'; }
                                elseif ($st == 'Komplain/Revisi') { $s_cls = 'bp-red pulse'; }
                                else { $s_cls = 'bp-green'; }
                                
                                // Kalkulasi deadline pill badge
                                $dl_badge = 'dl-none'; $dl_txt = 'Tidak ada';
                                if(!empty($r['deadline'])) {
                                    $tgl_dl = strtotime($r['deadline']);
                                    $skrg   = time();
                                    $diff   = ($tgl_dl - $skrg) / (60 * 60 * 24); // Hari
                                    
                                    $dl_txt = date('d/m/Y', $tgl_dl);
                                    if ($diff < 0) { $dl_badge = 'dl-lewat'; $dl_txt .= ' (Terlambat)'; }
                                    elseif ($diff <= 2) { $dl_badge = 'dl-mepet'; $dl_txt .= ' (Mepet!)'; }
                                    else { $dl_badge = 'dl-aman'; }
                                }
                        ?>
                        <tr>
                            <td><span class="id-tag"><?= htmlspecialchars($r['ID_PRODUKSI']) ?></span></td>
                            <td>
                                <div style="font-weight:700; color:var(--text)"><?= htmlspecialchars($r['NAMA_PESANAN']) ?></div>
                                <span class="timestamp-sm">ID Pesanan: <?= htmlspecialchars($r['ID_PESANAN']) ?></span>
                            </td>
                            <td style="font-weight:700"><?= number_format($r['JUMLAH_DIPRODUKSI']) ?> <span style="font-size:11px; color:var(--text3)">Pcs</span></td>
                            <td style="color:var(--text2)">Rp <?= number_format($upah_satuan) ?></td>
                            <td style="font-weight:700; color:var(--g700)">Rp <?= number_format($est_upah) ?></td>
                            <td><span class="badge-pill <?= $s_cls ?>"><?= htmlspecialchars($st) ?></span></td>
                            <td><span class="deadline-pill <?= $dl_badge ?>"><i class="bi bi-clock-history"></i> <?= $dl_txt ?></span></td>
                            <td style="text-align:center">
                                <?php if($st != 'Selesai'): ?>
                                    <button class="btn-aksi btn-update" onclick="bukaModalUpdate('<?= $r['ID_PRODUKSI'] ?>', '<?= $st ?>', '<?= htmlspecialchars($r['KETERANGAN'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($r['KUALITAS_PRODUKSI'] ?? 'baik', ENT_QUOTES) ?>')">
                                        <i class="bi bi-pencil-square"></i> Update Status
                                    </button>
                                <?php else: ?>
                                    <button class="btn-aksi btn-nota" onclick="bukaModalKomplain('<?= $r['ID_PRODUKSI'] ?>', '<?= htmlspecialchars($r['KETERANGAN'] ?? '', ENT_QUOTES) ?>')">
                                        <i class="bi bi-chat-right-text"></i> Komplain Baru
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:40px 20px; color:var(--text3)">
                                <div style="font-size:32px; margin-bottom:8px">🌸</div>
                                <div style="font-weight:700; font-size:15px">Hore! Belum Ada Tugas Jahit</div>
                                <div style="font-size:12px; margin-top:2px">Silakan santai sejenak atau hubungi owner untuk distribusi kain baru.</div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

    <div class="modal-overlay" id="modalUpdate">
        <div class="modal-box">
            <button class="modal-close" onclick="tutupModal('modalUpdate')"><i class="bi bi-x"></i></button>
            <div class="modal-title"><i class="bi bi-arrow-repeat"></i> Update Progres Produksi</div>
            
            <form action="" method="POST">
                <input type="hidden" name="id_produksi" id="updateId">
                
                <div class="form-group">
                    <label class="form-lbl">Status Produksi Sekarang</label>
                    <select name="status_produksi" id="updateStatus" class="form-inp" style="height:42px">
                        <option value="Belum Dimulai">Belum Dimulai</option>
                        <option value="Sedang Diproses">Sedang Diproses</option>
                        <option value="Selesai">Selesai (Siap Setor)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-lbl">Kualitas Hasil Produksi</label>
                    <select name="kualitas_produksi" id="updateKualitas" class="form-inp" style="height:42px">
                        <option value="baik">Sangat Baik / Sesuai Standar</option>
                        <option value="cacat_minor">Cacat Minor (Sedikit Noda/Benang)</option>
                        <option value="perlu_revisi">Perlu Revisi (Kurang Rapi)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-lbl">Catatan / Keterangan Tambahan</label>
                    <textarea name="keterangan" id="updateKeterangan" class="form-inp" rows="3" placeholder="Contoh: Kain kurang 1 yard, atau Jahitan selesai saku kiri..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="tutupModal('modalUpdate')">Batal</button>
                    <button type="submit" name="update_status" class="btn-save">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="modalKomplain">
        <div class="modal-box">
            <button class="modal-close" onclick="tutupModal('modalKomplain')"><i class="bi bi-x"></i></button>
            <div class="modal-title"><i class="bi bi-chat-square-text-fill" style="color:var(--r500)"></i> Laporkan Masalah / Komplain</div>
            
            <div class="info-box">
                <i class="bi bi-info-circle-fill"></i>
                <div>Gunakan fitur ini jika jahitan yang Anda setorkan ditolak oleh sistem atau ada masalah bahan baku di tengah jalan. Status akan kembali ke <b>Revisi</b>.</div>
            </div>

            <form action="" method="POST">
                <input type="hidden" name="id_produksi" id="komplainId">
                
                <div class="form-group">
                    <label class="form-lbl">Uraikan Detail Kendala / Keluhan Anda</label>
                    <textarea name="catatan_komplain" id="komplainCatatan" class="form-inp" rows="4" required placeholder="Contoh: Resleting macet massal dari supplier, mohon kirim ulang part pengganti..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="tutupModal('modalKomplain')">Batal</button>
                    <button type="submit" name="kirim_komplain" class="btn-save btn-save-red">Kirim ke Owner</button>
                </div>
            </form>
        </div>
    </div>

<?php if(isset($_GET['sukses'])): ?>
<div class="toast-notif" id=\"toastEl\">
    <i class="bi bi-check-circle-fill"></i>
    Laporan komplain berhasil dikirim ke owner! 🌸
</div>
<script>setTimeout(()=>{const t=document.getElementById('toastEl');if(t){t.style.opacity='0';t.style.transform='translateY(20px)';t.style.transition='all .4s ease';setTimeout(()=>t.remove(),400)}},3500)</script>
<?php endif; ?>

<script>
function tutupModal(id) { document.getElementById(id).classList.remove('show'); }

function bukaModalUpdate(id, status, ket, kualitas) {
    document.getElementById('updateId').value         = id;
    document.getElementById('updateStatus').value     = status;
    document.getElementById('updateKeterangan').value = ket;
    document.getElementById('updateKualitas').value   = kualitas || 'baik';
    document.getElementById('modalUpdate').classList.add('show');
}

function bukaModalKomplain(id, catatan) {
    document.getElementById('komplainId').value      = id;
    document.getElementById('komplainCatatan').value = catatan;
    document.getElementById('modalKomplain').classList.add('show');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if(e.target===m) m.classList.remove('show'); });
});
</script>
</body>
</html>