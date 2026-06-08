<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

// =================== PROSES TAMBAH ASET ===================
if (isset($_POST['tambah_aset'])) {
    $id      = mysqli_real_escape_string($koneksi, $_POST['id_aset']);
    $nama    = mysqli_real_escape_string($koneksi, $_POST['nama_aset']);
    $jenis   = mysqli_real_escape_string($koneksi, $_POST['jenis_aset']);
    $nilai   = mysqli_real_escape_string($koneksi, $_POST['nilai_aset']);
    $kondisi = mysqli_real_escape_string($koneksi, $_POST['kondisi']);
    mysqli_query($koneksi, "INSERT INTO aset (ID_ASET, ID_OWNER, NAMA_ASET, JENIS_ASET, NILAI_ASET, KONDISI_ASET) 
                            VALUES ('$id', '{$_SESSION['id_owner']}', '$nama', '$jenis', '$nilai', '$kondisi')");
    echo "<script>alert('Aset berhasil ditambahkan!'); window.location='kelola_aset.php';</script>";
}

// =================== PROSES EDIT ASET ===================
if (isset($_POST['update_aset'])) {
    $id      = mysqli_real_escape_string($koneksi, $_POST['id_aset']);
    $nama    = mysqli_real_escape_string($koneksi, $_POST['nama_aset']);
    $jenis   = mysqli_real_escape_string($koneksi, $_POST['jenis_aset']);
    $nilai   = mysqli_real_escape_string($koneksi, $_POST['nilai_aset']);
    $kondisi = mysqli_real_escape_string($koneksi, $_POST['kondisi']);
    mysqli_query($koneksi, "UPDATE aset SET NAMA_ASET='$nama', JENIS_ASET='$jenis', NILAI_ASET='$nilai', KONDISI_ASET='$kondisi' WHERE ID_ASET='$id'");
    echo "<script>alert('Aset berhasil diperbarui!'); window.location='kelola_aset.php';</script>";
}

// =================== PROSES HAPUS ASET ===================
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    mysqli_query($koneksi, "DELETE FROM aset WHERE ID_ASET='$id'");
    header("Location: kelola_aset.php");
    exit;
}

// =================== PROSES CATAT SERVIS ===================
if (isset($_POST['tambah_servis'])) {
    $id_aset      = mysqli_real_escape_string($koneksi, $_POST['id_aset_servis']);
    $keterangan   = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $biaya        = mysqli_real_escape_string($koneksi, $_POST['biaya']);
    $tanggal      = mysqli_real_escape_string($koneksi, $_POST['tanggal_servis']);
    $kondisi_baru = mysqli_real_escape_string($koneksi, $_POST['kondisi_setelah']);
    mysqli_query($koneksi, "INSERT INTO servis_aset (ID_ASET, KETERANGAN, BIAYA_SERVIS, TANGGAL_SERVIS, KONDISI_SETELAH) 
                            VALUES ('$id_aset', '$keterangan', '$biaya', '$tanggal', '$kondisi_baru')");
    mysqli_query($koneksi, "UPDATE aset SET KONDISI_ASET='$kondisi_baru' WHERE ID_ASET='$id_aset'");
    echo "<script>alert('Catatan servis berhasil disimpan!'); window.location='kelola_aset.php';</script>";
}

// =================== AMBIL DATA ===================
$data_aset  = [];
$q_aset     = mysqli_query($koneksi, "SELECT * FROM aset ORDER BY ID_ASET ASC");
while ($r = mysqli_fetch_assoc($q_aset)) $data_aset[] = $r;

$total_nilai = mysqli_fetch_assoc(
    mysqli_query($koneksi,
    "SELECT COALESCE(SUM(NILAI_ASET),0) as total FROM aset")
)['total'] ?? 0;

$total_servis = mysqli_fetch_assoc(
    mysqli_query($koneksi,
    "SELECT COALESCE(SUM(BIAYA),0) as total FROM servis_aset")
)['total'] ?? 0;

// Database saat ini belum memiliki kolom KONDISI_ASET
$aset_rusak = 0;
$aset_baik = count($data_aset);

$q_servis = mysqli_query($koneksi, "SELECT s.*, a.NAMA_ASET, a.JENIS_ASET 
                                    FROM servis_aset s 
                                    JOIN aset a ON s.ID_ASET = a.ID_ASET 
                                    ORDER BY s.TANGGAL_SERVIS DESC LIMIT 20");
$riwayat_servis = [];
while ($r = mysqli_fetch_assoc($q_servis)) $riwayat_servis[] = $r;

// Sidebar notifications (sama seperti dashboard)
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;

$nama_owner = $_SESSION['user'];
$inisial    = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts   = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aset & Inventaris 🔧 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* ══════════════════════════════════════
   ROOT — Sama persis dengan dashboard
══════════════════════════════════════ */
:root {
    --p50:  #fff0f5;
    --p100: #ffd6e7;
    --p200: #ffadd0;
    --p300: #ff80b8;
    --p400: #f950a0;
    --p500: #e8328a;
    --p600: #cc1a73;
    --p700: #a8105d;
    --v100: #f3e8ff;
    --v300: #d8b4fe;
    --v500: #a855f7;
    --g100: #dcfce7;
    --g500: #22c55e;
    --g700: #15803d;
    --a100: #fef9c3;
    --a500: #eab308;
    --a700: #854d0e;
    --b100: #dbeafe;
    --b500: #3b82f6;
    --b700: #1d4ed8;
    --r100: #fee2e2;
    --r500: #ef4444;
    --r700: #991b1b;
    --o100: #ffedd5;
    --o500: #f97316;
    --o700: #9a3412;
    --white:   #ffffff;
    --bg:      #fff5f9;
    --bg2:     #ffeef5;
    --text:    #3d1a28;
    --text2:   #7d4460;
    --text3:   #b07090;
    --border:  rgba(232,50,138,0.13);
    --border2: rgba(232,50,138,0.24);
    --sidebar-w: 256px;
    --topbar-h:  64px;
    --r-sm: 10px;
    --r-md: 16px;
    --r-lg: 22px;
    --r-xl: 28px;
    --ease: 0.2s cubic-bezier(0.34,1.56,0.64,1);
    --ease-plain: 0.17s ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Nunito', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    font-size: 14.5px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}
body::before {
    content: '';
    position: fixed; inset: 0;
    background-image: radial-gradient(circle, rgba(232,50,138,0.055) 1.5px, transparent 1.5px);
    background-size: 28px 28px;
    pointer-events: none; z-index: 0;
}
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: var(--p50); }
::-webkit-scrollbar-thumb { background: var(--p200); border-radius: 99px; }

/* ══ SIDEBAR ══ */
.sidebar {
    position: fixed; top: 0; left: 0;
    width: var(--sidebar-w); height: 100vh;
    background: var(--white);
    border-right: 1.5px solid var(--border);
    display: flex; flex-direction: column;
    z-index: 300; overflow: hidden;
}
.sidebar::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--p400), var(--v500), var(--p300), var(--p500));
    background-size: 200%; z-index: 1;
    animation: shimmer 3s linear infinite;
}
@keyframes shimmer { 0%{background-position:0%} 100%{background-position:200%} }

