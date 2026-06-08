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

$q_kirim = mysqli_query($koneksi, "
    SELECT p.*, pr.NAMA_PRODUK, dp.JUMLAH,
           k.ID_PENGIRIMAN, k.JASA_KIRIM, k.NO_RESI,
           k.ALAMAT_KIRIM, k.ONGKIR, k.EST_TIBA,
           k.STATUS_KIRIM, k.TGL_KIRIM
    FROM pesanan p
    JOIN detail_pesanan dp ON p.ID_PESANAN = dp.ID_PESANAN
    JOIN produk pr ON dp.ID_PRODUK = pr.ID_PRODUK
    LEFT JOIN pengiriman k ON p.ID_PESANAN = k.ID_PESANAN
    WHERE p.ID_PELANGGAN = '$id_pelanggan'
      AND (p.STATUS = 'Proses' OR p.STATUS = 'Selesai')
    ORDER BY p.WAKTU_PESAN DESC
");
$pengiriman_list = [];
while ($s = mysqli_fetch_assoc($q_kirim)) $pengiriman_list[] = $s;

$total_proses  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Proses'"))['n'];
$total_selesai = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Selesai'"))['n'];
$total_pending = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Pending'"))['n'];
$total_semua   = count($pengiriman_list);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengiriman & Logistik 🚚 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --p50:#fff0f5; --p100:#ffd6e7; --p200:#ffadd0; --p300:#ff80b8;
    --p400:#f950a0; --p500:#e8328a; --p600:#cc1a73; --p700:#a8105d;
    --v100:#f3e8ff; --v300:#d8b4fe; --v500:#a855f7; --v600:#9333ea;
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
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--p50)}::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px}

@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.4}}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
@keyframes truckMove{0%,100%{transform:translateX(0)}50%{transform:translateX(8px)}}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(0.85);opacity:0.6}}
.pulse{animation:blink 1.6s ease-in-out infinite}

/* ══ SIDEBAR ══ */
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

.sb-stats{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:0 12px 6px;flex-shrink:0}
.sb-stat{background:var(--white);border:1.5px solid var(--border);border-radius:12px;padding:8px 10px;text-align:center}
.sb-stat-n{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--p500)}
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
.pill-pink{background:var(--p500)}.pill-red{background:var(--r500)}.pill-orange{background:var(--o500)}
.nav-item.active .nav-pill{background:rgba(255,255,255,0.3)}
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0}
.nav-item.logout{color:var(--r700)}.nav-item.logout i{color:var(--r500)}.nav-item.logout:hover{background:var(--r100);color:var(--r700);transform:none}

/* ══ TOPBAR ══ */
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
.user-chip{display:flex;align-items:center;gap:8px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:5px 14px 5px 5px}
.chip-av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff}
.chip-name{font-size:12.5px;font-weight:700;color:var(--text)}

/* ══ LAYOUT ══ */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1}
.content{padding:28px 30px 48px}

