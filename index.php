<?php
session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['cip'] ?? '');
    if (empty($dni)) {
        $error = 'Si us plau, introdueix el teu CIP.';
    } else {
        $host = 'localhost'; $port = '5432'; $dbname = 'hp';
        $user = 'postgres';  $pass = 'http';
        try {
            $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $rol = null;
            $stmt = $pdo->prepare("SELECT 1 FROM paciente WHERE cip = ? LIMIT 1");
            $stmt->execute([$dni]);
            if ($stmt->fetchColumn()) { $rol = 'pacient'; }
            if (!$rol) {
                $stmt = $pdo->prepare("SELECT 1 FROM medico WHERE cip = ? LIMIT 1");
                $stmt->execute([$dni]);
                if ($stmt->fetchColumn()) { $rol = 'metge'; }
            }
            if (!$rol) {
                $stmt = $pdo->prepare("SELECT 1 FROM enfermera WHERE cip = ? LIMIT 1");
                $stmt->execute([$dni]);
                if ($stmt->fetchColumn()) { $rol = 'infermer'; }
            }
            if ($rol) {
                $_SESSION['dni'] = $dni;
                $_SESSION['rol'] = $rol;
                $paginaDestino = ['pacient' => 'pacient.php', 'metge' => 'metge.php', 'infermer' => 'infermer.php'][$rol];
                header("Location: $paginaDestino");
                exit;
            } else {
                $error = 'CIP no trobat al sistema. Verifica les teves dades.';
            }
        } catch (PDOException $e) {
            $error = 'Error de connexió amb la base de dades: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>Hospital Puig Castellar – Accés al Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --blue-main:#1a56c4; --blue-dark:#1344a3; --blue-hover:#1048b8; --blue-light:#e8f0fc;
            --bg:#eef2f7; --card-bg:#ffffff; --input-bg:#f4f6f9; --input-border:#d0d7e3;
            --input-focus:#1a56c4; --text-main:#1a1f2e; --text-muted:#5a6478;
            --error-bg:#fde8e8; --error-border:#e53e3e; --error-text:#c53030;
            --radius-card:14px; --radius-input:8px;
            --shadow-card:0 4px 24px rgba(26,86,196,0.10),0 1px 4px rgba(0,0,0,0.06);
        }
        body { font-family:'Source Sans 3',sans-serif; background:var(--bg); min-height:100vh; display:flex; flex-direction:column; align-items:center; }
        header { width:100%; background:#fff; border-bottom:1px solid #dde3ef; padding:0 32px; height:120px; display:flex; align-items:center; justify-content:center; }
        .logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
        main { flex:1; display:flex; align-items:center; justify-content:center; padding:48px 16px; width:100%; }
        .card { background:var(--card-bg); border-radius:var(--radius-card); box-shadow:var(--shadow-card); padding:40px 36px 36px; width:100%; max-width:440px; animation:fadeUp 0.4s ease both; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
        .card h1 { font-size:1.45rem; font-weight:700; color:var(--text-main); margin-bottom:28px; letter-spacing:-0.2px; }
        .error-box { background:var(--error-bg); border:1px solid var(--error-border); border-radius:var(--radius-input); color:var(--error-text); font-size:0.88rem; font-weight:500; padding:12px 14px; margin-bottom:20px; display:flex; align-items:flex-start; gap:8px; }
        .field { margin-bottom:20px; }
        label { display:block; font-size:0.92rem; font-weight:600; color:var(--text-main); margin-bottom:8px; }
        input[type="text"] { width:100%; background:var(--input-bg); border:1.5px solid var(--input-border); border-radius:var(--radius-input); padding:13px 14px; font-family:inherit; font-size:0.97rem; color:var(--text-main); outline:none; transition:border-color 0.2s,box-shadow 0.2s,background 0.2s; }
        input[type="text"]::placeholder { color:#9aa3b5; }
        input[type="text"]:focus { border-color:var(--input-focus); background:#fff; box-shadow:0 0 0 3px rgba(26,86,196,0.12); }
        .btn-primary { width:100%; background:var(--blue-main); color:#fff; border:none; border-radius:var(--radius-input); padding:14px; font-family:inherit; font-size:1rem; font-weight:700; cursor:pointer; transition:background 0.2s,transform 0.1s,box-shadow 0.2s; box-shadow:0 2px 8px rgba(26,86,196,0.25); margin-top:8px; }
        .btn-primary:hover { background:var(--blue-hover); box-shadow:0 4px 14px rgba(26,86,196,0.35); }
        .btn-primary:active { transform:scale(0.985); }
        footer { width:100%; text-align:center; padding:20px; font-size:0.8rem; color:var(--text-muted); }
    </style>
</head>
<body>
<header>
    <a class="logo" href="#">
        <img src="logo.png" alt="Hospital Puig Castellar" style="height:90px;width:auto;">
    </a>
</header>
<main>
    <div class="card">
        <h1>Inicie sesión</h1>
        <?php if ($error): ?>
        <div class="error-box">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="7" stroke="#e53e3e" stroke-width="1.5"/>
                <path d="M8 5v3.5M8 10.5v.5" stroke="#e53e3e" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        <form method="POST" action="index.php" novalidate>
            <div class="field">
                <label for="cip">CIP</label>
                <input type="text" id="cip" name="cip" placeholder="Ej: 12345678A"
                    value="<?= htmlspecialchars($_POST['cip'] ?? '') ?>"
                    autocomplete="off" autofocus maxlength="20">
            </div>
            <button type="submit" class="btn-primary">Ingresar</button>
        </form>
    </div>
</main>
<footer>&copy;Emma Ch. <?= date('Y') ?> Hospital Puig Castellar – Portal de Gestión Interna</footer>
</body>
</html>
