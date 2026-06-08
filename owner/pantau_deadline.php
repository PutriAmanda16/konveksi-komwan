<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$nama_owner = $_SESSION['user'];
$inisial = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$notif_chat  = 0;
$aset_rusak  = 0;
$today       = date('Y-m-d');

// Filter dari query string
$filter_status = $_GET['status'] ?? 'semua';
$filter_penjahit = $_GET['penjahit'] ?? '';

// Query utama: semua produksi yang masih aktif (belum selesai) atau baru selesai
$where_clauses = ["pr.STATUS_PRODUKSI != 'Dibatalkan'"];
if ($filter_status === 'aman')      $where_clauses[] = "DATEDIFF(pr.DEADLINE, '$today') >= 3 AND pr.STATUS_PRODUKSI != 'Selesai'";
if ($filter_status === 'mepet')     $where_clauses[] = "DATEDIFF(pr.DEADLINE, '$today') BETWEEN 0 AND 2 AND pr.STATUS_PRODUKSI != 'Selesai'";
if ($filter_status === 'melewati')  $where_clauses[] = "pr.DEADLINE < '$today' AND pr.STATUS_PRODUKSI != 'Selesai'";
if ($filter_status === 'selesai')   $where_clauses[] = "pr.STATUS_PRODUKSI = 'Selesai'";
if ($filter_penjahit !== '')        $where_clauses[] = "pr.ID_PENJAHIT = '" . mysqli_real_escape_string($koneksi, $filter_penjahit) . "'";

$where_sql = implode(' AND ', $where_clauses);

$query = mysqli_query($koneksi, "
    SELECT
        pr.*,
        pj.NAMA_PENJAHIT,
        pj.KEAHLIAN,
        pj.UPAH_PER_UNIT,
        pd.NAMA_PRODUK,
        DATEDIFF(pr.DEADLINE, '$today') AS sisa_hari,
        DATEDIFF(pr.DEADLINE, pr.TANGGAL_MULAI) AS durasi_total,
        CASE
            WHEN pr.STATUS_PRODUKSI = 'Selesai' AND pr.TANGGAL_SELESAI IS NOT NULL
                THEN DATEDIFF(pr.DEADLINE, pr.TANGGAL_SELESAI)
            ELSE NULL
        END AS selisih_selesai
    FROM produksi pr
    LEFT JOIN penjahit pj ON pr.ID_PENJAHIT = pj.ID_PENJAHIT
    LEFT JOIN produk pd ON pr.ID_PRODUK = pd.ID_PRODUK
    WHERE $where_sql
    ORDER BY
        CASE
            WHEN pr.STATUS_PRODUKSI != 'Selesai' AND pr.DEADLINE < '$today' THEN 1
            WHEN pr.STATUS_PRODUKSI != 'Selesai' AND DATEDIFF(pr.DEADLINE, '$today') BETWEEN 0 AND 2 THEN 2
            WHEN pr.STATUS_PRODUKSI != 'Selesai' AND DATEDIFF(pr.DEADLINE, '$today') >= 3 THEN 3
            ELSE 4
        END ASC,
        pr.DEADLINE ASC
");

$produksi_list = [];
while ($row = mysqli_fetch_assoc($query)) $produksi_list[] = $row;

// Statistik ringkasan
$stat = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN STATUS_PRODUKSI != 'Selesai' AND DEADLINE < '$today' THEN 1 ELSE 0 END) as melewati,
        SUM(CASE WHEN STATUS_PRODUKSI != 'Selesai' AND DATEDIFF(DEADLINE, '$today') BETWEEN 0 AND 2 THEN 1 ELSE 0 END) as mepet,
        SUM(CASE WHEN STATUS_PRODUKSI != 'Selesai' AND DATEDIFF(DEADLINE, '$today') >= 3 THEN 1 ELSE 0 END) as aman,
        SUM(CASE WHEN STATUS_PRODUKSI = 'Selesai' THEN 1 ELSE 0 END) as selesai
    FROM produksi
    WHERE STATUS_PRODUKSI != 'Dibatalkan'
