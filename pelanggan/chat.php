<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'pelanggan') {
    header("Location: ../index.php");
    exit;
}

$id_pelanggan = $_SESSION['id'];
$nama_user    = $_SESSION['user'];
$initials     = strtoupper(substr($nama_user, 0, 2));

$total_pesan   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan'"))['n'];
$total_pending = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Pending'"))['n'];
$total_selesai = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pesanan WHERE ID_PELANGGAN='$id_pelanggan' AND STATUS='Selesai'"))['n'];

// =============================================
// LOGIKA SESI
// =============================================
$buat_baru = isset($_GET['new']) && $_GET['new'] == '1';
if ($buat_baru) {
    $id_sesi = 'SESI' . $id_pelanggan . time();
    mysqli_query($koneksi, "INSERT INTO chat_sesi (ID_SESI, ID_PELANGGAN, STATUS) VALUES ('$id_sesi', '$id_pelanggan', 'bot')");
    header("Location: chat.php?sesi=$id_sesi");
    exit;
}

if (isset($_GET['sesi'])) {
    $sesi_param = mysqli_real_escape_string($koneksi, $_GET['sesi']);
    $cek_sesi   = mysqli_query($koneksi, "SELECT * FROM chat_sesi WHERE ID_SESI = '$sesi_param' AND ID_PELANGGAN = '$id_pelanggan' LIMIT 1");
    if (mysqli_num_rows($cek_sesi) > 0) {
        $sesi        = mysqli_fetch_assoc($cek_sesi);
        $id_sesi     = $sesi['ID_SESI'];
        $status_sesi = $sesi['STATUS'];
    } else {
        header("Location: chat.php"); exit;
    }
} else {
    $cek_sesi = mysqli_query($koneksi,
    "SELECT * FROM chat_sesi
    WHERE ID_PELANGGAN = '$id_pelanggan'
    AND STATUS != 'selesai'
    ORDER BY DIBUAT_PADA DESC
    LIMIT 1");
    if (mysqli_num_rows($cek_sesi) > 0) {
        $sesi    = mysqli_fetch_assoc($cek_sesi);
        $id_sesi = $sesi['ID_SESI'];
        header("Location: chat.php?sesi=$id_sesi"); exit;
    } else {
        mysqli_query($koneksi,
        "INSERT INTO chat_sesi
        (ID_PELANGGAN, STATUS)
        VALUES
        ('$id_pelanggan', 'aktif')");

        $id_sesi = mysqli_insert_id($koneksi);

        header("Location: chat.php?sesi=$id_sesi");
        exit;
    }
}

$sesi_aktif_query = mysqli_query($koneksi, "
SELECT ID_SESI
FROM chat_sesi
WHERE ID_PELANGGAN = '$id_pelanggan'
AND STATUS != 'selesai'
ORDER BY DIBUAT_PADA DESC
LIMIT 1");
$sesi_aktif_row   = mysqli_fetch_assoc($sesi_aktif_query);
$is_readonly      = ($sesi_aktif_row && $sesi_aktif_row['ID_SESI'] != $id_sesi);

$semua_sesi = mysqli_query($koneksi, "
SELECT ID_SESI, STATUS, DIBUAT_PADA
FROM chat_sesi
WHERE ID_PELANGGAN = '$id_pelanggan'
ORDER BY DIBUAT_PADA DESC");

// =============================================
// AJAX HANDLER
// =============================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] == 'kirim') {
        if ($is_readonly) { echo json_encode(['status' => 'readonly', 'pesan' => '']); exit; }

        $pesan       = mysqli_real_escape_string($koneksi, $_POST['pesan']);
        mysqli_query($koneksi, "INSERT INTO chat_pesan (ID_SESI, PENGIRIM, ISI_PESAN) VALUES ('$id_sesi', 'pelanggan', '$pesan')");

        if ($status_sesi == 'bot') {
            $pesan_asli  = $_POST['pesan'];
            $pesan_lower = strtolower($pesan_asli);
            $balas_bot   = null;
            $eskalasi    = false;

            if (preg_match('/halo|hai|hello|hi|selamat/', $pesan_lower)) {
                $balas_bot = "Halo kak *$nama_user*! 👋 Selamat datang di Konveksi Apps.\nAda yang bisa saya bantu?\n\n1️⃣ Info Produk\n2️⃣ Cara Pemesanan\n3️⃣ Status Pesanan\n4️⃣ Info Harga\n5️⃣ Hubungi Admin";
            } elseif (preg_match('/^1$|info produk|produk|katalog|baju|seragam|kaos|kemeja|jaket/', $pesan_lower)) {
                $balas_bot = "Kami menyediakan berbagai produk konveksi berkualitas:\n\n👕 Kaos / T-Shirt\n👔 Kemeja\n🧥 Jaket\n👗 Seragam\n👘 Busana Muslim\n\nSemua bisa custom sesuai kebutuhan kamu! Mau lihat katalog lengkap? Kunjungi menu *Pesan Produk* ya kak 😊";
            } elseif (preg_match('/^2$|cara pem|cara pesan|cara order|gimana pesan|bagaimana pesan|order|pemesanan/', $pesan_lower)) {
                $balas_bot = "Cara pemesanan di Konveksi Apps:\n\n1️⃣ Pilih produk\n2️⃣ Tentukan jumlah\n3️⃣ Klik *Kirim Pesanan*\n4️⃣ Lakukan pembayaran\n5️⃣ Pesanan diproses & dikirim!\n\nYuk langsung pesan sekarang 👇\n[LINK:pesan.php|🛒 Buka Form Pemesanan]";
            } elseif (preg_match('/^3$|status|pesanan saya|cek pesanan|tracking/', $pesan_lower)) {
                $q_pesanan = mysqli_query($koneksi, "SELECT ID_PESANAN, STATUS FROM pesanan WHERE ID_PELANGGAN = '$id_pelanggan' ORDER BY WAKTU_PESAN DESC LIMIT 3");
                if (mysqli_num_rows($q_pesanan) > 0) {
                    $balas_bot = "Berikut status pesanan terbaru kamu kak:\n\n";
                    while ($p = mysqli_fetch_assoc($q_pesanan)) {
                        $emoji = $p['STATUS'] == 'Selesai' ? '✅' : ($p['STATUS'] == 'Proses' ? '🔄' : '⏳');
                        $balas_bot .= "$emoji *{$p['ID_PESANAN']}* — {$p['STATUS']}\n";
                    }
                    $balas_bot .= "\nUntuk detail lengkap cek menu *Status Pesanan* ya kak!";
                } else {
                    $balas_bot = "Kamu belum memiliki pesanan kak. Yuk mulai pesan sekarang! 😊";
                }
            } elseif (preg_match('/^4$|harga|berapa|biaya|cost|price|info harga/', $pesan_lower)) {
                $balas_bot = "Info harga produk kami:\n\n👕 Kaos mulai Rp 45.000/pcs\n👔 Kemeja mulai Rp 85.000/pcs\n🧥 Jaket mulai Rp 120.000/pcs\n👗 Seragam mulai Rp 60.000/pcs\n\nHarga bisa berbeda tergantung bahan & jumlah order.\nUntuk harga pasti, silakan hubungi admin kami ya kak! 😊";
            } elseif (preg_match('/^5$|admin|cs|customer service|operator|manusia|hubungi/', $pesan_lower)) {
                $eskalasi = true;
            } elseif (preg_match('/terima kasih|makasih|thanks|oke|ok|siap/', $pesan_lower)) {
                $balas_bot = "Sama-sama kak! 😊 Senang bisa membantu. Ada yang lain?\n\n1️⃣ Info Produk\n2️⃣ Cara Pemesanan\n3️⃣ Status Pesanan\n4️⃣ Info Harga\n5️⃣ Hubungi Admin";
            } elseif (preg_match('/menu|bantuan|help/', $pesan_lower)) {
                $balas_bot = "Berikut yang bisa saya bantu kak:\n\n1️⃣ Info Produk\n2️⃣ Cara Pemesanan\n3️⃣ Status Pesanan\n4️⃣ Info Harga\n5️⃣ Hubungi Admin\n\nKetik angka atau kata kuncinya ya! 😊";
            } else {
                $eskalasi = true;
            }

            if ($eskalasi) {
                echo json_encode(['status' => 'eskalasi', 'pesan' => 'Maaf, saya tidak bisa menjawab pertanyaan itu. Apakah kamu ingin dihubungkan ke admin kami?']);
                exit;
            }

            $balas_escaped = mysqli_real_escape_string($koneksi, $balas_bot);
            mysqli_query($koneksi, "INSERT INTO chat_pesan (ID_SESI, PENGIRIM, ISI_PESAN) VALUES ('$id_sesi', 'bot', '$balas_escaped')");
            echo json_encode(['status' => 'bot', 'pesan' => $balas_bot]);
        } else {
            echo json_encode(['status' => 'tunggu', 'pesan' => '']);
        }
        exit;
    }

    if ($_POST['action'] == 'eskalasi') {
        mysqli_query($koneksi, "UPDATE chat_sesi SET STATUS = 'eskalasi' WHERE ID_SESI = '$id_sesi'");
        mysqli_query($koneksi, "INSERT INTO chat_pesan (ID_SESI, PENGIRIM, ISI_PESAN) VALUES ('$id_sesi', 'bot', 'Pelanggan meminta bantuan admin.')");
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($_POST['action'] == 'polling') {
        $last_id    = intval($_POST['last_id']);
        $hasil      = mysqli_query($koneksi, "SELECT * FROM chat_pesan WHERE ID_SESI = '$id_sesi' AND ID_PESAN > $last_id ORDER BY DIBUAT_PADA ASC");
        $pesan_baru = [];
        while ($r = mysqli_fetch_assoc($hasil)) $pesan_baru[] = $r;
        $cek_status = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT STATUS FROM chat_sesi WHERE ID_SESI = '$id_sesi'"));
        echo json_encode(['pesan' => $pesan_baru, 'status_sesi' => $cek_status['STATUS']]);
        exit;
    }
}

// =============================================
// DATA RENDER HTML
// =============================================
$riwayat      = mysqli_query($koneksi, "SELECT * FROM chat_pesan WHERE ID_SESI = '$id_sesi' ORDER BY DIKIRIM_PADA ASC");
$last_id_awal = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT MAX(ID_PESAN) as mid FROM chat_pesan WHERE ID_SESI = '$id_sesi'"));
$total_sesi   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM chat_sesi WHERE ID_PELANGGAN = '$id_pelanggan'"))['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Chat 💬 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
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
    --v500: #a855f7;
    --g100: #dcfce7;
    --g500: #22c55e;
    --g700: #15803d;
    --a100: #fef9c3;
    --a500: #eab308;
    --a700: #854d0e;
    --b100: #dbeafe;
    --b500: #3b82f6;
    --r100: #fee2e2;
    --r500: #ef4444;
    --white: #ffffff;
    --bg:   #fff5f9;
    --text: #3d1a28;
    --text2:#7d4460;
    --text3:#b07090;
    --border:  rgba(232,50,138,0.13);
    --border2: rgba(232,50,138,0.24);
    --sidebar-w: 240px;
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
    background-size: 200%;
    animation: shimmer 3s linear infinite; z-index: 1;
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
    transition: transform var(--ease);
}
.sb-brand:hover .brand-mark { transform: rotate(-8deg) scale(1.08); }
.brand-mark i { font-size: 18px; color: #fff; }
.brand-name { font-family:'Quicksand',sans-serif; font-size: 16px; font-weight: 700; color: var(--text); white-space: nowrap; }
.brand-sub  { font-size: 10px; font-weight: 600; color: var(--p500); letter-spacing: 0.8px; text-transform: uppercase; margin-top: 1px; }

.sb-profile {
    margin: 12px 12px 6px; padding: 12px 14px;
    background: linear-gradient(135deg, var(--p50), var(--v100));
    border: 1.5px solid var(--border); border-radius: var(--r-lg);
    display: flex; align-items: center; gap: 10px; flex-shrink: 0;
}
.owner-av {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, var(--p500), var(--v500));
    display: flex; align-items: center; justify-content: center;
    font-family:'Quicksand',sans-serif; font-size: 13px; font-weight: 700; color: #fff;
    flex-shrink: 0; position: relative;
    box-shadow: 0 3px 10px rgba(232,50,138,0.35);
}
.owner-av::after { content:''; position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:var(--g500); border:2px solid var(--white); }
.owner-name { font-size: 13.5px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.owner-role { font-size: 11px; color: var(--p500); font-weight: 600; }

.sb-stats-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 8px; margin: 0 12px 6px; flex-shrink: 0;
}
.sb-stat {
    background: var(--white); border: 1.5px solid var(--border);
    border-radius: var(--r-sm); padding: 9px 10px; text-align: center;
}
.sb-stat-n { font-family:'Quicksand',sans-serif; font-size: 18px; font-weight: 700; color: var(--p500); }
.sb-stat-l { font-size: 10px; color: var(--text3); font-weight: 600; margin-top: 1px; }

.sb-nav { flex: 1; overflow-y: auto; padding: 4px 10px 10px; }
.nav-group-label { font-size: 9.5px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: var(--text3); padding: 12px 10px 4px; display: flex; align-items: center; gap: 6px; }
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
.nav-pill { margin-left:auto; min-width:20px; height:20px; padding:0 6px; border-radius:99px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; color:#fff; flex-shrink:0; background:var(--p500); }
.nav-item.active .nav-pill { background: rgba(255,255,255,0.3); }

.sb-footer { padding: 10px 10px 14px; border-top: 1.5px solid var(--border); flex-shrink: 0; }
.nav-item.logout { color: var(--r500); }
.nav-item.logout i { color: var(--r500); }
.nav-item.logout:hover { background: var(--r100); color: var(--r500); transform: none; }

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
.tb-sub   { font-size:12px; color:var(--text3); font-weight:500; margin-top:1px; }

.tb-divider { width:1px; height:24px; background:var(--border2); margin:0 4px; }
.tb-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }

.user-chip {
    display:flex; align-items:center; gap:8px;
    background:var(--p50); border:1.5px solid var(--border2);
    border-radius:99px; padding:5px 14px 5px 5px;
}
.user-chip-av {
    width:30px; height:30px; border-radius:50%;
    background:linear-gradient(135deg,var(--p500),var(--v500));
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:11px; font-weight:700;
}
.user-chip-name { font-size:13px; font-weight:700; color:var(--text); }
.user-chip-id   { font-size:10px; color:var(--text3); }

.date-pill { display:flex; align-items:center; gap:6px; background:var(--p50); border:1.5px solid var(--border); border-radius:99px; padding:7px 16px; font-size:12.5px; font-weight:600; color:var(--text2); }
.date-pill i { color:var(--p500); }

.btn-new-chat {
    display:flex; align-items:center; gap:6px;
    background:linear-gradient(135deg,var(--p500),var(--p400));
    color:#fff; border:none; border-radius:99px; padding:8px 16px;
    font-size:12.5px; font-weight:700; cursor:pointer;
    box-shadow:0 4px 14px rgba(232,50,138,0.4);
    transition:all var(--ease); text-decoration:none;
}
.btn-new-chat:hover { transform:translateY(-2px) scale(1.03); box-shadow:0 8px 22px rgba(232,50,138,0.5); color:#fff; }

/* ══ MAIN ══ */
.main { margin-left: var(--sidebar-w); padding-top: var(--topbar-h); min-height: 100vh; position: relative; z-index: 1; }
.content { padding: 28px; max-width: 900px; }

@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }

/* ══ CHAT WRAP ══ */
.chat-wrap {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--r-xl);
    overflow: visible; /* PENTING: biar dropdown tidak terpotong */
    box-shadow: 0 8px 32px rgba(232,50,138,0.10);
    animation: fadeUp 0.35s ease;
    position: relative;
}

/* ── CHAT HEADER ── */
.chat-header {
    background: linear-gradient(135deg, var(--p500) 0%, var(--p600) 60%, var(--p700) 100%);
    padding: 18px 24px;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    position: relative; overflow: visible; /* PENTING: biar dropdown tidak terpotong */
    border-radius: var(--r-xl) var(--r-xl) 0 0;
}
.chat-header::before {
    content:''; position:absolute; right:-30px; top:-30px;
    width:120px; height:120px; border-radius:50%;
    background:rgba(255,255,255,0.07);
    pointer-events:none;
}
.chat-header::after {
    content:''; position:absolute; right:60px; bottom:-50px;
    width:100px; height:100px; border-radius:50%;
    background:rgba(255,255,255,0.05);
    pointer-events:none;
}
.chat-hd-left { display:flex; align-items:center; gap:14px; position:relative; z-index:1; }
.bot-avatar {
    width:46px; height:46px; border-radius:50%;
    background:rgba(255,255,255,0.18);
    display:flex; align-items:center; justify-content:center;
    font-size:22px; border:2px solid rgba(255,255,255,0.25);
}
.chat-hd-title  { font-family:'Quicksand',sans-serif; font-size:15px; font-weight:700; color:#fff; }
.chat-hd-status { font-size:11.5px; color:rgba(255,255,255,0.75); margin-top:2px; display:flex; align-items:center; gap:5px; }
.status-dot { width:7px; height:7px; border-radius:50%; background:#4ade80; display:inline-block; }

.chat-hd-actions {
    display:flex; align-items:center; gap:8px;
    position: relative; z-index: 10; /* PENTING: pastikan di atas elemen lain */
}

/* ── CUSTOM DROPDOWN (tanpa Bootstrap) ── */
.dropdown-wrap {
    position: relative;
    z-index: 999;
}

.hd-btn {
    display:flex; align-items:center; gap:6px;
    background:rgba(255,255,255,0.16); color:#fff;
    border:1px solid rgba(255,255,255,0.28); border-radius:99px;
    padding:7px 14px; font-size:12px; font-weight:700;
    cursor:pointer; transition:all var(--ease-plain);
    text-decoration:none; white-space:nowrap;
    font-family:'Nunito',sans-serif;
    user-select: none;
}
.hd-btn:hover { background:rgba(255,255,255,0.3); color:#fff; }
.hd-btn.white { background:rgba(255,255,255,0.9); color:var(--p600); }
.hd-btn.white:hover { background:#fff; color:var(--p600); }
.hd-btn .caret { font-size:10px; transition:transform 0.2s ease; }
.dropdown-wrap.open .hd-btn .caret { transform:rotate(180deg); }

/* ── DROPDOWN PANEL ── */
.dropdown-panel {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 290px;
    background: var(--white);
    border: 1.5px solid var(--border2);
    border-radius: var(--r-lg);
    box-shadow: 0 16px 48px rgba(61,26,40,0.18), 0 4px 16px rgba(232,50,138,0.12);
    z-index: 9999;
    overflow: hidden;
    animation: dropIn 0.18s ease;
}
.dropdown-wrap.open .dropdown-panel { display: block; }

@keyframes dropIn {
    from { opacity: 0; transform: translateY(-8px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
}

.dp-header {
    padding: 12px 16px 8px;
    font-size: 10px; font-weight: 800; letter-spacing: 0.8px;
    text-transform: uppercase; color: var(--text3);
    border-bottom: 1px solid var(--border);
    background: var(--p50);
}

.dp-list { max-height: 260px; overflow-y: auto; }
.dp-list::-webkit-scrollbar { width: 3px; }
.dp-list::-webkit-scrollbar-thumb { background: var(--p200); border-radius: 99px; }

.sesi-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 11px 16px; gap: 10px;
    text-decoration: none; color: var(--text);
    transition: background var(--ease-plain);
    border-bottom: 1px solid var(--border);
}
.sesi-item:last-child { border-bottom: none; }
.sesi-item:hover { background: var(--p50); }
.sesi-item.aktif {
    background: linear-gradient(135deg, var(--p500), var(--p400));
    color: #fff;
}
.sesi-item.aktif:hover { background: linear-gradient(135deg, var(--p500), var(--p400)); }
.sesi-label { font-size: 13px; font-weight: 700; }
.sesi-tgl   { font-size: 10.5px; opacity: 0.65; margin-top: 1px; }
.sesi-badge {
    font-size: 10.5px; padding: 3px 10px; border-radius: 99px;
    background: var(--p50); color: var(--p600);
    white-space: nowrap; font-weight: 700; flex-shrink: 0;
}
.sesi-item.aktif .sesi-badge { background: rgba(255,255,255,0.25); color: #fff; }

.dp-footer {
    padding: 10px 16px;
    border-top: 1.5px solid var(--border);
    background: var(--p50);
}
.btn-sesi-baru {
    display: flex; align-items: center; gap: 8px;
    color: var(--g700); font-weight: 700; font-size: 13px;
    text-decoration: none; transition: all var(--ease-plain);
    padding: 4px 0;
}
.btn-sesi-baru:hover { color: var(--g700); opacity: 0.75; }
.btn-sesi-baru i { font-size: 15px; }

/* ── READONLY BANNER ── */
.readonly-banner {
    background:var(--a100); border-bottom:1.5px solid var(--a500);
    padding:11px 24px; font-size:13px; color:var(--a700);
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    font-weight:600;
}
.readonly-banner a { color:var(--p600); font-weight:700; text-decoration:none; }
.readonly-banner a:hover { text-decoration:underline; }

/* ── CHAT BODY ── */
.chat-body {
    height: 430px; overflow-y: auto;
    padding: 22px; background: var(--bg);
    display: flex; flex-direction: column; gap: 14px;
}
.chat-body::-webkit-scrollbar { width: 4px; }
.chat-body::-webkit-scrollbar-thumb { background: var(--p200); border-radius: 99px; }

.msg-wrapper { display:flex; flex-direction:column; }
.msg-wrapper.kanan { align-items:flex-end; }
.msg-label { font-size:11px; color:var(--text3); font-weight:700; margin-bottom:5px; }
.msg-label.kanan { text-align:right; }

.bubble {
    max-width: 72%; padding: 12px 16px; border-radius: 18px;
    font-size: 13.5px; line-height: 1.65; white-space: pre-wrap;
}
.bubble-bot, .bubble-owner {
    background: var(--white);
    color: var(--text);
    border-radius: 4px 18px 18px 18px;
    box-shadow: 0 2px 12px rgba(232,50,138,0.08);
    border-left: 3px solid var(--p200);
}
.bubble-owner { border-left-color: var(--p500); }
.bubble-pelanggan {
    background: linear-gradient(135deg, var(--p500), var(--p600));
    color: #fff;
    border-radius: 18px 4px 18px 18px;
    box-shadow: 0 4px 16px rgba(232,50,138,0.30);
}

/* ── ESKALASI BOX ── */
.eskalasi-box {
    margin: 0 22px 14px;
    background: var(--a100); border: 1.5px solid var(--a500);
    border-radius: var(--r-md); padding: 18px 22px; text-align: center;
}
.eskalasi-box p { font-size:13.5px; color:var(--a700); margin-bottom:14px; font-weight:600; }
.btn-ya {
    background:linear-gradient(135deg,var(--g500),#16a34a); color:#fff;
    border:none; border-radius:99px; padding:8px 20px;
    font-size:12.5px; font-weight:700; cursor:pointer;
    transition:all var(--ease); margin-right:8px;
    font-family:'Nunito',sans-serif;
}
.btn-ya:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(34,197,94,0.4); }
.btn-tidak {
    background:var(--white); color:var(--text2);
    border:1.5px solid var(--border2); border-radius:99px;
    padding:8px 20px; font-size:12.5px; font-weight:700;
    cursor:pointer; transition:all var(--ease-plain);
    font-family:'Nunito',sans-serif;
}
.btn-tidak:hover { background:var(--p50); color:var(--p600); border-color:var(--p300); }

/* ── QUICK REPLY ── */
.quick-menu {
    padding: 14px 22px 8px;
    background: var(--white);
    border-top: 1.5px solid var(--border);
}
.quick-menu-label { font-size:11px; font-weight:800; color:var(--text3); margin-bottom:10px; letter-spacing:0.5px; text-transform:uppercase; }
.quick-grid { display:flex; flex-wrap:wrap; gap:8px; }
.quick-btn {
    display:inline-flex; align-items:center; gap:6px;
    background:var(--p50); color:var(--p600);
    border:1.5px solid var(--border2); border-radius:99px;
    padding:7px 15px; font-size:12.5px; font-weight:700;
    cursor:pointer; transition:all var(--ease);
    font-family:'Nunito',sans-serif; white-space:nowrap;
}
.quick-btn:hover {
    background:linear-gradient(135deg,var(--p500),var(--p400));
    color:#fff; border-color:var(--p500);
    transform:translateY(-2px); box-shadow:0 4px 14px rgba(232,50,138,0.3);
}

/* ── STATUS BAR ── */
.status-bar {
    padding: 7px 22px; background: var(--white);
    font-size: 11.5px; color: var(--text3); font-weight: 600;
    border-top: 1px solid var(--border); min-height: 32px;
    display:flex; align-items:center; gap:6px;
}

/* ── CHAT FOOTER ── */
.chat-footer {
    padding: 16px 22px; background: var(--white);
    border-top: 1.5px solid var(--border);
    display: flex; gap: 10px; align-items: center;
    border-radius: 0 0 var(--r-xl) var(--r-xl);
}
.chat-input {
    flex: 1; padding: 11px 16px;
    border: 1.5px solid var(--border2); border-radius: var(--r-sm);
    font-family:'Nunito',sans-serif; font-size: 13.5px; color: var(--text);
    background: var(--p50); outline: none;
    transition: border-color var(--ease-plain), box-shadow var(--ease-plain), background var(--ease-plain);
}
.chat-input:focus { border-color:var(--p400); background:var(--white); box-shadow:0 0 0 3px rgba(232,50,138,0.10); }
.chat-input:disabled { background:var(--border); color:var(--text3); cursor:not-allowed; }
.btn-kirim {
    width:44px; height:44px; border-radius:var(--r-sm);
    background:linear-gradient(135deg,var(--p500),var(--p400));
    color:#fff; border:none; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    font-size:17px; transition:all var(--ease);
    box-shadow:0 4px 14px rgba(232,50,138,0.35); flex-shrink:0;
}
.btn-kirim:hover { transform:scale(1.08); box-shadow:0 8px 22px rgba(232,50,138,0.5); }
.btn-kirim:disabled { opacity:0.4; cursor:not-allowed; transform:none; box-shadow:none; }

/* Bot link button */
.bot-link-btn {
    display:inline-flex; align-items:center; gap:6px;
    margin-top:10px; padding:8px 18px; border-radius:99px;
    background:linear-gradient(135deg,var(--p500),var(--p400));
    color:#fff !important; text-decoration:none;
    font-size:12.5px; font-weight:700;
    box-shadow:0 3px 12px rgba(232,50,138,0.35);
    transition:all var(--ease);
}
.bot-link-btn:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(232,50,138,0.5); }

/* WA floating */
.wa-btn {
    position:fixed; bottom:28px; right:28px; z-index:100;
    background:#25D366; border-radius:50%;
    width:52px; height:52px;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 4px 16px rgba(37,211,102,0.45);
    transition:all var(--ease); text-decoration:none;
}
.wa-btn:hover { transform:scale(1.12); }

@media(max-width:900px) {
    .sidebar { transform:translateX(-100%); }
    .topbar  { left:0; }
    .main    { margin-left:0; }
    .content { padding:16px; }
}
</style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="brand-mark"><i class="bi bi-scissors"></i></div>
        <div>
            <div class="brand-name">Konveksi Apps</div>
            <div class="brand-sub">Panel Pelanggan</div>
        </div>
    </a>

    <div class="sb-profile">
        <div class="owner-av"><?= $initials ?></div>
        <div style="overflow:hidden;min-width:0">
            <div class="owner-name"><?= htmlspecialchars($nama_user) ?></div>
            <div class="owner-role">🌸 Pelanggan</div>
        </div>
    </div>

    <div class="sb-stats-grid">
        <div class="sb-stat">
            <div class="sb-stat-n"><?= $total_pesan ?></div>
            <div class="sb-stat-l">Pesanan</div>
        </div>
        <div class="sb-stat">
            <div class="sb-stat-n"><?= $total_selesai ?></div>
            <div class="sb-stat-l">Selesai</div>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="nav-group-label">Menu Utama</div>
        <a class="nav-item" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
        <a class="nav-item" href="pesan.php">
            <i class="bi bi-cart-plus-fill"></i> Pesan Produk
            <span class="nav-pill" style="background:var(--g500)">Baru</span>
        </a>
        <a class="nav-item" href="status_pesanan.php">
            <i class="bi bi-clock-history"></i> Status Pesanan
            <?php if ($total_pending > 0): ?><span class="nav-pill"><?= $total_pending ?></span><?php endif; ?>
        </a>
        <a class="nav-item active" href="chat.php"><i class="bi bi-chat-dots-fill"></i> Live Chat</a>
    </nav>

    <div class="sb-footer">
        <a class="nav-item logout" href="../auth/logout.php"><i class="bi bi-box-arrow-left"></i> Keluar</a>
    </div>
</aside>

<!-- ════ TOPBAR ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Live Chat 💬</div>
        <div class="tb-sub">Tanya apa saja, kami siap membantu kamu! 🌸</div>
    </div>
    <div class="tb-actions">
        <a class="btn-new-chat" href="chat.php?new=1"
            onclick="return confirm('Mulai sesi chat baru?\nSesi sebelumnya tetap tersimpan.')">
            <i class="bi bi-plus-circle-fill"></i> Chat Baru
        </a>
        <div class="tb-divider"></div>
        <div class="user-chip">
            <div class="user-chip-av"><?= $initials ?></div>
            <div>
                <div class="user-chip-name"><?= htmlspecialchars($nama_user) ?></div>
                <div class="user-chip-id"><?= htmlspecialchars($id_pelanggan) ?></div>
            </div>
        </div>
        <div class="date-pill"><i class="bi bi-calendar-heart"></i> <?= date('d M Y') ?></div>
    </div>
</header>

<!-- ════ MAIN ════ -->
<main class="main">
<div class="content">

    <div class="chat-wrap">

        <!-- CHAT HEADER -->
        <div class="chat-header">
            <div class="chat-hd-left">
                <div class="bot-avatar">🤖</div>
                <div>
                    <div class="chat-hd-title">Asisten Konveksi Apps</div>
                    <div class="chat-hd-status" id="status-label">
                        <span class="status-dot"></span>
                        <?php
                        if ($is_readonly)             echo 'Mode Baca — Sesi Lama';
                        elseif ($status_sesi=='bot')  echo 'Bot Aktif — Siap Membantu';
                        else                          echo 'Terhubung ke Admin';
                        ?>
                    </div>
                </div>
            </div>

            <div class="chat-hd-actions">
                <!-- CUSTOM DROPDOWN RIWAYAT (tanpa Bootstrap) -->
                <div class="dropdown-wrap" id="dropdownWrap">
                    <button class="hd-btn" id="dropdownToggle" onclick="toggleDropdown(event)" type="button">
                        <i class="bi bi-clock-history"></i> Riwayat
                        <i class="bi bi-chevron-down caret"></i>
                    </button>

                    <div class="dropdown-panel" id="dropdownPanel">
                        <div class="dp-header">💬 Sesi Chat Kamu</div>
                        <div class="dp-list">
                            <?php
                            $no = $total_sesi;
                            while ($s = mysqli_fetch_assoc($semua_sesi)):
                                $aktif = $s['ID_SESI'] == $id_sesi;
                                $tgl   = date('d M Y H:i', strtotime($s['DIBUAT_PADA']));
                                $badge = $s['STATUS']=='selesai'   ? '✅ Selesai'
                                       : ($s['STATUS']=='eskalasi' ? '🟣 Admin'  : '🤖 Bot');
                            ?>
                            <a class="sesi-item <?= $aktif ? 'aktif' : '' ?>"
                               href="chat.php?sesi=<?= $s['ID_SESI'] ?>">
                                <div>
                                    <div class="sesi-label">Sesi <?= $no-- ?></div>
                                    <div class="sesi-tgl"><?= $tgl ?></div>
                                </div>
                                <span class="sesi-badge"><?= $badge ?></span>
                            </a>
                            <?php endwhile; ?>
                        </div>
                        <div class="dp-footer">
                            <a class="btn-sesi-baru" href="chat.php?new=1"
                               onclick="return confirm('Mulai sesi chat baru?\nSesi sebelumnya tetap tersimpan.')">
                                <i class="bi bi-plus-circle-fill"></i> Mulai Chat Baru
                            </a>
                        </div>
                    </div>
                </div>

                <a class="hd-btn white" href="chat.php?new=1"
                   onclick="return confirm('Mulai sesi chat baru?\nSesi sebelumnya tetap tersimpan.')">
                    <i class="bi bi-plus-lg"></i> Chat Baru
                </a>
            </div>
        </div>

        <!-- READONLY BANNER -->
        <?php if ($is_readonly): ?>
        <div class="readonly-banner">
            <span>📁 Kamu sedang melihat riwayat sesi lama. Pesan tidak bisa dikirim di sini.</span>
            <a href="chat.php">Kembali ke Chat Aktif →</a>
        </div>
        <?php endif; ?>

        <!-- CHAT BODY -->
        <div class="chat-body" id="chat-body">
            <?php if (mysqli_num_rows($riwayat) == 0): ?>
            <div class="msg-wrapper">
                <div class="msg-label">🤖 Asisten</div>
                <div class="bubble bubble-bot">Halo kak <strong><?= htmlspecialchars($nama_user) ?></strong>! 👋 Selamat datang di Konveksi Apps. Ada yang bisa saya bantu?

1️⃣ Info Produk
2️⃣ Cara Pemesanan
3️⃣ Status Pesanan
4️⃣ Info Harga
5️⃣ Hubungi Admin</div>
            </div>
            <?php else:
                while ($msg = mysqli_fetch_assoc($riwayat)):
                    $kanan = $msg['PENGIRIM'] == 'pelanggan';
                    $label = $msg['PENGIRIM'] == 'pelanggan' ? 'Kamu'
                           : ($msg['PENGIRIM'] == 'owner'    ? '👤 Admin' : '🤖 Asisten');
                    $kelas = 'bubble-' . $msg['PENGIRIM'];
            ?>
            <div class="msg-wrapper <?= $kanan ? 'kanan' : '' ?>">
                <div class="msg-label <?= $kanan ? 'kanan' : '' ?>"><?= $label ?></div>
                <div class="bubble <?= $kelas ?>"><?= nl2br(htmlspecialchars($msg['ISI_PESAN'])) ?></div>
            </div>
            <?php endwhile; endif; ?>
        </div>

        <!-- ESKALASI BOX -->
        <div id="eskalasi-box" style="display:none">
            <div class="eskalasi-box">
                <p>😕 Maaf, saya tidak bisa menjawab pertanyaan itu.<br>Apakah kamu ingin dihubungkan ke admin kami?</p>
                <button class="btn-ya" onclick="konfirmasiEskalasi()">✅ Ya, hubungkan ke Admin</button>
                <button class="btn-tidak" onclick="tutupEskalasi()">❌ Tidak, kembali ke menu</button>
            </div>
        </div>

        <!-- QUICK REPLY -->
        <?php if (!$is_readonly && $status_sesi == 'bot'): ?>
        <div class="quick-menu" id="quick-menu">
            <div class="quick-menu-label">✨ Pilihan Cepat</div>
            <div class="quick-grid">
                <button class="quick-btn" onclick="kirimCepat('1')">1️⃣ Info Produk</button>
                <button class="quick-btn" onclick="kirimCepat('2')">2️⃣ Cara Pemesanan</button>
                <button class="quick-btn" onclick="kirimCepat('3')">3️⃣ Status Pesanan</button>
                <button class="quick-btn" onclick="kirimCepat('4')">4️⃣ Info Harga</button>
                <button class="quick-btn" onclick="kirimCepat('5')">5️⃣ Hubungi Admin</button>
            </div>
        </div>
        <?php endif; ?>

        <!-- STATUS BAR -->
        <div class="status-bar" id="status-bar">
            <?= $status_sesi == 'eskalasi' ? '<span style="color:var(--v500)">🟣 Menunggu balasan admin...</span>' : '' ?>
        </div>

        <!-- CHAT FOOTER -->
        <div class="chat-footer">
            <?php if ($is_readonly): ?>
                <input class="chat-input" type="text" placeholder="Sesi ini sudah tidak aktif — mulai chat baru untuk mengirim pesan." disabled>
                <button class="btn-kirim" disabled><i class="bi bi-send-fill"></i></button>
            <?php else: ?>
                <input class="chat-input" type="text" id="input-pesan"
                    placeholder="Ketik pesan atau pilih menu di atas…"
                    onkeypress="if(event.key==='Enter') kirimPesan()">
                <button class="btn-kirim" id="btn-kirim" onclick="kirimPesan()">
                    <i class="bi bi-send-fill"></i>
                </button>
            <?php endif; ?>
        </div>

    </div>
</div>
</main>

<!-- FLOATING WA -->
<a class="wa-btn" href="https://wa.me/62895414630496" target="_blank" title="Chat via WhatsApp">
    <i class="bi bi-whatsapp" style="font-size:24px;color:#fff"></i>
</a>

<script>
// ══ DROPDOWN CUSTOM — tanpa Bootstrap ══
function toggleDropdown(e) {
    e.stopPropagation();
    document.getElementById('dropdownWrap').classList.toggle('open');
}

// Tutup dropdown kalau klik di luar
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('dropdownWrap');
    if (wrap && !wrap.contains(e.target)) {
        wrap.classList.remove('open');
    }
});

// Tutup dropdown kalau tekan Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('dropdownWrap')?.classList.remove('open');
    }
});

// ══ CHAT ══
let lastId         = <?= $last_id_awal['mid'] ?? 0 ?>;
let statusSesi     = '<?= $status_sesi ?>';
let isReadonly     = <?= $is_readonly ? 'true' : 'false' ?>;
let nungguEskalasi = false;

function scrollBawah() {
    const b = document.getElementById('chat-body');
    if (b) b.scrollTop = b.scrollHeight;
}
scrollBawah();

function formatPesan(teks) {
    return teks
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>')
        .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
        .replace(/\[LINK:(.*?)\|(.*?)\]/g, '<br><a href="$1" class="bot-link-btn"><i class="bi bi-cart-fill"></i> $2</a>');
}

function tambahBubble(pengirim, pesan) {
    const body  = document.getElementById('chat-body');
    const kanan = pengirim === 'pelanggan';
    const label = pengirim === 'pelanggan' ? 'Kamu'
                : (pengirim === 'owner'    ? '👤 Admin' : '🤖 Asisten');
    const kelas = 'bubble-' + pengirim;
    const div   = document.createElement('div');
    div.className = 'msg-wrapper' + (kanan ? ' kanan' : '');
    div.innerHTML = `
        <div class="msg-label ${kanan ? 'kanan' : ''}">${label}</div>
        <div class="bubble ${kelas}">${formatPesan(pesan)}</div>`;
    body.appendChild(div);
    scrollBawah();
}

function kirimCepat(teks) {
    if (isReadonly || nungguEskalasi) return;
    document.getElementById('input-pesan').value = teks;
    kirimPesan();
}

async function kirimPesan() {
    if (isReadonly || nungguEskalasi) return;
    const input = document.getElementById('input-pesan');
    const pesan = input.value.trim();
    if (!pesan) return;

    tambahBubble('pelanggan', pesan);
    input.value = '';

    const res  = await fetch('chat.php?sesi=<?= $id_sesi ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=kirim&pesan=${encodeURIComponent(pesan)}`
    });
    const data = await res.json();

    if (data.status === 'eskalasi') {
        nungguEskalasi = true;
        tambahBubble('bot', data.pesan);
        document.getElementById('eskalasi-box').style.display = 'block';
        const qm = document.getElementById('quick-menu');
        if (qm) qm.style.display = 'none';
    } else if (data.status === 'bot') {
        tambahBubble('bot', data.pesan);
    } else if (data.status === 'tunggu') {
        document.getElementById('status-bar').innerHTML = '<span style="color:var(--v500)">🟣 Menunggu balasan admin...</span>';
    }
}

