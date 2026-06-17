<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

/*
  ── Helper query functions ──────────────────────────────────────────────
  PHP 8.1+ membuat mysqli melempar mysqli_sql_exception kalau query SQL
  error (mode default: MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT).
  Kalau exception ini tidak ditangkap, SELURUH SCRIPT BERHENTI di titik
  itu juga -> sisa HTML di bawahnya (termasuk <script> yang isinya
  switchTab()) tidak pernah terkirim ke browser.

  Akibatnya: tab "Pesanan" tetap kelihatan (karena sudah class="active"
  bawaan di HTML), tapi tab lain tidak bisa diklik karena fungsi
  switchTab() tidak pernah ter-load -> error di console:
  "Uncaught ReferenceError: switchTab is not defined".

  Fungsi-fungsi di bawah ini "membungkus" setiap query: kalau error,
  errornya dicatat ke $db_errors (akan ditampilkan di halaman untuk
  debugging) tapi halaman tetap lanjut dirender sampai selesai.
*/
$db_errors = [];

function db_fetch_one($koneksi, $sql, $col = 't', $default = 0) {
    global $db_errors;
    try {
        $result = mysqli_query($koneksi, $sql);
        if ($result === false) {
            $db_errors[] = mysqli_error($koneksi) . " | SQL: " . $sql;
            return $default;
        }
        $row = mysqli_fetch_assoc($result);
        return $row[$col] ?? $default;
    } catch (mysqli_sql_exception $e) {
        $db_errors[] = $e->getMessage() . " | SQL: " . $sql;
        return $default;
    }
}

function db_fetch_all($koneksi, $sql) {
    global $db_errors;
    try {
        $result = mysqli_query($koneksi, $sql);
        if ($result === false) {
            $db_errors[] = mysqli_error($koneksi) . " | SQL: " . $sql;
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    } catch (mysqli_sql_exception $e) {
        $db_errors[] = $e->getMessage() . " | SQL: " . $sql;
        return [];
    }
}

function db_count($koneksi, $sql) {
    global $db_errors;
    try {
        $result = mysqli_query($koneksi, $sql);
        if ($result === false) {
            $db_errors[] = mysqli_error($koneksi) . " | SQL: " . $sql;
            return 0;
        }
        return mysqli_num_rows($result);
    } catch (mysqli_sql_exception $e) {
        $db_errors[] = $e->getMessage() . " | SQL: " . $sql;
        return 0;
    }
}

// Sidebar helpers
$nama_owner = $_SESSION['user'];
$inisial     = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts   = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}
$notif_bayar = db_count($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'");
$notif_chat  = db_fetch_one($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'");
$aset_rusak  = 0;
$stok_kritis = db_count($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25");
$total_notif = $notif_bayar + $notif_chat + $stok_kritis + $aset_rusak;

// ══ KEUANGAN ══
$omset             = db_fetch_one($koneksi, "SELECT SUM(TOTAL_HARGA) as t FROM pesanan WHERE STATUS='Selesai'");
$biaya_gaji = db_fetch_one($koneksi, "SELECT SUM(TOTAL_UPAH) as t FROM penggajian");
$biaya_bahan       = db_fetch_one($koneksi, "SELECT SUM(TOTAL_BIAYA) as t FROM pembelian_bahan");
$biaya_lain        = db_fetch_one($koneksi, "SELECT SUM(JUMLAH_PENGELUARAN) as t FROM pengeluaran");
$biaya_servis = db_fetch_one($koneksi, 
    "SELECT COALESCE(SUM(JUMLAH_PENGELUARAN),0) as t FROM pengeluaran WHERE JENIS_PENGELUARAN='Perawatan'");
$hutang = db_fetch_one($koneksi, "SELECT SUM(TOTAL_BIAYA) as t FROM pembelian_bahan WHERE STATUS_BAYAR='Belum Dibayar'");
$total_pengeluaran = $biaya_gaji + $biaya_bahan + $biaya_lain + $biaya_servis;
$laba_bersih       = $omset - $total_pengeluaran;
$margin_pct        = $omset > 0 ? round(($laba_bersih / $omset) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Keuangan 📊 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --p50:#fff0f5;--p100:#ffd6e7;--p200:#ffadd0;--p300:#ff80b8;
    --p400:#f950a0;--p500:#e8328a;--p600:#cc1a73;--p700:#a8105d;
    --v100:#f3e8ff;--v300:#d8b4fe;--v500:#a855f7;
    --g100:#dcfce7;--g500:#22c55e;--g700:#15803d;
    --a100:#fef9c3;--a500:#eab308;--a700:#854d0e;
    --b100:#dbeafe;--b500:#3b82f6;--b700:#1d4ed8;
    --r100:#fee2e2;--r500:#ef4444;--r700:#991b1b;
    --o100:#ffedd5;--o500:#f97316;--o700:#9a3412;
    --white:#ffffff;--bg:#fff5f9;
    --text:#3d1a28;--text2:#7d4460;--text3:#b07090;
    --border:rgba(232,50,138,0.13);--border2:rgba(232,50,138,0.24);
    --sidebar-w:256px;--topbar-h:64px;
    --r-sm:10px;--r-md:16px;--r-lg:22px;--r-xl:28px;
    --ease:0.2s cubic-bezier(0.34,1.56,0.64,1);--ease-plain:0.17s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;font-size:14.5px;line-height:1.6;-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(232,50,138,0.055) 1.5px,transparent 1.5px);background-size:28px 28px;pointer-events:none;z-index:0}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--p50)}::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px}

