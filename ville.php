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
<div id="hint">🖱 Clic + glisser pour tourner · Molette pour zoomer</div>

<?php if ($next): ?>
<div id="next-info">Prochain : <strong><?= htmlspecialchars($next['label']) ?></strong> dans <?= $next['visit_threshold'] - $city['visits'] ?> visite(s)</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
const BUILDINGS_DATA = <?= $buildings_json ?>;

// ============================================
//  Scène — ambiance jour ensoleillé
// ============================================
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x87CEEB);
scene.fog = new THREE.FogExp2(0xb8ddf0, 0.016);

const W = window.innerWidth, H = window.innerHeight;
const camera = new THREE.PerspectiveCamera(50, W/H, 0.1, 300);
const renderer = new THREE.WebGLRenderer({ antialias:true });
renderer.setSize(W, H);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.15;
document.getElementById('canvas-container').appendChild(renderer.domElement);

// Lumières
const sun = new THREE.DirectionalLight(0xfffbe8, 2.4);
sun.position.set(25, 40, 20);
sun.castShadow = true;
sun.shadow.mapSize.width = sun.shadow.mapSize.height = 4096;
sun.shadow.camera.near = 1; sun.shadow.camera.far = 120;
sun.shadow.camera.left = sun.shadow.camera.bottom = -45;
sun.shadow.camera.right = sun.shadow.camera.top = 45;
sun.shadow.bias = -0.0005;
scene.add(sun);
scene.add(new THREE.AmbientLight(0xc8e8ff, 1.0));
scene.add(new THREE.HemisphereLight(0x87CEEB, 0x3a8a1a, 0.7));
const fill = new THREE.DirectionalLight(0xffd4a0, 0.5);
fill.position.set(-15, 10, -10); scene.add(fill);

// Sol herbe
const ground = new THREE.Mesh(new THREE.PlaneGeometry(100, 100), new THREE.MeshLambertMaterial({ color:0x5aaa32 }));
ground.rotation.x = -Math.PI/2; ground.receiveShadow = true; scene.add(ground);

// Routes
function road(w, h, x, z) {
  const m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshLambertMaterial({ color:0xc8b89a }));
  m.rotation.x = -Math.PI/2; m.position.set(x, 0.01, z); m.receiveShadow = true; scene.add(m);
}
for (let i = -1; i <= 4; i++) { road(100, 0.8, 0, i*3.5-0.5); road(0.8, 100, i*3.5-0.5, 0); }

// Trottoirs
for (let i = -1; i <= 4; i++) {
  for (let j = -1; j <= 4; j++) {
    const curb = new THREE.Mesh(new THREE.PlaneGeometry(0.25, 0.25), new THREE.MeshLambertMaterial({ color:0xddccbb }));
    curb.rotation.x = -Math.PI/2; curb.position.set(i*3.5-0.5, 0.015, j*3.5-0.5); scene.add(curb);
  }
}

// Nuages
function cloud(x, y, z, s) {
  const g = new THREE.Group();
  const mat = new THREE.MeshLambertMaterial({ color:0xffffff });
  [[0,0,0,1.5,.7,1.3],[1.3,.1,0,1.,.6,1.],[-.9,.05,0,.9,.5,.9],[.4,.45,0,.75,.48,.75],[-1.4,-.05,-.3,.7,.4,.7]].forEach(([px,py,pz,sx,sy,sz]) => {
    const m = new THREE.Mesh(new THREE.SphereGeometry(1,7,5), mat);
    m.scale.set(sx,sy,sz); m.position.set(px,py,pz); g.add(m);
  });
  g.position.set(x,y,z); g.scale.setScalar(s); scene.add(g); return g;
}
const clouds = [cloud(-12,20,-6,2.2),cloud(6,24,-14,2.6),cloud(20,21,3,1.9),cloud(-6,27,12,2.3),cloud(28,20,-10,1.7),cloud(2,18,20,2.0)];

