<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

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

// ── Folder upload
$upload_dir = "../uploads/produk/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// ── Helper upload foto
function uploadFoto($file, $upload_dir) {
    if (empty($file['name'])) return null;
    $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allow = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allow)) return false;
    if ($file['size'] > 3 * 1024 * 1024) return false;
    $nama  = 'produk_' . time() . '_' . rand(100,999) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $upload_dir . $nama);
    return $nama;
}

$flash = '';

// 1. TAMBAH PRODUK
if (isset($_POST['tambah'])) {
    $id          = mysqli_real_escape_string($koneksi, trim($_POST['id_produk']));
    $nama        = mysqli_real_escape_string($koneksi, trim($_POST['nama_produk']));
    $jenis_bahan = mysqli_real_escape_string($koneksi, trim($_POST['jenis_bahan']));
    $ukuran      = mysqli_real_escape_string($koneksi, trim($_POST['ukuran']));
    $harga       = mysqli_real_escape_string($koneksi, $_POST['harga']);
    $foto_val    = 'NULL';

    if (!empty($_FILES['foto_produk']['name'])) {
        $hasil = uploadFoto($_FILES['foto_produk'], $upload_dir);
        if ($hasil === false) { $flash = 'error:Format foto tidak valid atau ukuran > 3MB.'; }
        elseif ($hasil) { $foto_val = "'$hasil'"; }
    }

    if (!$flash) {
        $q = "INSERT INTO produk (ID_PRODUK, NAMA_PRODUK, JENIS_BAHAN, UKURAN, HARGA, FOTO_PRODUK)
              VALUES ('$id','$nama','$jenis_bahan','$ukuran','$harga',$foto_val)";
        if (mysqli_query($koneksi, $q)) $flash = 'ok:Produk berhasil ditambahkan! 🎉';
        else $flash = 'error:Gagal menyimpan produk.';
    }
}

// 2. UPDATE PRODUK
if (isset($_POST['update'])) {
    $id          = mysqli_real_escape_string($koneksi, $_POST['id_produk']);
    $nama        = mysqli_real_escape_string($koneksi, trim($_POST['nama_produk']));
    $jenis_bahan = mysqli_real_escape_string($koneksi, trim($_POST['jenis_bahan']));
    $ukuran      = mysqli_real_escape_string($koneksi, trim($_POST['ukuran']));
    $harga       = mysqli_real_escape_string($koneksi, $_POST['harga']);
    $foto_set    = '';

    if (!empty($_FILES['foto_produk']['name'])) {
        $hasil = uploadFoto($_FILES['foto_produk'], $upload_dir);
        if ($hasil === false) { $flash = 'error:Format foto tidak valid atau ukuran > 3MB.'; }
        elseif ($hasil) {
            // Hapus foto lama
            $lama = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT FOTO_PRODUK FROM produk WHERE ID_PRODUK='$id'"));
            if ($lama && $lama['FOTO_PRODUK'] && file_exists($upload_dir.$lama['FOTO_PRODUK'])) {
                unlink($upload_dir.$lama['FOTO_PRODUK']);
            }
            $foto_set = ", FOTO_PRODUK='$hasil'";
        }
    }

    if (!$flash) {
        $q = "UPDATE produk SET NAMA_PRODUK='$nama', JENIS_BAHAN='$jenis_bahan', UKURAN='$ukuran', HARGA='$harga'$foto_set
              WHERE ID_PRODUK='$id'";
        if (mysqli_query($koneksi, $q)) $flash = 'ok:Produk berhasil diperbarui! ✨';
        else $flash = 'error:Gagal memperbarui produk.';
    }
}