/* SIDEBAR */
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
.sb-owner{margin:12px 12px 6px;padding:12px 14px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:var(--r-lg);display:flex;align-items:center;gap:10px;flex-shrink:0}
.owner-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;position:relative;box-shadow:0 3px 10px rgba(232,50,138,0.35)}
.owner-av::after{content:'';position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:var(--g500);border:2px solid var(--white)}
.owner-name{font-size:13.5px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.owner-role{font-size:11px;color:var(--p500);font-weight:600}
.sb-nav{flex:1;overflow-y:auto;padding:6px 10px 10px}
.nav-group-label{font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--text3);padding:14px 10px 4px;display:flex;align-items:center;gap:6px}
.nav-group-label::after{content:'✦';font-size:7px;color:var(--p300)}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:var(--r-sm);text-decoration:none;color:var(--text2);font-size:14px;font-weight:600;transition:background var(--ease-plain),color var(--ease-plain),transform var(--ease-plain);margin-bottom:2px;position:relative;white-space:nowrap}
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
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(0.85);opacity:0.6}}
.pulse{animation:pulse 1.8s ease-in-out infinite}

/* TOPBAR */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(12px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px}
.topbar::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;animation:shimmer 3s linear infinite}
.tb-greeting{flex:1}
.tb-hello{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text)}
.tb-sub{font-size:12px;color:var(--text3);font-weight:500;margin-top:1px}
.tb-nav{display:flex;align-items:center;gap:2px}
.tb-nav-item{display:flex;align-items:center;gap:5px;padding:7px 13px;border-radius:99px;font-size:13px;font-weight:600;color:var(--text2);text-decoration:none;transition:all var(--ease-plain);white-space:nowrap;border:1.5px solid transparent}
.tb-nav-item i{font-size:14px}
.tb-nav-item:hover{background:var(--p50);color:var(--p500)}
.tb-nav-item.active{background:var(--p50);color:var(--p500);border-color:var(--border2)}
.tb-divider{width:1px;height:24px;background:var(--border2);margin:0 4px}
.tb-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.icon-btn{width:36px;height:36px;border-radius:10px;background:var(--p50);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;color:var(--p500);font-size:16px;transition:all var(--ease);position:relative}
.icon-btn:hover{background:var(--p100);transform:scale(1.08)}
.icon-btn .dot{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--r500);border:2px solid var(--white);animation:blink 1.6s ease-in-out infinite}
.date-pill{display:flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:600;color:var(--text2)}
.date-pill i{color:var(--p500)}

/* MAIN */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1}
.content{padding:28px 28px 70px;max-width:1360px}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.anim{animation:fadeUp 0.35s ease both}

/* Page header */
.page-header{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);padding:22px 28px;display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;position:relative;overflow:hidden}
.page-header::before{content:'';position:absolute;right:-30px;top:-30px;width:160px;height:160px;border-radius:50%;background:linear-gradient(135deg,var(--p50),var(--v100));opacity:0.7}
.ph-left{display:flex;align-items:center;gap:14px;position:relative;z-index:1}
.ph-icon{width:50px;height:50px;border-radius:15px;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;box-shadow:0 6px 20px rgba(232,50,138,0.4)}
.ph-title{font-family:'Quicksand',sans-serif;font-size:21px;font-weight:700;color:var(--text)}
.ph-sub{font-size:13px;color:var(--text3);font-weight:500;margin-top:2px}
.ph-right{position:relative;z-index:1;display:flex;align-items:center;gap:8px}
.print-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:99px;font-size:13px;font-weight:700;background:var(--p50);color:var(--p600);border:1.5px solid var(--border2);cursor:pointer;transition:all var(--ease-plain);text-decoration:none}
.print-btn:hover{background:var(--p500);color:#fff;border-color:var(--p500)}

/* Section header */
.sec-hd{display:flex;align-items:center;justify-content:space-between;margin:28px 0 14px}
.sec-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.sec-dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:inline-block;box-shadow:0 0 0 3px rgba(232,50,138,0.15);flex-shrink:0}