.sb-brand {
    display: flex; align-items: center; gap: 12px;
    padding: 0 18px; height: var(--topbar-h);
    border-bottom: 1.5px solid var(--border);
    text-decoration: none; flex-shrink: 0;
    transition: background var(--ease-plain);
    margin-top: 4px;
}
.sb-brand:hover { background: var(--p50); }
.brand-mark {
    width: 38px; height: 38px; border-radius: 13px;
    background: linear-gradient(135deg, var(--p500) 0%, var(--p400) 50%, var(--v500) 100%);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(232,50,138,0.4), 0 0 0 3px rgba(232,50,138,0.12);
    transition: transform var(--ease), box-shadow var(--ease);
}
.sb-brand:hover .brand-mark { transform: rotate(-8deg) scale(1.08); }
.brand-mark i { font-size: 18px; color: #fff; }
.brand-name { font-family: 'Quicksand', sans-serif; font-size: 16px; font-weight: 700; color: var(--text); white-space: nowrap; letter-spacing: -0.2px; }
.brand-sub { font-size: 10px; font-weight: 600; color: var(--p500); letter-spacing: 0.8px; text-transform: uppercase; margin-top: 1px; }

.sb-owner {
    margin: 12px 12px 6px; padding: 12px 14px;
    background: linear-gradient(135deg, var(--p50), var(--v100));
    border: 1.5px solid var(--border); border-radius: var(--r-lg);
    display: flex; align-items: center; gap: 10px; flex-shrink: 0;
}
.owner-av {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, var(--p500), var(--v500));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Quicksand', sans-serif; font-size: 13px; font-weight: 700; color: #fff;
    flex-shrink: 0; position: relative;
    box-shadow: 0 3px 10px rgba(232,50,138,0.35);
}
.owner-av::after { content:''; position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:var(--g500); border:2px solid var(--white); }
.owner-name { font-size: 13.5px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.owner-role { font-size: 11px; color: var(--p500); font-weight: 600; }

.sb-nav { flex: 1; overflow-y: auto; padding: 6px 10px 10px; }
.nav-group-label { font-size: 9.5px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: var(--text3); padding: 14px 10px 4px; display: flex; align-items: center; gap: 6px; }
.nav-group-label::after { content:'✦'; font-size:7px; color:var(--p300); }

.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 11px; border-radius: var(--r-sm);
    text-decoration: none; color: var(--text2);
    font-size: 14px; font-weight: 600;
    transition: background var(--ease-plain), color var(--ease-plain), transform var(--ease-plain);
    margin-bottom: 2px; position: relative; white-space: nowrap;
}
.nav-item i { font-size: 17px; width: 19px; text-align: center; flex-shrink: 0; color: var(--text3); transition: color var(--ease-plain); }
.nav-item:hover { background: var(--p50); color: var(--p500); transform: translateX(2px); }
.nav-item:hover i { color: var(--p400); }
.nav-item.active { background: linear-gradient(135deg, var(--p500), var(--p400)); color: #fff; font-weight: 700; box-shadow: 0 4px 16px rgba(232,50,138,0.35); }
.nav-item.active i { color: rgba(255,255,255,0.9); }
.nav-item.active::after { content:''; position:absolute; right:10px; width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,0.6); }

