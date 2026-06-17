<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$total_pelanggan = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pelanggan"));
$total_supplier  = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM supplier"));
$total_penjahit  = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM penjahit"));
$total_produk    = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM produk"));

$omset              = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(TOTAL_HARGA) as t FROM pesanan WHERE STATUS='Selesai'"))['t'] ?? 0;
// Tanpa filter STATUS_TERIMA
$biaya_gaji = mysqli_fetch_assoc(mysqli_query($koneksi, 
    "SELECT SUM(TOTAL_UPAH) as t FROM penggajian"))['t'] ?? 0;
// atau coba TOTAL_UPAH kalau JUMLAH_GAJI tetap 0:
// "SELECT SUM(TOTAL_UPAH) as t FROM penggajian"
$biaya_bahan        = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(TOTAL_BIAYA) as t FROM pembelian_bahan"))['t'] ?? 0;
$biaya_lain = mysqli_fetch_assoc(mysqli_query($koneksi, 
    "SELECT SUM(JUMLAH_PENGELUARAN) as t FROM pengeluaran 
     WHERE JENIS_PENGELUARAN != 'Perawatan'"))['t'] ?? 0;
$pengeluaran_servis = mysqli_fetch_assoc(mysqli_query($koneksi, 
    "SELECT SUM(JUMLAH_PENGELUARAN) as t FROM pengeluaran 
     WHERE JENIS_PENGELUARAN = 'Perawatan'"))['t'] ?? 0;
$total_pengeluaran = $biaya_gaji + $biaya_bahan + $pengeluaran_servis + $biaya_lain;
$laba_bersih        = $omset - $total_pengeluaran;
$hutang_bahan = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(TOTAL_BIAYA) as t FROM pembelian_bahan WHERE STATUS_BAYAR='Belum Dibayar'"))['t'] ?? 0;
$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat = 0; // Tabel chat_sesi tidak tersedia
$aset_rusak = 0; // Kolom KONDISI_ASET tidak tersedia di tabel aset
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$total_dikirim = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pesanan WHERE STATUS='Proses'"))['t'] ?? 0;
$total_pending = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pesanan WHERE STATUS='Pending'"))['t'] ?? 0;

$nama_owner = $_SESSION['user'];
$inisial = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}
$total_notif = $notif_bayar + $notif_chat + $stok_kritis + $aset_rusak;
$margin_pct  = $omset > 0 ? round(($laba_bersih / $omset) * 100, 1) : 0;

// ── Grafik 12 bulan
$chart_labels = []; $chart_omset = []; $chart_order = [];
for ($i = 11; $i >= 0; $i--) {
    $tgl = date('Y-m', strtotime("-$i months"));
    $chart_labels[] = date('M Y', strtotime("-$i months"));
    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT SUM(TOTAL_HARGA) as t, COUNT(*) as c FROM pesanan
         WHERE STATUS='Selesai' AND DATE_FORMAT(WAKTU_PESAN,'%Y-%m')='$tgl'"));
    $chart_omset[] = (float)($r['t'] ?? 0);
    $chart_order[] = (int)($r['c'] ?? 0);
}

// ── Tren bulan ini vs bulan lalu
$bln_ini  = date('Y-m');
$bln_lalu = date('Y-m', strtotime('-1 month'));
$omset_ini   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(TOTAL_HARGA) as t FROM pesanan WHERE STATUS='Selesai' AND DATE_FORMAT(WAKTU_PESAN,'%Y-%m')='$bln_ini'"))['t'] ?? 0;
$omset_lalu  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(TOTAL_HARGA) as t FROM pesanan WHERE STATUS='Selesai' AND DATE_FORMAT(WAKTU_PESAN,'%Y-%m')='$bln_lalu'"))['t'] ?? 0;
$order_ini   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pesanan WHERE DATE_FORMAT(WAKTU_PESAN,'%Y-%m')='$bln_ini'"))['t'] ?? 0;
$order_lalu  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pesanan WHERE DATE_FORMAT(WAKTU_PESAN,'%Y-%m')='$bln_lalu'"))['t'] ?? 0;
$tren_omset  = $omset_lalu > 0 ? round((($omset_ini - $omset_lalu) / $omset_lalu) * 100, 1) : 0;
$tren_order  = $order_lalu > 0 ? round((($order_ini - $order_lalu) / $order_lalu) * 100, 1) : 0;

// ── Produk terlaris
$produk_terlaris = []; $max_qty = 1;
$q_top = mysqli_query($koneksi,
    "SELECT pr.NAMA_PRODUK, SUM(dp.JUMLAH) as total_qty, COUNT(dp.ID_PESANAN) as total_order,
            SUM(p.TOTAL_HARGA) as total_rev
     FROM detail_pesanan dp
     JOIN produk pr ON dp.ID_PRODUK = pr.ID_PRODUK
     JOIN pesanan p ON dp.ID_PESANAN = p.ID_PESANAN
     WHERE p.STATUS='Selesai'
     GROUP BY dp.ID_PRODUK ORDER BY total_qty DESC LIMIT 5");
while ($r = mysqli_fetch_assoc($q_top)) {
    $produk_terlaris[] = $r;
    if ($r['total_qty'] > $max_qty) $max_qty = $r['total_qty'];
}

// ── Produktivitas penjahit
$penjahit_prod = [];
$q_pj = mysqli_query($koneksi,
    "SELECT pj.NAMA_PENJAHIT, COUNT(pg.ID_GAJI) as total_kerja,
            SUM(pg.TOTAL_UPAH) as total_upah, AVG(pg.TOTAL_UPAH) as avg_upah
     FROM penggajian pg
     JOIN produksi pr ON pg.ID_PRODUKSI = pr.ID_PRODUKSI
     JOIN penjahit pj ON pr.ID_PENJAHIT = pj.ID_PENJAHIT
     GROUP BY pj.ID_PENJAHIT
     ORDER BY total_upah DESC LIMIT 5");
while ($r = mysqli_fetch_assoc($q_pj)) $penjahit_prod[] = $r;

// ── Pesanan per status (untuk donut)
$q_status = mysqli_query($koneksi, "SELECT STATUS, COUNT(*) as n FROM pesanan GROUP BY STATUS");
$status_data = ['Pending'=>0,'Proses'=>0,'Selesai'=>0,'Dibatalkan'=>0];
while ($r = mysqli_fetch_assoc($q_status)) {
    if (isset($status_data[$r['STATUS']])) $status_data[$r['STATUS']] = (int)$r['n'];
}
$total_pesanan_all = array_sum($status_data);

// ── Aktivitas terbaru (feed)
$aktivitas = [];
$q_akt = mysqli_query($koneksi,
    "SELECT 'pesanan' as tipe, ID_PESANAN as ref_id, STATUS as info,
            WAKTU_PESAN as waktu, ID_PELANGGAN as sub
     FROM pesanan ORDER BY WAKTU_PESAN DESC LIMIT 8");
while ($r = mysqli_fetch_assoc($q_akt)) $aktivitas[] = $r;

// ── Omset hari ini
$omset_hari = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT SUM(TOTAL_HARGA) as t FROM pesanan WHERE STATUS='Selesai' AND DATE(WAKTU_PESAN)=CURDATE()"))['t'] ?? 0;
$order_hari = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) as t FROM pesanan WHERE DATE(WAKTU_PESAN)=CURDATE()"))['t'] ?? 0;

// ── Repeat customer rate
$repeat = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) as t FROM (SELECT ID_PELANGGAN FROM pesanan GROUP BY ID_PELANGGAN HAVING COUNT(*)>1) x"))['t'] ?? 0;
$repeat_rate = $total_pelanggan > 0 ? round(($repeat/$total_pelanggan)*100) : 0;

// ── AOV (Average Order Value)
$total_orders_done = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pesanan WHERE STATUS='Selesai'"))['t'] ?? 0;
$aov = $total_orders_done > 0 ? round($omset / $total_orders_done) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard 🌸 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root {
    --p50:#fff0f5;--p100:#ffd6e7;--p200:#ffadd0;--p300:#ff80b8;
    --p400:#f950a0;--p500:#e8328a;--p600:#cc1a73;--p700:#a8105d;
    --v100:#f3e8ff;--v300:#d8b4fe;--v500:#a855f7;--v600:#9333ea;
    --g100:#dcfce7;--g500:#22c55e;--g700:#15803d;
    --a100:#fef9c3;--a500:#eab308;--a700:#854d0e;
    --b100:#dbeafe;--b500:#3b82f6;--b700:#1d4ed8;
    --r100:#fee2e2;--r500:#ef4444;--r700:#991b1b;
    --o100:#ffedd5;--o500:#f97316;--o700:#9a3412;
    --white:#ffffff;--bg:#fff5f9;
    --text:#3d1a28;--text2:#7d4460;--text3:#b07090;
    --border:rgba(232,50,138,0.13);--border2:rgba(232,50,138,0.24);
    --shadow-sm:0 2px 8px rgba(232,50,138,0.08);
    --shadow-md:0 8px 28px rgba(232,50,138,0.12);
    --shadow-lg:0 16px 48px rgba(232,50,138,0.18);
    --sidebar-w:256px;--topbar-h:64px;
    --r-sm:10px;--r-md:16px;--r-lg:22px;--r-xl:28px;
    --ease:0.22s cubic-bezier(0.34,1.56,0.64,1);--ease-plain:0.17s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;font-size:14.5px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}

/* ── dot grid background ── */
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(232,50,138,0.05) 1.5px,transparent 1.5px);background-size:28px 28px;pointer-events:none;z-index:0}

::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:var(--p50)}
::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px}

