<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelanggan | Konveksi Apps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7fe;
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: flex-start; /* Agar tidak terpotong saat scroll */
            padding: 40px 0; /* Memberi ruang atas bawah saat scroll */
            min-height: 100vh;
        }
        .register-card {
            background: white;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 450px;
        }
        .brand-title {
            color: #2b63ff;
            font-weight: 800;
            text-align: center;
        }
        .form-label {
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }
        .form-control {
            background-color: #eff4ff;
            border: none;
            padding: 12px;
            border-radius: 12px;
        }
        .btn-register {
            background: #5a32ea;
            color: white;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <h2 class="brand-title">Konveksi Apps</h2>
        <p class="text-muted text-center small mb-4">Daftar Akun Pelanggan Baru</p>
        
        <form action="proses_register.php" method="post">
            <div class="mb-3">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama_pelanggan" class="form-control" placeholder="Nama Anda/Instansi" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Alamat</label>
                <input type="text" name="alamat_pelanggan" class="form-control" placeholder="Alamat Lengkap" required>
            </div>
            <div class="mb-3">
                <label class="form-label">No. HP (WhatsApp)</label>
                <input type="number" name="no_hp" class="form-control" placeholder="62812xxx" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Username untuk login" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-register">Daftar Sekarang</button>
        </form>
        <div class="mt-3 text-center">
            <a href="../index.php" class="small text-muted text-decoration-none">Sudah punya akun? Login</a>
        </div>
    </div>
</body>
</html>