.nav-pill { margin-left:auto; min-width:20px; height:20px; padding:0 6px; border-radius:99px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; color:#fff; flex-shrink:0; }
.pill-red    { background: var(--r500); }
.pill-orange { background: var(--o500); }
.pill-pink   { background: var(--p500); }
.nav-item.active .nav-pill { background: rgba(255,255,255,0.3); }

.sb-footer { padding: 10px 10px 14px; border-top: 1.5px solid var(--border); flex-shrink: 0; }
.nav-item.logout { color: var(--r700); }
.nav-item.logout i { color: var(--r500); }
.nav-item.logout:hover { background: var(--r100); color: var(--r700); transform: none; }

/* ══ TOPBAR ══ */
.topbar {
    position: fixed; top: 0; left: var(--sidebar-w); right: 0;
    height: var(--topbar-h);
    background: rgba(255,255,255,0.94);
    backdrop-filter: blur(12px);
    border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center;
    padding: 0 26px; z-index: 200; gap: 12px;
}
.topbar::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500)); background-size:200%; animation:shimmer 3s linear infinite; }

.tb-greeting { flex: 1; }
.tb-hello { font-family:'Quicksand',sans-serif; font-size:16px; font-weight:700; color:var(--text); }
.tb-sub { font-size:12px; color:var(--text3); font-weight:500; margin-top:1px; }

.tb-divider { width:1px; height:24px; background:var(--border2); margin:0 4px; }
.tb-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }

.icon-btn { width:36px; height:36px; border-radius:10px; background:var(--p50); border:1.5px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none; color:var(--p500); font-size:16px; transition:all var(--ease); }
.icon-btn:hover { background:var(--p100); transform:scale(1.08); }

.date-pill { display:flex; align-items:center; gap:6px; background:var(--p50); border:1.5px solid var(--border); border-radius:99px; padding:7px 16px; font-size:12.5px; font-weight:600; color:var(--text2); }
.date-pill i { color:var(--p500); }

.add-btn {
    display:flex; align-items:center; gap:7px;
    background: linear-gradient(135deg, var(--p500), var(--p400));
    color: #fff; border-radius: 99px; padding: 8px 18px;
    font-size: 13px; font-weight: 700; border: none; cursor: pointer;
    box-shadow: 0 4px 14px rgba(232,50,138,0.4);
    transition: all var(--ease);
}
.add-btn:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 8px 22px rgba(232,50,138,0.5); color:#fff; }
.add-btn i { font-size: 15px; }

/* ══ MAIN ══ */
.main { margin-left: var(--sidebar-w); padding-top: var(--topbar-h); min-height: 100vh; position: relative; z-index: 1; }
.content { padding: 28px 28px 70px; max-width: 1360px; }

@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.anim { animation: fadeUp 0.35s ease both; }

/* ══ STAT CARDS ══ */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
.stat-card {
    background: var(--white); border: 1.5px solid var(--border);
    border-radius: var(--r-lg); padding: 20px 22px;
    position: relative; overflow: hidden;
    transition: transform var(--ease), box-shadow var(--ease);
    animation: fadeUp 0.35s ease both;
}
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(232,50,138,0.13); }
.stat-stripe { position:absolute; top:0; left:0; right:0; height:4px; border-radius: var(--r-lg) var(--r-lg) 0 0; }
.stat-blob { position:absolute; right:-16px; bottom:-16px; width:72px; height:72px; border-radius:50%; opacity:0.07; }
.stat-ico { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:12px; }
.stat-label { font-size:12px; font-weight:600; color:var(--text2); margin-bottom:4px; }
.stat-val { font-family:'Quicksand',sans-serif; font-size:21px; font-weight:700; line-height:1.1; margin-bottom:3px; }
.stat-note { font-size:11px; color:var(--text3); font-weight:500; }

