<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($id === '') { header("Location: pelanggan.php"); exit; }

$id_esc = mysqli_real_escape_string($koneksi, $id);
$result = mysqli_query($koneksi, "SELECT * FROM pelanggan WHERE ID_PELANGGAN='$id_esc'");
if (!$result || mysqli_num_rows($result) === 0) {
    header("Location: pelanggan.php");
    exit;
}
$p = mysqli_fetch_assoc($result);

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama    = trim($_POST['nama']    ?? '');
    $alamat  = trim($_POST['alamat']  ?? '');
    $no_hp   = trim($_POST['no_hp']   ?? '');
    $status  = trim($_POST['status']  ?? 'Aktif');
    $username= trim($_POST['username']?? '');

    if ($nama   === '') $errors[] = 'Nama pelanggan tidak boleh kosong.';
    if ($alamat === '') $errors[] = 'Alamat tidak boleh kosong.';
    if ($no_hp  !== '' && !ctype_digit($no_hp)) $errors[] = 'No. HP hanya boleh berisi angka.';
    if (!in_array($status, ['Aktif','Tidak Aktif'])) $status = 'Aktif';

    if (empty($errors)) {
        $nama_esc    = mysqli_real_escape_string($koneksi, $nama);
        $alamat_esc  = mysqli_real_escape_string($koneksi, $alamat);
        $no_hp_esc   = mysqli_real_escape_string($koneksi, $no_hp);
        $status_esc  = mysqli_real_escape_string($koneksi, $status);
        $uname_esc   = mysqli_real_escape_string($koneksi, $username);

        $sql = "UPDATE pelanggan SET
                    NAMA_PELANGGAN   = '$nama_esc',
                    ALAMAT_PELANGGAN = '$alamat_esc',
                    NO_HP            = ".($no_hp!==''?"'$no_hp_esc'":"NULL").",
                    STATUS           = '$status_esc',
                    username         = ".($username!==''?"'$uname_esc'":"NULL")."
                WHERE ID_PELANGGAN   = '$id_esc'";

        if (mysqli_query($koneksi, $sql)) {
            // Refresh data
            $result = mysqli_query($koneksi, "SELECT * FROM pelanggan WHERE ID_PELANGGAN='$id_esc'");
            $p      = mysqli_fetch_assoc($result);
            $success = 'Data pelanggan berhasil diperbarui! ✅';
        } else {
            $errors[] = 'Gagal menyimpan: ' . mysqli_error($koneksi);
        }
    }
}

// Notif sidebar
$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;
$aset_rusak  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM aset WHERE KONDISI_ASET IN ('Rusak','Perlu Perbaikan')"))['t'] ?? 0;
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$total_notif = $notif_bayar + $notif_chat + $stok_kritis + $aset_rusak;

$nama_owner = $_SESSION['user'];
$inisial = strtoupper(substr($nama_owner,0,1));
if (strpos($nama_owner,' ')!==false) {
    $parts=$explode=explode(' ',$nama_owner);
    $inisial=strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

$nama_p  = $p['NAMA_PELANGGAN']    ?? '';
$alamat_p= $p['ALAMAT_PELANGGAN']  ?? '';
$no_hp_p = $p['NO_HP']             ?? '';
$status_p= $p['STATUS']            ?? 'Aktif';
$uname_p = $p['username']          ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Pelanggan | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --p50:#fff0f5;--p100:#ffd6e7;--p200:#ffadd0;--p300:#ff80b8;--p400:#f950a0;--p500:#e8328a;--p600:#cc1a73;--p700:#a8105d;
    --v100:#f3e8ff;--v300:#d8b4fe;--v500:#a855f7;
    --g100:#dcfce7;--g500:#22c55e;--g700:#15803d;
    --a100:#fef9c3;--a500:#eab308;--a700:#854d0e;
    --b100:#dbeafe;--b500:#3b82f6;--b700:#1d4ed8;
    --r100:#fee2e2;--r500:#ef4444;--r700:#991b1b;
    --o100:#ffedd5;--o500:#f97316;
    --white:#ffffff;--bg:#fff5f9;--text:#3d1a28;--text2:#7d4460;--text3:#b07090;
    --border:rgba(232,50,138,0.13);--border2:rgba(232,50,138,0.24);
    --sidebar-w:256px;--topbar-h:64px;
    --r-sm:10px;--r-md:16px;--r-lg:22px;--r-xl:28px;
    --ease:0.2s cubic-bezier(0.34,1.56,0.64,1);--ease-plain:0.17s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;font-size:14.5px;line-height:1.6;-webkit-font-smoothing:antialiased;}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(232,50,138,0.055) 1.5px,transparent 1.5px);background-size:28px 28px;pointer-events:none;z-index:0;}
