<?php
session_start();
include "../config/koneksi.php";
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'pelanggan') {
    header("Location: ../index.php"); exit;
}

$id_pelanggan = $_SESSION['id'];
$nama_user    = $_SESSION['user'];
$inisial      = strtoupper(substr($nama_user, 0, 1));
if (strpos($nama_user, ' ') !== false) {
    $parts   = explode(' ', $nama_user);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

$total_pesan   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan'"))['n'];
$total_pending = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Pending'"))['n'];
$total_selesai = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Selesai'"))['n'];

$produk_query = mysqli_query($koneksi, "SELECT * FROM produk");
$produk_list  = [];
while ($p = mysqli_fetch_assoc($produk_query)) $produk_list[] = $p;

$selected_id = $_GET['id'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesan Produk 🛍️ | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
    --p50:#fff0f5;--p100:#ffd6e7;--p200:#ffadd0;--p300:#ff80b8;
    --p400:#f950a0;--p500:#e8328a;--p600:#cc1a73;--p700:#a8105d;
    --v100:#f3e8ff;--v300:#d8b4fe;--v500:#a855f7;
    --g100:#dcfce7;--g500:#22c55e;--g700:#15803d;
    --a100:#fef9c3;--a500:#eab308;--a700:#854d0e;
    --b100:#dbeafe;--b500:#3b82f6;--b700:#1d4ed8;
    --r100:#fee2e2;--r500:#ef4444;--r700:#991b1b;
    --white:#ffffff;--bg:#fff5f9;
    --text:#3d1a28;--text2:#7d4460;--text3:#b07090;
    --border:rgba(232,50,138,0.13);--border2:rgba(232,50,138,0.24);
    --sidebar-w:240px;--topbar-h:64px;
    --r-sm:10px;--r-md:16px;--r-lg:22px;--r-xl:28px;
    --ease:0.2s cubic-bezier(0.34,1.56,0.64,1);--ease-plain:0.17s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;font-size:14.5px;line-height:1.6;-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(232,50,138,0.055) 1.5px,transparent 1.5px);background-size:28px 28px;pointer-events:none;z-index:0}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--p50)}::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px}

/* ── SIDEBAR ── */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--white);border-right:1.5px solid var(--border);display:flex;flex-direction:column;z-index:300;overflow:hidden}
.sidebar::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;z-index:1;animation:shimmer 3s linear infinite}
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}
.sb-brand{display:flex;align-items:center;gap:12px;padding:0 18px;height:var(--topbar-h);border-bottom:1.5px solid var(--border);text-decoration:none;flex-shrink:0;transition:background var(--ease-plain);margin-top:4px}
.sb-brand:hover{background:var(--p50)}
.brand-mark{width:38px;height:38px;border-radius:13px;background:linear-gradient(135deg,var(--p500) 0%,var(--p400) 50%,var(--v500) 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4),0 0 0 3px rgba(232,50,138,0.12);transition:transform var(--ease)}
.sb-brand:hover .brand-mark{transform:rotate(-8deg) scale(1.08)}
.brand-mark i{font-size:18px;color:#fff}
.brand-name{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text);white-space:nowrap}
.brand-sub{font-size:10px;font-weight:600;color:var(--p500);letter-spacing:0.8px;text-transform:uppercase;margin-top:1px}

.sb-profile{margin:12px 12px 6px;padding:12px 14px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:var(--r-lg);display:flex;align-items:center;gap:10px;flex-shrink:0}
.owner-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;position:relative;box-shadow:0 3px 10px rgba(232,50,138,0.35)}
.owner-av::after{content:'';position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:var(--g500);border:2px solid var(--white)}
.owner-name{font-size:13.5px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.owner-role{font-size:11px;color:var(--p500);font-weight:600}

.sb-stats{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:0 12px 10px;flex-shrink:0}
.sb-stat{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-sm);padding:8px;text-align:center}
.sb-stat-n{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--text)}
.sb-stat-l{font-size:9.5px;color:var(--text3);font-weight:600;margin-top:1px}

