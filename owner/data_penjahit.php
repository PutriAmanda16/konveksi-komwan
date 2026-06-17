<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

// ── Notif badges ──
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

// ── Handle Edit Upah ──
if (isset($_POST['edit_upah'])) {
    $id    = mysqli_real_escape_string($koneksi, $_POST['id_penjahit']);
    $upah  = intval($_POST['upah_baru']);
    mysqli_query($koneksi, "UPDATE penjahit SET UPAH_PER_UNIT = $upah WHERE ID_PENJAHIT = '$id'");
    header("Location: data_penjahit.php?sukses=1");
    exit;
}

// ── Handle Tambah Penjahit ──
if (isset($_POST['tambah_penjahit'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_penjahit']);
    $upah = intval($_POST['upah_penjahit']);
    $last = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT ID_PENJAHIT FROM penjahit ORDER BY ID_PENJAHIT DESC LIMIT 1"));
    $num  = $last ? (int)substr($last['ID_PENJAHIT'], 3) + 1 : 1;
    $id   = 'PJT' . str_pad($num, 2, '0', STR_PAD_LEFT);
    mysqli_query($koneksi, "INSERT INTO penjahit (ID_PENJAHIT, NAMA_PENJAHIT, UPAH_PER_UNIT) VALUES ('$id', '$nama', $upah)");
    header("Location: data_penjahit.php?sukses=2");
    exit;
}

// ── Handle Hapus Penjahit ──
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    mysqli_query($koneksi, "DELETE FROM penjahit WHERE ID_PENJAHIT = '$id'");
    header("Location: data_penjahit.php?sukses=3");
    exit;
}

$total_penjahit = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM penjahit"));
$avg_upah = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT AVG(UPAH_PER_UNIT) as a FROM penjahit"))['a'] ?? 0;
$max_upah = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT MAX(UPAH_PER_UNIT) as m FROM penjahit"))['m'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Penjahit 🧵 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --p50:  #fff0f5; --p100: #ffd6e7; --p200: #ffadd0;
    --p300: #ff80b8; --p400: #f950a0; --p500: #e8328a;
    --p600: #cc1a73; --p700: #a8105d;
    --v100: #f3e8ff; --v300: #d8b4fe; --v500: #a855f7;
    --g100: #dcfce7; --g500: #22c55e; --g700: #15803d;
    --a100: #fef9c3; --a500: #eab308; --a700: #854d0e;
    --b100: #dbeafe; --b500: #3b82f6; --b700: #1d4ed8;
    --r100: #fee2e2; --r500: #ef4444; --r700: #991b1b;
    --o100: #ffedd5; --o500: #f97316;
    --white: #ffffff; --bg: #fff5f9; --bg2: #ffeef5;
    --text: #3d1a28; --text2: #7d4460; --text3: #b07090;
    --border: rgba(232,50,138,0.13); --border2: rgba(232,50,138,0.24);
    --sidebar-w: 256px; --topbar-h: 64px;
    --ease: 0.2s cubic-bezier(0.34,1.56,0.64,1);
    --ease-plain: 0.17s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body { font-family:'Nunito',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; font-size:14.5px; line-height:1.6; -webkit-font-smoothing:antialiased; }
body::before { content:''; position:fixed; inset:0; background-image:radial-gradient(circle,rgba(232,50,138,0.055) 1.5px,transparent 1.5px); background-size:28px 28px; pointer-events:none; z-index:0; }
::-webkit-scrollbar{width:5px} ::-webkit-scrollbar-track{background:var(--p50)} ::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px}
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}