// 3. HAPUS PRODUK
if (isset($_GET['hapus'])) {
    $id  = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    $lama = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT FOTO_PRODUK FROM produk WHERE ID_PRODUK='$id'"));
    if ($lama && $lama['FOTO_PRODUK'] && file_exists($upload_dir.$lama['FOTO_PRODUK'])) {
        unlink($upload_dir.$lama['FOTO_PRODUK']);
    }
    mysqli_query($koneksi, "DELETE FROM produk WHERE ID_PRODUK='$id'");
    header("Location: kelola_produk.php?deleted=1");
    exit;
}
if (isset($_GET['deleted'])) $flash = 'ok:Produk berhasil dihapus.';

// Fetch semua produk
$rows = [];
$q    = mysqli_query($koneksi, "SELECT * FROM produk ORDER BY ID_PRODUK ASC");
while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
$total_produk = count($rows);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Produk 📦 | Konveksi Apps</title>
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
.brand-mark{width:38px;height:38px;border-radius:13px;background:linear-gradient(135deg,var(--p500) 0%,var(--p400) 50%,var(--v500) 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4),0 0 0 3px rgba(232,50,138,0.12);transition:transform var(--ease),box-shadow var(--ease)}
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

/* Flash toast */
.toast-wrap{position:fixed;top:80px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.toast-msg{display:flex;align-items:center;gap:10px;padding:13px 20px;border-radius:var(--r-lg);font-size:13.5px;font-weight:700;box-shadow:0 8px 28px rgba(0,0,0,0.12);animation:slideIn 0.3s cubic-bezier(0.34,1.56,0.64,1);border:1.5px solid}
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
.btn-tambah{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:99px;font-size:13.5px;font-weight:700;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border:none;cursor:pointer;transition:all var(--ease);box-shadow:0 4px 16px rgba(232,50,138,0.4);text-decoration:none}
.btn-tambah:hover{transform:translateY(-2px) scale(1.03);box-shadow:0 8px 24px rgba(232,50,138,0.5);color:#fff}

/* Search bar */
.search-row{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.search-box{display:flex;align-items:center;gap:10px;background:var(--white);border:1.5px solid var(--border);border-radius:99px;padding:9px 18px;flex:1;max-width:360px;transition:border-color var(--ease-plain)}
.search-box:focus-within{border-color:var(--p400);box-shadow:0 0 0 3px rgba(232,50,138,0.1)}
.search-box i{color:var(--p400);font-size:15px;flex-shrink:0}
.search-box input{border:none;outline:none;background:none;font-family:'Nunito',sans-serif;font-size:14px;color:var(--text);width:100%}
.search-box input::placeholder{color:var(--text3)}
.count-pill{display:inline-flex;align-items:center;gap:6px;background:var(--p50);border:1.5px solid var(--border2);border-radius:99px;padding:7px 16px;font-size:12.5px;font-weight:700;color:var(--p600)}

/* Product grid */
.prod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.prod-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--r-xl);overflow:hidden;transition:transform var(--ease),box-shadow var(--ease);display:flex;flex-direction:column}
.prod-card:hover{transform:translateY(-5px);box-shadow:0 16px 40px rgba(232,50,138,0.13);border-color:var(--border2)}
.prod-img-wrap{position:relative;width:100%;aspect-ratio:4/3;background:linear-gradient(135deg,var(--p50),var(--v100));overflow:hidden;flex-shrink:0}
.prod-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease}
.prod-card:hover .prod-img-wrap img{transform:scale(1.06)}
.prod-img-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:var(--p300)}
.prod-img-placeholder i{font-size:36px}
.prod-img-placeholder span{font-size:11.5px;font-weight:600;color:var(--text3)}
.prod-id-badge{position:absolute;top:10px;left:10px;background:rgba(255,255,255,0.92);border:1px solid var(--border2);border-radius:8px;padding:3px 9px;font-size:11.5px;font-weight:700;color:var(--p600);backdrop-filter:blur(4px)}
.prod-body{padding:16px;flex:1;display:flex;flex-direction:column}
.prod-name{font-family:'Quicksand',sans-serif;font-size:15px;font-weight:700;color:var(--text);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prod-bahan{font-size:12px;color:var(--text3);font-weight:600;font-style:italic;margin-bottom:10px}
.prod-sizes{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px}
.size-tag{background:var(--b100);color:var(--b700);border-radius:7px;padding:2px 9px;font-size:11.5px;font-weight:700}
.prod-price{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;color:var(--g700);margin-top:auto}
.prod-actions{display:flex;gap:8px;margin-top:12px}
.btn-edit{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:8px;border-radius:99px;font-size:12.5px;font-weight:700;background:var(--p50);color:var(--p600);border:1.5px solid var(--border2);cursor:pointer;transition:all var(--ease-plain);text-decoration:none}
.btn-edit:hover{background:var(--p500);color:#fff;border-color:var(--p500)}
.btn-hapus{display:flex;align-items:center;justify-content:center;gap:5px;padding:8px 14px;border-radius:99px;font-size:12.5px;font-weight:700;background:var(--r100);color:var(--r700);border:1.5px solid rgba(239,68,68,0.2);cursor:pointer;transition:all var(--ease-plain);text-decoration:none}
.btn-hapus:hover{background:var(--r500);color:#fff;border-color:var(--r500)}

/* Empty state */
.empty-state{padding:72px 24px;text-align:center;grid-column:1/-1}
.empty-ico{font-size:52px;color:var(--p200);display:block;margin-bottom:14px}
.empty-text{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;color:var(--text2)}
.empty-sub{font-size:13px;color:var(--text3);margin-top:5px}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(61,26,40,0.45);backdrop-filter:blur(6px);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.open{display:flex;animation:fadeIn 0.2s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-box{background:var(--white);border-radius:var(--r-xl);width:100%;max-width:500px;margin:16px;overflow:hidden;box-shadow:0 24px 64px rgba(61,26,40,0.25);animation:slideUp 0.25s cubic-bezier(0.34,1.56,0.64,1);max-height:90vh;display:flex;flex-direction:column}
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:none;opacity:1}}
.modal-header{padding:20px 24px 16px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white));display:flex;align-items:center;gap:12px;flex-shrink:0}
.modal-hico{width:42px;height:42px;border-radius:13px;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0;box-shadow:0 4px 14px rgba(232,50,138,0.4)}
.modal-htitle{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text)}
.modal-hsub{font-size:12px;color:var(--text3);margin-top:2px;font-weight:500}
.modal-close{margin-left:auto;width:30px;height:30px;border-radius:50%;background:var(--p50);border:none;color:var(--text3);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all var(--ease-plain)}
.modal-close:hover{background:var(--r100);color:var(--r500)}
.modal-scroll{overflow-y:auto;flex:1;min-height:0}
.modal-body{padding:22px 24px}
.form-group{margin-bottom:16px}
.form-lbl{font-size:12.5px;font-weight:700;color:var(--text2);margin-bottom:6px;display:block}
.form-lbl span{color:var(--r500)}
.form-ctrl{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-family:'Nunito',sans-serif;font-size:14px;color:var(--text);background:var(--white);transition:border-color var(--ease-plain),box-shadow var(--ease-plain);outline:none}
.form-ctrl:focus{border-color:var(--p400);box-shadow:0 0 0 3px rgba(232,50,138,0.1)}
.form-ctrl::placeholder{color:var(--text3)}
.form-hint{font-size:11px;color:var(--text3);margin-top:4px}

