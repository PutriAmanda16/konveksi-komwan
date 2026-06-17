<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

// =========================================================
// 1. PROSES SIMPAN EDIT ITEM + UPDATE TOTAL
// =========================================================
if (isset($_POST['simpan_edit_total'])) {
    $id_pesanan  = mysqli_real_escape_string($koneksi, $_POST['id_pesanan']);
    $tgl         = mysqli_real_escape_string($koneksi, $_POST['tgl']);
    $produk_ids  = $_POST['id_produk'] ?? [];
    $jumlahs     = $_POST['jumlah'] ?? [];
    $ukurans     = $_POST['ukuran'] ?? [];

    mysqli_query($koneksi, "UPDATE pesanan SET WAKTU_PESAN='$tgl' WHERE ID_PESANAN='$id_pesanan'");
    mysqli_query($koneksi, "DELETE FROM detail_pesanan WHERE ID_PESANAN='$id_pesanan'");

    $total_harga_baru = 0;
    for ($i = 0; $i < count($produk_ids); $i++) {
        $id_p = mysqli_real_escape_string($koneksi, $produk_ids[$i]);
        $jml  = (int)$jumlahs[$i];
        $ukr  = mysqli_real_escape_string($koneksi, $ukurans[$i]);
        if (!empty($id_p) && $jml > 0) {
            $q_harga      = mysqli_query($koneksi, "SELECT HARGA FROM produk WHERE ID_PRODUK='$id_p'");
            $h_row        = mysqli_fetch_assoc($q_harga);
            $harga_satuan = $h_row['HARGA'] ?? 0;
            $subtotal     = $harga_satuan * $jml;
            $total_harga_baru += $subtotal;
            $id_detail = 'DTL-' . time() . rand(10,99);
            mysqli_query($koneksi, "INSERT INTO detail_pesanan (ID_DETAIL, ID_PESANAN, ID_PRODUK, JUMLAH, UKURAN, SUBTOTAL)
                VALUES ('$id_detail','$id_pesanan','$id_p','$jml','$ukr','$subtotal')");
        }
    }
    mysqli_query($koneksi, "UPDATE pesanan SET TOTAL_HARGA='$total_harga_baru' WHERE ID_PESANAN='$id_pesanan'");
    echo "<script>alert('Data berhasil diperbarui!'); window.location='produksi.php';</script>";
    exit();
}

// =========================================================
// 2. UPDATE STATUS & PENJAHIT + DEADLINE
// =========================================================
if (isset($_POST['update_produksi'])) {
    $id_pesanan  = mysqli_real_escape_string($koneksi, $_POST['id_pesanan']);
    $status      = mysqli_real_escape_string($koneksi, $_POST['status']);
    $id_penjahit = mysqli_real_escape_string($koneksi, $_POST['id_penjahit']);
    $deadline    = mysqli_real_escape_string($koneksi, $_POST['deadline'] ?? '');

    mysqli_query($koneksi,
        "UPDATE pesanan SET STATUS='$status', ID_PENJAHIT='$id_penjahit', DEADLINE='$deadline'
        WHERE ID_PESANAN='$id_pesanan'");

    if (!empty($id_penjahit)) {
        $q_detail = mysqli_query($koneksi,
            "SELECT dp.ID_PRODUK, dp.JUMLAH
             FROM detail_pesanan dp
             WHERE dp.ID_PESANAN = '$id_pesanan'");

        while ($dp = mysqli_fetch_assoc($q_detail)) {
            $id_produk = mysqli_real_escape_string($koneksi, $dp['ID_PRODUK']);
            $jumlah    = (int)$dp['JUMLAH'];

            $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT ID_PRODUKSI FROM produksi
                 WHERE ID_PRODUK='$id_produk' AND ID_PENJAHIT='$id_penjahit'
                 LIMIT 1"));

            if ($cek) {
                $id_prk = mysqli_real_escape_string($koneksi, $cek['ID_PRODUKSI']);
                $dl_sql = !empty($deadline) ? ", DEADLINE='$deadline'" : "";
                mysqli_query($koneksi,
                    "UPDATE produksi SET
                        STATUS_PRODUKSI   = '$status',
                        JUMLAH_DIPRODUKSI = '$jumlah'
                        $dl_sql
                     WHERE ID_PRODUKSI = '$id_prk'");
            } else {
                $last_prk = mysqli_fetch_assoc(mysqli_query($koneksi,
                    "SELECT ID_PRODUKSI FROM produksi
                     WHERE ID_PRODUKSI LIKE 'PRK%'
                     ORDER BY ID_PRODUKSI DESC LIMIT 1"));
                $num_baru = $last_prk
                    ? (int)substr($last_prk['ID_PRODUKSI'], 3) + 1
                    : 1;
                $id_baru  = 'PRK' . str_pad($num_baru, 2, '0', STR_PAD_LEFT);

                $tgl_mulai = date('Y-m-d');
                $dl_val    = !empty($deadline) ? "'$deadline'" : "NULL";
                mysqli_query($koneksi,
                    "INSERT INTO produksi
                        (ID_PRODUKSI, ID_PRODUK, ID_PENJAHIT, STATUS_PRODUKSI,
                         TANGGAL_MULAI, TANGGAL_SELESAI, JUMLAH_DIPRODUKSI, DEADLINE)
                     VALUES
                        ('$id_baru', '$id_produk', '$id_penjahit', '$status',
                         '$tgl_mulai', '$tgl_mulai', '$jumlah', $dl_val)");
            }
        }
    }

    header("Location: produksi.php?msg=updated");
    exit();
}

// =========================================================
// 3. HAPUS PESANAN
// =========================================================
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    mysqli_query($koneksi, "DELETE FROM pesanan WHERE ID_PESANAN='$id'");
    mysqli_query($koneksi, "DELETE FROM detail_pesanan WHERE ID_PESANAN='$id'");
    header("Location: produksi.php?msg=deleted");
    exit();
}

// =========================================================
// NOTIF & SIDEBAR
// =========================================================
$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;
$aset_rusak  = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM aset"));
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$total_notif = $notif_bayar + $notif_chat + $stok_kritis + $aset_rusak;

$nama_owner = $_SESSION['user'];
$inisial = strtoupper(substr($nama_owner, 0, 1));
if (strpos($nama_owner, ' ') !== false) {
    $parts   = explode(' ', $nama_owner);
    $inisial = strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
}

// Data produksi
$query = mysqli_query($koneksi,
    "SELECT p.*, pl.NAMA_PELANGGAN FROM pesanan p
     JOIN pelanggan pl ON p.ID_PELANGGAN = pl.ID_PELANGGAN
     WHERE p.STATUS != 'Selesai'
     ORDER BY p.WAKTU_PESAN ASC");

$total_produksi = mysqli_num_rows($query);
$total_pending  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pesanan WHERE STATUS='Pending'"))['t']  ?? 0;
$total_proses   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pesanan WHERE STATUS='Proses'"))['t']   ?? 0;
$total_selesai  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM pesanan WHERE STATUS='Selesai'"))['t']  ?? 0;

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Produksi Aktif 🏭 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --p50:#fff0f5;--p100:#ffd6e7;--p200:#ffadd0;--p300:#ff80b8;--p400:#f950a0;--p500:#e8328a;--p600:#cc1a73;--p700:#a8105d;
    --v100:#f3e8ff;--v300:#d8b4fe;--v500:#a855f7;
    --g100:#dcfce7;--g500:#22c55e;--g700:#15803d;
    --a100:#fef9c3;--a500:#eab308;--a700:#854d0e;
    --b100:#dbeafe;--b500:#3b82f6;--b700:#1d4ed8;
    --r100:#fee2e2;--r500:#ef4444;--r700:#991b1b;
    --o100:#ffedd5;--o500:#f97316;--o700:#c2410c;
    --white:#ffffff;--bg:#fff5f9;--text:#3d1a28;--text2:#7d4460;--text3:#b07090;
    --border:rgba(232,50,138,0.13);--border2:rgba(232,50,138,0.24);
    --sidebar-w:256px;--topbar-h:64px;
    --r-sm:10px;--r-md:16px;--r-lg:22px;--r-xl:28px;
    --ease:0.2s cubic-bezier(0.34,1.56,0.64,1);--ease-plain:0.17s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;font-size:14.5px;line-height:1.6;-webkit-font-smoothing:antialiased;}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(232,50,138,0.055) 1.5px,transparent 1.5px);background-size:28px 28px;pointer-events:none;z-index:0;}
::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-track{background:var(--p50);}::-webkit-scrollbar-thumb{background:var(--p200);border-radius:99px;}

/* ══ SIDEBAR ══ */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--white);border-right:1.5px solid var(--border);display:flex;flex-direction:column;z-index:300;overflow:hidden;}
.sidebar::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;z-index:1;animation:shimmer 3s linear infinite;}
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}
.sb-brand{display:flex;align-items:center;gap:12px;padding:0 18px;height:var(--topbar-h);border-bottom:1.5px solid var(--border);text-decoration:none;flex-shrink:0;transition:background var(--ease-plain);margin-top:4px;}
.sb-brand:hover{background:var(--p50);}
.brand-mark{width:38px;height:38px;border-radius:13px;background:linear-gradient(135deg,var(--p500) 0%,var(--p400) 50%,var(--v500) 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4),0 0 0 3px rgba(232,50,138,0.12);transition:transform var(--ease);}
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
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:var(--r-sm);text-decoration:none;color:var(--text2);font-size:14px;font-weight:600;transition:background var(--ease-plain),color var(--ease-plain),transform var(--ease-plain);margin-bottom:2px;position:relative;white-space:nowrap;}
.nav-item i{font-size:17px;width:19px;text-align:center;flex-shrink:0;color:var(--text3);transition:color var(--ease-plain);}
.nav-item:hover{background:var(--p50);color:var(--p500);transform:translateX(2px);}
.nav-item:hover i{color:var(--p400);}
.nav-item.active{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-weight:700;box-shadow:0 4px 16px rgba(232,50,138,0.35);}
.nav-item.active i{color:rgba(255,255,255,0.9);}
.nav-item.active::after{content:'';position:absolute;right:10px;width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.6);}
.nav-pill{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0;}
.pill-red{background:var(--r500);}.pill-orange{background:var(--o500);}.pill-pink{background:var(--p500);}
.nav-item.active .nav-pill{background:rgba(255,255,255,0.3);}
.sb-footer{padding:10px 10px 14px;border-top:1.5px solid var(--border);flex-shrink:0;}
.nav-item.logout{color:var(--r700);}
.nav-item.logout i{color:var(--r500);}
.nav-item.logout:hover{background:var(--r100);color:var(--r700);transform:none;}