.sb-nav{flex:1;overflow-y:auto;padding:6px 10px 10px}
.nav-group-label{font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--text3);padding:12px 10px 4px;display:flex;align-items:center;gap:6px}
.nav-group-label::after{content:'✦';font-size:7px;color:var(--p300)}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:var(--r-sm);text-decoration:none;color:var(--text2);font-size:14px;font-weight:600;transition:background var(--ease-plain),color var(--ease-plain),transform var(--ease-plain);margin-bottom:2px;position:relative;white-space:nowrap}
.nav-item i{font-size:17px;width:19px;text-align:center;flex-shrink:0;color:var(--text3);transition:color var(--ease-plain)}
.nav-item:hover{background:var(--p50);color:var(--p500);transform:translateX(2px)}
.nav-item:hover i{color:var(--p400)}
.nav-item.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-weight:700;box-shadow:0 4px 16px rgba(232,50,138,0.35)}
.nav-item.active i{color:rgba(255,255,255,0.9)}
.nav-item.active::after{content:'';position:absolute;right:10px;width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.6)}
.nav-pill{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0}
.pill-pink{background:var(--p500)}
.nav-item.active .nav-pill{background:rgba(255,255,255,0.3)}
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0}
.nav-item.logout{color:var(--r700)}.nav-item.logout i{color:var(--r500)}.nav-item.logout:hover{background:var(--r100);color:var(--r700);transform:none}

@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(0.85);opacity:0.6}}

/* ── TOPBAR ── */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(12px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px}
.topbar::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;animation:shimmer 3s linear infinite}
.tb-greeting{flex:1}
.tb-hello{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text)}
.tb-sub{font-size:12px;color:var(--text3);font-weight:500;margin-top:1px}
.tb-breadcrumb{display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--text3)}
.tb-breadcrumb a{color:var(--p500);text-decoration:none;font-weight:600}
.tb-breadcrumb a:hover{text-decoration:underline}
.tb-breadcrumb .sep{color:var(--border2)}
.tb-breadcrumb .cur{color:var(--text);font-weight:700}
.tb-divider{width:1px;height:24px;background:var(--border2);margin:0 4px}
.tb-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.user-chip{display:flex;align-items:center;gap:8px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:5px 14px 5px 5px}
.user-chip-av{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff}
.user-chip-name{font-size:12.5px;font-weight:700;color:var(--text)}
.date-pill{display:flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:600;color:var(--text2)}
.date-pill i{color:var(--p500)}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1}
.content{padding:28px 28px 80px}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.anim{animation:fadeUp 0.35s ease both}

/* Page header */
.page-header{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);padding:20px 26px;display:flex;align-items:center;gap:14px;margin-bottom:22px;position:relative;overflow:hidden}
.page-header::before{content:'';position:absolute;right:-30px;top:-30px;width:140px;height:140px;border-radius:50%;background:linear-gradient(135deg,var(--p50),var(--v100));opacity:0.7}
.ph-icon{width:48px;height:48px;border-radius:15px;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;box-shadow:0 6px 18px rgba(232,50,138,0.4)}
.ph-title{font-family:'Quicksand',sans-serif;font-size:20px;font-weight:700;color:var(--text)}
.ph-sub{font-size:13px;color:var(--text3);font-weight:500;margin-top:2px}

/* Tip box */
.tip-box{display:flex;align-items:flex-start;gap:10px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border2);border-radius:var(--r-md);padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--text2);font-weight:500}
.tip-box i{color:var(--p500);font-size:16px;flex-shrink:0;margin-top:1px}

/* Section title */
.sec-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;margin-bottom:14px}
.sec-dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:inline-block;box-shadow:0 0 0 3px rgba(232,50,138,0.15);flex-shrink:0}
.sec-badge{font-size:10px;font-weight:700;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:99px;padding:2px 10px}

