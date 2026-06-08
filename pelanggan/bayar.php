<?php
session_start();
include "../config/koneksi.php";

if ($_SESSION['role'] != 'pelanggan') { header("Location: ../index.php"); exit; }

$id_pesanan = $_GET['id'];
$query = mysqli_query($koneksi, "SELECT p.*, pr.NAMA_PRODUK, dp.JUMLAH 
                                 FROM pesanan p 
                                 JOIN detail_pesanan dp ON p.ID_PESANAN = dp.ID_PESANAN 
                                 JOIN produk pr ON dp.ID_PRODUK = pr.ID_PRODUK 
                                 WHERE p.ID_PESANAN = '$id_pesanan'");
$data = mysqli_fetch_assoc($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran | Konveksi Apps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --pink-50:  #fff0f6;
            --pink-100: #ffd6e7;
            --pink-200: #ffadd2;
            --pink-400: #f06292;
            --pink-500: #e91e8c;
            --pink-600: #c2185b;
            --pink-700: #880e4f;
            --white: #ffffff;
            --gray-50: #fafafa;
            --gray-100: #f5f5f5;
            --gray-200: #eeeeee;
            --gray-400: #bdbdbd;
            --gray-600: #757575;
            --gray-800: #424242;
            --gray-900: #212121;
            --shadow-sm: 0 2px 8px rgba(233,30,140,0.08);
            --shadow-md: 0 8px 24px rgba(233,30,140,0.12);
            --shadow-lg: 0 20px 48px rgba(233,30,140,0.16);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--pink-50);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Decorative background blobs */
        body::before {
            content: '';
            position: fixed;
            top: -120px; right: -120px;
            width: 480px; height: 480px;
            background: radial-gradient(circle, rgba(240,98,146,0.18) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -100px; left: -100px;
            width: 380px; height: 380px;
            background: radial-gradient(circle, rgba(233,30,140,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        .page-wrapper {
            position: relative; z-index: 1;
            min-height: 100vh;
            padding: 32px 16px 48px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        /* ── Card ── */
        .pay-card {
            background: var(--white);
            border-radius: 28px;
            padding: 36px 32px;
            width: 100%;
            max-width: 520px;
            box-shadow: var(--shadow-lg);
            border: 1.5px solid var(--pink-100);
            animation: slideUp 0.45s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes slideUp {
            from { opacity:0; transform: translateY(32px) scale(0.97); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }

        /* ── Back Button ── */
        .btn-back {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--pink-50);
            border: 1.5px solid var(--pink-100);
            color: var(--pink-500);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 18px;
            text-decoration: none;
            transition: background 0.2s, transform 0.15s;
            flex-shrink: 0;
        }
        .btn-back:hover { background: var(--pink-100); transform: scale(1.08); color: var(--pink-600); }

        .page-title { font-size: 18px; font-weight: 800; color: var(--gray-900); line-height: 1.2; }
        .page-sub   { font-size: 12.5px; color: var(--gray-600); margin-top: 2px; }

        /* ── Order Summary ── */
        .order-summary {
            background: linear-gradient(135deg, var(--pink-50) 0%, #fff5fb 100%);
            border: 1.5px solid var(--pink-100);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }
        .order-summary::before {
            content: '';
            position: absolute; top: -20px; right: -20px;
            width: 100px; height: 100px;
            background: radial-gradient(circle, rgba(233,30,140,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .order-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .order-label { font-size: 12px; color: var(--gray-600); font-weight: 500; }
        .order-value { font-size: 12.5px; color: var(--gray-800); font-weight: 600; }
        .order-divider { height: 1px; background: var(--pink-100); margin: 12px 0; }
        .total-label { font-size: 14px; font-weight: 700; color: var(--gray-900); }
        .total-amount {
            font-size: 22px; font-weight: 800;
            background: linear-gradient(90deg, var(--pink-500), var(--pink-700));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* ── Section Label ── */
        .section-label {
            font-size: 12px; font-weight: 700; color: var(--gray-600);
            text-transform: uppercase; letter-spacing: 0.8px;
            margin-bottom: 14px;
        }

        /* ── Method Buttons ── */
        .methods-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }

        .method-btn {
            border: 2px solid var(--pink-100);
            border-radius: 18px;
            padding: 16px 14px;
            cursor: pointer;
            background: var(--white);
            transition: border-color 0.2s, background 0.2s, transform 0.15s, box-shadow 0.2s;
            text-align: left;
            position: relative;
            overflow: hidden;
        }
        .method-btn::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, var(--pink-50), transparent);
            opacity: 0; transition: opacity 0.2s;
        }
        .method-btn:hover { border-color: var(--pink-400); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .method-btn:hover::before { opacity: 1; }
        .method-btn.active {
            border-color: var(--pink-500);
            background: linear-gradient(135deg, var(--pink-50), #fff0f9);
            box-shadow: 0 0 0 4px rgba(233,30,140,0.08), var(--shadow-md);
            transform: translateY(-2px);
        }
        .method-btn.active .method-icon { background: var(--pink-500); color: white; }

        .method-icon {
            width: 44px; height: 44px;
            border-radius: 14px;
            background: var(--pink-50);
            color: var(--pink-500);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            margin-bottom: 10px;
            transition: background 0.2s, color 0.2s;
        }
        .method-name { font-size: 13.5px; font-weight: 700; color: var(--gray-900); }
        .method-desc { font-size: 11px; color: var(--gray-600); margin-top: 2px; }

        /* Active check badge */
        .check-badge {
            position: absolute; top: 10px; right: 10px;
            width: 20px; height: 20px;
            background: var(--pink-500);
            border-radius: 50%;
            display: none; align-items: center; justify-content: center;
            color: white; font-size: 11px;
        }
        .method-btn.active .check-badge { display: flex; }

        /* ── Sections (QRIS / Transfer) ── */
        .section { display: none; }
        .section.show { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }

        /* QR Box */
        .qr-box {
            background: linear-gradient(135deg, #fff0f6, #fff5fb);
            border: 1.5px solid var(--pink-100);
            border-radius: 20px;
            padding: 28px 24px;
            text-align: center;
            margin-bottom: 20px;
        }
        .qr-title { font-size: 13.5px; font-weight: 700; color: var(--gray-800); margin-bottom: 18px; }
        .qr-frame {
            display: inline-block;
            padding: 12px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(233,30,140,0.12);
            border: 2.5px solid var(--pink-200);
            margin-bottom: 16px;
        }
        .qr-frame img { width: 185px; height: auto; display: block; border-radius: 8px; }
        .qr-order-id { font-size: 12px; color: var(--gray-600); }
        .qr-order-id strong { color: var(--gray-900); }
        .qr-amount { font-size: 20px; font-weight: 800; color: var(--pink-500); margin-top: 4px; }

        .alert-info-custom {
            background: linear-gradient(90deg, rgba(233,30,140,0.06), rgba(240,98,146,0.04));
            border: 1px solid var(--pink-100);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 12px;
            color: var(--gray-700);
            display: flex; align-items: flex-start; gap: 8px;
        }
        .alert-info-custom i { color: var(--pink-500); margin-top: 1px; flex-shrink: 0; }

        /* Bank Cards */
        .bank-card {
            background: var(--gray-50);
            border: 1.5px solid var(--gray-200);
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 12px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .bank-card:hover { border-color: var(--pink-200); box-shadow: var(--shadow-sm); }
        .bank-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .bank-name { font-size: 13px; font-weight: 700; color: var(--gray-800); }
        .btn-copy {
            background: white;
            border: 1.5px solid var(--pink-200);
            color: var(--pink-500);
            border-radius: 8px;
            padding: 3px 10px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex; align-items: center; gap: 4px;
            transition: background 0.2s, color 0.2s;
        }
        .btn-copy:hover { background: var(--pink-500); color: white; border-color: var(--pink-500); }
        .btn-copy.copied { background: #4caf50; color: white; border-color: #4caf50; }
        .rek-number {
            font-family: 'Courier New', monospace;
            font-size: 18px; font-weight: 700;
            color: var(--pink-500);
            letter-spacing: 2px;
        }
        .bank-holder { font-size: 12px; color: var(--gray-600); margin-top: 4px; }
        .bank-holder span { font-weight: 600; color: var(--gray-800); }

        /* ── Upload Form ── */
        .upload-section {
            margin-top: 20px;
            animation: fadeIn 0.3s ease;
        }
        .upload-label {
            font-size: 12.5px; font-weight: 700; color: var(--gray-800);
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 10px;
        }
        .step-dot {
            width: 22px; height: 22px;
            background: var(--pink-500);
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; color: white;
            flex-shrink: 0;
        }

        .file-drop {
            border: 2px dashed var(--pink-200);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            background: var(--pink-50);
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }
        .file-drop:hover { border-color: var(--pink-400); background: #fff0f8; }
        .file-drop input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .file-drop-icon { font-size: 28px; color: var(--pink-300); margin-bottom: 6px; }
        .file-drop-text { font-size: 12.5px; color: var(--gray-600); }
        .file-drop-text strong { color: var(--pink-500); }
        .file-hint { font-size: 11px; color: var(--gray-400); margin-top: 4px; }
        .file-name-display {
            display: none; margin-top: 10px;
            background: white;
            border: 1.5px solid var(--pink-200);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 12px;
            color: var(--gray-700);
            display: none; align-items: center; gap: 6px;
        }
        .file-name-display i { color: var(--pink-500); }

        /* ── Submit Button ── */
        .btn-pay {
            background: linear-gradient(135deg, var(--pink-500), var(--pink-600));
            color: white;
            border: none;
            border-radius: 16px;
            padding: 15px;
            font-size: 14.5px;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 6px 20px rgba(233,30,140,0.35);
            margin-top: 20px;
            letter-spacing: 0.2px;
        }
        .btn-pay:hover { opacity: 0.92; transform: translateY(-1px); box-shadow: 0 10px 28px rgba(233,30,140,0.4); }
        .btn-pay:active { transform: translateY(0); }

        .btn-cancel {
            display: block; width: 100%;
            text-align: center;
            font-size: 13px; color: var(--gray-600);
            text-decoration: none;
            margin-top: 12px;
            padding: 8px;
            border-radius: 10px;
            transition: color 0.2s, background 0.2s;
        }
        .btn-cancel:hover { color: var(--pink-500); background: var(--pink-50); }

        /* ── Toast ── */
        .toast-copied {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(80px);
            background: var(--gray-900); color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 13px; font-weight: 600;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            display: flex; align-items: center; gap: 8px;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
            z-index: 9999;
            pointer-events: none;
        }
        .toast-copied.show { transform: translateX(-50%) translateY(0); }
        .toast-copied i { color: #4caf50; }

        @media (max-width: 480px) {
            .pay-card { padding: 24px 18px; }
            .methods-grid { gap: 8px; }
        }
    </style>
</head>
<body>
<div class="page-wrapper">
<div class="pay-card">

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i></a>
        <div>
            <div class="page-title">Pembayaran</div>
            <div class="page-sub">Selesaikan transaksi Anda</div>
        </div>
    </div>

    <!-- Order Summary -->
    <div class="order-summary mb-4">
        <div class="order-row">
            <span class="order-label"><i class="bi bi-hash me-1"></i>ID Pesanan</span>
            <span class="order-value"><?= htmlspecialchars($data['ID_PESANAN']); ?></span>
        </div>
        <div class="order-row">
            <span class="order-label"><i class="bi bi-box me-1"></i>Produk</span>
            <span class="order-value"><?= htmlspecialchars($data['NAMA_PRODUK']); ?></span>
        </div>
        <div class="order-row" style="margin-bottom:0">
            <span class="order-label"><i class="bi bi-layers me-1"></i>Jumlah</span>
            <span class="order-value"><?= $data['JUMLAH']; ?> pcs</span>
        </div>
        <div class="order-divider"></div>
        <div class="order-row" style="margin-bottom:0">
            <span class="total-label">Total Tagihan</span>
            <span class="total-amount">Rp <?= number_format($data['TOTAL_HARGA']); ?></span>
        </div>
    </div>

    <!-- Method Selection -->
    <div class="section-label">Pilih Metode Pembayaran</div>
    <div class="methods-grid">
        <button class="method-btn" id="btn-qris" onclick="pilihMetode('qris')">
            <span class="check-badge"><i class="bi bi-check"></i></span>
            <div class="method-icon"><i class="bi bi-qr-code"></i></div>
            <div class="method-name">QRIS</div>
            <div class="method-desc">Scan & bayar instan</div>
        </button>
        <button class="method-btn" id="btn-transfer" onclick="pilihMetode('transfer')">
            <span class="check-badge"><i class="bi bi-check"></i></span>
            <div class="method-icon"><i class="bi bi-bank2"></i></div>
            <div class="method-name">Transfer Bank</div>
            <div class="method-desc">ATM / m-Banking</div>
        </button>
    </div>

    <!-- SECTION: QRIS -->
    <div class="section" id="section-qris">
        <div class="qr-box">
            <div class="qr-title"><i class="bi bi-qr-code-scan me-1"></i>Scan QR Code Berikut</div>
            <div class="qr-frame">
                <img src="../assets/qris.png" alt="QRIS Payment">
            </div>
            <div class="qr-order-id">ID Pesanan: <strong><?= htmlspecialchars($data['ID_PESANAN']); ?></strong></div>
            <div class="qr-amount">Rp <?= number_format($data['TOTAL_HARGA']); ?></div>
        </div>
        <div class="alert-info-custom mb-4">
            <i class="bi bi-info-circle-fill"></i>
            <span>Setelah berhasil scan & bayar, upload screenshot bukti pembayaran di bawah ini untuk konfirmasi.</span>
        </div>
    </div>

    <!-- SECTION: Transfer Bank -->
    <div class="section" id="section-transfer">
        <div class="section-label mb-3">Transfer ke salah satu rekening:</div>

        <div class="bank-card">
            <div class="bank-header">
                <div class="bank-name">🏦 BNI</div>
                <button class="btn-copy" id="copy-bni" onclick="salin('1846581654', 'copy-bni')">
                    <i class="bi bi-copy"></i> Salin
                </button>
            </div>
            <div class="rek-number">1846 5816 54</div>
            <div class="bank-holder">a.n. <span>Nadia Raisa Romadhoni</span></div>
        </div>

        <div class="bank-card">
            <div class="bank-header">
                <div class="bank-name">🌊 SeaBank</div>
                <button class="btn-copy" id="copy-sea" onclick="salin('901420676512', 'copy-sea')">
                    <i class="bi bi-copy"></i> Salin
                </button>
            </div>
            <div class="rek-number">9014 2067 6512</div>
            <div class="bank-holder">a.n. <span>Nadia Raisa Romadhoni</span></div>
        </div>

        <div class="alert-info-custom mb-4">
            <i class="bi bi-exclamation-diamond-fill"></i>
            <span>Transfer tepat sebesar <strong style="color:var(--pink-500)">Rp <?= number_format($data['TOTAL_HARGA']); ?></strong>, lalu upload bukti di bawah.</span>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="section" id="section-form">
        <form action="proses_bayar.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_pesanan" value="<?= $id_pesanan ?>">
            <input type="hidden" name="total_harga" value="<?= $data['TOTAL_HARGA'] ?>">
            <input type="hidden" name="metode_bayar" id="input-metode" value="">

            <div class="upload-label">
                <span class="step-dot"><i class="bi bi-upload" style="font-size:10px"></i></span>
                Upload Bukti Pembayaran
            </div>

            <div class="file-drop" id="file-drop-area">
                <input type="file" name="bukti_bayar" id="file-input" required accept="image/*" onchange="showFileName(this)">
                <div class="file-drop-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                <div class="file-drop-text">Klik atau <strong>drag & drop</strong> file di sini</div>
                <div class="file-hint">Format: JPG / PNG · Maks 5MB</div>
            </div>

            <div class="file-name-display" id="file-name-display">
                <i class="bi bi-file-earmark-image-fill"></i>
                <span id="file-name-text">—</span>
            </div>

            <div class="alert-info-custom mt-3">
                <i class="bi bi-clock-history"></i>
                <span>Pembayaran dikonfirmasi admin dalam 1×24 jam. Struk dikirim setelah konfirmasi.</span>
            </div>

            <button type="submit" class="btn-pay">
                <i class="bi bi-send-fill"></i>
                Kirim Bukti Pembayaran
            </button>
        </form>
        <a href="dashboard.php" class="btn-cancel"><i class="bi bi-x-circle me-1"></i>Batalkan Pembayaran</a>
    </div>

</div>
</div>

<!-- Toast -->
<div class="toast-copied" id="toast">
    <i class="bi bi-check-circle-fill"></i>
    <span id="toast-msg">Disalin!</span>
</div>

<script>
function pilihMetode(metode) {
    ['qris','transfer'].forEach(m => {
        document.getElementById('btn-' + m).classList.remove('active');
        document.getElementById('section-' + m).classList.remove('show');
    });
    document.getElementById('btn-' + metode).classList.add('active');
    document.getElementById('section-' + metode).classList.add('show');
    document.getElementById('section-form').classList.add('show');
    document.getElementById('input-metode').value = metode;
}

function salin(nomor, btnId) {
    navigator.clipboard.writeText(nomor).then(() => {
        const btn = document.getElementById(btnId);
        const original = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Tersalin!';
        showToast('Nomor rekening berhasil disalin!');
        setTimeout(() => { btn.classList.remove('copied'); btn.innerHTML = original; }, 2000);
    });
}

function showToast(msg) {
    const t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

function showFileName(input) {
    if (input.files && input.files[0]) {
        const display = document.getElementById('file-name-display');
        display.style.display = 'flex';
        document.getElementById('file-name-text').textContent = input.files[0].name;
        document.getElementById('file-drop-area').style.borderColor = 'var(--pink-500)';
        document.getElementById('file-drop-area').style.background = '#fff5fb';
    }
}
</script>
</body>
</html>