<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$total_pelanggan = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pelanggan"))['t'] ?? 0;
$total_aktif     = 0;
$total_nonaktif  = 0;

$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;
$aset_rusak  = 0;
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$total_notif = $notif_bayar + $notif_chat + $stok_kritis + $aset_rusak;

$nama_owner = $_SESSION['user'];
$inisial = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts   = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

// Search, filter, pagination
$search        = isset($_GET['q'])      ? trim($_GET['q'])      : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$per_page      = 10;
$page          = max(1, intval($_GET['page'] ?? 1));
$offset        = ($page - 1) * $per_page;

$conditions = [];
if ($search !== '') {
    $s = mysqli_real_escape_string($koneksi, $search);
    $conditions[] = "(NAMA_PELANGGAN LIKE '%$s%' OR ID_PELANGGAN LIKE '%$s%' OR ALAMAT_PELANGGAN LIKE '%$s%' OR NO_HP LIKE '%$s%')";
}
if ($filter_status !== '') {
    $fs = mysqli_real_escape_string($koneksi, $filter_status);
    $conditions[] = "STATUS = '$fs'";
}
$where = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$total_rows  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pelanggan $where"))['t'] ?? 0;
$total_pages = max(1, ceil($total_rows / $per_page));
$rows        = mysqli_query($koneksi, "SELECT * FROM pelanggan $where ORDER BY ID_PELANGGAN ASC LIMIT $per_page OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Pelanggan 🌸 | Konveksi Apps</title>
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
.tb-nav{display:flex;align-items:center;gap:2px;}
.tb-nav-item{display:flex;align-items:center;gap:5px;padding:7px 13px;border-radius:99px;font-size:13px;font-weight:600;color:var(--text2);text-decoration:none;transition:all var(--ease-plain);white-space:nowrap;border:1.5px solid transparent;}
.tb-nav-item:hover{background:var(--p50);color:var(--p500);}
.tb-divider{width:1px;height:24px;background:var(--border2);margin:0 4px;}
.tb-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.icon-btn{width:36px;height:36px;border-radius:10px;background:var(--p50);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;color:var(--p500);font-size:16px;transition:all var(--ease);position:relative;}
.icon-btn:hover{background:var(--p100);transform:scale(1.08);}
.icon-btn .dot{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--r500);border:2px solid var(--white);animation:blink 1.6s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
.date-pill{display:flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:600;color:var(--text2);}
.date-pill i{color:var(--p500);}

/* MAIN */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1;}
.content{padding:28px 28px 70px;max-width:1360px;}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.anim{animation:fadeUp 0.35s ease both;}

/* Page header */
.page-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:14px;}
.page-title-wrap{display:flex;align-items:center;gap:14px;}
.page-icon{width:52px;height:52px;border-radius:16px;background:linear-gradient(135deg,var(--p500),var(--p400));display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;box-shadow:0 6px 18px rgba(232,50,138,0.35);}
.page-title{font-family:'Quicksand',sans-serif;font-size:22px;font-weight:700;color:var(--text);line-height:1.1;}
.page-subtitle{font-size:12.5px;color:var(--text3);font-weight:500;margin-top:3px;}
.back-btn{display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:99px;background:var(--white);border:1.5px solid var(--border);color:var(--text2);font-size:13.5px;font-weight:700;text-decoration:none;transition:all var(--ease);}
.back-btn:hover{background:var(--p50);color:var(--p500);border-color:var(--border2);transform:translateX(-2px);}