async function konfirmasiEskalasi() {
    document.getElementById('eskalasi-box').style.display = 'none';
    await fetch('chat.php?sesi=<?= $id_sesi ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=eskalasi'
    });
    statusSesi     = 'eskalasi';
    nungguEskalasi = false;
    document.getElementById('status-label').innerHTML = '<span class="status-dot" style="background:#a855f7"></span> Terhubung ke Admin';
    document.getElementById('status-bar').innerHTML   = '<span style="color:var(--v500)">🟣 Menunggu balasan admin...</span>';
    tambahBubble('bot', 'Baik! Kamu sekarang terhubung dengan admin kami.\nMohon tunggu sebentar ya kak 🙏');
}

function tutupEskalasi() {
    document.getElementById('eskalasi-box').style.display = 'none';
    nungguEskalasi = false;
    tambahBubble('bot', 'Baik! Ada yang lain yang ingin ditanyakan?\n\n1️⃣ Info Produk\n2️⃣ Cara Pemesanan\n3️⃣ Status Pesanan\n4️⃣ Info Harga\n5️⃣ Hubungi Admin');
    const qm = document.getElementById('quick-menu');
    if (qm) qm.style.display = 'block';
}

<?php if (!$is_readonly): ?>
setInterval(async () => {
    const res  = await fetch('chat.php?sesi=<?= $id_sesi ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=polling&last_id=${lastId}`
    });
    const data = await res.json();
    data.pesan.forEach(p => {
        if (p.PENGIRIM === 'owner') {
            tambahBubble('owner', p.ISI_PESAN);
            document.getElementById('status-bar').textContent = '';
        }
        lastId = Math.max(lastId, parseInt(p.ID_PESAN));
    });
}, 3000);
<?php endif; ?>
</script>
</body>
</html>