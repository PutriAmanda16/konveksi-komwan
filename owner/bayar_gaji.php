<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

$query = mysqli_query($koneksi, "SELECT p.*, pr.NAMA_PRODUK, pj.NAMA_PENJAHIT, pj.UPAH_PER_UNIT 
                                FROM produksi p 
                                JOIN produk pr ON p.ID_PRODUK = pr.ID_PRODUK 
                                JOIN penjahit pj ON p.ID_PENJAHIT = pj.ID_PENJAHIT 
                                LEFT JOIN penggajian g ON p.ID_PRODUKSI = g.ID_PRODUKSI 
                                WHERE g.ID_GAJI IS NULL OR g.STATUS_TERIMA = 'Belum'");

$rows = [];
while ($r = mysqli_fetch_assoc($query)) $rows[] = $r;
$total_semua = array_sum(array_map(fn($r) => $r['JUMLAH_DIPRODUKSI'] * $r['UPAH_PER_UNIT'], $rows));

// Sidebar helpers
$nama_owner = $_SESSION['user'];
$inisial     = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts   = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;
$aset_rusak  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM aset WHERE KONDISI_ASET IN ('Rusak','Perlu Perbaikan')"))['t'] ?? 0;
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$total_notif = $notif_bayar + $notif_chat + $stok_kritis + $aset_rusak;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Penggajian 💸 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --p50:  #fff0f5; --p100: #ffd6e7; --p200: #ffadd0; --p300: #ff80b8;
    --p400: #f950a0; --p500: #e8328a; --p600: #cc1a73; --p700: #a8105d;
    --v100: #f3e8ff; --v300: #d8b4fe; --v500: #a855f7;
    --g100: #dcfce7; --g500: #22c55e; --g700: #15803d;
    --a100: #fef9c3; --a500: #eab308; --a700: #854d0e;
    --b100: #dbeafe; --b500: #3b82f6; --b700: #1d4ed8;
    --r100: #fee2e2; --r500: #ef4444; --r700: #991b1b;
    --o100: #ffedd5; --o500: #f97316; --o700: #9a3412;
    --white:  #ffffff; --bg: #fff5f9; --bg2: #ffeef5;
    --text:   #3d1a28; --text2: #7d4460; --text3: #b07090;
    --border: rgba(232,50,138,0.13); --border2: rgba(232,50,138,0.24);
    --sidebar-w: 256px; --topbar-h: 64px;
    --r-sm: 10px; --r-md: 16px; --r-lg: 22px; --r-xl: 28px;
    --ease: 0.2s cubic-bezier(0.34,1.56,0.64,1);
    --ease-plain: 0.17s ease;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Nunito', sans-serif;
    background: var(--bg); color: var(--text);
    min-height: 100vh; font-size: 14.5px; line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}
body::before {
    content: ''; position: fixed; inset: 0;
    background-image: radial-gradient(circle, rgba(232,50,138,0.055) 1.5px, transparent 1.5px);
    background-size: 28px 28px; pointer-events: none; z-index: 0;
}
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: var(--p50); }
::-webkit-scrollbar-thumb { background: var(--p200); border-radius: 99px; }

/* ══ SIDEBAR ══ */
.sidebar {
    position: fixed; top: 0; left: 0;
    width: var(--sidebar-w); height: 100vh;
    background: var(--white); border-right: 1.5px solid var(--border);
    display: flex; flex-direction: column; z-index: 300; overflow: hidden;
}
.sidebar::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--p400), var(--v500), var(--p300), var(--p500));
    background-size: 200%; z-index: 1; animation: shimmer 3s linear infinite;
}
@keyframes shimmer { 0%{background-position:0%} 100%{background-position:200%} }