/* ════ KEYFRAMES ════ */
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.4}}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
@keyframes scaleIn{from{opacity:0;transform:scale(0.92)}to{opacity:1;transform:none}}
@keyframes countUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@keyframes pulseRing{0%{box-shadow:0 0 0 0 rgba(232,50,138,0.35)}70%{box-shadow:0 0 0 10px rgba(232,50,138,0)}100%{box-shadow:0 0 0 0 rgba(232,50,138,0)}}
@keyframes slideRight{from{width:0}to{width:var(--w)}}
.pulse-anim{animation:pulseRing 2s ease-in-out infinite}
.fade-up{animation:fadeUp .4s ease both}

/* ════ SIDEBAR ════ */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--white);border-right:1.5px solid var(--border);display:flex;flex-direction:column;z-index:300;overflow:hidden;transition:transform .3s ease}
.sidebar::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;z-index:1;animation:shimmer 3s linear infinite}
.sb-brand{display:flex;align-items:center;gap:12px;padding:0 18px;height:var(--topbar-h);border-bottom:1.5px solid var(--border);text-decoration:none;flex-shrink:0;transition:background var(--ease-plain);margin-top:4px}
.sb-brand:hover{background:var(--p50)}
.brand-mark{width:38px;height:38px;border-radius:13px;background:linear-gradient(135deg,var(--p500),var(--p400),var(--v500));display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4),0 0 0 3px rgba(232,50,138,0.12);transition:transform var(--ease)}
.sb-brand:hover .brand-mark{transform:rotate(-8deg) scale(1.08)}
.brand-mark i{font-size:18px;color:#fff}
.brand-name{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text);white-space:nowrap}
.brand-sub{font-size:10px;font-weight:600;color:var(--p500);letter-spacing:.8px;text-transform:uppercase;margin-top:1px}
.sb-owner{margin:12px 12px 6px;padding:12px 14px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:var(--r-lg);display:flex;align-items:center;gap:10px;flex-shrink:0}
.owner-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;position:relative;box-shadow:0 3px 10px rgba(232,50,138,0.35)}
.owner-av::after{content:'';position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:var(--g500);border:2px solid var(--white)}
.owner-name{font-size:13.5px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.owner-role{font-size:11px;color:var(--p500);font-weight:600}
.sb-nav{flex:1;overflow-y:auto;padding:6px 10px 10px}
.nav-group-label{font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--text3);padding:14px 10px 4px;display:flex;align-items:center;gap:6px}
.nav-group-label::after{content:'✦';font-size:7px;color:var(--p300)}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:var(--r-sm);text-decoration:none;color:var(--text2);font-size:14px;font-weight:600;transition:all var(--ease-plain);margin-bottom:2px;position:relative;white-space:nowrap}
.nav-item i{font-size:17px;width:19px;text-align:center;flex-shrink:0;color:var(--text3);transition:color var(--ease-plain)}
.nav-item:hover{background:var(--p50);color:var(--p500);transform:translateX(2px)}
.nav-item:hover i{color:var(--p400)}
.nav-item.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-weight:700;box-shadow:0 4px 16px rgba(232,50,138,0.35)}
.nav-item.active i{color:rgba(255,255,255,0.9)}
.nav-item.active::after{content:'';position:absolute;right:10px;width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.6)}
.nav-pill{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0}
.pill-red{background:var(--r500)}.pill-orange{background:var(--o500)}.pill-pink{background:var(--p500)}
.nav-item.active .nav-pill{background:rgba(255,255,255,0.3)}
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0}
.nav-item.logout{color:var(--r700)}.nav-item.logout i{color:var(--r500)}.nav-item.logout:hover{background:var(--r100);color:var(--r700);transform:none}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(.85);opacity:.6}}
.pulse{animation:pulse 1.8s ease-in-out infinite}

/* ════ TOPBAR ════ */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(16px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px}
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
.confirm-pill{display:flex;align-items:center;gap:6px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border-radius:99px;padding:8px 16px;font-size:12.5px;font-weight:700;text-decoration:none;box-shadow:0 4px 14px rgba(232,50,138,0.4);transition:all var(--ease);white-space:nowrap}
.confirm-pill:hover{transform:translateY(-2px) scale(1.03);box-shadow:0 8px 22px rgba(232,50,138,0.5);color:#fff}

/* ════ LAYOUT ════ */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1}
.content{padding:24px 28px 80px;max-width:1440px}

/* ════ ALERTS ════ */
.alerts{display:flex;flex-direction:column;gap:10px;margin-bottom:22px}
.alert-card{display:flex;align-items:center;gap:14px;padding:13px 18px;border-radius:var(--r-md);border:1.5px solid;background:var(--white);animation:fadeUp .28s ease;box-shadow:var(--shadow-sm)}
.alert-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.alert-text{flex:1;font-size:13.5px;color:var(--text2);font-weight:500}
.alert-text b{color:var(--text);font-weight:700}
.alert-btn{display:flex;align-items:center;gap:5px;padding:7px 15px;border-radius:99px;font-size:12.5px;font-weight:700;text-decoration:none;white-space:nowrap;transition:all var(--ease-plain);border:1.5px solid}
.a-pink{border-color:var(--p200)}.a-pink .alert-ico{background:var(--p50);color:var(--p500)}.a-pink .alert-btn{background:var(--p500);color:#fff;border-color:var(--p500)}.a-pink .alert-btn:hover{background:var(--p600);color:#fff}
.a-red{border-color:#fca5a5}.a-red .alert-ico{background:var(--r100);color:var(--r500)}.a-red .alert-btn{background:var(--r100);color:var(--r700);border-color:#fca5a5}.a-red .alert-btn:hover{background:#fca5a5}
.a-amber{border-color:#fde047}.a-amber .alert-ico{background:var(--a100);color:var(--a500)}.a-amber .alert-btn{background:var(--a100);color:var(--a700);border-color:#fde047}.a-amber .alert-btn:hover{background:#fde047}
.a-orange{border-color:#fdba74}.a-orange .alert-ico{background:var(--o100);color:var(--o500)}.a-orange .alert-btn{background:var(--o100);color:var(--o700);border-color:#fdba74}.a-orange .alert-btn:hover{background:#fdba74}

/* ════ SECTION HEADER ════ */
.sec-hd{display:flex;align-items:center;justify-content:space-between;margin:24px 0 14px}
.sec-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.sec-dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:inline-block;box-shadow:0 0 0 3px rgba(232,50,138,0.15);flex-shrink:0}
.see-all{display:flex;align-items:center;gap:5px;font-size:12.5px;font-weight:700;color:var(--p500);text-decoration:none;padding:6px 14px;border:1.5px solid var(--border2);border-radius:99px;background:var(--p50);transition:all var(--ease)}
.see-all:hover{background:var(--p500);color:#fff;border-color:var(--p500);transform:scale(1.03)}

/* ════ KPI HERO ROW ════ */
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:14px}
.kpi-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);padding:22px 24px;position:relative;overflow:hidden;transition:transform var(--ease),box-shadow var(--ease);cursor:default}
.kpi-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-lg)}
.kpi-stripe{position:absolute;top:0;left:0;right:0;height:4px;border-radius:var(--r-xl) var(--r-xl) 0 0}
.kpi-glow{position:absolute;right:-20px;bottom:-20px;width:88px;height:88px;border-radius:50%;opacity:.07;pointer-events:none}
.kpi-icon-wrap{width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:14px;flex-shrink:0}
.kpi-label{font-size:12px;font-weight:600;color:var(--text3);margin-bottom:4px;display:flex;align-items:center;gap:5px}
.kpi-val{font-family:'Quicksand',sans-serif;font-size:22px;font-weight:700;line-height:1.1;margin-bottom:4px}
.kpi-sub{font-size:11px;color:var(--text3);font-weight:500}
.kpi-tren{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:700;margin-top:6px}
.kpi-up{background:var(--g100);color:var(--g700)}.kpi-down{background:var(--r100);color:var(--r700)}.kpi-flat{background:var(--a100);color:var(--a700)}