/* ══ TOPBAR ══ */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(255,255,255,0.94);backdrop-filter:blur(12px);border-bottom:1.5px solid var(--border);display:flex;align-items:center;padding:0 26px;z-index:200;gap:12px;}
.topbar::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));background-size:200%;animation:shimmer 3s linear infinite;}
.tb-greeting{flex:1;}
.tb-hello{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text);}
.tb-sub{font-size:12px;color:var(--text3);font-weight:500;margin-top:1px;}
.tb-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.icon-btn{width:36px;height:36px;border-radius:10px;background:var(--p50);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;color:var(--p500);font-size:16px;transition:all var(--ease);position:relative;}
.icon-btn:hover{background:var(--p100);transform:scale(1.08);}
.icon-btn .dot{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--r500);border:2px solid var(--white);animation:blink 1.6s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
.date-pill{display:flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:600;color:var(--text2);}
.date-pill i{color:var(--p500);}

/* ══ MAIN ══ */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;position:relative;z-index:1;}
.content{padding:28px 28px 70px;max-width:1400px;}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.anim{animation:fadeUp 0.35s ease both;}

/* Page header */
.page-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:14px;}
.page-title-wrap{display:flex;align-items:center;gap:14px;}
.page-icon{width:52px;height:52px;border-radius:16px;background:linear-gradient(135deg,var(--p500),var(--p400));display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;box-shadow:0 6px 18px rgba(232,50,138,0.35);}
.page-title{font-family:'Quicksand',sans-serif;font-size:22px;font-weight:700;color:var(--text);line-height:1.1;}
.page-subtitle{font-size:12.5px;color:var(--text3);font-weight:500;margin-top:3px;}
.back-btn{display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:99px;background:var(--white);border:1.5px solid var(--border);color:var(--text2);font-size:13.5px;font-weight:700;text-decoration:none;transition:all var(--ease);}
.back-btn:hover{background:var(--p50);color:var(--p500);border-color:var(--border2);transform:translateX(-2px);}