/* Stat strip */
.stat-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:18px 22px;display:flex;align-items:center;gap:14px;transition:transform var(--ease),box-shadow var(--ease);position:relative;overflow:hidden;}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(232,50,138,0.12);}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:var(--r-lg) var(--r-lg) 0 0;}
.stat-card.sc-pink::before{background:linear-gradient(90deg,var(--p500),var(--p300));}
.stat-card.sc-green::before{background:linear-gradient(90deg,var(--g500),#86efac);}
.stat-card.sc-red::before{background:linear-gradient(90deg,var(--r500),#fca5a5);}
.stat-card.sc-blue::before{background:linear-gradient(90deg,var(--b500),#93c5fd);}
.stat-ico{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.stat-lbl{font-size:11.5px;font-weight:600;color:var(--text3);margin-bottom:3px;}
.stat-val{font-family:'Quicksand',sans-serif;font-size:24px;font-weight:700;line-height:1;}

/* Toolbar */
.toolbar{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:16px 20px;margin-bottom:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.search-wrap{flex:1;min-width:220px;position:relative;}
.search-wrap i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--p400);font-size:15px;pointer-events:none;}
.search-input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border);border-radius:99px;font-family:'Nunito',sans-serif;font-size:14px;font-weight:600;color:var(--text);background:var(--p50);outline:none;transition:border-color var(--ease-plain),box-shadow var(--ease-plain);}
.search-input::placeholder{color:var(--text3);font-weight:500;}
.search-input:focus{border-color:var(--p400);box-shadow:0 0 0 3px rgba(232,50,138,0.12);background:var(--white);}
.filter-select{padding:9px 16px;border:1.5px solid var(--border);border-radius:99px;font-family:'Nunito',sans-serif;font-size:13.5px;font-weight:600;color:var(--text2);background:var(--p50);outline:none;cursor:pointer;transition:border-color var(--ease-plain);}
.filter-select:focus{border-color:var(--p400);}
.search-btn{display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:99px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-size:13.5px;font-weight:700;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(232,50,138,0.35);transition:all var(--ease);font-family:'Nunito',sans-serif;}
.search-btn:hover{transform:translateY(-2px) scale(1.03);}
.reset-btn{display:flex;align-items:center;gap:6px;padding:9px 16px;border-radius:99px;background:var(--r100);color:var(--r700);font-size:13px;font-weight:700;border:1.5px solid #fca5a5;cursor:pointer;text-decoration:none;font-family:'Nunito',sans-serif;transition:all var(--ease-plain);}
.reset-btn:hover{background:#fca5a5;}
.result-info{font-size:12.5px;color:var(--text3);font-weight:600;white-space:nowrap;}
.result-info b{color:var(--p500);}

/* Table */
.tbl-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;}
.tbl-hd{padding:18px 24px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--p50),var(--white));}
.tbl-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.tbl-title i{color:var(--p500);}
.tbl-badge{background:var(--p50);border:1.5px solid var(--border2);border-radius:99px;padding:4px 14px;font-size:12px;font-weight:700;color:var(--p600);}
.data-table{width:100%;border-collapse:collapse;}
.data-table thead th{padding:12px 20px;font-size:11.5px;font-weight:700;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border);white-space:nowrap;}
.data-table thead th i{margin-right:5px;color:var(--p300);}
.data-table tbody td{padding:14px 20px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:14px;vertical-align:middle;}
.data-table tbody tr:last-child td{border-bottom:none;}
.data-table tbody tr{transition:background var(--ease-plain);animation:fadeUp 0.3s ease both;}
.data-table tbody tr:hover td{background:var(--p50);}
<?php for($i=1;$i<=15;$i++): ?>
.data-table tbody tr:nth-child(<?=$i?>){animation-delay:<?=($i-1)*0.03?>s;}
<?php endfor; ?>

.id-tag{display:inline-flex;align-items:center;background:linear-gradient(135deg,var(--p50),var(--v100));color:var(--p600);border:1px solid var(--border2);border-radius:8px;padding:4px 10px;font-size:12px;font-weight:700;white-space:nowrap;}
.avatar-cell{display:flex;align-items:center;gap:10px;}
.row-av{width:36px;height:36px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;box-shadow:0 2px 8px rgba(232,50,138,0.25);}
.row-name{font-weight:700;color:var(--text);}
.addr-cell{max-width:200px;color:var(--text2);}
.addr-cell span{display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;}

/* Phone & WA */
.phone-wrap{display:flex;align-items:center;gap:7px;flex-wrap:wrap;}
.phone-chip{display:inline-flex;align-items:center;gap:6px;background:var(--b100);color:var(--b700);border:1px solid rgba(59,130,246,0.2);border-radius:99px;padding:5px 13px;font-size:13px;font-weight:600;}
.phone-chip i{font-size:12px;}
.wa-btn{display:inline-flex;align-items:center;gap:5px;background:#dcfce7;color:#15803d;border:1.5px solid rgba(34,197,94,0.3);border-radius:99px;padding:5px 12px;font-size:12px;font-weight:700;text-decoration:none;transition:all var(--ease);white-space:nowrap;}
.wa-btn:hover{background:#22c55e;color:#fff;border-color:#22c55e;transform:scale(1.05);}
.wa-btn i{font-size:13px;}

/* Status badge */
.status-badge{display:inline-flex;align-items:center;gap:6px;border-radius:99px;padding:5px 13px;font-size:12px;font-weight:700;white-space:nowrap;}
.status-badge.aktif{background:var(--g100);color:var(--g700);border:1px solid rgba(34,197,94,0.25);}
.status-badge.nonaktif{background:var(--r100);color:var(--r700);border:1px solid rgba(239,68,68,0.25);}
.dot-s{width:7px;height:7px;border-radius:50%;display:inline-block;}
.aktif .dot-s{background:var(--g500);}
.nonaktif .dot-s{background:var(--r500);}

/* Action buttons */
.action-btns{display:flex;align-items:center;gap:6px;}
.act-btn{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;text-decoration:none;border:1.5px solid;transition:all var(--ease);}
.act-btn:hover{transform:scale(1.12);}
.act-view{background:var(--b100);color:var(--b700);border-color:rgba(59,130,246,0.25);}
.act-view:hover{background:var(--b500);color:#fff;border-color:var(--b500);}
.act-edit{background:var(--a100);color:var(--a700);border-color:rgba(234,179,8,0.25);}
.act-edit:hover{background:var(--a500);color:#fff;border-color:var(--a500);}
.act-del{background:var(--r100);color:var(--r700);border-color:rgba(239,68,68,0.25);}
.act-del:hover{background:var(--r500);color:#fff;border-color:var(--r500);}

/* Empty */
.empty-state{padding:64px 32px;text-align:center;}
.empty-icon{font-size:52px;color:var(--p200);margin-bottom:16px;}
.empty-title{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--text2);margin-bottom:8px;}
.empty-sub{font-size:13.5px;color:var(--text3);}

/* Pagination */
.pagination-wrap{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-top:1.5px solid var(--border);flex-wrap:wrap;gap:10px;}
.page-info{font-size:13px;color:var(--text3);font-weight:600;}
.page-info b{color:var(--p500);}
.pg-btns{display:flex;align-items:center;gap:4px;}
.pg-btn{min-width:34px;height:34px;padding:0 10px;border-radius:9px;border:1.5px solid var(--border);background:var(--white);color:var(--text2);font-size:13px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;transition:all var(--ease-plain);font-family:'Nunito',sans-serif;}
.pg-btn:hover:not(.disabled){background:var(--p50);color:var(--p500);border-color:var(--border2);}
.pg-btn.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border-color:var(--p500);box-shadow:0 3px 10px rgba(232,50,138,0.3);}
.pg-btn.disabled{opacity:0.4;pointer-events:none;}

@media(max-width:1280px){.stat-strip{grid-template-columns:repeat(2,1fr);}}
@media(max-width:900px){.sidebar{transform:translateX(-100%);}.topbar{left:0;}.main{margin-left:0;}.stat-strip{grid-template-columns:1fr;}}
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
        <div class="tb-hello">Data Pelanggan 👥</div>
        <div class="tb-sub">Kelola semua pelanggan konveksi kamu</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi</a>
        <a class="tb-nav-item" href="laporan.php"><i class="bi bi-bar-chart-line"></i> Laporan</a>
    </nav>
    <div class="tb-divider"></div>
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
            <div class="page-icon"><i class="bi bi-person-badge-fill"></i></div>
            <div>
                <div class="page-title">Data Pelanggan 🌸</div>
                <div class="page-subtitle">Total <?=number_format($total_pelanggan)?> pelanggan terdaftar</div>
            </div>
        </div>
        <a href="dashboard.php" class="back-btn"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
    </div>

    <!-- Stat Strip -->
    <div class="stat-strip anim" style="animation-delay:0.06s">
        <div class="stat-card sc-pink">
            <div class="stat-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-lbl">Total Pelanggan</div>
                <div class="stat-val" style="color:var(--p600)"><?=number_format($total_pelanggan)?></div>
            </div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-person-check-fill"></i></div>
            <div>
                <div class="stat-lbl">Pelanggan Aktif</div>
                <div class="stat-val" style="color:var(--g700)"><?=number_format($total_aktif)?></div>
            </div>
        </div>
        <div class="stat-card sc-red">
            <div class="stat-ico" style="background:var(--r100);color:var(--r500)"><i class="bi bi-person-dash-fill"></i></div>
            <div>
                <div class="stat-lbl">Tidak Aktif</div>
                <div class="stat-val" style="color:var(--r700)"><?=number_format($total_nonaktif)?></div>
            </div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-funnel-fill"></i></div>
            <div>
                <div class="stat-lbl">Hasil Filter</div>
                <div class="stat-val" style="color:var(--b700)"><?=number_format($total_rows)?></div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <form method="GET" action="">
    <div class="toolbar anim" style="animation-delay:0.1s">
        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" name="q" class="search-input" placeholder="Cari nama, ID, alamat, atau no. HP..." value="<?=htmlspecialchars($search)?>">
        </div>
        <select name="status" class="filter-select">
            <option value="">🔽 Semua Status</option>
            <option value="Aktif"       <?=$filter_status==='Aktif'      ?'selected':''?>>✅ Aktif</option>
            <option value="Tidak Aktif" <?=$filter_status==='Tidak Aktif'?'selected':''?>>❌ Tidak Aktif</option>
        </select>
        <button type="submit" class="search-btn"><i class="bi bi-search"></i> Cari</button>
        <?php if($search!==''||$filter_status!==''):?>
        <a href="pelanggan.php" class="reset-btn"><i class="bi bi-x-circle"></i> Reset</a>
        <?php endif;?>
        <div class="result-info">Total <b><?=$total_rows?></b> data ditemukan</div>
    </div>
    </form>

    <!-- Table -->
    <div class="tbl-card anim" style="animation-delay:0.14s">
        <div class="tbl-hd">
            <div class="tbl-title"><i class="bi bi-person-lines-fill"></i> Daftar Pelanggan</div>
            <span class="tbl-badge"><?=$total_rows?> data</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th><i class="bi bi-hash"></i>ID</th>
                    <th><i class="bi bi-person"></i>Nama Pelanggan</th>
                    <th><i class="bi bi-geo-alt"></i>Alamat</th>
                    <th><i class="bi bi-telephone"></i>No. HP &amp; WhatsApp</th>
                    <th><i class="bi bi-activity"></i>Status</th>
                    <th><i class="bi bi-gear"></i>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if(mysqli_num_rows($rows)>0): ?>
            <?php while($row=mysqli_fetch_assoc($rows)):
                $nama   = $row['NAMA_PELANGGAN'] ?? '-';
                $huruf  = strtoupper(substr($nama,0,1));
                $colors = ['#e8328a','#a855f7','#3b82f6','#22c55e','#f97316','#eab308','#ef4444','#06b6d4'];
                $clr    = $colors[ord($huruf)%count($colors)];
                $telp   = $row['NO_HP'] ?? '';
                $wa_num = '62'.ltrim((string)$telp,'0');
                $status = $row['STATUS'] ?? 'Aktif';
            ?>
            <tr>
                <td><span class="id-tag"><?=htmlspecialchars($row['ID_PELANGGAN'])?></span></td>
                <td>
                    <div class="avatar-cell">
                        <div class="row-av" style="background:linear-gradient(135deg,<?=$clr?>,<?=$clr?>bb)"><?=$huruf?></div>
                        <span class="row-name"><?=htmlspecialchars($nama)?></span>
                    </div>
                </td>
                <td class="addr-cell"><span><?=htmlspecialchars($row['ALAMAT_PELANGGAN']??'-')?></span></td>
                <td>
                    <div class="phone-wrap">
                        <span class="phone-chip">
                            <i class="bi bi-telephone-fill"></i>
                            <?=htmlspecialchars($telp?:'—')?>
                        </span>
                        <?php if($telp):?>
                        <a href="https://wa.me/<?=$wa_num?>" target="_blank" class="wa-btn">
                            <i class="bi bi-whatsapp"></i> WA
                        </a>
                        <?php endif;?>
                    </div>
                </td>
                <td>
                    <span class="status-badge <?=$status==='Aktif'?'aktif':'nonaktif'?>">
                        <span class="dot-s"></span>
                        <?=htmlspecialchars($status)?>
                    </span>
                </td>
                <td>
                    <div class="action-btns">
                        <a href="detail_pelanggan.php?id=<?=urlencode($row['ID_PELANGGAN'])?>" class="act-btn act-view" title="Lihat Detail"><i class="bi bi-eye"></i></a>
                        <a href="edit_pelanggan.php?id=<?=urlencode($row['ID_PELANGGAN'])?>"   class="act-btn act-edit" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="hapus_pelanggan.php?id=<?=urlencode($row['ID_PELANGGAN'])?>"  class="act-btn act-del"  title="Hapus" onclick="return confirm('Yakin hapus pelanggan ini?')"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endwhile;?>
            <?php else:?>
            <tr><td colspan="6">
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-person-x"></i></div>
                    <div class="empty-title"><?=$search||$filter_status?'Pelanggan tidak ditemukan 🔍':'Belum ada data pelanggan 🌸'?></div>
                    <div class="empty-sub"><?=$search||$filter_status?'Coba ubah kata kunci atau filter ya!':'Data pelanggan akan muncul di sini.'?></div>
                </div>
            </td></tr>
            <?php endif;?>
            </tbody>
        </table>

        <?php if($total_pages>1):?>
        <div class="pagination-wrap">
            <div class="page-info">Halaman <b><?=$page?></b> dari <b><?=$total_pages?></b> &nbsp;·&nbsp; <?=$offset+1?>–<?=min($offset+$per_page,$total_rows)?> dari <b><?=$total_rows?></b> data</div>
            <div class="pg-btns">
                <?php
                $qe=http_build_query(array_merge($_GET,['page'=>1]));
                $qs=http_build_query(array_merge($_GET,['page'=>$page-1]));
                $qn=http_build_query(array_merge($_GET,['page'=>$page+1]));
                $ql=http_build_query(array_merge($_GET,['page'=>$total_pages]));
                ?>
                <a href="?<?=$qe?>" class="pg-btn <?=$page<=1?'disabled':''?>"><i class="bi bi-chevron-double-left"></i></a>
                <a href="?<?=$qs?>" class="pg-btn <?=$page<=1?'disabled':''?>"><i class="bi bi-chevron-left"></i></a>
                <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++):
                    $qi=http_build_query(array_merge($_GET,['page'=>$i]));?>
                <a href="?<?=$qi?>" class="pg-btn <?=$i==$page?'active':''?>"><?=$i?></a>
                <?php endfor;?>
                <a href="?<?=$qn?>" class="pg-btn <?=$page>=$total_pages?'disabled':''?>"><i class="bi bi-chevron-right"></i></a>
                <a href="?<?=$ql?>" class="pg-btn <?=$page>=$total_pages?'disabled':''?>"><i class="bi bi-chevron-double-right"></i></a>
            </div>
        </div>
        <?php endif;?>
    </div>

</div>
</main>
</body>
</html>