/* ══ HERO BANNER — pink-purple ══ */
.page-hero{
    background:linear-gradient(135deg,var(--p600) 0%,var(--p500) 45%,var(--v500) 100%);
    border-radius:24px;padding:28px 32px;margin-bottom:28px;
    display:flex;align-items:center;justify-content:space-between;
    overflow:hidden;position:relative;animation:fadeUp .4s both
}
.page-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,0.08);pointer-events:none}
.page-hero::after{content:'';position:absolute;bottom:-60px;right:80px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,0.05);pointer-events:none}
.hero-eyebrow{font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:rgba(255,255,255,0.75);margin-bottom:6px}
.hero-title{font-family:'Quicksand',sans-serif;font-size:26px;font-weight:700;color:#fff;line-height:1.2;margin-bottom:6px}
.hero-sub{font-size:13px;color:rgba(255,255,255,0.8);max-width:420px}
.hero-icon{font-size:64px;color:rgba(255,255,255,0.18);flex-shrink:0;animation:truckMove 2s ease-in-out infinite;line-height:1;position:relative;z-index:1}

/* ══ STAT CARDS ══ */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:20px;padding:20px 22px;display:flex;align-items:center;gap:16px;transition:transform var(--ease),box-shadow var(--ease);animation:fadeUp .4s both;cursor:default;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:20px 20px 0 0}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 10px 30px rgba(232,50,138,0.12)}
.sc-pink::before  {background:linear-gradient(90deg,var(--p500),var(--p300))}
.sc-purple::before{background:linear-gradient(90deg,var(--v500),var(--v300))}
.sc-rose::before  {background:linear-gradient(90deg,var(--p400),var(--v500))}
.sc-icon{width:48px;height:48px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.sc-pink   .sc-icon{background:var(--p50);color:var(--p500)}
.sc-purple .sc-icon{background:var(--v100);color:var(--v500)}
.sc-rose   .sc-icon{background:linear-gradient(135deg,var(--p50),var(--v100));color:var(--p500)}
.sc-num{font-family:'Quicksand',sans-serif;font-size:28px;font-weight:700;line-height:1}
.sc-pink   .sc-num{color:var(--p600)}
.sc-purple .sc-num{color:var(--v500)}
.sc-rose   .sc-num{color:var(--p500)}
.sc-lbl{font-size:12px;color:var(--text3);font-weight:600;margin-top:3px}

/* ══ SECTION HEADER ══ */
.sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.sec-title{display:flex;align-items:center;gap:8px;font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text)}
.sec-dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));flex-shrink:0;box-shadow:0 0 0 3px rgba(232,50,138,0.15)}