/* ── SIDEBAR ── */
.sidebar { position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--white);border-right:1.5px solid var(--border);display:flex;flex-direction:column;z-index:300;overflow:hidden; }
.sidebar::before { content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;z-index:1;animation:shimmer 3s linear infinite; }
.sb-brand { display:flex;align-items:center;gap:12px;padding:0 18px;height:var(--topbar-h);border-bottom:1.5px solid var(--border);text-decoration:none;flex-shrink:0;transition:background var(--ease-plain);margin-top:4px; }
.sb-brand:hover{background:var(--p50)}
.brand-mark { width:38px;height:38px;border-radius:13px;background:linear-gradient(135deg,var(--p500) 0%,var(--p400) 50%,var(--v500) 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4),0 0 0 3px rgba(232,50,138,0.12);transition:transform var(--ease),box-shadow var(--ease); }
.sb-brand:hover .brand-mark{transform:rotate(-8deg) scale(1.08)}
.brand-mark i{font-size:18px;color:#fff}
.brand-name{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text);white-space:nowrap}
.brand-sub{font-size:10px;font-weight:600;color:var(--p500);letter-spacing:.8px;text-transform:uppercase;margin-top:1px}
.sb-owner { margin:12px 12px 6px;padding:12px 14px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:22px;display:flex;align-items:center;gap:10px;flex-shrink:0; }
.owner-av { width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;position:relative;box-shadow:0 3px 10px rgba(232,50,138,0.35); }
.owner-av::after{content:'';position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:var(--g500);border:2px solid var(--white)}
.owner-name{font-size:13.5px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.owner-role{font-size:11px;color:var(--p500);font-weight:600}
.sb-nav{flex:1;overflow-y:auto;padding:6px 10px 10px}
.nav-group-label{font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--text3);padding:14px 10px 4px;display:flex;align-items:center;gap:6px}
.nav-group-label::after{content:'✦';font-size:7px;color:var(--p300)}
.nav-item { display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:10px;text-decoration:none;color:var(--text2);font-size:14px;font-weight:600;transition:background var(--ease-plain),color var(--ease-plain),transform var(--ease-plain);margin-bottom:2px;position:relative;white-space:nowrap; }
.nav-item i{font-size:17px;width:19px;text-align:center;flex-shrink:0;color:var(--text3);transition:color var(--ease-plain)}
.nav-item:hover{background:var(--p50);color:var(--p500);transform:translateX(2px)}
.nav-item:hover i{color:var(--p400)}
.nav-item.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-weight:700;box-shadow:0 4px 16px rgba(232,50,138,0.35)}
.nav-item.active i{color:rgba(255,255,255,0.9)}
.nav-item.active::after{content:'';position:absolute;right:10px;width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.6)}
.nav-pill{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0}
.pill-red{background:var(--r500)} .pill-orange{background:var(--o500)} .pill-pink{background:var(--p500)}
.nav-item.active .nav-pill{background:rgba(255,255,255,0.3)}
.pulse{animation:blink 1.6s ease-in-out infinite}
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0}
.nav-item.logout{color:var(--r700)} .nav-item.logout i{color:var(--r500)} .nav-item.logout:hover{background:var(--r100);color:var(--r700);transform:none}

/* ── TOPBAR ── */
.topbar { position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(12px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px; }
.topbar::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;animation:shimmer 3s linear infinite}
.tb-greeting{flex:1}
.tb-hello{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text)}
.tb-sub{font-size:12px;color:var(--text3);font-weight:500;margin-top:1px}
.tb-nav{display:flex;align-items:center;gap:2px}
.tb-nav-item{display:flex;align-items:center;gap:5px;padding:7px 13px;border-radius:99px;font-size:13px;font-weight:600;color:var(--text2);text-decoration:none;transition:all var(--ease-plain);white-space:nowrap;border:1.5px solid transparent}
.tb-nav-item:hover{background:var(--p50);color:var(--p500)}
.tb-nav-item.active{background:var(--p50);color:var(--p500);border-color:var(--border2)}
.tb-divider{width:1px;height:24px;background:var(--border2);margin:0 4px}
.tb-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.icon-btn{width:36px;height:36px;border-radius:10px;background:var(--p50);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;color:var(--p500);font-size:16px;transition:all var(--ease);position:relative}
.icon-btn:hover{background:var(--p100);transform:scale(1.08)}
.icon-btn .dot{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--r500);border:2px solid var(--white);animation:blink 1.6s ease-in-out infinite}
.date-pill{display:flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:600;color:var(--text2)}
.date-pill i{color:var(--p500)}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1}
.content{padding:28px 28px 70px;max-width:1200px}

/* ── STAT CARDS ── */
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:20px;padding:20px 22px;position:relative;overflow:hidden;transition:transform var(--ease),box-shadow var(--ease);animation:fadeUp 0.35s ease both}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 14px 36px rgba(232,50,138,0.12)}
.stat-stripe{position:absolute;top:0;left:0;right:0;height:4px;border-radius:20px 20px 0 0}
.stat-blob{position:absolute;right:-16px;bottom:-16px;width:72px;height:72px;border-radius:50%;opacity:0.07}
.stat-ico{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px}
.stat-label{font-size:12px;font-weight:600;color:var(--text2);margin-bottom:4px}
.stat-val{font-family:'Quicksand',sans-serif;font-size:26px;font-weight:700;line-height:1.1;margin-bottom:2px}
.stat-note{font-size:11px;color:var(--text3);font-weight:500}

