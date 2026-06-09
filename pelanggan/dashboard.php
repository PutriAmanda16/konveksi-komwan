<?php
session_start();
include "../config/koneksi.php";
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'pelanggan') {
    header("Location: ../index.php"); exit;
}
$id_pelanggan = $_SESSION['id'];
$nama_user    = $_SESSION['user'];

$inisial = strtoupper(substr($nama_user, 0, 1));
if (strpos($nama_user, ' ') !== false) {
    $pp = explode(' ', $nama_user);
    $inisial = strtoupper(substr($pp[0],0,1).substr($pp[1],0,1));
}

$total_pesan   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan'"))['n'];
$total_pending = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Pending'"))['n'];
$total_proses  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Proses'"))['n'];
$total_selesai = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Selesai'"))['n'];

$q_notif    = mysqli_query($koneksi, "SELECT ID_PESANAN, WAKTU_PESAN FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Pending' ORDER BY WAKTU_PESAN DESC LIMIT 5");
$notif_list = [];
while ($n = mysqli_fetch_assoc($q_notif)) $notif_list[] = $n;
$notif_count = count($notif_list);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Pelanggan 🌸 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --p50:#fff0f5; --p100:#ffd6e7; --p200:#ffadd0; --p300:#ff80b8;
    --p400:#f950a0; --p500:#e8328a; --p600:#cc1a73; --p700:#a8105d;
    --v100:#f3e8ff; --v300:#d8b4fe; --v500:#a855f7;
    --g100:#dcfce7; --g500:#22c55e; --g700:#15803d;
    --a100:#fef9c3; --a500:#eab308; --a700:#854d0e;
    --b100:#dbeafe; --b500:#3b82f6; --b700:#1d4ed8;
    --r100:#fee2e2; --r500:#ef4444; --r700:#991b1b;
    --o100:#ffedd5; --o500:#f97316;
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

.sb-owner{margin:12px 12px 6px;padding:12px 14px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:22px;display:flex;align-items:center;gap:10px;flex-shrink:0}
.owner-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;position:relative;box-shadow:0 3px 10px rgba(232,50,138,0.35)}
.owner-av::after{content:'';position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:var(--g500);border:2px solid var(--white)}
.owner-name{font-size:13.5px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.owner-role{font-size:11px;color:var(--p500);font-weight:600}

/* sidebar stats mini */
.sb-stats{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:0 12px 6px;flex-shrink:0}
.sb-stat{background:var(--white);border:1.5px solid var(--border);border-radius:12px;padding:8px 10px;text-align:center}
.sb-stat-n{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--text)}
.sb-stat-l{font-size:9.5px;color:var(--text3);font-weight:600;margin-top:1px}

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
.pill-new{background:var(--v500)} .pill-red{background:var(--r500)} .pill-orange{background:var(--o500)}
.nav-item.active .nav-pill{background:rgba(255,255,255,0.3)}
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0}
.nav-item.logout{color:var(--r700)} .nav-item.logout i{color:var(--r500)} .nav-item.logout:hover{background:var(--r100);color:var(--r700);transform:none}

/* ── TOPBAR ── */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(12px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px}
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
.date-pill{display:flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:600;color:var(--text2)}
.date-pill i{color:var(--p500)}