"));

// Daftar penjahit untuk filter dropdown
$q_penjahit = mysqli_query($koneksi, "SELECT ID_PENJAHIT, NAMA_PENJAHIT FROM penjahit ORDER BY NAMA_PENJAHIT ASC");
$list_penjahit = [];
while ($pj = mysqli_fetch_assoc($q_penjahit)) $list_penjahit[] = $pj;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pantau Deadline | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;font-size:14.5px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(232,50,138,0.05) 1.5px,transparent 1.5px);background-size:28px 28px;pointer-events:none;z-index:0}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:var(--p50)}
::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px}
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.4}}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(.85);opacity:.6}}
.pulse{animation:pulse 1.8s ease-in-out infinite}
.fade-up{animation:fadeUp .4s ease both}

/* SIDEBAR */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--white);border-right:1.5px solid var(--border);display:flex;flex-direction:column;z-index:300;overflow:hidden}
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
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0}
.nav-item.logout{color:var(--r700)}.nav-item.logout i{color:var(--r500)}.nav-item.logout:hover{background:var(--r100);color:var(--r700);transform:none}

/* TOPBAR */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(16px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px}
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

/* LAYOUT */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1}
.content{padding:28px 32px 80px}

/* BREADCRUMB */
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text3);margin-bottom:18px}
.breadcrumb a{color:var(--p500);text-decoration:none;font-weight:600}
.breadcrumb a:hover{text-decoration:underline}

/* STAT CARDS */
.stat-row{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px}
.stat-card{border-radius:var(--r-lg);padding:16px 18px;display:flex;flex-direction:column;gap:6px;cursor:pointer;text-decoration:none;transition:all var(--ease);border:2px solid transparent;position:relative;overflow:hidden}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md)}
.stat-card.active-filter{border-color:currentColor;box-shadow:var(--shadow-md)}
.stat-ico{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:4px}
.stat-num{font-family:'Quicksand',sans-serif;font-size:28px;font-weight:700;line-height:1}
.stat-lbl{font-size:12px;font-weight:600}

