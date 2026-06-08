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

$pesan_sukses = "";
$pesan_error  = "";
$data_edit    = null;

// TAMBAH ATURAN
if (isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $hari_cepat   = (int)$_POST['hari_lebih_cepat'];
    $bonus_persen = (float)$_POST['bonus_persen'];
    $hari_lambat  = (int)$_POST['hari_terlambat'];
    $penalti      = (float)$_POST['penalti_persen'];

    if ($hari_cepat < 0 || $bonus_persen < 0 || $hari_lambat < 0 || $penalti < 0) {
        $pesan_error = "Semua nilai harus berupa angka positif.";
    } elseif ($bonus_persen > 100 || $penalti > 100) {
        $pesan_error = "Persentase tidak boleh melebihi 100%.";
    } else {
        $cek = mysqli_prepare($koneksi, "SELECT id FROM aturan_deadline WHERE hari_lebih_cepat = ? OR hari_terlambat = ?");
        mysqli_stmt_bind_param($cek, "ii", $hari_cepat, $hari_lambat);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);
        if (mysqli_stmt_num_rows($cek) > 0) {
            $pesan_error = "Sudah ada aturan dengan nilai hari yang sama. Gunakan nilai berbeda atau edit aturan yang ada.";
        } else {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO aturan_deadline (hari_lebih_cepat, bonus_persen, hari_terlambat, penalti_persen) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "idid", $hari_cepat, $bonus_persen, $hari_lambat, $penalti);
            if (mysqli_stmt_execute($stmt)) {
                $pesan_sukses = "Aturan berhasil ditambahkan.";
            } else {
                $pesan_error = "Gagal menyimpan aturan.";
            }
        }
        mysqli_stmt_close($cek);
    }
}

// HAPUS ATURAN
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $stmt = mysqli_prepare($koneksi, "DELETE FROM aturan_deadline WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_hapus);
    if (mysqli_stmt_execute($stmt)) {
        $pesan_sukses = "Aturan berhasil dihapus.";
    } else {
        $pesan_error = "Gagal menghapus aturan.";
    }
}

// AMBIL DATA UNTUK EDIT
if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];
    $res = mysqli_prepare($koneksi, "SELECT * FROM aturan_deadline WHERE id = ?");
    mysqli_stmt_bind_param($res, "i", $id_edit);
    mysqli_stmt_execute($res);
    $data_edit = mysqli_fetch_assoc(mysqli_stmt_get_result($res));
}

// SIMPAN HASIL EDIT
if (isset($_POST['aksi']) && $_POST['aksi'] === 'edit') {
    $id_edit      = (int)$_POST['id'];
    $hari_cepat   = (int)$_POST['hari_lebih_cepat'];
    $bonus_persen = (float)$_POST['bonus_persen'];
    $hari_lambat  = (int)$_POST['hari_terlambat'];
    $penalti      = (float)$_POST['penalti_persen'];

    if ($hari_cepat < 0 || $bonus_persen < 0 || $hari_lambat < 0 || $penalti < 0) {
        $pesan_error = "Semua nilai harus berupa angka positif.";
    } elseif ($bonus_persen > 100 || $penalti > 100) {
        $pesan_error = "Persentase tidak boleh melebihi 100%.";
    } else {
        $stmt = mysqli_prepare($koneksi, "UPDATE aturan_deadline SET hari_lebih_cepat=?, bonus_persen=?, hari_terlambat=?, penalti_persen=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ididi", $hari_cepat, $bonus_persen, $hari_lambat, $penalti, $id_edit);
        if (mysqli_stmt_execute($stmt)) {
            $pesan_sukses = "Aturan berhasil diperbarui.";
            $data_edit = null;
        } else {
            $pesan_error = "Gagal memperbarui aturan.";
        }
    }
}

// AMBIL SEMUA ATURAN
$query_aturan = mysqli_query($koneksi, "SELECT * FROM aturan_deadline ORDER BY hari_lebih_cepat ASC");
$aturan = [];
while ($row = mysqli_fetch_assoc($query_aturan)) $aturan[] = $row;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aturan Bonus & Penalti | Konveksi Apps</title>
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
.content{padding:28px 32px 80px;max-width:1100px}

