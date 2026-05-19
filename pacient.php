/*
----------------------------------------------------------------
----------------------------------------------------------------
			pacient.php
----------------------------------------------------------------
----------------------------------------------------------------
*/
<?php
session_start();
if (!isset($_SESSION['dni']) || $_SESSION['rol'] !== 'pacient') {
    header('Location: index.php');
    exit;
}
$dni = $_SESSION['dni'];
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vall d'Hebron – Pacient</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --blue-main: #1a56c4; --bg: #eef2f7; --card-bg: #fff;
            --text-main: #1a1f2e; --text-muted: #5a6478;
            --shadow-card: 0 4px 24px rgba(26,86,196,0.10), 0 1px 4px rgba(0,0,0,0.06);
        }
        body { font-family: 'Source Sans 3', sans-serif; background: var(--bg); min-height: 100vh; display: flex; flex-direction: column; }
        header { width: 100%; background: #fff; border-bottom: 1px solid #dde3ef; padding: 0 32px; height: 72px; display: flex; align-items: center; justify-content: space-between; }
        .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo-text span { display: block; font-size: 1.35rem; font-weight: 700; color: var(--blue-main); line-height: 1.1; }
        .badge { background: #e8f0fc; color: var(--blue-main); font-size: 0.82rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; letter-spacing: 0.5px; }
        .logout { color: var(--text-muted); font-size: 0.88rem; text-decoration: none; padding: 6px 12px; border-radius: 6px; border: 1px solid #dde3ef; transition: background 0.2s; }
        .logout:hover { background: #f4f6f9; }
        main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 48px 16px; }
        .card { background: var(--card-bg); border-radius: 14px; box-shadow: var(--shadow-card); padding: 48px 36px; width: 100%; max-width: 560px; text-align: center; }
        .placeholder-icon { font-size: 3rem; margin-bottom: 16px; }
        h1 { font-size: 1.4rem; font-weight: 700; color: var(--text-main); margin-bottom: 10px; }
        p { color: var(--text-muted); font-size: 0.95rem; }
        footer { text-align: center; padding: 20px; font-size: 0.8rem; color: var(--text-muted); }
    </style>
</head>
<body>
<header>
    <a class="logo" href="#">
        <img src="logo.png" alt="Hospital Puig Castellar" style="height:48px; width:auto;">
    </a>
    <div style="display:flex;align-items:center;gap:12px;">
        <span class="badge">PACIENT</span>
        <a class="logout" href="logout.php">Tancar sessió</a>
    </div>
</header>
<main>
    <div class="card">
        <div class="placeholder-icon">🏥</div>
        <h1>Portal del Pacient</h1>
        <p>Pàgina en construcció. Aquí aniria la informació del pacient amb DNI: <strong><?= htmlspecialchars($dni) ?></strong></p>
    </div>
</main>
<footer>&copy; <?= date('Y') ?> Hospital Puig Castellar – Portal de Gestió Interna</footer>
</body>
</html>
