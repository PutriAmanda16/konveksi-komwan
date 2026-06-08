<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

// Sidebar helpers
$nama_owner  = $_SESSION['user'];
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

$flash = '';

// 1. TAMBAH
if (isset($_POST['tambah'])) {
    $id       = mysqli_real_escape_string($koneksi, trim($_POST['id_bahan']));
    $nama     = mysqli_real_escape_string($koneksi, trim($_POST['nama_bahan']));
    $stok     = (int)$_POST['stok'];
    $harga    = (int)$_POST['harga'];
    $id_sup   = mysqli_real_escape_string($koneksi, $_POST['id_supplier']);
    $tgl_beli = mysqli_real_escape_string($koneksi, $_POST['tgl_beli']);
    $total    = $stok * $harga;

    $q1 = "INSERT INTO bahan_baku (ID_BAHAN, NAMA_BAHAN, JUMLAH_STOK, HARGA_SATUAN, ID_SUPPLIER)
           VALUES ('$id','$nama','$stok','$harga','$id_sup')";
    if (mysqli_query($koneksi, $q1)) {
        $last  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT ID_PEMBELIAN FROM pembelian_bahan ORDER BY ID_PEMBELIAN DESC LIMIT 1"));
        $num   = $last ? (int)substr($last['ID_PEMBELIAN'], 2) + 1 : 1;
        $id_pb = 'PB' . str_pad($num, 2, '0', STR_PAD_LEFT);
        mysqli_query($koneksi, "INSERT INTO pembelian_bahan (ID_PEMBELIAN,ID_SUPPLIER,ID_OWNER,TANGGAL_BELI,TOTAL_BIAYA,STATUS_BAYAR)
            VALUES ('$id_pb','$id_sup','{$_SESSION['user']}','$tgl_beli','$total','Belum Dibayar')");
        mysqli_query($koneksi, "INSERT INTO detail_pembelian (ID_PEMBELIAN,ID_BAHAN,JUMLAH,HARGA_SATUAN)
            VALUES ('$id_pb','$id','$stok','$harga')");
        $flash = 'ok:Bahan berhasil ditambahkan & pembelian tercatat! 🎉';
    } else {
        $flash = 'error:Gagal: ' . mysqli_error($koneksi);
    }
}

// 2. UPDATE
if (isset($_POST['update'])) {
    $id     = mysqli_real_escape_string($koneksi, $_POST['id_bahan']);
    $nama   = mysqli_real_escape_string($koneksi, trim($_POST['nama_bahan']));
    $stok   = (int)$_POST['stok'];
    $harga  = (int)$_POST['harga'];
    $id_sup = mysqli_real_escape_string($koneksi, $_POST['id_supplier']);
    $q = "UPDATE bahan_baku SET NAMA_BAHAN='$nama',JUMLAH_STOK='$stok',HARGA_SATUAN='$harga',ID_SUPPLIER='$id_sup' WHERE ID_BAHAN='$id'";
    if (mysqli_query($koneksi, $q)) $flash = 'ok:Data berhasil diperbarui! ✨';
    else $flash = 'error:Gagal memperbarui data.';
}

// 3. HAPUS
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    mysqli_query($koneksi, "DELETE FROM bahan_baku WHERE ID_BAHAN='$id'");
    header("Location: kelola_bahan.php?deleted=1"); exit;
}
if (isset($_GET['deleted'])) $flash = 'ok:Bahan baku berhasil dihapus.';

// Fetch suppliers
$suppliers = [];
$res_sup = mysqli_query($koneksi, "SELECT ID_SUPPLIER, NAMA_SUPPLIER FROM supplier ORDER BY ID_SUPPLIER ASC");
while ($s = mysqli_fetch_assoc($res_sup)) $suppliers[] = $s;

// Fetch bahan baku
$rows = [];
$q = mysqli_query($koneksi,
    "SELECT *
     FROM bahan_baku
     ORDER BY ID_BAHAN ASC");

while ($r = mysqli_fetch_assoc($q)) {
    $rows[] = $r;
}

$total_bahan  = count($rows);
$total_kritis = count(array_filter($rows, fn($r) => $r['JUMLAH_STOK'] <= 25 && $r['JUMLAH_STOK'] > 0));
$total_habis  = count(array_filter($rows, fn($r) => $r['JUMLAH_STOK'] == 0));
$total_aman   = count(array_filter($rows, fn($r) => $r['JUMLAH_STOK'] > 25));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Bahan Baku 🧶 | Konveksi Apps</title>
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
.content{padding:28px 28px 70px;max-width:1300px}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.anim{animation:fadeUp 0.35s ease both}

/* Toast */
.toast-wrap{position:fixed;top:80px;right:24px;z-index:9999}
.toast-msg{display:flex;align-items:center;gap:10px;padding:13px 20px;border-radius:var(--r-lg);font-size:13.5px;font-weight:700;box-shadow:0 8px 28px rgba(0,0,0,0.12);animation:slideIn 0.3s cubic-bezier(0.34,1.56,0.64,1);border:1.5px solid;transition:opacity 0.4s}
@keyframes slideIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:none}}
.toast-ok{background:var(--g100);color:var(--g700);border-color:rgba(34,197,94,0.3)}
.toast-err{background:var(--r100);color:var(--r700);border-color:rgba(239,68,68,0.3)}

