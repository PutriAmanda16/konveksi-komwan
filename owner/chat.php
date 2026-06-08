<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php");
    exit;
}

// =============================================
// AJAX HANDLER
// =============================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] == 'daftar') {
        $hasil = mysqli_query($koneksi, "
            SELECT cs.ID_SESI, cs.ID_PELANGGAN, cs.STATUS, cs.CREATED_AT, cs.UPDATED_AT,
                pl.NAMA_PELANGGAN,
                (SELECT ISI_PESAN FROM chat_pesan WHERE ID_SESI = cs.ID_SESI ORDER BY CREATED_AT DESC LIMIT 1) as PESAN_TERAKHIR,
                (SELECT COUNT(*) FROM chat_pesan WHERE ID_SESI = cs.ID_SESI AND PENGIRIM = 'pelanggan' AND DIBACA = 0) as BELUM_DIBACA,
                (SELECT COUNT(*) FROM chat_sesi cs2 WHERE cs2.ID_PELANGGAN = cs.ID_PELANGGAN AND cs2.CREATED_AT <= cs.CREATED_AT) as NOMOR_SESI
            FROM chat_sesi cs
            LEFT JOIN pelanggan pl ON cs.ID_PELANGGAN = pl.ID_PELANGGAN
            WHERE cs.STATUS = 'eskalasi'
            ORDER BY cs.UPDATED_AT DESC
        ");
        $data = [];
        while ($r = mysqli_fetch_assoc($hasil)) $data[] = $r;
        echo json_encode($data);
        exit;
    }

    if ($_POST['action'] == 'buka') {
        $id_sesi = mysqli_real_escape_string($koneksi, $_POST['id_sesi']);
        $last_id = intval($_POST['last_id'] ?? 0);
        mysqli_query($koneksi, "UPDATE chat_pesan SET DIBACA = 1 WHERE ID_SESI = '$id_sesi' AND PENGIRIM = 'pelanggan'");
        $hasil = mysqli_query($koneksi, "SELECT * FROM chat_pesan WHERE ID_SESI = '$id_sesi' AND ID_PESAN > $last_id ORDER BY CREATED_AT ASC");
        $data  = [];
        while ($r = mysqli_fetch_assoc($hasil)) $data[] = $r;
        echo json_encode($data);
        exit;
    }

    if ($_POST['action'] == 'balas') {
        $id_sesi = mysqli_real_escape_string($koneksi, $_POST['id_sesi']);
        $pesan   = mysqli_real_escape_string($koneksi, $_POST['pesan']);
        mysqli_query($koneksi, "INSERT INTO chat_pesan (ID_SESI, PENGIRIM, ISI_PESAN) VALUES ('$id_sesi', 'owner', '$pesan')");
        mysqli_query($koneksi, "UPDATE chat_sesi SET UPDATED_AT = NOW() WHERE ID_SESI = '$id_sesi'");
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($_POST['action'] == 'selesai') {
        $id_sesi = mysqli_real_escape_string($koneksi, $_POST['id_sesi']);
        mysqli_query($koneksi, "UPDATE chat_sesi SET STATUS = 'selesai' WHERE ID_SESI = '$id_sesi'");
        mysqli_query($koneksi, "INSERT INTO chat_pesan (ID_SESI, PENGIRIM, ISI_PESAN) VALUES ('$id_sesi', 'bot', 'Sesi chat telah diselesaikan oleh admin. Terima kasih! 😊')");
        echo json_encode(['status' => 'ok']);
        exit;
    }
}