/* Upload foto dalam modal */
.foto-upload-area{border:2px dashed var(--border2);border-radius:var(--r-lg);overflow:hidden;transition:border-color var(--ease-plain)}
.foto-upload-area:has(input:focus){border-color:var(--p400)}
.foto-preview-box{width:100%;height:160px;;position:relative;background:linear-gradient(135deg,var(--p50),var(--v100));display:flex;align-items:center;justify-content:center;overflow:hidden}
.foto-preview-box img{width:100%;height:100%;object-fit:cover;display:none}
.foto-preview-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:var(--p300);width:100%;height:100%;position:absolute;inset:0}
.foto-preview-placeholder i{font-size:32px}
.foto-preview-placeholder span{font-size:12px;font-weight:600;color:var(--text3)}
.foto-upload-btn{display:flex;align-items:center;gap:8px;padding:11px 18px;border-top:1.5px dashed var(--border2);cursor:pointer;background:var(--p50);transition:background var(--ease-plain)}
.foto-upload-btn:hover{background:var(--p100)}
.foto-upload-btn i{color:var(--p500);font-size:16px}
.foto-upload-btn span{font-size:13px;font-weight:600;color:var(--text2)}
.foto-upload-btn .file-name{font-size:11.5px;color:var(--p600);font-weight:700;margin-left:auto}
.foto-upload-btn input[type=file]{display:none}