/* Alert banner */
.alert-maintenance {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 20px; border-radius: var(--r-md);
    border: 1.5px solid #fdba74;
    background: linear-gradient(135deg, var(--o100), #fff7ed);
    margin-bottom: 22px; animation: fadeUp 0.3s ease;
}
.alert-ico { width:40px; height:40px; border-radius:12px; background:var(--o500); color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.alert-text { flex:1; font-size:13.5px; font-weight:500; color:var(--o700); }
.alert-text b { font-weight:800; color:#c2410c; }
.alert-cta { display:flex; align-items:center; gap:5px; background:var(--o500); color:#fff; border-radius:99px; padding:7px 16px; font-size:12.5px; font-weight:700; border:none; cursor:pointer; white-space:nowrap; transition: all var(--ease-plain); }
.alert-cta:hover { background:#ea6e0d; }

/* ══ SECTION HEADER ══ */
.sec-hd { display:flex; align-items:center; justify-content:space-between; margin: 8px 0 16px; }
.sec-title { font-family:'Quicksand',sans-serif; font-size:15px; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
.sec-dot { width:8px; height:8px; border-radius:50%; background:linear-gradient(135deg,var(--p500),var(--v500)); display:inline-block; box-shadow:0 0 0 3px rgba(232,50,138,0.15); flex-shrink:0; }

/* ══ TAB PILLS ══ */
.tab-pills { display:flex; gap:6px; background:var(--p50); border:1.5px solid var(--border); border-radius:99px; padding:4px; }
.tab-pill { display:flex; align-items:center; gap:6px; padding:8px 18px; border-radius:99px; font-size:13px; font-weight:700; color:var(--text2); cursor:pointer; border:none; background:none; transition:all var(--ease-plain); }
.tab-pill i { font-size:14px; }
.tab-pill.active { background:linear-gradient(135deg,var(--p500),var(--p400)); color:#fff; box-shadow:0 4px 14px rgba(232,50,138,0.35); }
.tab-pill:not(.active):hover { background:var(--p100); color:var(--p600); }

/* ══ TABLE CARD ══ */
.tbl-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--r-xl); overflow: hidden; animation: fadeUp 0.35s ease both; }
.tbl-hd { padding: 18px 24px; border-bottom: 1.5px solid var(--border); background: linear-gradient(135deg, var(--p50), var(--white)); }

.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { padding:11px 20px; font-size:11px; font-weight:800; color:var(--text3); text-align:left; background:var(--p50); border-bottom:1.5px solid var(--border); letter-spacing:0.5px; text-transform:uppercase; }
.data-table tbody td { padding:13px 20px; border-bottom:1px solid rgba(232,50,138,0.06); font-size:14px; vertical-align:middle; }
.data-table tbody tr:last-child td { border-bottom:none; }
.data-table tbody tr { transition: background var(--ease-plain); }
.data-table tbody tr:hover td { background: var(--p50); }

/* ID tag */
.id-tag { display:inline-flex; align-items:center; background:var(--p50); color:var(--p600); border:1px solid var(--border2); border-radius:7px; padding:3px 9px; font-size:12px; font-weight:700; }

/* Kondisi badges */
.badge-kondisi { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:99px; font-size:12px; font-weight:700; }
.badge-kondisi i { font-size:10px; }
.k-baik    { background:var(--g100); color:var(--g700); }
.k-service { background:var(--a100); color:var(--a700); }
.k-perlu   { background:var(--o100); color:var(--o700); }
.k-rusak   { background:var(--r100); color:var(--r700); }

/* Jenis badges */
.badge-jenis { display:inline-flex; align-items:center; gap:4px; padding:4px 11px; border-radius:99px; font-size:11.5px; font-weight:700; }
.j-produksi  { background:var(--p50);  color:var(--p600); }
.j-peralatan { background:var(--b100); color:var(--b700); }
.j-inventaris{ background:var(--v100); color:var(--v500); }
.j-elektronik{ background:var(--a100); color:var(--a700); }

/* Action buttons */
.act-btn { display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:700; border:none; cursor:pointer; transition:all var(--ease-plain); text-decoration:none; }
.act-servis { background:var(--o100); color:var(--o700); border:1px solid #fdba74; }
.act-servis:hover { background:#fdba74; color:var(--o700); }
.act-edit { background:var(--b100); color:var(--b700); border:1px solid #93c5fd; }
.act-edit:hover { background:#93c5fd; color:var(--b700); }
.act-hapus { background:var(--r100); color:var(--r700); border:1px solid #fca5a5; }
.act-hapus:hover { background:#fca5a5; color:var(--r700); }

/* Empty state */
.empty-state { text-align:center; padding:50px 20px; color:var(--text3); }
.empty-state i { font-size:40px; color:var(--p200); margin-bottom:12px; display:block; }
.empty-state p { font-size:14px; font-weight:600; }

/* Total row */
.total-row { background:linear-gradient(135deg,var(--o100),#fff7ed); border:1.5px solid #fdba74; border-radius:var(--r-md); padding:14px 22px; display:flex; align-items:center; justify-content:space-between; margin-top:14px; }
.total-row-label { font-size:13.5px; font-weight:700; color:var(--o700); display:flex; align-items:center; gap:8px; }
.total-row-val { font-family:'Quicksand',sans-serif; font-size:20px; font-weight:700; color:#c2410c; }

/* ══ MODAL ══ */
.modal-overlay {
    display:none; position:fixed; inset:0; z-index:900;
    background:rgba(61,26,40,0.45); backdrop-filter:blur(4px);
    align-items:center; justify-content:center;
    animation: fadeOverlay 0.2s ease;
}
.modal-overlay.open { display:flex; }
@keyframes fadeOverlay { from{opacity:0} to{opacity:1} }
.modal-box {
    background:var(--white); border-radius:var(--r-xl);
    width:100%; max-width:500px; max-height:90vh; overflow-y:auto;
    box-shadow:0 24px 64px rgba(61,26,40,0.25);
    border:1.5px solid var(--border);
    animation:modalUp 0.28s cubic-bezier(0.34,1.56,0.64,1);
    margin:16px;
}
@keyframes modalUp { from{opacity:0;transform:translateY(24px) scale(0.96)} to{opacity:1;transform:none} }
.modal-header {
    padding:20px 24px; border-bottom:1.5px solid var(--border);
    display:flex; align-items:center; justify-content:space-between;
    background:linear-gradient(135deg,var(--p50),var(--v100));
    border-radius:var(--r-xl) var(--r-xl) 0 0;
}
.modal-header.servis {
    background:linear-gradient(135deg,var(--o100),#fff7ed);
}
.modal-title { font-family:'Quicksand',sans-serif; font-size:15px; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
.modal-title i { color:var(--p500); }
.modal-title.servis i { color:var(--o500); }
.modal-close { width:30px; height:30px; border-radius:8px; background:rgba(232,50,138,0.1); border:none; color:var(--p500); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; transition:all var(--ease-plain); }
.modal-close:hover { background:var(--p500); color:#fff; }
.modal-body { padding:24px; }
.modal-footer { padding:16px 24px; border-top:1.5px solid var(--border); display:flex; gap:10px; justify-content:flex-end; }

.form-group { margin-bottom:16px; }
.form-label { display:block; font-size:12px; font-weight:700; color:var(--text2); margin-bottom:6px; }
.form-control, .form-select {
    width:100%; padding:10px 14px; border-radius:var(--r-sm);
    border:1.5px solid var(--border2); background:var(--white);
    font-family:'Nunito',sans-serif; font-size:14px; color:var(--text);
    transition:border-color var(--ease-plain), box-shadow var(--ease-plain);
    outline:none;
}
.form-control:focus, .form-select:focus { border-color:var(--p400); box-shadow:0 0 0 3px rgba(232,50,138,0.1); }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
textarea.form-control { resize:vertical; min-height:80px; }

.btn-cancel { display:flex; align-items:center; gap:5px; padding:9px 18px; border-radius:99px; font-size:13px; font-weight:700; border:1.5px solid var(--border2); background:var(--p50); color:var(--text2); cursor:pointer; transition:all var(--ease-plain); }
.btn-cancel:hover { background:var(--p100); color:var(--p600); }
.btn-submit { display:flex; align-items:center; gap:6px; padding:9px 22px; border-radius:99px; font-size:13px; font-weight:700; border:none; cursor:pointer; transition:all var(--ease); color:#fff; }
.btn-submit.pink { background:linear-gradient(135deg,var(--p500),var(--p400)); box-shadow:0 4px 14px rgba(232,50,138,0.4); }
.btn-submit.pink:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(232,50,138,0.5); }
.btn-submit.orange { background:linear-gradient(135deg,var(--o500),#fb923c); box-shadow:0 4px 14px rgba(249,115,22,0.4); }
.btn-submit.orange:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(249,115,22,0.5); }

@keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(0.85);opacity:0.6} }
.pulse { animation:pulse 1.8s ease-in-out infinite; }

@media(max-width:900px) {
    .sidebar { transform:translateX(-100%); }
    .topbar  { left:0; }
    .main    { margin-left:0; }
    .stat-grid { grid-template-columns:repeat(2,1fr); }
    .form-row { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand" title="Kembali ke Dashboard">
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
            <?php if ($stok_kritis > 0): ?><span class="nav-pill pill-orange pulse"><?= $stok_kritis ?></span><?php endif; ?>
        </a>
        <a class="nav-item active" href="kelola_aset.php">
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
        <a class="nav-item" href="laporan.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Keuangan</a>
    </nav>

    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Aset &amp; Inventaris 🔧</div>
        <div class="tb-sub">Pantau kondisi, nilai, dan riwayat servis seluruh aset konveksi</div>
    </div>
    <div class="tb-actions">
        <button class="add-btn" onclick="openModal('modalTambah')">
            <i class="bi bi-plus-circle-fill"></i> Tambah Aset
        </button>
        <div class="tb-divider"></div>
        <a href="#" class="icon-btn"><i class="bi bi-bell-fill"></i></a>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- ALERT ASET RUSAK -->
    <?php if ($aset_rusak > 0): ?>
    <div class="alert-maintenance anim">
        <div class="alert-ico"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="alert-text">
            <b><?= $aset_rusak ?> aset butuh perbaikan!</b> Segera lakukan servis agar operasional tetap lancar.
        </div>
        <button class="alert-cta" onclick="switchTab('tabServis')">
            <i class="bi bi-tools"></i> Lihat Detail
        </button>
    </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stat-grid">
        <!-- Total Aset -->
        <div class="stat-card" style="animation-delay:0.05s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--p500),var(--p300))"></div>
            <div class="stat-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-building-gear"></i></div>
            <div class="stat-label">Total Aset</div>
            <div class="stat-val" style="color:var(--p600)"><?= count($data_aset) ?> Item</div>
            <div class="stat-note">Terdaftar di sistem</div>
            <div class="stat-blob" style="background:var(--p500)"></div>
        </div>
        <!-- Nilai Aset -->
        <div class="stat-card" style="animation-delay:0.1s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--g500),#86efac)"></div>
            <div class="stat-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-label">Total Nilai Aset</div>
            <div class="stat-val" style="color:var(--g700)">Rp <?= number_format($total_nilai) ?></div>
            <div class="stat-note">Estimasi nilai keseluruhan</div>
            <div class="stat-blob" style="background:var(--g500)"></div>
        </div>
        <!-- Aset Baik -->
        <div class="stat-card" style="animation-delay:0.15s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--b500),#93c5fd)"></div>
            <div class="stat-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-label">Kondisi Baik</div>
            <div class="stat-val" style="color:var(--b700)"><?= $aset_baik ?> Aset</div>
            <div class="stat-note">Beroperasi normal</div>
            <div class="stat-blob" style="background:var(--b500)"></div>
        </div>
        <!-- Biaya Servis -->
        <div class="stat-card" style="animation-delay:0.2s">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--o500),#fdba74)"></div>
            <div class="stat-ico" style="background:var(--o100);color:var(--o500)"><i class="bi bi-tools"></i></div>
            <div class="stat-label">Total Biaya Servis</div>
            <div class="stat-val" style="color:var(--o700)">Rp <?= number_format($total_servis) ?></div>
            <div class="stat-note">Akumulasi semua servis</div>
            <div class="stat-blob" style="background:var(--o500)"></div>
        </div>
    </div>

    <!-- TAB SWITCH -->
    <div class="sec-hd">
        <div class="sec-title"><span class="sec-dot"></span> Manajemen Aset 🏭</div>
        <div class="tab-pills">
            <button class="tab-pill active" id="btnTabAset" onclick="switchTab('tabAset')">
                <i class="bi bi-list-check"></i> Daftar Aset
            </button>
            <button class="tab-pill" id="btnTabServis" onclick="switchTab('tabServis')">
                <i class="bi bi-tools"></i> Riwayat Servis
                <?php if ($aset_rusak > 0): ?><span style="background:var(--o500);color:#fff;border-radius:99px;padding:1px 7px;font-size:10px;font-weight:800"><?= $aset_rusak ?></span><?php endif; ?>
            </button>
        </div>
    </div>

    <!-- TAB: DAFTAR ASET -->
    <div id="tabAset">
        <div class="tbl-card">
            <div class="tbl-hd" style="display:flex;align-items:center;justify-content:space-between">
                <div style="font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
                    <i class="bi bi-building-gear" style="color:var(--p500)"></i> Daftar Aset
                </div>
                <span style="font-size:12px;color:var(--text3);font-weight:600"><?= count($data_aset) ?> item terdaftar</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID Aset</th>
                        <th>Nama Aset</th>
                        <th>Jenis</th>
                        <th>Nilai Aset</th>
                        <th>Kondisi</th>
                        <th style="text-align:center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($data_aset)): ?>
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <i class="bi bi-building-gear"></i>
                            <p>Belum ada aset terdaftar 🌸<br><small>Klik "Tambah Aset" untuk mulai mencatat</small></p>
                        </div>
                    </td></tr>
                <?php else: foreach ($data_aset as $aset):
                    $k = $aset['KONDISI_ASET'] ?? 'Baik';
                    $klass = match(true) {
                        $k === 'Baik' => 'k-baik',
                        $k === 'Perlu Service' => 'k-service',
                        $k === 'Perlu Perbaikan' => 'k-perlu',
                        default => 'k-rusak'
                    };
                    $kico = match(true) {
                        $k === 'Baik' => 'check-circle-fill',
                        $k === 'Perlu Service' => 'clock-history',
                        $k === 'Perlu Perbaikan' => 'exclamation-triangle-fill',
                        default => 'x-circle-fill'
                    };
                    $jenis = $aset['JENIS_ASET'] ?? '-';
                    $jklass = match($jenis) {
                        'Produksi' => 'j-produksi',
                        'Peralatan' => 'j-peralatan',
                        'Inventaris' => 'j-inventaris',
                        'Elektronik' => 'j-elektronik',
                        default => 'j-inventaris'
                    };
                ?>
                <tr>
                    <td><span class="id-tag"><?= htmlspecialchars($aset['ID_ASET']) ?></span></td>
                    <td style="font-weight:700;color:var(--text)"><?= htmlspecialchars($aset['NAMA_ASET']) ?></td>
                    <td><span class="badge-jenis <?= $jklass ?>"><?= htmlspecialchars($jenis) ?></span></td>
                    <td style="font-weight:700;color:var(--g700)">Rp <?= number_format($aset['NILAI_ASET']) ?></td>
                    <td><span class="badge-kondisi <?= $klass ?>"><i class="bi bi-<?= $kico ?>"></i> <?= htmlspecialchars($k) ?></span></td>
                    <td style="text-align:center">
                        <button class="act-btn act-servis" onclick="openServisModal('<?= $aset['ID_ASET'] ?>', '<?= htmlspecialchars($aset['NAMA_ASET'], ENT_QUOTES) ?>')">
                            <i class="bi bi-tools"></i> Servis
                        </button>
                        <button class="act-btn act-edit" style="margin-left:4px" onclick="openEditModal(<?= htmlspecialchars(json_encode($aset), ENT_QUOTES) ?>)">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                        <a href="kelola_aset.php?hapus=<?= $aset['ID_ASET'] ?>"
                            class="act-btn act-hapus" style="margin-left:4px"
                            onclick="return confirm('Hapus aset <?= htmlspecialchars($aset['NAMA_ASET'], ENT_QUOTES) ?>?')">
                            <i class="bi bi-trash-fill"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: RIWAYAT SERVIS -->
    <div id="tabServis" style="display:none">
        <div class="tbl-card">
            <div class="tbl-hd" style="display:flex;align-items:center;justify-content:space-between">
                <div style="font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
                    <i class="bi bi-tools" style="color:var(--o500)"></i> Riwayat Servis &amp; Perbaikan
                </div>
                <span style="font-size:12px;color:var(--text3);font-weight:600"><?= count($riwayat_servis) ?> catatan</span>
            </div>
            <?php if (empty($riwayat_servis)): ?>
            <div class="empty-state" style="padding:50px">
                <i class="bi bi-tools"></i>
                <p>Belum ada riwayat servis 🔧<br><small>Catatan servis akan muncul di sini</small></p>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nama Aset</th>
                        <th>Jenis</th>
                        <th>Keterangan Servis</th>
                        <th>Biaya</th>
                        <th>Kondisi Setelah</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($riwayat_servis as $s):
                    $ks = $s['KONDISI_SETELAH'] ?? 'Baik';
                    $klass2 = match(true) {
                        $ks === 'Baik' => 'k-baik',
                        $ks === 'Perlu Service' => 'k-service',
                        $ks === 'Perlu Perbaikan' => 'k-perlu',
                        default => 'k-rusak'
                    };
                    $kico2 = match(true) {
                        $ks === 'Baik' => 'check-circle-fill',
                        $ks === 'Perlu Service' => 'clock-history',
                        $ks === 'Perlu Perbaikan' => 'exclamation-triangle-fill',
                        default => 'x-circle-fill'
                    };
                ?>
                <tr>
                    <td style="color:var(--text2);font-weight:600"><?= date('d M Y', strtotime($s['TANGGAL_SERVIS'])) ?></td>
                    <td style="font-weight:700"><?= htmlspecialchars($s['NAMA_ASET']) ?></td>
                    <td><span class="badge-jenis j-inventaris"><?= htmlspecialchars($s['JENIS_ASET']) ?></span></td>
                    <td style="color:var(--text2)"><?= htmlspecialchars($s['KETERANGAN']) ?></td>
                    <td style="font-weight:700;color:var(--r500)">Rp <?= number_format($s['BIAYA_SERVIS']) ?></td>
                    <td><span class="badge-kondisi <?= $klass2 ?>"><i class="bi bi-<?= $kico2 ?>"></i> <?= htmlspecialchars($ks) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="padding:16px 20px">
                <div class="total-row">
                    <div class="total-row-label"><i class="bi bi-tools"></i> Total Pengeluaran Servis &amp; Perbaikan</div>
                    <div class="total-row-val">Rp <?= number_format($total_servis) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<!-- ════ MODAL TAMBAH ASET ════ -->
<div class="modal-overlay" id="modalTambah">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-plus-circle-fill"></i> Tambah Aset Baru</div>
            <button class="modal-close" onclick="closeModal('modalTambah')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ID Aset</label>
                        <input type="text" name="id_aset" class="form-control" placeholder="AST011" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Aset</label>
                        <input type="text" name="nama_aset" class="form-control" placeholder="Mesin Jahit Juki" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jenis Aset</label>
                        <select name="jenis_aset" class="form-select" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Produksi">🏭 Produksi</option>
                            <option value="Peralatan">🔨 Peralatan</option>
                            <option value="Inventaris">📦 Inventaris</option>
                            <option value="Elektronik">💻 Elektronik</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nilai Aset (Rp)</label>
                        <input type="number" name="nilai_aset" class="form-control" placeholder="5500000" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kondisi Aset</label>
                    <select name="kondisi" class="form-select" required>
                        <option value="Baik">✅ Baik — Beroperasi normal</option>
                        <option value="Perlu Service">🕐 Perlu Service — Butuh perawatan</option>
                        <option value="Perlu Perbaikan">⚠️ Perlu Perbaikan — Ada masalah</option>
                        <option value="Rusak">❌ Rusak — Tidak bisa dipakai</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modalTambah')"><i class="bi bi-x"></i> Batal</button>
                <button type="submit" name="tambah_aset" class="btn-submit pink"><i class="bi bi-check-lg"></i> Simpan Aset</button>
            </div>
        </form>
    </div>
</div>

<!-- ════ MODAL EDIT ASET ════ -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-pencil-square"></i> Edit Aset</div>
            <button class="modal-close" onclick="closeModal('modalEdit')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id_aset" id="edit_id">
                <div class="form-group">
                    <label class="form-label">Nama Aset</label>
                    <input type="text" name="nama_aset" id="edit_nama" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jenis Aset</label>
                        <select name="jenis_aset" id="edit_jenis" class="form-select">
                            <option value="Produksi">🏭 Produksi</option>
                            <option value="Peralatan">🔨 Peralatan</option>
                            <option value="Inventaris">📦 Inventaris</option>
                            <option value="Elektronik">💻 Elektronik</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nilai Aset (Rp)</label>
                        <input type="number" name="nilai_aset" id="edit_nilai" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kondisi Aset</label>
                    <select name="kondisi" id="edit_kondisi" class="form-select">
                        <option value="Baik">✅ Baik</option>
                        <option value="Perlu Service">🕐 Perlu Service</option>
                        <option value="Perlu Perbaikan">⚠️ Perlu Perbaikan</option>
                        <option value="Rusak">❌ Rusak</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEdit')"><i class="bi bi-x"></i> Batal</button>
                <button type="submit" name="update_aset" class="btn-submit pink"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- ════ MODAL SERVIS ════ -->
<div class="modal-overlay" id="modalServis">
    <div class="modal-box">
        <div class="modal-header servis">
            <div class="modal-title servis"><i class="bi bi-tools"></i> Catat Servis: <span id="servis_nama_display" style="color:var(--o600)"></span></div>
            <button class="modal-close" onclick="closeModal('modalServis')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id_aset_servis" id="servis_id">
                <div class="form-group">
                    <label class="form-label">Keterangan Servis / Perbaikan</label>
                    <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Ganti oli mesin, service rutin 3 bulan..." required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Biaya Servis (Rp)</label>
                        <input type="number" name="biaya" class="form-control" placeholder="150000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Servis</label>
                        <input type="date" name="tanggal_servis" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kondisi Setelah Servis</label>
                    <select name="kondisi_setelah" class="form-select">
                        <option value="Baik">✅ Baik — Sudah normal kembali</option>
                        <option value="Perlu Service">🕐 Perlu Service — Butuh penanganan lanjut</option>
                        <option value="Perlu Perbaikan">⚠️ Perlu Perbaikan — Masih ada masalah</option>
                        <option value="Rusak">❌ Rusak — Tidak bisa dipakai</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modalServis')"><i class="bi bi-x"></i> Batal</button>
                <button type="submit" name="tambah_servis" class="btn-submit orange"><i class="bi bi-save"></i> Simpan Catatan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === el) el.classList.remove('open');
    });
});

// Edit modal
function openEditModal(aset) {
    document.getElementById('edit_id').value    = aset.ID_ASET;
    document.getElementById('edit_nama').value  = aset.NAMA_ASET;
    document.getElementById('edit_nilai').value = aset.NILAI_ASET;
    document.getElementById('edit_jenis').value   = aset.JENIS_ASET;
    document.getElementById('edit_kondisi').value = aset.KONDISI_ASET || 'Baik';
    openModal('modalEdit');
}

// Servis modal
function openServisModal(id, nama) {
    document.getElementById('servis_id').value = id;
    document.getElementById('servis_nama_display').textContent = nama;
    openModal('modalServis');
}

// Tab switching
function switchTab(tab) {
    document.getElementById('tabAset').style.display   = (tab === 'tabAset')   ? 'block' : 'none';
    document.getElementById('tabServis').style.display = (tab === 'tabServis') ? 'block' : 'none';
    document.getElementById('btnTabAset').className   = 'tab-pill' + (tab === 'tabAset'   ? ' active' : '');
    document.getElementById('btnTabServis').className = 'tab-pill' + (tab === 'tabServis' ? ' active' : '');
}
</script>
</body>
</html>