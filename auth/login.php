<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Sistem Informasi Konveksi</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f4f4;
        }
        .login-box {
            width: 350px;
            margin: 100px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        input, select, button {
            width: 100%;
            padding: 8px;
            margin-top: 10px;
        }
        button {
            background: #2c7be5;
            color: white;
            border: none;
            cursor: pointer;
        }
        .info {
            font-size: 12px;
            margin-top: 10px;
            color: #555;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h3 align="center">Login Sistem Konveksi</h3>

    <form action="proses_login.php" method="post">
        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Login sebagai</label>
        <select name="role" required>
            <option value="">-- Pilih Role --</option>
            <option value="owner">Owner</option>
            <option value="penjahit">Penjahit</option>
            <option value="pelanggan">Pelanggan</option>
        </select>

        <button type="submit">Login</button>
    </form>

    <div class="info">
        * Khusus pelanggan wajib daftar terlebih dahulu
        <br>
        <a href="register.php">Daftar Pelanggan</a>
    </div>
</div>

</body>
</html>
