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

$q_notif    = mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM produksi p JOIN penggajian g ON p.ID_PRODUKSI = g.ID_PRODUKSI WHERE p.ID_PENJAHIT = '$id_penjahit' AND g.BUKTI_BAYAR IS NOT NULL AND g.BUKTI_BAYAR != '' AND (g.STATUS_TERIMA IS NULL OR g.STATUS_TERIMA = 'Belum')");
$d_notif    = mysqli_fetch_assoc($q_notif);
$jumlah_notif = (int)($d_notif['jml'] ?? 0);

$q_selesai  = mysqli_query($koneksi, "SELECT COUNT(*) as t FROM produksi WHERE ID_PENJAHIT='$id_penjahit' AND STATUS_PRODUKSI='Selesai'");
$total_selesai = mysqli_fetch_assoc($q_selesai)['t'] ?? 0;

$q_proses   = mysqli_query($koneksi, "SELECT COUNT(*) as t FROM produksi WHERE ID_PENJAHIT='$id_penjahit' AND STATUS_PRODUKSI='Proses'");
$total_proses = mysqli_fetch_assoc($q_proses)['t'] ?? 0;

$q_lunas    = mysqli_query($koneksi, "SELECT COUNT(*) as t FROM produksi p JOIN penggajian g ON p.ID_PRODUKSI=g.ID_PRODUKSI WHERE p.ID_PENJAHIT='$id_penjahit' AND g.STATUS_TERIMA='Diterima'");
$total_lunas = mysqli_fetch_assoc($q_lunas)['t'] ?? 0;

if (isset($_POST['komplain'])) {
    $id_komplain    = mysqli_real_escape_string($koneksi, $_POST['id_produksi_komplain']);
    $catatan        = mysqli_real_escape_string($koneksi, $_POST['catatan_komplain']);
    $waktu_komplain = date('Y-m-d H:i:s');
// Cek dulu apakah kolom sudah ada
    $cek_col = mysqli_query($koneksi, "SHOW COLUMNS FROM penggajian LIKE 'BUKTI_KOMPLAIN'");
    if (mysqli_num_rows($cek_col) == 0) {
        mysqli_query($koneksi, "ALTER TABLE penggajian ADD COLUMN BUKTI_KOMPLAIN VARCHAR(255) NULL");
    }
    $nama_bukti_komplain = '';
    if (!empty($_FILES['bukti_komplain']['name'])) {
        $folder_komplain = "../assets/bukti_gaji/komplain/";
        if (!is_dir($folder_komplain)) mkdir($folder_komplain, 0755, true);
        $ext       = pathinfo($_FILES['bukti_komplain']['name'], PATHINFO_EXTENSION);
        $nama_file = 'komplain_' . $id_komplain . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['bukti_komplain']['tmp_name'], $folder_komplain . $nama_file);
        $nama_bukti_komplain = mysqli_real_escape_string($koneksi, $nama_file);
    }

    $cek = mysqli_query($koneksi, "SELECT ID_PRODUKSI FROM penggajian WHERE ID_PRODUKSI = '$id_komplain'");
    if (mysqli_num_rows($cek) == 0) {
        mysqli_query($koneksi, "INSERT INTO penggajian (ID_PRODUKSI, STATUS_TERIMA, CATATAN_KOMPLAIN, BUKTI_KOMPLAIN, TANGGAL_KOMPLAIN, STATUS_KOMPLAIN) VALUES ('$id_komplain', 'Belum', '$catatan', '$nama_bukti_komplain', '$waktu_komplain', 'Menunggu')");
    } else {
        $set_bukti = $nama_bukti_komplain ? ", BUKTI_KOMPLAIN = '$nama_bukti_komplain'" : "";
        mysqli_query($koneksi, "UPDATE penggajian SET CATATAN_KOMPLAIN='$catatan', TANGGAL_KOMPLAIN='$waktu_komplain', STATUS_KOMPLAIN='Menunggu' $set_bukti WHERE ID_PRODUKSI='$id_komplain'");
    }
    header("Location: dashboard.php?sukses=komplain");
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