::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-track{background:var(--p50);}::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px;}

/* SIDEBAR */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--white);border-right:1.5px solid var(--border);display:flex;flex-direction:column;z-index:300;overflow:hidden;}
.sidebar::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;z-index:1;animation:shimmer 3s linear infinite;}
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}
.sb-brand{display:flex;align-items:center;gap:12px;padding:0 18px;height:var(--topbar-h);border-bottom:1.5px solid var(--border);text-decoration:none;flex-shrink:0;transition:background var(--ease-plain);margin-top:4px;}
.sb-brand:hover{background:var(--p50);}
.brand-mark{width:38px;height:38px;border-radius:13px;background:linear-gradient(135deg,var(--p500) 0%,var(--p400) 50%,var(--v500) 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4),0 0 0 3px rgba(232,50,138,0.12);transition:transform var(--ease);}
.sb-brand:hover .brand-mark{transform:rotate(-8deg) scale(1.08);}
.brand-mark i{font-size:18px;color:#fff;}
.brand-name{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text);white-space:nowrap;}
.brand-sub{font-size:10px;font-weight:600;color:var(--p500);letter-spacing:0.8px;text-transform:uppercase;margin-top:1px;}
.sb-owner{margin:12px 12px 6px;padding:12px 14px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:var(--r-lg);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.owner-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;position:relative;box-shadow:0 3px 10px rgba(232,50,138,0.35);}
.owner-av::after{content:'';position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:var(--g500);border:2px solid var(--white);}
.owner-name{font-size:13.5px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.owner-role{font-size:11px;color:var(--p500);font-weight:600;}
.sb-nav{flex:1;overflow-y:auto;padding:6px 10px 10px;}
.nav-group-label{font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--text3);padding:14px 10px 4px;display:flex;align-items:center;gap:6px;}
.nav-group-label::after{content:'✦';font-size:7px;color:var(--p300);}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:var(--r-sm);text-decoration:none;color:var(--text2);font-size:14px;font-weight:600;transition:background var(--ease-plain),color var(--ease-plain),transform var(--ease-plain);margin-bottom:2px;position:relative;white-space:nowrap;}
.nav-item i{font-size:17px;width:19px;text-align:center;flex-shrink:0;color:var(--text3);transition:color var(--ease-plain);}
.nav-item:hover{background:var(--p50);color:var(--p500);transform:translateX(2px);}
.nav-item:hover i{color:var(--p400);}
.nav-item.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-weight:700;box-shadow:0 4px 16px rgba(232,50,138,0.35);}
.nav-item.active i{color:rgba(255,255,255,0.9);}
.nav-item.active::after{content:'';position:absolute;right:10px;width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.6);}
.nav-pill{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0;}
.pill-red{background:var(--r500);}.pill-orange{background:var(--o500);}.pill-pink{background:var(--p500);}
.nav-item.active .nav-pill{background:rgba(255,255,255,0.3);}
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0;}
.nav-item.logout{color:var(--r700);}
.nav-item.logout i{color:var(--r500);}
.nav-item.logout:hover{background:var(--r100);color:var(--r700);transform:none;}

/* TOPBAR */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(12px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px;}
.topbar::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;animation:shimmer 3s linear infinite;}
.tb-greeting{flex:1;}
.tb-hello{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text);}
.tb-sub{font-size:12px;color:var(--text3);font-weight:500;margin-top:1px;}
.tb-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.icon-btn{width:36px;height:36px;border-radius:10px;background:var(--p50);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;color:var(--p500);font-size:16px;transition:all var(--ease);position:relative;}
.icon-btn:hover{background:var(--p100);transform:scale(1.08);}
.icon-btn .dot{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--r500);border:2px solid var(--white);animation:blink 1.6s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
.date-pill{display:flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:600;color:var(--text2);}
.date-pill i{color:var(--p500);}

