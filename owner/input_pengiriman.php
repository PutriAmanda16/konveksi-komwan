<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: ../index.php"); exit;
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

$flash = '';

// ── PROSES SIMPAN / UPDATE PENGIRIMAN ──
if (isset($_POST['simpan'])) {
    $id_pesanan  = mysqli_real_escape_string($koneksi, $_POST['id_pesanan']);
    $jasa        = mysqli_real_escape_string($koneksi, trim($_POST['jasa_kirim']));
    $resi        = mysqli_real_escape_string($koneksi, trim($_POST['no_resi']));
    $alamat      = mysqli_real_escape_string($koneksi, trim($_POST['alamat_kirim']));
    $ongkir      = (float) $_POST['ongkir'];
    $est         = mysqli_real_escape_string($koneksi, $_POST['est_tiba']);
    $status_kirim= mysqli_real_escape_string($koneksi, $_POST['status_kirim']);
    $tgl_kirim   = date('Y-m-d H:i:s');

    // Cek apakah sudah ada data pengiriman untuk pesanan ini
    $cek = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT ID_PENGIRIMAN FROM pengiriman WHERE ID_PESANAN='$id_pesanan'"));

    if ($cek) {
        // UPDATE
        $q = "UPDATE pengiriman SET
                JASA_KIRIM='$jasa', NO_RESI='$resi', ALAMAT_KIRIM='$alamat',
                ONGKIR='$ongkir', EST_TIBA='$est', STATUS_KIRIM='$status_kirim',
                TGL_KIRIM='$tgl_kirim'
              WHERE ID_PESANAN='$id_pesanan'";
        if (mysqli_query($koneksi, $q)) $flash = 'ok:Data pengiriman berhasil diperbarui! ✨';
        else $flash = 'error:Gagal memperbarui: ' . mysqli_error($koneksi);
    } else {
        // INSERT — generate ID
        $last  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT ID_PENGIRIMAN FROM pengiriman ORDER BY ID_PENGIRIMAN DESC LIMIT 1"));
        $num   = $last ? (int)substr($last['ID_PENGIRIMAN'], 2) + 1 : 1;
        $id_pg = 'KR' . str_pad($num, 3, '0', STR_PAD_LEFT);

        $q = "INSERT INTO pengiriman (ID_PENGIRIMAN, ID_PESANAN, JASA_KIRIM, NO_RESI, ALAMAT_KIRIM, ONGKIR, EST_TIBA, STATUS_KIRIM, TGL_KIRIM)
              VALUES ('$id_pg','$id_pesanan','$jasa','$resi','$alamat','$ongkir','$est','$status_kirim','$tgl_kirim')";
        if (mysqli_query($koneksi, $q)) {
            // Update status pesanan jadi Proses
            mysqli_query($koneksi, "UPDATE pesanan SET STATUS='Proses' WHERE ID_PESANAN='$id_pesanan'");
            $flash = 'ok:Data pengiriman berhasil disimpan! 🎉';
        } else {
            $flash = 'error:Gagal menyimpan: ' . mysqli_error($koneksi);
        }
    }
}