.kv-pink .kpi-stripe{background:linear-gradient(90deg,var(--p500),var(--p300))}.kv-pink .kpi-glow{background:var(--p500)}.kv-pink .kpi-icon-wrap{background:var(--p50);color:var(--p500)}.kv-pink .kpi-val{color:var(--p600)}
.kv-purple .kpi-stripe{background:linear-gradient(90deg,var(--v500),var(--v300))}.kv-purple .kpi-glow{background:var(--v500)}.kv-purple .kpi-icon-wrap{background:var(--v100);color:var(--v500)}.kv-purple .kpi-val{color:var(--v600)}
.kv-amber .kpi-stripe{background:linear-gradient(90deg,var(--a500),#fcd34d)}.kv-amber .kpi-glow{background:var(--a500)}.kv-amber .kpi-icon-wrap{background:var(--a100);color:var(--a500)}.kv-amber .kpi-val{color:var(--a700)}
.kv-green .kpi-stripe{background:linear-gradient(90deg,var(--g500),#86efac)}.kv-green .kpi-glow{background:var(--g500)}.kv-green .kpi-icon-wrap{background:var(--g100);color:var(--g500)}.kv-green .kpi-val{color:var(--g700)}

/* ════ LABA BANNER ════ */
.laba-banner{border-radius:var(--r-xl);padding:26px 32px;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden;margin-bottom:14px;box-shadow:var(--shadow-md)}
.laba-banner.profit{background:linear-gradient(135deg,var(--g700) 0%,#16a34a 55%,#4ade80 100%)}
.laba-banner.loss{background:linear-gradient(135deg,#991b1b 0%,var(--r500) 60%,#fca5a5 100%)}
.laba-banner::before{content:'';position:absolute;right:-40px;top:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.08);pointer-events:none}
.laba-banner::after{content:'';position:absolute;right:60px;bottom:-60px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,0.05);pointer-events:none}
.laba-eyebrow{font-size:11.5px;font-weight:700;color:rgba(255,255,255,0.65);margin-bottom:5px;letter-spacing:.3px}
.laba-val{font-family:'Quicksand',sans-serif;font-size:36px;font-weight:700;color:#fff;line-height:1}
.laba-note{font-size:12px;color:rgba(255,255,255,0.5);margin-top:5px;font-weight:500}
.laba-big-icon{font-size:64px;color:rgba(255,255,255,0.13);position:relative;z-index:1}
.laba-stats{display:flex;gap:28px;margin-top:14px}
.laba-stat{text-align:center}
.laba-stat-n{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:#fff}
.laba-stat-l{font-size:11px;color:rgba(255,255,255,0.55);margin-top:1px}

/* ════ BREAKDOWN GRID ════ */
.breakdown-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:24px}
.bd-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-md);padding:15px 16px;display:flex;align-items:center;gap:12px;transition:border-color var(--ease-plain),transform var(--ease),box-shadow var(--ease)}
.bd-card:hover{border-color:var(--border2);transform:translateY(-2px);box-shadow:var(--shadow-sm)}
.bd-ico{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.bd-lbl{font-size:11px;font-weight:600;color:var(--text3);margin-bottom:2px}
.bd-val{font-size:14px;font-weight:700;color:var(--text)}

/* ════ TODAY STRIP ════ */
.today-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.today-card{background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:var(--r-lg);padding:16px 18px;display:flex;align-items:center;gap:14px;transition:transform var(--ease),box-shadow var(--ease)}
.today-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md)}
.td-ico{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
.td-lbl{font-size:11.5px;font-weight:600;color:var(--text3);margin-bottom:2px}
.td-val{font-family:'Quicksand',sans-serif;font-size:19px;font-weight:700;color:var(--text)}

/* ════ TREN CARDS ════ */
.tren-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:14px}
.tren-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:20px 22px;display:flex;align-items:center;gap:16px;transition:transform var(--ease),box-shadow var(--ease)}
.tren-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md)}
.tren-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:21px;flex-shrink:0}
.tren-label{font-size:12px;font-weight:600;color:var(--text3);margin-bottom:3px}
.tren-val{font-family:'Quicksand',sans-serif;font-size:22px;font-weight:700;color:var(--text);line-height:1}
.tren-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;margin-top:5px}
.tren-up{background:var(--g100);color:var(--g700)}.tren-down{background:var(--r100);color:var(--r700)}.tren-flat{background:var(--a100);color:var(--a700)}

/* ════ EXPORT BUTTONS ════ */
.export-row{display:flex;gap:10px}
.btn-export{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:99px;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all var(--ease);text-decoration:none;font-family:'Nunito',sans-serif}
.btn-pdf{background:linear-gradient(135deg,var(--r500),#dc2626);color:#fff;box-shadow:0 4px 14px rgba(239,68,68,0.3)}
.btn-pdf:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(239,68,68,0.4);color:#fff}
.btn-excel{background:linear-gradient(135deg,var(--g700),var(--g500));color:#fff;box-shadow:0 4px 14px rgba(34,197,94,0.3)}
.btn-excel:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(34,197,94,0.4);color:#fff}

/* ════ ANALITIK LAYOUT ════ */
.analitik-main{display:grid;grid-template-columns:1fr 360px;gap:16px;margin-bottom:16px}
.analitik-bottom{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px}

/* Chart card */
.chart-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-sm)}
.chart-hd{padding:16px 22px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--p50),var(--white))}
.chart-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.chart-title i{color:var(--p500)}
.chart-body{padding:18px 20px}
.chart-toggle{display:flex;gap:4px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:3px}
.ct-btn{padding:5px 14px;border-radius:99px;font-size:12px;font-weight:700;color:var(--text2);border:none;background:none;cursor:pointer;transition:all var(--ease-plain);font-family:'Nunito',sans-serif}
.ct-btn.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;box-shadow:0 2px 8px rgba(232,50,138,0.3)}

/* Period filter */
.period-row{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap}
.period-btn{padding:5px 13px;border-radius:99px;font-size:12px;font-weight:700;border:1.5px solid var(--border);background:var(--white);color:var(--text2);cursor:pointer;transition:all var(--ease-plain);font-family:'Nunito',sans-serif}
.period-btn:hover{border-color:var(--border2);color:var(--p500)}
.period-btn.active{background:linear-gradient(135deg,var(--p500),var(--p400));border-color:transparent;color:#fff;box-shadow:0 2px 8px rgba(232,50,138,0.25)}

/* Produk terlaris */
.top-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-sm)}
.top-hd{padding:16px 22px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;justify-content:space-between}
.top-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.top-title i{color:var(--p500)}
.top-body{padding:14px 18px}
.top-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(232,50,138,0.07)}
.top-item:last-child{border-bottom:none}
.top-rank{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0}
.rank-1{background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#fff;box-shadow:0 2px 8px rgba(245,158,11,0.4)}
.rank-2{background:linear-gradient(135deg,#94a3b8,#cbd5e1);color:#fff}
.rank-3{background:linear-gradient(135deg,#b45309,#d97706);color:#fff}
.rank-other{background:var(--p50);color:var(--text3)}
.top-info{flex:1;min-width:0}
.top-name{font-size:13px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px}
.top-bar-wrap{height:5px;background:var(--p50);border-radius:99px;overflow:hidden}
.top-bar{height:100%;background:linear-gradient(90deg,var(--p500),var(--p300));border-radius:99px;transition:width .8s ease}
.top-qty{font-family:'Quicksand',sans-serif;font-size:14px;font-weight:700;color:var(--p600);flex-shrink:0}

/* Donut chart card */
.donut-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-sm)}
.donut-hd{padding:16px 22px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white))}
.donut-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.donut-title i{color:var(--p500)}
.donut-body{padding:16px 20px}
.donut-center{position:relative;max-width:200px;margin:0 auto 14px}
.donut-legend{display:flex;flex-direction:column;gap:8px}
.legend-item{display:flex;align-items:center;justify-content:space-between;gap:8px}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.legend-lbl{font-size:12px;font-weight:600;color:var(--text2);flex:1}
.legend-val{font-size:12px;font-weight:700;color:var(--text)}

/* Penjahit productivity */
.pj-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-sm)}
.pj-hd{padding:16px 22px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white))}
.pj-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.pj-title i{color:var(--p500)}
.pj-body{padding:14px 18px}
.pj-item{display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid rgba(232,50,138,0.07)}
.pj-item:last-child{border-bottom:none}
.pj-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--p400),var(--v500));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.pj-name{font-size:13px;font-weight:700;color:var(--text)}
.pj-meta{font-size:11px;color:var(--text3);margin-top:1px}
.pj-upah{font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:var(--p500);flex-shrink:0;white-space:nowrap}