/* ── PAGE HEADER ── */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp 0.3s ease both}
.page-title{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px}
.sec-dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:inline-block;box-shadow:0 0 0 3px rgba(232,50,138,0.15);flex-shrink:0}
.btn-primary-pink{display:flex;align-items:center;gap:7px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border:none;border-radius:12px;padding:10px 20px;font-size:13.5px;font-weight:700;font-family:'Nunito',sans-serif;cursor:pointer;box-shadow:0 4px 16px rgba(232,50,138,0.35);transition:all var(--ease);text-decoration:none}
.btn-primary-pink:hover{transform:translateY(-2px) scale(1.03);box-shadow:0 8px 24px rgba(232,50,138,0.45);color:#fff}
.btn-primary-pink i{font-size:16px}

/* ── SEARCH BAR ── */
.search-wrap{display:flex;align-items:center;gap:10px;margin-bottom:16px;animation:fadeUp 0.35s ease both}
.search-box{flex:1;position:relative}
.search-box input{width:100%;border:1.5px solid var(--border);border-radius:99px;padding:10px 16px 10px 42px;font-size:13.5px;font-weight:500;background:var(--white);color:var(--text);font-family:'Nunito',sans-serif;outline:none;transition:border-color var(--ease-plain),box-shadow var(--ease-plain)}
.search-box input:focus{border-color:var(--border2);box-shadow:0 0 0 3px rgba(232,50,138,0.1)}
.search-box input::placeholder{color:var(--text3)}
.search-box i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:15px}

/* ── TABLE CARD ── */
.tbl-card{background:var(--white);border:1.5px solid var(--border);border-radius:22px;overflow:hidden;animation:fadeUp 0.4s ease both;box-shadow:0 4px 24px rgba(232,50,138,0.06)}
.tbl-hd{padding:18px 24px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;gap:10px}
.tbl-hd-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;flex:1}
.tbl-hd-title i{color:var(--p500)}
.tbl-count{font-size:12px;font-weight:700;color:var(--p500);background:var(--p50);border:1px solid var(--border2);border-radius:99px;padding:3px 12px}

.data-table{width:100%;border-collapse:collapse}
.data-table thead th{padding:12px 20px;font-size:11.5px;font-weight:800;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border);letter-spacing:.4px;text-transform:uppercase}
.data-table tbody td{padding:14px 20px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:14px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr{transition:background var(--ease-plain)}
.data-table tbody tr:hover td{background:var(--p50)}

.id-tag{display:inline-flex;align-items:center;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:8px;padding:3px 10px;font-size:12px;font-weight:700;font-family:'Quicksand',sans-serif}
.penjahit-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--p400),var(--v500));display:inline-flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;box-shadow:0 3px 8px rgba(232,50,138,0.25)}
.upah-val{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--g700)}
.upah-val span{font-size:12px;color:var(--g500);margin-right:2px}