.sc-total{background:linear-gradient(135deg,var(--p50),var(--v100));color:var(--p600)}
.sc-total .stat-ico{background:var(--p100);color:var(--p500)}
.sc-melewati{background:linear-gradient(135deg,var(--r100),#fff1f1);color:var(--r700)}
.sc-melewati .stat-ico{background:#fecaca;color:var(--r500)}
.sc-mepet{background:linear-gradient(135deg,var(--a100),#fffbeb);color:var(--a700)}
.sc-mepet .stat-ico{background:#fde68a;color:var(--a500)}
.sc-aman{background:linear-gradient(135deg,var(--g100),#f0fdf4);color:var(--g700)}
.sc-aman .stat-ico{background:#bbf7d0;color:var(--g500)}
.sc-selesai{background:linear-gradient(135deg,var(--b100),#eff6ff);color:var(--b700)}
.sc-selesai .stat-ico{background:#bfdbfe;color:var(--b500)}

/* FILTER BAR */
.filter-bar{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:var(--shadow-sm)}
.filter-bar label{font-size:12.5px;font-weight:700;color:var(--text2);white-space:nowrap}
.filter-select{padding:8px 14px;border:1.5px solid var(--border);border-radius:99px;font-size:13px;font-family:'Nunito',sans-serif;color:var(--text);background:var(--white);cursor:pointer;transition:border-color var(--ease-plain)}
.filter-select:focus{border-color:var(--p400);outline:none}
.btn-reset{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:99px;font-size:12.5px;font-weight:700;background:var(--p50);color:var(--p500);border:1.5px solid var(--border2);text-decoration:none;transition:all var(--ease-plain)}
.btn-reset:hover{background:var(--p500);color:#fff}
.filter-info{margin-left:auto;font-size:12px;color:var(--text3);font-weight:600}

/* TABLE CARD */
.table-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-sm)}
.table-hd{padding:16px 22px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;justify-content:space-between}
.table-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.table-title i{color:var(--p500)}
.live-dot{width:8px;height:8px;border-radius:50%;background:var(--g500);animation:blink 1.2s ease-in-out infinite}

.data-table{width:100%;border-collapse:collapse}
.data-table thead th{padding:11px 16px;font-size:11px;font-weight:700;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border);text-transform:uppercase;letter-spacing:.4px}
.data-table tbody td{padding:13px 16px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:13.5px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr{transition:background var(--ease-plain)}
.data-table tbody tr:hover td{background:var(--p50)}

/* STATUS BADGES */
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:99px;font-size:12px;font-weight:700}
.sb-melewati{background:var(--r100);color:var(--r700)}
.sb-mepet{background:var(--a100);color:var(--a700)}
.sb-aman{background:var(--g100);color:var(--g700)}
.sb-selesai{background:var(--b100);color:var(--b700)}
.sb-pending{background:var(--p50);color:var(--p600)}

/* DEADLINE INDICATOR */
.deadline-wrap{display:flex;flex-direction:column;gap:4px}
.deadline-date{font-size:13px;font-weight:700;color:var(--text)}
.deadline-bar-wrap{height:5px;background:var(--p50);border-radius:99px;overflow:hidden;width:120px}
.deadline-bar{height:100%;border-radius:99px;transition:width .6s ease}
.sisa-label{font-size:11px;font-weight:600}

/* KUALITAS BADGE */
.kual-baik{background:var(--g100);color:var(--g700);padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.kual-salah{background:var(--r100);color:var(--r700);padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.kual-null{background:var(--p50);color:var(--text3);padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:700}

/* BONUS/PENALTI CHIP */
.chip-bonus{background:var(--g100);color:var(--g700);padding:3px 9px;border-radius:99px;font-size:11.5px;font-weight:700}
.chip-penalti{background:var(--r100);color:var(--r700);padding:3px 9px;border-radius:99px;font-size:11.5px;font-weight:700}
.chip-none{background:var(--p50);color:var(--text3);padding:3px 9px;border-radius:99px;font-size:11.5px;font-weight:600}

/* PENJAHIT CELL */
.pj-cell{display:flex;align-items:center;gap:9px}
.pj-av-sm{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--p400),var(--v500));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
.pj-name-sm{font-size:13px;font-weight:700;color:var(--text)}
.pj-keahlian{font-size:11px;color:var(--text3);margin-top:1px}

/* EMPTY STATE */
.empty-state{text-align:center;padding:56px 24px;color:var(--text3)}
.empty-state i{font-size:44px;color:var(--p200);display:block;margin-bottom:14px}
.empty-state p{font-size:14.5px;font-weight:600;color:var(--text2);margin-bottom:4px}
.empty-state span{font-size:13px}

/* LEGEND */
.legend-row{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px}
.legend-item{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--text2)}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
</style>
</head>
<body>

<!-- SIDEBAR -->
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
        <a class="nav-item" href="kelola_aset.php"><i class="bi bi-building-gear"></i> Aset &amp; Inventaris</a>
        <a class="nav-item" href="data_penjahit.php"><i class="bi bi-people"></i> Data Penjahit</a>
        <a class="nav-item" href="pelanggan.php"><i class="bi bi-person-badge"></i> Data Pelanggan</a>
        <a class="nav-item" href="supplier.php"><i class="bi bi-truck"></i> Data Supplier</a>
        <div class="nav-group-label">Operasional</div>
        <a class="nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi Aktif</a>
        <a class="nav-item" href="aturan_bonus_penalti.php"><i class="bi bi-sliders"></i> Aturan Bonus & Penalti</a>
        <a class="nav-item active" href="pantau_deadline.php"><i class="bi bi-alarm-fill"></i> Pantau Deadline</a>
        <a class="nav-item" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="nav-item" href="konfirmasi_pembayaran.php">
            <i class="bi bi-credit-card-2-front"></i> Konfirmasi Bayar
            <?php if ($notif_bayar > 0): ?><span class="nav-pill pill-pink pulse"><?= $notif_bayar ?></span><?php endif; ?>
        </a>
        <a class="nav-item" href="input_pengiriman.php"><i class="bi bi-truck-front-fill"></i> Input Pengiriman</a>
        <a class="nav-item" href="chat.php"><i class="bi bi-chat-dots-fill"></i> Inbox Chat</a>
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
        <div class="tb-hello">Pantau Deadline Penjahit</div>
        <div class="tb-sub">Monitoring real-time status penyelesaian dan prediksi keterlambatan</div>
    </div>
    <div class="tb-actions">
        <a href="<?= $notif_bayar > 0 ? 'konfirmasi_pembayaran.php' : '#' ?>" class="icon-btn">
            <i class="bi bi-bell-fill"></i>
            <?php if ($notif_bayar > 0): ?><span class="dot"></span><?php endif; ?>
        </a>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- MAIN -->
<main class="main">
<div class="content">

    <div class="breadcrumb">
        <a href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:10px"></i>
        <span>Pantau Deadline</span>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-row fade-up">
        <a href="?status=semua<?= $filter_penjahit ? '&penjahit='.$filter_penjahit : '' ?>"
           class="stat-card sc-total <?= $filter_status==='semua'?'active-filter':'' ?>">
            <div class="stat-ico"><i class="bi bi-collection-fill"></i></div>
            <div class="stat-num"><?= $stat['total'] ?></div>
            <div class="stat-lbl">Total Produksi</div>
        </a>
        <a href="?status=melewati<?= $filter_penjahit ? '&penjahit='.$filter_penjahit : '' ?>"
           class="stat-card sc-melewati <?= $filter_status==='melewati'?'active-filter':'' ?>">
            <div class="stat-ico"><i class="bi bi-exclamation-octagon-fill"></i></div>
            <div class="stat-num"><?= $stat['melewati'] ?></div>
            <div class="stat-lbl">Melewati Deadline</div>
        </a>
        <a href="?status=mepet<?= $filter_penjahit ? '&penjahit='.$filter_penjahit : '' ?>"
           class="stat-card sc-mepet <?= $filter_status==='mepet'?'active-filter':'' ?>">
            <div class="stat-ico"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-num"><?= $stat['mepet'] ?></div>
            <div class="stat-lbl">Hampir Deadline</div>
        </a>
        <a href="?status=aman<?= $filter_penjahit ? '&penjahit='.$filter_penjahit : '' ?>"
           class="stat-card sc-aman <?= $filter_status==='aman'?'active-filter':'' ?>">
            <div class="stat-ico"><i class="bi bi-shield-fill-check"></i></div>
            <div class="stat-num"><?= $stat['aman'] ?></div>
            <div class="stat-lbl">Masih Aman</div>
        </a>
        <a href="?status=selesai<?= $filter_penjahit ? '&penjahit='.$filter_penjahit : '' ?>"
           class="stat-card sc-selesai <?= $filter_status==='selesai'?'active-filter':'' ?>">
            <div class="stat-ico"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-num"><?= $stat['selesai'] ?></div>
            <div class="stat-lbl">Sudah Selesai</div>
        </a>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" class="filter-bar fade-up">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
        <label><i class="bi bi-funnel-fill" style="color:var(--p500)"></i> Filter Penjahit:</label>
        <select name="penjahit" class="filter-select" onchange="this.form.submit()">
            <option value="">Semua Penjahit</option>
            <?php foreach ($list_penjahit as $pj): ?>
            <option value="<?= htmlspecialchars($pj['ID_PENJAHIT']) ?>"
                <?= $filter_penjahit === $pj['ID_PENJAHIT'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($pj['NAMA_PENJAHIT']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($filter_penjahit || $filter_status !== 'semua'): ?>
        <a href="pantau_deadline.php" class="btn-reset"><i class="bi bi-x-circle"></i> Reset Filter</a>
        <?php endif; ?>
        <div class="filter-info">
            <i class="bi bi-table"></i> Menampilkan <?= count($produksi_list) ?> data
        </div>
    </form>

    <!-- LEGEND -->
    <div class="legend-row fade-up">
        <div class="legend-item"><span class="legend-dot" style="background:var(--r500)"></span> Melewati deadline</div>
        <div class="legend-item"><span class="legend-dot" style="background:var(--a500)"></span> Sisa 0–2 hari</div>
        <div class="legend-item"><span class="legend-dot" style="background:var(--g500)"></span> Sisa 3+ hari</div>
        <div class="legend-item"><span class="legend-dot" style="background:var(--b500)"></span> Sudah selesai</div>
    </div>

    <!-- TABEL UTAMA -->
    <div class="table-card fade-up">
        <div class="table-hd">
            <div class="table-title">
                <i class="bi bi-alarm-fill"></i> Status Deadline Seluruh Produksi
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <div class="live-dot"></div>
                <span style="font-size:12px;color:var(--text3);font-weight:600">Live · <?= date('H:i') ?></span>
            </div>
        </div>

        <?php if (empty($produksi_list)): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <p>Tidak ada data untuk filter ini</p>
            <span>Coba ubah filter atau tambahkan data produksi terlebih dahulu</span>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Produksi</th>
                    <th>Penjahit</th>
                    <th>Produk</th>
                    <th>Mulai</th>
                    <th>Deadline & Progress</th>
                    <th>Status</th>
                    <th>Kualitas</th>
                    <th>Bonus / Penalti</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($produksi_list as $row):
                $status  = $row['STATUS_PRODUKSI'];
                $sisa    = (int)$row['sisa_hari'];
                $durasi  = (int)$row['durasi_total'];
                $selisih = $row['selisih_selesai'];
                $inisial_pj = strtoupper(substr($row['NAMA_PENJAHIT'] ?? '-', 0, 1));

                // Tentukan kategori warna deadline
                if ($status === 'Selesai') {
                    $row_class  = '';
                    $badge_class = 'sb-selesai';
                    $bar_color  = '#3b82f6';
                    $bar_pct    = 100;
                    $sisa_label = $selisih !== null
                        ? ($selisih > 0 ? "<span style='color:var(--g700);font-weight:700'>Selesai ".abs($selisih)." hari lebih awal</span>"
                            : ($selisih < 0 ? "<span style='color:var(--r700);font-weight:700'>Terlambat ".abs($selisih)." hari</span>"
                            : "<span style='color:var(--b700);font-weight:700'>Tepat waktu</span>"))
                        : '<span style="color:var(--text3)">Selesai</span>';
                } elseif ($row['DEADLINE'] === null) {
                    $badge_class = 'sb-pending';
                    $bar_color  = '#b07090';
                    $bar_pct    = 0;
                    $sisa_label = '<span style="color:var(--text3)">Belum ada deadline</span>';
                } elseif ($sisa < 0) {
                    $badge_class = 'sb-melewati';
                    $bar_color  = '#ef4444';
                    $bar_pct    = 100;
                    $sisa_label = '<span style="color:var(--r700);font-weight:700">Terlambat '.abs($sisa).' hari</span>';
                } elseif ($sisa <= 2) {
                    $badge_class = 'sb-mepet';
                    $bar_color  = '#eab308';
                    $elapsed    = $durasi > 0 ? min(100, round((($durasi - $sisa) / $durasi) * 100)) : 50;
                    $bar_pct    = $elapsed;
                    $sisa_label = '<span style="color:var(--a700);font-weight:700">Sisa '.$sisa.' hari</span>';
                } else {
                    $badge_class = 'sb-aman';
                    $bar_color  = '#22c55e';
                    $elapsed    = $durasi > 0 ? min(100, round((($durasi - $sisa) / $durasi) * 100)) : 30;
                    $bar_pct    = $elapsed;
                    $sisa_label = '<span style="color:var(--g700);font-weight:700">Sisa '.$sisa.' hari</span>';
                }

                // Badge status produksi
                $status_label = match($status) {
                    'Selesai'    => '<i class="bi bi-check-circle-fill"></i> Selesai',
                    'In Progress'=> '<i class="bi bi-arrow-repeat"></i> In Progress',
                    'Pending'    => '<i class="bi bi-clock"></i> Pending',
                    default      => htmlspecialchars($status)
                };

                // Kualitas
                $kual = $row['STATUS_KUALITAS'];
                if ($status !== 'Selesai') {
                    $kual_html = '<span class="kual-null">—</span>';
                } elseif ($kual === 'baik') {
                    $kual_html = '<span class="kual-baik"><i class="bi bi-patch-check-fill"></i> Baik</span>';
                } else {
                    $kual_html = '<span class="kual-salah"><i class="bi bi-exclamation-circle-fill"></i> Ada Kesalahan</span>';
                }

                // Bonus/Penalti
                $bonus   = (float)$row['BONUS'];
                $penalti = (float)$row['PENALTI'];
                if ($status !== 'Selesai') {
                    $bp_html = '<span class="chip-none">Belum dihitung</span>';
                } elseif ($bonus > 0) {
                    $bp_html = '<span class="chip-bonus"><i class="bi bi-arrow-up-circle-fill"></i> +Rp '.number_format($bonus).'</span>';
                } elseif ($penalti > 0) {
                    $bp_html = '<span class="chip-penalti"><i class="bi bi-arrow-down-circle-fill"></i> -Rp '.number_format($penalti).'</span>';
                } else {
                    $bp_html = '<span class="chip-none">Tidak ada</span>';
                }
            ?>
            <tr>
                <td>
                    <span style="font-family:'Quicksand',sans-serif;font-weight:700;color:var(--p600);font-size:13px">
                        <?= htmlspecialchars($row['ID_PRODUKSI']) ?>
                    </span>
                    <div style="font-size:11px;color:var(--text3)"><?= $row['JUMLAH_DIPRODUKSI'] ?> unit</div>
                </td>
                <td>
                    <div class="pj-cell">
                        <div class="pj-av-sm"><?= $inisial_pj ?></div>
                        <div>
                            <div class="pj-name-sm"><?= htmlspecialchars($row['NAMA_PENJAHIT'] ?? '-') ?></div>
                            <div class="pj-keahlian"><?= htmlspecialchars($row['KEAHLIAN'] ?? '') ?></div>
                        </div>
                    </div>
                </td>
                <td style="font-weight:600;color:var(--text2)"><?= htmlspecialchars($row['NAMA_PRODUK'] ?? '-') ?></td>
                <td style="font-size:13px;color:var(--text3)"><?= $row['TANGGAL_MULAI'] ? date('d M Y', strtotime($row['TANGGAL_MULAI'])) : '—' ?></td>
                <td>
                    <?php if ($row['DEADLINE']): ?>
                    <div class="deadline-wrap">
                        <div class="deadline-date"><?= date('d M Y', strtotime($row['DEADLINE'])) ?></div>
                        <div class="deadline-bar-wrap">
                            <div class="deadline-bar" style="width:<?= $bar_pct ?>%;background:<?= $bar_color ?>"></div>
                        </div>
                        <div class="sisa-label"><?= $sisa_label ?></div>
                    </div>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text3)">Belum diset</span>
                    <?php endif; ?>
                </td>
                <td><span class="status-badge <?= $badge_class ?>"><?= $status_label ?></span></td>
                <td><?= $kual_html ?></td>
                <td><?= $bp_html ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

</div>
</main>
</body>
</html>