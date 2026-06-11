<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login 🌸 | Konveksi Apps</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --p50:#fff0f5;--p100:#ffd6e7;--p200:#ffadd0;--p300:#ff80b8;
    --p400:#f950a0;--p500:#e8328a;--p600:#cc1a73;--p700:#a8105d;
    --v100:#f3e8ff;--v300:#d8b4fe;--v500:#a855f7;
    --g100:#dcfce7;--g500:#22c55e;
    --r100:#fee2e2;--r500:#ef4444;--r700:#991b1b;
    --white:#ffffff;--bg:#fff5f9;
    --text:#3d1a28;--text2:#7d4460;--text3:#b07090;
    --border:rgba(232,50,138,0.13);--border2:rgba(232,50,138,0.24);
    --ease:0.2s cubic-bezier(0.34,1.56,0.64,1);--ease-plain:0.17s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body {
    font-family:'Nunito',sans-serif;
    min-height: 100vh;
    background: var(--bg);
    
    /* SOLUSI TERBAIK UNTUK SCROLLING: */
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    
    /* Berikan padding vertikal yang cukup agar saat di-scroll ke bawah tidak mepet */
    padding: 40px 16px; 
    
    position: relative;
    color: var(--text);
    box-sizing: border-box;
}

/* Dot pattern */
body::before {
    content:'';position:fixed;inset:0;
    background-image:radial-gradient(circle,rgba(232,50,138,0.07) 1.5px,transparent 1.5px);
    background-size:28px 28px;pointer-events:none;z-index:0;
}

/* Decorative blobs */
.blob {
    position:fixed;border-radius:50%;pointer-events:none;z-index:0;
    filter:blur(60px);opacity:0.35;
}
.blob-1{width:500px;height:500px;top:-160px;right:-160px;background:radial-gradient(circle,var(--p300),transparent 70%)}
.blob-2{width:400px;height:400px;bottom:-140px;left:-140px;background:radial-gradient(circle,var(--v300),transparent 70%)}
.blob-3{width:260px;height:260px;bottom:10%;right:10%;background:radial-gradient(circle,var(--p200),transparent 70%)}

/* Shimmer bar top */
.shimmer-bar {
    position:fixed;top:0;left:0;right:0;height:4px;z-index:10;
    background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));
    background-size:200%;
    animation:shimmer 3s linear infinite;
}
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}