/* ══ JASA KIRIM ══ */
.jasa-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:28px}
.jasa-card{background:var(--white);border:1.5px solid var(--border);border-radius:16px;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:border-color var(--ease-plain),transform var(--ease),box-shadow var(--ease)}
.jasa-card:hover{border-color:var(--border2);transform:translateY(-2px);box-shadow:0 6px 20px rgba(232,50,138,0.09)}
.jasa-logo{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;flex-shrink:0;letter-spacing:-.3px}
.logo-jne    {background:linear-gradient(135deg,#e63c2f,#c0392b)}
.logo-jnt    {background:linear-gradient(135deg,#e83e1d,#cc2200)}
.logo-sicepat{background:linear-gradient(135deg,var(--p500),var(--p600))}
.logo-pos    {background:linear-gradient(135deg,var(--o500),#d45d00)}
.logo-anteraja{background:linear-gradient(135deg,var(--p400),var(--v500))}
.logo-ninja  {background:linear-gradient(135deg,var(--v500),var(--v600))}
.jasa-name{font-size:13px;font-weight:700;color:var(--text)}
.jasa-desc{font-size:11px;color:var(--text3);margin-top:1px}
.jasa-badge{margin-top:5px;display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700}
.badge-fast {background:var(--g100);color:var(--g700)}
.badge-cheap{background:var(--p50);color:var(--p600)}
.badge-pop  {background:var(--p100);color:var(--p600)}
.badge-same {background:var(--v100);color:var(--v600)}

/* ══ FILTER PILLS ══ */
.filter-pills{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.fpill{display:flex;align-items:center;gap:6px;padding:7px 18px;border-radius:99px;background:var(--white);border:1.5px solid var(--border);font-size:12.5px;font-weight:600;color:var(--text2);cursor:pointer;transition:all var(--ease-plain);user-select:none}
.fpill:hover{border-color:var(--border2);color:var(--p500)}
.fpill.active{background:linear-gradient(135deg,var(--p500),var(--p400));border-color:transparent;color:#fff;box-shadow:0 3px 12px rgba(232,50,138,0.3)}

/* ══ PENGIRIMAN CARD ══ */
.pengiriman-card{background:var(--white);border:1.5px solid var(--border);border-radius:22px;padding:22px 24px;margin-bottom:16px;animation:fadeUp .4s both;transition:box-shadow var(--ease),border-color var(--ease-plain)}
.pengiriman-card:hover{box-shadow:0 8px 28px rgba(232,50,138,0.1);border-color:var(--border2)}

.p-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding-bottom:16px;border-bottom:1.5px solid var(--border);margin-bottom:18px}
.p-order-tag{display:inline-flex;align-items:center;gap:5px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:3px 10px 3px 6px;font-size:11.5px;font-weight:700;color:var(--p500);margin-bottom:6px}
.p-nama{font-size:15px;font-weight:800;color:var(--text);margin-bottom:3px}
.p-meta{display:flex;align-items:center;gap:12px;font-size:12px;color:var(--text3)}
.p-meta i{font-size:12px}

/* Status badges — all pink/purple/green */
.p-badge{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:99px;font-size:12px;font-weight:700;flex-shrink:0;white-space:nowrap}
.badge-proses       {background:var(--p50);color:var(--p600);border:1.5px solid var(--border2)}
.badge-selesai      {background:var(--g100);color:var(--g700)}
.badge-pending-kirim{background:var(--v100);color:var(--v600);border:1.5px solid rgba(168,85,247,0.2)}

/* ══ TRACKER STEPPER — pink ══ */
.track-wrap{background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:16px;padding:18px 20px;margin-bottom:18px}
.track-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.track-label-title{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);display:flex;align-items:center;gap:6px}
.track-label-title i{color:var(--p400);font-size:13px}
.track-id{font-size:11px;font-weight:600;color:var(--text3);background:var(--white);border:1.5px solid var(--border);padding:3px 10px;border-radius:99px}

.stepper{display:flex;align-items:flex-start;position:relative;padding:0 10px}
.stepper::before{content:'';position:absolute;top:15px;left:36px;right:36px;height:2px;background:var(--p100);z-index:0;border-radius:99px}
.step-item{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;z-index:1}
.step-circle{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;border:2px solid var(--p100);background:var(--white);color:var(--text3);font-weight:700;transition:all var(--ease);position:relative;z-index:1}
.step-item.done .step-circle{background:linear-gradient(135deg,var(--p500),var(--p400));border-color:var(--p500);color:#fff;box-shadow:0 3px 10px rgba(232,50,138,0.35)}
.step-item.active .step-circle{background:var(--white);border-color:var(--p500);color:var(--p500);box-shadow:0 0 0 5px rgba(232,50,138,0.12)}
.step-item.active .step-circle i{animation:blink 1.6s ease-in-out infinite}
.step-lbl{font-size:10px;font-weight:600;color:var(--text3);margin-top:8px;text-align:center;line-height:1.4;max-width:64px}
.step-item.done   .step-lbl{color:var(--p500);font-weight:700}
.step-item.active .step-lbl{color:var(--p500);font-weight:700}

.progress-bar-wrap{height:4px;background:var(--p100);border-radius:99px;margin-top:12px;overflow:hidden}
.progress-bar-fill{height:100%;background:linear-gradient(90deg,var(--p500),var(--p300));border-radius:99px;transition:width .6s ease}

/* ══ INFO CHIPS — pink ══ */
.info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.info-chip{background:var(--p50);border:1.5px solid var(--border);border-radius:14px;padding:12px 14px}
.info-chip-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--text3);margin-bottom:4px;display:flex;align-items:center;gap:4px}
.info-chip-label i{font-size:11px;color:var(--p400)}
.info-chip-val{font-size:13.5px;font-weight:700;color:var(--text)}
.info-chip-empty{font-size:12px;color:var(--text3);font-style:italic}

/* ══ RESI BOX — pink ══ */
.resi-box{background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border2);border-radius:14px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
.resi-lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--p600);margin-bottom:3px;display:flex;align-items:center;gap:5px}
.resi-num{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;color:var(--text);letter-spacing:.5px}
.resi-actions{display:flex;gap:8px;flex-shrink:0}
.btn-copy-resi{display:flex;align-items:center;gap:5px;padding:7px 14px;background:var(--white);border:1.5px solid var(--border2);border-radius:99px;font-size:12px;font-weight:700;color:var(--p600);cursor:pointer;transition:all var(--ease-plain);text-decoration:none}
.btn-copy-resi:hover{background:var(--p100)}
.btn-copy-resi.copied{background:var(--g100);border-color:var(--g500);color:var(--g700)}
.btn-lacak-inline{display:flex;align-items:center;gap:5px;padding:7px 14px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border-radius:99px;font-size:12px;font-weight:700;text-decoration:none;transition:transform var(--ease),box-shadow var(--ease);border:none}
.btn-lacak-inline:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(232,50,138,0.4);color:#fff}

.no-resi-note{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:var(--v100);border:1.5px solid rgba(168,85,247,0.2);border-radius:12px;font-size:12px;font-weight:600;color:var(--v600);margin-bottom:16px}

/* ══ ALAMAT BOX ══ */
.alamat-box{display:flex;align-items:flex-start;gap:12px;background:var(--white);border:1.5px dashed var(--border2);border-radius:14px;padding:13px 16px;margin-bottom:16px}
.alamat-icon{width:34px;height:34px;border-radius:10px;background:var(--p50);display:flex;align-items:center;justify-content:center;color:var(--p500);font-size:16px;flex-shrink:0;margin-top:1px}
.alamat-lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--text3);margin-bottom:3px}
.alamat-val{font-size:13px;font-weight:600;color:var(--text);line-height:1.5}

.no-kirim-box{background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px dashed var(--border2);border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;margin-bottom:16px}
.no-kirim-icon{width:36px;height:36px;border-radius:12px;background:var(--p100);display:flex;align-items:center;justify-content:center;color:var(--p500);font-size:18px;flex-shrink:0}
.no-kirim-text{font-size:13px;font-weight:600;color:var(--text2)}
.no-kirim-sub{font-size:11.5px;color:var(--text3);margin-top:2px}

/* ══ CARD FOOTER ══ */
.p-card-footer{display:flex;align-items:center;justify-content:space-between;border-top:1.5px solid var(--border);padding-top:14px;margin-top:4px}
.p-total-lbl{font-size:11px;color:var(--text3);font-weight:600;margin-bottom:2px}
.p-total{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--p500)}
.footer-actions{display:flex;gap:8px}
.btn-nota{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--p50);color:var(--p500);border:1.5px solid var(--border2);border-radius:99px;font-size:13px;font-weight:700;text-decoration:none;transition:all var(--ease-plain)}
.btn-nota:hover{background:var(--p100);color:var(--p600)}

