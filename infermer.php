<?php
session_start();
if (!isset($_SESSION['dni']) || $_SESSION['rol'] !== 'infermer') {
    header('Location: index.php');
    exit;
}
$cip = $_SESSION['dni'];

$host = 'localhost'; $port = '5432'; $dbname = 'hp';
$user = 'postgres';  $pass = 'http';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener datos del enfermero/a asignado
$stmt = $pdo->prepare("
    SELECT p.nombre, p.apellido, e.planta, e.vacunas_poner, e.vacunas_puestas, e.idhospital, h.nombre AS hospital_nom
    FROM enfermera e JOIN persona p ON p.cip = e.cip JOIN hospital h ON h.idhospital = e.idhospital
    WHERE e.cip = ?
");
$stmt->execute([$cip]);
$infermer = $stmt->fetch(PDO::FETCH_ASSOC);

$msg = ''; $msg_type = ''; $map_data = null; $stock_alert = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'dosi1') {
        $p_cip_persona = trim($_POST['d_cip_persona']);
        $p_idvacuna    = trim($_POST['d_idvacuna']);
        
        try {
            $stmt = $pdo->prepare("SELECT public.pondosis1(?, ?, ?)");
            $stmt->execute([$p_cip_persona, $p_idvacuna, $cip]);
            $res = $stmt->fetchColumn();
            $msg = $res;
            $msg_type = 'ok';
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                $errorInfo = $e->errorInfo;
                $detallePostgres = isset($errorInfo[2]) ? $errorInfo[2] : $e->getMessage();
                $msg = "<strong>Error de duplicado en la Base de Datos:</strong> " . htmlspecialchars($detallePostgres);
            } else {
                $msg = "Error en la base de datos: " . $e->getMessage();
            }
            $msg_type = 'err';
        }

        // Validación independiente del stock crítico
        $stmt2 = $pdo->prepare("SELECT s.cantidad, v.nombre as nom_vacuna FROM stock s JOIN almacen a ON a.idalmacen = s.idalmacen JOIN vacunas v ON v.idvacuna = s.idvacuna WHERE a.idhospital = ? AND s.idvacuna = ?");
        $stmt2->execute([$infermer['idhospital'], $p_idvacuna]);
        $stock_row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($stock_row && (int)$stock_row['cantidad'] < 5) {
            $stock_alert = "¡AVISO DE ALMACÉN! La vacuna <strong>{$stock_row['nom_vacuna']}</strong> cuenta con existencias críticas de <strong>{$stock_row['cantidad']}</strong> unidades.";
        }

    } elseif ($_POST['action'] === 'stock') {
        $p_idstock  = trim($_POST['s_idstock']);
        $p_cantidad = (int)$_POST['s_cantidad'];
        $stmt = $pdo->prepare("SELECT sumar_stock(?, ?)");
        $stmt->execute([$p_idstock, $p_cantidad]);
        $res = $stmt->fetchColumn();
        $msg = $res;
        $msg_type = str_contains($res, 'correctament') || str_contains($res, 'correctamente') ? 'ok' : 'err';
        
        $stmt2 = $pdo->prepare("SELECT s.cantidad, v.nombre as nom_vacuna FROM stock s JOIN vacunas v ON v.idvacuna = s.idvacuna WHERE s.idstock = ?");
        $stmt2->execute([$p_idstock]);
        $stock_row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($stock_row && (int)$stock_row['cantidad'] < 5) {
            $stock_alert = "¡AVISO DE ALMACÉN! La vacuna <strong>{$stock_row['nom_vacuna']}</strong> continúa con existencias críticas de <strong>{$stock_row['cantidad']}</strong> unidades.";
        }

    } elseif ($_POST['action'] === 'proper') {
        $p_cip_pac = trim($_POST['hp_cip']);
        $stmt = $pdo->prepare("SELECT * FROM hospital_cercano(?)");
        $stmt->execute([$p_cip_pac]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $msg = "Hospital más cercano: <strong>{$res['hospital_nombre']}</strong> ({$res['ciudad_nombre']}) · UCI disponibles: {$res['camas_disponibles_uci']}";
            $msg_type = 'ok';
            $stmt3 = $pdo->prepare("SELECT c.latitud, c.longitud, c.nombre as ciutat FROM persona p JOIN ciudad c ON c.idciudad = p.idciudad WHERE p.cip = ?");
            $stmt3->execute([$p_cip_pac]);
            $pac_loc = $stmt3->fetch(PDO::FETCH_ASSOC);
            $stmt4 = $pdo->prepare("SELECT c.latitud, c.longitud FROM hospital h JOIN ciudad c ON c.idciudad = h.idciudad WHERE h.idhospital = ?");
            $stmt4->execute([$res['idhospital']]);
            $hosp_loc = $stmt4->fetch(PDO::FETCH_ASSOC);
            if ($pac_loc && $hosp_loc) {
                $map_data = ['pac_lat' => $pac_loc['latitud'], 'pac_lng' => $pac_loc['longitud'], 'pac_ciutat' => $pac_loc['ciutat'], 'hosp_lat' => $hosp_loc['latitud'], 'hosp_lng' => $hosp_loc['longitud'], 'hosp_nom' => $res['hospital_nombre'], 'proper_nom' => $res['hospital_nombre'], 'proper_ciutat' => $res['ciudad_nombre'], 'proper_uci' => $res['camas_disponibles_uci']];
            }
        } else {
            $msg = "No se ha localizado ningún centro hospitalario cercano con plazas de UCI libres.";
            $msg_type = 'err';
        }

    } elseif ($_POST['action'] === 'estres') {
        $p_cip_m = trim($_POST['e_cip']);
        $stmt = $pdo->prepare("SELECT estres_metge(?)");
        $stmt->execute([$p_cip_m]);
        $res = $stmt->fetchColumn();
        $res_clean = str_ireplace(['libre', 'lliure'], 'Disponible (Sin Estrés)', $res);
        $msg = "Estado del médico/a [$p_cip_m]: $res_clean";
        $msg_type = str_contains(strtolower($res), 'estresado') || str_contains(strtolower($res), 'estressat') ? 'warn' : 'ok';
    }

    // Recargar datos de enfermero tras acciones
    $stmt = $pdo->prepare("SELECT p.nombre, p.apellido, e.planta, e.vacunas_poner, e.vacunas_puestas, e.idhospital, h.nombre AS hospital_nom FROM enfermera e JOIN persona p ON p.cip = e.cip JOIN hospital h ON h.idhospital = e.idhospital WHERE e.cip = ?");
    $stmt->execute([$cip]);
    $infermer = $stmt->fetch(PDO::FETCH_ASSOC);
}