.btn-edit{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:10px;background:var(--p50);color:var(--p500);border:1.5px solid var(--border2);font-size:12.5px;font-weight:700;font-family:'Nunito',sans-serif;cursor:pointer;transition:all var(--ease);text-decoration:none}
.btn-edit:hover{background:var(--p500);color:#fff;transform:scale(1.05)}
.btn-edit i{font-size:13px}

/* ── MODAL ── */
.modal-overlay{position:fixed;inset:0;background:rgba(61,26,40,0.45);backdrop-filter:blur(6px);z-index:500;display:none;align-items:center;justify-content:center}
.modal-overlay.show{display:flex;animation:fadeUp 0.25s ease both}
.modal-box{background:var(--white);border-radius:24px;padding:32px;width:100%;max-width:420px;box-shadow:0 24px 60px rgba(232,50,138,0.2);border:1.5px solid var(--border);position:relative;animation:fadeUp 0.3s cubic-bezier(0.34,1.56,0.64,1) both}
.modal-box::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;border-radius:24px 24px 0 0;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;animation:shimmer 3s linear infinite}
.modal-title{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--text);margin-bottom:20px;display:flex;align-items:center;gap:9px}
.modal-title i{color:var(--p500)}
.modal-close{position:absolute;top:16px;right:16px;width:32px;height:32px;border-radius:50%;background:var(--p50);border:none;display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--text3);cursor:pointer;transition:all var(--ease-plain)}
.modal-close:hover{background:var(--r100);color:var(--r500)}
.form-label-custom{font-size:12.5px;font-weight:700;color:var(--text2);margin-bottom:6px;display:block;letter-spacing:.3px}
.form-input-custom{width:100%;border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;font-size:14px;font-family:'Nunito',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:border-color var(--ease-plain),box-shadow var(--ease-plain)}
.form-input-custom:focus{border-color:var(--border2);box-shadow:0 0 0 3px rgba(232,50,138,0.12);background:var(--white)}
.form-group{margin-bottom:16px}
.modal-actions{display:flex;gap:10px;margin-top:22px}
.btn-modal-cancel{flex:1;padding:11px;border-radius:12px;background:var(--bg);border:1.5px solid var(--border);color:var(--text2);font-size:14px;font-weight:700;font-family:'Nunito',sans-serif;cursor:pointer;transition:all var(--ease-plain)}
.btn-modal-cancel:hover{background:var(--p50);border-color:var(--border2)}
.btn-modal-save{flex:2;padding:11px;border-radius:12px;background:linear-gradient(135deg,var(--p500),var(--p400));border:none;color:#fff;font-size:14px;font-weight:700;font-family:'Nunito',sans-serif;cursor:pointer;box-shadow:0 4px 16px rgba(232,50,138,0.35);transition:all var(--ease)}
.btn-modal-save:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(232,50,138,0.45)}