.sb-brand {
    display: flex; align-items: center; gap: 12px;
    padding: 0 18px; height: var(--topbar-h);
    border-bottom: 1.5px solid var(--border);
    text-decoration: none; flex-shrink: 0;
    transition: background var(--ease-plain); margin-top: 4px;
}
.sb-brand:hover { background: var(--p50); }
.brand-mark {
    width: 38px; height: 38px; border-radius: 13px;
    background: linear-gradient(135deg, var(--p500) 0%, var(--p400) 50%, var(--v500) 100%);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(232,50,138,0.4), 0 0 0 3px rgba(232,50,138,0.12);
    transition: transform var(--ease), box-shadow var(--ease);
}
.sb-brand:hover .brand-mark { transform: rotate(-8deg) scale(1.08); }
.brand-mark i { font-size: 18px; color: #fff; }
.brand-name { font-family: 'Quicksand', sans-serif; font-size: 16px; font-weight: 700; color: var(--text); white-space: nowrap; }
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
.pill-red { background: var(--r500); } .pill-orange { background: var(--o500); } .pill-pink { background: var(--p500); }
.nav-item.active .nav-pill { background: rgba(255,255,255,0.3); }
.sb-footer { padding: 10px 10px 14px; border-top: 1.5px solid var(--border); flex-shrink: 0; }
.nav-item.logout { color: var(--r700); }
.nav-item.logout i { color: var(--r500); }
.nav-item.logout:hover { background: var(--r100); color: var(--r700); transform: none; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.4} }
@keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(0.85);opacity:0.6} }
.pulse { animation: pulse 1.8s ease-in-out infinite; }

/* ══ TOPBAR ══ */
.topbar {
    position: fixed; top: 0; left: var(--sidebar-w); right: 0;
    height: var(--topbar-h); background: rgba(255,255,255,0.94);
    backdrop-filter: blur(12px); border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center; padding: 0 26px; z-index: 200; gap: 12px;
}
.topbar::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500)); background-size:200%; animation:shimmer 3s linear infinite; }
.tb-greeting { flex: 1; }
.tb-hello { font-family:'Quicksand',sans-serif; font-size:16px; font-weight:700; color:var(--text); }
.tb-sub { font-size:12px; color:var(--text3); font-weight:500; margin-top:1px; }
.tb-nav { display:flex; align-items:center; gap:2px; }
.tb-nav-item { display:flex; align-items:center; gap:5px; padding:7px 13px; border-radius:99px; font-size:13px; font-weight:600; color:var(--text2); text-decoration:none; transition:all var(--ease-plain); white-space:nowrap; border:1.5px solid transparent; }
.tb-nav-item i { font-size:14px; }
.tb-nav-item:hover { background:var(--p50); color:var(--p500); }
.tb-nav-item.active { background:var(--p50); color:var(--p500); border-color:var(--border2); }
.tb-divider { width:1px; height:24px; background:var(--border2); margin:0 4px; }
.tb-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }
.icon-btn { width:36px; height:36px; border-radius:10px; background:var(--p50); border:1.5px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none; color:var(--p500); font-size:16px; transition:all var(--ease); position:relative; }
.icon-btn:hover { background:var(--p100); transform:scale(1.08); }
.icon-btn .dot { position:absolute; top:4px; right:4px; width:8px; height:8px; border-radius:50%; background:var(--r500); border:2px solid var(--white); animation:blink 1.6s ease-in-out infinite; }
.date-pill { display:flex; align-items:center; gap:6px; background:var(--p50); border:1.5px solid var(--border); border-radius:99px; padding:7px 16px; font-size:12.5px; font-weight:600; color:var(--text2); }
.date-pill i { color:var(--p500); }

/* ══ MAIN ══ */
.main { margin-left: var(--sidebar-w); padding-top: var(--topbar-h); min-height: 100vh; position: relative; z-index: 1; }
.content { padding: 28px 28px 70px; max-width: 1200px; }

@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.anim { animation: fadeUp 0.35s ease both; }

/* ── Page header ── */
.page-header {
    background: var(--white); border: 1.5px solid var(--border);
    border-radius: var(--r-xl); padding: 24px 28px;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; position: relative; overflow: hidden;
}
.page-header::before {
    content: ''; position: absolute; right: -30px; top: -30px;
    width: 160px; height: 160px; border-radius: 50%;
    background: linear-gradient(135deg, var(--p50), var(--v100)); opacity: 0.7;
}
.ph-icon { width: 52px; height: 52px; border-radius: 16px; background: linear-gradient(135deg, var(--p500), var(--v500)); display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff; flex-shrink: 0; box-shadow: 0 6px 20px rgba(232,50,138,0.4); margin-right: 16px; }
.ph-title { font-family: 'Quicksand', sans-serif; font-size: 22px; font-weight: 700; color: var(--text); }
.ph-sub { font-size: 13px; color: var(--text3); font-weight: 500; margin-top: 2px; }
.ph-left { display: flex; align-items: center; position: relative; z-index: 1; }
.ph-right { position: relative; z-index: 1; }