// TABLA 1: Pacientes con ingresos vigentes en planta
$pacients_hospitalitzats = $pdo->query("
    SELECT p.cip, p.nombre AS nom, p.apellido AS cognom, p.fecha_nacimiento,
           h.nombre AS hospital, pa.planta, pa.habitacion AS habitacio, pa.estado,
           pa.cip_medico, pm.nombre AS metge_nom, pm.apellido AS metge_cognom
    FROM paciente pa
    JOIN persona p ON p.cip = pa.cip
    JOIN hospital h ON h.idhospital = pa.idhospital
    LEFT JOIN persona pm ON pm.cip = pa.cip_medico
    ORDER BY p.apellido
")->fetchAll(PDO::FETCH_ASSOC);

// TABLA 2: MUESTRA SOLO LAS PERSONAS YA VACUNADAS (INNER JOIN con cartilla_vacunas)
$cartilla_vacunacio = $pdo->query("
    SELECT p.cip, p.nombre AS nom, p.apellido AS cognom,
           cv.idvacuna, cv.dosi_1, cv.dosi_2, cv.dosi_3
    FROM cartilla_vacunas cv
    JOIN persona p ON p.cip = cv.cip_persona
    ORDER BY p.apellido
")->fetchAll(PDO::FETCH_ASSOC);

$pacients_actuals = $pdo->query("SELECT pa.cip, p.nombre, p.apellido, pa.estado FROM paciente pa JOIN persona p ON p.cip = pa.cip ORDER BY p.apellido")->fetchAll(PDO::FETCH_ASSOC);

$vacunes = $pdo->prepare("SELECT s.idstock, s.idvacuna, v.nombre as nom_vacuna, s.cantidad FROM stock s JOIN almacen a ON a.idalmacen = s.idalmacen JOIN vacunas v ON v.idvacuna = s.idvacuna WHERE a.idhospital = ? ORDER BY v.nombre");
$vacunes->execute([$infermer['idhospital']]);
$vacunes = $vacunes->fetchAll(PDO::FETCH_ASSOC);

// SELECT 1 MODIFICADA: Muestra ÚNICAMENTE personas que NO tienen registro en cartilla_vacunas
$persones_no_vacunades = $pdo->query("
    SELECT p.cip, p.nombre, p.apellido 
    FROM persona p
    LEFT JOIN cartilla_vacunas cv ON p.cip = cv.cip_persona
    WHERE cv.cip_persona IS NULL
    ORDER BY p.apellido, p.nombre
")->fetchAll(PDO::FETCH_ASSOC);

//$metges_all = $pdo->query("SELECT m.cip, p.nombre, p.apellido, m.num_pacientes, m.estado FROM medico m JOIN persona p ON p.cip = m.cip ORDER BY p.apellido")->fetchAll(PDO::FETCH_ASSOC);
// Medicos ordenados: primero estresados y con mas pacientes
$metges_all = $pdo->query("
    SELECT m.cip, p.nombre, p.apellido, m.num_pacientes, m.estado
    FROM medico m JOIN persona p ON p.cip = m.cip
    ORDER BY
        CASE WHEN m.estado = 'estresado/a' THEN 0 ELSE 1 END ASC,
        m.num_pacientes DESC,
        p.apellido ASC
")->fetchAll(PDO::FETCH_ASSOC);

$GMAPS_KEY = 'AIzaSyC2Q1Obdba1Cor2RsZtOM77m77atRbxPAE';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>Portal de Enfermería – Gestión Hospitalaria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php if ($map_data): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= $GMAPS_KEY ?>"></script>
    <?php endif; ?>
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --blue:#1a56c4; --blue-dark:#1048b8; --blue-light:#e8f0fc;
            --bg:#eef2f7; --card:#ffffff; --border:#dde3ef;
            --text:#1a1f2e; --muted:#5a6478;
            --ok:#1a7a4a; --ok-bg:#e6f4ed;
            --err:#c53030; --err-bg:#fde8e8;
            --warn:#92601a; --warn-bg:#fef3e2;
            --radius:10px; --shadow:0 4px 24px rgba(26,86,196,0.09),0 1px 4px rgba(0,0,0,0.05);
        }
        body { font-family:'Source Sans 3',sans-serif; background:var(--bg); min-height:100vh; display:flex; flex-direction:column; }
        header { background:#fff; border-bottom:1px solid var(--border); padding:0 28px; height:72px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
        .logo img { height:48px; }
        .header-right { display:flex; align-items:center; gap:12px; }
        .badge { background:var(--blue-light); color:var(--blue); font-size:0.78rem; font-weight:700; padding:4px 12px; border-radius:20px; letter-spacing:0.5px; }
        .inf-info { font-size:0.88rem; color:var(--muted); font-weight:500; }
        .btn-logout { color:var(--muted); font-size:0.85rem; text-decoration:none; padding:6px 12px; border-radius:6px; border:1px solid var(--border); transition:background 0.2s; }
        .btn-logout:hover { background:var(--bg); }
        main { flex:1; max-width:1200px; width:100%; margin:0 auto; padding:28px 20px; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:28px; }
        .stat-card { background:var(--card); border-radius:var(--radius); border:1px solid var(--border); padding:18px 20px; }
        .stat-card .label { font-size:0.78rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
        .stat-card .value { font-size:1.4rem; font-weight:700; color:var(--text); line-height:1.2; }
        .stat-card .value.ok { color:#1a7a4a; }
        .msg { padding:13px 16px; border-radius:var(--radius); font-size:0.93rem; font-weight:500; margin-bottom:16px; display:flex; align-items:flex-start; gap:10px; }
        .msg.ok   { background:var(--ok-bg);   color:var(--ok);   border:1px solid #b2dfcc; }
        .msg.err  { background:var(--err-bg);  color:var(--err);  border:1px solid #f5c6c6; }
        .msg.warn { background:var(--warn-bg); color:var(--warn); border:1px solid #f5dfa8; }
        .msg.alert-stock { background:#fff8e1; color:#7a5c00; border:2px dashed #d4a313; margin-bottom:24px; }
        .map-section { background:var(--card); border-radius:var(--radius); border:1px solid var(--border); overflow:hidden; margin-bottom:28px; box-shadow:var(--shadow); }
        .map-section .map-header { padding:14px 20px; border-bottom:1px solid var(--border); font-weight:700; color:var(--text); font-size:0.95rem; }
        #map { height:320px; width:100%; }
        .map-legend { padding:10px 20px; font-size:0.82rem; color:var(--muted); display:flex; gap:20px; flex-wrap:wrap; border-top:1px solid var(--border); }
        .dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
        .dot.blue { background:#1a56c4; } .dot.red { background:#e53e3e; }
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        @media(max-width:768px){.grid-2{grid-template-columns:1fr;}}
        .section-card { background:var(--card); border-radius:var(--radius); border:1px solid var(--border); box-shadow:var(--shadow); overflow:hidden; }
        .sc-header { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
        .sc-header h2 { font-size:0.95rem; font-weight:700; color:var(--text); }
        .sc-body { padding:20px; }
        .field { margin-bottom:14px; }
        .field label { display:block; font-size:0.83rem; font-weight:600; color:var(--muted); margin-bottom:5px; text-transform:uppercase; letter-spacing:0.4px; }
        select, input[type="text"], input[type="number"] { width:100%; background:#f4f6f9; border:1.5px solid var(--border); border-radius:7px; padding:10px 12px; font-family:inherit; font-size:0.92rem; color:var(--text); outline:none; transition:border-color 0.2s,box-shadow 0.2s; }
        select:focus, input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(26,86,196,0.1); background:#fff; }
        .btn { width:100%; padding:11px; border:none; border-radius:7px; font-family:inherit; font-size:0.93rem; font-weight:700; cursor:pointer; transition:background 0.2s,transform 0.1s; margin-top:4px; }
        .btn-blue  { background:var(--blue);  color:#fff; } .btn-blue:hover  { background:var(--blue-dark); }
        .btn-green { background:#1a7a4a;       color:#fff; } .btn-green:hover { background:#155e39; }
        .btn-warn  { background:#c47a1a;       color:#fff; } .btn-warn:hover  { background:#a86215; }
        .btn:active { transform:scale(0.98); }
        .table-section { background:var(--card); border-radius:var(--radius); border:1px solid var(--border); box-shadow:var(--shadow); margin-bottom:20px; overflow:hidden; }
        .ts-header { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
        .ts-header h2 { font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
        .count-badge { background:var(--blue-light); color:var(--blue); font-size:0.78rem; font-weight:700; padding:2px 8px; border-radius:10px; }
        .tbl { width:100%; border-collapse:collapse; font-size:0.87rem; }
        .tbl th { padding:10px 14px; text-align:left; font-size:0.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid var(--border); background:#f8f9fc; }
        .tbl td { padding:10px 14px; border-bottom:1px solid #f0f2f7; color:var(--text); vertical-align:middle; }
        .tbl tr:last-child td { border-bottom:none; }
        .tbl tr:hover td { background:#f8f9fc; }
        .pill { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:700; }
        .pill-ok   { background:#e6f4ed; color:#1a7a4a; }
        .pill-pend { background:#e8f0fc; color:#1a56c4; }
        .pill-none { background:#f0f2f7; color:#5a6478; }
        .stock-low { color:var(--err); font-weight:700; }
        .no-data { text-align:center; padding:32px; color:var(--muted); font-size:0.9rem; }
        footer { text-align:center; padding:20px; font-size:0.8rem; color:var(--muted); border-top:1px solid var(--border); margin-top:8px; }
    </style>
</head>
<body>

<header>
    <a class="logo" href="#"><img src="logo.png" alt="Hospital Puig Castellar"></a>
    <div class="header-right">
        <span class="inf-info">Enf. <?= htmlspecialchars($infermer['nombre'] . ' ' . $infermer['apellido']) ?> · Planta <?= $infermer['planta'] ?></span>
        <span class="badge">PERSONAL DE ENFERMERÍA</span>
        <a class="btn-logout" href="logout.php">Cerrar Sesión</a>
    </div>
</header>

<main>

    <div class="stats">
        <div class="stat-card">
            <div class="label">Hospital Asignado</div>
            <div class="value" style="font-size:1rem;margin-top:4px;"><?= htmlspecialchars($infermer['hospital_nom']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Planta Asignada</div>
            <div class="value ok">Planta <?= $infermer['planta'] ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Vacunas Administradas</div>
            <div class="value ok"><?= $infermer['vacunas_puestas'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="label">CIP Identificador</div>
            <div class="value" style="font-size:1rem;margin-top:4px;font-family:monospace;"><?= htmlspecialchars($cip) ?></div>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="msg <?= $msg_type ?>">
        <?php if ($msg_type === 'ok'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?php elseif ($msg_type === 'warn'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?php else: ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php endif; ?>
        <span><?= $msg ?></span>
    </div>
    <?php endif; ?>

    <?php if ($stock_alert): ?>
    <div class="msg alert-stock">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span><?= $stock_alert ?></span>
    </div>
    <?php endif; ?>

    <?php if ($map_data): ?>
    <div class="map-section">
        <div class="map-header">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Hospital más cercano al paciente
        </div>
        <div id="map"></div>
        <div class="map-legend">
            <span><span class="dot blue"></span> Paciente (<?= htmlspecialchars($map_data['pac_ciutat']) ?>)</span>
            <span><span class="dot red"></span> Hospital: <?= htmlspecialchars($map_data['hosp_nom']) ?></span>
            <?php if (!empty($map_data['proper_uci'])): ?>
            <span>UCI disponibles: <strong><?= $map_data['proper_uci'] ?></strong></span>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function initMap() {
        const pacPos  = { lat: <?= (float)$map_data['pac_lat'] ?>, lng: <?= (float)$map_data['pac_lng'] ?> };
        const hospPos = { lat: <?= (float)$map_data['hosp_lat'] ?>, lng: <?= (float)$map_data['hosp_lng'] ?> };
        const center  = { lat: (pacPos.lat + hospPos.lat) / 2, lng: (pacPos.lng + hospPos.lng) / 2 };
        const map = new google.maps.Map(document.getElementById('map'), { zoom: 10, center: center, styles: [{featureType:'poi',stylers:[{visibility:'off'}]}] });
        new google.maps.Marker({ position: pacPos, map, title: 'Paciente', icon: { path: google.maps.SymbolPath.CIRCLE, scale: 9, fillColor: '#1a56c4', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 } });
        new google.maps.Marker({ position: hospPos, map, title: '<?= addslashes($map_data['hosp_nom']) ?>', icon: { path: google.maps.SymbolPath.BACKWARD_CLOSED_ARROW, scale: 7, fillColor: '#e53e3e', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 } });
        new google.maps.Polyline({ path: [pacPos, hospPos], map, strokeColor: '#1a56c4', strokeOpacity: 0.6, strokeWeight: 2, geodesic: true });
    }
    window.onload = initMap;
    </script>
    <?php endif; ?>

    <div class="grid-2">

        <div class="section-card">
            <div class="sc-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a56c4" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                <h2>Administrar Primera Dosis</h2>
            </div>
            <div class="sc-body">
                <form method="POST">
                    <input type="hidden" name="action" value="dosi1">
                    <div class="field">
                        <label>Persona (Sin Vacunas Registradas)</label>
                        <select name="d_cip_persona" required>
                            <option value="">— Selecciona ciudadano no vacunado —</option>
                            <?php foreach ($persones_no_vacunades as $p): ?>
                            <option value="<?= $p['cip'] ?>"><?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre']) ?> (<?= $p['cip'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Vacuna Disponible</label>
                        <select name="d_idvacuna" required>
                            <option value="">— Selecciona vacuna —</option>
                            <?php foreach ($vacunes as $v): ?>
                            <option value="<?= $v['idvacuna'] ?>"><?= htmlspecialchars($v['nom_vacuna']) ?> (<?= $v['idvacuna'] ?>) · Inventario: <?= $v['cantidad'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size:0.78rem;color:var(--muted);margin-top:4px;">Lotes disponibles en el almacén de tu hospital corporativo</div>
                    </div>
                    <button class="btn btn-blue" type="submit">Registrar Dosis 1</button>
                </form>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;">

            <div class="section-card">
                <div class="sc-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a7a4a" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    <h2>Añadir Existencias al Stock</h2>
                </div>
                <div class="sc-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="stock">
                        <div class="field">
                            <label>Registro de Stock</label>
                            <select name="s_idstock" required>
                                <option value="">— Selecciona stock —</option>
                                <?php foreach ($vacunes as $v): ?>
                                <option value="<?= $v['idstock'] ?>"><?= htmlspecialchars($v['nom_vacuna']) ?> · Actual: <?= $v['cantidad'] ?> u.<?= $v['cantidad'] < 5 ? ' !' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Unidades a Añadir</label>
                            <input type="number" name="s_cantidad" min="1" max="1000" value="10" required>
                        </div>
                        <button class="btn btn-green" type="submit">Aumentar Stock</button>
                    </form>
                </div>
            </div>

            <div class="section-card">
                <div class="sc-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a56c4" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <h2>Ubicación de Hospital Más Cercano</h2>
                </div>
                <div class="sc-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="proper">
                        <div class="field">
                            <label>Paciente Ingresado</label>
                            <select name="hp_cip" required>
                                <option value="">— Selecciona paciente —</option>
                                <?php foreach ($pacients_actuals as $p): ?>
                                <option value="<?= $p['cip'] ?>"><?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre']) ?> · <?= $p['estado'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-blue" type="submit">Buscar Hospital Cercano</button>
                    </form>
                </div>
            </div>

            <div class="section-card">
                <div class="sc-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c47a1a" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <h2>Consultar Estrés Médico</h2>
                </div>
                <div class="sc-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="estres">
                        <div class="field">
                            <label>Médico</label>
                            <select name="e_cip" required>
                                <option value="">— Selecciona médico —</option>
                                <?php foreach ($metges_all as $m): ?>
                                <option value="<?= $m['cip'] ?>"><?= htmlspecialchars($m['apellido'] . ', ' . $m['nombre']) ?> (<?= $m['num_pacientes'] ?>/5 asignados) </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-warn" type="submit">Consultar Estado de Carga</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <div class="table-section">
        <div class="ts-header">
            <h2>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Inventario General de Vacunas del Hospital
                <span class="count-badge"><?= count($vacunes) ?></span>
            </h2>
        </div>
        <?php if (empty($vacunes)): ?>
        <div class="no-data">No se registran lotes de inventario asignados a este centro.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="tbl">
            <thead>
                <tr><th>ID Stock</th><th>Vacuna</th><th>ID Vacuna</th><th>Cantidad</th><th>Estado del Almacén</th></tr>
            </thead>
            <tbody>
            <?php foreach ($vacunes as $v): ?>
            <tr>
                <td><code style="font-size:0.82rem;"><?= $v['idstock'] ?></code></td>
                <td><?= htmlspecialchars($v['nom_vacuna']) ?></td>
                <td><?= $v['idvacuna'] ?></td>
                <td class="<?= $v['cantidad'] < 5 ? 'stock-low' : '' ?>"><?= $v['cantidad'] ?></td>
                <td>
                    <?php if ($v['cantidad'] === 0): ?>
                        <span class="pill pill-none">Agotado</span>
                    <?php elseif ($v['cantidad'] < 5): ?>
                        <span class="pill" style="background:#fde8e8;color:#c53030;">Stock Crítico</span>
                    <?php else: ?>
                        <span class="pill pill-ok">Disponible</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="table-section">
        <div class="ts-header">
            <h2>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Pacientes Hospitalizados Actuales
                <span class="count-badge"><?= count($pacients_hospitalitzats) ?></span>
            </h2>
        </div>
        <?php if (empty($pacients_hospitalitzats)): ?>
        <div class="no-data">No hay ningún paciente ingresado actualmente en las dependencias.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="tbl">
            <thead>
                <tr>
                    <th>CIP</th><th>Nombre</th><th>Apellidos</th><th>Fecha Nac.</th><th>Centro Hospitalario</th>
                    <th>Planta</th><th>Habitación</th><th>Estado Clínico</th><th>Médico Responsable</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pacients_hospitalitzats as $p): ?>
            <tr>
                <td><code style="font-size:0.82rem;"><?= htmlspecialchars($p['cip']) ?></code></td>
                <td><?= htmlspecialchars($p['nom']) ?></td>
                <td><?= htmlspecialchars($p['cognom']) ?></td>
                <td><?= $p['fecha_nacimiento'] ?? '—' ?></td>
                <td><?= htmlspecialchars($p['hospital']) ?></td>
                <td>Planta <?= $p['planta'] ?></td>
                <td>Hab. <?= $p['habitacio'] ?></td>
                <td><span class="pill pill-pend"><?= htmlspecialchars($p['estado']) ?></span></td>
                <td>
                    <?php if ($p['cip_medico']): ?>
                        <span style="font-size:0.82rem;">
                            <?= htmlspecialchars($p['metge_nom'] . ' ' . $p['metge_cognom']) ?>
                            <br><code style="font-size:0.75rem;color:#5a6478;"><?= $p['cip_medico'] ?></code>
                        </span>
                    <?php else: ?>
                        <span class="pill pill-none">No asignado</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="table-section">
        <div class="ts-header">
            <h2>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Historial de la Cartilla de Vacunación (Solo Ciudadanos Inmunizados)
                <span class="count-badge"><?= count($cartilla_vacunacio) ?></span>
            </h2>
        </div>
        <?php if (empty($cartilla_vacunacio)): ?>
        <div class="no-data">No constan registros de inmunización ni dosis administradas en el sistema.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="tbl">
            <thead>
                <tr>
                    <th>CIP del Ciudadano</th><th>Nombre</th><th>Apellidos</th><th>Vacuna Asignada</th>
                    <th>Primera Dosis</th><th>Segunda Dosis</th><th>Tercera Dosis</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cartilla_vacunacio as $c): ?>
            <tr>
                <td><code style="font-size:0.82rem;"><?= htmlspecialchars($c['cip']) ?></code></td>
                <td><?= htmlspecialchars($c['nom']) ?></td>
                <td><?= htmlspecialchars($c['cognom']) ?></td>
                <td><?= $c['idvacuna'] ? '<span class="pill pill-pend">'.$c['idvacuna'].'</span>' : '<span class="pill pill-none">—</span>' ?></td>
                <td><?= $c['dosi_1'] ? '<span class="pill pill-ok">'.$c['dosi_1'].'</span>' : '<span class="pill pill-none">Pendiente</span>' ?></td>
                <td><?= $c['dosi_2'] ? '<span class="pill pill-ok">'.$c['dosi_2'].'</span>' : '<span class="pill pill-none">—</span>' ?></td>
                <td><?= $c['dosi_3'] ? '<span class="pill pill-ok">'.$c['dosi_3'].'</span>' : '<span class="pill pill-none">—</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

</main>
<footer>&copy;Emma Ch. <?= date('Y') ?> Hospital Puig Castellar – Portal de Gestión Interna</footer>
</body>
</html>