/* Card */
.login-card {
    background:var(--white);
    border-radius:28px;
    padding:40px 38px 34px;
    width:100%;max-width:430px;
    box-shadow:0 24px 64px rgba(232,50,138,0.14),0 4px 16px rgba(232,50,138,0.06);
    border:1.5px solid var(--border);
    position:relative;z-index:1;
    animation:slideUp 0.45s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes slideUp{from{opacity:0;transform:translateY(36px) scale(0.96)}to{opacity:1;transform:none}}

/* Deco stripe on card top */
.login-card::before {
    content:'';position:absolute;top:0;left:0;right:0;height:4px;
    border-radius:28px 28px 0 0;
    background:linear-gradient(90deg,var(--p400),var(--v500),var(--p300),var(--p500));
    background-size:200%;
    animation:shimmer 3s linear infinite;
}

/* Brand */
.brand-wrap{text-align:center;margin-bottom:30px}
.brand-mark-wrap{
    display:flex;align-items:center;justify-content:center;gap:13px;
    margin-bottom:14px;
}
.brand-mark {
    width:62px;height:62px;border-radius:19px;
    background:linear-gradient(135deg,var(--p500) 0%,var(--p400) 50%,var(--v500) 100%);
    display:flex;align-items:center;justify-content:center;
    font-size:28px;color:#fff;flex-shrink:0;
    box-shadow:0 8px 24px rgba(232,50,138,0.45),0 0 0 4px rgba(232,50,138,0.12);
    animation:markPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.15s both;
}
@keyframes markPop{from{transform:scale(0.7) rotate(-15deg);opacity:0}to{transform:none;opacity:1}}
.brand-text{text-align:left}
.brand-name{font-family:'Quicksand',sans-serif;font-size:22px;font-weight:700;color:var(--text);line-height:1.1}
.brand-panel{font-size:11px;font-weight:700;color:var(--p500);letter-spacing:0.8px;text-transform:uppercase;margin-top:2px}
.brand-sub{font-size:12.5px;color:var(--text3);font-weight:500}

/* Divider */
.divider{height:1px;background:linear-gradient(90deg,transparent,var(--border2),transparent);margin:0 0 24px}

/* Field */
.field-group{margin-bottom:16px}
.field-label{display:block;font-size:12.5px;font-weight:700;color:var(--text2);margin-bottom:7px}
.input-wrap{position:relative}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:16px;pointer-events:none;transition:color var(--ease-plain)}
.input-wrap:focus-within .input-icon{color:var(--p500)}
.form-input {
    width:100%;padding:11px 14px 11px 42px;
    border:1.5px solid var(--border);border-radius:12px;
    font-size:13.5px;font-family:'Nunito',sans-serif;
    color:var(--text);background:var(--p50);
    outline:none;
    transition:border-color var(--ease-plain),background var(--ease-plain),box-shadow var(--ease-plain);
}
.form-input:focus{
    border-color:var(--p400);background:var(--white);
    box-shadow:0 0 0 3px rgba(232,50,138,0.1);
}
.form-input::placeholder{color:var(--text3)}
.pwd-toggle{
    position:absolute;right:13px;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;
    color:var(--text3);font-size:16px;padding:0;
    display:flex;align-items:center;transition:color var(--ease-plain);
}
.pwd-toggle:hover{color:var(--p500)}

/* Role cards */
.role-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.role-option{display:none}
.role-option:checked + .role-label {
    border-color:var(--p500);
    background:linear-gradient(135deg,var(--p50),var(--v100));
    color:var(--p600);
    box-shadow:0 0 0 3px rgba(232,50,138,0.12);
}
.role-option:checked + .role-label .role-icon{
    background:linear-gradient(135deg,var(--p500),var(--v500));
    color:#fff;
    box-shadow:0 4px 12px rgba(232,50,138,0.35);
}
.role-label {
    display:flex;flex-direction:column;align-items:center;gap:6px;
    border:1.5px solid var(--border);border-radius:14px;
    padding:12px 8px;cursor:pointer;
    font-size:12px;font-weight:700;color:var(--text2);
    background:var(--p50);
    transition:all var(--ease-plain);user-select:none;
}
.role-label:hover{border-color:var(--border2);background:var(--p100);color:var(--p600)}
.role-icon {
    width:36px;height:36px;border-radius:10px;
    background:var(--p100);color:var(--p500);
    display:flex;align-items:center;justify-content:center;
    font-size:17px;
    transition:all var(--ease-plain);
}

/* Button */
.btn-login {
    width:100%;
    background:linear-gradient(135deg,var(--p500),var(--p400));
    color:#fff;border:none;border-radius:12px;
    padding:13px;
    font-size:14.5px;font-weight:700;font-family:'Nunito',sans-serif;
    cursor:pointer;
    display:flex;align-items:center;justify-content:center;gap:8px;
    box-shadow:0 6px 20px rgba(232,50,138,0.38);
    transition:transform var(--ease),box-shadow var(--ease),opacity var(--ease-plain);
    margin-top:22px;
}
.btn-login:hover{transform:translateY(-2px) scale(1.02);box-shadow:0 10px 28px rgba(232,50,138,0.48)}
.btn-login:active{transform:translateY(0) scale(1)}

/* Register box */
.register-box {
    margin-top:18px;text-align:center;
    padding:13px 16px;
    background:var(--p50);
    border:1.5px solid var(--border);border-radius:14px;
}
.register-box p{font-size:12.5px;color:var(--text3);margin-bottom:6px;font-weight:500}
.register-box a {
    font-size:13px;font-weight:700;color:var(--p500);
    text-decoration:none;
    display:inline-flex;align-items:center;gap:5px;
    transition:color var(--ease-plain);
}
.register-box a:hover{color:var(--p700)}