// ── AMBIL PESANAN YANG SIAP DIKIRIM (Selesai produksi / sudah bayar) ──
$pesanan_list = [];
$q_p = mysqli_query($koneksi, "
    SELECT p.ID_PESANAN, p.TOTAL_HARGA, p.WAKTU_PESAN, p.STATUS,
           pl.NAMA_PELANGGAN, pl.NO_HP,
           pr.NAMA_PRODUK, dp.JUMLAH,
           k.ID_PENGIRIMAN, k.JASA_KIRIM, k.NO_RESI, k.ALAMAT_KIRIM,
           k.ONGKIR, k.EST_TIBA, k.STATUS_KIRIM, k.TGL_KIRIM
    FROM pesanan p
    LEFT JOIN pelanggan pl ON p.ID_PELANGGAN = pl.ID_PELANGGAN
    LEFT JOIN detail_pesanan dp ON p.ID_PESANAN = dp.ID_PESANAN
    LEFT JOIN produk pr ON dp.ID_PRODUK = pr.ID_PRODUK
    LEFT JOIN pengiriman k ON p.ID_PESANAN = k.ID_PESANAN
    WHERE p.STATUS IN ('Proses','Selesai')
    ORDER BY p.WAKTU_PESAN DESC
");
while ($r = mysqli_fetch_assoc($q_p)) $pesanan_list[] = $r;

$total_belum = count(array_filter($pesanan_list, fn($r) => empty($r['ID_PENGIRIMAN'])));
$total_proses = count(array_filter($pesanan_list, fn($r) => !empty($r['ID_PENGIRIMAN']) && $r['STATUS_KIRIM'] != 'Terkirim'));
$total_terkirim = count(array_filter($pesanan_list, fn($r) => $r['STATUS_KIRIM'] == 'Terkirim'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Input Pengiriman 🚚 | Konveksi Apps</title>
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
    --t100:#ccfbf1;--t500:#14b8a6;--t700:#0f766e;
    --white:#ffffff;--bg:#fff5f9;
    --text:#3d1a28;--text2:#7d4460;--text3:#b07090;
    --border:rgba(232,50,138,0.13);--border2:rgba(232,50,138,0.24);
    --sidebar-w:256px;--topbar-h:64px;
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
.sb-owner{margin:12px 12px 6px;padding:12px 14px;background:linear-gradient(135deg,var(--p50),var(--v100));border:1.5px solid var(--border);border-radius:22px;display:flex;align-items:center;gap:10px;flex-shrink:0}
.owner-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--p500),var(--v500));display:flex;align-items:center;justify-content:center;font-family:'Quicksand',sans-serif;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;position:relative;box-shadow:0 3px 10px rgba(232,50,138,0.35)}
.owner-av::after{content:'';position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:var(--g500);border:2px solid var(--white)}
.owner-name{font-size:13.5px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.owner-role{font-size:11px;color:var(--p500);font-weight:600}
.sb-nav{flex:1;overflow-y:auto;padding:6px 10px 10px}
.nav-group-label{font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--text3);padding:14px 10px 4px;display:flex;align-items:center;gap:6px}
.nav-group-label::after{content:'✦';font-size:7px;color:var(--p300)}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:10px;text-decoration:none;color:var(--text2);font-size:14px;font-weight:600;transition:background var(--ease-plain),color var(--ease-plain),transform var(--ease-plain);margin-bottom:2px;position:relative;white-space:nowrap}
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
.toast-msg{display:flex;align-items:center;gap:10px;padding:13px 20px;border-radius:22px;font-size:13.5px;font-weight:700;box-shadow:0 8px 28px rgba(0,0,0,0.12);animation:slideIn 0.3s cubic-bezier(0.34,1.56,0.64,1);border:1.5px solid;transition:opacity 0.4s}
@keyframes slideIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:none}}
.toast-ok{background:var(--g100);color:var(--g700);border-color:rgba(34,197,94,0.3)}
.toast-err{background:var(--r100);color:var(--r700);border-color:rgba(239,68,68,0.3)}

/* Page header */
.page-header{background:var(--white);border:1.5px solid var(--border);border-radius:28px;padding:22px 28px;display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;overflow:hidden}
.page-header::before{content:'';position:absolute;right:-30px;top:-30px;width:160px;height:160px;border-radius:50%;background:linear-gradient(135deg,var(--t100),var(--b100));opacity:0.7}
.ph-left{display:flex;align-items:center;gap:14px;position:relative;z-index:1}
.ph-icon{width:50px;height:50px;border-radius:15px;background:linear-gradient(135deg,var(--t500),var(--b500));display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;box-shadow:0 6px 20px rgba(20,184,166,0.4)}
.ph-title{font-family:'Quicksand',sans-serif;font-size:21px;font-weight:700;color:var(--text)}
.ph-sub{font-size:13px;color:var(--text3);font-weight:500;margin-top:2px}

/* Stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
.stat-card{background:var(--white);border:1.5px solid var(--border);border-radius:22px;padding:18px 20px;display:flex;align-items:center;gap:14px;transition:transform var(--ease),box-shadow var(--ease)}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(232,50,138,0.1)}
.sc-ico{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
.sc-num{font-family:'Quicksand',sans-serif;font-size:24px;font-weight:700;line-height:1}
.sc-lbl{font-size:11.5px;font-weight:600;color:var(--text3);margin-top:2px}
.sv-amber .sc-ico{background:var(--a100);color:var(--a500)} .sv-amber .sc-num{color:var(--a700)}
.sv-teal  .sc-ico{background:var(--t100);color:var(--t500)} .sv-teal  .sc-num{color:var(--t700)}
.sv-green .sc-ico{background:var(--g100);color:var(--g500)} .sv-green .sc-num{color:var(--g700)}

/* Filter pills */
.filter-pills{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.fpill{display:flex;align-items:center;gap:6px;padding:7px 16px;border-radius:99px;background:var(--white);border:1.5px solid var(--border);font-size:12.5px;font-weight:600;color:var(--text2);cursor:pointer;transition:all var(--ease-plain);user-select:none}
.fpill:hover{border-color:var(--border2);color:var(--p500)}
.fpill.active{background:linear-gradient(135deg,var(--p500),var(--p400));border-color:transparent;color:#fff}

/* Pesanan card */
.pesanan-card{background:var(--white);border:1.5px solid var(--border);border-radius:22px;overflow:hidden;margin-bottom:14px;transition:box-shadow var(--ease),border-color var(--ease-plain)}
.pesanan-card:hover{box-shadow:0 8px 28px rgba(232,50,138,0.1);border-color:var(--border2)}

.pc-header{padding:16px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1.5px solid var(--border);background:linear-gradient(135deg,var(--p50),var(--white))}
.pc-id{display:inline-flex;align-items:center;gap:5px;background:var(--p50);color:var(--p600);border:1px solid var(--border2);border-radius:8px;padding:3px 10px;font-size:12px;font-weight:700;margin-bottom:5px}
.pc-nama{font-size:15px;font-weight:800;color:var(--text)}
.pc-meta{font-size:12px;color:var(--text3);display:flex;align-items:center;gap:10px;margin-top:2px}
.pc-meta i{font-size:12px}
.pc-badge{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:99px;font-size:12px;font-weight:700;white-space:nowrap}
.badge-belum{background:var(--a100);color:var(--a700)}
.badge-dikirim{background:var(--b100);color:var(--b700)}
.badge-terkirim{background:var(--g100);color:var(--g700)}

.pc-body{padding:18px 22px}

/* Current info */
.current-info{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.ci-chip{background:var(--p50);border:1.5px solid var(--border);border-radius:14px;padding:12px 14px}
.ci-lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.7px;color:var(--text3);margin-bottom:3px;display:flex;align-items:center;gap:4px}
.ci-lbl i{color:var(--p400);font-size:11px}
.ci-val{font-size:13.5px;font-weight:700;color:var(--text)}
.ci-empty{font-size:12px;color:var(--text3);font-style:italic}

/* Resi display */
.resi-display{background:linear-gradient(135deg,var(--t100),#dbeafe);border:1.5px solid rgba(20,184,166,0.2);border-radius:14px;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.resi-lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.7px;color:var(--t700);margin-bottom:2px}
.resi-num{font-family:'Quicksand',sans-serif;font-size:16px;font-weight:700;color:var(--text)}

/* Form inline */
.form-section{background:var(--bg);border:1.5px solid var(--border);border-radius:16px;padding:18px 20px}
.form-section-title{font-family:'Quicksand',sans-serif;font-size:13.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:7px;margin-bottom:14px}
.form-section-title i{color:var(--p500)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px}
.form-group{display:flex;flex-direction:column}
.form-group.full{grid-column:1/-1}
.form-lbl{font-size:12px;font-weight:700;color:var(--text2);margin-bottom:6px;display:flex;align-items:center;gap:4px}
.form-lbl i{color:var(--p500);font-size:12px}
.form-ctrl{width:100%;padding:9px 13px;border:1.5px solid var(--border);border-radius:10px;font-family:'Nunito',sans-serif;font-size:13.5px;color:var(--text);background:var(--white);outline:none;transition:border-color var(--ease-plain),box-shadow var(--ease-plain)}
.form-ctrl:focus{border-color:var(--p400);box-shadow:0 0 0 3px rgba(232,50,138,0.1)}
.form-ctrl::placeholder{color:var(--text3)}
textarea.form-ctrl{resize:vertical;min-height:70px}

/* Jasa kirim pills */
.jasa-pills{display:flex;gap:6px;flex-wrap:wrap;margin-top:4px}
.jasa-pill{padding:5px 14px;border-radius:99px;border:1.5px solid var(--border);font-size:12px;font-weight:700;color:var(--text2);cursor:pointer;transition:all var(--ease-plain);background:var(--white);user-select:none}
.jasa-pill:hover{border-color:var(--border2);color:var(--p500)}
.jasa-pill.selected{background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border-color:transparent}

.btn-simpan{display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border-radius:99px;font-size:13.5px;font-weight:700;background:linear-gradient(135deg,var(--p500),var(--p400));color:#fff;border:none;cursor:pointer;transition:all var(--ease);box-shadow:0 4px 14px rgba(232,50,138,0.35);margin-top:14px}
.btn-simpan:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(232,50,138,0.45)}

/* Empty */
.empty-state{text-align:center;padding:60px 24px}
.empty-ico{font-size:48px;color:var(--p200);display:block;margin-bottom:12px}

/* Status badge mapping */
.sk-dikemas{background:var(--a100);color:var(--a700)}
.sk-diserahkan{background:var(--b100);color:var(--b700)}
.sk-dikirim{background:var(--o100);color:var(--o700)}
.sk-terkirim{background:var(--g100);color:var(--g700)}

@media(max-width:900px){.sidebar{transform:translateX(-100%)}.topbar{left:0}.main{margin-left:0}.stat-grid{grid-template-columns:1fr 1fr}.current-info{grid-template-columns:1fr 1fr}.form-row,.form-row-3{grid-template-columns:1fr}}
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
        <a class="nav-item active" href="input_pengiriman.php">
            <i class="bi bi-truck-front-fill"></i> Input Pengiriman
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
        <div class="tb-hello">Input Pengiriman 🚚</div>
        <div class="tb-sub">Isi data pengiriman & resi untuk pesanan yang sudah selesai diproduksi</div>
    </div>
    <nav class="tb-nav">
        <a class="tb-nav-item" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a class="tb-nav-item" href="produksi.php"><i class="bi bi-gear-wide-connected"></i> Produksi</a>
        <a class="tb-nav-item active" href="input_pengiriman.php"><i class="bi bi-truck"></i> Pengiriman</a>
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
            <div class="ph-icon"><i class="bi bi-truck-front-fill"></i></div>
            <div>
                <div class="ph-title">Manajemen Pengiriman</div>
                <div class="ph-sub">Input resi & data kirim untuk pesanan pelanggan</div>
            </div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
        <div class="stat-card sv-amber">
            <div class="sc-ico"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="sc-num"><?= $total_belum ?></div><div class="sc-lbl">Belum Diisi Resi</div></div>
        </div>
        <div class="stat-card sv-teal">
            <div class="sc-ico"><i class="bi bi-send-fill"></i></div>
            <div><div class="sc-num"><?= $total_proses ?></div><div class="sc-lbl">Sedang Dikirim</div></div>
        </div>
        <div class="stat-card sv-green">
            <div class="sc-ico"><i class="bi bi-patch-check-fill"></i></div>
            <div><div class="sc-num"><?= $total_terkirim ?></div><div class="sc-lbl">Terkirim</div></div>
        </div>
    </div>

    <!-- Filter -->
    <div class="filter-pills">
        <div class="fpill active" onclick="filterKirim('semua',this)"><i class="bi bi-grid-3x3-gap"></i> Semua (<?= count($pesanan_list) ?>)</div>
        <div class="fpill" onclick="filterKirim('belum',this)"><i class="bi bi-hourglass"></i> Belum Diisi (<?= $total_belum ?>)</div>
        <div class="fpill" onclick="filterKirim('proses',this)"><i class="bi bi-send"></i> Dikirim (<?= $total_proses ?>)</div>
        <div class="fpill" onclick="filterKirim('terkirim',this)"><i class="bi bi-patch-check-fill"></i> Terkirim (<?= $total_terkirim ?>)</div>
    </div>

    <!-- Cards -->
    <?php if (!empty($pesanan_list)): foreach ($pesanan_list as $idx => $s):
        $has_kirim  = !empty($s['ID_PENGIRIMAN']);
        $sk         = $s['STATUS_KIRIM'] ?? '';
        $is_terkirim= ($sk == 'Terkirim');

        $filter_key = !$has_kirim ? 'belum' : ($is_terkirim ? 'terkirim' : 'proses');

        $badge_cls = !$has_kirim ? 'badge-belum' : ($is_terkirim ? 'badge-terkirim' : 'badge-dikirim');
        $badge_ic  = !$has_kirim ? 'hourglass-split' : ($is_terkirim ? 'patch-check-fill' : 'send-fill');
        $badge_txt = !$has_kirim ? 'Belum Ada Resi' : ($is_terkirim ? 'Terkirim' : 'Sedang Dikirim');

        $sk_cls = match($sk) {
            'Diserahkan' => 'sk-diserahkan',
            'Dikirim'    => 'sk-dikirim',
            'Terkirim'   => 'sk-terkirim',
            default      => 'sk-dikemas'
        };
    ?>
    <div class="pesanan-card" data-filter="<?= $filter_key ?>">
        <!-- Header -->
        <div class="pc-header">
            <div>
                <div class="pc-id"><i class="bi bi-hash"></i><?= htmlspecialchars($s['ID_PESANAN']) ?></div>
                <div class="pc-nama"><?= htmlspecialchars($s['NAMA_PRODUK'] ?? '—') ?> <span style="font-size:13px;font-weight:600;color:var(--text3)">&times; <?= (int)($s['JUMLAH'] ?? 0) ?> pcs</span></div>
                <div class="pc-meta">
                    <span><i class="bi bi-person-fill"></i> <?= htmlspecialchars($s['NAMA_PELANGGAN'] ?? '—') ?></span>
                    <span><i class="bi bi-calendar3"></i> <?= date('d M Y', strtotime($s['WAKTU_PESAN'])) ?></span>
                    <span><i class="bi bi-currency-dollar"></i> Rp <?= number_format($s['TOTAL_HARGA']) ?></span>
                    <?php if (!empty($s['NO_HP'])): ?>
                    <span><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($s['NO_HP']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <span class="pc-badge <?= $badge_cls ?>"><i class="bi bi-<?= $badge_ic ?>"></i> <?= $badge_txt ?></span>
        </div>

        <div class="pc-body">
            <!-- Info pengiriman saat ini (jika sudah ada) -->
            <?php if ($has_kirim): ?>
            <div class="current-info" style="margin-bottom:14px">
                <div class="ci-chip">
                    <div class="ci-lbl"><i class="bi bi-truck"></i> Jasa Kirim</div>
                    <div class="ci-val"><?= htmlspecialchars($s['JASA_KIRIM'] ?: '—') ?></div>
                </div>
                <div class="ci-chip">
                    <div class="ci-lbl"><i class="bi bi-wallet2"></i> Ongkir</div>
                    <div class="ci-val"><?= $s['ONGKIR'] ? 'Rp '.number_format($s['ONGKIR']) : '—' ?></div>
                </div>
                <div class="ci-chip">
                    <div class="ci-lbl"><i class="bi bi-calendar-check"></i> Est. Tiba</div>
                    <div class="ci-val"><?= $s['EST_TIBA'] ? date('d M Y', strtotime($s['EST_TIBA'])) : '—' ?></div>
                </div>
            </div>
            <?php if (!empty($s['NO_RESI'])): ?>
            <div class="resi-display" style="margin-bottom:14px">
                <div>
                    <div class="resi-lbl"><i class="bi bi-upc-scan"></i> Nomor Resi</div>
                    <div class="resi-num"><?= htmlspecialchars($s['NO_RESI']) ?></div>
                </div>
                <span class="pc-badge <?= $sk_cls ?>"><?= $sk ?></span>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Form input/update -->
            <?php if (!$is_terkirim): ?>
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-<?= $has_kirim ? 'pencil-square' : 'plus-circle-fill' ?>"></i>
                    <?= $has_kirim ? 'Update Data Pengiriman' : 'Input Data Pengiriman' ?>
                </div>
                <form method="POST">
                    <input type="hidden" name="id_pesanan" value="<?= $s['ID_PESANAN'] ?>">

                    <!-- Jasa kirim pills -->
                    <div class="form-group" style="margin-bottom:12px">
                        <label class="form-lbl"><i class="bi bi-truck"></i> Jasa Kirim</label>
                        <div class="jasa-pills" id="jasa-pills-<?= $idx ?>">
                            <?php $jasas = ['JNE','J&T','SiCepat','Pos Indonesia','Anteraja','Ninja Xpress','GoSend','GrabExpress'];
                            foreach ($jasas as $j): ?>
                            <div class="jasa-pill <?= ($s['JASA_KIRIM'] == $j) ? 'selected' : '' ?>"
                                 onclick="pilihJasa('<?= $idx ?>','<?= $j ?>')">
                                <?= $j ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" name="jasa_kirim" id="jasa-input-<?= $idx ?>"
                               class="form-ctrl" style="margin-top:8px"
                               placeholder="Atau ketik manual..."
                               value="<?= htmlspecialchars($s['JASA_KIRIM'] ?? '') ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-lbl"><i class="bi bi-upc-scan"></i> Nomor Resi</label>
                            <input type="text" name="no_resi" class="form-ctrl"
                                   placeholder="cth: JNE123456789"
                                   value="<?= htmlspecialchars($s['NO_RESI'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-lbl"><i class="bi bi-wallet2"></i> Ongkos Kirim (Rp)</label>
                            <input type="number" name="ongkir" class="form-ctrl"
                                   placeholder="cth: 15000"
                                   value="<?= $s['ONGKIR'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-lbl"><i class="bi bi-calendar-event"></i> Estimasi Tiba</label>
                            <input type="date" name="est_tiba" class="form-ctrl"
                                   value="<?= $s['EST_TIBA'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-lbl"><i class="bi bi-arrow-repeat"></i> Status Pengiriman</label>
                            <select name="status_kirim" class="form-ctrl">
                                <?php foreach (['Dikemas','Diserahkan','Dikirim','Terkirim'] as $st): ?>
                                <option value="<?= $st ?>" <?= ($s['STATUS_KIRIM'] == $st) ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-lbl"><i class="bi bi-geo-alt-fill"></i> Alamat Pengiriman</label>
                        <textarea name="alamat_kirim" class="form-ctrl"
                                  placeholder="Masukkan alamat lengkap pengiriman..."><?= htmlspecialchars($s['ALAMAT_KIRIM'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" name="simpan" class="btn-simpan">
                        <i class="bi bi-<?= $has_kirim ? 'check-circle-fill' : 'send-fill' ?>"></i>
                        <?= $has_kirim ? 'Update Pengiriman' : 'Simpan & Kirim' ?>
                    </button>
                </form>
            </div>
            <?php else: ?>
            <!-- Terkirim — tampilkan alamat saja -->
            <?php if (!empty($s['ALAMAT_KIRIM'])): ?>
            <div style="display:flex;align-items:flex-start;gap:10px;background:var(--p50);border:1.5px dashed var(--border2);border-radius:14px;padding:13px 16px">
                <div style="width:32px;height:32px;border-radius:9px;background:var(--p100);display:flex;align-items:center;justify-content:center;color:var(--p500);font-size:15px;flex-shrink:0">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.7px;color:var(--text3);margin-bottom:3px">Alamat Tujuan</div>
                    <div style="font-size:13px;font-weight:600;color:var(--text)"><?= nl2br(htmlspecialchars($s['ALAMAT_KIRIM'])) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">
        <i class="bi bi-truck empty-ico"></i>
        <div style="font-family:'Quicksand',sans-serif;font-size:17px;font-weight:700;color:var(--text2);margin-bottom:6px">Belum ada pesanan yang perlu dikirim</div>
        <div style="font-size:13px;color:var(--text3)">Pesanan yang sudah diproses akan muncul di sini</div>
    </div>
    <?php endif; ?>

</div>
</main>

<script>
function pilihJasa(idx, nama) {
    document.querySelectorAll('#jasa-pills-' + idx + ' .jasa-pill').forEach(p => p.classList.remove('selected'));
    event.target.classList.add('selected');
    document.getElementById('jasa-input-' + idx).value = nama;
}

function filterKirim(status, el) {
    document.querySelectorAll('.fpill').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.pesanan-card').forEach(card => {
        card.style.display = (status === 'semua' || card.dataset.filter === status) ? 'block' : 'none';
    });
}
</script>
</body>
</html>