/* Finance cards */
.fin-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:14px}
.fin-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:20px 22px;position:relative;overflow:hidden;transition:transform var(--ease),box-shadow var(--ease)}
.fin-card:hover{transform:translateY(-4px);box-shadow:0 14px 36px rgba(232,50,138,0.13)}
.fin-stripe{position:absolute;top:0;left:0;right:0;height:4px;border-radius:var(--r-lg) var(--r-lg) 0 0}
.fin-blob{position:absolute;right:-16px;bottom:-16px;width:72px;height:72px;border-radius:50%;opacity:0.07}
.fc-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px}
.fc-label{font-size:12px;font-weight:600;color:var(--text2);margin-bottom:4px}
.fc-val{font-family:'Quicksand',sans-serif;font-size:19px;font-weight:700;line-height:1.1;margin-bottom:3px}
.fc-note{font-size:11px;color:var(--text3);font-weight:500}
.fv-pink   .fin-stripe{background:linear-gradient(90deg,var(--p500),var(--p300))} .fv-pink   .fin-blob{background:var(--p500)} .fv-pink   .fc-icon{background:var(--p50);color:var(--p500)} .fv-pink   .fc-val{color:var(--p600)}
.fv-purple .fin-stripe{background:linear-gradient(90deg,var(--v500),var(--v300))} .fv-purple .fin-blob{background:var(--v500)} .fv-purple .fc-icon{background:var(--v100);color:var(--v500)} .fv-purple .fc-val{color:var(--v500)}
.fv-amber  .fin-stripe{background:linear-gradient(90deg,var(--a500),#fcd34d)}    .fv-amber  .fin-blob{background:var(--a500)} .fv-amber  .fc-icon{background:var(--a100);color:var(--a500)} .fv-amber  .fc-val{color:var(--a700)}
.fv-green  .fin-stripe{background:linear-gradient(90deg,var(--g500),#86efac)}    .fv-green  .fin-blob{background:var(--g500)} .fv-green  .fc-icon{background:var(--g100);color:var(--g500)} .fv-green  .fc-val{color:var(--g700)}
.fv-red    .fin-stripe{background:linear-gradient(90deg,var(--r500),#fca5a5)}    .fv-red    .fin-blob{background:var(--r500)} .fv-red    .fc-icon{background:var(--r100);color:var(--r500)} .fv-red    .fc-val{color:var(--r700)}

/* Laba banner */
.laba-banner{border-radius:var(--r-xl);padding:24px 30px;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden;margin-bottom:14px}
.laba-banner.profit{background:linear-gradient(135deg,var(--g700) 0%,#16a34a 50%,#4ade80 100%)}
.laba-banner.loss{background:linear-gradient(135deg,#991b1b 0%,var(--r500) 60%,#fca5a5 100%)}
.laba-banner::before{content:'';position:absolute;right:-30px;top:-30px;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,0.08)}
.laba-banner::after{content:'';position:absolute;right:50px;bottom:-55px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,0.05)}
.laba-eyebrow{font-size:12px;font-weight:700;color:rgba(255,255,255,0.65);margin-bottom:6px}
.laba-val{font-family:'Quicksand',sans-serif;font-size:32px;font-weight:700;color:#fff;line-height:1}
.laba-note{font-size:11.5px;color:rgba(255,255,255,0.5);margin-top:5px;font-weight:500}
.laba-big-icon{font-size:56px;color:rgba(255,255,255,0.13);position:relative;z-index:1}

/* Breakdown */
.breakdown-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:28px}
.bd-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-md);padding:14px 16px;display:flex;align-items:center;gap:12px;transition:border-color var(--ease-plain),transform var(--ease)}
.bd-card:hover{border-color:var(--border2);transform:translateY(-2px)}
.bd-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.bd-lbl{font-size:11px;font-weight:600;color:var(--text3);margin-bottom:2px}
.bd-val{font-size:13.5px;font-weight:700;color:var(--text)}

/* Table card */
.tbl-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;margin-bottom:20px}
.tbl-hd{padding:16px 24px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--p50),var(--white))}
.tbl-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.tbl-title i{color:var(--p500)}
.tbl-total{display:inline-flex;align-items:center;gap:6px;background:var(--r100);color:var(--r700);border-radius:99px;padding:5px 14px;font-size:12.5px;font-weight:700}
.tbl-total.green{background:var(--g100);color:var(--g700)}
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{padding:11px 20px;font-size:11.5px;font-weight:700;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border);white-space:nowrap}
.data-table tbody td{padding:13px 20px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:14px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr{transition:background var(--ease-plain)}
.data-table tbody tr:hover td{background:var(--p50)}

.id-tag{display:inline-flex;align-items:center;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:7px;padding:3px 9px;font-size:12px;font-weight:700}
.id-tag.green{background:var(--g100);color:var(--g700);border-color:rgba(34,197,94,0.25)}
.id-tag.amber{background:var(--a100);color:var(--a700);border-color:rgba(234,179,8,0.25)}
.id-tag.blue{background:var(--b100);color:var(--b700);border-color:rgba(59,130,246,0.25)}
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 11px;border-radius:99px;font-size:12px;font-weight:700}
.badge i{font-size:10px}
.badge-g{background:var(--g100);color:var(--g700)}
.badge-b{background:var(--b100);color:var(--b700)}
.badge-y{background:var(--a100);color:var(--a700)}
.badge-r{background:var(--r100);color:var(--r700)}
.badge-dark{background:#1f2937;color:#f9fafb}

/* Kondisi aset */
.kond-baik{background:var(--g100);color:var(--g700);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.kond-service{background:var(--a100);color:var(--a700);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.kond-perlu{background:var(--o100);color:var(--o700);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.kond-rusak{background:var(--r100);color:var(--r700);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px}

/* Tab nav */
.tab-nav{display:flex;gap:6px;background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:6px;margin-bottom:20px;flex-wrap:wrap}
.tab-btn{padding:8px 18px;border-radius:var(--r-md);font-size:13px;font-weight:700;color:var(--text2);background:none;border:none;cursor:pointer;transition:all var(--ease-plain);display:flex;align-items:center;gap:6px;white-space:nowrap}
.tab-btn:hover{background:var(--p50);color:var(--p500)}
.tab-btn.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;box-shadow:0 4px 12px rgba(232,50,138,0.3)}
.tab-pane{display:none}.tab-pane.active{display:block}

.empty-cell{padding:40px 24px;text-align:center;color:var(--text3);font-size:13px}
.empty-cell i{font-size:32px;display:block;margin-bottom:8px;color:var(--p200)}

.bukti-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:99px;font-size:12px;font-weight:700;background:var(--b100);color:var(--b700);border:1px solid rgba(59,130,246,0.2);text-decoration:none;transition:all var(--ease-plain)}
.bukti-btn:hover{background:var(--b500);color:#fff}

/* Debug panel (query error) */
.debug-card{background:var(--white);border:1.5px solid var(--r500);border-radius:var(--r-xl);overflow:hidden;margin-bottom:20px}
.debug-hd{padding:14px 24px;background:var(--r100);display:flex;align-items:center;gap:8px;font-family:'Quicksand',sans-serif;font-size:14px;font-weight:700;color:var(--r700)}
.debug-body{padding:14px 24px;font-size:12px;color:var(--r700);font-family:monospace;word-break:break-all}
.debug-item{padding:8px 0;border-bottom:1px dashed var(--r100)}
.debug-item:last-child{border-bottom:none}

@media(max-width:1280px){.fin-grid,.breakdown-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.sidebar{transform:translateX(-100%)}.topbar{left:0}.main{margin-left:0}.fin-grid{grid-template-columns:repeat(2,1fr)}.breakdown-grid{grid-template-columns:1fr 1fr}.laba-banner{flex-direction:column;gap:14px}}
@media print{.sidebar,.topbar,.tab-nav,.print-btn,.debug-card{display:none!important}.main{margin-left:0!important;padding-top:0!important}.content{padding:16px!important}}
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
        <a class="nav-item" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="nav-item" href="konfirmasi_pembayaran.php">
            <i class="bi bi-credit-card-2-front"></i> Konfirmasi Bayar
            <?php if ($notif_bayar > 0): ?><span class="nav-pill pill-pink pulse"><?= $notif_bayar ?></span><?php endif; ?>
        </a>
        <a class="nav-item" href="chat.php">
            <i class="bi bi-chat-dots-fill"></i> Inbox Chat
            <?php if ($notif_chat > 0): ?><span class="nav-pill pill-red pulse"><?= $notif_chat ?></span><?php endif; ?>
        </a>
        <div class="nav-group-label">Laporan</div>
        <a class="nav-item active" href="laporan.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Keuangan</a>
    </nav>
    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Laporan Keuangan 📊</div>
        <div class="tb-sub">Rekap lengkap pendapatan, pengeluaran, dan aset bisnis</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi</a>
        <a class="tb-nav-item active" href="laporan.php"><i class="bi bi-bar-chart-line"></i> Laporan</a>
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
<div class="content anim">

    <!-- Page header -->
    <div class="page-header">
        <div class="ph-left">
            <div class="ph-icon"><i class="bi bi-file-earmark-bar-graph"></i></div>
            <div>
                <div class="ph-title">Laporan Keuangan</div>
                <div class="ph-sub">Periode: semua transaksi hingga <?= date('d M Y') ?></div>
            </div>
        </div>
        <div class="ph-right">
            <a href="#" class="print-btn" onclick="window.print()">
                <i class="bi bi-printer-fill"></i> Cetak
            </a>
        </div>
    </div>

    <?php if (!empty($db_errors)): ?>
    <!-- ── DEBUG: Query Error ── -->
    <div class="debug-card">
        <div class="debug-hd"><i class="bi bi-exclamation-triangle-fill"></i> Query Error Terdeteksi (<?= count($db_errors) ?>) — perbaiki query/struktur tabel di bawah ini</div>
        <div class="debug-body">
            <?php foreach ($db_errors as $err): ?>
                <div class="debug-item"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── RINGKASAN KEUANGAN ── -->
    <div class="sec-hd" style="margin-top:4px">
        <div class="sec-title"><span class="sec-dot"></span> Ringkasan Keuangan 💰</div>
    </div>

    <div class="fin-grid">
        <div class="fin-card fv-pink">
            <div class="fin-stripe"></div>
            <div class="fc-icon"><i class="bi bi-arrow-up-circle-fill"></i></div>
            <div class="fc-label">Total Pendapatan</div>
            <div class="fc-val">Rp <?= number_format($omset) ?></div>
            <div class="fc-note">Dari pesanan selesai</div>
            <div class="fin-blob" style="background:var(--p500)"></div>
        </div>
        <div class="fin-card fv-purple">
            <div class="fin-stripe"></div>
            <div class="fc-icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
            <div class="fc-label">Total Pengeluaran</div>
            <div class="fc-val">Rp <?= number_format($total_pengeluaran) ?></div>
            <div class="fc-note">Gaji + Bahan + Servis + Lainnya</div>
            <div class="fin-blob" style="background:var(--v500)"></div>
        </div>
        <div class="fin-card fv-amber">
            <div class="fin-stripe"></div>
            <div class="fc-icon"><i class="bi bi-clock-history"></i></div>
            <div class="fc-label">Hutang Supplier</div>
            <div class="fc-val">Rp <?= number_format($hutang) ?></div>
            <div class="fc-note">Pembelian belum dibayar</div>
            <div class="fin-blob" style="background:var(--a500)"></div>
        </div>
        <div class="fin-card fv-green">
            <div class="fin-stripe"></div>
            <div class="fc-icon"><i class="bi bi-percent"></i></div>
            <div class="fc-label">Margin Keuntungan</div>
            <div class="fc-val"><?= $margin_pct ?>%</div>
            <div class="fc-note">Laba bersih / pendapatan</div>
            <div class="fin-blob" style="background:var(--g500)"></div>
        </div>
    </div>

    <!-- Laba banner -->
    <div class="laba-banner <?= $laba_bersih >= 0 ? 'profit' : 'loss' ?>">
        <div>
            <div class="laba-eyebrow"><?= $laba_bersih >= 0 ? '📈 Laba Bersih — Mantap!' : '📉 Rugi Bersih' ?></div>
            <div class="laba-val">Rp <?= number_format(abs($laba_bersih)) ?></div>
            <div class="laba-note">Pendapatan − Total Pengeluaran</div>
        </div>
        <i class="bi <?= $laba_bersih >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow' ?> laba-big-icon"></i>
    </div>

    <!-- Breakdown pengeluaran -->
    <div class="breakdown-grid">
        <div class="bd-card"><div class="bd-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-scissors"></i></div><div><div class="bd-lbl">Gaji Penjahit</div><div class="bd-val">Rp <?= number_format($biaya_gaji) ?></div></div></div>
        <div class="bd-card"><div class="bd-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-basket"></i></div><div><div class="bd-lbl">Pembelian Bahan</div><div class="bd-val">Rp <?= number_format($biaya_bahan) ?></div></div></div>
        <div class="bd-card"><div class="bd-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-tools"></i></div><div><div class="bd-lbl">Servis Aset</div><div class="bd-val">Rp <?= number_format($biaya_servis) ?></div></div></div>
        <div class="bd-card"><div class="bd-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-three-dots"></i></div><div><div class="bd-lbl">Pengeluaran Lainnya</div><div class="bd-val">Rp <?= number_format($biaya_lain) ?></div></div></div>
    </div>

    <!-- ── TAB TABEL ── -->
    <div class="sec-hd">
        <div class="sec-title"><span class="sec-dot"></span> Detail Transaksi 📋</div>
    </div>

    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('pesanan',this)"><i class="bi bi-bag-heart-fill"></i> Pesanan</button>
        <button class="tab-btn" onclick="switchTab('pembelian',this)"><i class="bi bi-basket-fill"></i> Pembelian Bahan</button>
        <button class="tab-btn" onclick="switchTab('penggajian',this)"><i class="bi bi-cash-stack"></i> Penggajian</button>
        <button class="tab-btn" onclick="switchTab('aset',this)"><i class="bi bi-building-gear"></i> Aset</button>
        <button class="tab-btn" onclick="switchTab('servis',this)"><i class="bi bi-tools"></i> Servis Aset</button>
    </div>

    <!-- TAB: Pesanan -->
    <div class="tab-pane active" id="tab-pesanan">
        <div class="tbl-card">
            <div class="tbl-hd">
                <div class="tbl-title"><i class="bi bi-bag-heart-fill"></i> Riwayat Transaksi Pesanan</div>
                <span class="tbl-total green"><i class="bi bi-arrow-up-circle-fill"></i> Rp <?= number_format($omset) ?></span>
            </div>
            <table class="data-table">
                <thead><tr><th>ID Pesanan</th><th>Tanggal</th><th>Pelanggan</th><th>Total Harga</th><th>Status</th></tr></thead>
                <tbody>
                <?php
                $rows_pesanan = db_fetch_all($koneksi, "SELECT p.*, pl.NAMA_PELANGGAN FROM pesanan p LEFT JOIN pelanggan pl ON p.ID_PELANGGAN=pl.ID_PELANGGAN ORDER BY WAKTU_PESAN DESC");
                if (empty($rows_pesanan)): ?>
                    <tr><td colspan="5"><div class="empty-cell"><i class="bi bi-bag-x"></i>Belum ada data pesanan</div></td></tr>
                <?php else: foreach ($rows_pesanan as $row):
                    $st=$row['STATUS']??'Pending';
                    $sc=($st=='Selesai')?'badge-g':(($st=='Proses')?'badge-b':'badge-y');
                    $si=($st=='Selesai')?'check-circle-fill':(($st=='Proses')?'arrow-repeat':'clock');
                ?>
                <tr>
                    <td><span class="id-tag"><?= htmlspecialchars($row['ID_PESANAN']) ?></span></td>
                    <td style="color:var(--text2)"><?= date('d/m/Y', strtotime($row['WAKTU_PESAN'])) ?></td>
                    <td style="font-weight:700"><?= htmlspecialchars($row['NAMA_PELANGGAN'] ?? $row['ID_PELANGGAN']) ?></td>
                    <td style="font-weight:700;color:var(--g700)">Rp <?= number_format($row['TOTAL_HARGA']) ?></td>
                    <td><span class="badge <?= $sc ?>"><i class="bi bi-<?= $si ?>"></i> <?= $st ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Pembelian Bahan -->
    <div class="tab-pane" id="tab-pembelian">
        <div class="tbl-card">
            <div class="tbl-hd">
                <div class="tbl-title"><i class="bi bi-basket-fill"></i> Riwayat Pembelian Bahan Baku</div>
                <span class="tbl-total"><i class="bi bi-arrow-down-circle-fill"></i> Rp <?= number_format($biaya_bahan) ?> lunas</span>
            </div>
            <table class="data-table">
                <thead><tr><th>ID Pembelian</th><th>Tanggal</th><th>Supplier</th><th>Detail Bahan</th><th>Total Biaya</th><th>Status</th></tr></thead>
                <tbody>
                <?php
                $rows_pembelian = db_fetch_all($koneksi,
                    "SELECT pb.*, s.NAMA_SUPPLIER
                    FROM pembelian_bahan pb
                    LEFT JOIN supplier s ON pb.ID_SUPPLIER = s.ID_SUPPLIER
                    ORDER BY pb.TANGGAL_BELI DESC");
                if (empty($rows_pembelian)): ?>
                    <tr><td colspan="6"><div class="empty-cell"><i class="bi bi-basket2"></i>Belum ada data pembelian</div></td></tr>
                <?php else: foreach ($rows_pembelian as $pb):
                    $sp=$pb['STATUS_BAYAR']??'Belum Dibayar';
                    $lunas=($sp=='Sudah Dibayar');
                ?>
                <tr>
                    <td><span class="id-tag green"><?= htmlspecialchars($pb['ID_PEMBELIAN']) ?></span></td>
                    <td style="color:var(--text2)"><?= date('d/m/Y', strtotime($pb['TANGGAL_BELI'])) ?></td>
                    <td style="font-weight:700"><?= htmlspecialchars($pb['NAMA_SUPPLIER'] ?? $pb['ID_SUPPLIER']) ?></td>
                    <td style="color:var(--text3);font-size:13px"><?= htmlspecialchars($pb['NAMA_BAHAN'] ?? '—') ?></td>                    
                    <td style="font-weight:700;color:var(--r700)">Rp <?= number_format($pb['TOTAL_BIAYA']) ?></td>
                    <td>
                        <span class="badge <?= $lunas ? 'badge-g' : 'badge-y' ?>">
                            <i class="bi bi-<?= $lunas ? 'check-circle-fill' : 'clock' ?>"></i>
                            <?= $lunas ? 'Lunas ✓' : 'Belum Lunas' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Penggajian -->
    <div class="tab-pane" id="tab-penggajian">
        <div class="tbl-card">
            <div class="tbl-hd">
                <div class="tbl-title"><i class="bi bi-cash-stack"></i> Status Pembayaran Upah Penjahit</div>
                <span class="tbl-total"><i class="bi bi-scissors"></i> Rp <?= number_format($biaya_gaji) ?> terbayar</span>
            </div>
            <table class="data-table">
                <thead><tr><th>ID Gaji</th><th>Nama Penjahit</th><th>Total Upah</th><th>Status</th><th>Bukti</th></tr></thead>
                <tbody>
                <?php
                $rows_gaji = db_fetch_all($koneksi,
                    "SELECT g.*, p.NAMA_PENJAHIT FROM penggajian g
                    LEFT JOIN penjahit p ON g.ID_PENJAHIT = p.ID_PENJAHIT
                    ORDER BY g.ID_GAJI DESC");
                if (empty($rows_gaji)): ?>
                    <tr><td colspan="5"><div class="empty-cell"><i class="bi bi-cash-coin"></i>Belum ada data penggajian</div></td></tr>
                <?php else: foreach ($rows_gaji as $g):
                    $sg=$g['STATUS_GAJI']??'Belum Dibayar';
                    $lunas=($sg=='Sudah Dibayar');
                ?>
                <tr>
                    <td><span class="id-tag amber"><?= htmlspecialchars($g['ID_GAJI']) ?></span></td>
                    <td style="font-weight:700"><?= htmlspecialchars($g['NAMA_PENJAHIT']) ?></td>
                    <td style="font-weight:700;color:var(--p600)">Rp <?= number_format($g['TOTAL_UPAH']) ?></td>
                    <td>
                        <span class="badge <?= $lunas ? 'badge-g' : 'badge-r' ?>">
                            <i class="bi bi-<?= $lunas ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                            <?= $lunas ? 'Sudah Dibayar' : 'Belum Dibayar' ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($g['BUKTI_BAYAR'])): ?>
                        <a href="../assets/bukti_gaji/<?= htmlspecialchars($g['BUKTI_BAYAR']) ?>" target="_blank" class="bukti-btn">
                            <i class="bi bi-image"></i> Lihat Bukti
                        </a>
                        <?php else: ?>
                        <span style="color:var(--text3);font-size:13px">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Aset -->
    <div class="tab-pane" id="tab-aset">
        <div class="tbl-card">
            <div class="tbl-hd">
                <div class="tbl-title"><i class="bi bi-building-gear"></i> Rekap Kondisi Aset</div>
                <a href="kelola_aset.php" style="display:inline-flex;align-items:center;gap:5px;font-size:12.5px;font-weight:700;color:var(--p500);text-decoration:none;padding:5px 14px;border:1.5px solid var(--border2);border-radius:99px;background:var(--p50)">Kelola Aset <i class="bi bi-arrow-right"></i></a>
            </div>
            <table class="data-table">
                <thead><tr><th>ID Aset</th><th>Nama Aset</th><th>Jenis</th><th>Nilai Aset</th><th>Kondisi</th></tr></thead>
                <tbody>
                <?php
                $rows_aset = db_fetch_all($koneksi, "SELECT * FROM aset ORDER BY ID_ASET ASC");
                if (empty($rows_aset)): ?>
                    <tr><td colspan="5"><div class="empty-cell"><i class="bi bi-building"></i>Belum ada data aset</div></td></tr>
                <?php else: foreach ($rows_aset as $a):
                    $k = $a['KONDISI_ASET'] ?? 'Baik'; // Menyesuaikan dengan kolom kondisi aset kamu
                    $kc = match($k){'Perlu Service'=>'kond-service','Perlu Perbaikan'=>'kond-perlu','Rusak'=>'kond-rusak',default=>'kond-baik'};
                    $ki = match($k){'Perlu Service'=>'wrench','Perlu Perbaikan'=>'exclamation-triangle-fill','Rusak'=>'x-circle-fill',default=>'check-circle-fill'};
                ?>
                <tr>
                    <td><span class="id-tag blue"><?= htmlspecialchars($a['ID_ASET']) ?></span></td>
                    <td style="font-weight:700"><?= htmlspecialchars($a['NAMA_ASET']) ?></td>
                    <td><span class="badge badge-b"><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($a['JENIS_ASET']) ?></span></td>
                    <td style="font-weight:700;color:var(--text)">Rp <?= number_format($a['NILAI_ASET']) ?></td>
                    <td><span class="<?= $kc ?>"><i class="bi bi-<?= $ki ?>"></i> <?= htmlspecialchars($k) ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Servis -->
    <div class="tab-pane" id="tab-servis">
        <div class="tbl-card">
            <div class="tbl-hd">
                <div class="tbl-title"><i class="bi bi-tools"></i> Riwayat Servis &amp; Perbaikan Aset</div>
                <span class="tbl-total"><i class="bi bi-arrow-down-circle-fill"></i> Rp <?= number_format($biaya_servis) ?></span>
            </div>
            <table class="data-table">
                <thead><tr><th>Tanggal</th><th>Nama Aset</th><th>Jenis</th><th>Keterangan</th><th>Biaya</th><th>Kondisi Setelah</th></tr></thead>
                <tbody>
                <?php
                $rows_servis = db_fetch_all($koneksi,
                    "SELECT s.*, a.NAMA_ASET, a.JENIS_ASET FROM servis s
                    JOIN aset a ON s.ID_ASET=a.ID_ASET ORDER BY s.TANGGAL_SERVIS DESC");
                if (empty($rows_servis)): ?>
                    <tr><td colspan="6"><div class="empty-cell"><i class="bi bi-wrench"></i>Belum ada riwayat servis</div></td></tr>
                <?php else: foreach ($rows_servis as $s):
                    $ks = $s['KONDISI_SETELAH'] ?? 'Baik';
                    $kc2 = match($ks){'Perlu Service'=>'kond-service','Perlu Perbaikan'=>'kond-perlu','Rusak'=>'kond-rusak',default=>'kond-baik'};
                    $ki2 = match($ks){'Perlu Service'=>'wrench','Perlu Perbaikan'=>'exclamation-triangle-fill','Rusak'=>'x-circle-fill',default=>'check-circle-fill'};
                ?>
                <tr>
                    <td style="color:var(--text2)"><?= date('d/m/Y', strtotime($s['TANGGAL_SERVIS'])) ?></td>
                    <td style="font-weight:700"><?= htmlspecialchars($s['NAMA_ASET']) ?></td>
                    <td><span class="badge badge-b" style="font-size:11px"><?= htmlspecialchars($s['JENIS_ASET']) ?></span></td>
                    <td style="color:var(--text2);font-size:13px"><?= htmlspecialchars($s['KETERANGAN']) ?></td>
                    <td style="font-weight:700;color:var(--r700)">Rp <?= number_format($s['BIAYA_SERVIS']) ?></td>
                    <td><span class="<?= $kc2 ?>"><i class="bi bi-<?= $ki2 ?>"></i> <?= htmlspecialchars($ks) ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>