/* Activity feed card */
.feed-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-sm)}
.feed-hd{padding:16px 22px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;justify-content:space-between}
.feed-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.feed-title i{color:var(--p500)}
.live-dot{width:8px;height:8px;border-radius:50%;background:var(--g500);animation:blink 1.2s ease-in-out infinite}
.feed-body{padding:6px 0;max-height:340px;overflow-y:auto}
.feed-item{display:flex;align-items:flex-start;gap:12px;padding:11px 20px;border-bottom:1px solid rgba(232,50,138,0.05);transition:background var(--ease-plain)}
.feed-item:hover{background:var(--p50)}
.feed-item:last-child{border-bottom:none}
.feed-ico{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;margin-top:1px}
.feed-text{flex:1;font-size:13px;color:var(--text2)}
.feed-text b{color:var(--text);font-weight:700}
.feed-time{font-size:11px;color:var(--text3);margin-top:2px}
.feed-badge{padding:2px 8px;border-radius:99px;font-size:10.5px;font-weight:700;flex-shrink:0;align-self:center}
.fb-selesai{background:var(--g100);color:var(--g700)}
.fb-proses{background:var(--p50);color:var(--p600)}
.fb-pending{background:var(--a100);color:var(--a700)}

/* ════ ENTITY CARDS ════ */
.entity-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
.ent-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);padding:22px 18px;text-align:center;text-decoration:none;color:inherit;display:block;transition:all var(--ease);position:relative;overflow:hidden;box-shadow:var(--shadow-sm)}
.ent-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-lg);border-color:var(--border2);color:inherit}
.ent-deco{position:absolute;right:-20px;bottom:-20px;width:90px;height:90px;border-radius:50%;opacity:.06;transition:transform .3s ease}
.ent-card:hover .ent-deco{transform:scale(1.3)}
.ent-ico{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto 12px}
.ent-num{font-family:'Quicksand',sans-serif;font-size:32px;font-weight:700;line-height:1;margin-bottom:4px}
.ent-lbl{font-size:12px;color:var(--text2);font-weight:600}

/* ════ QUICK ACTIONS ════ */
.quick-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px}
.q-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-md);padding:14px;display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--text);transition:all var(--ease);position:relative;box-shadow:var(--shadow-sm)}
.q-card:hover{border-color:var(--border2);background:var(--p50);transform:translateY(-3px);box-shadow:var(--shadow-md);color:var(--text)}
.q-ico{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.q-name{font-size:13px;font-weight:700}
.q-hint{font-size:11px;color:var(--text3);font-weight:500;margin-top:1px}
.q-notif{position:absolute;top:7px;right:9px;background:var(--p500);color:#fff;border-radius:99px;font-size:10px;font-weight:800;padding:2px 7px;animation:blink 1.6s ease-in-out infinite}

/* ════ TABLE CARD ════ */
.tbl-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-sm)}
.tbl-hd{padding:16px 22px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--p50),var(--white))}
.tbl-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.tbl-title i{color:var(--p500)}
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{padding:11px 20px;font-size:11.5px;font-weight:700;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border)}
.data-table tbody td{padding:13px 20px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:14px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr{transition:background var(--ease-plain)}
.data-table tbody tr:hover td{background:var(--p50)}
.id-tag{display:inline-flex;align-items:center;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:7px;padding:3px 9px;font-size:12px;font-weight:700}
.id-tag.green{background:var(--g100);color:var(--g700);border-color:rgba(34,197,94,0.25)}
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 11px;border-radius:99px;font-size:12px;font-weight:700}
.badge i{font-size:10px}
.badge-g{background:var(--g100);color:var(--g700)}.badge-b{background:var(--b100);color:var(--b700)}.badge-y{background:var(--a100);color:var(--a700)}