/* ══ EMPTY STATE ══ */
.empty-state{text-align:center;padding:64px 20px;animation:fadeUp .4s both}
.empty-ico-wrap{width:90px;height:90px;border-radius:28px;background:linear-gradient(135deg,var(--p50),var(--v100));display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:1.5px solid var(--border)}
.empty-ico-wrap i{font-size:42px;color:var(--p300)}
.empty-state h6{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--text2);margin-bottom:8px}
.empty-state p{color:var(--text3);font-size:13.5px;margin-bottom:20px;max-width:320px;margin-left:auto;margin-right:auto}
.btn-empty{display:inline-flex;align-items:center;gap:8px;padding:12px 26px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border-radius:99px;font-size:14px;font-weight:700;text-decoration:none;transition:transform var(--ease),box-shadow var(--ease)}
.btn-empty:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(232,50,138,0.4);color:#fff}

/* ══ FLOATING BUTTONS ══ */
.chat-btn{position:fixed;bottom:82px;right:24px;width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--p400));display:flex;align-items:center;justify-content:center;box-shadow:0 4px 18px rgba(232,50,138,0.45);z-index:999;text-decoration:none;transition:transform var(--ease)}
.chat-btn:hover{transform:scale(1.1)}
.wa-btn{position:fixed;bottom:24px;right:24px;width:50px;height:50px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 18px rgba(37,211,102,0.45);z-index:999;text-decoration:none;transition:transform var(--ease)}
.wa-btn:hover{transform:scale(1.1)}