/* ── Summary cards ── */
.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.sum-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--r-lg); padding: 18px 22px; position: relative; overflow: hidden; transition: transform var(--ease), box-shadow var(--ease); }
.sum-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(232,50,138,0.11); }
.sum-stripe { position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: var(--r-lg) var(--r-lg) 0 0; }
.sc-icon { width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 17px; margin-bottom: 10px; }
.sc-label { font-size: 11.5px; font-weight: 600; color: var(--text3); margin-bottom: 3px; }
.sc-val { font-family: 'Quicksand', sans-serif; font-size: 22px; font-weight: 700; }
.sc-note { font-size: 11px; color: var(--text3); margin-top: 2px; }
.sv-pink   .sum-stripe { background: linear-gradient(90deg,var(--p500),var(--p300)); } .sv-pink   .sc-icon { background:var(--p50);  color:var(--p500); } .sv-pink   .sc-val { color:var(--p600); }
.sv-amber  .sum-stripe { background: linear-gradient(90deg,var(--a500),#fcd34d); }    .sv-amber  .sc-icon { background:var(--a100); color:var(--a500); } .sv-amber  .sc-val { color:var(--a700); }
.sv-green  .sum-stripe { background: linear-gradient(90deg,var(--g500),#86efac); }    .sv-green  .sc-icon { background:var(--g100); color:var(--g500); } .sv-green  .sc-val { color:var(--g700); }

/* ── Table card ── */
.tbl-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--r-xl); overflow: hidden; }
.tbl-hd { padding: 18px 24px; border-bottom: 1.5px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, var(--p50), var(--white)); }
.tbl-title { font-family: 'Quicksand', sans-serif; font-size: 15px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 8px; }
.tbl-title i { color: var(--p500); }
.tbl-count { display: inline-flex; align-items: center; gap: 5px; background: var(--p50); border: 1.5px solid var(--border2); border-radius: 99px; padding: 4px 12px; font-size: 12px; font-weight: 700; color: var(--p600); }

.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th { padding: 11px 20px; font-size: 11.5px; font-weight: 700; color: var(--text3); text-align: left; background: var(--p50); border-bottom: 1.5px solid var(--border); white-space: nowrap; }
.data-table tbody td { padding: 14px 20px; border-bottom: 1px solid rgba(232,50,138,0.06); font-size: 14px; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr { transition: background var(--ease-plain); }
.data-table tbody tr:hover td { background: var(--p50); }

.avatar-cell { display: flex; align-items: center; gap: 10px; }
.av-circle { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--p500), var(--v500)); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; }
.av-name { font-weight: 700; color: var(--text); }
.av-id { font-size: 11px; color: var(--text3); margin-top: 1px; }

.id-tag { display: inline-flex; align-items: center; background: var(--p50); color: var(--p600); border: 1px solid var(--border2); border-radius: 7px; padding: 3px 9px; font-size: 12px; font-weight: 700; }
.id-tag.green { background: var(--g100); color: var(--g700); border-color: rgba(34,197,94,0.25); }

.qty-badge { display: inline-flex; align-items: center; gap: 4px; background: var(--b100); color: var(--b700); border-radius: 8px; padding: 4px 10px; font-size: 12.5px; font-weight: 700; }

.upah-val { font-size: 15px; font-weight: 800; color: var(--g700); }

.btn-bayar { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 99px; font-size: 13px; font-weight: 700; background: linear-gradient(135deg, var(--p500), var(--p400)); color: #fff; border: none; cursor: pointer; transition: all var(--ease); box-shadow: 0 4px 14px rgba(232,50,138,0.35); white-space: nowrap; }
.btn-bayar:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 8px 22px rgba(232,50,138,0.45); }

.empty-state { padding: 60px 24px; text-align: center; }
.empty-ico { font-size: 48px; color: var(--p200); display: block; margin-bottom: 12px; }
.empty-text { font-family: 'Quicksand', sans-serif; font-size: 16px; font-weight: 700; color: var(--text2); }
.empty-sub { font-size: 13px; color: var(--text3); margin-top: 4px; }