/* MAIN */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1;}
.content{padding:28px 28px 70px;max-width:800px;}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.anim{animation:fadeUp 0.35s ease both;}

/* Page header */
.page-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:14px;}
.page-title-wrap{display:flex;align-items:center;gap:14px;}
.page-icon{width:52px;height:52px;border-radius:16px;background:linear-gradient(135deg,var(--a500),var(--a700));display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;box-shadow:0 6px 18px rgba(234,179,8,0.3);}
.page-title{font-family:'Quicksand',sans-serif;font-size:22px;font-weight:700;color:var(--text);line-height:1.1;}
.page-subtitle{font-size:12.5px;color:var(--text3);font-weight:500;margin-top:3px;}
.hd-actions{display:flex;gap:10px;}
.back-btn{display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:99px;background:var(--white);border:1.5px solid var(--border);color:var(--text2);font-size:13.5px;font-weight:700;text-decoration:none;transition:all var(--ease);}
.back-btn:hover{background:var(--p50);color:var(--p500);border-color:var(--border2);transform:translateX(-2px);}
.detail-btn{display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:99px;background:var(--b100);border:1.5px solid rgba(59,130,246,0.25);color:var(--b700);font-size:13.5px;font-weight:700;text-decoration:none;transition:all var(--ease);}
.detail-btn:hover{background:var(--b500);color:#fff;border-color:var(--b500);}

/* Alert */
.alert{border-radius:var(--r-md);padding:14px 18px;margin-bottom:20px;display:flex;align-items:flex-start;gap:12px;font-size:14px;font-weight:600;}
.alert i{font-size:18px;flex-shrink:0;margin-top:1px;}
.alert-success{background:var(--g100);color:var(--g700);border:1.5px solid rgba(34,197,94,0.3);}
.alert-error{background:var(--r100);color:var(--r700);border:1.5px solid rgba(239,68,68,0.3);}

/* Form card */
.form-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;}
.form-card-hd{padding:18px 24px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;gap:10px;}
.form-card-hd i{color:var(--p500);font-size:18px;}
.form-card-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);}
.form-body{padding:24px;}

/* ID badge */
.id-badge{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border2);border-radius:var(--r-md);padding:10px 18px;margin-bottom:22px;}
.id-badge i{color:var(--p500);}
.id-badge span{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--p600);}