// ── Notif badge (sama seperti dashboard) ──
$notif_bayar = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE STATUS_BAYAR='Menunggu Konfirmasi'"));
$notif_chat  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM chat_sesi WHERE STATUS='eskalasi'"))['t'] ?? 0;
$aset_rusak  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM aset WHERE KONDISI_ASET IN ('Rusak','Perlu Perbaikan')"))['t'] ?? 0;
$stok_kritis = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM bahan_baku WHERE JUMLAH_STOK <= 25"));
$total_notif = $notif_bayar + $notif_chat + $stok_kritis + $aset_rusak;

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
<title>Inbox Chat 💬 | Konveksi Apps</title>
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
    --white:   #ffffff;
    --bg:      #fff5f9;
    --text:    #3d1a28;
    --text2:   #7d4460;
    --text3:   #b07090;
    --border:  rgba(232,50,138,0.13);
    --border2: rgba(232,50,138,0.24);
    --sidebar-w: 256px;
    --topbar-h:  64px;
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
    overflow: hidden;
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
.brand-name { font-family: 'Quicksand', sans-serif; font-size: 16px; font-weight: 700; color: var(--text); white-space: nowrap; }
.brand-sub { font-size: 10px; font-weight: 600; color: var(--p500); letter-spacing: 0.8px; text-transform: uppercase; margin-top: 1px; }

.sb-owner {
    margin: 12px 12px 6px; padding: 12px 14px;
    background: linear-gradient(135deg, var(--p50), var(--v100));
    border: 1.5px solid var(--border); border-radius: 22px;
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
    padding: 9px 11px; border-radius: 10px;
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
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.4} }
.pulse { animation: blink 1.6s ease-in-out infinite; }

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
.main { margin-left:var(--sidebar-w); padding-top:var(--topbar-h); height:100vh; position:relative; z-index:1; display:flex; flex-direction:column; }

/* ══ CHAT LAYOUT ══ */
.chat-wrapper {
    flex: 1;
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 0;
    overflow: hidden;
    height: calc(100vh - var(--topbar-h));
}

/* ── Sesi List ── */
.sesi-panel {
    background: var(--white);
    border-right: 1.5px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sesi-panel-header {
    padding: 18px 16px 12px;
    border-bottom: 1.5px solid var(--border);
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--p50), var(--white));
}
.sesi-panel-title {
    font-family: 'Quicksand', sans-serif;
    font-size: 15px; font-weight: 700; color: var(--text);
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 12px;
}
.sesi-panel-title i { color: var(--p500); font-size: 16px; }
.sesi-count-badge {
    background: linear-gradient(135deg, var(--p500), var(--p400));
    color: #fff; border-radius: 99px; padding: 2px 9px;
    font-size: 11px; font-weight: 800;
    box-shadow: 0 2px 8px rgba(232,50,138,0.35);
}
.sesi-search {
    width: 100%;
    border: 1.5px solid var(--border);
    border-radius: 99px;
    padding: 8px 14px 8px 36px;
    font-size: 13px; font-weight: 500;
    background: var(--bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%23b07090' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E") no-repeat 12px center;
    color: var(--text);
    font-family: 'Nunito', sans-serif;
    outline: none;
    transition: border-color var(--ease-plain);
}
.sesi-search:focus { border-color: var(--border2); background-color: var(--white); }
.sesi-search::placeholder { color: var(--text3); }

.sesi-list-body { flex: 1; overflow-y: auto; }

.sesi-item {
    padding: 14px 16px;
    border-bottom: 1px solid var(--p50);
    cursor: pointer;
    transition: background var(--ease-plain), border-left var(--ease-plain);
    border-left: 3px solid transparent;
    position: relative;
}
.sesi-item:hover { background: var(--p50); border-left-color: var(--p300); }
.sesi-item.active { background: linear-gradient(135deg, var(--p50), var(--v100)); border-left: 3px solid var(--p500); }

.sesi-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, var(--p400), var(--v500));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Quicksand', sans-serif; font-size: 14px; font-weight: 700; color: #fff;
    flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(232,50,138,0.25);
}
.sesi-nama { font-size: 13.5px; font-weight: 700; color: var(--text); }
.sesi-nomor-tag {
    font-size: 10px; color: var(--p500);
    background: var(--p50); border: 1px solid var(--border2);
    padding: 1px 7px; border-radius: 99px; font-weight: 700;
}
.sesi-preview { font-size: 12px; color: var(--text3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; margin-top: 2px; }
.sesi-waktu { font-size: 10.5px; color: var(--text3); white-space: nowrap; }
.unread-dot {
    width: 20px; height: 20px;
    background: linear-gradient(135deg, var(--p500), var(--p400));
    color: #fff; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 800;
    box-shadow: 0 2px 6px rgba(232,50,138,0.4);
    animation: blink 1.6s ease-in-out infinite;
}

.sesi-empty { text-align: center; padding: 50px 20px; color: var(--text3); }
.sesi-empty i { font-size: 2.5rem; color: var(--p100); display: block; margin-bottom: 12px; }
.sesi-empty p { font-size: 13px; font-weight: 600; }

/* ── Chat Area ── */
.chat-area {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--bg);
    position: relative;
}