/* ════ GOAL / TARGET CARD ════ */
.target-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);padding:22px 24px;box-shadow:var(--shadow-sm)}
.target-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.target-title{font-family:'Quicksand',sans-serif;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.target-title i{color:var(--p500)}
.target-item{margin-bottom:16px}
.target-item:last-child{margin-bottom:0}
.target-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.target-lbl{font-size:13px;font-weight:600;color:var(--text2)}
.target-pct{font-size:12px;font-weight:700;color:var(--p500)}
.target-bar{height:8px;background:var(--p50);border-radius:99px;overflow:hidden}
.target-fill{height:100%;border-radius:99px;transition:width 1s ease}

/* ════ RESPONSIVE ════ */
@media(max-width:1280px){
    .kpi-row,.breakdown-grid{grid-template-columns:repeat(2,1fr)}
    .entity-grid,.quick-grid{grid-template-columns:repeat(3,1fr)}
    .analitik-main{grid-template-columns:1fr}
    .analitik-bottom{grid-template-columns:1fr 1fr}
    .today-strip{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:900px){
    .sidebar{transform:translateX(-100%)}
    .topbar{left:0}.main{margin-left:0}
    .kpi-row,.entity-grid,.quick-grid{grid-template-columns:repeat(2,1fr)}
    .analitik-bottom{grid-template-columns:1fr}
    .tren-grid{grid-template-columns:1fr}
    .laba-banner{flex-direction:column;gap:14px}
    .today-strip{grid-template-columns:1fr 1fr}
}
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
        <a class="nav-item active" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
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
        <a class="nav-item" href="aturan_bonus_penalti.php"><i class="bi bi-sliders"></i> Aturan Bonus & Penalti</a>
        <a class="nav-item" href="pantau_deadline.php"><i class="bi bi-alarm-fill"></i> Pantau Deadline</a>
        <a class="nav-item" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="nav-item" href="konfirmasi_pembayaran.php">
            <i class="bi bi-credit-card-2-front"></i> Konfirmasi Bayar
            <?php if ($notif_bayar > 0): ?><span class="nav-pill pill-pink pulse"><?= $notif_bayar ?></span><?php endif; ?>
        </a>
        <a class="nav-item" href="input_pengiriman.php"><i class="bi bi-truck-front-fill"></i> Input Pengiriman</a>
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

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Halo, <?= htmlspecialchars($nama_owner) ?>! 🌸</div>
        <div class="tb-sub">Semangat ya, ini ringkasan bisnis kamu hari ini 💪</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item active" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi</a>
        <a class="tb-nav-item" href="laporan.php"><i class="bi bi-bar-chart-line"></i> Laporan</a>
    </nav>
    <div class="tb-divider"></div>
    <div class="tb-actions">
        <a href="<?= $total_notif > 0 ? 'konfirmasi_pembayaran.php' : '#' ?>" class="icon-btn">
            <i class="bi bi-bell-fill"></i>
            <?php if ($total_notif > 0): ?><span class="dot"></span><?php endif; ?>
        </a>
        <?php if ($notif_bayar > 0): ?>
        <a href="konfirmasi_pembayaran.php" class="confirm-pill">
            <i class="bi bi-credit-card-2-front-fill"></i> <?= $notif_bayar ?> Pembayaran
        </a>
        <?php endif; ?>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- ALERTS -->
    <?php if ($notif_bayar>0||$stok_kritis>0||$hutang_bahan>0||$aset_rusak>0): ?>
    <div class="alerts">
        <?php if ($notif_bayar>0): ?>
        <div class="alert-card a-pink">
            <div class="alert-ico"><i class="bi bi-credit-card-2-front-fill"></i></div>
            <div class="alert-text"><b><?= $notif_bayar ?> pembayaran</b> lagi nunggu konfirmasi dari pelanggan.</div>
            <a href="konfirmasi_pembayaran.php" class="alert-btn">Konfirmasi Sekarang <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php endif; ?>
        <?php if ($stok_kritis>0): ?>
        <div class="alert-card a-red">
            <div class="alert-ico"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div class="alert-text"><b><?= $stok_kritis ?> bahan baku</b> stoknya hampir habis nih!</div>
            <a href="kelola_bahan.php" class="alert-btn">Cek Stok <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php endif; ?>
        <?php if ($hutang_bahan>0): ?>
        <div class="alert-card a-amber">
            <div class="alert-ico"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="alert-text">Masih ada hutang bahan belum lunas: <b>Rp <?= number_format($hutang_bahan) ?></b></div>
            <a href="supplier.php" class="alert-btn">Lihat Supplier <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php endif; ?>
        <?php if ($aset_rusak>0): ?>
        <div class="alert-card a-orange">
            <div class="alert-ico"><i class="bi bi-tools"></i></div>
            <div class="alert-text"><b><?= $aset_rusak ?> aset</b> butuh perbaikan atau perhatian segera.</div>
            <a href="kelola_aset.php" class="alert-btn">Cek Aset <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── TODAY STRIP ── -->
    <div class="today-strip fade-up">
        <div class="today-card">
            <div class="td-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-sun-fill"></i></div>
            <div>
                <div class="td-lbl">Omset Hari Ini</div>
                <div class="td-val" style="color:var(--p600)">Rp <?= number_format($omset_hari) ?></div>
            </div>
        </div>
        <div class="today-card">
            <div class="td-ico" style="background:var(--v100);color:var(--v500)"><i class="bi bi-bag-heart-fill"></i></div>
            <div>
                <div class="td-lbl">Order Masuk Hari Ini</div>
                <div class="td-val" style="color:var(--v600)"><?= $order_hari ?> pesanan</div>
            </div>
        </div>
        <div class="today-card">
            <div class="td-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-arrow-repeat"></i></div>
            <div>
                <div class="td-lbl">Repeat Customer</div>
                <div class="td-val" style="color:var(--g700)"><?= $repeat_rate ?>%</div>
            </div>
        </div>
        <div class="today-card">
            <div class="td-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-calculator-fill"></i></div>
            <div>
                <div class="td-lbl">Rata-rata Nilai Order</div>
                <div class="td-val" style="color:var(--a700)">Rp <?= number_format($aov) ?></div>
            </div>
        </div>
    </div>

    <!-- ── KPI CARDS ── -->
    <div class="sec-hd" style="margin-top:4px">
        <div class="sec-title"><span class="sec-dot"></span> Ringkasan Keuangan 💰</div>
        <a href="laporan.php" class="see-all">Laporan Lengkap <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="kpi-row">
        <div class="kpi-card kv-pink fade-up" style="animation-delay:.04s">
            <div class="kpi-stripe"></div>
            <div class="kpi-icon-wrap"><i class="bi bi-arrow-up-circle-fill"></i></div>
            <div class="kpi-label"><i class="bi bi-circle-fill" style="font-size:6px;color:var(--p300)"></i> Total Pendapatan</div>
            <div class="kpi-val counter" data-target="<?= $omset ?>">Rp 0</div>
            <div class="kpi-sub">Dari semua pesanan selesai</div>
            <?php $ti=$tren_omset; ?>
            <span class="kpi-tren <?= $ti>0?'kpi-up':($ti<0?'kpi-down':'kpi-flat') ?>">
                <i class="bi bi-<?= $ti>0?'arrow-up-right':($ti<0?'arrow-down-right':'dash') ?>"></i>
                <?= abs($ti) ?>% vs bulan lalu
            </span>
            <div class="kpi-glow" style="background:var(--p500)"></div>
        </div>
        <div class="kpi-card kv-purple fade-up" style="animation-delay:.08s">
            <div class="kpi-stripe"></div>
            <div class="kpi-icon-wrap"><i class="bi bi-arrow-down-circle-fill"></i></div>
            <div class="kpi-label"><i class="bi bi-circle-fill" style="font-size:6px;color:var(--v300)"></i> Total Pengeluaran</div>
            <div class="kpi-val counter-idr" data-target="<?= $total_pengeluaran ?>">Rp 0</div>
            <div class="kpi-sub">Gaji + Bahan + Servis + Lainnya</div>
            <div class="kpi-glow" style="background:var(--v500)"></div>
        </div>
        <div class="kpi-card kv-amber fade-up" style="animation-delay:.12s">
            <div class="kpi-stripe"></div>
            <div class="kpi-icon-wrap"><i class="bi bi-clock-history"></i></div>
            <div class="kpi-label"><i class="bi bi-circle-fill" style="font-size:6px;color:var(--a500)"></i> Hutang Supplier</div>
            <div class="kpi-val counter-idr" data-target="<?= $hutang_bahan ?>">Rp 0</div>
            <div class="kpi-sub">Pembelian belum dibayar</div>
            <div class="kpi-glow" style="background:var(--a500)"></div>
        </div>
        <div class="kpi-card kv-green fade-up" style="animation-delay:.16s">
            <div class="kpi-stripe"></div>
            <div class="kpi-icon-wrap"><i class="bi bi-percent"></i></div>
            <div class="kpi-label"><i class="bi bi-circle-fill" style="font-size:6px;color:var(--g500)"></i> Margin Keuntungan</div>
            <div class="kpi-val"><?= $margin_pct ?>%</div>
            <div class="kpi-sub">Laba bersih / pendapatan</div>
            <div class="kpi-glow" style="background:var(--g500)"></div>
        </div>
    </div>

    <!-- LABA BANNER -->
    <div class="laba-banner <?= $laba_bersih>=0?'profit':'loss' ?> fade-up" style="animation-delay:.18s">
        <div>
            <div class="laba-eyebrow"><?= $laba_bersih>=0?'📈 Laba Bersih — Keren!':'📉 Rugi Bersih' ?></div>
            <div class="laba-val">Rp <?= number_format(abs($laba_bersih)) ?></div>
            <div class="laba-note">Pendapatan − Total Pengeluaran</div>
            <div class="laba-stats">
                <div class="laba-stat">
                    <div class="laba-stat-n"><?= $total_orders_done ?></div>
                    <div class="laba-stat-l">Pesanan Selesai</div>
                </div>
                <div class="laba-stat">
                    <div class="laba-stat-n">Rp <?= number_format($aov) ?></div>
                    <div class="laba-stat-l">Avg Order Value</div>
                </div>
                <div class="laba-stat">
                    <div class="laba-stat-n"><?= $repeat_rate ?>%</div>
                    <div class="laba-stat-l">Repeat Rate</div>
                </div>
            </div>
        </div>
        <i class="bi <?= $laba_bersih>=0?'bi-graph-up-arrow':'bi-graph-down-arrow' ?> laba-big-icon"></i>
    </div>

    <div class="breakdown-grid">
        <div class="bd-card"><div class="bd-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-scissors"></i></div><div><div class="bd-lbl">Gaji Penjahit</div><div class="bd-val">Rp <?= number_format($biaya_gaji) ?></div></div></div>
        <div class="bd-card"><div class="bd-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-basket"></i></div><div><div class="bd-lbl">Pembelian Bahan</div><div class="bd-val">Rp <?= number_format($biaya_bahan) ?></div></div></div>
        <div class="bd-card"><div class="bd-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-tools"></i></div><div><div class="bd-lbl">Servis Aset</div><div class="bd-val">Rp <?= number_format($pengeluaran_servis) ?></div></div></div>
        <div class="bd-card"><div class="bd-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-three-dots"></i></div><div><div class="bd-lbl">Pengeluaran Lainnya</div><div class="bd-val">Rp <?= number_format($biaya_lain) ?></div></div></div>
    </div>

    <!-- ── TREN + EXPORT ── -->
    <div class="sec-hd">
        <div class="sec-title"><span class="sec-dot"></span> Tren Bulan Ini vs Bulan Lalu 📈</div>
        <div class="export-row">
            <button class="btn-export btn-pdf" onclick="exportPDF()"><i class="bi bi-file-earmark-pdf-fill"></i> Export PDF</button>
            <button class="btn-export btn-excel" onclick="exportExcel()"><i class="bi bi-file-earmark-excel-fill"></i> Export Excel</button>
        </div>
    </div>
    <div class="tren-grid">
        <div class="tren-card fade-up" style="animation-delay:.05s">
            <div class="tren-icon" style="background:var(--p50);color:var(--p500)"><i class="bi bi-currency-dollar"></i></div>
            <div>
                <div class="tren-label">Pendapatan Bulan Ini</div>
                <div class="tren-val">Rp <?= number_format($omset_ini) ?></div>
                <?php $ti=$tren_omset; ?>
                <span class="tren-badge <?= $ti>0?'tren-up':($ti<0?'tren-down':'tren-flat') ?>">
                    <i class="bi bi-<?= $ti>0?'arrow-up':($ti<0?'arrow-down':'dash') ?>"></i> <?= abs($ti) ?>% vs bulan lalu
                </span>
            </div>
        </div>
        <div class="tren-card fade-up" style="animation-delay:.1s">
            <div class="tren-icon" style="background:var(--v100);color:var(--v500)"><i class="bi bi-bag-heart-fill"></i></div>
            <div>
                <div class="tren-label">Pesanan Bulan Ini</div>
                <div class="tren-val"><?= $order_ini ?> pesanan</div>
                <?php $to=$tren_order; ?>
                <span class="tren-badge <?= $to>0?'tren-up':($to<0?'tren-down':'tren-flat') ?>">
                    <i class="bi bi-<?= $to>0?'arrow-up':($to<0?'arrow-down':'dash') ?>"></i> <?= abs($to) ?>% vs bulan lalu
                </span>
            </div>
        </div>
    </div>

    <!-- ── ANALITIK UTAMA: GRAFIK + PRODUK TERLARIS ── -->
    <div class="sec-hd">
        <div class="sec-title"><span class="sec-dot"></span> Analitik Penjualan 📊</div>
    </div>
    <div class="analitik-main">
        <div class="chart-card fade-up">
            <div class="chart-hd">
                <div class="chart-title"><i class="bi bi-bar-chart-fill"></i> Grafik Penjualan</div>
                <div style="display:flex;align-items:center;gap:10px">
                    <div class="chart-toggle">
                        <button class="ct-btn active" id="btn-omset" onclick="switchChart('omset')">Pendapatan</button>
                        <button class="ct-btn" id="btn-order" onclick="switchChart('order')">Pesanan</button>
                    </div>
                </div>
            </div>
            <div class="chart-body">
                <div class="period-row">
                    <button class="period-btn" onclick="setPeriod(3,this)">3 Bln</button>
                    <button class="period-btn" onclick="setPeriod(6,this)">6 Bln</button>
                    <button class="period-btn active" onclick="setPeriod(12,this)">12 Bln</button>
                </div>
                <canvas id="salesChart" height="200"></canvas>
            </div>
        </div>
        <div class="top-card fade-up" style="animation-delay:.08s">
            <div class="top-hd">
                <div class="top-title"><i class="bi bi-trophy-fill"></i> Produk Terlaris</div>
            </div>
            <div class="top-body">
                <?php if (!empty($produk_terlaris)): foreach ($produk_terlaris as $i => $p):
                    $pct = round(($p['total_qty']/$max_qty)*100);
                    $rc  = match($i){0=>'rank-1',1=>'rank-2',2=>'rank-3',default=>'rank-other'};
                ?>
                <div class="top-item">
                    <div class="top-rank <?= $rc ?>"><?= $i+1 ?></div>
                    <div class="top-info">
                        <div class="top-name"><?= htmlspecialchars($p['NAMA_PRODUK']) ?></div>
                        <div class="top-bar-wrap"><div class="top-bar" style="width:<?= $pct ?>%"></div></div>
                        <div style="font-size:11px;color:var(--text3);margin-top:3px"><?= $p['total_order'] ?> order · Rp <?= number_format($p['total_rev']) ?></div>
                    </div>
                    <div class="top-qty"><?= number_format($p['total_qty']) ?> pcs</div>
                </div>
                <?php endforeach; else: ?>
                <div style="text-align:center;padding:32px;color:var(--text3)">
                    <i class="bi bi-trophy" style="font-size:32px;color:var(--p200);display:block;margin-bottom:8px"></i>
                    Belum ada data produk terlaris
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── ANALITIK BAWAH: DONUT + PENJAHIT + AKTIVITAS ── -->
    <div class="analitik-bottom">
        <!-- Donut: status pesanan -->
        <div class="donut-card fade-up">
            <div class="donut-hd">
                <div class="donut-title"><i class="bi bi-pie-chart-fill"></i> Status Pesanan</div>
            </div>
            <div class="donut-body">
                <div class="donut-center">
                    <canvas id="donutChart" height="180"></canvas>
                </div>
                <div class="donut-legend">
                    <div class="legend-item"><span class="legend-dot" style="background:#e8328a"></span><span class="legend-lbl">Pending</span><span class="legend-val"><?= $status_data['Pending'] ?></span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:#3b82f6"></span><span class="legend-lbl">Proses</span><span class="legend-val"><?= $status_data['Proses'] ?></span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:#22c55e"></span><span class="legend-lbl">Selesai</span><span class="legend-val"><?= $status_data['Selesai'] ?></span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:#94a3b8"></span><span class="legend-lbl">Dibatalkan</span><span class="legend-val"><?= $status_data['Dibatalkan'] ?></span></div>
                </div>
            </div>
        </div>

        <!-- Produktivitas penjahit -->
        <div class="pj-card fade-up" style="animation-delay:.06s">
            <div class="pj-hd">
                <div class="pj-title"><i class="bi bi-person-badge-fill"></i> Top Penjahit</div>
            </div>
            <div class="pj-body">
                <?php if (!empty($penjahit_prod)): foreach ($penjahit_prod as $pj):
                    $initPj = strtoupper(substr($pj['NAMA_PENJAHIT'],0,1));
                ?>
                <div class="pj-item">
                    <div class="pj-av"><?= $initPj ?></div>
                    <div style="flex:1;min-width:0">
                        <div class="pj-name"><?= htmlspecialchars($pj['NAMA_PENJAHIT']) ?></div>
                        <div class="pj-meta"><?= $pj['total_kerja'] ?> kali · Avg Rp <?= number_format($pj['avg_upah'] ?? 0) ?></div>
                    </div>
                    <div class="pj-upah">Rp <?= number_format($pj['total_upah'] ?? 0) ?></div>                
                </div>
                <?php endforeach; else: ?>
                <div style="text-align:center;padding:24px;color:var(--text3)">
                    <i class="bi bi-people" style="font-size:28px;color:var(--p200);display:block;margin-bottom:8px"></i>
                    Belum ada data
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity feed -->
        <div class="feed-card fade-up" style="animation-delay:.12s">
            <div class="feed-hd">
                <div class="feed-title"><i class="bi bi-activity"></i> Aktivitas Terbaru</div>
                <div class="live-dot"></div>
            </div>
            <div class="feed-body">
                <?php if (!empty($aktivitas)): foreach ($aktivitas as $ak):
                    $st = $ak['info']??'Pending';
                    $bc = ($st=='Selesai')?'fb-selesai':(($st=='Proses')?'fb-proses':'fb-pending');
                    $ico = ($st=='Selesai')?'check-circle-fill':(($st=='Proses')?'arrow-repeat':'clock');
                    $ico_bg = ($st=='Selesai')?'var(--g100)':(($st=='Proses')?'var(--p50)':'var(--a100)');
                    $ico_c  = ($st=='Selesai')?'var(--g700)':(($st=='Proses')?'var(--p500)':'var(--a700)');
                    $waktu  = date('d M, H:i', strtotime($ak['waktu']));
                ?>
                <div class="feed-item">
                    <div class="feed-ico" style="background:<?= $ico_bg ?>;color:<?= $ico_c ?>"><i class="bi bi-<?= $ico ?>"></i></div>
                    <div class="feed-text">
                        Pesanan <b>#<?= htmlspecialchars($ak['ref_id']) ?></b>
                        <div class="feed-time"><i class="bi bi-clock" style="font-size:10px"></i> <?= $waktu ?></div>
                    </div>
                    <span class="feed-badge <?= $bc ?>"><?= $st ?></span>
                </div>
                <?php endforeach; else: ?>
                <div style="text-align:center;padding:32px;color:var(--text3)">Belum ada aktivitas</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── ENTITY + QUICK ACTIONS ── -->
    <div class="sec-hd"><div class="sec-title"><span class="sec-dot"></span> Data Entitas 🏪</div></div>
    <div class="entity-grid">
        <a class="ent-card" href="pelanggan.php">
            <div class="ent-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-people-fill"></i></div>
            <div class="ent-num" style="color:var(--b500)"><?= $total_pelanggan ?></div>
            <div class="ent-lbl">Pelanggan</div>
            <div class="ent-deco" style="background:var(--b500)"></div>
        </a>
        <a class="ent-card" href="supplier.php">
            <div class="ent-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-truck"></i></div>
            <div class="ent-num" style="color:var(--g500)"><?= $total_supplier ?></div>
            <div class="ent-lbl">Supplier</div>
            <div class="ent-deco" style="background:var(--g500)"></div>
        </a>
        <a class="ent-card" href="data_penjahit.php">
            <div class="ent-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-scissors"></i></div>
            <div class="ent-num" style="color:var(--a500)"><?= $total_penjahit ?></div>
            <div class="ent-lbl">Penjahit</div>
            <div class="ent-deco" style="background:var(--a500)"></div>
        </a>
        <a class="ent-card" href="kelola_produk.php">
            <div class="ent-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-box-seam"></i></div>
            <div class="ent-num" style="color:var(--p500)"><?= $total_produk ?></div>
            <div class="ent-lbl">Varian Produk</div>
            <div class="ent-deco" style="background:var(--p500)"></div>
        </a>
        <a class="ent-card" href="input_pengiriman.php">
            <div class="ent-ico" style="background:var(--v100);color:var(--v500)"><i class="bi bi-truck-front-fill"></i></div>
            <div class="ent-num" style="color:var(--v500)"><?= $total_dikirim ?></div>
            <div class="ent-lbl">Sedang Dikirim</div>
            <div class="ent-deco" style="background:var(--v500)"></div>
        </a>
    </div>

    <div class="sec-hd"><div class="sec-title"><span class="sec-dot"></span> Pintasan Cepat ⚡</div></div>
    <div class="quick-grid">
        <a href="konfirmasi_pembayaran.php" class="q-card">
            <?php if ($notif_bayar>0): ?><span class="q-notif"><?= $notif_bayar ?> baru</span><?php endif; ?>
            <div class="q-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-credit-card-2-front-fill"></i></div>
            <div><div class="q-name">Konfirmasi Bayar</div><div class="q-hint"><?= $notif_bayar>0?"$notif_bayar menunggu 🔔":"Semua beres ✓" ?></div></div>
        </a>
        <a href="produksi.php" class="q-card">
            <div class="q-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-gear-wide-connected"></i></div>
            <div><div class="q-name">Produksi Aktif</div><div class="q-hint">Pantau progress 👀</div></div>
        </a>
        <a href="input_pengiriman.php" class="q-card">
            <?php if ($total_dikirim>0): ?><span class="q-notif"><?= $total_dikirim ?></span><?php endif; ?>
            <div class="q-ico" style="background:var(--v100);color:var(--v500)"><i class="bi bi-truck-front-fill"></i></div>
            <div><div class="q-name">Input Pengiriman</div><div class="q-hint"><?= $total_dikirim>0?"$total_dikirim perlu resi 🚚":"Semua terkirim ✓" ?></div></div>
        </a>
        <a href="penggajian.php" class="q-card">
            <div class="q-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-cash-stack"></i></div>
            <div><div class="q-name">Penggajian</div><div class="q-hint">Bayar penjahit 💸</div></div>
        </a>
        <a href="laporan.php" class="q-card">
            <div class="q-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-file-earmark-bar-graph"></i></div>
            <div><div class="q-name">Laporan Keuangan</div><div class="q-hint">Lihat rekapitulasi 📊</div></div>
        </a>
    </div>

    <!-- ── TABEL PESANAN ── -->
    <div class="sec-hd" style="margin-top:4px">
        <div class="sec-title"><span class="sec-dot"></span> Pesanan Terbaru 🛍️</div>
        <a href="laporan.php" class="see-all">Lihat Semua <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="tbl-card" style="margin-bottom:20px">
        <div class="tbl-hd"><div class="tbl-title"><i class="bi bi-bag-heart-fill"></i> Daftar Pesanan</div></div>
        <table class="data-table">
            <thead><tr><th>ID Pesanan</th><th>Pelanggan</th><th>Produk</th><th>Total Harga</th><th>Status</th></tr></thead>
            <tbody>
            <?php
            $q = mysqli_query($koneksi, "SELECT p.ID_PESANAN,p.TOTAL_HARGA,p.STATUS,p.WAKTU_PESAN,pl.NAMA_PELANGGAN,pr.NAMA_PRODUK FROM pesanan p LEFT JOIN pelanggan pl ON p.ID_PELANGGAN=pl.ID_PELANGGAN LEFT JOIN detail_pesanan dp ON p.ID_PESANAN=dp.ID_PESANAN LEFT JOIN produk pr ON dp.ID_PRODUK=pr.ID_PRODUK ORDER BY p.WAKTU_PESAN DESC LIMIT 8");
            if (mysqli_num_rows($q)>0): while ($row=mysqli_fetch_assoc($q)):
                $st=$row['STATUS']??'Pending';$sc=($st=='Selesai')?'badge-g':(($st=='Proses')?'badge-b':'badge-y');$si=($st=='Selesai')?'check-circle-fill':(($st=='Proses')?'arrow-repeat':'clock');
            ?>
            <tr>
                <td><span class="id-tag"><?= htmlspecialchars($row['ID_PESANAN']) ?></span></td>
                <td style="font-weight:700"><?= htmlspecialchars($row['NAMA_PELANGGAN']??'-') ?></td>
                <td style="color:var(--text2)"><?= htmlspecialchars($row['NAMA_PRODUK']??'-') ?></td>
                <td style="font-weight:700;color:var(--p600)">Rp <?= number_format($row['TOTAL_HARGA']) ?></td>
                <td><span class="badge <?= $sc ?>"><i class="bi bi-<?= $si ?>"></i> <?= $st ?></span></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="5" style="text-align:center;padding:36px;color:var(--text3)"><i class="bi bi-bag-x" style="font-size:28px;display:block;margin-bottom:8px;color:var(--p200)"></i>Belum ada pesanan nih 🌸</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TABEL BAHAN -->
    <div class="sec-hd">
        <div class="sec-title"><span class="sec-dot"></span> Pembelian Bahan Terbaru 🧶</div>
        <a href="supplier.php" class="see-all">Lihat Supplier <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="tbl-card">
        <div class="tbl-hd"><div class="tbl-title"><i class="bi bi-basket-fill"></i> Pembelian Bahan</div></div>
        <table class="data-table">
            <thead><tr><th>ID</th><th>Tanggal</th><th>Supplier</th><th>Total</th><th>Status Bayar</th></tr></thead>
            <tbody>
            <?php
            $q4=mysqli_query($koneksi,"SELECT pb.*,s.NAMA_SUPPLIER FROM pembelian_bahan pb LEFT JOIN supplier s ON pb.ID_SUPPLIER=s.ID_SUPPLIER ORDER BY pb.TANGGAL_BELI DESC LIMIT 6");
            if (mysqli_num_rows($q4)>0): while ($pb=mysqli_fetch_assoc($q4)):
                $sb = $pb['STATUS_BAYAR'] ?? 'Belum Dibayar';
                $sc2 = $sb == 'Sudah Dibayar' ? 'badge-g' : 'badge-y';
                $si2 = $sb == 'Sudah Dibayar' ? 'check-circle-fill' : 'clock';
                $sl2 = $sb;            
            ?>
            <tr>
                <td><span class="id-tag green"><?= htmlspecialchars($pb['ID_PEMBELIAN']) ?></span></td>
                <td style="color:var(--text2)"><?= date('d/m/Y',strtotime($pb['TANGGAL_BELI'])) ?></td>
                <td style="font-weight:700"><?= htmlspecialchars($pb['NAMA_SUPPLIER']??$pb['ID_SUPPLIER']) ?></td>
                <td style="font-weight:700;color:var(--r500)">Rp <?= number_format($pb['TOTAL_BIAYA']) ?></td>
                <td><span class="badge <?= $sc2 ?>"><i class="bi bi-<?= $si2 ?>"></i> <?= $sl2 ?></span></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="5" style="text-align:center;padding:36px;color:var(--text3)"><i class="bi bi-basket2" style="font-size:28px;display:block;margin-bottom:8px;color:var(--p200)"></i>Belum ada data pembelian 🌸</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</main>

<script>
/* ── Data PHP → JS ── */
const allLabels = <?= json_encode($chart_labels) ?>;
const allOmset  = <?= json_encode($chart_omset) ?>;
const allOrder  = <?= json_encode($chart_order) ?>;
const donutData = <?= json_encode(array_values($status_data)) ?>;

let currentMode   = 'omset';
let currentPeriod = 12;
let salesChart, donutChart;

/* ── Build Sales Chart ── */
function buildChart(mode, period) {
    const ctx = document.getElementById('salesChart').getContext('2d');
    if (salesChart) salesChart.destroy();
    const labels = allLabels.slice(-period);
    const data   = (mode === 'omset' ? allOmset : allOrder).slice(-period);
    const isOmset = mode === 'omset';
    const c1 = '#e8328a', c2 = '#a855f7';
    const grad = ctx.createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, c1 + '30');
    grad.addColorStop(1, c1 + '04');

    salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: data.map((v, i) => {
                    const g = ctx.createLinearGradient(0, 0, 0, 200);
                    g.addColorStop(0, i === data.indexOf(Math.max(...data)) ? '#e8328a' : '#f950a0');
                    g.addColorStop(1, i === data.indexOf(Math.max(...data)) ? '#a855f7' : '#ffadd0');
                    return g;
                }),
                borderRadius: 10,
                borderSkipped: false,
                maxBarThickness: 40,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#3d1a28',
                    titleColor: '#ffadd0',
                    bodyColor: '#fff',
                    cornerRadius: 12,
                    padding: 14,
                    callbacks: {
                        label: ctx => isOmset
                            ? '  Rp ' + ctx.raw.toLocaleString('id-ID')
                            : '  ' + ctx.raw + ' pesanan'
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { family: 'Nunito', size: 11 }, color: '#b07090', maxRotation: 45 } },
                y: {
                    grid: { color: 'rgba(232,50,138,0.06)', drawBorder: false },
                    ticks: {
                        font: { family: 'Nunito', size: 11 }, color: '#b07090',
                        callback: v => isOmset ? 'Rp ' + (v >= 1000000 ? (v/1000000).toFixed(1)+'jt' : (v/1000)+'k') : v
                    }
                }
            },
            animation: { duration: 600, easing: 'easeOutQuart' }
        }
    });
}