/* PAGE HEADER */
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px}
.page-title{font-family:'Quicksand',sans-serif;font-size:22px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px}
.page-title i{color:var(--p500)}
.page-sub{font-size:13px;color:var(--text3);font-weight:500;margin-top:3px}
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text3)}
.breadcrumb a{color:var(--p500);text-decoration:none;font-weight:600}
.breadcrumb a:hover{text-decoration:underline}

/* ALERT */
.alert{padding:13px 18px;border-radius:var(--r-md);margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px;border:1.5px solid;font-weight:600}
.alert-success{background:var(--g100);color:var(--g700);border-color:#86efac}
.alert-danger{background:var(--r100);color:var(--r700);border-color:#fca5a5}

/* INFO BOX */
.info-box{background:linear-gradient(135deg,var(--b100),#eff6ff);border:1.5px solid rgba(59,130,246,0.2);border-radius:var(--r-md);padding:16px 20px;margin-bottom:24px;display:flex;gap:14px;align-items:flex-start}
.info-box-ico{width:36px;height:36px;border-radius:10px;background:var(--b100);color:var(--b500);display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.info-box-text{font-size:13px;color:#1e40af;line-height:1.7}
.info-box-text strong{display:block;font-size:13.5px;font-weight:700;margin-bottom:4px;color:#1d4ed8}

/* CARD */
.card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-sm);margin-bottom:24px}
.card-hd{padding:18px 24px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.card-title i{color:var(--p500)}
.card-body{padding:24px}

/* FORM GRID */
.form-section-label{font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--text3);margin-bottom:12px;display:flex;align-items:center;gap:7px}
.form-section-label::before{content:'';display:inline-block;width:12px;height:3px;border-radius:99px}
.bonus-label::before{background:var(--g500)}
.penalti-label::before{background:var(--r500)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column}
.form-group label{font-size:12.5px;font-weight:700;color:var(--text2);margin-bottom:6px}
.form-group input{padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:14px;font-family:'Nunito',sans-serif;color:var(--text);background:var(--white);transition:border-color var(--ease-plain),box-shadow var(--ease-plain)}
.form-group input:focus{border-color:var(--p400);outline:none;box-shadow:0 0 0 3px rgba(232,50,138,0.12)}
.form-hint{font-size:11px;color:var(--text3);margin-top:5px}
.form-divider{border:none;border-top:1.5px dashed var(--border);margin:20px 0}
.form-actions{display:flex;gap:10px;margin-top:22px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:99px;font-size:13.5px;font-weight:700;border:none;cursor:pointer;transition:all var(--ease);text-decoration:none;font-family:'Nunito',sans-serif}
.btn-primary{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;box-shadow:0 4px 14px rgba(232,50,138,0.3)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(232,50,138,0.4);color:#fff}
.btn-secondary{background:var(--white);color:var(--text2);border:1.5px solid var(--border)}
.btn-secondary:hover{background:var(--p50);color:var(--p500);border-color:var(--border2)}
.btn-sm{padding:6px 14px;font-size:12px;border-radius:99px;font-weight:700;border:none;cursor:pointer;font-family:'Nunito',sans-serif;display:inline-flex;align-items:center;gap:5px;transition:all var(--ease-plain);text-decoration:none}
.btn-edit{background:var(--a100);color:var(--a700)}
.btn-edit:hover{background:#fde047;color:var(--a700)}
.btn-hapus{background:var(--r100);color:var(--r700)}
.btn-hapus:hover{background:#fca5a5;color:var(--r700)}

/* TABLE */
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{padding:11px 18px;font-size:11.5px;font-weight:700;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border)}
.data-table tbody td{padding:13px 18px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:14px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr:hover td{background:var(--p50)}

/* BADGE */
.badge-bonus{background:var(--g100);color:var(--g700);padding:4px 12px;border-radius:99px;font-size:12.5px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.badge-penalti{background:var(--r100);color:var(--r700);padding:4px 12px;border-radius:99px;font-size:12.5px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.tier-badge{background:linear-gradient(135deg,var(--p500),var(--v500));color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;box-shadow:0 2px 8px rgba(232,50,138,0.3)}

/* EMPTY STATE */
.empty-state{text-align:center;padding:48px 24px;color:var(--text3)}
.empty-state i{font-size:40px;color:var(--p200);display:block;margin-bottom:12px}
.empty-state p{font-size:14px;color:var(--text3);margin-bottom:4px}
.empty-state span{font-size:12px}

/* STAT ROW */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:16px 18px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow-sm)}
.stat-ico{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-lbl{font-size:11px;font-weight:600;color:var(--text3);margin-bottom:2px}
.stat-val{font-family:'Quicksand',sans-serif;font-size:20px;font-weight:700}
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
        <a class="nav-item active" href="aturan_bonus_penalti.php"><i class="bi bi-sliders"></i> Aturan Bonus & Penalti</a>
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
        <div class="tb-hello">Aturan Bonus &amp; Penalti 💰</div>
        <div class="tb-sub">Konfigurasi threshold dan persentase untuk kalkulasi upah penjahit</div>
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

    <!-- BREADCRUMB -->
    <div class="breadcrumb" style="margin-bottom:16px">
        <a href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:10px"></i>
        <span>Aturan Bonus &amp; Penalti</span>
    </div>

    <!-- ALERT -->
    <?php if ($pesan_sukses): ?>
    <div class="alert alert-success fade-up"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($pesan_sukses) ?></div>
    <?php endif; ?>
    <?php if ($pesan_error): ?>
    <div class="alert alert-danger fade-up"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($pesan_error) ?></div>
    <?php endif; ?>

    <!-- STAT ROW -->
    <div class="stat-row fade-up">
        <div class="stat-card">
            <div class="stat-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-list-check"></i></div>
            <div>
                <div class="stat-lbl">Total Aturan Aktif</div>
                <div class="stat-val" style="color:var(--p600)"><?= count($aturan) ?> Tier</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="stat-lbl">Bonus Tertinggi</div>
                <div class="stat-val" style="color:var(--g700)">
                    <?php
                    $max_bonus = !empty($aturan) ? max(array_column($aturan, 'bonus_persen')) : 0;
                    echo number_format($max_bonus, 2) . '%';
                    ?>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-ico" style="background:var(--r100);color:var(--r500)"><i class="bi bi-graph-down-arrow"></i></div>
            <div>
                <div class="stat-lbl">Penalti Tertinggi</div>
                <div class="stat-val" style="color:var(--r700)">
                    <?php
                    $max_penalti = !empty($aturan) ? max(array_column($aturan, 'penalti_persen')) : 0;
                    echo number_format($max_penalti, 2) . '%';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- INFO BOX -->
    <div class="info-box fade-up">
        <div class="info-box-ico"><i class="bi bi-info-circle-fill"></i></div>
        <div class="info-box-text">
            <strong>Cara Kerja Sistem Aturan</strong>
            Setiap baris mewakili satu tier aturan. Sistem mencocokkan selisih hari penyelesaian penjahit dengan threshold yang didefinisikan di sini.
            Jika penjahit selesai lebih cepat dari deadline sebanyak nilai <em>Hari Lebih Cepat</em> atau lebih, persentase bonus pada baris tersebut diterapkan ke upah dasar.
            Sebaliknya, jika terlambat sebanyak nilai <em>Hari Terlambat</em> atau lebih, penalti dipotong dari upah dasar.
            Sistem memilih tier dengan threshold terbesar yang masih terpenuhi oleh selisih hari aktual.
        </div>
    </div>

    <!-- FORM TAMBAH / EDIT -->
    <div class="card fade-up">
        <div class="card-hd">
            <div class="card-title">
                <i class="bi bi-<?= $data_edit ? 'pencil-square' : 'plus-circle-fill' ?>"></i>
                <?= $data_edit ? 'Edit Aturan — Tier #' . $data_edit['id'] : 'Tambah Aturan Baru' ?>
            </div>
            <?php if ($data_edit): ?>
            <a href="aturan_bonus_penalti.php" class="btn btn-secondary" style="font-size:12px;padding:7px 16px">
                <i class="bi bi-x-circle"></i> Batal Edit
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="aksi" value="<?= $data_edit ? 'edit' : 'tambah' ?>">
                <?php if ($data_edit): ?>
                <input type="hidden" name="id" value="<?= $data_edit['id'] ?>">
                <?php endif; ?>

                <div class="form-section-label bonus-label">Ketentuan Bonus — Selesai Lebih Cepat</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Minimum Hari Lebih Cepat</label>
                        <input type="number" name="hari_lebih_cepat" min="0"
                               value="<?= $data_edit ? htmlspecialchars($data_edit['hari_lebih_cepat']) : '' ?>"
                               placeholder="Contoh: 3" required>
                        <span class="form-hint">Penjahit harus selesai minimal sekian hari sebelum deadline agar bonus berlaku</span>
                    </div>
                    <div class="form-group">
                        <label>Persentase Bonus (%)</label>
                        <input type="number" name="bonus_persen" min="0" max="100" step="0.01"
                               value="<?= $data_edit ? htmlspecialchars($data_edit['bonus_persen']) : '' ?>"
                               placeholder="Contoh: 5.00" required>
                        <span class="form-hint">Ditambahkan ke upah dasar. Maksimal 100%</span>
                    </div>
                </div>

                <hr class="form-divider">

                <div class="form-section-label penalti-label">Ketentuan Penalti — Terlambat dari Deadline</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Minimum Hari Terlambat</label>
                        <input type="number" name="hari_terlambat" min="0"
                               value="<?= $data_edit ? htmlspecialchars($data_edit['hari_terlambat']) : '' ?>"
                               placeholder="Contoh: 2" required>
                        <span class="form-hint">Penjahit harus terlambat minimal sekian hari agar penalti berlaku</span>
                    </div>
                    <div class="form-group">
                        <label>Persentase Penalti (%)</label>
                        <input type="number" name="penalti_persen" min="0" max="100" step="0.01"
                               value="<?= $data_edit ? htmlspecialchars($data_edit['penalti_persen']) : '' ?>"
                               placeholder="Contoh: 3.00" required>
                        <span class="form-hint">Dipotong dari upah dasar. Maksimal 100%</span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?= $data_edit ? 'floppy-fill' : 'plus-circle-fill' ?>"></i>
                        <?= $data_edit ? 'Simpan Perubahan' : 'Tambah Aturan' ?>
                    </button>
                    <?php if ($data_edit): ?>
                    <a href="aturan_bonus_penalti.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Batal
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- TABEL ATURAN -->
    <div class="card fade-up">
        <div class="card-hd">
            <div class="card-title"><i class="bi bi-table"></i> Daftar Aturan yang Berlaku</div>
            <span style="font-size:12px;color:var(--text3);font-weight:600"><?= count($aturan) ?> aturan terdaftar</span>
        </div>
        <?php if (empty($aturan)): ?>
        <div class="empty-state">
            <i class="bi bi-sliders2"></i>
            <p>Belum ada aturan yang ditambahkan</p>
            <span>Tambahkan aturan di atas agar kalkulasi bonus dan penalti pada penggajian dapat berjalan</span>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tier</th>
                    <th>Selesai Lebih Cepat</th>
                    <th>Bonus</th>
                    <th>Terlambat</th>
                    <th>Penalti</th>
                    <th>Terakhir Diperbarui</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aturan as $i => $row): ?>
                <tr>
                    <td><span class="tier-badge"><?= $i + 1 ?></span></td>
                    <td style="font-weight:700;color:var(--text)">&ge; <?= htmlspecialchars($row['hari_lebih_cepat']) ?> hari lebih awal</td>
                    <td><span class="badge-bonus"><i class="bi bi-arrow-up-circle-fill"></i> +<?= number_format($row['bonus_persen'], 2) ?>%</span></td>
                    <td style="font-weight:700;color:var(--text)">&ge; <?= htmlspecialchars($row['hari_terlambat']) ?> hari terlambat</td>
                    <td><span class="badge-penalti"><i class="bi bi-arrow-down-circle-fill"></i> -<?= number_format($row['penalti_persen'], 2) ?>%</span></td>
                    <td style="color:var(--text3);font-size:13px"><?= date('d M Y, H:i', strtotime($row['updated_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <a href="?edit=<?= $row['id'] ?>" class="btn-sm btn-edit">
                                <i class="bi bi-pencil-fill"></i> Edit
                            </a>
                            <a href="?hapus=<?= $row['id'] ?>" class="btn-sm btn-hapus"
                               onclick="return confirm('Hapus tier ini?\n\nKalkulasi penggajian yang sudah tersimpan tidak terpengaruh. Hanya aturan ke depan yang berubah.')">
                                <i class="bi bi-trash3-fill"></i> Hapus
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>
</main>
</body>
</html>