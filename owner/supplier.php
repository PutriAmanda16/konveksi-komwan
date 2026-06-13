<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php"); exit;
}

if (isset($_POST['simpan_supplier'])) {
    $id     = mysqli_real_escape_string($koneksi, $_POST['id_supplier']);
    $nama   = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $telp   = mysqli_real_escape_string($koneksi, $_POST['telp']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    if ($_POST['aksi'] == 'tambah') {
        mysqli_query($koneksi, "INSERT INTO supplier (ID_SUPPLIER, NAMA_SUPPLIER, NO_TELP, ALAMAT_SUPPLIER) VALUES ('$id','$nama','$telp','$alamat')");
    } else {
        mysqli_query($koneksi, "UPDATE supplier SET NAMA_SUPPLIER='$nama', NO_TELP='$telp', ALAMAT_SUPPLIER='$alamat' WHERE ID_SUPPLIER='$id'");
    }
    header("Location: supplier.php"); exit;
}

if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    mysqli_query($koneksi, "DELETE FROM supplier WHERE ID_SUPPLIER='$id'");
    header("Location: supplier.php"); exit;
}

if (isset($_POST['tambah_pembelian'])) {
    $id_sup   = mysqli_real_escape_string($koneksi, $_POST['id_supplier_trx']);
    $id_bahan = mysqli_real_escape_string($koneksi, $_POST['id_bahan']);
    $jumlah   = (int)$_POST['jumlah'];
    $tgl      = mysqli_real_escape_string($koneksi, $_POST['tgl_beli']);
    $catatan  = mysqli_real_escape_string($koneksi, $_POST['catatan'] ?? '');
    $bhn      = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT HARGA_SATUAN FROM bahan_baku WHERE ID_BAHAN='$id_bahan'"));
    $harga_satuan = $bhn ? $bhn['HARGA_SATUAN'] : 0;
    $total    = $jumlah * $harga_satuan;
    $last     = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT ID_PEMBELIAN FROM pembelian_bahan ORDER BY ID_PEMBELIAN DESC LIMIT 1"));
    $num      = $last ? (int)substr($last['ID_PEMBELIAN'], 2) + 1 : 1;
    $id_pb    = 'PB' . str_pad($num, 2, '0', STR_PAD_LEFT);
    mysqli_query($koneksi, "INSERT INTO pembelian_bahan (ID_PEMBELIAN, ID_SUPPLIER, ID_OWNER, TANGGAL_BELI, TOTAL_BIAYA, STATUS_BAYAR, CATATAN) VALUES ('$id_pb','$id_sup','{$_SESSION['user']}','$tgl','$total','Belum Dibayar','$catatan')");
    $id_detail = 'DTL' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);
    mysqli_query($koneksi, "INSERT INTO detail_pembelian (ID_DETAIL_BELI, ID_PEMBELIAN, ID_BAHAN, JUMLAH_BELI, SUBTOTAL_BELI) VALUES ('$id_detail','$id_pb','$id_bahan','$jumlah','$total')");    mysqli_query($koneksi, "UPDATE bahan_baku SET JUMLAH_STOK = JUMLAH_STOK + $jumlah WHERE ID_BAHAN='$id_bahan'");
    header("Location: supplier.php"); exit;
}

if (isset($_POST['bayar_pembelian'])) {
    $id_pb = mysqli_real_escape_string($koneksi, $_POST['id_pembelian']);
    mysqli_query($koneksi, "UPDATE pembelian_bahan SET STATUS_BAYAR='Sudah Dibayar' WHERE ID_PEMBELIAN='$id_pb'");
    header("Location: supplier.php"); exit;
}

$semua_bahan = [];
$res_bhn = mysqli_query($koneksi, "SELECT ID_BAHAN, NAMA_BAHAN, HARGA_SATUAN FROM bahan_baku ORDER BY ID_BAHAN ASC");
while ($b = mysqli_fetch_assoc($res_bhn)) $semua_bahan[] = $b;

$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;
$aset_rusak  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM aset WHERE KONDISI_ASET IN ('Rusak','Perlu Perbaikan')"))['t'] ?? 0;
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));

$nama_owner = $_SESSION['user'];
$inisial = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