// Arbres
function tree(x, z) {
  const g = new THREE.Group();
  const trunkMat = new THREE.MeshLambertMaterial({ color:0x7a4a20 });
  const leafMat  = new THREE.MeshLambertMaterial({ color:new THREE.Color(0.15+Math.random()*.1, 0.55+Math.random()*.15, 0.15+Math.random()*.05) });
  const trunk = new THREE.Mesh(new THREE.CylinderGeometry(.11,.17,.9,6), trunkMat);
  trunk.position.y = .45; trunk.castShadow = true; g.add(trunk);
  [[.45,1.8],[.58,1.15],[.4,1.45]].forEach(([r,y],i) => {
    const h = [.9,.7,.55][i];
    const cone = new THREE.Mesh(new THREE.ConeGeometry(r,h,7), leafMat);
    cone.position.y = y; cone.castShadow = true; g.add(cone);
  });
  g.position.set(x, 0, z);
  g.scale.setScalar(.85+Math.random()*.3);
  scene.add(g);
}
[[-3.5,-3.5],[-3.5,0],[-3.5,3.5],[-3.5,7],[-3.5,10.5],[-3.5,14],
 [0,-3.5],[3.5,-3.5],[7,-3.5],[10.5,-3.5],[14,-3.5],
 [0,14],[3.5,14],[7,14],[10.5,14],[14,14],
 [17.5,-3.5],[17.5,0],[17.5,3.5],[17.5,7],[17.5,10.5],[17.5,14],
 [-7,3],[-7,7],[20,3],[20,7]].forEach(([x,z]) => tree(x,z));

// Bancs et lampadaires
function bench(x, z) {
  const g = new THREE.Group();
  const seat = new THREE.Mesh(new THREE.BoxGeometry(.7,.06,.25), new THREE.MeshLambertMaterial({color:0x8B5E3C}));
  seat.position.y = .3; g.add(seat);
  [-0.28,0.28].forEach(lx => {
    const leg = new THREE.Mesh(new THREE.BoxGeometry(.06,.3,.06), new THREE.MeshLambertMaterial({color:0x555}));
    leg.position.set(lx,.15,0); g.add(leg);
  });
  g.position.set(x,.0,z); scene.add(g);
}
function lamp(x, z) {
  const g = new THREE.Group();
  const pole = new THREE.Mesh(new THREE.CylinderGeometry(.04,.04,2.2,6), new THREE.MeshLambertMaterial({color:0x666}));
  pole.position.y = 1.1; g.add(pole);
  const head = new THREE.Mesh(new THREE.SphereGeometry(.14,8,8), new THREE.MeshLambertMaterial({color:0xffffcc, emissive:new THREE.Color(0xffff88), emissiveIntensity:.8}));
  head.position.y = 2.3; g.add(head);
  g.position.set(x,0,z); scene.add(g);
}
[[-.8,-.8],[-.8,3.2],[-.8,6.7],[3.2,-.8],[6.7,-.8]].forEach(([x,z]) => lamp(x,z));
[[1.2,-.4],[4.7,-.4],[1.2,3.1]].forEach(([x,z]) => bench(x,z));

// ============================================
//  Bâtiments
// ============================================
function box3(w,h,d,color) {
  const m = new THREE.Mesh(new THREE.BoxGeometry(w,h,d), new THREE.MeshLambertMaterial({color}));
  m.castShadow = m.receiveShadow = true; return m;
}
function wins(parent, bW,bH,bD, floors,cols, wc=0xaad4f5) {
  const mat = new THREE.MeshLambertMaterial({color:wc, emissive:new THREE.Color(wc), emissiveIntensity:.12});
  const sX=bW/(cols+1), sY=bH/(floors+1);
  for (let f=0;f<floors;f++) for (let c=0;c<cols;c++) {
    const w = new THREE.Mesh(new THREE.BoxGeometry(.2,.26,.03), mat);
    w.position.set(-bW/2+sX*(c+1), -bH/2+sY*(f+1), bD/2+.02); parent.add(w);
    const w2=w.clone(); w2.position.z=-bD/2-.02; w2.rotation.y=Math.PI; parent.add(w2);
  }
}