/* Error */
.alert-error {
    background:var(--r100);border:1.5px solid rgba(239,68,68,0.25);
    border-radius:12px;padding:11px 14px;
    font-size:12.5px;color:var(--r700);font-weight:600;
    display:flex;align-items:center;gap:8px;
    margin-bottom:18px;
}

/* Footer */
.card-footer-note{
    text-align:center;margin-top:16px;
    font-size:11px;color:var(--text3);font-weight:500;
    display:flex;align-items:center;justify-content:center;gap:5px;
}
.card-footer-note i{color:var(--p300);font-size:12px}

@media(max-width:440px){.login-card{padding:32px 20px 26px}.brand-mark{width:52px;height:52px;font-size:24px}}
</style>
</head>
<body>

<div class="shimmer-bar"></div>
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<div class="login-card">

    <!-- Brand -->
    <div class="brand-wrap">
        <div class="brand-mark-wrap">
            <div class="brand-mark">
                <i class="bi bi-scissors"></i>
            </div>
            <div class="brand-text">
                <div class="brand-name">Konveksi Apps</div>
                <div class="brand-panel">Management System</div>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <?php if (!empty($_GET['error'])): ?>
    <div class="alert-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?php
            $err = $_GET['error'];
            if ($err === 'wrong')      echo 'Username atau password salah.';
            elseif ($err === 'role')   echo 'Role tidak sesuai dengan akun kamu.';
            else                       echo 'Login gagal. Silakan coba lagi.';
        ?>
    </div>
    <?php endif; ?>

    <form action="auth/proses_login.php" method="POST">

        <!-- Username -->
        <div class="field-group">
            <label class="field-label" for="username">Username</label>
            <div class="input-wrap">
                <i class="bi bi-person-fill input-icon"></i>
                <input type="text" id="username" name="username" class="form-input"
                       placeholder="Masukkan username kamu" required autocomplete="username">
            </div>
        </div>

        <!-- Password -->
        <div class="field-group">
            <label class="field-label" for="password">Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock-fill input-icon"></i>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="••••••••••" required autocomplete="current-password">
                <button type="button" class="pwd-toggle" onclick="togglePwd()">
                    <i class="bi bi-eye" id="pwd-icon"></i>
                </button>
            </div>
        </div>

        <!-- Role -->
        <div class="field-group">
            <label class="field-label">Login Sebagai</label>
            <div class="role-grid">
                <label>
                    <input type="radio" name="role" value="owner" class="role-option" required>
                    <span class="role-label">
                        <span class="role-icon"><i class="bi bi-shield-fill-check"></i></span>
                        Owner
                    </span>
                </label>
                <label>
                    <input type="radio" name="role" value="penjahit" class="role-option">
                    <span class="role-label">
                        <span class="role-icon"><i class="bi bi-scissors"></i></span>
                        Penjahit
                    </span>
                </label>
                <label>
                    <input type="radio" name="role" value="pelanggan" class="role-option">
                    <span class="role-label">
                        <span class="role-icon"><i class="bi bi-bag-heart-fill"></i></span>
                        Pelanggan
                    </span>
                </label>
            </div>
        </div>

        <button type="submit" name="login" class="btn-login">
            <i class="bi bi-box-arrow-in-right"></i> Masuk Sekarang
        </button>

    </form>

    <div class="register-box">
        <p>Belum punya akun pelanggan?</p>
        <a href="auth/register.php">
            <i class="bi bi-person-plus-fill"></i> Daftar Sebagai Pelanggan
        </a>
    </div>

    <div class="card-footer-note">
        <i class="bi bi-heart-fill"></i>
        Konveksi Apps · <?= date('Y') ?>
    </div>

</div>

<script>
function togglePwd() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('pwd-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>