$total_supplier = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM supplier"))['t'] ?? 0;
$total_hutang   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(TOTAL_BIAYA) as t FROM pembelian_bahan WHERE STATUS_BAYAR='Belum Dibayar'"))['t'] ?? 0;
$total_lunas    = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(TOTAL_BIAYA) as t FROM pembelian_bahan WHERE STATUS_BAYAR='Sudah Dibayar'"))['t'] ?? 0;
$total_trx      = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pembelian_bahan"))['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Supplier 🌸 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --p50:#fff0f5; --p100:#ffd6e7; --p200:#ffadd0; --p300:#ff80b8;
    --p400:#f950a0; --p500:#e8328a; --p600:#cc1a73; --p700:#a8105d;
    --v100:#f3e8ff; --v300:#d8b4fe; --v500:#a855f7;
    --g100:#dcfce7; --g500:#22c55e; --g700:#15803d;
    --a100:#fef9c3; --a500:#eab308; --a700:#854d0e;
    --b100:#dbeafe; --b500:#3b82f6; --b700:#1d4ed8;
    --r100:#fee2e2; --r500:#ef4444; --r700:#991b1b;
    --o100:#ffedd5; --o500:#f97316; --o700:#9a3412;
    --white:#ffffff; --bg:#fff5f9;
    --text:#3d1a28; --text2:#7d4460; --text3:#b07090;
    --border:rgba(232,50,138,0.13); --border2:rgba(232,50,138,0.24);
    --sidebar-w:256px; --topbar-h:64px;
    --r-sm:10px; --r-md:16px; --r-lg:22px; --r-xl:28px;
    --ease:0.2s cubic-bezier(0.34,1.56,0.64,1);
    --ease-plain:0.17s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;font-size:14.5px;line-height:1.6;-webkit-font-smoothing:antialiased;}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(232,50,138,0.055) 1.5px,transparent 1.5px);background-size:28px 28px;pointer-events:none;z-index:0;}
::-webkit-scrollbar{width:5px;}
::-webkit-scrollbar-track{background:var(--p50);}
::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px;}

/* ══ SIDEBAR ══ */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--white);border-right:1.5px solid var(--border);display:flex;flex-direction:column;z-index:300;overflow:hidden;}
.sidebar::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;z-index:1;animation:shimmer 3s linear infinite;}
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}

.sb-brand{display:flex;align-items:center;gap:12px;padding:0 18px;height:var(--topbar-h);border-bottom:1.5px solid var(--border);text-decoration:none;flex-shrink:0;transition:background var(--ease-plain);margin-top:4px;}
.sb-brand:hover{background:var(--p50);}
.brand-mark{width:38px;height:38px;border-radius:13px;background:linear-gradient(135deg,var(--p500) 0%,var(--p400) 50%,var(--v500) 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4),0 0 0 3px rgba(232,50,138,0.12);transition:transform var(--ease),box-shadow var(--ease);}
.sb-brand:hover .brand-mark{transform:rotate(-8deg) scale(1.08);}
.brand-mark i{font-size:18px;color:#fff;}
.brand-name{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text);white-space:nowrap;}
.brand-sub{font-size:10px;font-weight:600;color:var(--p500);letter-spacing:0.8px;text-transform:uppercase;margin-top:1px;}

.sb-owner{margin:12px 12px 6px;padding:12px 14px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:var(--r-lg);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.owner-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;position:relative;box-shadow:0 3px 10px rgba(232,50,138,0.35);}
.owner-av::after{content:'';position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:var(--g500);border:2px solid var(--white);}
.owner-name{font-size:13.5px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.owner-role{font-size:11px;color:var(--p500);font-weight:600;}

.sb-nav{flex:1;overflow-y:auto;padding:6px 10px 10px;}
.nav-group-label{font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--text3);padding:14px 10px 4px;display:flex;align-items:center;gap:6px;}
.nav-group-label::after{content:'✦';font-size:7px;color:var(--p300);}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:var(--r-sm);text-decoration:none;color:var(--text2);font-size:14px;font-weight:600;transition:all var(--ease-plain);margin-bottom:2px;position:relative;white-space:nowrap;}
.nav-item i{font-size:17px;width:19px;text-align:center;flex-shrink:0;color:var(--text3);}
.nav-item:hover{background:var(--p50);color:var(--p500);transform:translateX(2px);}
.nav-item:hover i{color:var(--p400);}
.nav-item.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-weight:700;box-shadow:0 4px 16px rgba(232,50,138,0.35);}
.nav-item.active i{color:rgba(255,255,255,0.9);}
.nav-pill{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0;}
.pill-red{background:var(--r500);} .pill-orange{background:var(--o500);} .pill-pink{background:var(--p500);}
.nav-item.active .nav-pill{background:rgba(255,255,255,0.3);}
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0;}
.nav-item.logout{color:var(--r700);} .nav-item.logout i{color:var(--r500);}
.nav-item.logout:hover{background:var(--r100);transform:none;}

