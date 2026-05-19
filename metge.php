<?php
session_start();
if (!isset($_SESSION['dni']) || $_SESSION['rol'] !== 'metge') {
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
    die("Error de conexion: " . $e->getMessage());
}

$stmt = $pdo->prepare("
    SELECT p.nombre, p.apellido, m.especialidad, m.num_pacientes, m.estado, m.idhospital, h.nombre AS hospital_nom
    FROM medico m JOIN persona p ON p.cip = m.cip JOIN hospital h ON h.idhospital = m.idhospital
    WHERE m.cip = ?
");
$stmt->execute([$cip]);
$metge = $stmt->fetch(PDO::FETCH_ASSOC);

$msg = ''; $msg_type = ''; $map_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'ingresa') {
        $p_cip = trim($_POST['p_cip']); $p_hospital = trim($_POST['p_hospital']);
        $p_estado = trim($_POST['p_estado']); $p_planta = (int)$_POST['p_planta']; $p_hab = (int)$_POST['p_habitacion'];
        $stmt = $pdo->prepare("SELECT ingresa_pacient(?, ?, ?, ?, ?)");
        $stmt->execute([$p_cip, $p_hospital, $p_estado, $p_planta, $p_hab]);
        $res = $stmt->fetchColumn();
        $msg = $res;
        $msg_type = str_contains($res, 'correctament') ? 'ok' : 'err';
        if ($msg_type === 'ok') {
            try {
                $stmt2 = $pdo->prepare("SELECT * FROM hospital_cercano(?)");
                $stmt2->execute([$p_cip]);
                $hosp = $stmt2->fetch(PDO::FETCH_ASSOC);
                $stmt3 = $pdo->prepare("SELECT c.latitud, c.longitud, c.nombre as ciutat FROM persona p JOIN ciudad c ON c.idciudad = p.idciudad WHERE p.cip = ?");
                $stmt3->execute([$p_cip]);
                $pac_loc = $stmt3->fetch(PDO::FETCH_ASSOC);
                $stmt4 = $pdo->prepare("SELECT c.latitud, c.longitud FROM hospital h JOIN ciudad c ON c.idciudad = h.idciudad WHERE h.idhospital = ?");
                $stmt4->execute([$p_hospital]);
                $hosp_loc = $stmt4->fetch(PDO::FETCH_ASSOC);
                if ($pac_loc && $hosp_loc) {
                    $map_data = ['pac_lat' => $pac_loc['latitud'], 'pac_lng' => $pac_loc['longitud'], 'pac_ciutat' => $pac_loc['ciutat'], 'hosp_lat' => $hosp_loc['latitud'], 'hosp_lng' => $hosp_loc['longitud'], 'hosp_nom' => $metge['hospital_nom'], 'hosp_id' => $p_hospital];
                    if ($hosp) { $map_data['proper_nom'] = $hosp['hospital_nombre']; $map_data['proper_ciutat'] = $hosp['ciudad_nombre']; $map_data['proper_uci'] = $hosp['camas_disponibles_uci']; }
                }
            } catch (Exception $e) {}
        }

    } elseif ($_POST['action'] === 'revisa') {
        $stmt = $pdo->prepare("SELECT revisa_paciente(?, ?)");
        $stmt->execute([trim($_POST['r_cip']), trim($_POST['r_estat'])]);
        $res = $stmt->fetchColumn();
        $msg = $res;
        $msg_type = (str_contains($res, 'Error') || str_contains($res, 'no existe')) ? 'err' : 'ok';

    } elseif ($_POST['action'] === 'estres') {
        $p_cip_m = trim($_POST['e_cip']);
        $stmt = $pdo->prepare("SELECT estres_metge(?)");
        $stmt->execute([$p_cip_m]);
        $res = $stmt->fetchColumn();
        $msg = "Estado del medico $p_cip_m: $res";
        $msg_type = str_contains($res, 'estresado') ? 'warn' : 'ok';

    } elseif ($_POST['action'] === 'proper') {
        $p_cip_pac = trim($_POST['hp_cip']);
        $stmt = $pdo->prepare("SELECT * FROM hospital_cercano(?)");
        $stmt->execute([$p_cip_pac]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $msg = "Hospital mas cercano: <strong>{$res['hospital_nombre']}</strong> ({$res['ciudad_nombre']}) · UCI disponibles: {$res['camas_disponibles_uci']}";
            $msg_type = 'ok';
            $stmt3 = $pdo->prepare("SELECT c.latitud, c.longitud, c.nombre as ciutat FROM persona p JOIN ciudad c ON c.idciudad = p.idciudad WHERE p.cip = ?");
            $stmt3->execute([$p_cip_pac]);
            $pac_loc = $stmt3->fetch(PDO::FETCH_ASSOC);
            $stmt4 = $pdo->prepare("SELECT c.latitud, c.longitud FROM hospital h JOIN ciudad c ON c.idciudad = h.idciudad WHERE h.idhospital = ?");
            $stmt4->execute([$res['idhospital']]);
            $hosp_loc = $stmt4->fetch(PDO::FETCH_ASSOC);
            if ($pac_loc && $hosp_loc) {
                $map_data = ['pac_lat' => $pac_loc['latitud'], 'pac_lng' => $pac_loc['longitud'], 'pac_ciutat' => $pac_loc['ciutat'], 'hosp_lat' => $hosp_loc['latitud'], 'hosp_lng' => $hosp_loc['longitud'], 'hosp_nom' => $res['hospital_nombre'], 'hosp_id' => $res['idhospital'], 'proper_nom' => $res['hospital_nombre'], 'proper_ciutat' => $res['ciudad_nombre'], 'proper_uci' => $res['camas_disponibles_uci']];
            }
        } else {
            $msg = "No se ha encontrado ningun hospital cercano con UCI disponible.";
            $msg_type = 'err';
        }
    }

    $stmt = $pdo->prepare("SELECT p.nombre, p.apellido, m.especialidad, m.num_pacientes, m.estado, m.idhospital, h.nombre AS hospital_nom FROM medico m JOIN persona p ON p.cip = m.cip JOIN hospital h ON h.idhospital = m.idhospital WHERE m.cip = ?");
    $stmt->execute([$cip]);
    $metge = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pacients = $pdo->query("
    SELECT p.cip, p.nombre AS nom, p.apellido AS cognom, p.fecha_nacimiento,
        h.nombre AS hospital, pa.planta, pa.habitacion AS habitacio,
        cv.idvacuna, cv.dosi_1, cv.dosi_2, cv.dosi_3,
        v.cip_enfermera, pe.nombre AS inf_nom, pe.apellido AS inf_cognom
    FROM paciente pa
    JOIN persona p ON p.cip = pa.cip
    JOIN hospital h ON h.idhospital = pa.idhospital
    LEFT JOIN cartilla_vacunas cv ON cv.cip_persona = pa.cip
    LEFT JOIN vacunas v ON v.idvacuna = cv.idvacuna
    LEFT JOIN persona pe ON pe.cip = v.cip_enfermera
    ORDER BY p.apellido
")->fetchAll(PDO::FETCH_ASSOC);

$hospitals        = $pdo->query("SELECT idhospital, nombre FROM hospital ORDER BY idhospital")->fetchAll(PDO::FETCH_ASSOC);
$persones         = $pdo->query("SELECT p.cip, p.nombre, p.apellido FROM persona p WHERE NOT EXISTS (SELECT 1 FROM paciente pa WHERE pa.cip = p.cip) ORDER BY p.apellido, p.nombre")->fetchAll(PDO::FETCH_ASSOC);
$pacients_actuals = $pdo->query("SELECT pa.cip, p.nombre, p.apellido, pa.estado FROM paciente pa JOIN persona p ON p.cip = pa.cip ORDER BY p.apellido")->fetchAll(PDO::FETCH_ASSOC);

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

// No disponible si estresado O si tiene 5 pacientes
$noDisponible = ($metge['estado'] === 'estresado/a' || (int)$metge['num_pacientes'] >= 5);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>Portal Medico – Hospital Puig Castellar</title>
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
        .metge-info { font-size:0.88rem; color:var(--muted); font-weight:500; }
        .btn-logout { color:var(--muted); font-size:0.85rem; text-decoration:none; padding:6px 12px; border-radius:6px; border:1px solid var(--border); transition:background 0.2s; }
        .btn-logout:hover { background:var(--bg); }
        main { flex:1; max-width:1200px; width:100%; margin:0 auto; padding:28px 20px; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:28px; }
        .stat-card { background:var(--card); border-radius:var(--radius); border:1px solid var(--border); padding:18px 20px; }
        .stat-card .label { font-size:0.78rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
        .stat-card .value { font-size:1.6rem; font-weight:700; color:var(--text); line-height:1; }
        .stat-card .value.stress { color:#c53030; }
        .stat-card .value.ok { color:#1a7a4a; }
        .estat-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px; font-size:0.85rem; font-weight:700; }
        .estat-badge.lliure { background:#e6f4ed; color:#1a7a4a; }
        .estat-badge.estres { background:#fde8e8; color:#c53030; }
        .msg { padding:13px 16px; border-radius:var(--radius); font-size:0.93rem; font-weight:500; margin-bottom:24px; display:flex; align-items:flex-start; gap:10px; }
        .msg.ok   { background:var(--ok-bg);   color:var(--ok);   border:1px solid #b2dfcc; }
        .msg.err  { background:var(--err-bg);  color:var(--err);  border:1px solid #f5c6c6; }
        .msg.warn { background:var(--warn-bg); color:var(--warn); border:1px solid #f5dfa8; }
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
        .no-data { text-align:center; padding:32px; color:var(--muted); font-size:0.9rem; }
        footer { text-align:center; padding:20px; font-size:0.8rem; color:var(--muted); border-top:1px solid var(--border); margin-top:8px; }
    </style>
</head>
<body>

<header>
    <a class="logo" href="#"><img src="logo.png" alt="Hospital Puig Castellar"></a>
    <div class="header-right">
        <span class="metge-info">Dr/a. <?= htmlspecialchars($metge['nombre'] . ' ' . $metge['apellido']) ?> · <?= htmlspecialchars($metge['especialidad']) ?></span>
        <span class="badge">MEDICO</span>
        <a class="btn-logout" href="logout.php">Cerrar sesion</a>
    </div>
</header>

<main>

    <div class="stats">
        <div class="stat-card">
            <div class="label">Hospital asignado</div>
            <div class="value" style="font-size:1rem;margin-top:4px;"><?= htmlspecialchars($metge['hospital_nom']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Pacientes actuales</div>
            <div class="value <?= $noDisponible ? 'stress' : 'ok' ?>"><?= (int)$metge['num_pacientes'] ?> / 5</div>
        </div>
        <div class="stat-card">
            <div class="label">Estado</div>
            <div style="margin-top:4px;">
                <?php if ($noDisponible): ?>
                    <span class="estat-badge estres">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        No disponible
                    </span>
                <?php else: ?>
                    <span class="estat-badge lliure">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Disponible
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="label">CIP</div>
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

    <?php if ($map_data): ?>
    <div class="map-section">
        <div class="map-header">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Ubicacion del paciente y hospital asignado
        </div>
        <div id="map"></div>
        <div class="map-legend">
            <span><span class="dot blue"></span> Paciente (<?= htmlspecialchars($map_data['pac_ciutat']) ?>)</span>
            <span><span class="dot red"></span> Hospital: <?= htmlspecialchars($map_data['hosp_nom']) ?></span>
            <?php if (!empty($map_data['proper_nom'])): ?>
            <span>Hospital cercano con UCI: <strong><?= htmlspecialchars($map_data['proper_nom']) ?></strong> · <?= $map_data['proper_uci'] ?> camas libres</span>
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
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a56c4" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <h2>Ingresar Paciente</h2>
            </div>
            <div class="sc-body">
                <form method="POST">
                    <input type="hidden" name="action" value="ingresa">
                    <div class="field">
                        <label>Paciente (CIP)</label>
                        <select name="p_cip" required>
                            <option value="">— Selecciona persona —</option>
                            <?php foreach ($persones as $p): ?>
                            <option value="<?= $p['cip'] ?>"><?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre']) ?> (<?= $p['cip'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Hospital</label>
                        <select name="p_hospital" required>
                            <option value="">— Selecciona hospital —</option>
                            <?php foreach ($hospitals as $h): ?>
                            <option value="<?= $h['idhospital'] ?>" <?= $h['idhospital'] === $metge['idhospital'] ? 'selected' : '' ?>><?= htmlspecialchars($h['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Estado</label>
                        <select name="p_estado" required>
                            <option value="leve">Leve</option>
                            <option value="moderado">Moderado</option>
                            <option value="grave">Grave</option>
                            <option value="muy grave">Muy grave (UCI)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Planta</label>
                        <input type="number" name="p_planta" min="1" max="10" value="1" required>
                    </div>
                    <div class="field">
                        <label>Habitacion</label>
                        <input type="number" name="p_habitacion" min="1" max="999" value="101" required>
                    </div>
                    <button class="btn btn-blue" type="submit">Ingresar Paciente</button>
                </form>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;">

            <div class="section-card">
                <div class="sc-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a7a4a" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    <h2>Revisar Estado Paciente</h2>
                </div>
                <div class="sc-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="revisa">
                        <div class="field">
                            <label>Paciente ingresado</label>
                            <select name="r_cip" required>
                                <option value="">— Selecciona paciente —</option>
                                <?php foreach ($pacients_actuals as $p): ?>
                                <option value="<?= $p['cip'] ?>"><?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre']) ?> · <?= $p['estado'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Nuevo estado</label>
                            <select name="r_estat" required>
                                <option value="leve">Leve</option>
                                <option value="moderado">Moderado</option>
                                <option value="grave">Grave</option>
                                <option value="muy grave">Muy grave (UCI)</option>
                                <option value="fuera de peligro">Fuera de peligro (Alta)</option>
                            </select>
                        </div>
                        <button class="btn btn-green" type="submit">Actualizar Estado</button>
                    </form>
                </div>
            </div>

            <div class="section-card">
                <div class="sc-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c47a1a" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <h2>Consultar Estres Medico</h2>
                </div>
                <div class="sc-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="estres">
                        <div class="field">
                            <label>Medico/a</label>
                            <select name="e_cip" required>
                                <option value="">— Selecciona medico —</option>
                                <?php foreach ($metges_all as $m): ?>
                                <option value="<?= $m['cip'] ?>" <?= $m['cip'] === $cip ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['apellido'] . ', ' . $m['nombre']) ?>
                                    (<?= $m['num_pacientes'] ?>/5)<?= $m['estado'] === 'estresado/a' ? ' — ESTRESADO' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-warn" type="submit">Consultar Estres</button>
                    </form>
                </div>
            </div>

            <div class="section-card">
                <div class="sc-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a56c4" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <h2>Hospital mas Cercano</h2>
                </div>
                <div class="sc-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="proper">
                        <div class="field">
                            <label>Paciente (CIP)</label>
                            <select name="hp_cip" required>
                                <option value="">— Selecciona paciente —</option>
                                <?php foreach ($pacients_actuals as $p): ?>
				<option value="<?= $p['cip'] ?>"><?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre'] . ' (' . $p['cip'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-blue" type="submit">Buscar Hospital Cercano</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <div class="table-section">
        <div class="ts-header">
            <h2>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Lista Pacientes Hospitalizados
                <span class="count-badge"><?= count($pacients) ?></span>
            </h2>
        </div>
        <?php if (empty($pacients)): ?>
        <div class="no-data">No hay pacientes ingresados actualmente.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="tbl">
            <thead>
                <tr>
                    <th>CIP</th><th>Nombre</th><th>Apellido</th><th>Fecha Nac.</th><th>Hospital</th>
                    <th>Planta</th><th>Hab.</th><th>Vacuna</th><th>Enfermero/a</th>
                    <th>Dosis 1</th><th>Dosis 2</th><th>Dosis 3</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pacients as $p): ?>
            <tr>
                <td><code style="font-size:0.82rem;"><?= htmlspecialchars($p['cip']) ?></code></td>
                <td><?= htmlspecialchars($p['nom']) ?></td>
                <td><?= htmlspecialchars($p['cognom']) ?></td>
                <td><?= $p['fecha_nacimiento'] ?? '—' ?></td>
                <td><?= htmlspecialchars($p['hospital']) ?></td>
                <td><?= $p['planta'] ?></td>
                <td><?= $p['habitacio'] ?></td>
                <td><?= $p['idvacuna'] ? htmlspecialchars($p['idvacuna']) : '—' ?></td>
                <td>
                    <?php if ($p['idvacuna'] && $p['inf_nom']): ?>
                        <span style="font-size:0.82rem;">
                            <?= htmlspecialchars($p['inf_nom'] . ' ' . $p['inf_cognom']) ?>
                            <br><code style="font-size:0.75rem;color:#5a6478;"><?= htmlspecialchars($p['cip_enfermera'] ?? '') ?></code>
                        </span>
                    <?php elseif ($p['idvacuna']): ?>
                        <span style="color:#5a6478;font-size:0.82rem;">—</span>
                    <?php else: ?>
                        <span style="color:#9aa3b5;font-size:0.82rem;">Sin vacuna</span>
                    <?php endif; ?>
                </td>
                <td><?= $p['dosi_1'] ?? '—' ?></td>
                <td><?= $p['dosi_2'] ?? '—' ?></td>
                <td><?= $p['dosi_3'] ?? '—' ?></td>
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