function switchChart(mode) {
    currentMode = mode;
    document.getElementById('btn-omset').classList.toggle('active', mode === 'omset');
    document.getElementById('btn-order').classList.toggle('active', mode === 'order');
    buildChart(mode, currentPeriod);
}

function setPeriod(n, el) {
    currentPeriod = n;
    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    buildChart(currentMode, n);
}

/* ── Donut Chart ── */
function buildDonut() {
    const ctx = document.getElementById('donutChart').getContext('2d');
    donutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Proses', 'Selesai', 'Dibatalkan'],
            datasets: [{
                data: donutData,
                backgroundColor: ['#e8328a', '#3b82f6', '#22c55e', '#94a3b8'],
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#3d1a28',
                    titleColor: '#ffadd0',
                    bodyColor: '#fff',
                    cornerRadius: 10,
                    padding: 12
                }
            },
            animation: { animateRotate: true, duration: 800 }
        }
    });
}

/* ── Counter animation ── */
function animateCounters() {
    document.querySelectorAll('.counter, .counter-idr').forEach(el => {
        const target = parseInt(el.dataset.target) || 0;
        const isIdr  = el.classList.contains('counter-idr') || el.classList.contains('counter');
        let start = 0;
        const duration = 1200;
        const step = (timestamp) => {
            if (!start) start = timestamp;
            const progress = Math.min((timestamp - start) / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(ease * target);
            el.textContent = 'Rp ' + current.toLocaleString('id-ID');
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    });
}

/* ── Intersection Observer for stagger ── */
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.style.opacity = '1'; e.target.style.transform = 'none'; } });
}, { threshold: 0.1 });
document.querySelectorAll('.fade-up').forEach(el => {
    el.style.opacity = '0'; el.style.transform = 'translateY(18px)';
    el.style.transition = 'opacity .4s ease, transform .4s ease';
    observer.observe(el);
});

