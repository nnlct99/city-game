<?php
// ============================================
//  ville.php — Page principale d'une ville
//  URL : http://localhost/city_builder/ville.php?id=1
// ============================================
require_once 'config.php';

$pdo = getPDO();

// --- Récupération de la ville ---
$city_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($city_id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("
    SELECT c.*, u.username
    FROM cities c
    JOIN users u ON u.id = c.user_id
    WHERE c.id = ?
");
$stmt->execute([$city_id]);
$city = $stmt->fetch();
if (!$city) { echo 'Ville introuvable.'; exit; }

// --- Récupération des bâtiments ---
$stmt = $pdo->prepare("
    SELECT b.*, bt.label, bt.population_bonus
    FROM buildings b
    JOIN building_types bt ON bt.type = b.type
    WHERE b.city_id = ?
    ORDER BY b.unlocked_at ASC
");
$stmt->execute([$city_id]);
$buildings = $stmt->fetchAll();

// --- Logique de visite (anti-spam IP / jour) ---
$visitor_ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$already_visited = false;
$new_building    = null;
$visit_message   = '';

$check = $pdo->prepare("
    SELECT id FROM visits
    WHERE city_id = ? AND visitor_ip = ? AND DATE(visited_at) = CURDATE()
");
$check->execute([$city_id, $visitor_ip]);

if (!$check->fetch()) {
    // Nouvelle visite aujourd'hui
    $pdo->prepare("INSERT INTO visits (city_id, visitor_ip) VALUES (?, ?)")
        ->execute([$city_id, $visitor_ip]);

    $pdo->prepare("UPDATE cities SET visits = visits + 1, updated_at = NOW() WHERE id = ?")
        ->execute([$city_id]);

    $city['visits']++;

    // Vérifie si un bâtiment se débloque
    $next = $pdo->prepare("
        SELECT bt.*
        FROM building_types bt
        WHERE bt.visit_threshold = ?
          AND NOT EXISTS (
              SELECT 1 FROM buildings b
              WHERE b.city_id = ? AND b.type = bt.type
          )
        LIMIT 1
    ");
    $next->execute([$city['visits'], $city_id]);
    $unlock = $next->fetch();

    if ($unlock) {
        // Calcule une position libre sur la grille
        $taken = array_map(fn($b) => $b['pos_x'].','.$b['pos_z'], $buildings);
        $pos_x = count($buildings) % 8;
        $pos_z = intdiv(count($buildings), 8);

        $pdo->prepare("
            INSERT INTO buildings (city_id, type, pos_x, pos_z)
            VALUES (?, ?, ?, ?)
        ")->execute([$city_id, $unlock['type'], $pos_x, $pos_z]);

        $pdo->prepare("
            UPDATE cities SET population = population + ? WHERE id = ?
        ")->execute([$unlock['population_bonus'], $city_id]);

        $city['population'] += $unlock['population_bonus'];

        // Recharge les bâtiments avec le nouveau
        $stmt->execute([$city_id]);
        $buildings = $stmt->fetchAll();
        $new_building  = $unlock['label'];
        $visit_message = "Nouveau bâtiment débloqué : {$unlock['label']} !";
    } else {
        $visit_message = "Merci pour ta visite ! Reviens demain.";
    }
} else {
    $already_visited = true;
    $visit_message   = "Tu as déjà visité cette ville aujourd'hui.";
}

// --- Prochain bâtiment ---
$next_building = $pdo->prepare("
    SELECT bt.label, bt.visit_threshold
    FROM building_types bt
    WHERE bt.visit_threshold > ?
      AND NOT EXISTS (
          SELECT 1 FROM buildings b
          WHERE b.city_id = ? AND b.type = bt.type
      )
    ORDER BY bt.visit_threshold ASC
    LIMIT 1
");
$next_building->execute([$city['visits'], $city_id]);
$next = $next_building->fetch();

// JSON des bâtiments pour Three.js
$buildings_json = json_encode(array_map(fn($b) => [
    'type'  => $b['type'],
    'pos_x' => (int)$b['pos_x'],
    'pos_z' => (int)$b['pos_z'],
], $buildings));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($city['name']) ?> — City Builder</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { background:#1a1a2e; color:#eee; font-family: 'Segoe UI', sans-serif; overflow: hidden; }

  #hud {
    position: fixed; top: 0; left: 0; right: 0;
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 20px;
    background: rgba(10,10,30,0.85);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    z-index: 10;
  }
  #hud h1 { font-size: 18px; font-weight: 500; }
  #hud h1 span { font-size: 13px; color: #aaa; font-weight: 400; margin-left: 8px; }
  .stats { display: flex; gap: 24px; }
  .stat { text-align: center; }
  .stat-val { font-size: 20px; font-weight: 600; color: #7ec8e3; }
  .stat-lbl { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.05em; }

  #toast {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
    background: rgba(20,20,50,0.95); border: 1px solid rgba(126,200,227,0.3);
    color: #eee; padding: 10px 24px; border-radius: 8px;
    font-size: 14px; z-index: 20;
    transition: opacity 0.5s ease;
  }
  #toast.hidden { opacity: 0; pointer-events: none; }

  <?php if ($new_building): ?>
  #toast { animation: pulse 1s ease 2; border-color: rgba(100,255,150,0.5); }
  @keyframes pulse { 0%,100% { box-shadow: 0 0 0 rgba(100,255,150,0); } 50% { box-shadow: 0 0 16px rgba(100,255,150,0.4); } }
  <?php endif; ?>

  #next-info {
    position: fixed; bottom: 24px; right: 24px;
    background: rgba(10,10,30,0.85); border: 1px solid rgba(255,255,255,0.08);
    padding: 10px 16px; border-radius: 8px; font-size: 13px; color: #aaa;
    z-index: 10;
  }
  #next-info strong { color: #7ec8e3; }

  #canvas-container { width: 100vw; height: 100vh; }
</style>
</head>
<body>

<div id="hud">
  <h1>
    <?= htmlspecialchars($city['name']) ?>
    <span>par <?= htmlspecialchars($city['username']) ?></span>
  </h1>
  <div class="stats">
    <div class="stat">
      <div class="stat-val"><?= number_format($city['visits']) ?></div>
      <div class="stat-lbl">Visites</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= number_format($city['population']) ?></div>
      <div class="stat-lbl">Habitants</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= count($buildings) ?></div>
      <div class="stat-lbl">Bâtiments</div>
    </div>
  </div>
</div>

<div id="canvas-container"></div>

<div id="toast"><?= htmlspecialchars($visit_message) ?></div>

<?php if ($next): ?>
<div id="next-info">
  Prochain : <strong><?= htmlspecialchars($next['label']) ?></strong>
  dans <?= $next['visit_threshold'] - $city['visits'] ?> visite(s)
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
// ============================================
//  Rendu 3D de la ville avec Three.js
// ============================================

const BUILDINGS_DATA = <?= $buildings_json ?>;

// --- Scène ---
const scene    = new THREE.Scene();
scene.background = new THREE.Color(0x1a1a2e);
scene.fog        = new THREE.Fog(0x1a1a2e, 30, 80);

const W = window.innerWidth, H = window.innerHeight;
const camera = new THREE.PerspectiveCamera(45, W / H, 0.1, 200);
camera.position.set(12, 14, 18);
camera.lookAt(4, 0, 4);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(W, H);
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
document.getElementById('canvas-container').appendChild(renderer.domElement);

// --- Lumières ---
const ambient = new THREE.AmbientLight(0x334466, 0.8);
scene.add(ambient);

const sun = new THREE.DirectionalLight(0xffe8b0, 1.4);
sun.position.set(20, 30, 15);
sun.castShadow = true;
sun.shadow.mapSize.width  = 2048;
sun.shadow.mapSize.height = 2048;
sun.shadow.camera.near = 0.5;
sun.shadow.camera.far  = 100;
sun.shadow.camera.left = sun.shadow.camera.bottom = -30;
sun.shadow.camera.right = sun.shadow.camera.top = 30;
scene.add(sun);

// Lumière bleue nocturne
const fill = new THREE.DirectionalLight(0x4466aa, 0.4);
fill.position.set(-10, 5, -10);
scene.add(fill);

// --- Sol ---
const groundGeo = new THREE.PlaneGeometry(60, 60, 20, 20);
const groundMat = new THREE.MeshLambertMaterial({ color: 0x2d4a1e });
const ground = new THREE.Mesh(groundGeo, groundMat);
ground.rotation.x = -Math.PI / 2;
ground.receiveShadow = true;
scene.add(ground);

// Grille de rues
const gridHelper = new THREE.GridHelper(60, 20, 0x3a5a2a, 0x3a5a2a);
gridHelper.position.y = 0.01;
scene.add(gridHelper);

// --- Helpers de construction ---
function box(w, h, d, color, emissive = 0x000000) {
  const geo = new THREE.BoxGeometry(w, h, d);
  const mat = new THREE.MeshLambertMaterial({ color, emissiveIntensity: 0.3 });
  if (emissive) mat.emissive = new THREE.Color(emissive);
  const mesh = new THREE.Mesh(geo, mat);
  mesh.castShadow    = true;
  mesh.receiveShadow = true;
  return mesh;
}

function addWindows(parent, buildingW, buildingH, buildingD, floors, cols, color = 0xaaddf0) {
  const wW = 0.18, wH = 0.22;
  const spacingX = buildingW / (cols + 1);
  const spacingY = buildingH / (floors + 1);
  const mat = new THREE.MeshLambertMaterial({ color, emissive: new THREE.Color(color), emissiveIntensity: 0.6 });
  for (let f = 0; f < floors; f++) {
    for (let c = 0; c < cols; c++) {
      const geo = new THREE.BoxGeometry(wW, wH, 0.02);
      const w = new THREE.Mesh(geo, mat);
      w.position.set(
        -buildingW/2 + spacingX*(c+1),
        -buildingH/2 + spacingY*(f+1),
        buildingD/2 + 0.01
      );
      parent.add(w);
      // Face arrière aussi
      const w2 = w.clone();
      w2.position.z = -buildingD/2 - 0.01;
      w2.rotation.y = Math.PI;
      parent.add(w2);
    }
  }
}

// --- Définitions des bâtiments 3D ---
const BUILDERS = {
  tent(group) {
    const pole = box(0.05, 1.4, 0.05, 0x8B4513);
    pole.position.y = 0.7;
    group.add(pole);
    const geo = new THREE.ConeGeometry(1, 1.2, 4);
    const mat = new THREE.MeshLambertMaterial({ color: 0xe8c87a });
    const cone = new THREE.Mesh(geo, mat);
    cone.position.y = 1.5;
    cone.rotation.y = Math.PI/4;
    cone.castShadow = true;
    group.add(cone);
  },

  house(group) {
    const walls = box(1.6, 1, 1.6, 0xd9795b);
    walls.position.y = 0.5;
    group.add(walls);
    const geo = new THREE.ConeGeometry(1.3, 0.8, 4);
    const mat = new THREE.MeshLambertMaterial({ color: 0x9b3a2a });
    const roof = new THREE.Mesh(geo, mat);
    roof.position.y = 1.4;
    roof.rotation.y = Math.PI/4;
    roof.castShadow = true;
    group.add(roof);
    const door = box(0.3, 0.5, 0.05, 0x5a3010);
    door.position.set(0, 0.25, 0.83);
    group.add(door);
  },

  shop(group) {
    const base = box(2, 1.4, 2, 0x6a9ec9);
    base.position.y = 0.7;
    group.add(base);
    addWindows(base, 2, 1.4, 2, 1, 2, 0xfff0a0);
    const sign = box(1.4, 0.3, 0.05, 0xff6633);
    sign.position.set(0, 1.2, 1.02);
    const signMat = new THREE.MeshLambertMaterial({ color: 0xff6633, emissive: new THREE.Color(0xff3300), emissiveIntensity: 0.4 });
    sign.material = signMat;
    group.add(sign);
    const awning = box(2.2, 0.06, 0.6, 0xcc4400);
    awning.position.set(0, 0.9, 1.3);
    awning.rotation.x = 0.3;
    group.add(awning);
  },

  flat(group) {
    const base = box(2, 5, 2, 0x8a7fc9);
    base.position.y = 2.5;
    group.add(base);
    addWindows(base, 2, 5, 2, 5, 2);
    const roof = box(2.2, 0.15, 2.2, 0x6a5fa9);
    roof.position.y = 5.07;
    group.add(roof);
    // Antenne
    const ant = box(0.04, 0.6, 0.04, 0x444);
    ant.position.set(0.6, 5.6, 0.6);
    group.add(ant);
  },

  factory(group) {
    const main = box(3, 2, 2.5, 0x888);
    main.position.y = 1;
    group.add(main);
    // Cheminées
    [[0.8, 1.4], [-0.8, 1.8]].forEach(([ox, h]) => {
      const ch = box(0.3, h, 0.3, 0x666);
      ch.position.set(ox, h/2 + 2, 0);
      group.add(ch);
      const smoke = new THREE.Mesh(
        new THREE.CylinderGeometry(0.25, 0.15, 0.3, 8),
        new THREE.MeshLambertMaterial({ color: 0x555, transparent: true, opacity: 0.6 })
      );
      smoke.position.set(ox, h + 2.3, 0);
      group.add(smoke);
    });
    // Fenêtres atelier
    addWindows(main, 3, 2, 2.5, 1, 3, 0xffd080);
  },

  town_hall(group) {
    const base = box(3.5, 2.5, 3, 0xc9a84c);
    base.position.y = 1.25;
    group.add(base);
    addWindows(base, 3.5, 2.5, 3, 2, 3, 0xfff0a0);
    // Colonnes
    [-1.2, -0.4, 0.4, 1.2].forEach(ox => {
      const col = box(0.2, 2.5, 0.2, 0xd9b86c);
      col.position.set(ox, 1.25, 1.6);
      group.add(col);
    });
    // Fronton triangulaire
    const geo = new THREE.CylinderGeometry(0, 2.2, 1, 3);
    const mat = new THREE.MeshLambertMaterial({ color: 0xb09030 });
    const ped = new THREE.Mesh(geo, mat);
    ped.position.y = 3;
    ped.rotation.y = Math.PI/6;
    ped.castShadow = true;
    group.add(ped);
    // Dôme
    const dome = new THREE.Mesh(
      new THREE.SphereGeometry(0.6, 12, 8, 0, Math.PI*2, 0, Math.PI/2),
      new THREE.MeshLambertMaterial({ color: 0x7ec8e3, emissive: new THREE.Color(0x336688), emissiveIntensity: 0.3 })
    );
    dome.position.y = 4.2;
    dome.castShadow = true;
    group.add(dome);
  },

  tower(group) {
    // Base large
    const base = box(2, 1, 2, 0x5b9ea0);
    base.position.y = 0.5;
    group.add(base);
    // Corps principal effilé
    const shaft = box(1.2, 10, 1.2, 0x5b9ea0);
    shaft.position.y = 6;
    group.add(shaft);
    addWindows(shaft, 1.2, 10, 1.2, 9, 2);
    // Couronnement
    const crown = box(1.6, 0.3, 1.6, 0x4a8a8e);
    crown.position.y = 11.15;
    group.add(crown);
    // Antenne lumineuse
    const ant = box(0.04, 1.5, 0.04, 0x888);
    ant.position.y = 12.15;
    group.add(ant);
    const light = new THREE.Mesh(
      new THREE.SphereGeometry(0.08, 6, 6),
      new THREE.MeshLambertMaterial({ color: 0xff2222, emissive: new THREE.Color(0xff0000), emissiveIntensity: 1 })
    );
    light.position.y = 13;
    group.add(light);
  },

  cathedral(group) {
    // Nef
    const nave = box(2.5, 3, 5, 0xb06a8a);
    nave.position.y = 1.5;
    group.add(nave);
    // Tour centrale
    const tower = box(1.4, 6, 1.4, 0x9a5a78);
    tower.position.y = 4;
    group.add(tower);
    // Flèche
    const geo = new THREE.ConeGeometry(0.9, 3, 4);
    const mat = new THREE.MeshLambertMaterial({ color: 0x7a3a58 });
    const spire = new THREE.Mesh(geo, mat);
    spire.position.y = 8.5;
    spire.rotation.y = Math.PI/4;
    spire.castShadow = true;
    group.add(spire);
    // Rosace (vitrail)
    const rose = new THREE.Mesh(
      new THREE.CircleGeometry(0.5, 16),
      new THREE.MeshLambertMaterial({ color: 0xffd080, emissive: new THREE.Color(0xff8800), emissiveIntensity: 0.6 })
    );
    rose.position.set(0, 2.5, 2.51);
    group.add(rose);
    addWindows(nave, 2.5, 3, 5, 2, 2, 0x80ccff);
  }
};

// --- Placement des bâtiments ---
BUILDINGS_DATA.forEach((b, i) => {
  const group = new THREE.Group();
  const builder = BUILDERS[b.type] || BUILDERS['tent'];
  builder(group);

  // Position sur la grille (chaque case = 3.5 unités)
  group.position.set(b.pos_x * 3.5, 0, b.pos_z * 3.5);

  // Animation d'apparition légère
  group.scale.set(0.01, 0.01, 0.01);
  scene.add(group);

  // Apparition progressive avec délai
  const delay = i * 120;
  setTimeout(() => {
    let t = 0;
    const grow = setInterval(() => {
      t += 0.06;
      const s = Math.min(1, t);
      group.scale.set(s, s, s);
      if (s >= 1) clearInterval(grow);
    }, 16);
  }, delay);
});

// --- Contrôle caméra à la souris ---
let isDragging = false, lastX = 0, lastY = 0;
let theta = 0.8, phi = 0.9, radius = 22;
const target = new THREE.Vector3(6, 0, 6);

renderer.domElement.addEventListener('mousedown', e => { isDragging = true; lastX = e.clientX; lastY = e.clientY; });
renderer.domElement.addEventListener('mouseup',   () => { isDragging = false; });
renderer.domElement.addEventListener('mousemove', e => {
  if (!isDragging) return;
  theta -= (e.clientX - lastX) * 0.006;
  phi    = Math.max(0.25, Math.min(1.4, phi - (e.clientY - lastY) * 0.006));
  lastX = e.clientX; lastY = e.clientY;
  updateCamera();
});
renderer.domElement.addEventListener('wheel', e => {
  radius = Math.max(8, Math.min(50, radius + e.deltaY * 0.05));
  updateCamera();
});

// Touch support
renderer.domElement.addEventListener('touchstart', e => { isDragging = true; lastX = e.touches[0].clientX; lastY = e.touches[0].clientY; });
renderer.domElement.addEventListener('touchend',   () => { isDragging = false; });
renderer.domElement.addEventListener('touchmove',  e => {
  if (!isDragging) return;
  theta -= (e.touches[0].clientX - lastX) * 0.006;
  phi    = Math.max(0.25, Math.min(1.4, phi - (e.touches[0].clientY - lastY) * 0.006));
  lastX = e.touches[0].clientX; lastY = e.touches[0].clientY;
  updateCamera();
});

function updateCamera() {
  camera.position.set(
    target.x + radius * Math.sin(phi) * Math.sin(theta),
    target.y + radius * Math.cos(phi),
    target.z + radius * Math.sin(phi) * Math.cos(theta)
  );
  camera.lookAt(target);
}
updateCamera();

// --- Toast auto-dismiss ---
setTimeout(() => {
  const toast = document.getElementById('toast');
  if (toast) toast.classList.add('hidden');
}, 4000);

// --- Boucle de rendu ---
let time = 0;
function animate() {
  requestAnimationFrame(animate);
  time += 0.01;
  // Légère oscillation du soleil (cycle jour/nuit très lent)
  sun.intensity = 1.2 + 0.2 * Math.sin(time * 0.05);
  renderer.render(scene, camera);
}
animate();

// --- Resize ---
window.addEventListener('resize', () => {
  camera.aspect = window.innerWidth / window.innerHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(window.innerWidth, window.innerHeight);
});
</script>
</body>
</html>