const BUILDERS = {
  tent(g) {
    const trunk=box3(.07,.95,.07,0x8B5E3C); trunk.position.y=.47; g.add(trunk);
    const cone=new THREE.Mesh(new THREE.ConeGeometry(.95,1.15,4),new THREE.MeshLambertMaterial({color:0xf5c842}));
    cone.rotation.y=Math.PI/4; cone.position.y=1.3; cone.castShadow=true; g.add(cone);
    const door=box3(.22,.36,.04,0x8B4513); door.position.set(0,.18,.67); g.add(door);
  },
  house(g) {
    const walls=box3(1.8,1.1,1.8,0xf5dfa0); walls.position.y=.55; g.add(walls);
    const roof=new THREE.Mesh(new THREE.ConeGeometry(1.5,.95,4),new THREE.MeshLambertMaterial({color:0xcc3311}));
    roof.rotation.y=Math.PI/4; roof.position.y=1.58; roof.castShadow=true; g.add(roof);
    [[-.5,.62,.91],[.5,.62,.91]].forEach(([x,y,z])=>{ const w=box3(.3,.3,.04,0xaad4f5); w.position.set(x,y,z); g.add(w); });
    const door=box3(.32,.56,.04,0x8B4513); door.position.set(0,.28,.91); g.add(door);
    const ch=box3(.2,.42,.2,0xcc8855); ch.position.set(.42,1.82,.2); ch.castShadow=true; g.add(ch);
  },
  shop(g) {
    const base=box3(2.2,1.6,2.,0x4a9fd4); base.position.y=.8; g.add(base);
    wins(base,2.2,1.6,2.,1,2,0xfff5cc);
    const roof=box3(2.4,.12,2.2,0x2a7ab0); roof.position.y=1.66; g.add(roof);
    const awn=box3(2.5,.08,.72,0xff6622); awn.position.set(0,1.,.135); awn.rotation.x=.25; g.add(awn);
    const sign=box3(1.5,.32,.06,0xff8800); sign.position.set(0,1.3,1.04); g.add(sign);
    const door=box3(.38,.65,.04,0x1a4a6a); door.position.set(0,.32,1.01); g.add(door);
  },
  flat(g) {
    const soc=box3(2.2,.25,2.2,0xddccbb); soc.position.y=.12; g.add(soc);
    const main=box3(2.,5.5,2.,0xf0e0c0); main.position.y=3.; g.add(main);
    wins(main,2.,5.5,2.,6,2,0xaad4f5);
    for(let f=0;f<3;f++){const bal=box3(2.2,.08,.4,0xccbbaa); bal.position.set(0,.8+f*1.7,1.2); g.add(bal);}
    const roof=box3(2.2,.18,2.2,0xbbaa99); roof.position.y=6.09; g.add(roof);
    const ant=box3(.04,.7,.04,0x888); ant.position.set(.5,6.55,.5); g.add(ant);
  },
  factory(g) {
    const main=box3(3.2,2.2,2.8,0xb8b8b8); main.position.y=1.1; g.add(main);
    wins(main,3.2,2.2,2.8,1,3,0xffdd88);
    for(let i=0;i<3;i++){const s=box3(.9,.5,2.8,0xaaa); s.position.set(-1+i*1.,2.45,0); g.add(s);}
    [[1.,2.],[-.8,2.5]].forEach(([ox,h])=>{
      const ch=box3(.35,h,.35,0x888); ch.position.set(ox,2.2+h/2,0); ch.castShadow=true; g.add(ch);
      const sm=new THREE.Mesh(new THREE.CylinderGeometry(.22,.14,.4,8),new THREE.MeshLambertMaterial({color:0xccc,transparent:true,opacity:.45}));
      sm.position.set(ox,2.2+h+.3,0); g.add(sm);
    });
    const door=box3(.8,.9,.05,0x777); door.position.set(.6,.45,1.41); g.add(door);
  },
  town_hall(g) {
    // Escaliers d'entrée
    const steps=box3(3.,.18,3.6,0xeeddbb); steps.position.set(0,.09,0); g.add(steps);
    const steps2=box3(3.6,.18,4.2,0xe8d8aa); steps2.position.set(0,-.09,0); g.add(steps2);
    // Corps principal
    const base=box3(3.6,2.6,3.2,0xf8ecc8); base.position.y=1.48; g.add(base);
    wins(base,3.6,2.6,3.2,2,3,0xfff5aa);
    // 6 colonnes en façade
    [-1.3,-.42,.42,1.3].forEach(ox=>{
      const col=box3(.2,2.6,.2,0xfffaee); col.position.set(ox,1.48,1.7); g.add(col);
      // Chapiteau
      const cap=box3(.32,.12,.32,0xeedd99); cap.position.set(ox,2.84,1.7); g.add(cap);
    });
    // Fronton triangulaire plat (pas conique)
    const pedL=box3(.08,1.,.08,0xeedd99); pedL.position.set(-1.9,3.28,1.7); pedL.rotation.z=.72; g.add(pedL);
    const pedR=box3(.08,1.,.08,0xeedd99); pedR.position.set( 1.9,3.28,1.7); pedR.rotation.z=-.72; g.add(pedR);
    const pedTop=box3(.08,.08,.08,0xeedd99); pedTop.position.set(0,3.78,1.7); g.add(pedTop);
    const pedBase=box3(3.9,.1,.1,0xeedd99); pedBase.position.set(0,2.82,1.7); g.add(pedBase);
    // Attique (bloc au-dessus du corps)
    const attic=box3(3.2,.5,2.8,0xf0ddb0); attic.position.y=2.95; g.add(attic);
    // Tambour (cylindre sous le dôme)
    const drum=new THREE.Mesh(new THREE.CylinderGeometry(.72,.72,.55,16),new THREE.MeshLambertMaterial({color:0xeedd99}));
    drum.position.y=3.47; drum.castShadow=true; g.add(drum);
    // Dôme propre
    const dome=new THREE.Mesh(new THREE.SphereGeometry(.72,16,12,0,Math.PI*2,0,Math.PI/2),new THREE.MeshLambertMaterial({color:0x4ecdc4}));
    dome.position.y=3.98; dome.castShadow=true; g.add(dome);
    // Lanterne sur le dôme
    const lantern=box3(.22,.3,.22,0xeedd99); lantern.position.y=4.76; g.add(lantern);
    // Drapeau
    const pole=box3(.04,.9,.04,0x888); pole.position.set(0,5.35,0); g.add(pole);
    const flag=box3(.44,.28,.03,0xff3333); flag.position.set(.24,5.65,0); g.add(flag);
    // Porte centrale double
    const door=box3(.5,.75,.05,0x8B4513); door.position.set(0,.37,1.61); g.add(door);
    const arch=new THREE.Mesh(new THREE.CylinderGeometry(.26,.26,.05,10,1,false,0,Math.PI),new THREE.MeshLambertMaterial({color:0x8B4513}));
    arch.rotation.z=-Math.PI/2; arch.position.set(0,1.02,1.61); g.add(arch);
  },
  tower(g) {
    const base=box3(2.4,.8,2.4,0xc8c0b8); base.position.y=.4; g.add(base);
    [[1.6,3.,1.6,0xe8e0d8,2.3],[1.2,3.,1.2,0xddd5cc,5.3],[.9,3.,.9,0xd0c8bf,8.3],[.7,2.,.7,0xc8c0b8,11.]].forEach(([w,h,d,col,y])=>{
      const s=box3(w,h,d,col); s.position.y=y; wins(s,w,h,d,Math.floor(h),1,0xaad4f5); g.add(s);
    });
    const crown=box3(1.,.3,1.,0xaaa8a0); crown.position.y=12.15; g.add(crown);
    const ant=box3(.05,1.8,.05,0x999); ant.position.y=13.2; g.add(ant);
    const light=new THREE.Mesh(new THREE.SphereGeometry(.1,8,8),new THREE.MeshLambertMaterial({color:0xff2222,emissive:new THREE.Color(0xff0000),emissiveIntensity:1.5}));
    light.position.y=14.15; g.add(light);
  },
  cathedral(g) {
    const nave=box3(3.,3.5,6.,0xd4c4e8); nave.position.y=1.75; g.add(nave);
    wins(nave,3.,3.5,6.,2,2,0x88ccff);
    const tower=box3(1.6,7.,1.6,0xc4b4d8); tower.position.y=4.8; g.add(tower);
    const spire=new THREE.Mesh(new THREE.ConeGeometry(1.,3.5,4),new THREE.MeshLambertMaterial({color:0x9a7ab4}));
    spire.position.y=9.75; spire.rotation.y=Math.PI/4; spire.castShadow=true; g.add(spire);
    [[-1.5,3.2,0],[1.5,3.2,0]].forEach(([x,y,z])=>{
      const st=box3(.7,2.5,.7,0xc4b4d8); st.position.set(x,y,z); g.add(st);
      const ss=new THREE.Mesh(new THREE.ConeGeometry(.45,1.2,4),new THREE.MeshLambertMaterial({color:0x9a7ab4}));
      ss.position.set(x,y+1.85,z); ss.rotation.y=Math.PI/4; ss.castShadow=true; g.add(ss);
    });
    const rose=new THREE.Mesh(new THREE.CircleGeometry(.55,16),new THREE.MeshLambertMaterial({color:0xffcc44,emissive:new THREE.Color(0xff8800),emissiveIntensity:.6}));
    rose.position.set(0,2.8,3.01); g.add(rose);
    const door=box3(.5,.9,.05,0x5a3070); door.position.set(0,.45,3.01); g.add(door);
  }
};