/* empty state */
.chat-area-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 14px;
    text-align: center;
    padding: 40px;
}
.chat-empty-ico {
    width: 80px; height: 80px; border-radius: 24px;
    background: linear-gradient(135deg, var(--p50), var(--v100));
    border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 32px;
    margin-bottom: 8px;
}
.chat-empty-title { font-family: 'Quicksand', sans-serif; font-size: 17px; font-weight: 700; color: var(--text); }
.chat-empty-sub { font-size: 13px; color: var(--text3); }

/* chat header */
.chat-header {
    background: linear-gradient(135deg, var(--p500), var(--p400), var(--v500));
    padding: 16px 24px;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
    box-shadow: 0 4px 20px rgba(232,50,138,0.3);
    position: relative; overflow: hidden;
}
.chat-header::before {
    content: '';
    position: absolute; right: -30px; top: -30px;
    width: 120px; height: 120px; border-radius: 50%;
    background: rgba(255,255,255,0.08);
}
.ch-av {
    width: 42px; height: 42px; border-radius: 50%;
    background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.4);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Quicksand', sans-serif; font-size: 15px; font-weight: 700; color: #fff;
    flex-shrink: 0;
}
.ch-name { font-family: 'Quicksand', sans-serif; font-size: 15px; font-weight: 700; color: #fff; }
.ch-sub { font-size: 12px; color: rgba(255,255,255,0.75); margin-top: 1px; }
.ch-online { display: inline-flex; align-items: center; gap: 5px; }
.ch-online::before { content:''; width:7px; height:7px; border-radius:50%; background:#86efac; display:inline-block; box-shadow:0 0 0 2px rgba(134,239,172,0.35); animation:blink 2s ease-in-out infinite; }

.btn-selesai {
    display: flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,0.2);
    color: #fff; border: 1.5px solid rgba(255,255,255,0.35);
    border-radius: 10px; padding: 8px 16px;
    font-size: 13px; font-weight: 700;
    cursor: pointer; font-family: 'Nunito', sans-serif;
    transition: all var(--ease-plain); position: relative; z-index: 1;
    backdrop-filter: blur(4px);
}
.btn-selesai:hover { background: rgba(255,255,255,0.35); transform: translateY(-1px); }
.btn-selesai i { font-size: 14px; }

/* chat body */
.chat-body {
    flex: 1; overflow-y: auto;
    padding: 24px;
    display: flex; flex-direction: column; gap: 14px;
    background: var(--bg);
    background-image: radial-gradient(circle, rgba(232,50,138,0.04) 1.5px, transparent 1.5px);
    background-size: 24px 24px;
}

.msg-wrapper { display: flex; flex-direction: column; }
.msg-wrapper.kanan { align-items: flex-end; }

.msg-label {
    font-size: 11px; color: var(--text3); font-weight: 600;
    margin-bottom: 4px; display: flex; align-items: center; gap: 5px;
}
.msg-label.kanan { justify-content: flex-end; }

.bubble {
    max-width: 70%;
    padding: 11px 16px;
    border-radius: 18px;
    font-size: 14px; line-height: 1.55;
    white-space: pre-wrap; word-break: break-word;
    position: relative;
}
.bubble-pelanggan {
    background: var(--white);
    color: var(--text);
    border-radius: 4px 18px 18px 18px;
    box-shadow: 0 2px 12px rgba(232,50,138,0.08);
    border: 1.5px solid var(--border);
}
.bubble-bot {
    background: var(--p50);
    color: var(--text3);
    border-radius: 4px 18px 18px 18px;
    font-style: italic; font-size: 13px;
    border: 1.5px solid var(--border);
}
.bubble-owner {
    background: linear-gradient(135deg, var(--p500), var(--p400));
    color: #fff;
    border-radius: 18px 4px 18px 18px;
    box-shadow: 0 4px 16px rgba(232,50,138,0.35);
}
.bubble-time {
    font-size: 10.5px;
    margin-top: 5px;
    color: var(--text3);
    padding: 0 4px;
}
.msg-wrapper.kanan .bubble-time { text-align: right; color: rgba(255,255,255,0.6); }

/* typing separator */
.chat-date-sep {
    text-align: center; font-size: 11px; color: var(--text3);
    display: flex; align-items: center; gap: 10px;
    font-weight: 600;
}
.chat-date-sep::before, .chat-date-sep::after { content:''; flex:1; height:1px; background:var(--border); }

/* chat footer */
.chat-footer {
    padding: 14px 20px;
    background: var(--white);
    border-top: 1.5px solid var(--border);
    display: flex; align-items: center; gap: 10px;
    flex-shrink: 0;
}
.chat-input-wrap {
    flex: 1;
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: 99px;
    display: flex; align-items: center;
    padding: 4px 8px 4px 16px;
    gap: 8px;
    transition: border-color var(--ease-plain), box-shadow var(--ease-plain);
}
.chat-input-wrap:focus-within { border-color: var(--border2); box-shadow: 0 0 0 3px rgba(232,50,138,0.1); }
.chat-input {
    flex: 1; border: none; background: transparent;
    font-size: 14px; font-family: 'Nunito', sans-serif;
    color: var(--text); outline: none; padding: 8px 0;
}
.chat-input::placeholder { color: var(--text3); }
.btn-emoji { font-size: 18px; cursor: pointer; line-height: 1; border: none; background: none; color: var(--text3); transition: transform var(--ease); }
.btn-emoji:hover { transform: scale(1.2); }
.btn-send {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, var(--p500), var(--p400));
    color: #fff; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    box-shadow: 0 4px 14px rgba(232,50,138,0.4);
    transition: all var(--ease);
    flex-shrink: 0;
}
.btn-send:hover { transform: scale(1.1) rotate(-10deg); box-shadow: 0 6px 20px rgba(232,50,138,0.5); }
.btn-send:active { transform: scale(0.95); }