/* ── TOAST ── */
.toast-notif{position:fixed;bottom:28px;right:28px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border-radius:14px;padding:14px 22px;font-size:13.5px;font-weight:700;box-shadow:0 8px 24px rgba(232,50,138,0.4);z-index:999;display:flex;align-items:center;gap:10px;animation:fadeUp 0.4s cubic-bezier(0.34,1.56,0.64,1) both}
.toast-notif i{font-size:18px}
</style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div><div class="brand-name">Konveksi Apps</div><div class="brand-sub">Panel Owner</div></div>
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
        <a class="nav-item" href="kelola_bahan.php"><i class="bi bi-basket"></i> Bahan Baku
            <?php if($stok_kritis>0):?><span class="nav-pill pill-orange pulse"><?=$stok_kritis?></span><?php endif;?></a>
        <a class="nav-item" href="kelola_aset.php"><i class="bi bi-building-gear"></i> Aset &amp; Inventaris
            <?php if($aset_rusak>0):?><span class="nav-pill pill-orange pulse"><?=$aset_rusak?></span><?php endif;?></a>
        <a class="nav-item active" href="data_penjahit.php"><i class="bi bi-people"></i> Data Penjahit</a>
        <a class="nav-item" href="pelanggan.php"><i class="bi bi-person-badge"></i> Data Pelanggan</a>
        <a class="nav-item" href="supplier.php"><i class="bi bi-truck"></i> Data Supplier</a>
        <div class="nav-group-label">Operasional</div>
        <a class="nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi Aktif</a>
        <a class="nav-item" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="nav-item" href="konfirmasi_pembayaran.php"><i class="bi bi-credit-card-2-front"></i> Konfirmasi Bayar
            <?php if($notif_bayar>0):?><span class="nav-pill pill-pink pulse"><?=$notif_bayar?></span><?php endif;?></a>
        <a class="nav-item" href="chat.php"><i class="bi bi-chat-dots-fill"></i> Inbox Chat
            <?php if($notif_chat>0):?><span class="nav-pill pill-red pulse"><?=$notif_chat?></span><?php endif;?></a>
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
        <div class="tb-hello">Data Penjahit 🧵</div>
        <div class="tb-sub">Kelola seluruh penjahit dan upah mereka</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item active" href="data_penjahit.php"><i class="bi bi-people"></i> Penjahit</a>
        <a class="tb-nav-item" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
    </nav>
    <div class="tb-divider"></div>
    <div class="tb-actions">
        <a href="<?= $total_notif>0 ? 'konfirmasi_pembayaran.php' : '#' ?>" class="icon-btn">
            <i class="bi bi-bell-fill"></i>
            <?php if($total_notif>0):?><span class="dot"></span><?php endif;?>
        </a>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- STAT CARDS -->
    <div class="stat-grid">
        <div class="stat-card" style="animation-delay:.05s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--p500),var(--p300))"></div>
            <div class="stat-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-people-fill"></i></div>
            <div class="stat-label">Total Penjahit</div>
            <div class="stat-val" style="color:var(--p600)"><?= $total_penjahit ?></div>
            <div class="stat-note">Penjahit terdaftar aktif</div>
            <div class="stat-blob" style="background:var(--p500)"></div>
        </div>
        <div class="stat-card" style="animation-delay:.1s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--g500),#86efac)"></div>
            <div class="stat-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-label">Rata-rata Upah/Unit</div>
            <div class="stat-val" style="color:var(--g700)">Rp <?= number_format(round($avg_upah)) ?></div>
            <div class="stat-note">Rata-rata semua penjahit</div>
            <div class="stat-blob" style="background:var(--g500)"></div>
        </div>
        <div class="stat-card" style="animation-delay:.15s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--a500),#fcd34d)"></div>
            <div class="stat-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-trophy-fill"></i></div>
            <div class="stat-label">Upah Tertinggi</div>
            <div class="stat-val" style="color:var(--a700)">Rp <?= number_format($max_upah) ?></div>
            <div class="stat-note">Upah per unit terbesar</div>
            <div class="stat-blob" style="background:var(--a500)"></div>
        </div>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-title"><span class="sec-dot"></span> Daftar Penjahit 🪡</div>
        <button class="btn-primary-pink" onclick="bukaModalTambah()">
            <i class="bi bi-plus-lg"></i> Tambah Penjahit
        </button>
    </div>

    <!-- SEARCH -->
    <div class="search-wrap">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Cari nama penjahit..." id="searchInput" oninput="filterTabel(this.value)">
        </div>
    </div>

    <!-- TABLE -->
    <div class="tbl-card">
        <div class="tbl-hd">
            <div class="tbl-hd-title"><i class="bi bi-scissors"></i> Database Penjahit</div>
            <span class="tbl-count"><?= $total_penjahit ?> penjahit</span>
        </div>
        <table class="data-table" id="tabelPenjahit">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Penjahit</th>
                    <th>Upah per Unit</th>
                    <th>Sedang Dikerjakan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $q = mysqli_query($koneksi, "SELECT p.*, 
                (SELECT KETERANGAN FROM produksi WHERE ID_PENJAHIT = p.ID_PENJAHIT AND STATUS_PRODUKSI NOT IN ('Selesai','Batal') LIMIT 1) as PEKERJAAN_AKTIF
                FROM penjahit p ORDER BY p.NAMA_PENJAHIT ASC");
            $idx = 0;
            while($d = mysqli_fetch_assoc($q)):
                $idx++;
                $nama   = htmlspecialchars($d['NAMA_PENJAHIT']);
                $inisial_pj = strtoupper(substr($d['NAMA_PENJAHIT'],0,1));
                if(strpos($d['NAMA_PENJAHIT'],' ')!==false){
                    $pp=explode(' ',$d['NAMA_PENJAHIT']);
                    $inisial_pj=strtoupper(substr($pp[0],0,1).substr($pp[1],0,1));
                }
                $delay = $idx * 0.04;
            ?>
            <tr style="animation:fadeUp 0.3s ease <?= $delay ?>s both" data-nama="<?= strtolower($d['NAMA_PENJAHIT']) ?>">
                <td><span class="id-tag"><?= htmlspecialchars($d['ID_PENJAHIT']) ?></span></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="penjahit-av"><?= $inisial_pj ?></div>
                        <span style="font-weight:700"><?= $nama ?></span>
                    </div>
                </td>
                <td>
                    <div class="upah-val"><span>Rp</span><?= number_format($d['UPAH_PER_UNIT']) ?></div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px">per unit produksi</div>
                </td>
                <td>
                    <?php if($d['PEKERJAAN_AKTIF']): ?>
                        <span style="background:var(--g100);color:var(--g700);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700">
                            🔧 <?= htmlspecialchars($d['PEKERJAAN_AKTIF']) ?>
                        </span>
                    <?php else: ?>
                        <span style="color:var(--text3);font-size:12px">— Tidak ada</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn-edit"
                        onclick="bukaModalEdit('<?= htmlspecialchars($d['ID_PENJAHIT']) ?>', '<?= $nama ?>', <?= $d['UPAH_PER_UNIT'] ?>)">
                        <i class="bi bi-pencil-fill"></i> Edit Upah
                    </button>
                    <a href="data_penjahit.php?hapus=<?= $d['ID_PENJAHIT'] ?>"
                       class="btn-edit" style="background:var(--r100);color:var(--r700);border-color:rgba(239,68,68,0.2);margin-left:6px"
                       onclick="return confirm('Hapus penjahit <?= $nama ?>?')">
                        <i class="bi bi-trash-fill"></i> Hapus
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>
</main>

<!-- ════ MODAL EDIT UPAH ════ -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-box">
        <button class="modal-close" onclick="tutupModal('modalEdit')"><i class="bi bi-x-lg"></i></button>
        <div class="modal-title"><i class="bi bi-pencil-fill"></i> Edit Upah Penjahit</div>
        <form method="POST">
            <input type="hidden" name="edit_upah" value="1">
            <input type="hidden" name="id_penjahit" id="editId">
            <div class="form-group">
                <label class="form-label-custom">Nama Penjahit</label>
                <input type="text" class="form-input-custom" id="editNama" readonly style="opacity:.7;cursor:not-allowed">
            </div>
            <div class="form-group">
                <label class="form-label-custom">Upah Baru per Unit (Rp)</label>
                <input type="number" name="upah_baru" id="editUpah" class="form-input-custom" placeholder="Contoh: 5000" min="0" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="tutupModal('modalEdit')">Batal</button>
                <button type="submit" class="btn-modal-save"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- ════ MODAL TAMBAH PENJAHIT ════ -->
<div class="modal-overlay" id="modalTambah">
    <div class="modal-box">
        <button class="modal-close" onclick="tutupModal('modalTambah')"><i class="bi bi-x-lg"></i></button>
        <div class="modal-title"><i class="bi bi-person-plus-fill"></i> Tambah Penjahit Baru</div>
        <form method="POST">
            <input type="hidden" name="tambah_penjahit" value="1">
            <div class="form-group">
                <label class="form-label-custom">Nama Lengkap Penjahit</label>
                <input type="text" name="nama_penjahit" class="form-input-custom" placeholder="Masukkan nama penjahit..." required>
            </div>
            <div class="form-group">
                <label class="form-label-custom">Upah per Unit (Rp)</label>
                <input type="number" name="upah_penjahit" class="form-input-custom" placeholder="Contoh: 5000" min="0" required>
            </div>
            <div class="form-group">
                <label class="form-label-custom">Keahlian</label>
                <input type="text" name="keahlian_penjahit" class="form-input-custom" placeholder="cth: Jahit baju, bordir, obras...">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="tutupModal('modalTambah')">Batal</button>
                <button type="submit" class="btn-modal-save"><i class="bi bi-plus-lg"></i> Tambah Penjahit</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<?php if(isset($_GET['sukses'])): ?>
<div class="toast-notif" id="toastNotif">
    <i class="bi bi-check-circle-fill"></i>
    <?= $_GET['sukses']==1 ? 'Upah berhasil diperbarui! 🎉' : ($_GET['sukses']==2 ? 'Penjahit baru berhasil ditambahkan! 🌸' : 'Penjahit berhasil dihapus! 🗑️') ?></div>
<script>setTimeout(()=>{const t=document.getElementById('toastNotif');if(t){t.style.opacity='0';t.style.transform='translateY(20px)';t.style.transition='all 0.4s ease';setTimeout(()=>t.remove(),400)}},3000)</script>
<?php endif; ?>

<script>
function bukaModalEdit(id, nama, upah) {
    document.getElementById('editId').value  = id;
    document.getElementById('editNama').value = nama;
    document.getElementById('editUpah').value = upah;
    document.getElementById('modalEdit').classList.add('show');
}
function bukaModalTambah() {
    document.getElementById('modalTambah').classList.add('show');
}
function tutupModal(id) {
    document.getElementById(id).classList.remove('show');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if(e.target===m) m.classList.remove('show'); });
});
function filterTabel(kata) {
    const rows = document.querySelectorAll('#tabelPenjahit tbody tr');
    rows.forEach(row => {
        row.style.display = row.dataset.nama.includes(kata.toLowerCase()) ? '' : 'none';
    });
}
document.addEventListener('keydown', e => {
    if(e.key==='Escape') document.querySelectorAll('.modal-overlay.show').forEach(m=>m.classList.remove('show'));
});
</script>
</body>
</html>