const buildingGroups = [];
const LABELS = {tent:'Tente',house:'Maison',shop:'Épicerie',flat:'Immeuble',factory:'Usine',town_hall:'Mairie',tower:'Tour',cathedral:'Cathédrale'};

BUILDINGS_DATA.forEach((b, i) => {
  const g = new THREE.Group();
  if (BUILDERS[b.type]) BUILDERS[b.type](g);
  g.position.set(b.pos_x*3.5, -5, b.pos_z*3.5);
  g.userData.label = LABELS[b.type] || b.type;
  scene.add(g);
  buildingGroups.push(g);

  setTimeout(() => {
    const rise = setInterval(() => {
      const dy = (0 - g.position.y) * .14;
      g.position.y += dy;
      if (Math.abs(g.position.y) < .015) { g.position.y = 0; clearInterval(rise); }
    }, 16);
  }, i * 160);
});

// ============================================
//  Caméra orbitale
// ============================================
let drag=false, lx=0, ly=0, theta=0.78, phi=0.82, radius=26;
const target = new THREE.Vector3(7, 0, 7);

function updateCam() {
  camera.position.set(
    target.x + radius*Math.sin(phi)*Math.sin(theta),
    target.y + radius*Math.cos(phi),
    target.z + radius*Math.sin(phi)*Math.cos(theta)
  );
  camera.lookAt(target);
}
renderer.domElement.addEventListener('mousedown', e=>{drag=true;lx=e.clientX;ly=e.clientY;});
window.addEventListener('mouseup', ()=>drag=false);
window.addEventListener('mousemove', e=>{
  if(!drag)return;
  theta-=(e.clientX-lx)*.005; phi=Math.max(.18,Math.min(1.3,phi-(e.clientY-ly)*.005));
  lx=e.clientX;ly=e.clientY; updateCam();
});
renderer.domElement.addEventListener('wheel', e=>{radius=Math.max(7,Math.min(60,radius+e.deltaY*.04));updateCam();},{passive:true});
renderer.domElement.addEventListener('touchstart',e=>{drag=true;lx=e.touches[0].clientX;ly=e.touches[0].clientY;});
window.addEventListener('touchend',()=>drag=false);
window.addEventListener('touchmove',e=>{if(!drag)return;theta-=(e.touches[0].clientX-lx)*.005;phi=Math.max(.18,Math.min(1.3,phi-(e.touches[0].clientY-ly)*.005));lx=e.touches[0].clientX;ly=e.touches[0].clientY;updateCam();});
updateCam();