/* Alert toast */
.toast-notif{position:fixed;top:80px;right:24px;z-index:999;display:flex;align-items:center;gap:10px;padding:14px 20px;border-radius:var(--r-lg);font-size:14px;font-weight:700;box-shadow:0 8px 28px rgba(0,0,0,0.12);animation:slideIn 0.4s ease both,fadeOut 0.4s ease 3s both;}
@keyframes slideIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:none}}
@keyframes fadeOut{to{opacity:0;transform:translateX(40px)}}
.toast-success{background:var(--g100);color:var(--g700);border:1.5px solid rgba(34,197,94,0.3);}
.toast-deleted{background:var(--r100);color:var(--r700);border:1.5px solid rgba(239,68,68,0.3);}

/* Stat strip */
.stat-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:18px 22px;display:flex;align-items:center;gap:14px;transition:transform var(--ease),box-shadow var(--ease);position:relative;overflow:hidden;}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(232,50,138,0.12);}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:var(--r-lg) var(--r-lg) 0 0;}
.sc-pink::before{background:linear-gradient(90deg,var(--p500),var(--p300));}
.sc-orange::before{background:linear-gradient(90deg,var(--o500),#fcd34d);}
.sc-blue::before{background:linear-gradient(90deg,var(--b500),#93c5fd);}
.sc-green::before{background:linear-gradient(90deg,var(--g500),#86efac);}
.stat-ico{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.stat-lbl{font-size:11.5px;font-weight:600;color:var(--text3);margin-bottom:3px;}
.stat-val{font-family:'Quicksand',sans-serif;font-size:24px;font-weight:700;line-height:1;}

/* Cards grid */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:20px;}

/* Pesanan card */
.pesanan-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:visible;transition:transform var(--ease),box-shadow var(--ease);animation:fadeUp 0.35s ease both;}
.pesanan-card:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(232,50,138,0.13);}
<?php for($i=1;$i<=20;$i++): ?>
.pesanan-card:nth-child(<?=$i?>){animation-delay:<?=($i-1)*0.05?>s;}
<?php endfor; ?>

/* ══ CARD ══ */
.card-top{display:flex;align-items:center;justify-content:space-between;padding:14px 18px 10px;border-bottom:1.5px solid var(--border);}
.card-id{font-family:'Quicksand',sans-serif;font-size:13px;font-weight:800;color:var(--text3);letter-spacing:0.5px;}
.card-actions{display:flex;align-items:center;gap:6px;}
.status-badge{display:inline-flex;align-items:center;padding:4px 12px;border-radius:99px;font-size:11.5px;font-weight:700;}
.st-pending{background:var(--a100);color:var(--a700);}
.st-proses{background:var(--b100);color:var(--b700);}
.st-selesai{background:var(--g100);color:var(--g700);}
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--white);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;transition:all var(--ease);}
.act-edit{color:var(--b500);}.act-edit:hover{background:var(--b100);border-color:var(--b500);}
.act-del{color:var(--r500);}.act-del:hover{background:var(--r100);border-color:var(--r500);}
.card-body-inner{padding:14px 18px;}
.pelanggan-row{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.pel-av{width:38px;height:38px;border-radius:50%;background:var(--clr,var(--p400));display:flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:15px;font-weight:800;color:#fff;flex-shrink:0;}
.pel-name{font-size:14px;font-weight:700;color:var(--text);}
.pel-date{font-size:11.5px;color:var(--text3);font-weight:500;}
.item-box{background:var(--p50);border:1.5px solid var(--border);border-radius:var(--r-md);padding:12px 14px;margin-bottom:12px;}
.item-box-title{font-size:11px;font-weight:800;letter-spacing:0.7px;text-transform:uppercase;color:var(--text3);margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.item-row{display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px dashed var(--border2);}
.item-row:last-child{border-bottom:none;}
.item-name{font-size:13.5px;font-weight:700;color:var(--text);}
.item-meta{font-size:11.5px;color:var(--text3);}
.item-sub{font-size:13px;font-weight:700;color:var(--p500);}
.total-row{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border2);border-radius:var(--r-md);padding:10px 14px;margin-bottom:14px;}
.total-lbl{font-size:12px;font-weight:700;color:var(--text2);display:flex;align-items:center;gap:6px;}
.total-val{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:800;color:var(--p500);}
.card-form{background:var(--p50);border:1.5px solid var(--border);border-radius:var(--r-md);padding:12px 14px;}
.form-row-inline{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;}
.form-grp{display:flex;flex-direction:column;gap:4px;flex:1;}
.form-lbl{font-size:10.5px;font-weight:700;color:var(--text3);letter-spacing:0.5px;display:flex;align-items:center;gap:4px;}
.form-sel,.form-inp{width:100%;padding:7px 10px;border:1.5px solid var(--border2);border-radius:8px;font-family:'Nunito',sans-serif;font-size:13px;color:var(--text);background:var(--white);outline:none;transition:border-color var(--ease-plain);}
.form-sel:focus,.form-inp:focus{border-color:var(--p400);}
.update-btn{display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all var(--ease);white-space:nowrap;height:36px;}
.update-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(232,50,138,0.35);}

/* ══ EMPTY ══ */
.empty-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;text-align:center;}
.empty-icon{font-size:52px;margin-bottom:14px;opacity:0.3;}
.empty-title{font-family:'Quicksand',sans-serif;font-size:20px;font-weight:700;color:var(--text2);margin-bottom:6px;}
.empty-sub{font-size:13.5px;color:var(--text3);}

/* ══ MODAL ══ */
.modal-overlay{position:fixed;inset:0;background:rgba(61,26,40,0.45);backdrop-filter:blur(6px);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;pointer-events:none;transition:opacity 0.22s ease;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal-box{background:var(--white);border-radius:var(--r-xl);width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(61,26,40,0.22);transform:scale(0.96);transition:transform 0.22s ease;}
.modal-overlay.open .modal-box{transform:scale(1);}
.modal-hd{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1.5px solid var(--border);position:sticky;top:0;background:var(--white);z-index:1;}
.modal-title{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;}
.modal-close{width:32px;height:32px;border-radius:8px;border:1.5px solid var(--border);background:var(--white);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text3);font-size:14px;transition:all var(--ease);}
.modal-close:hover{background:var(--r100);color:var(--r500);border-color:var(--r500);}
.modal-body{padding:20px 22px;}
.m-form-group{display:flex;flex-direction:column;gap:5px;margin-bottom:16px;}
.m-label{font-size:11px;font-weight:800;letter-spacing:0.6px;text-transform:uppercase;color:var(--text3);display:flex;align-items:center;gap:6px;}
.m-control{width:100%;padding:9px 12px;border:1.5px solid var(--border2);border-radius:9px;font-family:'Nunito',sans-serif;font-size:13.5px;color:var(--text);background:var(--white);outline:none;transition:border-color var(--ease-plain);}
.m-control:focus{border-color:var(--p400);}
.item-edit-row{display:grid;grid-template-columns:1fr 80px 80px;gap:10px;padding:10px 0;border-bottom:1px dashed var(--border);}
.item-edit-row:last-child{border-bottom:none;}
.item-edit-row label{font-size:10.5px;font-weight:700;color:var(--text3);display:block;margin-bottom:3px;}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;padding:16px 22px;border-top:1.5px solid var(--border);background:var(--p50);}
.modal-cancel-btn{display:flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;background:var(--white);border:1.5px solid var(--border);color:var(--text2);font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:all var(--ease);}
.modal-cancel-btn:hover{background:var(--r100);color:var(--r700);border-color:var(--r500);}
.modal-save-btn{display:flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all var(--ease);}
.modal-save-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(232,50,138,0.35);}
</style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div>
            <div class="brand-name">Konveksi</div>
            <div class="brand-sub">Management</div>
        </div>
    </a>
    <div class="sb-owner">
        <div class="owner-av"><?=$inisial?></div>
        <div>
            <div class="owner-name"><?=htmlspecialchars($nama_owner)?></div>
            <div class="owner-role">Owner</div>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="nav-group-label">Menu Utama</div>
        <a href="dashboard.php"  class="nav-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="pesanan.php"    class="nav-item"><i class="bi bi-bag-heart"></i> Pesanan
            <?php if($notif_bayar > 0): ?>
            <span class="nav-pill pill-red"><?=$notif_bayar?></span>
            <?php endif; ?>
        </a>
        <a href="produksi.php"   class="nav-item active"><i class="bi bi-gear-fill"></i> Produksi</a>
        <a href="penggajian.php" class="nav-item"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <div class="nav-group-label">Manajemen</div>
        <a href="kelola_produk.php"   class="nav-item"><i class="bi bi-box-seam"></i> Produk</a>
        <a href="data_penjahit.php" class="nav-item"><i class="bi bi-people"></i> Penjahit</a>
        <a href="kelola_bahan.php"      class="nav-item"><i class="bi bi-archive"></i> Bahan Baku
            <?php if($stok_kritis > 0): ?>
            <span class="nav-pill pill-orange"><?=$stok_kritis?></span>
            <?php endif; ?>
        </a>
        <a href="kelola_aset.php" class="nav-item"><i class="bi bi-tools"></i> Aset</a>
        <div class="nav-group-label">Laporan</div>
        <a href="laporan.php" class="nav-item"><i class="bi bi-bar-chart-line"></i> Laporan</a>
        <a href="chat.php"    class="nav-item"><i class="bi bi-chat-dots"></i> Chat
            <?php if($notif_chat > 0): ?>
            <span class="nav-pill pill-pink"><?=$notif_chat?></span>
            <?php endif; ?>
        </a>
    </nav>
    <div class="sb-footer">
        <a href="../auth/logout.php" class="nav-item logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
    </div>