/* ── Init ── */
buildChart('omset', 12);
buildDonut();
setTimeout(animateCounters, 400);

/* ── Export PDF ── */
function exportPDF() {
    const w = window.open('', '_blank');
    w.document.write(`
        <html><head><title>Laporan Keuangan - Konveksi Apps</title>
        <style>
            body{font-family:Arial,sans-serif;padding:32px;color:#3d1a28;background:#fff}
            h1{color:#e8328a;border-bottom:3px solid #e8328a;padding-bottom:12px;font-size:22px}
            .meta{color:#b07090;font-size:12px;margin-bottom:24px}
            .grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin:20px 0}
            .card{border:1.5px solid #ffd6e7;border-radius:12px;padding:16px;background:#fff0f5}
            .label{font-size:11px;color:#b07090;font-weight:600;margin-bottom:4px;text-transform:uppercase}
            .val{font-size:20px;font-weight:700;color:#e8328a}
            .val.green{color:#15803d}.val.red{color:#ef4444}
            h3{color:#7d4460;margin:24px 0 10px;font-size:16px;border-left:4px solid #e8328a;padding-left:10px}
            table{width:100%;border-collapse:collapse;font-size:13px}
            th{background:#fff0f5;padding:10px 14px;text-align:left;font-size:11px;color:#b07090;font-weight:700;text-transform:uppercase}
            td{padding:11px 14px;border-bottom:1px solid #ffd6e7}
            tr:last-child td{border-bottom:none}
            .footer{margin-top:40px;text-align:center;color:#b07090;font-size:11px;border-top:1px solid #ffd6e7;padding-top:16px}
        </style></head><body>
        <h1>📊 Laporan Keuangan — Konveksi Apps</h1>
        <div class="meta">Dicetak oleh: <?= htmlspecialchars($nama_owner) ?> &nbsp;·&nbsp; <?= date('d M Y H:i') ?></div>
        <div class="grid">
            <div class="card"><div class="label">Total Pendapatan</div><div class="val">Rp <?= number_format($omset) ?></div></div>
            <div class="card"><div class="label">Total Pengeluaran</div><div class="val">Rp <?= number_format($total_pengeluaran) ?></div></div>
            <div class="card"><div class="label">Laba Bersih</div><div class="val <?= $laba_bersih>=0?'green':'red' ?>">Rp <?= number_format(abs($laba_bersih)) ?></div></div>
            <div class="card"><div class="label">Margin</div><div class="val"><?= $margin_pct ?>%</div></div>
            <div class="card"><div class="label">Avg Order Value</div><div class="val">Rp <?= number_format($aov) ?></div></div>
            <div class="card"><div class="label">Repeat Rate</div><div class="val"><?= $repeat_rate ?>%</div></div>
        </div>
        <h3>Rincian Pengeluaran</h3>
        <table>
            <tr><th>Komponen</th><th>Jumlah</th></tr>
            <tr><td>Gaji Penjahit</td><td>Rp <?= number_format($biaya_gaji) ?></td></tr>
            <tr><td>Pembelian Bahan</td><td>Rp <?= number_format($biaya_bahan) ?></td></tr>
            <tr><td>Servis Aset</td><td>Rp <?= number_format($pengeluaran_servis) ?></td></tr>
            <tr><td>Pengeluaran Lainnya</td><td>Rp <?= number_format($biaya_lain) ?></td></tr>
        </table>
        <h3>Status Pesanan</h3>
        <table>
            <tr><th>Status</th><th>Jumlah</th></tr>
            <?php foreach ($status_data as $k=>$v): ?>
            <tr><td><?= $k ?></td><td><?= $v ?></td></tr>
            <?php endforeach; ?>
        </table>
        <div class="footer">Konveksi Apps &copy; <?= date('Y') ?> &nbsp;·&nbsp; Dicetak <?= date('d M Y H:i') ?></div>
        </body></html>
    `);
    w.document.close();
    setTimeout(() => { w.focus(); w.print(); }, 600);
}