// Tooltip raycaster
const raycaster=new THREE.Raycaster(), mouse=new THREE.Vector2();
const tooltip=document.getElementById('tooltip');
window.addEventListener('mousemove', e=>{
  mouse.x=(e.clientX/W)*2-1; mouse.y=-(e.clientY/H)*2+1;
  tooltip.style.left=(e.clientX+15)+'px'; tooltip.style.top=(e.clientY-10)+'px';
});

// Boucle
const clock = new THREE.Clock();
function animate() {
  requestAnimationFrame(animate);
  const dt = clock.getDelta();
  clouds.forEach((c,i)=>{ c.position.x+=.018*(1+i*.08); if(c.position.x>45) c.position.x=-45; });

  raycaster.setFromCamera(mouse, camera);
  const meshes=[];
  buildingGroups.forEach(g=>g.traverse(c=>{if(c.isMesh)meshes.push(c);}));
  const hits=raycaster.intersectObjects(meshes);
  if(hits.length){
    let o=hits[0].object; while(o.parent&&!o.userData.label)o=o.parent;
    if(o.userData.label){tooltip.textContent=o.userData.label;tooltip.classList.add('visible');}
  } else { tooltip.classList.remove('visible'); }

  renderer.render(scene, camera);
}
animate();

setTimeout(()=>document.getElementById('toast').classList.add('hidden'), 4500);
window.addEventListener('resize',()=>{camera.aspect=window.innerWidth/window.innerHeight;camera.updateProjectionMatrix();renderer.setSize(window.innerWidth,window.innerHeight);});
</script>
</body>
</html>