/* Product grid */
.produk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;margin-bottom:24px}
.produk-card{background:var(--white);border:2px solid var(--border);border-radius:var(--r-lg);overflow:hidden;cursor:pointer;transition:all var(--ease);position:relative}
.produk-card:hover{border-color:var(--border2);transform:translateY(-4px);box-shadow:0 10px 24px rgba(232,50,138,0.13)}
.produk-card.selected{border-color:var(--p500);box-shadow:0 0 0 3px rgba(232,50,138,0.15)}
.check-mark{display:none;position:absolute;top:8px;right:8px;width:22px;height:22px;border-radius:50%;background:var(--p500);color:#fff;font-size:12px;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(232,50,138,0.4)}
.produk-card.selected .check-mark{display:flex}
.produk-img{height:110px;background:linear-gradient(135deg,var(--p50),var(--v100));display:flex;align-items:center;justify-content:center;overflow:hidden}
.produk-img img{width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease}
.produk-card:hover .produk-img img{transform:scale(1.07)}
.produk-img .no-img{font-size:30px;color:var(--p300)}
.produk-body{padding:10px 12px 12px}
.produk-bahan-tag{display:inline-block;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:99px;padding:2px 9px;font-size:9.5px;font-weight:700;margin-bottom:5px}
.produk-name{font-size:12.5px;font-weight:700;color:var(--text);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.produk-ukuran{font-size:10.5px;color:var(--text3);margin-bottom:6px}
.produk-price{font-family:'Quicksand',sans-serif;font-size:14px;font-weight:700;color:var(--p600)}

/* Divider */
.pink-divider{height:1px;background:linear-gradient(90deg,transparent,var(--border2),transparent);margin:22px 0}

/* Form card */
.form-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;margin-bottom:16px}
.form-card-hd{padding:16px 22px;background:linear-gradient(135deg,var(--p500),var(--p400));display:flex;align-items:center;gap:12px}
.form-card-hd.purple{background:linear-gradient(135deg,var(--p700),var(--v500))}
.fch-icon{width:38px;height:38px;border-radius:11px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;flex-shrink:0}
.fch-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:#fff}
.fch-sub{font-size:11px;color:rgba(255,255,255,0.8);margin-top:1px}
.form-card-body{padding:22px}

/* Selected product display */
.selected-info{background:var(--p50);border:1.5px solid var(--border2);border-radius:var(--r-md);padding:13px 16px;display:flex;align-items:center;gap:12px;margin-bottom:18px}
.sel-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;flex-shrink:0}
.sel-name{font-weight:700;font-size:13.5px;color:var(--text)}
.sel-bahan{font-size:11.5px;color:var(--text3);margin-top:1px}
.sel-price{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--p600)}
.sel-ukuran{font-size:10.5px;color:var(--text3)}

/* Form controls */
.form-lbl{font-size:12.5px;font-weight:700;color:var(--text2);margin-bottom:7px;display:flex;align-items:center;gap:5px}
.form-lbl i{color:var(--p500);font-size:13px}
.form-ctrl{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-family:'Nunito',sans-serif;font-size:14px;color:var(--text);background:var(--p50);outline:none;transition:border-color var(--ease-plain),box-shadow var(--ease-plain)}
.form-ctrl:focus{border-color:var(--p400);background:var(--white);box-shadow:0 0 0 3px rgba(232,50,138,0.1)}
.form-ctrl::placeholder{color:var(--text3)}

/* Summary box */
.summary-box{background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px dashed var(--border2);border-radius:var(--r-md);padding:16px 18px;margin-top:16px}
.sum-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:5px 0}
.sum-row:not(:last-child){border-bottom:1px solid var(--border)}
.sum-lbl{color:var(--text3);font-weight:500}
.sum-val{font-weight:700;color:var(--text)}
.sum-total .sum-lbl{font-weight:700;color:var(--text);font-size:14px}
.sum-total .sum-val{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;color:var(--p600)}

/* Submit btn */
.btn-pesan{width:100%;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border:none;border-radius:var(--r-lg);padding:13px;font-size:14px;font-weight:700;font-family:'Nunito',sans-serif;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 18px rgba(232,50,138,0.38);transition:all var(--ease);margin-top:18px}
.btn-pesan:hover{transform:translateY(-2px) scale(1.02);box-shadow:0 10px 26px rgba(232,50,138,0.48)}
.btn-pesan:active{transform:translateY(0) scale(1)}

/* Info card steps */
.step-item{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px}
.step-item:last-child{margin-bottom:0}
.step-num{width:34px;height:34px;border-radius:50%;background:var(--p50);border:1.5px solid var(--border2);display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--p500);flex-shrink:0}
.step-title{font-size:12.5px;font-weight:700;color:var(--text);margin-bottom:2px}
.step-sub{font-size:11.5px;color:var(--text3);line-height:1.5}