/* Form grid */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
.form-group{display:flex;flex-direction:column;gap:7px;}
.form-group.full{grid-column:1/-1;}
label{font-size:12.5px;font-weight:700;color:var(--text2);display:flex;align-items:center;gap:6px;}
label i{color:var(--p400);font-size:14px;}
.form-control{width:100%;padding:11px 16px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:'Nunito',sans-serif;font-size:14px;font-weight:600;color:var(--text);background:var(--p50);outline:none;transition:border-color var(--ease-plain),box-shadow var(--ease-plain);}
.form-control:focus{border-color:var(--p400);box-shadow:0 0 0 3px rgba(232,50,138,0.12);background:var(--white);}
.form-control::placeholder{color:var(--text3);font-weight:500;}
.form-control[readonly]{background:#f8f8f8;color:var(--text3);cursor:not-allowed;}
textarea.form-control{resize:vertical;min-height:90px;}
select.form-control{cursor:pointer;}
.form-hint{font-size:11.5px;color:var(--text3);font-weight:500;}

/* Status toggle */
.status-toggle{display:flex;gap:10px;}
.status-opt{flex:1;position:relative;}
.status-opt input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
.status-opt label{display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border-radius:var(--r-md);border:2px solid var(--border);background:var(--p50);cursor:pointer;font-size:14px;font-weight:700;transition:all var(--ease-plain);color:var(--text3);}
.status-opt input[type=radio]:checked + label.opt-aktif{background:var(--g100);color:var(--g700);border-color:var(--g500);box-shadow:0 0 0 3px rgba(34,197,94,0.12);}
.status-opt input[type=radio]:checked + label.opt-nonaktif{background:var(--r100);color:var(--r700);border-color:var(--r500);box-shadow:0 0 0 3px rgba(239,68,68,0.12);}
.status-opt label:hover{border-color:var(--p300);}

/* Form footer */
.form-footer{padding:20px 24px;border-top:1.5px solid var(--border);background:var(--p50);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.save-btn{display:flex;align-items:center;gap:8px;padding:11px 28px;border-radius:99px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-size:14px;font-weight:700;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(232,50,138,0.35);transition:all var(--ease);font-family:'Nunito',sans-serif;}
.save-btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(232,50,138,0.45);}
.cancel-btn{display:flex;align-items:center;gap:7px;padding:11px 22px;border-radius:99px;background:var(--white);border:1.5px solid var(--border);color:var(--text2);font-size:14px;font-weight:700;text-decoration:none;transition:all var(--ease);}
.cancel-btn:hover{background:var(--r100);color:var(--r700);border-color:#fca5a5;}

@media(max-width:900px){.sidebar{transform:translateX(-100%);}.topbar{left:0;}.main{margin-left:0;}.form-grid{grid-template-columns:1fr;}}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(0.85);opacity:0.6}}
.pulse{animation:pulse 1.8s ease-in-out infinite;}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div>
            <div class="brand-name">Konveksi Apps</div>
            <div class="brand-sub">Panel Owner</div>
        </div>
    </a>
    <div class="sb-owner">
        <div class="owner-av"><?=$inisial?></div>
        <div style="overflow:hidden;min-width:0">
            <div class="owner-name"><?=htmlspecialchars($nama_owner)?></div>
            <div class="owner-role">✨ Owner · Administrator</div>
        </div>
    </div>
    <nav class="sb-nav">
        <a class="nav-item" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
        <div class="nav-group-label">Manajemen Data</div>
        <a class="nav-item" href="kelola_produk.php"><i class="bi bi-box-seam"></i> Produk</a>
        <a class="nav-item" href="kelola_bahan.php">
            <i class="bi bi-basket"></i> Bahan Baku
            <?php if($stok_kritis>0):?><span class="nav-pill pill-orange pulse"><?=$stok_kritis?></span><?php endif;?>
        </a>
        <a class="nav-item" href="kelola_aset.php">
            <i class="bi bi-building-gear"></i> Aset &amp; Inventaris
            <?php if($aset_rusak>0):?><span class="nav-pill pill-orange pulse"><?=$aset_rusak?></span><?php endif;?>
        </a>
        <a class="nav-item" href="data_penjahit.php"><i class="bi bi-people"></i> Data Penjahit</a>
        <a class="nav-item active" href="pelanggan.php"><i class="bi bi-person-badge"></i> Data Pelanggan</a>
        <a class="nav-item" href="supplier.php"><i class="bi bi-truck"></i> Data Supplier</a>
        <div class="nav-group-label">Operasional</div>
        <a class="nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi Aktif</a>
        <a class="nav-item" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="nav-item" href="konfirmasi_pembayaran.php">
            <i class="bi bi-credit-card-2-front"></i> Konfirmasi Bayar
            <?php if($notif_bayar>0):?><span class="nav-pill pill-pink pulse"><?=$notif_bayar?></span><?php endif;?>
        </a>
        <a class="nav-item" href="chat.php">
            <i class="bi bi-chat-dots-fill"></i> Inbox Chat
            <?php if($notif_chat>0):?><span class="nav-pill pill-red pulse"><?=$notif_chat?></span><?php endif;?>
        </a>
        <div class="nav-group-label">Laporan</div>
        <a class="nav-item" href="laporan.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Keuangan</a>
    </nav>
    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Edit Pelanggan ✏️</div>
        <div class="tb-sub"><?=htmlspecialchars($nama_p)?> · <?=htmlspecialchars($p['ID_PELANGGAN'])?></div>
    </div>
    <div class="tb-actions">
        <a href="<?=$total_notif>0?'konfirmasi_pembayaran.php':'#'?>" class="icon-btn">
            <i class="bi bi-bell-fill"></i>
            <?php if($total_notif>0):?><span class="dot"></span><?php endif;?>
        </a>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?=date('d M Y')?></div>
    </div>
</header>

