<?php
// ============================================
//  ville.php — Page principale d'une ville
//  URL : http://localhost/city_builder/ville.php?id=1
// ============================================
require_once 'config.php';
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pdo = getPDO();

$city_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($city_id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT c.*, u.username FROM cities c JOIN users u ON u.id = c.user_id WHERE c.id = ?");
$stmt->execute([$city_id]);
$city = $stmt->fetch();
if (!$city) { echo 'Ville introuvable.'; exit; }

$stmt = $pdo->prepare("SELECT b.*, bt.label, bt.population_bonus FROM buildings b JOIN building_types bt ON bt.type = b.type WHERE b.city_id = ? ORDER BY b.unlocked_at ASC");
$stmt->execute([$city_id]);
$buildings = $stmt->fetchAll();

$visitor_ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$new_building    = null;
$visit_message   = '';

$check = $pdo->prepare("SELECT id FROM visits WHERE city_id = ? AND visitor_ip = ? AND visit_date = CURDATE()");
$check->execute([$city_id, $visitor_ip]);

if (!$check->fetch()) {
    $pdo->prepare("INSERT INTO visits (city_id, visitor_ip, visit_date) VALUES (?, ?, CURDATE())")->execute([$city_id, $visitor_ip]);
    $pdo->prepare("UPDATE cities SET visits = visits + 1, updated_at = NOW() WHERE id = ?")->execute([$city_id]);
    $city['visits']++;

    $next = $pdo->prepare("SELECT bt.* FROM building_types bt WHERE bt.visit_threshold = ? AND NOT EXISTS (SELECT 1 FROM buildings b WHERE b.city_id = ? AND b.type = bt.type) LIMIT 1");
    $next->execute([$city['visits'], $city_id]);
    $unlock = $next->fetch();

    if ($unlock) {
        $pos_x = count($buildings) % 8;
        $pos_z = intdiv(count($buildings), 8);
        $pdo->prepare("INSERT INTO buildings (city_id, type, pos_x, pos_z) VALUES (?, ?, ?, ?)")->execute([$city_id, $unlock['type'], $pos_x, $pos_z]);
        $pdo->prepare("UPDATE cities SET population = population + ? WHERE id = ?")->execute([$unlock['population_bonus'], $city_id]);
        $city['population'] += $unlock['population_bonus'];
        $stmt->execute([$city_id]);
        $buildings = $stmt->fetchAll();
        $new_building  = $unlock['label'];
        $visit_message = "🎉 Nouveau bâtiment débloqué : {$unlock['label']} !";
    } else {
        $visit_message = "Merci pour ta visite ! Reviens demain.";
    }
} else {
    $visit_message = "Tu as déjà visité cette ville aujourd'hui.";
}

$next_stmt = $pdo->prepare("SELECT bt.label, bt.visit_threshold FROM building_types bt WHERE bt.visit_threshold > ? AND NOT EXISTS (SELECT 1 FROM buildings b WHERE b.city_id = ? AND b.type = bt.type) ORDER BY bt.visit_threshold ASC LIMIT 1");
$next_stmt->execute([$city['visits'], $city_id]);
$next = $next_stmt->fetch();

$buildings_json = json_encode(array_map(fn($b) => ['type' => $b['type'], 'pos_x' => (int)$b['pos_x'], 'pos_z' => (int)$b['pos_z']], $buildings));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($city['name']) ?> — City Builder</title>
<link rel="stylesheet" href="ville.css">
</head>
<body>

<div id="hud">
  <h1><?= htmlspecialchars($city['name']) ?><span>par <?= htmlspecialchars($city['username']) ?></span></h1>
  <div class="stats">
    <div class="stat"><div class="stat-val"><?= number_format($city['visits']) ?></div><div class="stat-lbl">Visites</div></div>
    <div class="stat"><div class="stat-val"><?= number_format($city['population']) ?></div><div class="stat-lbl">Habitants</div></div>
    <div class="stat"><div class="stat-val"><?= count($buildings) ?></div><div class="stat-lbl">Bâtiments</div></div>
  </div>
  <div class="nav-links">
    <?php if (isset($_SESSION['city_id']) && $_SESSION['city_id'] == $city_id): ?>
      <a href="ville.php?id=<?= $city_id ?>&logout=1">Déconnexion</a>
    <?php elseif (!isset($_SESSION['user_id'])): ?>
      <a href="login.php">Connexion</a>
    <?php endif; ?>
    <a href="index.php">Classement</a>
  </div>
</div>

<div id="canvas-container" style="width:100vw;height:100vh;"></div>

<div id="toast"<?php if ($new_building) echo ' class="new-building"'; ?>><?= htmlspecialchars($visit_message) ?></div>
<div id="tooltip"></div>
<div id="hint">🖱 Clic + glisser pour tourner · Molette pour zoomer · <strong>F</strong> pour mode FPS</div>
<div id="fps-hint" style="display:none">🎮 <strong>ZQSD / Flèches</strong> pour avancer · <strong>Souris</strong> pour regarder · <strong>Shift</strong> pour courir · <strong>F</strong> pour quitter</div>

<?php if ($next): ?>
<div id="next-info">Prochain : <strong><?= htmlspecialchars($next['label']) ?></strong> dans <?= $next['visit_threshold'] - $city['visits'] ?> visite(s)</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
  const BUILDINGS_DATA = <?= $buildings_json ?>;
</script>
<script src="ville.js"></script>
</body>
</html>