/* Contact card */
.contact-card{background:linear-gradient(135deg,var(--p500),var(--p700));border-radius:var(--r-xl);padding:22px 20px;text-align:center;position:relative;overflow:hidden}
.contact-card::before{content:'♥';position:absolute;top:12px;left:16px;font-size:22px;color:rgba(255,255,255,0.15)}
.contact-card::after{content:'♥';position:absolute;bottom:12px;right:16px;font-size:22px;color:rgba(255,255,255,0.15)}
.cc-icon{width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.18);display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;margin:0 auto 12px}
.cc-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:#fff;margin-bottom:6px}
.cc-sub{font-size:11.5px;color:rgba(255,255,255,0.8);margin-bottom:16px;line-height:1.5}
.cc-chat-link{display:block;font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;margin-bottom:12px}
.cc-chat-link:hover{color:#fff}
.cc-wa-btn{display:inline-flex;align-items:center;gap:7px;background:#fff;color:var(--p700);border-radius:99px;padding:9px 22px;font-size:12.5px;font-weight:700;text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,0.15);transition:all var(--ease-plain)}
.cc-wa-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,0.2);color:var(--p700)}
.wa-dot{width:10px;height:10px;border-radius:50%;background:#25D366}

/* Floating btns */
.float-btn{position:fixed;right:26px;width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:all var(--ease);z-index:999;box-shadow:0 4px 16px rgba(0,0,0,0.15)}
.float-btn:hover{transform:scale(1.1)}
.float-chat{bottom:90px;background:linear-gradient(135deg,var(--p500),var(--v500))}
.float-wa{bottom:30px;background:#25D366}

@media(max-width:900px){.sidebar{transform:translateX(-100%)}.topbar{left:0}.main{margin-left:0}}
@media(max-width:768px){.produk-grid{grid-template-columns:repeat(2,1fr)}}
/* Ukuran row */
.ukuran-row{display:flex;align-items:center;gap:10px;background:var(--p50);border:1.5px solid var(--border);border-radius:var(--r-sm);padding:10px 14px}
.ukuran-label{font-size:13px;font-weight:700;color:var(--text);min-width:60px}
.ukuran-qty{width:80px;padding:6px 10px;border:1.5px solid var(--border2);border-radius:8px;font-family:'Nunito',sans-serif;font-size:14px;font-weight:700;text-align:center;color:var(--text);background:var(--white);outline:none}
.ukuran-qty:focus{border-color:var(--p400);box-shadow:0 0 0 3px rgba(232,50,138,0.1)}
.ukuran-note{font-size:11px;color:var(--text3);flex:1}
</style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div><div class="brand-name">Konveksi Apps</div><div class="brand-sub">Panel Pelanggan</div></div>
    </a>
    <div class="sb-profile">
        <div class="owner-av"><?= $inisial ?></div>
        <div style="overflow:hidden;min-width:0">
            <div class="owner-name"><?= htmlspecialchars($nama_user) ?></div>
            <div class="owner-role">🌸 Pelanggan</div>
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
        <a class="nav-item" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
        <a class="nav-item active" href="pesan.php">
            <i class="bi bi-cart-plus-fill"></i> Pesan Produk
            <span class="nav-pill pill-pink" style="font-size:9px;padding:1px 6px">Baru</span>
        </a>
        <a class="nav-item" href="status_pesanan.php">
            <i class="bi bi-clock-history"></i> Status Pesanan
            <?php if ($total_pending > 0): ?>
            <span class="nav-pill pill-pink"><?= $total_pending ?></span>
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
        <div class="tb-hello">Pesan Produk 🛍️</div>
        <div class="tb-breadcrumb">
            <a href="dashboard.php"><i class="bi bi-house-heart-fill"></i> Beranda</a>
            <span class="sep">/</span>
            <span class="cur">Pesan Produk</span>
        </div>
    </div>
    <div class="tb-divider"></div>
    <div class="tb-actions">
        <div class="user-chip">
            <div class="user-chip-av"><?= $inisial ?></div>
            <div>
                <div class="user-chip-name"><?= htmlspecialchars($nama_user) ?></div>
            </div>
        </div>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content anim">

    <!-- Page header -->
    <div class="page-header">
        <div class="ph-icon"><i class="bi bi-cart-plus-fill"></i></div>
        <div style="position:relative;z-index:1">
            <div class="ph-title">Pesan Produk</div>
            <div class="ph-sub">Pilih produk favoritmu dan isi detail pesanan 🌸</div>
        </div>
    </div>

    <!-- Tip -->
    <div class="tip-box">
        <i class="bi bi-lightbulb-fill"></i>
        <span>Klik kartu produk di bawah untuk memilih, lalu lengkapi detail pesanan. Harga akan dihitung otomatis!</span>
    </div>

    <div class="row g-4">

        <!-- KIRI: Produk + Form -->
        <div class="col-lg-8">

            <div class="sec-title">
                <span class="sec-dot"></span> Pilih Produk
                <span class="sec-badge">Klik untuk memilih</span>
            </div>

            <div class="produk-grid" id="produkGrid">
            <?php foreach ($produk_list as $prd):
                $foto_path = "../uploads/produk/" . ($prd['FOTO_PRODUK'] ?? '');
                $ada_foto  = !empty($prd['FOTO_PRODUK']) && file_exists($foto_path);
                $is_sel    = ($selected_id == $prd['ID_PRODUK']) ? 'selected' : '';
            ?>
            <div class="produk-card <?= $is_sel ?>"
                 onclick="pilihProduk('<?= $prd['ID_PRODUK'] ?>','<?= addslashes($prd['NAMA_PRODUK']) ?>',<?= $prd['HARGA'] ?>,'<?= addslashes($prd['JENIS_BAHAN']) ?>','<?= addslashes($prd['UKURAN']) ?>')"
                 data-id="<?= $prd['ID_PRODUK'] ?>">
                <div class="check-mark"><i class="bi bi-check-lg"></i></div>
                <div class="produk-img">
                    <?php if ($ada_foto): ?>
                    <img src="<?= htmlspecialchars($foto_path) ?>" alt="<?= htmlspecialchars($prd['NAMA_PRODUK']) ?>">
                    <?php else: ?>
                    <i class="bi bi-image no-img"></i>
                    <?php endif; ?>
                </div>
                <div class="produk-body">
                    <span class="produk-bahan-tag"><?= htmlspecialchars($prd['JENIS_BAHAN']) ?></span>
                    <div class="produk-name" title="<?= htmlspecialchars($prd['NAMA_PRODUK']) ?>"><?= htmlspecialchars($prd['NAMA_PRODUK']) ?></div>
                    <div class="produk-ukuran">📏 <?= htmlspecialchars($prd['UKURAN']) ?></div>
                    <div class="produk-price">Rp <?= number_format($prd['HARGA'],0,',','.') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <div class="pink-divider"></div>

            <!-- Form Pesanan -->
            <div class="form-card">
                <div class="form-card-hd">
                    <div class="fch-icon"><i class="bi bi-pencil-square"></i></div>
                    <div>
                        <div class="fch-title">Detail Pesanan</div>
                        <div class="fch-sub">Lengkapi informasi di bawah ini</div>
                    </div>
                </div>
                <div class="form-card-body">
                    <form action="proses_pesan.php" method="POST" id="formPesan">
                        <input type="hidden" name="id_produk" id="input_id_produk" value="<?= htmlspecialchars($selected_id) ?>" required>

                        <!-- Produk terpilih -->
                        <div id="selectedInfo" style="display:<?= $selected_id ? 'block' : 'none' ?>">
                            <label class="form-lbl"><i class="bi bi-bag-heart-fill"></i> Produk Dipilih</label>
                            <div class="selected-info">
                                <div class="sel-icon"><i class="bi bi-shirt-fill"></i></div>
                                <div style="flex:1;min-width:0">
                                    <div id="disp_nama" class="sel-name">—</div>
                                    <div id="disp_bahan" class="sel-bahan">—</div>
                                </div>
                                <div style="text-align:right;flex-shrink:0">
                                    <div id="disp_harga" class="sel-price">—</div>
                                    <div id="disp_ukuran" class="sel-ukuran">—</div>
                                </div>
                            </div>
                        </div>

                        <div id="noSelectWarning" style="display:<?= $selected_id ? 'none' : 'block' ?>" class="tip-box" style="margin-bottom:18px">
                            <i class="bi bi-arrow-up-circle-fill"></i>
                            <span>Silakan klik salah satu kartu produk di atas terlebih dahulu.</span>
                        </div>

                        <div style="margin-bottom:14px" id="ukuranSection">
                            <label class="form-lbl"><i class="bi bi-rulers"></i> Ukuran & Jumlah</label>
                            <div id="ukuranContainer" style="display:flex;flex-direction:column;gap:8px"></div>
                            <input type="hidden" name="ukuran_data" id="input_ukuran_data">
                            <input type="hidden" name="jumlah" id="input_jumlah" value="1">
                            <div style="margin-top:10px;font-size:12px;color:var(--text3)">
                                <i class="bi bi-info-circle"></i> Isi jumlah 0 untuk ukuran yang tidak dipesan
                            </div>
                        </div>

                        <div style="margin-bottom:4px">
                            <label class="form-lbl"><i class="bi bi-chat-left-text-fill"></i> Catatan Tambahan <span style="font-weight:500;color:var(--text3)">(opsional)</span></label>
                            <textarea name="catatan" class="form-ctrl" rows="3"
                                      placeholder="Contoh: warna biru dongker, sablon nama di dada kiri..."></textarea>
                        </div>

                        <!-- Summary -->
                        <div class="summary-box" id="summaryBox" style="display:<?= $selected_id ? 'block' : 'none' ?>">
                            <div class="sum-row"><span class="sum-lbl">Harga Satuan</span><span class="sum-val" id="sum_satuan">Rp 0</span></div>
                            <div class="sum-row"><span class="sum-lbl">Jumlah</span><span class="sum-val" id="sum_jumlah">0 pcs</span></div>
                            <div id="sum_ukuran_detail" style="padding:4px 0;font-size:12px;color:var(--text3)"></div>                            <div class="sum-row sum-total" style="padding-top:8px;margin-top:4px">
                                <span class="sum-lbl">Total Estimasi</span>
                                <span class="sum-val" id="sum_total">Rp 0</span>
                            </div>
                        </div>

                        <button type="submit" class="btn-pesan">
                            <i class="bi bi-send-fill"></i> Kirim Pesanan Sekarang
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <!-- KANAN: Info -->
        <div class="col-lg-4">

            <!-- Cara pesan -->
            <div class="form-card" style="margin-bottom:16px">
                <div class="form-card-hd purple">
                    <div class="fch-icon"><i class="bi bi-info-circle-fill"></i></div>
                    <div>
                        <div class="fch-title">Cara Pesan</div>
                        <div class="fch-sub">Mudah dalam 3 langkah</div>
                    </div>
                </div>
                <div class="form-card-body">
                    <?php
                    $steps = [
                        ['bi-cursor-fill','Pilih Produk','Klik kartu produk yang ingin kamu pesan dari katalog.'],
                        ['bi-pencil-fill','Isi Detail','Masukkan jumlah pcs dan catatan khusus jika ada.'],
                        ['bi-send-fill','Kirim Pesanan','Klik tombol kirim. Admin akan segera memproses!'],
                    ];
                    foreach ($steps as $i => $s): ?>
                    <div class="step-item">
                        <div class="step-num"><i class="bi <?= $s[0] ?>"></i></div>
                        <div>
                            <div class="step-title"><?= $i+1 ?>. <?= $s[1] ?></div>
                            <div class="step-sub"><?= $s[2] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Kontak -->
            <div class="contact-card">
                <div class="cc-icon"><i class="bi bi-headset"></i></div>
                <div class="cc-title">Butuh Bantuan? 💬</div>
                <div class="cc-sub">Tim kami siap membantu kamu memilih produk yang tepat!</div>
                <a href="chat.php" class="cc-chat-link"><i class="bi bi-chat-dots-fill me-1"></i>Live Chat di Sini</a>
                <a href="https://wa.me/62895414630496" target="_blank" class="cc-wa-btn">
                    <div class="wa-dot"></div> Chat WhatsApp
                </a>
            </div>

        </div>
    </div>

</div>
</main>

<!-- Floating buttons -->
<a href="chat.php" class="float-btn float-chat" title="Live Chat">
    <i class="bi bi-chat-dots-fill" style="font-size:22px;color:#fff"></i>
</a>
<a href="https://wa.me/62895414630496" target="_blank" class="float-btn float-wa" title="WhatsApp">
    <i class="bi bi-whatsapp" style="font-size:24px;color:#fff"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const produkData = <?= json_encode(array_column($produk_list, null, 'ID_PRODUK')) ?>;
let hargaSatuan = 0;
let ukuranList  = [];

<?php if ($selected_id): foreach ($produk_list as $prd): if ($prd['ID_PRODUK'] == $selected_id): ?>
window.addEventListener('DOMContentLoaded', () => {
    pilihProduk('<?= $prd['ID_PRODUK'] ?>','<?= addslashes($prd['NAMA_PRODUK']) ?>',<?= $prd['HARGA'] ?>,'<?= addslashes($prd['JENIS_BAHAN']) ?>','<?= addslashes($prd['UKURAN']) ?>');
});
<?php endif; endforeach; endif; ?>

function pilihProduk(id, nama, harga, bahan, ukuran) {
    document.querySelectorAll('.produk-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.produk-card[data-id="${id}"]`);
    if (card) card.classList.add('selected');

    document.getElementById('input_id_produk').value = id;
    hargaSatuan = harga;
    ukuranList  = ukuran.split(',').map(u => u.trim()).filter(u => u);

    document.getElementById('disp_nama').textContent   = nama;
    document.getElementById('disp_bahan').textContent  = bahan;
    document.getElementById('disp_harga').textContent  = 'Rp ' + harga.toLocaleString('id-ID');
    document.getElementById('disp_ukuran').textContent = 'Ukuran: ' + ukuran;
    document.getElementById('selectedInfo').style.display    = 'block';
    document.getElementById('noSelectWarning').style.display = 'none';
    document.getElementById('summaryBox').style.display      = 'block';

    renderUkuranRows();
}

function renderUkuranRows() {
    const container = document.getElementById('ukuranContainer');
    container.innerHTML = '';
    ukuranList.forEach(uk => {
        const row = document.createElement('div');
        row.className = 'ukuran-row';
        row.innerHTML = `
            <span class="ukuran-label">${uk}</span>
            <input type="number" class="ukuran-qty" min="0" value="0"
                   data-ukuran="${uk}" oninput="hitungTotal()" placeholder="0">
            <span class="ukuran-note">pcs</span>
        `;
        container.appendChild(row);
    });
    hitungTotal();
}

function hitungTotal() {
    const inputs  = document.querySelectorAll('.ukuran-qty');
    let totalQty  = 0;
    let detail    = [];
    const ukuranData = {};

    inputs.forEach(inp => {
        const qty = parseInt(inp.value) || 0;
        const uk  = inp.dataset.ukuran;
        ukuranData[uk] = qty;
        if (qty > 0) {
            totalQty += qty;
            detail.push(`${uk}: ${qty} pcs`);
        }
    });

    document.getElementById('input_jumlah').value          = totalQty;
    document.getElementById('input_ukuran_data').value     = JSON.stringify(ukuranData);
    document.getElementById('sum_satuan').textContent      = 'Rp ' + hargaSatuan.toLocaleString('id-ID');
    document.getElementById('sum_jumlah').textContent      = totalQty + ' pcs';
    document.getElementById('sum_ukuran_detail').textContent = detail.length ? '📏 ' + detail.join('  •  ') : '';
    document.getElementById('sum_total').textContent       = 'Rp ' + (hargaSatuan * totalQty).toLocaleString('id-ID');
}

document.getElementById('formPesan').addEventListener('submit', function(e) {
    if (!document.getElementById('input_id_produk').value) {
        e.preventDefault();
        alert('⚠️ Silakan pilih produk terlebih dahulu!');
        return;
    }
    const jumlah = parseInt(document.getElementById('input_jumlah').value) || 0;
    if (jumlah === 0) {
        e.preventDefault();
        alert('⚠️ Masukkan jumlah minimal 1 pcs untuk setidaknya satu ukuran!');
    }
});
</script>
</body>
</html>