<style>
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

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div><div class="brand-name">Konveksi Apps</div><div class="brand-sub">Panel Penjahit</div></div>
    </a>

    <!-- Upah card -->
    <div class="sb-upah">
        <div class="sb-upah-label">Upah per Item</div>
        <div class="sb-upah-val">Rp <?= number_format($upah_satuan) ?></div>
        <hr class="sb-upah-divider">
        <div class="sb-upah-note">Total Saldo Upah</div>
        <div class="sb-upah-total">Rp <?= number_format($total_upah_akumulasi) ?></div>
    </div>

    <nav class="sb-nav">
        <a class="nav-item active" href="dashboard.php">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
            <?php if($jumlah_notif > 0): ?>
            <span class="nav-pill pill-red pulse"><?= $jumlah_notif ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Halo, <?= htmlspecialchars($nama_penjahit) ?>! 🌸</div>
        <div class="tb-sub">Pantau produksi dan upah kamu di sini 💪</div>
    </div>
    <div class="tb-actions">
        <?php if($jumlah_notif > 0): ?>
        <div class="icon-btn">
            <i class="bi bi-bell-fill"></i>
            <span class="dot"></span>
        </div>
        <?php endif; ?>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- NOTIF BANNER -->
    <?php if($jumlah_notif > 0): ?>
    <div class="alert-banner a-pink">
        <div class="ab-ico"><i class="bi bi-cash-coin"></i></div>
        <div class="ab-text">
            <b><?= $jumlah_notif ?> gaji baru</b> sudah dikirim oleh owner! Cek tabel di bawah dan klik
            <b>Konfirmasi Terima</b> untuk menyelesaikan.
        </div>
        <a href="#tabel-produksi" class="ab-btn">Lihat Sekarang <i class="bi bi-arrow-down"></i></a>
    </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stat-grid">
        <div class="stat-card" style="animation-delay:.05s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--p500),var(--p300))"></div>
            <div class="stat-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-label">Upah per Item</div>
            <div class="stat-val" style="color:var(--p600)">Rp <?= number_format($upah_satuan) ?></div>
            <div class="stat-note">Tarif per unit produksi</div>
            <div class="stat-blob" style="background:var(--p500)"></div>
        </div>
        <div class="stat-card" style="animation-delay:.1s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--g500),#86efac)"></div>
            <div class="stat-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-label">Produksi Selesai</div>
            <div class="stat-val" style="color:var(--g700)"><?= $total_selesai ?> <span style="font-size:13px">tugas</span></div>
            <div class="stat-note">Total yang sudah rampung</div>
            <div class="stat-blob" style="background:var(--g500)"></div>
        </div>
        <div class="stat-card" style="animation-delay:.15s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--b500),#93c5fd)"></div>
            <div class="stat-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-gear-wide-connected"></i></div>
            <div class="stat-label">Sedang Dikerjakan</div>
            <div class="stat-val" style="color:var(--b700)"><?= $total_proses ?> <span style="font-size:13px">tugas</span></div>
            <div class="stat-note">Produksi aktif saat ini</div>
            <div class="stat-blob" style="background:var(--b500)"></div>
        </div>
        <div class="stat-card" style="animation-delay:.2s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--a500),#fcd34d)"></div>
            <div class="stat-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-wallet2"></i></div>
            <div class="stat-label">Gaji Terkonfirmasi</div>
            <div class="stat-val" style="color:var(--a700)"><?= $total_lunas ?> <span style="font-size:13px">item</span></div>
            <div class="stat-note">Sudah kamu konfirmasi diterima</div>
            <div class="stat-blob" style="background:var(--a500)"></div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="sec-hd" id="tabel-produksi">
        <div class="sec-title"><span class="sec-dot"></span> Daftar Produksi Kamu 🪡</div>
    </div>
    <div class="tbl-card">
        <div class="tbl-hd">
            <div class="tbl-hd-title"><i class="bi bi-table"></i> Riwayat Produksi &amp; Status Upah</div>
        </div>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Produk</th>
                    <th>Jumlah</th>
                    <th>Total Upah</th>
                    <th>Deadline</th>
                    <th>Status Gaji</th>
                    <th>Tgl Dibayar</th>
                    <th>Progress</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $query = mysqli_query($koneksi, "
                SELECT p.*, pr.NAMA_PRODUK,
                    p.DEADLINE, p.STATUS_KUALITAS,
                    g.BUKTI_BAYAR, g.STATUS_TERIMA, g.TANGGAL_BAYAR, g.TANGGAL_KONFIRMASI,
                    g.CATATAN_KOMPLAIN, g.TANGGAL_KOMPLAIN, g.STATUS_KOMPLAIN
                FROM produksi p
                JOIN produk pr ON p.ID_PRODUK = pr.ID_PRODUK
                LEFT JOIN penggajian g ON p.ID_PRODUKSI = g.ID_PRODUKSI
                WHERE p.ID_PENJAHIT = '$id_penjahit'
                ORDER BY p.ID_PRODUKSI DESC");

            while($row = mysqli_fetch_assoc($query)):
                $upah_tugas    = $row['JUMLAH_DIPRODUKSI'] * $upah_satuan;
                $st_pengerjaan = $row['STATUS_PRODUKSI'] ?? 'Pending';
                $status_terima = $row['STATUS_TERIMA'] ?? '';
                $ada_bukti     = !empty($row['BUKTI_BAYAR']);
                $tgl_bayar     = $row['TANGGAL_BAYAR'] ? date('d M Y', strtotime($row['TANGGAL_BAYAR'])) : '-';
                $tgl_konfirm   = $row['TANGGAL_KONFIRMASI'] ? date('d M Y, H:i', strtotime($row['TANGGAL_KONFIRMASI'])) : null;
                // Hitung sisa hari deadline
                $deadline_raw   = $row['DEADLINE'] ?? '';
                $sisa_hari      = null;
                $dl_class       = 'dl-none';
                $dl_label       = 'Belum diset';

                if (!empty($deadline_raw)) {
                    $sisa_hari = (int)ceil((strtotime($deadline_raw) - strtotime('today')) / 86400);
                    if ($sisa_hari < 0) {
                        $dl_class = 'dl-lewat';
                        $dl_label = 'Terlambat ' . abs($sisa_hari) . ' hari';
                    } elseif ($sisa_hari <= 2) {
                        $dl_class = 'dl-mepet';
                        $dl_label = $sisa_hari == 0 ? 'Hari ini!' : 'Sisa ' . $sisa_hari . ' hari';
                    } else {
                        $dl_class = 'dl-aman';
                        $dl_label = 'Sisa ' . $sisa_hari . ' hari';
                    }
                }
                $status_komplain  = $row['STATUS_KOMPLAIN'] ?? '';
                $catatan_komplain = $row['CATATAN_KOMPLAIN'] ?? '';
                $tgl_komplain     = $row['TANGGAL_KOMPLAIN'] ? date('d M Y, H:i', strtotime($row['TANGGAL_KOMPLAIN'])) : '';

                $prog_class = match($st_pengerjaan) {
                    'Selesai' => 'bp-green', 'Proses' => 'bp-blue',
                    'Kendala' => 'bp-red',   default  => 'bp-yellow',
                };
                $prog_icon = match($st_pengerjaan) {
                    'Selesai' => 'check-circle-fill', 'Proses' => 'arrow-repeat',
                    'Kendala' => 'exclamation-circle-fill', default => 'clock',
                };
            ?>
            <tr>
                <td><span class="id-tag"><?= $row['ID_PRODUKSI'] ?></span></td>
                <td style="font-weight:700"><?= htmlspecialchars($row['NAMA_PRODUK']) ?></td>
                <td style="font-weight:600"><?= $row['JUMLAH_DIPRODUKSI'] ?> <span style="font-size:11px;color:var(--text3)">pcs</span></td>
                <td style="font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--g700)">Rp <?= number_format($upah_tugas) ?></td>
                <td>
                    <span class="deadline-pill <?= $dl_class ?>">
                        <i class="bi bi-<?= ($dl_class=='dl-lewat') ? 'alarm-fill' : (($dl_class=='dl-mepet') ? 'clock-fill' : 'calendar-check') ?>"></i>
                        <?= $dl_label ?>
                    </span>

                    <?php if(!empty($deadline_raw)): ?>
                        <div class="timestamp-sm">
                            <?= date('d M Y', strtotime($deadline_raw)) ?>
                        </div>
                    <?php endif; ?>
                </td>    

                <!-- STATUS GAJI -->
                <td>
                    <?php if($status_terima == 'Diterima'): ?>
                        <span class="badge-pill bp-green"><i class="bi bi-check-all"></i> Lunas</span>
                        <?php if($tgl_konfirm): ?><span class="timestamp-sm">✓ <?= $tgl_konfirm ?></span><?php endif; ?>

                    <?php elseif($ada_bukti): ?>
                        <span class="badge-pill bp-pink pulse"><i class="bi bi-exclamation-circle"></i> Perlu Konfirmasi</span>

                    <?php elseif($status_komplain == 'Menunggu'): ?>
                        <span class="badge-pill bp-yellow"><i class="bi bi-clock-history"></i> Komplain Dikirim</span>
                        <?php if($tgl_komplain): ?><span class="timestamp-sm"><?= $tgl_komplain ?></span><?php endif; ?>

                    <?php else: ?>
                        <span class="badge-pill bp-purple"><i class="bi bi-hourglass-split"></i> Menunggu Owner</span>
                    <?php endif; ?>
                </td>

                <td style="font-size:13px;color:var(--text2)"><?= $tgl_bayar ?></td>

                <!-- PROGRESS -->
                <td>
                    <span class="badge-pill <?= $prog_class ?>"><i class="bi bi-<?= $prog_icon ?>"></i> <?= $st_pengerjaan ?></span>
                </td>

                <!-- AKSI -->
                <td>
                    <div style="display:flex;flex-wrap:wrap;gap:5px">
                        <!-- Update Progress -->
                        <button class="btn-aksi btn-update"
                         onclick="bukaModalUpdate('<?= $row['ID_PRODUKSI'] ?>', '<?= $st_pengerjaan ?>', '<?= htmlspecialchars($row['KETERANGAN'] ?? '', ENT_QUOTES) ?>', '<?= $row['STATUS_KUALITAS'] ?? 'baik' ?>')">
                         <i class="bi bi-pencil-square"></i> Update
                        </button>

                        <?php if($ada_bukti && $status_terima != 'Diterima'): ?>
                        <a href="../assets/bukti_gaji/<?= $row['BUKTI_BAYAR'] ?>" target="_blank" class="btn-aksi btn-nota">
                            <i class="bi bi-file-image"></i> Nota
                        </a>
                        <a href="konfirmasi_gaji.php?id=<?= $row['ID_PRODUKSI'] ?>" class="btn-aksi btn-konfirm"
                           onclick="return confirm('Pastikan gaji sudah kamu terima ya!')">
                            <i class="bi bi-cash-coin"></i> Konfirmasi
                        </a>
                        <?php endif; ?>

                        <?php if($status_komplain != 'Menunggu' && $status_terima != 'Diterima'): ?>
                        <button class="btn-aksi btn-komplain"
                            onclick="bukaModalKomplain('<?= $row['ID_PRODUKSI'] ?>', '<?= htmlspecialchars($catatan_komplain, ENT_QUOTES) ?>')">
                            <i class="bi bi-exclamation-triangle"></i> Komplain
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>
</main>

<!-- ════ MODAL UPDATE PROGRESS ════ -->
<div class="modal-overlay" id="modalUpdate">
    <div class="modal-box">
        <button class="modal-close" onclick="tutupModal('modalUpdate')"><i class="bi bi-x-lg"></i></button>
        <div class="modal-title"><i class="bi bi-pencil-square"></i> Update Progres Produksi</div>
        <form action="update_progres.php" method="POST">
            <input type="hidden" name="id" id="updateId">
            <div class="form-group">
                <label class="form-lbl">Status Pengerjaan</label>
                <select name="status" id="updateStatus" class="form-inp">
                    <option value="Pending">Pending</option>
                    <option value="Proses">Proses</option>
                    <option value="Kendala">Kendala</option>
                    <option value="Selesai">Selesai</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-lbl">Status Kualitas Produk</label>
                <select name="status_kualitas" id="updateKualitas" class="form-inp">
                    <option value="baik">✅ Baik / Tanpa Kesalahan</option>
                    <option value="ada_kesalahan">⚠️ Ada Kesalahan Produk</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-lbl">Keterangan</label>
                <textarea name="keterangan" id="updateKeterangan" class="form-inp" rows="3"
                    placeholder="Tulis keterangan progres kamu..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="tutupModal('modalUpdate')">Batal</button>
                <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Simpan Progres</button>
            </div>
        </form>
    </div>
</div>

<!-- ════ MODAL KOMPLAIN ════ -->
<div class="modal-overlay" id="modalKomplain">
    <div class="modal-box">
        <button class="modal-close" onclick="tutupModal('modalKomplain')"><i class="bi bi-x-lg"></i></button>
        <div class="modal-title" style="color:var(--r700)"><i class="bi bi-exclamation-triangle-fill" style="color:var(--r500)"></i> Laporkan Gaji Tidak Masuk</div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_produksi_komplain" id="komplainId">
            <div class="info-box">
                <i class="bi bi-info-circle-fill"></i>
                Laporan ini akan dikirim ke owner untuk segera ditindaklanjuti. Sertakan keterangan yang jelas ya!
            </div>
            <div class="form-group">
                <label class="form-lbl">Keterangan Masalah <span style="color:var(--r500)">*</span></label>
                <textarea name="catatan_komplain" id="komplainCatatan" class="form-inp" rows="4"
                    placeholder="Contoh: Sudah 3 hari gaji belum masuk, transfer tidak terdeteksi..." required></textarea>
            </div>
            <div class="form-group">
                <label class="form-lbl">Bukti Pendukung <span style="font-weight:500;color:var(--text3)">(opsional, foto/screenshot)</span></label>
                <input type="file" name="bukti_komplain" class="form-inp" accept="image/*,.pdf"
                    style="padding:8px 14px;cursor:pointer">
                <div style="font-size:11px;color:var(--text3);margin-top:4px">Format: JPG, PNG, PDF. Maks 2MB.</div>
            </div>
            <div style="font-size:11.5px;color:var(--text3);margin-bottom:4px">
                <i class="bi bi-clock"></i> Waktu laporan: <?= date('d M Y, H:i') ?> WIB
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="tutupModal('modalKomplain')">Batal</button>
                <button type="submit" name="komplain" class="btn-save btn-save-red">
                    <i class="bi bi-send-fill"></i> Kirim Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<?php if(isset($_GET['sukses'])): ?>
<div class="toast-notif" id="toastEl">
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
document.addEventListener('keydown', e => {
    if(e.key==='Escape') document.querySelectorAll('.modal-overlay.show').forEach(m=>m.classList.remove('show'));
});
</script>
</body>
</html>