/* ══ TOPBAR ══ */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(12px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px;}
.topbar::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;animation:shimmer 3s linear infinite;}
.tb-left{flex:1;}
.tb-page-title{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.tb-page-title i{color:var(--p500);}
.tb-sub{font-size:12px;color:var(--text3);font-weight:500;margin-top:1px;}
.tb-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.icon-btn{width:36px;height:36px;border-radius:10px;background:var(--p50);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;color:var(--p500);font-size:16px;transition:all var(--ease);position:relative;}
.icon-btn:hover{background:var(--p100);transform:scale(1.08);}
.icon-btn .dot{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--r500);border:2px solid var(--white);animation:blink 1.6s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
.date-pill{display:flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:600;color:var(--text2);}
.date-pill i{color:var(--p500);}
.btn-add-main{display:flex;align-items:center;gap:7px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border:none;border-radius:99px;padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(232,50,138,0.4);transition:all var(--ease);font-family:'Nunito',sans-serif;text-decoration:none;}
.btn-add-main:hover{transform:translateY(-2px) scale(1.03);box-shadow:0 8px 22px rgba(232,50,138,0.5);color:#fff;}

/* ══ MAIN ══ */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1;}
.content{padding:28px 28px 70px;max-width:1360px;}

/* ── Stats ── */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:18px 20px;position:relative;overflow:hidden;transition:transform var(--ease),box-shadow var(--ease);}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(232,50,138,0.12);}
.stat-stripe{position:absolute;top:0;left:0;right:0;height:4px;border-radius:var(--r-lg) var(--r-lg) 0 0;}
.stat-blob{position:absolute;right:-14px;bottom:-14px;width:68px;height:68px;border-radius:50%;opacity:0.07;}
.stat-ico{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:10px;}
.stat-label{font-size:12px;font-weight:600;color:var(--text2);margin-bottom:3px;}
.stat-val{font-family:'Quicksand',sans-serif;font-size:20px;font-weight:700;line-height:1.1;}