.modal-footer-custom{padding:16px 24px;border-top:1.5px solid var(--border);display:flex;justify-content:flex-end;gap:10px;flex-shrink:0}
.btn-cancel{padding:9px 20px;border-radius:99px;font-size:13px;font-weight:700;background:var(--p50);color:var(--text2);border:1.5px solid var(--border);cursor:pointer;transition:all var(--ease-plain)}
.btn-cancel:hover{background:var(--p100);color:var(--text)}
.btn-submit{padding:9px 24px;border-radius:99px;font-size:13px;font-weight:700;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border:none;cursor:pointer;transition:all var(--ease);box-shadow:0 4px 14px rgba(232,50,138,0.35);display:flex;align-items:center;gap:7px}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(232,50,138,0.45)}

/* Konfirmasi hapus mini-modal */
.confirm-overlay{display:none;position:fixed;inset:0;background:rgba(61,26,40,0.5);backdrop-filter:blur(6px);z-index:2000;align-items:center;justify-content:center}
.confirm-overlay.open{display:flex;animation:fadeIn 0.15s ease}
.confirm-box{background:var(--white);border-radius:var(--r-xl);padding:28px 28px 22px;max-width:360px;width:calc(100% - 32px);box-shadow:0 20px 60px rgba(61,26,40,0.25);animation:slideUp 0.2s cubic-bezier(0.34,1.56,0.64,1);text-align:center}
.confirm-ico{width:56px;height:56px;border-radius:50%;background:var(--r100);display:flex;align-items:center;justify-content:center;font-size:26px;color:var(--r500);margin:0 auto 16px}
.confirm-title{font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px}
.confirm-sub{font-size:13px;color:var(--text3);margin-bottom:22px}
.confirm-actions{display:flex;gap:10px}
.btn-confirm-cancel{flex:1;padding:10px;border-radius:99px;font-size:13px;font-weight:700;background:var(--p50);color:var(--text2);border:1.5px solid var(--border);cursor:pointer;transition:all var(--ease-plain)}
.btn-confirm-cancel:hover{background:var(--p100)}
.btn-confirm-del{flex:1;padding:10px;border-radius:99px;font-size:13px;font-weight:700;background:linear-gradient(135deg,var(--r500),#dc2626);color:#fff;border:none;cursor:pointer;transition:all var(--ease);box-shadow:0 4px 14px rgba(239,68,68,0.3)}
.btn-confirm-del:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(239,68,68,0.4)}

@media(max-width:900px){.sidebar{transform:translateX(-100%)}.topbar{left:0}.main{margin-left:0}.prod-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.prod-grid{grid-template-columns:1fr}}
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
        <a class="nav-item active" href="kelola_produk.php"><i class="bi bi-box-seam"></i> Produk</a>
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
        <a class="nav-item" href="laporan.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Keuangan</a>
    </nav>
    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Kelola Produk 📦</div>
        <div class="tb-sub">Tambah, edit, dan atur katalog produk konveksi kamu</div>
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

<!-- ════ FLASH TOAST ════ -->
<?php if ($flash): [$type, $msg] = explode(':', $flash, 2); ?>
<div class="toast-wrap" id="toastWrap">
    <div class="toast-msg <?= $type === 'ok' ? 'toast-ok' : 'toast-err' ?>">
        <i class="bi <?= $type === 'ok' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toastWrap');if(t)t.style.opacity='0';setTimeout(()=>{if(t)t.remove()},400)},3200)</script>
<?php endif; ?>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content anim">

    <!-- Page header -->
    <div class="page-header">
        <div class="ph-left">
            <div class="ph-icon"><i class="bi bi-box-seam"></i></div>
            <div>
                <div class="ph-title">Katalog Produk</div>
                <div class="ph-sub"><?= $total_produk ?> produk terdaftar dalam sistem</div>
            </div>
        </div>
        <div class="ph-right">
            <button class="btn-tambah" onclick="openModal('modal-tambah')">
                <i class="bi bi-plus-circle-fill"></i> Tambah Produk
            </button>
        </div>
    </div>

    <!-- Search & count -->
    <div class="search-row">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Cari nama produk, jenis bahan..." oninput="filterProduk()">
        </div>
        <div class="count-pill"><i class="bi bi-box-seam"></i> <span id="countLabel"><?= $total_produk ?></span> produk</div>
    </div>

    <!-- Product grid -->
    <div class="prod-grid" id="prodGrid">
    <?php if (!empty($rows)): foreach ($rows as $p):
        $sizes = array_filter(array_map('trim', explode(',', $p['UKURAN'])));
        $foto_path = (!empty($p['FOTO_PRODUK']) && file_exists($upload_dir.$p['FOTO_PRODUK']))
            ? '../uploads/produk/'.$p['FOTO_PRODUK'] : null;
    ?>
        <div class="prod-card" data-search="<?= strtolower($p['NAMA_PRODUK'].' '.$p['JENIS_BAHAN'].' '.$p['ID_PRODUK']) ?>">
            <div class="prod-img-wrap">
                <?php if ($foto_path): ?>
                    <img src="<?= htmlspecialchars($foto_path) ?>" alt="<?= htmlspecialchars($p['NAMA_PRODUK']) ?>">
                <?php else: ?>
                    <div class="prod-img-placeholder">
                        <i class="bi bi-image"></i>
                        <span>Belum ada foto</span>
                    </div>
                <?php endif; ?>
                <span class="prod-id-badge"><?= htmlspecialchars($p['ID_PRODUK']) ?></span>
            </div>
            <div class="prod-body">
                <div class="prod-name" title="<?= htmlspecialchars($p['NAMA_PRODUK']) ?>"><?= htmlspecialchars($p['NAMA_PRODUK']) ?></div>
                <div class="prod-bahan"><i class="bi bi-scissors"></i> <?= htmlspecialchars($p['JENIS_BAHAN']) ?></div>
                <div class="prod-sizes">
                    <?php foreach ($sizes as $s): ?>
                    <span class="size-tag"><?= htmlspecialchars($s) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="prod-price">Rp <?= number_format($p['HARGA']) ?></div>
                <div class="prod-actions">
                    <button class="btn-edit" onclick="openModal('modal-edit-<?= $p['ID_PRODUK'] ?>')">
                        <i class="bi bi-pencil-square"></i> Edit
                    </button>
                    <button class="btn-hapus" onclick="konfirmasiHapus('<?= $p['ID_PRODUK'] ?>','<?= htmlspecialchars(addslashes($p['NAMA_PRODUK'])) ?>')">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; else: ?>
        <div class="empty-state">
            <i class="bi bi-box-seam empty-ico"></i>
            <div class="empty-text">Belum ada produk nih 📦</div>
            <div class="empty-sub">Yuk tambah produk pertama kamu!</div>
        </div>
    <?php endif; ?>
    </div>

</div>
</main>

<!-- ════ MODAL TAMBAH ════ -->
<div class="modal-overlay" id="modal-tambah">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-hico"><i class="bi bi-plus-circle-fill"></i></div>
            <div>
                <div class="modal-htitle">Tambah Produk Baru</div>
                <div class="modal-hsub">Isi detail produk dan upload foto katalog</div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-tambah')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data">
        <div class="modal-scroll">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-lbl">Foto Produk <span style="color:var(--text3);font-weight:500">(opsional)</span></label>
                <div class="foto-upload-area">
                    <div class="foto-preview-box" id="prev-box-tambah">
                        <div class="foto-preview-placeholder" id="prev-placeholder-tambah">
                            <i class="bi bi-image"></i>
                            <span>Preview foto akan muncul di sini</span>
                        </div>
                        <img id="prev-img-tambah" src="" alt="preview">
                    </div>
                    <label class="foto-upload-btn" for="foto-tambah">
                        <i class="bi bi-cloud-upload-fill"></i>
                        <span>Pilih foto produk</span>
                        <span class="file-name" id="fname-tambah">JPG, PNG, WEBP · maks 3MB</span>
                        <input type="file" id="foto-tambah" name="foto_produk" accept="image/*"
                            onchange="previewFoto(this,'prev-img-tambah','prev-placeholder-tambah','fname-tambah')">
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-lbl">ID Produk <span>*</span></label>
                <input type="text" name="id_produk" class="form-ctrl" placeholder="cth: PRD-001" required>
                <div class="form-hint">ID unik, tidak bisa diubah setelah disimpan</div>
            </div>
            <div class="form-group">
                <label class="form-lbl">Nama Produk <span>*</span></label>
                <input type="text" name="nama_produk" class="form-ctrl" placeholder="cth: Seragam Sekolah SD" required>
            </div>
            <div class="form-group">
                <label class="form-lbl">Jenis Bahan <span>*</span></label>
                <input type="text" name="jenis_bahan" class="form-ctrl" placeholder="cth: Katun Drill / Polyester" required>
            </div>
            <div class="form-group">
                <label class="form-lbl">Ukuran Tersedia <span>*</span></label>
                <input type="text" name="ukuran" class="form-ctrl" placeholder="cth: S, M, L, XL, XXL" required>
                <div class="form-hint">Pisahkan dengan koma</div>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-lbl">Estimasi Harga (Rp) <span>*</span></label>
                <input type="number" name="harga" class="form-ctrl" placeholder="cth: 85000" required>
            </div>
        </div>
        </div>
        <div class="modal-footer-custom">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-tambah')">Batal</button>
            <button type="submit" name="tambah" class="btn-submit"><i class="bi bi-check-circle-fill"></i> Simpan Produk</button>
        </div>
        </form>
    </div>
</div>

<!-- ════ MODAL EDIT ════ -->
<?php foreach ($rows as $p):
    $foto_path = (!empty($p['FOTO_PRODUK']) && file_exists($upload_dir.$p['FOTO_PRODUK']))
        ? '../uploads/produk/'.$p['FOTO_PRODUK'] : null;
    $eid = $p['ID_PRODUK'];
?>
<div class="modal-overlay" id="modal-edit-<?= $eid ?>">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-hico"><i class="bi bi-pencil-square"></i></div>
            <div>
                <div class="modal-htitle">Edit Produk</div>
                <div class="modal-hsub"><?= htmlspecialchars($p['NAMA_PRODUK']) ?> · <?= htmlspecialchars($eid) ?></div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-edit-<?= $eid ?>')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_produk" value="<?= $eid ?>">
        <div class="modal-scroll">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-lbl">Foto Produk <span style="color:var(--text3);font-weight:500">(kosongkan jika tidak diganti)</span></label>
                <div class="foto-upload-area">
                    <div class="foto-preview-box" id="prev-box-<?= $eid ?>">
                        <div class="foto-preview-placeholder" id="prev-placeholder-<?= $eid ?>" <?= $foto_path ? 'style="display:none"' : '' ?>>
                            <i class="bi bi-image"></i>
                            <span>Belum ada foto</span>
                        </div>
                        <img id="prev-img-<?= $eid ?>" src="<?= $foto_path ? htmlspecialchars($foto_path) : '' ?>"
                             alt="preview" <?= $foto_path ? 'style="display:block"' : '' ?>>
                    </div>
                    <label class="foto-upload-btn" for="foto-<?= $eid ?>">
                        <i class="bi bi-cloud-upload-fill"></i>
                        <span><?= $foto_path ? 'Ganti foto' : 'Pilih foto produk' ?></span>
                        <span class="file-name" id="fname-<?= $eid ?>">JPG, PNG, WEBP · maks 3MB</span>
                        <input type="file" id="foto-<?= $eid ?>" name="foto_produk" accept="image/*"
                            onchange="previewFoto(this,'prev-img-<?= $eid ?>','prev-placeholder-<?= $eid ?>','fname-<?= $eid ?>')">
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-lbl">Nama Produk <span>*</span></label>
                <input type="text" name="nama_produk" class="form-ctrl" value="<?= htmlspecialchars($p['NAMA_PRODUK']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-lbl">Jenis Bahan <span>*</span></label>
                <input type="text" name="jenis_bahan" class="form-ctrl" value="<?= htmlspecialchars($p['JENIS_BAHAN']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-lbl">Ukuran Tersedia <span>*</span></label>
                <input type="text" name="ukuran" class="form-ctrl" value="<?= htmlspecialchars($p['UKURAN']) ?>" required>
                <div class="form-hint">Pisahkan dengan koma</div>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-lbl">Estimasi Harga (Rp) <span>*</span></label>
                <input type="number" name="harga" class="form-ctrl" value="<?= $p['HARGA'] ?>" required>
            </div>
        </div>
        </div>
        <div class="modal-footer-custom">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-<?= $eid ?>')">Batal</button>
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
        <div class="confirm-title">Hapus Produk?</div>
        <div class="confirm-sub" id="confirmSub">Produk ini akan dihapus permanen beserta fotonya.</div>
        <div class="confirm-actions">
            <button class="btn-confirm-cancel" onclick="closeConfirm()">Batal</button>
            <a class="btn-confirm-del" id="confirmLink" href="#">Ya, Hapus</a>
        </div>
    </div>
</div>

<script>
// Modal helpers
function openModal(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e){ if(e.target===this) closeModal(this.id); });
});
document.addEventListener('keydown', e => {
    if (e.key==='Escape') {
        document.querySelectorAll('.modal-overlay.open,.confirm-overlay.open').forEach(el => {
            el.classList.remove('open'); document.body.style.overflow='';
        });
    }
});

// Foto preview
function previewFoto(input, imgId, placeholderId, fnameId) {
    const img = document.getElementById(imgId);
    const ph  = document.getElementById(placeholderId);
    const fn  = document.getElementById(fnameId);
    if (input.files && input.files[0]) {
        const file = input.files[0];
        fn.textContent = file.name;
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            img.style.display = 'block';
            if (ph) ph.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
}

// Konfirmasi hapus
function konfirmasiHapus(id, nama) {
    document.getElementById('confirmSub').textContent = '"' + nama + '" akan dihapus permanen beserta fotonya.';
    document.getElementById('confirmLink').href = 'kelola_produk.php?hapus=' + encodeURIComponent(id);
    document.getElementById('confirmOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('confirmOverlay').addEventListener('click', function(e){
    if(e.target===this) closeConfirm();
});

// Search / filter
function filterProduk() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('#prodGrid .prod-card');
    let visible = 0;
    cards.forEach(c => {
        const match = c.dataset.search.includes(q);
        c.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('countLabel').textContent = visible;
}
</script>
</body>
</html>