<!-- MAIN -->
<main class="main">
<div class="content">

    <!-- Page Header -->
    <div class="page-hd anim">
        <div class="page-title-wrap">
            <div class="page-icon"><i class="bi bi-pencil-square"></i></div>
            <div>
                <div class="page-title">Edit Data Pelanggan</div>
                <div class="page-subtitle">Perbarui informasi pelanggan</div>
            </div>
        </div>
        <div class="hd-actions">
            <a href="detail_pelanggan.php?id=<?=urlencode($p['ID_PELANGGAN'])?>" class="detail-btn"><i class="bi bi-eye"></i> Lihat Detail</a>
            <a href="pelanggan.php" class="back-btn"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>
    </div>

    <!-- Alert -->
    <?php if($success):?>
    <div class="alert alert-success anim"><i class="bi bi-check-circle-fill"></i><span><?=htmlspecialchars($success)?></span></div>
    <?php endif;?>
    <?php if(!empty($errors)):?>
    <div class="alert alert-error anim">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><?php foreach($errors as $e):?><div>• <?=htmlspecialchars($e)?></div><?php endforeach;?></div>
    </div>
    <?php endif;?>

    <!-- Form Card -->
    <div class="form-card anim" style="animation-delay:0.06s">
        <div class="form-card-hd">
            <i class="bi bi-person-gear"></i>
            <div class="form-card-title">Formulir Edit Pelanggan</div>
        </div>
        <form method="POST" action="">
        <div class="form-body">

            <!-- ID (readonly) -->
            <div class="id-badge">
                <i class="bi bi-fingerprint"></i>
                <span>ID: <?=htmlspecialchars($p['ID_PELANGGAN'])?></span>
            </div>

            <div class="form-grid">
                <!-- Nama -->
                <div class="form-group full">
                    <label><i class="bi bi-person-fill"></i> Nama Pelanggan <span style="color:var(--r500)">*</span></label>
                    <input type="text" name="nama" class="form-control"
                           value="<?=htmlspecialchars(isset($_POST['nama'])?$_POST['nama']:$nama_p)?>"
                           placeholder="Masukkan nama lengkap..." maxlength="25" required>
                    <span class="form-hint">Maksimal 25 karakter</span>
                </div>

                <!-- Alamat -->
                <div class="form-group full">
                    <label><i class="bi bi-geo-alt-fill"></i> Alamat <span style="color:var(--r500)">*</span></label>
                    <textarea name="alamat" class="form-control"
                              placeholder="Masukkan alamat lengkap..." maxlength="100" required><?=htmlspecialchars(isset($_POST['alamat'])?$_POST['alamat']:$alamat_p)?></textarea>
                    <span class="form-hint">Maksimal 100 karakter</span>
                </div>

                <!-- No HP -->
                <div class="form-group">
                    <label><i class="bi bi-telephone-fill"></i> No. HP</label>
                    <input type="text" name="no_hp" class="form-control"
                           value="<?=htmlspecialchars(isset($_POST['no_hp'])?$_POST['no_hp']:$no_hp_p)?>"
                           placeholder="cth: 081234567890" maxlength="15"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    <span class="form-hint">Angka saja, tanpa tanda +</span>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label><i class="bi bi-person-badge-fill"></i> Username</label>
                    <input type="text" name="username" class="form-control"
                           value="<?=htmlspecialchars(isset($_POST['username'])?$_POST['username']:$uname_p)?>"
                           placeholder="Username login pelanggan..." maxlength="50">
                </div>

                <!-- Status -->
                <div class="form-group full">
                    <label><i class="bi bi-activity"></i> Status Pelanggan</label>
                    <div class="status-toggle">
                        <?php $cur_status=isset($_POST['status'])?$_POST['status']:$status_p; ?>
                        <div class="status-opt">
                            <input type="radio" name="status" id="st_aktif" value="Aktif" <?=$cur_status==='Aktif'?'checked':''?>>
                            <label for="st_aktif" class="opt-aktif">
                                <i class="bi bi-check-circle-fill"></i> Aktif
                            </label>
                        </div>
                        <div class="status-opt">
                            <input type="radio" name="status" id="st_nonaktif" value="Tidak Aktif" <?=$cur_status==='Tidak Aktif'?'checked':''?>>
                            <label for="st_nonaktif" class="opt-nonaktif">
                                <i class="bi bi-x-circle-fill"></i> Tidak Aktif
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-footer">
            <a href="pelanggan.php" class="cancel-btn"><i class="bi bi-x-lg"></i> Batal</a>
            <button type="submit" class="save-btn"><i class="bi bi-floppy-fill"></i> Simpan Perubahan</button>
        </div>
        </form>
    </div>

</div>
</main>
</body>
</html>