</aside>

<!-- ══ TOPBAR ══ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">🏭 Manajemen Produksi</div>
        <div class="tb-sub"><?=date('l, d F Y')?></div>
    </div>
    <div class="tb-actions">
        <?php if($total_notif > 0): ?>
        <a href="#" class="icon-btn"><i class="bi bi-bell"></i><span class="dot"></span></a>
        <?php endif; ?>
        <div class="date-pill"><i class="bi bi-calendar3"></i><?=date('d/m/Y')?></div>
    </div>
</header>

<!-- ══ TOAST ══ -->
<?php if($msg === 'updated'): ?>
<div class="toast-notif toast-success"><i class="bi bi-check-circle-fill"></i> Produksi berhasil diperbarui!</div>
<?php elseif($msg === 'deleted'): ?>
<div class="toast-notif toast-deleted"><i class="bi bi-trash3-fill"></i> Pesanan berhasil dihapus.</div>
<?php endif; ?>

<!-- ══ MAIN ══ -->
<main class="main">
<div class="content">

    <!-- Page Header -->
    <div class="page-hd anim">
        <div class="page-title-wrap">
            <div class="page-icon"><i class="bi bi-gear-fill"></i></div>
            <div>
                <div class="page-title">Produksi Aktif</div>
                <div class="page-subtitle">Kelola status, penjahit &amp; deadline pesanan berjalan</div>
            </div>
        </div>
        <a href="dashboard.php" class="back-btn"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <!-- Stat Strip -->
    <div class="stat-strip anim">
        <div class="stat-card sc-pink">
            <div class="stat-ico" style="background:var(--p100);color:var(--p500)"><i class="bi bi-gear-fill"></i></div>
            <div>
                <div class="stat-lbl">Total Aktif</div>
                <div class="stat-val" style="color:var(--p500)"><?=$total_produksi?></div>
            </div>
        </div>
        <div class="stat-card sc-orange">
            <div class="stat-ico" style="background:var(--a100);color:var(--a500)"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-lbl">Pending</div>
                <div class="stat-val" style="color:var(--a500)"><?=$total_pending?></div>
            </div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-ico" style="background:var(--b100);color:var(--b500)"><i class="bi bi-arrow-repeat"></i></div>
            <div>
                <div class="stat-lbl">Proses</div>
                <div class="stat-val" style="color:var(--b500)"><?=$total_proses?></div>
            </div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-ico" style="background:var(--g100);color:var(--g500)"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-lbl">Selesai</div>
                <div class="stat-val" style="color:var(--g500)"><?=$total_selesai?></div>
            </div>
        </div>
    </div>

    <!-- Cards Grid -->
    <?php
    $has_data    = false;
    $all_penjahit = [];
    $q_pj = mysqli_query($koneksi, "SELECT ID_PENJAHIT, NAMA_PENJAHIT FROM penjahit ORDER BY NAMA_PENJAHIT ASC");
    while ($pj = mysqli_fetch_assoc($q_pj)) $all_penjahit[] = $pj;

    $all_produk = [];
    $q_pr = mysqli_query($koneksi, "SELECT ID_PRODUK, NAMA_PRODUK, HARGA FROM produk ORDER BY NAMA_PRODUK ASC");
    while ($pr = mysqli_fetch_assoc($q_pr)) $all_produk[] = $pr;
    ?>

    <div class="cards-grid">

    <?php while ($row = mysqli_fetch_assoc($query)): ?>
    <?php
        $id_psn   = $row['ID_PESANAN'];
        $nama_pel = $row['NAMA_PELANGGAN'] ?? '';
        $huruf    = strtoupper(substr($nama_pel, 0, 1));
        $st       = $row['STATUS'] ?? 'Pending';
        $has_data = true;
    ?>

    <!-- ── Pesanan Card ── -->
    <div class="pesanan-card">

        <!-- Card Top -->
        <div class="card-top">
            <span class="card-id"># <?=$id_psn?></span>
            <div class="card-actions">
                <span class="status-badge st-<?=strtolower($st)?>"><?=$st?></span>
                <button class="act-btn act-edit" onclick="openModal('modal-<?=$id_psn?>')">
                    <i class="bi bi-pencil"></i>
                </button>
                <a href="produksi.php?hapus=<?=urlencode($id_psn)?>"
                   class="act-btn act-del"
                   onclick="return confirm('Hapus pesanan ini?')">
                    <i class="bi bi-trash3"></i>
                </a>
            </div>
        </div>

        <!-- Card Body -->
        <div class="card-body-inner">

            <!-- Pelanggan -->
            <div class="pelanggan-row">
                <div class="pel-av" style="--clr:var(--p400)"><?=$huruf?></div>
                <div>
                    <div class="pel-name"><?=htmlspecialchars($nama_pel)?></div>
                    <div class="pel-date">
                        <i class="bi bi-calendar3" style="font-size:10px"></i>
                        <?=date('d F Y', strtotime($row['WAKTU_PESAN']))?>
                    </div>
                </div>
            </div>

            <!-- Item Box -->
            <div class="item-box">
                <div class="item-box-title"><i class="bi bi-list-check"></i> Rincian Item</div>
                <?php
                $q_item = mysqli_query($koneksi,
                    "SELECT pr.NAMA_PRODUK, dp.*
                     FROM detail_pesanan dp
                     JOIN produk pr ON dp.ID_PRODUK = pr.ID_PRODUK
                     WHERE dp.ID_PESANAN='" . mysqli_real_escape_string($koneksi, $id_psn) . "'");

                if ($q_item && mysqli_num_rows($q_item) > 0):
                    while ($it = mysqli_fetch_assoc($q_item)):
                ?>
                <div class="item-row">
                    <div>
                        <div class="item-name"><?=htmlspecialchars($it['NAMA_PRODUK'])?></div>
                        <div class="item-meta">
                            Ukuran: <?=htmlspecialchars($it['UKURAN'] ?? '-')?> &middot;
                            <?=htmlspecialchars((string)($it['JUMLAH'] ?? ''))?> pcs
                        </div>
                        <div class="item-meta" style="color:var(--text2);font-style:italic;margin-top:3px;">
                            <i class="bi bi-info-circle"></i>
                            Keterangan: <?=htmlspecialchars($row['KETERANGAN'] ?? $row['keterangan'] ?? 'Tidak ada keterangan')?>
                        </div>
                    </div>
                    <div class="item-sub">Rp <?=number_format($it['SUBTOTAL'], 0, ',', '.')?></div>
                </div>
                <?php
                    endwhile;
                else:
                ?>
                <div style="color:var(--text3);font-style:italic;text-align:center;padding:10px 0;">
                    Belum ada item
                </div>
                <?php endif; ?>
            </div><!-- /.item-box -->

            <!-- Total -->
            <div class="total-row">
                <span class="total-lbl"><i class="bi bi-receipt"></i> Total Harga</span>
                <span class="total-val">Rp <?=number_format($row['TOTAL_HARGA'] ?? 0, 0, ',', '.')?></span>
            </div>

        </div><!-- /.card-body-inner -->

        <!-- Card Form -->
        <div class="card-form">
            <form method="POST" action="">
                <input type="hidden" name="id_pesanan" value="<?=$id_psn?>">
                <div class="form-row-inline">

                    <!-- Penjahit -->
                    <div class="form-grp">
                        <span class="form-lbl"><i class="bi bi-person-workspace"></i> Penjahit</span>
                        <select name="id_penjahit" class="form-sel">
                            <option value="">— Pilih Penjahit —</option>
                            <?php foreach ($all_penjahit as $pj): ?>
                            <option value="<?=$pj['ID_PENJAHIT']?>"
                                <?= $row['ID_PENJAHIT'] == $pj['ID_PENJAHIT'] ? 'selected' : '' ?>>
                                <?=htmlspecialchars($pj['NAMA_PENJAHIT'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Deadline -->
                    <div class="form-grp" style="max-width:150px">
                        <span class="form-lbl"><i class="bi bi-calendar-check"></i> Deadline</span>
                        <?php
                        $val_deadline = '';
                        if (!empty($row['ID_PENJAHIT'])) {
                            $q_dl = mysqli_query($koneksi,
                                "SELECT DEADLINE FROM produksi
                                 WHERE ID_PENJAHIT='" . mysqli_real_escape_string($koneksi, $row['ID_PENJAHIT']) . "'
                                 LIMIT 1");
                            if ($q_dl && mysqli_num_rows($q_dl) > 0) {
                                $dl_row       = mysqli_fetch_assoc($q_dl);
                                $val_deadline = $dl_row['DEADLINE'];
                            }
                        }
                        ?>
                        <input type="date" name="deadline" class="form-inp"
                               value="<?=htmlspecialchars($row['DEADLINE'] ?? $row['deadline'] ?? $val_deadline)?>">
                    </div>

                    <!-- Status -->
                    <div class="form-grp" style="max-width:130px">
                        <span class="form-lbl"><i class="bi bi-activity"></i> Status</span>
                        <select name="status" class="form-sel">
                            <option value="Pending" <?= $st === 'Pending' ? 'selected' : '' ?>>⏳ Pending</option>
                            <option value="Proses"  <?= $st === 'Proses'  ? 'selected' : '' ?>>🔄 Proses</option>
                            <option value="Selesai" <?= $st === 'Selesai' ? 'selected' : '' ?>>✅ Selesai</option>
                        </select>
                    </div>

                    <button type="submit" name="update_produksi" class="update-btn">
                        <i class="bi bi-arrow-clockwise"></i> Update
                    </button>

                </div>
            </form>
        </div><!-- /.card-form -->

    </div><!-- /.pesanan-card -->

    <?php endwhile; ?>

    <?php if (!$has_data): ?>
    <div class="empty-wrap">
        <div class="empty-icon"><i class="bi bi-gear"></i></div>
        <div class="empty-title">Tidak ada produksi aktif 🎉</div>
        <div class="empty-sub">Semua pesanan sudah selesai atau belum ada pesanan masuk.</div>
    </div>
    <?php endif; ?>

    </div><!-- /.cards-grid -->

</div><!-- /.content -->
</main>

<!-- ══ MODALS EDIT ══ -->
<?php
mysqli_data_seek($query, 0);
while ($row = mysqli_fetch_assoc($query)):
    $id_psn = $row['ID_PESANAN'];
    $q_edit = mysqli_query($koneksi,
        "SELECT * FROM detail_pesanan WHERE ID_PESANAN='" . mysqli_real_escape_string($koneksi, $id_psn) . "'");
?>
<div class="modal-overlay" id="modal-<?=$id_psn?>">
    <div class="modal-box">

        <div class="modal-hd">
            <div class="modal-title">
                <i class="bi bi-pencil-square"></i> Edit Pesanan #<?=$id_psn?>
            </div>
            <button class="modal-close" onclick="closeModal('modal-<?=$id_psn?>')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="id_pesanan" value="<?=$id_psn?>">

            <div class="modal-body">
                <div class="m-form-group">
                    <label class="m-label"><i class="bi bi-calendar3"></i> Tanggal Pesan</label>
                    <input type="date" name="tgl" class="m-control"
                           value="<?=date('Y-m-d', strtotime($row['WAKTU_PESAN']))?>">
                </div>

                <div style="font-size:11px;font-weight:800;letter-spacing:0.7px;text-transform:uppercase;color:var(--text3);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                    <i class="bi bi-list-check" style="color:var(--p400)"></i> Item Pesanan
                </div>

                <?php while ($ed = mysqli_fetch_assoc($q_edit)): ?>
                <div class="item-edit-row">
                    <div>
                        <label>Produk</label>
                        <select name="id_produk[]" class="m-control">
                            <?php foreach ($all_produk as $p): ?>
                            <option value="<?=$p['ID_PRODUK']?>"
                                <?= $ed['ID_PRODUK'] == $p['ID_PRODUK'] ? 'selected' : '' ?>>
                                <?=htmlspecialchars($p['NAMA_PRODUK'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Ukuran</label>
                        <input type="text" name="ukuran[]" class="m-control"
                               value="<?=htmlspecialchars($ed['UKURAN'])?>" placeholder="S/M/L/XL">
                    </div>
                    <div>
                        <label>Jumlah</label>
                        <input type="number" name="jumlah[]" class="m-control"
                               value="<?=htmlspecialchars($ed['JUMLAH'])?>" min="1">
                    </div>
                </div>
                <?php endwhile; ?>

            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <button type="button" class="modal-cancel-btn" onclick="closeModal('modal-<?=$id_psn?>')">
                    <i class="bi bi-x"></i> Batal
                </button>
                <button type="submit" name="simpan_edit_total" class="modal-save-btn">
                    <i class="bi bi-floppy-fill"></i> Simpan Perubahan
                </button>
            </div>

        </form>
    </div><!-- /.modal-box -->
</div><!-- /.modal-overlay -->
<?php endwhile; ?>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) closeModal(el.id);
    });
});
setTimeout(function() {
    var t = document.querySelector('.toast-notif');
    if (t) t.style.display = 'none';
}, 3500);
</script>
</body>
</html>