/* ══ TOAST ══ */
.toast-wrap{position:fixed;bottom:90px;left:50%;transform:translateX(-50%) translateY(20px);background:#1e1e2e;color:#fff;padding:11px 22px;border-radius:99px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;opacity:0;transition:all .3s;z-index:9999;pointer-events:none;white-space:nowrap}
.toast-wrap.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast-wrap i{color:var(--g500)}

@media(max-width:900px){
    .sidebar{transform:translateX(-100%)}
    .topbar{left:0}
    .main{margin-left:0}
    .stat-row,.jasa-grid{grid-template-columns:1fr 1fr}
    .info-grid{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a class="sb-brand" href="dashboard.php">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div>
            <div class="brand-name">Konveksi Apps</div>
            <div class="brand-sub">Pelanggan</div>
        </div>
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
            <div class="sb-stat-n"><?= $total_proses ?></div>
            <div class="sb-stat-l">Dikirim</div>
        </div>
        <div class="sb-stat">
            <div class="sb-stat-n"><?= $total_selesai ?></div>
            <div class="sb-stat-l">Selesai</div>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="nav-group-label">Menu Utama</div>
        <a class="nav-item" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
        <a class="nav-item" href="pesan.php"><i class="bi bi-cart-plus-fill"></i> Buat Pesanan</a>
        <a class="nav-item" href="status_pesanan.php">
            <i class="bi bi-clock-history"></i> Status Pesanan
            <?php if ($total_pending > 0): ?><span class="nav-pill pill-pink pulse"><?= $total_pending ?></span><?php endif; ?>
        </a>
        <a class="nav-item active" href="pengiriman.php">
            <i class="bi bi-truck"></i> Pengiriman
            <?php if ($total_proses > 0): ?><span class="nav-pill pill-orange pulse"><?= $total_proses ?></span><?php endif; ?>
        </a>

        <div class="nav-group-label">Transaksi</div>
        <a class="nav-item" href="nota.php"><i class="bi bi-receipt-cutoff"></i> Nota / Struk</a>

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
        <div class="tb-hello">Pengiriman & Logistik 🚚</div>
        <div class="tb-sub">Pantau status pengiriman pesananmu secara real-time</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="pesan.php"><i class="bi bi-cart-plus"></i> Pesan</a>
        <a class="tb-nav-item" href="status_pesanan.php"><i class="bi bi-clock-history"></i> Status</a>
        <a class="tb-nav-item active" href="pengiriman.php"><i class="bi bi-truck"></i> Pengiriman</a>
    </nav>
    <div class="tb-divider"></div>
    <div class="tb-actions">
        <div class="user-chip">
            <div class="chip-av"><?= $inisial ?></div>
            <span class="chip-name"><?= htmlspecialchars($nama_user) ?></span>
        </div>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- Hero -->
    <div class="page-hero">
        <div class="hero-text">
            <div class="hero-eyebrow">📦 Manajemen Logistik</div>
            <div class="hero-title">Pengiriman Pesananmu 🌸</div>
            <div class="hero-sub">Lacak resi, cek status pengiriman, dan pantau estimasi tiba paketmu di sini</div>
        </div>
        <div class="hero-icon"><i class="bi bi-truck-front-fill"></i></div>
    </div>

    <!-- Stats -->
    <div class="stat-row">
        <div class="stat-card sc-pink" style="animation-delay:.05s">
            <div class="sc-icon"><i class="bi bi-send-fill"></i></div>
            <div>
                <div class="sc-num"><?= $total_proses ?></div>
                <div class="sc-lbl">Sedang Dikirim</div>
            </div>
        </div>
        <div class="stat-card sc-purple" style="animation-delay:.1s">
            <div class="sc-icon"><i class="bi bi-patch-check-fill"></i></div>
            <div>
                <div class="sc-num"><?= $total_selesai ?></div>
                <div class="sc-lbl">Berhasil Terkirim</div>
            </div>
        </div>
        <div class="stat-card sc-rose" style="animation-delay:.15s">
            <div class="sc-icon"><i class="bi bi-boxes"></i></div>
            <div>
                <div class="sc-num"><?= $total_semua ?></div>
                <div class="sc-lbl">Total Pengiriman</div>
            </div>
        </div>
    </div>

    <!-- Jasa Pengiriman -->
    <div class="sec-hd">
        <div class="sec-title"><span class="sec-dot"></span> Mitra Jasa Pengiriman 🚐</div>
    </div>
    <div class="jasa-grid">
        <div class="jasa-card">
            <div class="jasa-logo logo-jne">JNE</div>
            <div>
                <div class="jasa-name">JNE Express</div>
                <div class="jasa-desc">Reguler, YES, OKE</div>
                <span class="jasa-badge badge-pop">Populer</span>
            </div>
        </div>
        <div class="jasa-card">
            <div class="jasa-logo logo-jnt">J&T</div>
            <div>
                <div class="jasa-name">J&T Express</div>
                <div class="jasa-desc">Express & Cargo</div>
                <span class="jasa-badge badge-fast">Cepat</span>
            </div>
        </div>
        <div class="jasa-card">
            <div class="jasa-logo logo-sicepat">Si!</div>
            <div>
                <div class="jasa-name">SiCepat</div>
                <div class="jasa-desc">BEST, Reguler, Cargo</div>
                <span class="jasa-badge badge-fast">Cepat</span>
            </div>
        </div>
        <div class="jasa-card">
            <div class="jasa-logo logo-pos">POS</div>
            <div>
                <div class="jasa-name">Pos Indonesia</div>
                <div class="jasa-desc">Biasa & Kilat Khusus</div>
                <span class="jasa-badge badge-cheap">Hemat</span>
            </div>
        </div>
        <div class="jasa-card">
            <div class="jasa-logo logo-anteraja">AJ</div>
            <div>
                <div class="jasa-name">Anteraja</div>
                <div class="jasa-desc">Same Day & Reguler</div>
                <span class="jasa-badge badge-same">Same Day</span>
            </div>
        </div>
        <div class="jasa-card">
            <div class="jasa-logo logo-ninja">NX</div>
            <div>
                <div class="jasa-name">Ninja Xpress</div>
                <div class="jasa-desc">Standard & Express</div>
                <span class="jasa-badge badge-cheap">Hemat</span>
            </div>
        </div>
    </div>

    <!-- Daftar Pengiriman -->
    <div class="sec-hd">
        <div class="sec-title"><span class="sec-dot"></span> Riwayat Pengiriman Pesanan 📦</div>
    </div>

    <div class="filter-pills">
        <div class="fpill active" onclick="filterKirim('semua',this)">
            <i class="bi bi-grid-3x3-gap"></i> Semua (<?= $total_semua ?>)
        </div>
        <div class="fpill" onclick="filterKirim('proses',this)">
            <i class="bi bi-send"></i> Dikirim (<?= $total_proses ?>)
        </div>
        <div class="fpill" onclick="filterKirim('selesai',this)">
            <i class="bi bi-patch-check-fill"></i> Terkirim (<?= $total_selesai ?>)
        </div>
    </div>

    <div id="kirimContainer">
    <?php if (count($pengiriman_list) > 0):
        foreach ($pengiriman_list as $idx => $s):
            $st        = strtolower($s['STATUS']);
            $has_kirim = !empty($s['ID_PENGIRIMAN']);
            $has_resi  = $has_kirim && !empty($s['NO_RESI']);

            $badge_cls = ($st=='selesai') ? 'badge-selesai' : ($has_kirim ? 'badge-proses' : 'badge-pending-kirim');
            $badge_ic  = ($st=='selesai') ? 'patch-check-fill' : ($has_kirim ? 'send-fill' : 'hourglass');
            $badge_txt = ($st=='selesai') ? 'Terkirim' : ($has_kirim ? 'Sedang Dikirim' : 'Menunggu Dikirim');

            $sk = strtolower($s['STATUS_KIRIM'] ?? '');
            if      ($st=='selesai' || $sk=='terkirim') $step = 4;
            elseif  ($sk=='dikirim')                    $step = 3;
            elseif  ($sk=='diserahkan')                 $step = 2;
            elseif  ($has_kirim)                        $step = 1;
            else                                        $step = 0;

            $pct = round(($step / 4) * 100);

            $steps_track = [
                ['label' => "Pesanan\nDikonfirmasi", 'icon' => 'check-circle'],
                ['label' => "Diserahkan\nke Kurir",  'icon' => 'box-seam'],
                ['label' => "Dalam\nPerjalanan",     'icon' => 'truck'],
                ['label' => "Paket\nTerkirim",       'icon' => 'house-check'],
            ];
    ?>
    <div class="pengiriman-card <?= $st ?>" data-status="<?= $st ?>" style="animation-delay:<?= 0.06*$idx ?>s">

        <div class="p-card-top">
            <div>
                <div class="p-order-tag"><i class="bi bi-hash"></i><?= htmlspecialchars($s['ID_PESANAN']) ?></div>
                <div class="p-nama"><?= htmlspecialchars($s['NAMA_PRODUK']) ?> <span style="font-size:13px;font-weight:600;color:var(--text3)">&times; <?= (int)$s['JUMLAH'] ?> pcs</span></div>
                <div class="p-meta">
                    <span><i class="bi bi-calendar3"></i> <?= date('d M Y', strtotime($s['WAKTU_PESAN'])) ?></span>
                    <span><i class="bi bi-clock"></i> <?= date('H:i', strtotime($s['WAKTU_PESAN'])) ?></span>
                    <?php if ($has_kirim && !empty($s['TGL_KIRIM'])): ?>
                    <span><i class="bi bi-send"></i> Dikirim <?= date('d M Y', strtotime($s['TGL_KIRIM'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <span class="p-badge <?= $badge_cls ?>"><i class="bi bi-<?= $badge_ic ?>"></i> <?= $badge_txt ?></span>
        </div>

        <?php if ($has_kirim): ?>

        <!-- Stepper -->
        <div class="track-wrap">
            <div class="track-header">
                <div class="track-label-title"><i class="bi bi-geo-alt-fill"></i> Tracking Status</div>
                <?php if (!empty($s['ID_PENGIRIMAN'])): ?>
                <div class="track-id"><?= htmlspecialchars($s['ID_PENGIRIMAN']) ?></div>
                <?php endif; ?>
            </div>
            <div class="stepper">
                <?php foreach ($steps_track as $i => $ts):
                    $sn  = $i + 1;
                    $cls = ($sn < $step) ? 'done' : (($sn == $step) ? 'active' : '');
                    $ic  = ($sn < $step) ? 'check-lg' : $ts['icon'];
                ?>
                <div class="step-item <?= $cls ?>">
                    <div class="step-circle"><i class="bi bi-<?= $ic ?>"></i></div>
                    <div class="step-lbl"><?= nl2br(htmlspecialchars($ts['label'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-chip">
                <div class="info-chip-label"><i class="bi bi-truck"></i> Jasa Kirim</div>
                <div class="info-chip-val">
                    <?= !empty($s['JASA_KIRIM']) ? htmlspecialchars($s['JASA_KIRIM']) : '<span class="info-chip-empty">Belum ditentukan</span>' ?>
                </div>
            </div>
            <div class="info-chip">
                <div class="info-chip-label"><i class="bi bi-wallet2"></i> Ongkos Kirim</div>
                <div class="info-chip-val">
                    <?= !empty($s['ONGKIR']) ? 'Rp&nbsp;'.number_format($s['ONGKIR'],0,',','.') : '<span class="info-chip-empty">—</span>' ?>
                </div>
            </div>
            <div class="info-chip">
                <div class="info-chip-label"><i class="bi bi-calendar-check"></i> Estimasi Tiba</div>
                <div class="info-chip-val">
                    <?= !empty($s['EST_TIBA']) ? date('d M Y', strtotime($s['EST_TIBA'])) : '<span class="info-chip-empty">Menunggu</span>' ?>
                </div>
            </div>
        </div>

        <!-- Resi -->
        <?php if ($has_resi): ?>
        <div class="resi-box">
            <div>
                <div class="resi-lbl"><i class="bi bi-upc-scan"></i> Nomor Resi</div>
                <div class="resi-num"><?= htmlspecialchars($s['NO_RESI']) ?></div>
            </div>
            <div class="resi-actions">
                <button class="btn-copy-resi" id="copy-<?= $s['ID_PESANAN'] ?>" onclick="copyResi('<?= htmlspecialchars($s['NO_RESI']) ?>','<?= $s['ID_PESANAN'] ?>')">
                    <i class="bi bi-copy"></i> Salin
                </button>
                <a href="https://cekresi.com/?noresi=<?= urlencode($s['NO_RESI']) ?>" target="_blank" class="btn-lacak-inline">
                    <i class="bi bi-geo-alt-fill"></i> Lacak Paket
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="no-resi-note">
            <i class="bi bi-clock-history"></i>
            Nomor resi belum tersedia — admin akan segera mengupdate
        </div>
        <?php endif; ?>

        <!-- Alamat -->
        <?php if (!empty($s['ALAMAT_KIRIM'])): ?>
        <div class="alamat-box">
            <div class="alamat-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
                <div class="alamat-lbl">Alamat Pengiriman</div>
                <div class="alamat-val"><?= nl2br(htmlspecialchars($s['ALAMAT_KIRIM'])) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="no-kirim-box">
            <div class="no-kirim-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="no-kirim-text">Data pengiriman belum tersedia</div>
                <div class="no-kirim-sub">Admin sedang memproses pesananmu. Detail pengiriman akan muncul di sini segera.</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="p-card-footer">
            <div>
                <div class="p-total-lbl">Total Tagihan</div>
                <div class="p-total">Rp <?= number_format($s['TOTAL_HARGA'],0,',','.') ?></div>
            </div>
            <div class="footer-actions">
                <a href="nota.php?id=<?= $s['ID_PESANAN'] ?>" class="btn-nota">
                    <i class="bi bi-receipt"></i> Nota
                </a>
            </div>
        </div>

    </div>
    <?php endforeach;
    else: ?>
    <div class="empty-state">
        <div class="empty-ico-wrap"><i class="bi bi-truck"></i></div>
        <h6>Belum Ada Pengiriman</h6>
        <p>Pengiriman akan muncul di sini setelah pesananmu masuk tahap proses. Yuk buat pesanan dulu! 🌸</p>
        <a href="pesan.php" class="btn-empty"><i class="bi bi-cart-plus-fill"></i> Pesan Sekarang</a>
    </div>
    <?php endif; ?>
    </div>

</div>
</main>

<!-- Floating -->
<a class="chat-btn" href="chat.php" title="Live Chat">
    <i class="bi bi-chat-dots-fill" style="font-size:22px;color:#fff"></i>
</a>
<a class="wa-btn" href="https://wa.me/62895414630496" target="_blank" title="WhatsApp">
    <i class="fa-brands fa-whatsapp" style="font-size:26px;color:#fff"></i>
</a>

<div class="toast-wrap" id="toast">
    <i class="bi bi-check-circle-fill"></i>
    <span id="toastMsg">Berhasil disalin!</span>
</div>

<script>
function copyResi(resi, id) {
    navigator.clipboard.writeText(resi).then(() => {
        const btn = document.getElementById('copy-' + id);
        const orig = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Tersalin!';
        showToast('Nomor resi berhasil disalin!');
        setTimeout(() => { btn.classList.remove('copied'); btn.innerHTML = orig; }, 2500);
    });
}
function showToast(msg) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}
function filterKirim(status, el) {
    document.querySelectorAll('.fpill').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.pengiriman-card').forEach(card => {
        card.style.display = (status === 'semua' || card.dataset.status === status) ? 'block' : 'none';
    });
}
</script>
</body>
</html>