/* ── Table ── */
.tbl-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;}
.tbl-hd{padding:18px 24px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--p50),var(--white));}
.tbl-title{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.tbl-title i{color:var(--p500);}
.data-table{width:100%;border-collapse:collapse;}
.data-table thead th{padding:12px 20px;font-size:11.5px;font-weight:700;color:var(--text3);text-align:left;background:var(--p50);border-bottom:1.5px solid var(--border);}
.data-table tbody td{padding:14px 20px;border-bottom:1px solid rgba(232,50,138,0.06);font-size:14px;vertical-align:middle;}
.data-table tbody tr:last-child td{border-bottom:none;}
.data-table tbody tr{transition:background var(--ease-plain);}
.data-table tbody tr:hover td{background:var(--p50);}

.sup-name-link{color:var(--p600);font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;transition:color var(--ease-plain);}
.sup-name-link:hover{color:var(--p500);}
.id-tag{display:inline-flex;align-items:center;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:7px;padding:3px 9px;font-size:12px;font-weight:700;}
.badge-trx{background:var(--g100);color:var(--g700);border-radius:99px;padding:3px 10px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.badge-hutang{background:var(--a100);color:var(--a700);border-radius:99px;padding:3px 10px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.badge-lunas{background:var(--g100);color:var(--g700);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.badge-belum{background:var(--a100);color:var(--a700);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;}

/* phone + wa chips */
.phone-chip{display:inline-flex;align-items:center;gap:5px;background:var(--b100);color:var(--b700);border:1px solid rgba(59,130,246,0.2);border-radius:99px;padding:5px 13px;font-size:13px;font-weight:700;white-space:nowrap;}
.phone-chip i{font-size:11px;}
.wa-chip{display:inline-flex;align-items:center;gap:5px;background:var(--g100);color:var(--g700);border:1px solid rgba(34,197,94,0.25);border-radius:99px;padding:5px 13px;font-size:13px;font-weight:700;text-decoration:none;transition:all var(--ease-plain);white-space:nowrap;}
.wa-chip:hover{background:var(--g500);color:#fff;border-color:var(--g500);}
.wa-chip i{font-size:13px;}

.act-btn{width:32px;height:32px;border-radius:9px;border:1.5px solid var(--border);background:var(--white);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;transition:all var(--ease);text-decoration:none;}
.act-btn:hover{transform:scale(1.1);}
.act-btn-pink{color:var(--p500);} .act-btn-pink:hover{background:var(--p50);border-color:var(--border2);}
.act-btn-blue{color:var(--b500);} .act-btn-blue:hover{background:var(--b100);border-color:rgba(59,130,246,0.3);}
.act-btn-red{color:var(--r500);}  .act-btn-red:hover{background:var(--r100);border-color:rgba(239,68,68,0.3);}

/* ══ MODALS ══ */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(61,26,40,0.35);backdrop-filter:blur(4px);z-index:999;align-items:center;justify-content:center;padding:20px;}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-box{background:var(--white);border-radius:var(--r-xl);width:100%;max-width:520px;overflow:hidden;box-shadow:0 24px 60px rgba(61,26,40,0.2);animation:slideUp 0.25s cubic-bezier(0.34,1.56,0.64,1);max-height:90vh;overflow-y:auto;}
.modal-xl-box{max-width:900px;}
@keyframes slideUp{from{opacity:0;transform:translateY(20px) scale(0.97)}to{opacity:1;transform:none}}

.modal-header-pink{background:linear-gradient(135deg,var(--p500),var(--p400));padding:20px 24px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-green{background:linear-gradient(135deg,var(--g700),var(--g500));padding:20px 24px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-blue{background:linear-gradient(135deg,var(--b700),var(--b500));padding:20px 24px;display:flex;align-items:center;justify-content:space-between;}
.modal-hd-title{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:#fff;display:flex;align-items:center;gap:8px;}
.modal-hd-sub{font-size:11.5px;color:rgba(255,255,255,0.65);margin-top:2px;}
.btn-close-white{background:none;border:none;color:rgba(255,255,255,0.7);font-size:20px;cursor:pointer;line-height:1;padding:0;transition:color var(--ease-plain);}
.btn-close-white:hover{color:#fff;}

.modal-body-inner{padding:24px;}
.form-group{margin-bottom:18px;}
.form-label-cute{display:block;font-size:12px;font-weight:700;color:var(--text2);margin-bottom:6px;letter-spacing:0.2px;}
.form-ctrl{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-family:'Nunito',sans-serif;font-size:14px;color:var(--text);background:var(--white);transition:border-color var(--ease-plain),box-shadow var(--ease-plain);outline:none;}
.form-ctrl:focus{border-color:var(--p400);box-shadow:0 0 0 3px rgba(232,50,138,0.12);}
.form-ctrl::placeholder{color:var(--text3);}
select.form-ctrl{cursor:pointer;}
textarea.form-ctrl{resize:vertical;min-height:80px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

.total-preview{background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:var(--r-md);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.total-preview-label{font-size:12px;font-weight:600;color:var(--text2);}
.total-preview-val{font-family:'Quicksand',sans-serif;font-size:18px;font-weight:700;color:var(--p600);}

.modal-footer-inner{padding:16px 24px;border-top:1.5px solid var(--border);background:var(--p50);}
.btn-submit-pink{width:100%;padding:12px;border-radius:99px;border:none;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-family:'Nunito',sans-serif;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(232,50,138,0.35);transition:all var(--ease);display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-submit-pink:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(232,50,138,0.45);}
.btn-submit-green{width:100%;padding:12px;border-radius:99px;border:none;background:linear-gradient(135deg,var(--g700),var(--g500));color:#fff;font-family:'Nunito',sans-serif;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(34,197,94,0.35);transition:all var(--ease);display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-submit-green:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(34,197,94,0.45);}

.modal-riwayat-table{width:100%;border-collapse:collapse;font-size:13.5px;}
.modal-riwayat-table thead th{padding:10px 16px;font-size:11px;font-weight:700;color:var(--text3);background:var(--p50);border-bottom:1.5px solid var(--border);text-align:left;}
.modal-riwayat-table tbody td{padding:12px 16px;border-bottom:1px solid rgba(232,50,138,0.06);vertical-align:middle;}
.modal-riwayat-table tbody tr:last-child td{border-bottom:none;}
.modal-riwayat-table tbody tr:hover td{background:var(--p50);}
.nota-box{background:var(--p50);border:1px dashed var(--border2);border-radius:8px;padding:6px 10px;font-size:12px;color:var(--text2);display:inline-block;}

.btn-bayar{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:99px;border:none;background:linear-gradient(135deg,var(--g700),var(--g500));color:#fff;font-family:'Nunito',sans-serif;font-size:11.5px;font-weight:700;cursor:pointer;box-shadow:0 3px 10px rgba(34,197,94,0.3);transition:all var(--ease);}
.btn-bayar:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(34,197,94,0.4);}

.empty-state{text-align:center;padding:40px 20px;color:var(--text3);}
.empty-state i{font-size:40px;color:var(--p200);display:block;margin-bottom:12px;}
.empty-state p{font-size:14px;font-weight:600;}

@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(0.85);opacity:0.6}}
.pulse{animation:pulse 1.8s ease-in-out infinite;}

@media(max-width:1280px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:900px){
    .sidebar{transform:translateX(-100%);}
    .topbar{left:0;} .main{margin-left:0;}
    .stats-grid{grid-template-columns:1fr 1fr;}
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
        <a class="nav-item" href="kelola_aset.php">
            <i class="bi bi-building-gear"></i> Aset &amp; Inventaris
            <?php if ($aset_rusak > 0): ?><span class="nav-pill pill-orange pulse"><?= $aset_rusak ?></span><?php endif; ?>
        </a>
        <a class="nav-item" href="data_penjahit.php"><i class="bi bi-people"></i> Data Penjahit</a>
        <a class="nav-item" href="pelanggan.php"><i class="bi bi-person-badge"></i> Data Pelanggan</a>
        <a class="nav-item active" href="supplier.php"><i class="bi bi-truck"></i> Data Supplier</a>
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
    <div class="tb-left">
        <div class="tb-page-title"><i class="bi bi-truck"></i> Data Supplier 🌸</div>
        <div class="tb-sub">Kelola supplier dan riwayat pembelian bahan baku</div>
    </div>
    <div class="tb-actions">
        <a href="dashboard.php" class="icon-btn" title="Dashboard"><i class="bi bi-grid-1x2"></i></a>
        <a href="konfirmasi_pembayaran.php" class="icon-btn" title="Notifikasi">
            <i class="bi bi-bell-fill"></i>
            <?php if ($notif_bayar > 0): ?><span class="dot"></span><?php endif; ?>
        </a>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
        <button class="btn-add-main" onclick="openModal('modalTambahSup')">
            <i class="bi bi-plus-lg"></i> Tambah Supplier
        </button>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--p500),var(--p300))"></div>
            <div class="stat-ico" style="background:var(--p50);color:var(--p500)"><i class="bi bi-truck"></i></div>
            <div class="stat-label">Total Supplier</div>
            <div class="stat-val" style="color:var(--p600)"><?= $total_supplier ?></div>
            <div class="stat-blob" style="background:var(--p500)"></div>
        </div>
        <div class="stat-card">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--a500),#fcd34d)"></div>
            <div class="stat-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-clock-history"></i></div>
            <div class="stat-label">Hutang Belum Lunas</div>
            <div class="stat-val" style="color:var(--a700)">Rp <?= number_format($total_hutang) ?></div>
            <div class="stat-blob" style="background:var(--a500)"></div>
        </div>
        <div class="stat-card">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--g500),#86efac)"></div>
            <div class="stat-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-label">Total Sudah Lunas</div>
            <div class="stat-val" style="color:var(--g700)">Rp <?= number_format($total_lunas) ?></div>
            <div class="stat-blob" style="background:var(--g500)"></div>
        </div>
        <div class="stat-card">
            <div class="stat-stripe" style="background:linear-gradient(90deg,var(--b500),var(--v300))"></div>
            <div class="stat-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-receipt"></i></div>
            <div class="stat-label">Total Transaksi</div>
            <div class="stat-val" style="color:var(--b700)"><?= $total_trx ?> kali</div>
            <div class="stat-blob" style="background:var(--b500)"></div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="tbl-card">
        <div class="tbl-hd">
            <div class="tbl-title"><i class="bi bi-shop"></i> Daftar Supplier</div>
            <span style="font-size:12px;color:var(--text3);font-weight:600">Klik nama supplier untuk lihat riwayat transaksi 👆</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Supplier</th>
                    <th>No. HP &amp; WhatsApp</th>
                    <th>Alamat</th>
                    <th style="text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $modals = "";
            $query  = mysqli_query($koneksi, "SELECT * FROM supplier ORDER BY ID_SUPPLIER ASC");
            while ($row = mysqli_fetch_assoc($query)):
                $id_sup = $row['ID_SUPPLIER'];
                $count  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pembelian_bahan WHERE ID_SUPPLIER='$id_sup'"))['t'];
                $belum  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pembelian_bahan WHERE ID_SUPPLIER='$id_sup' AND STATUS_BAYAR='Belum Dibayar'"))['t'];
                // Format nomor WA
                $no_wa = preg_replace('/[^0-9]/', '', $row['NO_TELP']);
                if (substr($no_wa, 0, 1) === '0') $no_wa = '62' . substr($no_wa, 1);
            ?>
            <tr>
                <td><span class="id-tag"><?= $id_sup ?></span></td>
                <td>
                    <span class="sup-name-link" onclick="openModal('riwayat<?= $id_sup ?>')">
                        <i class="bi bi-shop"></i> <?= htmlspecialchars($row['NAMA_SUPPLIER']) ?>
                    </span>
                    <div style="margin-top:5px;display:flex;gap:6px;flex-wrap:wrap">
                        <span class="badge-trx"><i class="bi bi-receipt"></i> <?= $count ?> Transaksi</span>
                        <?php if ($belum > 0): ?>
                        <span class="badge-hutang"><i class="bi bi-clock"></i> <?= $belum ?> Belum Lunas</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
                        <span class="phone-chip">
                            <i class="bi bi-telephone-fill"></i>
                            <?= htmlspecialchars($row['NO_TELP']) ?>
                        </span>
                        <a href="https://wa.me/<?= $no_wa ?>" target="_blank" class="wa-chip">
                            <i class="bi bi-whatsapp"></i> WA
                        </a>
                    </div>
                </td>
                <td style="color:var(--text2);font-size:13.5px">
                    <i class="bi bi-geo-alt" style="color:var(--p400);margin-right:4px"></i>
                    <?= htmlspecialchars($row['ALAMAT_SUPPLIER']) ?>
                </td>
                <td style="text-align:center">
                    <div style="display:flex;align-items:center;justify-content:center;gap:6px">
                        <button class="act-btn act-btn-pink" onclick="openModal('tambahTrx<?= $id_sup ?>')" title="Tambah Pembelian">
                            <i class="bi bi-cart-plus"></i>
                        </button>
                        <button class="act-btn act-btn-blue" onclick="openModal('edit<?= $id_sup ?>')" title="Edit Supplier">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <a href="?hapus=<?= $id_sup ?>" class="act-btn act-btn-red" onclick="return confirm('Hapus supplier ini?')" title="Hapus">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>

            <?php
            ob_start();
            $q_beli = mysqli_query($koneksi,
                "SELECT pb.ID_PEMBELIAN,
                        pb.TANGGAL_BELI,
                        pb.TOTAL_BIAYA,
                        GROUP_CONCAT(CONCAT(dp.JUMLAH_BELI,'x ', bk.NAMA_BAHAN) SEPARATOR ', ') as DETAIL_BAHAN
                FROM pembelian_bahan pb
                LEFT JOIN detail_pembelian dp ON pb.ID_PEMBELIAN=dp.ID_PEMBELIAN
                LEFT JOIN bahan_baku bk ON dp.ID_BAHAN=bk.ID_BAHAN
                WHERE pb.ID_SUPPLIER='$id_sup'
                GROUP BY pb.ID_PEMBELIAN
                ORDER BY pb.TANGGAL_BELI DESC");
            ?>

            <!-- Modal Riwayat -->
            <div id="riwayat<?= $id_sup ?>" class="modal-overlay" onclick="closeModalOverlay(this)">
                <div class="modal-box modal-xl-box" onclick="event.stopPropagation()">
                    <div class="modal-header-pink">
                        <div>
                            <div class="modal-hd-title"><i class="bi bi-receipt"></i> Riwayat Pembelian</div>
                            <div class="modal-hd-sub">Supplier: <?= htmlspecialchars($row['NAMA_SUPPLIER']) ?> · <?= htmlspecialchars($row['NO_TELP']) ?></div>
                        </div>
                        <button class="btn-close-white" onclick="closeModal('riwayat<?= $id_sup ?>')"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div style="overflow-x:auto;max-height:500px;overflow-y:auto">
                        <?php if (mysqli_num_rows($q_beli) > 0): ?>
                        <table class="modal-riwayat-table">
                            <thead>
                                <tr><th>ID</th><th>Tanggal</th><th>Detail Bahan</th><th>Total</th><th>Catatan</th><th>Status</th><th>Aksi</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($rb = mysqli_fetch_assoc($q_beli)): ?>
                            <tr>
                                <td><span class="id-tag" style="background:var(--g100);color:var(--g700);border-color:rgba(34,197,94,0.25)"><?= $rb['ID_PEMBELIAN'] ?></span></td>
                                <td style="font-weight:600;color:var(--text2)"><?= date('d/m/Y', strtotime($rb['TANGGAL_BELI'])) ?></td>
                                <td style="color:var(--text2);font-size:13px"><?= $rb['DETAIL_BAHAN'] ?? '-' ?></td>
                                <td style="font-weight:700;color:var(--g700)">Rp <?= number_format($rb['TOTAL_BIAYA']) ?></td>
                                <td><?php if ($rb['CATATAN']): ?><span class="nota-box"><?= htmlspecialchars($rb['CATATAN']) ?></span><?php else: ?><span style="color:var(--text3);font-size:12px">—</span><?php endif; ?></td>
                                <td>
                                    <?php if ($rb['STATUS_BAYAR'] == 'Sudah Dibayar'): ?>
                                    <span class="badge-lunas"><i class="bi bi-check-circle-fill"></i> Lunas</span>
                                    <?php else: ?>
                                    <span class="badge-belum"><i class="bi bi-clock"></i> Belum Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($rb['STATUS_BAYAR'] != 'Sudah Dibayar'): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Tandai <?= $rb['ID_PEMBELIAN'] ?> sebagai Sudah Dibayar?')">
                                        <input type="hidden" name="id_pembelian" value="<?= $rb['ID_PEMBELIAN'] ?>">
                                        <button type="submit" name="bayar_pembelian" class="btn-bayar"><i class="bi bi-cash-coin"></i> Bayar</button>
                                    </form>
                                    <?php else: ?>
                                    <span style="color:var(--g500);font-size:16px">✓</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state"><i class="bi bi-inbox"></i><p>Belum ada riwayat pembelian 🌸</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Modal Tambah Transaksi -->
            <div id="tambahTrx<?= $id_sup ?>" class="modal-overlay" onclick="closeModalOverlay(this)">
                <div class="modal-box" onclick="event.stopPropagation()">
                    <form action="" method="POST">
                        <div class="modal-header-pink">
                            <div>
                                <div class="modal-hd-title"><i class="bi bi-cart-plus"></i> Tambah Pembelian</div>
                                <div class="modal-hd-sub"><?= htmlspecialchars($row['NAMA_SUPPLIER']) ?></div>
                            </div>
                            <button type="button" class="btn-close-white" onclick="closeModal('tambahTrx<?= $id_sup ?>')"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="modal-body-inner">
                            <input type="hidden" name="id_supplier_trx" value="<?= $id_sup ?>">
                            <div class="form-group">
                                <label class="form-label-cute">Bahan yang Dibeli</label>
                                <select name="id_bahan" class="form-ctrl" onchange="updateHarga(this,'<?= $id_sup ?>')" required>
                                    <option value="">— Pilih Bahan —</option>
                                    <?php foreach ($semua_bahan as $bhn): ?>
                                    <option value="<?= $bhn['ID_BAHAN'] ?>" data-harga="<?= $bhn['HARGA_SATUAN'] ?>">
                                        <?= $bhn['ID_BAHAN'] ?> · <?= $bhn['NAMA_BAHAN'] ?> (Rp <?= number_format($bhn['HARGA_SATUAN']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-group" style="margin-bottom:0">
                                    <label class="form-label-cute">Jumlah Beli</label>
                                    <input type="number" name="jumlah" class="form-ctrl" id="jumlah_<?= $id_sup ?>" min="1" required oninput="hitungTotalTrx('<?= $id_sup ?>')">
                                </div>
                                <div class="form-group" style="margin-bottom:0">
                                    <label class="form-label-cute">Harga Satuan</label>
                                    <input type="text" class="form-ctrl" id="satuan_<?= $id_sup ?>" readonly value="Rp 0" style="background:var(--p50);color:var(--p600);font-weight:700">
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:18px">
                                <label class="form-label-cute">Tanggal Pembelian</label>
                                <input type="date" name="tgl_beli" class="form-ctrl" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label-cute">Catatan / Nota <span style="color:var(--text3);font-weight:500">(opsional)</span></label>
                                <textarea name="catatan" class="form-ctrl" placeholder="Misal: No. Faktur, syarat pembayaran..."></textarea>
                            </div>
                            <div class="total-preview">
                                <div class="total-preview-label">💰 Estimasi Total Bayar</div>
                                <div class="total-preview-val" id="total_<?= $id_sup ?>">Rp 0</div>
                            </div>
                        </div>
                        <div class="modal-footer-inner">
                            <button type="submit" name="tambah_pembelian" class="btn-submit-green">
                                <i class="bi bi-save"></i> Catat Pembelian &amp; Update Stok
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Edit Supplier -->
            <div id="edit<?= $id_sup ?>" class="modal-overlay" onclick="closeModalOverlay(this)">
                <div class="modal-box" onclick="event.stopPropagation()">
                    <form action="" method="POST">
                        <div class="modal-header-blue">
                            <div class="modal-hd-title"><i class="bi bi-pencil-square"></i> Edit Supplier</div>
                            <button type="button" class="btn-close-white" onclick="closeModal('edit<?= $id_sup ?>')"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="modal-body-inner">
                            <input type="hidden" name="aksi" value="edit">
                            <input type="hidden" name="id_supplier" value="<?= $id_sup ?>">
                            <div class="form-group">
                                <label class="form-label-cute">Nama Supplier</label>
                                <input type="text" name="nama" class="form-ctrl" value="<?= htmlspecialchars($row['NAMA_SUPPLIER']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label-cute">No Telepon</label>
                                <input type="text" name="telp" class="form-ctrl" value="<?= htmlspecialchars($row['NO_TELP']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label-cute">Alamat Supplier</label>
                                <textarea name="alamat" class="form-ctrl" required><?= htmlspecialchars($row['ALAMAT_SUPPLIER']) ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer-inner">
                            <button type="submit" name="simpan_supplier" class="btn-submit-pink">
                                <i class="bi bi-save"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php
            $modals .= ob_get_clean();
            endwhile;
            ?>
            </tbody>
        </table>
    </div>
</div>
</main>

<!-- Modal Tambah Supplier Baru -->
<div id="modalTambahSup" class="modal-overlay" onclick="closeModalOverlay(this)" style="display:none">
    <div class="modal-box" onclick="event.stopPropagation()">
        <form action="" method="POST">
            <div class="modal-header-pink">
                <div class="modal-hd-title"><i class="bi bi-truck"></i> Tambah Supplier Baru</div>
                <button type="button" class="btn-close-white" onclick="closeModal('modalTambahSup')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body-inner">
                <input type="hidden" name="aksi" value="tambah">
                <div class="form-group">
                    <label class="form-label-cute">ID Supplier</label>
                    <input type="text" name="id_supplier" class="form-ctrl" placeholder="Contoh: SUP08" required>
                </div>
                <div class="form-group">
                    <label class="form-label-cute">Nama Supplier</label>
                    <input type="text" name="nama" class="form-ctrl" placeholder="Nama lengkap supplier" required>
                </div>
                <div class="form-group">
                    <label class="form-label-cute">No Telepon</label>
                    <input type="text" name="telp" class="form-ctrl" placeholder="08xxxxxxxxxx" required>
                </div>
                <div class="form-group">
                    <label class="form-label-cute">Alamat Supplier</label>
                    <textarea name="alamat" class="form-ctrl" placeholder="Alamat lengkap supplier..." required></textarea>
                </div>
            </div>
            <div class="modal-footer-inner">
                <button type="submit" name="simpan_supplier" class="btn-submit-pink">
                    <i class="bi bi-save"></i> Simpan Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<?= $modals ?>

<script>
const hargaBahan = <?= json_encode(array_column($semua_bahan, 'HARGA_SATUAN', 'ID_BAHAN')) ?>;

function openModal(id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
}
function closeModalOverlay(el) {
    el.style.display = 'none'; document.body.style.overflow = '';
}
function updateHarga(sel, supId) {
    const harga = hargaBahan[sel.value] ?? 0;
    document.getElementById('satuan_' + supId).value = 'Rp ' + Number(harga).toLocaleString('id-ID');
    hitungTotalTrx(supId);
}
function hitungTotalTrx(supId) {
    const jumlah = parseInt(document.getElementById('jumlah_' + supId)?.value) || 0;
    const raw    = document.getElementById('satuan_' + supId)?.value?.replace(/[^0-9]/g,'') || 0;
    document.getElementById('total_' + supId).textContent = 'Rp ' + (jumlah * parseInt(raw)).toLocaleString('id-ID');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            if (m.style.display === 'flex') { m.style.display = 'none'; document.body.style.overflow = ''; }
        });
    }
});
</script>
</body>
</html>