/* ── Export Excel ── */
function exportExcel() {
    const rows = [
        ['LAPORAN KEUANGAN KONVEKSI APPS'],
        ['Dicetak oleh','<?= addslashes($nama_owner) ?>'],
        ['Tanggal','<?= date('d M Y H:i') ?>'],
        [],
        ['RINGKASAN KEUANGAN'],
        ['Total Pendapatan (Rp)','<?= $omset ?>'],
        ['Total Pengeluaran (Rp)','<?= $total_pengeluaran ?>'],
        ['Laba Bersih (Rp)','<?= $laba_bersih ?>'],
        ['Margin (%)','<?= $margin_pct ?>'],
        ['Rata-rata Nilai Order','<?= $aov ?>'],
        ['Repeat Customer Rate (%)','<?= $repeat_rate ?>'],
        [],
        ['RINCIAN PENGELUARAN'],
        ['Gaji Penjahit','<?= $biaya_gaji ?>'],
        ['Pembelian Bahan','<?= $biaya_bahan ?>'],
        ['Servis Aset','<?= $pengeluaran_servis ?>'],
        ['Pengeluaran Lainnya','<?= $biaya_lain ?>'],
        [],
        ['STATUS PESANAN'],
        <?php foreach ($status_data as $k=>$v): ?>
        ['<?= $k ?>','<?= $v ?>'],
        <?php endforeach; ?>
        [],
        ['GRAFIK PENJUALAN 12 BULAN'],
        ['Bulan','Pendapatan (Rp)','Jumlah Pesanan'],
        ...allLabels.map((l,i) => [l, allOmset[i], allOrder[i]])
    ];
    const csv = rows.map(r => r.join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], {type:'text/csv;charset=utf-8'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'laporan_konveksi_<?= date('Y-m-d') ?>.csv';
    a.click();
}
</script>
</body>
</html>