/* notif bell */
.notif-wrap{position:relative}
.icon-btn{width:36px;height:36px;border-radius:10px;background:var(--p50);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--p500);font-size:16px;transition:all var(--ease);position:relative}
.icon-btn:hover{background:var(--p100);transform:scale(1.08)}
.icon-btn .dot{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--r500);border:2px solid var(--white);animation:blink 1.6s ease-in-out infinite}
.notif-dropdown{display:none;position:absolute;top:46px;right:0;width:300px;background:var(--white);border:1.5px solid var(--border);border-radius:16px;box-shadow:0 12px 36px rgba(232,50,138,0.15);z-index:9999;overflow:hidden}
.notif-dropdown.show{display:block;animation:fadeUp .2s ease}
.notif-hd{padding:14px 18px;font-size:13.5px;font-weight:700;color:var(--text);border-bottom:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--p50),var(--white))}
.notif-badge-sm{font-size:10px;font-weight:800;background:var(--p500);color:#fff;padding:2px 9px;border-radius:99px}
.notif-item-row{display:flex;align-items:center;gap:10px;padding:12px 18px;border-bottom:1px solid rgba(232,50,138,0.06);text-decoration:none;transition:background var(--ease-plain)}
.notif-item-row:hover{background:var(--p50)}
.notif-item-row:last-child{border-bottom:none}
.notif-ico-sm{width:34px;height:34px;border-radius:10px;background:var(--p50);color:var(--p500);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.notif-text{font-size:12.5px;color:var(--text);font-weight:600}
.notif-time{font-size:10.5px;color:var(--text3);margin-top:2px}
.notif-empty-row{padding:24px;text-align:center;font-size:13px;color:var(--text3)}
.notif-empty-row i{font-size:24px;color:var(--p200);display:block;margin-bottom:8px}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1}
.content{padding:28px 28px 100px;max-width:1200px}

/* ── ALERT ── */
.alert-banner{display:flex;align-items:center;gap:14px;padding:14px 20px;border-radius:16px;margin-bottom:22px;background:var(--white);border:1.5px solid var(--p200);box-shadow:0 4px 16px rgba(232,50,138,0.1);animation:fadeUp .3s ease both}
.ab-ico{width:42px;height:42px;border-radius:12px;background:var(--p50);color:var(--p500);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ab-text{flex:1;font-size:13.5px;font-weight:500;color:var(--text2)}
.ab-text b{color:var(--text);font-weight:700}
.ab-btn{display:flex;align-items:center;gap:5px;padding:8px 16px;border-radius:99px;font-size:12.5px;font-weight:700;background:var(--p500);color:#fff;text-decoration:none;transition:all var(--ease-plain);white-space:nowrap}
.ab-btn:hover{background:var(--p600);color:#fff}

/* ── STAT CARDS ── */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:26px}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:20px;padding:20px 22px;position:relative;overflow:hidden;transition:transform var(--ease),box-shadow var(--ease);animation:fadeUp .35s ease both}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 14px 36px rgba(232,50,138,0.12)}
.stat-stripe{position:absolute;top:0;left:0;right:0;height:4px;border-radius:20px 20px 0 0}
.stat-blob{position:absolute;right:-16px;bottom:-16px;width:72px;height:72px;border-radius:50%;opacity:.07}
.stat-ico{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px}
.stat-label{font-size:12px;font-weight:600;color:var(--text2);margin-bottom:4px}
.stat-val{font-family:'Quicksand',sans-serif;font-size:26px;font-weight:700;line-height:1.1;margin-bottom:2px}
.stat-note{font-size:11px;color:var(--text3);font-weight:500}

/* ── SEC HEADER ── */
.sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.sec-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.sec-dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:inline-block;box-shadow:0 0 0 3px rgba(232,50,138,0.15);flex-shrink:0}
.see-all{display:flex;align-items:center;gap:5px;font-size:12.5px;font-weight:700;color:var(--p500);text-decoration:none;padding:6px 14px;border:1.5px solid var(--border2);border-radius:99px;background:var(--p50);transition:all var(--ease)}
.see-all:hover{background:var(--p500);color:#fff;border-color:var(--p500)}

/* ── KATALOG ── */
.product-scroll{display:flex;overflow-x:auto;gap:14px;padding:4px 2px 16px;scrollbar-width:thin;scrollbar-color:var(--p200) transparent}
.product-scroll::-webkit-scrollbar{height:4px} .product-scroll::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px}
.product-card{min-width:190px;background:var(--white);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;flex-shrink:0;transition:all var(--ease)}
.product-card:hover{transform:translateY(-5px);box-shadow:0 14px 32px rgba(232,50,138,0.14);border-color:var(--border2)}
.product-img{height:110px;background:var(--p50);display:flex;align-items:center;justify-content:center;overflow:hidden}
.product-img img{width:100%;height:100%;object-fit:cover}
.product-placeholder{display:flex;flex-direction:column;align-items:center;gap:4px;color:var(--p200)}
.product-placeholder i{font-size:26px} .product-placeholder span{font-size:10px;color:var(--text3)}
.product-body{padding:12px 14px}
.badge-bahan{display:inline-block;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:99px;padding:2px 10px;font-size:10px;font-weight:700;margin-bottom:6px}
.product-name{font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px}
.product-ukuran{font-size:11px;color:var(--text3);margin-bottom:9px}
.product-footer{display:flex;align-items:center;justify-content:space-between;padding-top:9px;border-top:1.5px solid var(--border)}
.product-price{font-family:'Quicksand',sans-serif;font-size:14px;font-weight:700;color:var(--p600)}
.btn-pesan-ico{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;text-decoration:none;box-shadow:0 3px 10px rgba(232,50,138,0.3);transition:all var(--ease)}
.btn-pesan-ico:hover{transform:scale(1.15) rotate(90deg);color:#fff}

/* ── TABLE ── */
.tbl-card{background:var(--white);border:1.5px solid var(--border);border-radius:22px;overflow:hidden;box-shadow:0 4px 24px rgba(232,50,138,0.06);animation:fadeUp .4s ease both}
.tbl-hd{padding:16px 24px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;gap:10px}
.tbl-hd-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.tbl-hd-title i{color:var(--p500)}
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{padding:11px 20px;font-size:11px;font-weight:800;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border);letter-spacing:.4px;text-transform:uppercase}
.data-table tbody td{padding:13px 20px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:13.5px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr:hover td{background:var(--p50)}
.id-tag{display:inline-flex;align-items:center;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:8px;padding:3px 10px;font-size:12px;font-weight:700;font-family:'Quicksand',sans-serif}
.badge-pill{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:99px;font-size:12px;font-weight:700}
.bp-green{background:var(--g100);color:var(--g700)}
.bp-yellow{background:var(--a100);color:var(--a700)}
.bp-blue{background:var(--b100);color:var(--b700)}
.btn-aksi{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:99px;font-size:12.5px;font-weight:700;text-decoration:none;transition:all var(--ease-plain);border:1.5px solid}
.btn-bayar{background:var(--p500);color:#fff;border-color:var(--p500)}
.btn-bayar:hover{background:var(--p600);color:#fff}
.btn-nota{background:var(--white);color:var(--text2);border-color:var(--border2)}
.btn-nota:hover{background:var(--p50);color:var(--p500)}

/* ── CTA CARD ── */
.cta-card{border-radius:22px;padding:28px 24px;background:linear-gradient(135deg,var(--p500),var(--p400),var(--v500));color:#fff;text-align:center;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:0 8px 28px rgba(232,50,138,0.35);position:relative;overflow:hidden}
.cta-card::before{content:'';position:absolute;right:-30px;top:-30px;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,0.09)}
.cta-card::after{content:'';position:absolute;left:10px;bottom:-40px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06)}
.cta-ico{width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 14px;position:relative;z-index:1}
.cta-title{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;margin-bottom:8px;position:relative;z-index:1}
.cta-sub{font-size:12.5px;opacity:.85;margin-bottom:18px;line-height:1.5;position:relative;z-index:1}
.btn-cta{background:var(--white);color:var(--p600);border:none;border-radius:99px;padding:10px 28px;font-size:13.5px;font-weight:800;font-family:'Nunito',sans-serif;text-decoration:none;display:inline-block;transition:all var(--ease);box-shadow:0 4px 14px rgba(0,0,0,0.15);position:relative;z-index:1}
.btn-cta:hover{transform:translateY(-2px) scale(1.04);color:var(--p600)}

/* ── EMPTY STATE ── */
.empty-row td{text-align:center;padding:36px!important}
.empty-ico{font-size:28px;color:var(--p200);display:block;margin-bottom:8px}

/* ── FLOATING BUTTONS ── */
.chat-btn{position:fixed;bottom:92px;right:28px;z-index:9999;width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--p400));display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px rgba(232,50,138,0.45);transition:all var(--ease);text-decoration:none}
.chat-btn:hover{transform:scale(1.12) rotate(-8deg)}
.wa-btn{position:fixed;bottom:28px;right:28px;z-index:9999;width:52px;height:52px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px rgba(37,211,102,0.45);transition:all var(--ease);text-decoration:none}
.wa-btn:hover{transform:scale(1.12)}
</style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div><div class="brand-name">Konveksi Apps</div><div class="brand-sub">Portal Pelanggan</div></div>
    </a>

    <div class="sb-owner">
        <div class="owner-av"><?= $inisial ?></div>
        <div style="overflow:hidden;min-width:0">
            <div class="owner-name"><?= htmlspecialchars($nama_user) ?></div>
            <div class="owner-role">✨ Pelanggan</div>
        </div>
    </div>

    <div class="sb-stats">
        <div class="sb-stat">
            <div class="sb-stat-n"><?= $total_pesan ?></div>
            <div class="sb-stat-l">Pesanan</div>
        </div>
        <div class="sb-stat">
            <div class="sb-stat-n"><?= $total_selesai ?></div>
            <div class="sb-stat-l">Selesai</div>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="nav-group-label">Menu Utama</div>
        <a class="nav-item active" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
        <a class="nav-item" href="pesan.php">
            <i class="bi bi-cart-plus-fill"></i> Pesan Produk
            <span class="nav-pill pill-new">Baru</span>
        </a>
        <a class="nav-item" href="status_pesanan.php">
            <i class="bi bi-clock-history"></i> Status Pesanan
            <?php if($total_pending > 0): ?>
            <span class="nav-pill pill-red pulse"><?= $total_pending ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-item" href="pengiriman.php">
            <i class="bi bi-truck"></i> Pengiriman
            <?php if ($total_proses > 0): ?>
            <span class="nav-pill pill-orange pulse"><?= $total_proses ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-item" href="chat.php"><i class="bi bi-chat-dots-fill"></i> Live Chat</a>
    </nav>

    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Halo, <?= htmlspecialchars($nama_user) ?>! 🌸</div>
        <div class="tb-sub">Selamat datang kembali — senang melihatmu lagi 💕</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item active" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="pesan.php"><i class="bi bi-cart-plus"></i> Pesan</a>
        <a class="tb-nav-item" href="status_pesanan.php"><i class="bi bi-clock-history"></i> Status</a>
        <a class="tb-nav-item" href="pengiriman.php"><i class="bi bi-truck"></i> Pengiriman</a>
    </nav>
    <div class="tb-divider"></div>
    <div class="tb-actions">
        <!-- Bell Notif -->
        <div class="notif-wrap">
            <div class="icon-btn" onclick="toggleNotif()">
                <i class="bi bi-bell-fill"></i>
                <?php if($notif_count > 0): ?><span class="dot"></span><?php endif; ?>
            </div>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-hd">
                    Notifikasi
                    <?php if($notif_count > 0): ?>
                    <span class="notif-badge-sm"><?= $notif_count ?> belum bayar</span>
                    <?php endif; ?>
                </div>
                <?php if($notif_count > 0): ?>
                    <?php foreach($notif_list as $nl): ?>
                    <a href="bayar.php?id=<?= $nl['ID_PESANAN'] ?>" class="notif-item-row">
                        <div class="notif-ico-sm"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="notif-text">Pesanan <?= $nl['ID_PESANAN'] ?> belum dibayar</div>
                            <div class="notif-time"><?= date('d M Y, H:i', strtotime($nl['WAKTU_PESAN'])) ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notif-empty-row">
                        <i class="bi bi-check-circle-fill" style="color:var(--g500)"></i>
                        Semua pesanan sudah dibayar! 🌸
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- ALERT banner jika ada pending -->
    <?php if($notif_count > 0): ?>
    <div class="alert-banner">
        <div class="ab-ico"><i class="bi bi-credit-card-2-front-fill"></i></div>
        <div class="ab-text">
            Kamu punya <b><?= $notif_count ?> pesanan</b> yang belum dibayar. Yuk segera selesaikan pembayarannya!
        </div>
        <a href="status_pesanan.php" class="ab-btn">Bayar Sekarang <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stat-grid">
        <div class="stat-card" style="animation-delay:.05s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--p500),var(--p300))"></div>
            <div class="stat-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-bag-heart-fill"></i></div>
            <div class="stat-label">Total Pesanan</div>
            <div class="stat-val" style="color:var(--p600)"><?= $total_pesan ?></div>
            <div class="stat-note">Semua pesanan kamu</div>
            <div class="stat-blob" style="background:var(--p500)"></div>
        </div>
        <div class="stat-card" style="animation-delay:.1s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--a500),#fcd34d)"></div>
            <div class="stat-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-label">Menunggu Bayar</div>
            <div class="stat-val" style="color:var(--a700)"><?= $total_pending ?></div>
            <div class="stat-note">Perlu diselesaikan</div>
            <div class="stat-blob" style="background:var(--a500)"></div>
        </div>
        <div class="stat-card" style="animation-delay:.15s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--b500),#93c5fd)"></div>
            <div class="stat-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-arrow-repeat"></i></div>
            <div class="stat-label">Sedang Diproses</div>
            <div class="stat-val" style="color:var(--b700)"><?= $total_proses ?></div>
            <div class="stat-note">Sedang dikerjakan</div>
            <div class="stat-blob" style="background:var(--b500)"></div>
        </div>
        <div class="stat-card" style="animation-delay:.2s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--g500),#86efac)"></div>
            <div class="stat-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-patch-check-fill"></i></div>
            <div class="stat-label">Pesanan Selesai</div>
            <div class="stat-val" style="color:var(--g700)"><?= $total_selesai ?></div>
            <div class="stat-note">Sudah rampung 🎉</div>
            <div class="stat-blob" style="background:var(--g500)"></div>
        </div>
    </div>

    <!-- KATALOG -->
    <div class="sec-hd">
        <div class="sec-title"><span class="sec-dot"></span> Katalog Produk Kami 👗</div>
        <a href="pesan.php" class="see-all">Lihat Semua <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="product-scroll">
    <?php
    $q_produk = mysqli_query($koneksi, "SELECT * FROM produk");
    while($prd = mysqli_fetch_assoc($q_produk)):
        $foto_file = "../uploads/produk/" . $prd['FOTO_PRODUK'];
        $foto_url  = "/uploads/produk/" . ($prd['FOTO_PRODUK'] ?? '');

        $ada_foto = !empty($prd['FOTO_PRODUK'] ?? '');
    ?>
    <div class="product-card">
        <div class="product-img">
            <?php if($ada_foto): ?>
                <img src="<?= $foto_url ?>" alt="<?= $prd['NAMA_PRODUK'] ?>">
            <?php else: ?>
                <div class="product-placeholder">
                    <i class="bi bi-image"></i>
                    <span>Belum ada foto</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="product-body">
            <span class="badge-bahan"><?= htmlspecialchars($prd['JENIS_BAHAN']) ?></span>
            <div class="product-name"><?= htmlspecialchars($prd['NAMA_PRODUK']) ?></div>
            <div class="product-ukuran">Ukuran: <?= htmlspecialchars($prd['UKURAN']) ?></div>
            <div class="product-footer">
                <span class="product-price">Rp <?= number_format($prd['HARGA'], 0, ',', '.') ?></span>
                <a href="pesan.php?id=<?= $prd['ID_PRODUK'] ?>" class="btn-pesan-ico" title="Pesan">
                    <i class="bi bi-plus-lg"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
    </div>

    <!-- RIWAYAT + CTA -->
    <div class="sec-hd" style="margin-top:8px">
        <div class="sec-title"><span class="sec-dot"></span> Riwayat Pesanan Terakhir 🛍️</div>
        <a href="status_pesanan.php" class="see-all">Lihat Semua <i class="bi bi-arrow-right"></i></a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start">
        <div class="tbl-card">
            <div class="tbl-hd">
                <div class="tbl-hd-title"><i class="bi bi-bag-heart-fill"></i> Pesanan Terbaru</div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $q_pesanan = mysqli_query($koneksi, "SELECT * FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' ORDER BY WAKTU_PESAN DESC LIMIT 5");
                if(mysqli_num_rows($q_pesanan) > 0):
                    while($p = mysqli_fetch_assoc($q_pesanan)):
                        $st = $p['STATUS'];
                        $bp = ($st=='Selesai') ? 'bp-green' : (($st=='Proses') ? 'bp-blue' : 'bp-yellow');
                        $ic = ($st=='Selesai') ? 'check-circle-fill' : (($st=='Proses') ? 'arrow-repeat' : 'clock');
                ?>
                <tr>
                    <td><span class="id-tag"><?= $p['ID_PESANAN'] ?></span></td>
                    <td style="color:var(--text2)"><?= date('d M Y', strtotime($p['WAKTU_PESAN'])) ?></td>
                    <td><span class="badge-pill <?= $bp ?>"><i class="bi bi-<?= $ic ?>"></i> <?= $st ?></span></td>
                    <td>
                        <?php if($st == 'Pending'): ?>
                        <a href="bayar.php?id=<?= $p['ID_PESANAN'] ?>" class="btn-aksi btn-bayar">
                            <i class="bi bi-credit-card"></i> Bayar
                        </a>
                        <?php else: ?>
                        <a href="nota.php?id=<?= $p['ID_PESANAN'] ?>" class="btn-aksi btn-nota">
                            <i class="bi bi-receipt"></i> Nota
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr class="empty-row">
                    <td colspan="4">
                        <i class="bi bi-inbox empty-ico"></i>
                        <div style="font-size:13.5px;font-weight:600;color:var(--text2);margin-bottom:12px">Belum ada riwayat pesanan</div>
                        <a href="pesan.php" class="btn-aksi btn-bayar">Buat Pesanan Pertama</a>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- CTA CARD -->
        <div class="cta-card">
            <div class="cta-ico"><i class="bi bi-plus-circle-fill"></i></div>
            <div class="cta-title">Buat Pesanan Baru</div>
            <div class="cta-sub">Mau pesan seragam atau busana cantik? Yuk mulai sekarang!</div>
            <a href="pesan.php" class="btn-cta">Pesan Sekarang ✨</a>
        </div>
    </div>

</div>
</main>

<!-- FLOATING BUTTONS -->
<a class="chat-btn" href="chat.php" title="Live Chat">
    <i class="bi bi-chat-dots-fill" style="font-size:22px;color:#fff"></i>
</a>
<a class="wa-btn" href="https://wa.me/62895414630496" target="_blank" title="WhatsApp">
    <i class="fa-brands fa-whatsapp" style="font-size:26px;color:#fff"></i>
</a>

<script>
function toggleNotif() {
    document.getElementById('notifDropdown').classList.toggle('show');
}
document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.notif-wrap');
    if(wrap && !wrap.contains(e.target)) {
        document.getElementById('notifDropdown').classList.remove('show');
    }
});
</script>
</body>
</html>