/* Page header */
.page-header{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);padding:22px 28px;display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;overflow:hidden}
.page-header::before{content:'';position:absolute;right:-30px;top:-30px;width:160px;height:160px;border-radius:50%;background:linear-gradient(135deg,var(--p50),var(--v100));opacity:0.7}
.ph-left{display:flex;align-items:center;gap:14px;position:relative;z-index:1}
.ph-icon{width:50px;height:50px;border-radius:15px;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;box-shadow:0 6px 20px rgba(232,50,138,0.4)}
.ph-title{font-family:'Quicksand',sans-serif;font-size:21px;font-weight:700;color:var(--text)}
.ph-sub{font-size:13px;color:var(--text3);font-weight:500;margin-top:2px}
.ph-right{position:relative;z-index:1}
.btn-tambah{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:99px;font-size:13.5px;font-weight:700;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border:none;cursor:pointer;transition:all var(--ease);box-shadow:0 4px 16px rgba(232,50,138,0.4)}
.btn-tambah:hover{transform:translateY(-2px) scale(1.03);box-shadow:0 8px 24px rgba(232,50,138,0.5);color:#fff}

/* Stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:16px 20px;display:flex;align-items:center;gap:14px;transition:transform var(--ease),box-shadow var(--ease);position:relative;overflow:hidden}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(232,50,138,0.1)}
.stat-stripe{position:absolute;top:0;left:0;right:0;height:3px;border-radius:var(--r-lg) var(--r-lg) 0 0}
.sc-ico{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
.sc-num{font-family:'Quicksand',sans-serif;font-size:24px;font-weight:700;line-height:1}
.sc-lbl{font-size:11.5px;font-weight:600;color:var(--text3);margin-top:2px}
.sv-blue   .stat-stripe{background:linear-gradient(90deg,var(--b500),#93c5fd)} .sv-blue   .sc-ico{background:var(--b100);color:var(--b500)} .sv-blue   .sc-num{color:var(--b700)}
.sv-green  .stat-stripe{background:linear-gradient(90deg,var(--g500),#86efac)} .sv-green  .sc-ico{background:var(--g100);color:var(--g500)} .sv-green  .sc-num{color:var(--g700)}
.sv-orange .stat-stripe{background:linear-gradient(90deg,var(--o500),#fdba74)} .sv-orange .sc-ico{background:var(--o100);color:var(--o500)} .sv-orange .sc-num{color:var(--o700)}
.sv-red    .stat-stripe{background:linear-gradient(90deg,var(--r500),#fca5a5)} .sv-red    .sc-ico{background:var(--r100);color:var(--r500)} .sv-red    .sc-num{color:var(--r700)}

/* Search */
.search-row{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.search-box{display:flex;align-items:center;gap:10px;background:var(--white);border:1.5px solid var(--border);border-radius:99px;padding:9px 18px;flex:1;max-width:360px;transition:border-color var(--ease-plain)}
.search-box:focus-within{border-color:var(--p400);box-shadow:0 0 0 3px rgba(232,50,138,0.1)}
.search-box i{color:var(--p400);font-size:15px;flex-shrink:0}
.search-box input{border:none;outline:none;background:none;font-family:'Nunito',sans-serif;font-size:14px;color:var(--text);width:100%}
.search-box input::placeholder{color:var(--text3)}

/* Table */
.tbl-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden}
.tbl-hd{padding:18px 24px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--p50),var(--white))}
.tbl-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.tbl-title i{color:var(--p500)}
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{padding:11px 20px;font-size:11.5px;font-weight:700;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border);white-space:nowrap}
.data-table tbody td{padding:14px 20px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:14px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr{transition:background var(--ease-plain)}
.data-table tbody tr:hover td{background:var(--p50)}

.id-tag{display:inline-flex;align-items:center;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:7px;padding:3px 9px;font-size:12px;font-weight:700}
.sup-tag{display:inline-flex;align-items:center;gap:5px;background:var(--b100);color:var(--b700);border-radius:8px;padding:4px 11px;font-size:12.5px;font-weight:700}
.stok-num{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700}
.badge-aman{display:inline-flex;align-items:center;gap:4px;background:var(--g100);color:var(--g700);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700}
.badge-kritis{display:inline-flex;align-items:center;gap:4px;background:var(--o100);color:var(--o700);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700}
.badge-habis{display:inline-flex;align-items:center;gap:4px;background:#1f2937;color:#f9fafb;border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700}

/* Stok bar */
.stok-bar-wrap{margin-top:4px;height:4px;background:rgba(232,50,138,0.1);border-radius:99px;width:80px;overflow:hidden}
.stok-bar{height:100%;border-radius:99px;transition:width 0.5s ease}

.btn-edit{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:var(--p50);color:var(--p600);border:1.5px solid var(--border2);cursor:pointer;transition:all var(--ease-plain);font-size:15px}
.btn-edit:hover{background:var(--p500);color:#fff;border-color:var(--p500)}
.btn-hapus{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:var(--r100);color:var(--r700);border:1.5px solid rgba(239,68,68,0.2);cursor:pointer;transition:all var(--ease-plain);font-size:15px;text-decoration:none}
.btn-hapus:hover{background:var(--r500);color:#fff;border-color:var(--r500)}
.empty-state{padding:60px 24px;text-align:center}
.empty-ico{font-size:48px;color:var(--p200);display:block;margin-bottom:12px}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(61,26,40,0.45);backdrop-filter:blur(6px);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.open{display:flex;animation:fadeIn 0.2s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-box{background:var(--white);border-radius:var(--r-xl);width:100%;max-width:480px;margin:16px;overflow:hidden;box-shadow:0 24px 64px rgba(61,26,40,0.25);animation:slideUp 0.25s cubic-bezier(0.34,1.56,0.64,1);max-height:90vh;display:flex;flex-direction:column}
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:none;opacity:1}}
.modal-header{padding:20px 24px 16px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;gap:12px;flex-shrink:0}
.modal-hico{width:42px;height:42px;border-radius:13px;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4)}
.modal-htitle{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text)}
.modal-hsub{font-size:12px;color:var(--text3);margin-top:2px;font-weight:500}
.modal-close{margin-left:auto;width:30px;height:30px;border-radius:50%;background:var(--p50);border:none;color:var(--text3);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all var(--ease-plain)}
.modal-close:hover{background:var(--r100);color:var(--r500)}
.modal-scroll{overflow-y:auto;flex:1}
.modal-body{padding:22px 24px}
.form-group{margin-bottom:16px}
.form-lbl{font-size:12.5px;font-weight:700;color:var(--text2);margin-bottom:6px;display:block}
.form-lbl span{color:var(--r500)}
.form-ctrl{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-family:'Nunito',sans-serif;font-size:14px;color:var(--text);background:var(--white);transition:border-color var(--ease-plain),box-shadow var(--ease-plain);outline:none}
.form-ctrl:focus{border-color:var(--p400);box-shadow:0 0 0 3px rgba(232,50,138,0.1)}
.form-ctrl::placeholder{color:var(--text3)}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.total-box{background:linear-gradient(135deg,var(--g100),#bbf7d0);border:1.5px solid rgba(34,197,94,0.25);border-radius:var(--r-md);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;margin-top:4px}
.total-lbl{font-size:12px;font-weight:700;color:var(--g700)}
.total-val{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--g700)}
.modal-footer-custom{padding:16px 24px;border-top:1.5px solid var(--border);display:flex;justify-content:flex-end;gap:10px;flex-shrink:0}
.btn-cancel{padding:9px 20px;border-radius:99px;font-size:13px;font-weight:700;background:var(--p50);color:var(--text2);border:1.5px solid var(--border);cursor:pointer;transition:all var(--ease-plain)}
.btn-cancel:hover{background:var(--p100)}
.btn-submit{padding:9px 24px;border-radius:99px;font-size:13px;font-weight:700;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border:none;cursor:pointer;transition:all var(--ease);box-shadow:0 4px 14px rgba(232,50,138,0.35);display:flex;align-items:center;gap:7px}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(232,50,138,0.45)}

/* Konfirmasi hapus */
.confirm-overlay{display:none;position:fixed;inset:0;background:rgba(61,26,40,0.5);backdrop-filter:blur(6px);z-index:2000;align-items:center;justify-content:center}
.confirm-overlay.open{display:flex;animation:fadeIn 0.15s ease}
.confirm-box{background:var(--white);border-radius:var(--r-xl);padding:28px;max-width:340px;width:calc(100% - 32px);box-shadow:0 20px 60px rgba(61,26,40,0.25);animation:slideUp 0.2s cubic-bezier(0.34,1.56,0.64,1);text-align:center}
.confirm-ico{width:56px;height:56px;border-radius:50%;background:var(--r100);display:flex;align-items:center;justify-content:center;font-size:26px;color:var(--r500);margin:0 auto 16px}
.confirm-title{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px}
.confirm-sub{font-size:13px;color:var(--text3);margin-bottom:22px}
.confirm-actions{display:flex;gap:10px}
.btn-confirm-cancel{flex:1;padding:10px;border-radius:99px;font-size:13px;font-weight:700;background:var(--p50);color:var(--text2);border:1.5px solid var(--border);cursor:pointer;transition:all var(--ease-plain)}
.btn-confirm-cancel:hover{background:var(--p100)}
.btn-confirm-del{flex:1;padding:10px;border-radius:99px;font-size:13px;font-weight:700;background:linear-gradient(135deg,var(--r500),#dc2626);color:#fff;border:none;cursor:pointer;transition:all var(--ease);box-shadow:0 4px 14px rgba(239,68,68,0.3);text-decoration:none;display:flex;align-items:center;justify-content:center}
.btn-confirm-del:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(239,68,68,0.4);color:#fff}

@media(max-width:1100px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.sidebar{transform:translateX(-100%)}.topbar{left:0}.main{margin-left:0}}
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
        <a class="nav-item active" href="kelola_bahan.php">
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
        <a class="nav-item" href="laporan.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Keuangan</a>
    </nav>
    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Kelola Bahan Baku 🧶</div>
        <div class="tb-sub">Monitor stok & catat pembelian bahan dari supplier</div>
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

<!-- TOAST -->
<?php if ($flash): [$type, $msg] = explode(':', $flash, 2); ?>
<div class="toast-wrap" id="toastWrap">
    <div class="toast-msg <?= $type === 'ok' ? 'toast-ok' : 'toast-err' ?>">
        <i class="bi <?= $type === 'ok' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toastWrap');if(t){t.style.opacity='0';setTimeout(()=>t.remove(),400)}},3200)</script>
<?php endif; ?>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content anim">

    <!-- Page header -->
    <div class="page-header">
        <div class="ph-left">
            <div class="ph-icon"><i class="bi bi-basket"></i></div>
            <div>
                <div class="ph-title">Stok Bahan Baku</div>
                <div class="ph-sub">Setiap penambahan otomatis tercatat sebagai pembelian ke supplier</div>
            </div>
        </div>
        <div class="ph-right">
            <button class="btn-tambah" onclick="openModal('modal-tambah')">
                <i class="bi bi-plus-circle-fill"></i> Tambah Stok
            </button>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
        <div class="stat-card sv-blue">
            <div class="stat-stripe"></div>
            <div class="sc-ico"><i class="bi bi-basket-fill"></i></div>
            <div>
                <div class="sc-num"><?= $total_bahan ?></div>
                <div class="sc-lbl">Total Jenis Bahan</div>
            </div>
        </div>
        <div class="stat-card sv-green">
            <div class="stat-stripe"></div>
            <div class="sc-ico"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="sc-num"><?= $total_aman ?></div>
                <div class="sc-lbl">Stok Aman</div>
            </div>
        </div>
        <div class="stat-card sv-orange">
            <div class="stat-stripe"></div>
            <div class="sc-ico"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div>
                <div class="sc-num"><?= $total_kritis ?></div>
                <div class="sc-lbl">Hampir Habis</div>
            </div>
        </div>
        <div class="stat-card sv-red">
            <div class="stat-stripe"></div>
            <div class="sc-ico"><i class="bi bi-x-circle-fill"></i></div>
            <div>
                <div class="sc-num"><?= $total_habis ?></div>
                <div class="sc-lbl">Stok Habis</div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="search-row">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Cari nama bahan, supplier, ID..." oninput="filterBahan()">
        </div>
    </div>

    <!-- Table -->
    <div class="tbl-card">
        <div class="tbl-hd">
            <div class="tbl-title"><i class="bi bi-basket-fill"></i> Daftar Bahan Baku</div>
            <span style="font-size:12px;font-weight:600;color:var(--text3)"><?= $total_bahan ?> bahan terdaftar</span>
        </div>
        <table class="data-table" id="bahanTable">
            <thead>
                <tr>
                    <th>ID Bahan</th>
                    <th>Nama Bahan</th>
                    <th>Supplier</th>
                    <th>Stok</th>
                    <th>Harga Satuan</th>
                    <th>Status</th>
                    <th style="text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($rows)): foreach ($rows as $row):
                $stok    = (int)$row['JUMLAH_STOK'];
                $pct     = min(100, ($stok / 200) * 100);
                $bar_clr = $stok == 0 ? '#374151' : ($stok <= 25 ? 'var(--o500)' : 'var(--g500)');
            ?>
            <tr data-search="<?= strtolower($row['ID_BAHAN'].' '.$row['NAMA_BAHAN'].' '.($row['NAMA_SUPPLIER']??'')) ?>">
                <td><span class="id-tag"><?= htmlspecialchars($row['ID_BAHAN']) ?></span></td>
                <td style="font-weight:700"><?= htmlspecialchars($row['NAMA_BAHAN']) ?></td>
                <td>
                    <?php if (!empty($row['NAMA_SUPPLIER'])): ?>
                    <span class="sup-tag"><i class="bi bi-truck"></i><?= htmlspecialchars($row['NAMA_SUPPLIER']) ?></span>
                    <?php else: ?>
                    <span style="color:var(--text3);font-size:13px">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="stok-num" style="color:<?= $stok == 0 ? '#374151' : ($stok <= 25 ? 'var(--o700)' : 'var(--g700)') ?>"><?= number_format($stok) ?></div>
                    <div class="stok-bar-wrap"><div class="stok-bar" style="width:<?= $pct ?>%;background:<?= $bar_clr ?>"></div></div>
                </td>
                <td style="font-weight:700;color:var(--p600)">Rp <?= number_format($row['HARGA_SATUAN']) ?></td>
                <td>
                    <?php if ($stok == 0): ?>
                        <span class="badge-habis"><i class="bi bi-x-circle-fill"></i> Habis</span>
                    <?php elseif ($stok <= 25): ?>
                        <span class="badge-kritis"><i class="bi bi-exclamation-triangle-fill"></i> Hampir Habis</span>
                    <?php else: ?>
                        <span class="badge-aman"><i class="bi bi-check-circle-fill"></i> Aman</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <div style="display:flex;gap:8px;justify-content:center">
                        <button class="btn-edit" onclick="openModal('modal-edit-<?= $row['ID_BAHAN'] ?>')" title="Edit">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn-hapus" onclick="konfirmasiHapus('<?= $row['ID_BAHAN'] ?>','<?= htmlspecialchars(addslashes($row['NAMA_BAHAN'])) ?>')" title="Hapus">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7">
                <div class="empty-state">
                    <i class="bi bi-basket2 empty-ico"></i>
                    <div style="font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text2)">Belum ada bahan baku 🧶</div>
                    <div style="font-size:13px;color:var(--text3);margin-top:5px">Tambah bahan pertama kamu!</div>
                </div>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</main>

<!-- ════ MODAL TAMBAH ════ -->
<div class="modal-overlay" id="modal-tambah">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-hico"><i class="bi bi-plus-circle-fill"></i></div>
            <div>
                <div class="modal-htitle">Tambah Bahan Baru</div>
                <div class="modal-hsub">Pembelian akan otomatis tercatat ke supplier</div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-tambah')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="" method="POST">
        <div class="modal-scroll">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-lbl">ID Bahan <span>*</span></label>
                <input type="text" name="id_bahan" class="form-ctrl" placeholder="cth: BHN11" required>
            </div>
            <div class="form-group">
                <label class="form-lbl">Nama Bahan Baku <span>*</span></label>
                <input type="text" name="nama_bahan" class="form-ctrl" placeholder="cth: Kain Katun Drill" required>
            </div>
            <div class="form-group">
                <label class="form-lbl"><i class="bi bi-truck" style="color:var(--b500)"></i> Supplier Asal <span>*</span></label>
                <select name="id_supplier" class="form-ctrl" required>
                    <option value="">-- Pilih Supplier --</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['ID_SUPPLIER'] ?>"><?= $s['ID_SUPPLIER'] ?> — <?= htmlspecialchars($s['NAMA_SUPPLIER']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-lbl"><i class="bi bi-calendar-heart" style="color:var(--p500)"></i> Tanggal Pembelian <span>*</span></label>
                <input type="date" name="tgl_beli" class="form-ctrl" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="two-col">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-lbl">Jumlah Stok (qty) <span>*</span></label>
                    <input type="number" name="stok" id="stok_tambah" class="form-ctrl" min="1" placeholder="0" required oninput="hitungTotal('tambah')">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-lbl">Harga Satuan (Rp) <span>*</span></label>
                    <input type="number" name="harga" id="harga_tambah" class="form-ctrl" min="0" placeholder="0" required oninput="hitungTotal('tambah')">
                </div>
            </div>
            <div class="total-box" style="margin-top:14px">
                <span class="total-lbl"><i class="bi bi-calculator-fill"></i> Total Pembelian</span>
                <span class="total-val" id="totalBeli_tambah">Rp 0</span>
            </div>
        </div>
        </div>
        <div class="modal-footer-custom">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-tambah')">Batal</button>
            <button type="submit" name="tambah" class="btn-submit"><i class="bi bi-save-fill"></i> Simpan & Catat</button>
        </div>
        </form>
    </div>
</div>

<!-- ════ MODAL EDIT ════ -->
<?php foreach ($rows as $b): ?>
<div class="modal-overlay" id="modal-edit-<?= $b['ID_BAHAN'] ?>">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-hico" style="background:linear-gradient(135deg,var(--b500),var(--v500))"><i class="bi bi-pencil-square"></i></div>
            <div>
                <div class="modal-htitle">Edit Bahan</div>
                <div class="modal-hsub"><?= htmlspecialchars($b['NAMA_BAHAN']) ?> · <?= htmlspecialchars($b['ID_BAHAN']) ?></div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-edit-<?= $b['ID_BAHAN'] ?>')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="" method="POST">
        <input type="hidden" name="id_bahan" value="<?= $b['ID_BAHAN'] ?>">
        <div class="modal-scroll">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-lbl">Nama Bahan <span>*</span></label>
                <input type="text" name="nama_bahan" class="form-ctrl" value="<?= htmlspecialchars($b['NAMA_BAHAN']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-lbl"><i class="bi bi-truck" style="color:var(--b500)"></i> Supplier</label>
                <select name="id_supplier" class="form-ctrl">
                    <option value="">— Tidak ada / Tidak diketahui —</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['ID_SUPPLIER'] ?>" <?= ($b['ID_SUPPLIER'] == $s['ID_SUPPLIER']) ? 'selected' : '' ?>>
                        <?= $s['ID_SUPPLIER'] ?> — <?= htmlspecialchars($s['NAMA_SUPPLIER']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="two-col">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-lbl">Stok Baru <span>*</span></label>
                    <input type="number" name="stok" id="stok_edit_<?= $b['ID_BAHAN'] ?>" class="form-ctrl" value="<?= $b['JUMLAH_STOK'] ?>" required oninput="hitungTotal('edit_<?= $b['ID_BAHAN'] ?>')">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-lbl">Harga Satuan (Rp) <span>*</span></label>
                    <input type="number" name="harga" id="harga_edit_<?= $b['ID_BAHAN'] ?>" class="form-ctrl" value="<?= $b['HARGA_SATUAN'] ?>" required oninput="hitungTotal('edit_<?= $b['ID_BAHAN'] ?>')">
                </div>
            </div>
            <div class="total-box" style="margin-top:14px">
                <span class="total-lbl"><i class="bi bi-calculator-fill"></i> Estimasi Nilai Stok</span>
                <span class="total-val" id="totalBeli_edit_<?= $b['ID_BAHAN'] ?>">Rp <?= number_format($b['JUMLAH_STOK'] * $b['HARGA_SATUAN']) ?></span>
            </div>
        </div>
        </div>
        <div class="modal-footer-custom">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-<?= $b['ID_BAHAN'] ?>')">Batal</button>
            <button type="submit" name="update" class="btn-submit"><i class="bi bi-check-circle-fill"></i> Simpan Perubahan</button>
        </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- ════ KONFIRMASI HAPUS ════ -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-ico"><i class="bi bi-trash-fill"></i></div>
        <div class="confirm-title">Hapus Bahan?</div>
        <div class="confirm-sub" id="confirmSub">Bahan ini akan dihapus permanen.</div>
        <div class="confirm-actions">
            <button class="btn-confirm-cancel" onclick="closeConfirm()">Batal</button>
            <a class="btn-confirm-del" id="confirmLink" href="#">Ya, Hapus</a>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e){ if(e.target===this) closeModal(this.id); });
});
document.addEventListener('keydown', e => {
    if (e.key==='Escape') {
        document.querySelectorAll('.modal-overlay.open,.confirm-overlay.open').forEach(el=>{el.classList.remove('open');document.body.style.overflow='';});
    }
});

function hitungTotal(suffix) {
    const stok  = parseInt(document.getElementById('stok_' + suffix).value) || 0;
    const harga = parseInt(document.getElementById('harga_' + suffix).value) || 0;
    document.getElementById('totalBeli_' + suffix).textContent = 'Rp ' + (stok * harga).toLocaleString('id-ID');
}

function konfirmasiHapus(id, nama) {
    document.getElementById('confirmSub').textContent = '"' + nama + '" akan dihapus permanen dari sistem.';
    document.getElementById('confirmLink').href = 'kelola_bahan.php?hapus=' + encodeURIComponent(id);
    document.getElementById('confirmOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('confirmOverlay').addEventListener('click', function(e){ if(e.target===this) closeConfirm(); });

function filterBahan() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#bahanTable tbody tr[data-search]').forEach(tr => {
        tr.style.display = tr.dataset.search.includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>