/* ── Modal ── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(61,26,40,0.45); backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
.modal-box { background: var(--white); border-radius: var(--r-xl); width: 100%; max-width: 460px; margin: 16px; overflow: hidden; box-shadow: 0 24px 64px rgba(61,26,40,0.25); animation: slideUp 0.25s cubic-bezier(0.34,1.56,0.64,1); }
@keyframes slideUp { from{transform:translateY(30px);opacity:0} to{transform:none;opacity:1} }
.modal-header { padding: 20px 24px 16px; border-bottom: 1.5px solid var(--border); background: linear-gradient(135deg, var(--p50), var(--white)); display: flex; align-items: center; gap: 12px; }
.modal-hico { width: 42px; height: 42px; border-radius: 13px; background: linear-gradient(135deg, var(--p500), var(--v500)); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; flex-shrink: 0; box-shadow: 0 4px 14px rgba(232,50,138,0.4); }
.modal-htitle { font-family: 'Quicksand', sans-serif; font-size: 16px; font-weight: 700; color: var(--text); }
.modal-hsub { font-size: 12px; color: var(--text3); margin-top: 2px; font-weight: 500; }
.modal-close { margin-left: auto; width: 30px; height: 30px; border-radius: 50%; background: var(--p50); border: none; color: var(--text3); font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all var(--ease-plain); }
.modal-close:hover { background: var(--r100); color: var(--r500); }
.modal-body { padding: 22px 24px; }

.info-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-radius: var(--r-md); background: var(--bg); border: 1.5px solid var(--border); margin-bottom: 10px; }
.ir-label { font-size: 12px; font-weight: 600; color: var(--text3); }
.ir-val { font-size: 14px; font-weight: 700; color: var(--text); }
.ir-val.green { color: var(--g700); }

.form-lbl { font-size: 12.5px; font-weight: 700; color: var(--text2); margin-bottom: 7px; display: block; }
.upload-zone { border: 2px dashed var(--border2); border-radius: var(--r-md); padding: 28px 20px; text-align: center; background: var(--p50); cursor: pointer; transition: all var(--ease-plain); position: relative; }
.upload-zone:hover { border-color: var(--p400); background: var(--p100); }
.upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.upload-ico { font-size: 30px; color: var(--p300); display: block; margin-bottom: 8px; }
.upload-text { font-size: 13px; font-weight: 600; color: var(--text2); }
.upload-hint { font-size: 11.5px; color: var(--text3); margin-top: 4px; }
.upload-preview { display: none; align-items: center; gap: 10px; padding: 10px 14px; background: var(--g100); border-radius: var(--r-sm); border: 1.5px solid rgba(34,197,94,0.25); margin-top: 10px; }
.upload-preview i { color: var(--g500); font-size: 18px; }
.upload-preview-name { font-size: 13px; font-weight: 600; color: var(--g700); }

.modal-footer { padding: 16px 24px; border-top: 1.5px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
.btn-cancel { padding: 9px 20px; border-radius: 99px; font-size: 13px; font-weight: 700; background: var(--p50); color: var(--text2); border: 1.5px solid var(--border); cursor: pointer; transition: all var(--ease-plain); }
.btn-cancel:hover { background: var(--p100); color: var(--text); }
.btn-submit { padding: 9px 24px; border-radius: 99px; font-size: 13px; font-weight: 700; background: linear-gradient(135deg, var(--p500), var(--p400)); color: #fff; border: none; cursor: pointer; transition: all var(--ease); box-shadow: 0 4px 14px rgba(232,50,138,0.35); display: flex; align-items: center; gap: 7px; }
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(232,50,138,0.45); }

.sec-hd { display: flex; align-items: center; justify-content: space-between; margin: 0 0 14px; }
.sec-title { font-family: 'Quicksand', sans-serif; font-size: 15px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 8px; }
.sec-dot { width: 8px; height: 8px; border-radius: 50%; background: linear-gradient(135deg,var(--p500),var(--v500)); display: inline-block; box-shadow: 0 0 0 3px rgba(232,50,138,0.15); flex-shrink: 0; }

@media (max-width: 1280px) { .summary-grid { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 900px) { .sidebar { transform: translateX(-100%); } .topbar { left: 0; } .main { margin-left: 0; } .summary-grid { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
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
        <a class="nav-item active" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
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
        <div class="tb-hello">Penggajian Penjahit 💸</div>
        <div class="tb-sub">Kelola pembayaran upah penjahit kamu di sini</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi</a>
        <a class="tb-nav-item" href="laporan.php"><i class="bi bi-bar-chart-line"></i> Laporan</a>
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
            <div class="ph-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="ph-title">Daftar Gaji Penjahit</div>
                <div class="ph-sub">Tugas produksi selesai yang belum dibayar upahnya 💪</div>
            </div>
        </div>
        <div class="ph-right">
            <?php if (!empty($rows)): ?>
            <div style="text-align:right">
                <div style="font-size:11.5px;font-weight:600;color:var(--text3);margin-bottom:2px">Total yang harus dibayar</div>
                <div style="font-family:'Quicksand',sans-serif;font-size:26px;font-weight:700;color:var(--p600)">Rp <?= number_format($total_semua) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary cards -->
    <?php if (!empty($rows)):
        $jumlah_penjahit = count(array_unique(array_column($rows, 'ID_PENJAHIT')));
        $jumlah_tugas    = count($rows);
    ?>
    <div class="summary-grid" style="margin-bottom:24px">
        <div class="sum-card sv-pink">
            <div class="sum-stripe"></div>
            <div class="sc-icon"><i class="bi bi-people-fill"></i></div>
            <div class="sc-label">Penjahit Belum Dibayar</div>
            <div class="sc-val"><?= $jumlah_penjahit ?> orang</div>
            <div class="sc-note">Menunggu pembayaran</div>
        </div>
        <div class="sum-card sv-amber">
            <div class="sum-stripe"></div>
            <div class="sc-icon"><i class="bi bi-list-task"></i></div>
            <div class="sc-label">Total Tugas</div>
            <div class="sc-val"><?= $jumlah_tugas ?> tugas</div>
            <div class="sc-note">Produksi selesai belum lunas</div>
        </div>
        <div class="sum-card sv-green">
            <div class="sum-stripe"></div>
            <div class="sc-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="sc-label">Total Upah</div>
            <div class="sc-val">Rp <?= number_format($total_semua) ?></div>
            <div class="sc-note">Harus dibayarkan segera</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="tbl-card">
        <div class="tbl-hd">
            <div class="tbl-title"><i class="bi bi-scissors"></i> Daftar Tagihan Upah</div>
            <?php if (!empty($rows)): ?>
            <span class="tbl-count"><i class="bi bi-clock"></i> <?= count($rows) ?> menunggu</span>
            <?php endif; ?>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Penjahit</th>
                    <th>Produk</th>
                    <th>Qty</th>
                    <th>Upah/Pcs</th>
                    <th>Total Upah</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($rows)): foreach ($rows as $row):
                $total_upah = $row['JUMLAH_DIPRODUKSI'] * $row['UPAH_PER_UNIT'];
                $initials = strtoupper(substr($row['NAMA_PENJAHIT'], 0, 1));
                if (strpos($row['NAMA_PENJAHIT'], ' ') !== false) {
                    $np = explode(' ', $row['NAMA_PENJAHIT']);
                    $initials = strtoupper(substr($np[0],0,1).substr($np[1],0,1));
                }
            ?>
            <tr>
                <td>
                    <div class="avatar-cell">
                        <div class="av-circle"><?= $initials ?></div>
                        <div>
                            <div class="av-name"><?= htmlspecialchars($row['NAMA_PENJAHIT']) ?></div>
                            <div class="av-id"><?= htmlspecialchars($row['ID_PENJAHIT']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="id-tag"><?= htmlspecialchars($row['NAMA_PRODUK']) ?></span>
                </td>
                <td>
                    <span class="qty-badge"><i class="bi bi-box"></i><?= $row['JUMLAH_DIPRODUKSI'] ?> Pcs</span>
                </td>
                <td style="color:var(--text2);font-weight:600">Rp <?= number_format($row['UPAH_PER_UNIT']) ?></td>
                <td><span class="upah-val">Rp <?= number_format($total_upah) ?></span></td>
                <td>
                    <button class="btn-bayar" onclick="openModal('modal-<?= $row['ID_PRODUKSI'] ?>')">
                        <i class="bi bi-send-fill"></i> Bayar Gaji
                    </button>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <i class="bi bi-check-circle empty-ico" style="color:var(--g300)"></i>
                        <div class="empty-text">Semua gaji sudah dibayar! 🎉</div>
                        <div class="empty-sub">Tidak ada tagihan upah yang menunggu saat ini</div>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</main>

<!-- ════ MODALS ════ -->
<?php foreach ($rows as $row):
    $total_upah = $row['JUMLAH_DIPRODUKSI'] * $row['UPAH_PER_UNIT'];
    $initials = strtoupper(substr($row['NAMA_PENJAHIT'], 0, 1));
    if (strpos($row['NAMA_PENJAHIT'], ' ') !== false) {
        $np = explode(' ', $row['NAMA_PENJAHIT']);
        $initials = strtoupper(substr($np[0],0,1).substr($np[1],0,1));
    }
?>
<div class="modal-overlay" id="modal-<?= $row['ID_PRODUKSI'] ?>">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-hico"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="modal-htitle">Bayar Gaji Penjahit</div>
                <div class="modal-hsub"><?= htmlspecialchars($row['NAMA_PENJAHIT']) ?> · <?= htmlspecialchars($row['NAMA_PRODUK']) ?></div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-<?= $row['ID_PRODUKSI'] ?>')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="proses_simpan_gaji.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="id_pjt" value="<?= $row['ID_PENJAHIT'] ?>">
                <input type="hidden" name="id_prod" value="<?= $row['ID_PRODUKSI'] ?>">
                <input type="hidden" name="total" value="<?= $total_upah ?>">

                <div class="info-row">
                    <span class="ir-label"><i class="bi bi-person-fill" style="color:var(--p400)"></i> Penjahit</span>
                    <span class="ir-val"><?= htmlspecialchars($row['NAMA_PENJAHIT']) ?></span>
                </div>
                <div class="info-row">
                    <span class="ir-label"><i class="bi bi-box-seam" style="color:var(--b500)"></i> Produk</span>
                    <span class="ir-val"><?= htmlspecialchars($row['NAMA_PRODUK']) ?></span>
                </div>
                <div class="info-row">
                    <span class="ir-label"><i class="bi bi-layers" style="color:var(--a500)"></i> Jumlah Produksi</span>
                    <span class="ir-val"><?= $row['JUMLAH_DIPRODUKSI'] ?> Pcs</span>
                </div>
                <div class="info-row" style="margin-bottom:18px">
                    <span class="ir-label"><i class="bi bi-cash-coin" style="color:var(--g500)"></i> Total Upah</span>
                    <span class="ir-val green">Rp <?= number_format($total_upah) ?></span>
                </div>

                <label class="form-lbl"><i class="bi bi-paperclip"></i> Upload Nota / Bukti Transfer</label>
                <div class="upload-zone" id="uz-<?= $row['ID_PRODUKSI'] ?>">
                    <input type="file" name="bukti_gaji" accept="image/*,.pdf" required
                        onchange="previewFile(this, '<?= $row['ID_PRODUKSI'] ?>')">
                    <i class="bi bi-cloud-upload upload-ico"></i>
                    <div class="upload-text">Klik atau drag file ke sini</div>
                    <div class="upload-hint">Format: JPG, PNG, PDF · Maks 5MB</div>
                </div>
                <div class="upload-preview" id="prev-<?= $row['ID_PRODUKSI'] ?>">
                    <i class="bi bi-file-earmark-check-fill"></i>
                    <span class="upload-preview-name" id="prev-name-<?= $row['ID_PRODUKSI'] ?>"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-<?= $row['ID_PRODUKSI'] ?>')">Batal</button>
                <button type="submit" class="btn-submit"><i class="bi bi-send-fill"></i> Kirim Pembayaran</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(el => closeModal(el.id));
    }
});
function previewFile(input, id) {
    const prev = document.getElementById('prev-' + id);
    const name = document.getElementById('prev-name-' + id);
    if (input.files && input.files[0]) {
        name.textContent = input.files[0].name;
        prev.style.display = 'flex';
    }
}
</script>
</body>
</html>