/* ── Success state ── */
.chat-done-state {
    flex: 1;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 14px; text-align: center; padding: 40px;
}
.done-ico {
    width: 80px; height: 80px; border-radius: 50%;
    background: var(--g100); border: 2px solid rgba(34,197,94,0.25);
    display: flex; align-items: center; justify-content: center;
    font-size: 36px;
    animation: popIn 0.4s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes popIn { from{transform:scale(0.5);opacity:0} to{transform:scale(1);opacity:1} }
</style>
</head>
<body>

<!-- ════ SIDEBAR 🌸 ════ -->
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
        <a class="nav-item" href="penggajian.php"><i class="bi bi-cash-stack"></i> Penggajian</a>
        <a class="nav-item" href="konfirmasi_pembayaran.php">
            <i class="bi bi-credit-card-2-front"></i> Konfirmasi Bayar
            <?php if ($notif_bayar > 0): ?><span class="nav-pill pill-pink pulse"><?= $notif_bayar ?></span><?php endif; ?>
        </a>
        <a class="nav-item active" href="chat.php">
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

<!-- ════ TOPBAR 🎀 ════ -->
<header class="topbar">
    <div class="tb-greeting">
        <div class="tb-hello">Inbox Chat 💬</div>
        <div class="tb-sub">Balas peserta eskalasi dari pelanggan kamu</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi</a>
        <a class="tb-nav-item active" href="chat.php"><i class="bi bi-chat-dots-fill"></i> Chat</a>
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
<div class="chat-wrapper">

    <!-- ══ SESI PANEL ══ -->
    <div class="sesi-panel">
        <div class="sesi-panel-header">
            <div class="sesi-panel-title">
                <i class="bi bi-chat-heart-fill"></i>
                Chat Masuk
                <?php if ($notif_chat > 0): ?>
                <span class="sesi-count-badge"><?= $notif_chat ?></span>
                <?php endif; ?>
            </div>
            <input type="text" class="sesi-search" placeholder="Cari pelanggan..."
                   id="cari-pelanggan" oninput="filterSesi(this.value)">
        </div>
        <div class="sesi-list-body" id="daftar-sesi">
            <div class="sesi-empty">
                <i class="bi bi-inbox"></i>
                <p>Belum ada chat masuk</p>
            </div>
        </div>
    </div>

    <!-- ══ CHAT AREA ══ -->
    <div class="chat-area" id="chat-area">
        <div class="chat-area-empty">
            <div class="chat-empty-ico">💬</div>
            <div class="chat-empty-title">Pilih percakapan</div>
            <div class="chat-empty-sub">Klik salah satu chat di sebelah kiri<br>untuk mulai membalas pelanggan 🌸</div>
        </div>
    </div>

</div>
</main>

<script>
let sesiAktif    = null;
let namaAktif    = '';
let nomorAktif   = '';
let lastId       = 0;
let pollingTimer = null;
let semuaSesi    = [];

function formatWaktu(dt) {
    if (!dt) return '';
    const d    = new Date(dt.replace(' ', 'T'));
    const now  = new Date();
    const diff = Math.floor((now - d) / 60000);
    if (diff < 1)    return 'baru saja';
    if (diff < 60)   return diff + ' mnt lalu';
    if (diff < 1440) return Math.floor(diff/60) + ' jam lalu';
    return d.toLocaleDateString('id-ID', {day:'numeric', month:'short'});
}

function getInisial(nama) {
    if (!nama) return '?';
    const parts = nama.trim().split(' ');
    return parts.length >= 2
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : nama[0].toUpperCase();
}

function renderDaftar(data) {
    const container = document.getElementById('daftar-sesi');
    if (data.length === 0) {
        container.innerHTML = `<div class="sesi-empty"><i class="bi bi-inbox"></i><p>Belum ada chat masuk 🌸</p></div>`;
        return;
    }
    container.innerHTML = data.map(s => `
        <div class="sesi-item ${s.ID_SESI === sesiAktif ? 'active' : ''}"
             onclick="bukaSesi('${s.ID_SESI}', '${(s.NAMA_PELANGGAN || s.ID_PELANGGAN).replace(/'/g,"\\'")}', ${s.NOMOR_SESI})"
             data-nama="${(s.NAMA_PELANGGAN || '').toLowerCase()}">
            <div style="display:flex;align-items:flex-start;gap:10px">
                <div class="sesi-avatar">${getInisial(s.NAMA_PELANGGAN || s.ID_PELANGGAN)}</div>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:2px">
                        <div style="display:flex;align-items:center;gap:6px;min-width:0">
                            <span class="sesi-nama">${s.NAMA_PELANGGAN || s.ID_PELANGGAN}</span>
                            <span class="sesi-nomor-tag">Sesi ${s.NOMOR_SESI}</span>
                        </div>
                        <span class="sesi-waktu">${formatWaktu(s.UPDATED_AT || s.CREATED_AT)}</span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:6px">
                        <div class="sesi-preview">${s.PESAN_TERAKHIR || '—'}</div>
                        ${s.BELUM_DIBACA > 0 ? `<span class="unread-dot">${s.BELUM_DIBACA}</span>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

async function loadDaftar() {
    const res  = await fetch('chat.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=daftar'});
    semuaSesi  = await res.json();
    renderDaftar(semuaSesi);
}

function filterSesi(kata) {
    const filtered = semuaSesi.filter(s => (s.NAMA_PELANGGAN || '').toLowerCase().includes(kata.toLowerCase()));
    renderDaftar(filtered);
}

async function bukaSesi(idSesi, namaPelanggan, nomorSesi) {
    sesiAktif  = idSesi;
    namaAktif  = namaPelanggan;
    nomorAktif = nomorSesi;
    lastId     = 0;
    clearInterval(pollingTimer);

    document.getElementById('chat-area').innerHTML = `
        <div class="chat-header">
            <div style="display:flex;align-items:center;gap:12px;position:relative;z-index:1">
                <div class="ch-av">${getInisial(namaPelanggan)}</div>
                <div>
                    <div class="ch-name">${namaPelanggan}</div>
                    <div class="ch-sub"><span class="ch-online">Sesi ke-${nomorSesi} &nbsp;·&nbsp; Eskalasi ke Admin</span></div>
                </div>
            </div>
            <button class="btn-selesai" onclick="selesaikanSesi('${idSesi}')">
                <i class="bi bi-check-circle-fill"></i> Selesaikan Sesi
            </button>
        </div>
        <div class="chat-body" id="chat-body"></div>
        <div class="chat-footer">
            <div class="chat-input-wrap">
                <button class="btn-emoji">😊</button>
                <input type="text" class="chat-input" id="input-balas"
                       placeholder="Balas ke ${namaPelanggan}..."
                       onkeypress="if(event.key==='Enter') balasPesan()">
            </div>
            <button class="btn-send" onclick="balasPesan()">
                <i class="bi bi-send-fill"></i>
            </button>
        </div>`;

    await muatPesan();
    pollingTimer = setInterval(muatPesan, 3000);
    loadDaftar();
}

async function muatPesan() {
    if (!sesiAktif) return;
    const res  = await fetch('chat.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=buka&id_sesi=${sesiAktif}&last_id=${lastId}`});
    const data = await res.json();
    const body = document.getElementById('chat-body');
    if (!body) return;

    data.forEach(p => {
        const kanan  = p.PENGIRIM === 'owner';
        const label  = p.PENGIRIM === 'pelanggan' ? '👤 Pelanggan' : p.PENGIRIM === 'owner' ? '👑 Kamu (Admin)' : '🤖 Bot';
        const kelas  = 'bubble-' + p.PENGIRIM;
        const waktu  = p.CREATED_AT ? new Date(p.CREATED_AT.replace(' ','T')).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'}) : '';
        const div    = document.createElement('div');
        div.className = 'msg-wrapper' + (kanan ? ' kanan' : '');
        div.innerHTML = `
            <div class="msg-label ${kanan ? 'kanan' : ''}">${label}</div>
            <div class="bubble ${kelas}">${p.ISI_PESAN.replace(/\n/g,'<br>').replace(/\*(.*?)\*/g,'<strong>$1</strong>')}</div>
            <div class="bubble-time">${waktu}</div>`;
        body.appendChild(div);
        lastId = Math.max(lastId, parseInt(p.ID_PESAN));
    });

    if (data.length > 0) body.scrollTop = body.scrollHeight;
}

async function balasPesan() {
    const input = document.getElementById('input-balas');
    if (!input) return;
    const pesan = input.value.trim();
    if (!pesan || !sesiAktif) return;
    input.value = '';
    await fetch('chat.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=balas&id_sesi=${sesiAktif}&pesan=${encodeURIComponent(pesan)}`});
    await muatPesan();
}

async function selesaikanSesi(idSesi) {
    if (!confirm('Selesaikan sesi chat ini?\nPelanggan akan mendapat notifikasi bahwa chat sudah selesai.')) return;

    await fetch('chat.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=selesai&id_sesi=${idSesi}`});

    sesiAktif = null;
    clearInterval(pollingTimer);

    document.getElementById('chat-area').innerHTML = `
        <div class="chat-done-state">
            <div class="done-ico">✅</div>
            <div class="chat-empty-title">Sesi diselesaikan!</div>
            <div class="chat-empty-sub">Pelanggan sudah mendapat notifikasi.<br>Pilih percakapan lain untuk melanjutkan 🌸</div>
        </div>`;

    loadDaftar();
}

loadDaftar();
setInterval(loadDaftar, 5000);
</script>
</body>
</html>