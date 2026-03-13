window.addEventListener('load', function() {

// ============================================
//  Scène — ambiance jour ensoleillé
// ============================================
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x87CEEB);
scene.fog = new THREE.FogExp2(0xb8ddf0, 0.008);

const W = window.innerWidth, H = window.innerHeight;
const camera = new THREE.PerspectiveCamera(60, W/H, 0.1, 300);
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
const ground = new THREE.Mesh(new THREE.PlaneGeometry(300, 300), new THREE.MeshLambertMaterial({ color:0x5aaa32 }));
ground.rotation.x = -Math.PI/2; ground.receiveShadow = true; scene.add(ground);

// Routes
function road(w, h, x, z) {
  const m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshLambertMaterial({ color:0xc8b89a }));
  m.rotation.x = -Math.PI/2; m.position.set(x, 0.01, z); m.receiveShadow = true; scene.add(m);
}
for (let i = 0; i <= 8; i++) { road(200, 1.6, 0, i*10.0); road(1.6, 200, i*10.0, 0); }

// Trottoirs
for (let i = 0; i <= 8; i++) {
  for (let j = 0; j <= 8; j++) {
    const curb = new THREE.Mesh(new THREE.PlaneGeometry(1.0, 1.0), new THREE.MeshLambertMaterial({ color:0xddccbb }));
    curb.rotation.x = -Math.PI/2; curb.position.set(i*10.0, 0.015, j*10.0); scene.add(curb);
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
[
  [-6,-6],[-6,5],[-6,15],[-6,25],[-6,35],[-6,45],[-6,55],[-6,65],[-6,75],[-6,85],
  [5,-6],[15,-6],[25,-6],[35,-6],[45,-6],[55,-6],[65,-6],[75,-6],[85,-6],
  [5,90],[15,90],[25,90],[35,90],[45,90],[55,90],[65,90],[75,90],[85,90],
  [90,-6],[90,5],[90,15],[90,25],[90,35],[90,45],[90,55],[90,65],[90,75],[90,85],
  [-14,20],[-14,45],[97,20],[97,45]
].forEach(([x,z]) => tree(x,z));

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
[[0,10],[0,20],[0,30],[0,40],[0,50],[0,60],[0,70],[10,0],[20,0],[30,0],[40,0],[50,0],[60,0],[70,0]].forEach(([x,z]) => lamp(x,z));
[[1,1],[11,1],[21,1],[31,1],[1,11],[1,21]].forEach(([x,z]) => bench(x,z));

// ============================================
//  Helpers bâtiments
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

// ============================================
//  Bâtiments — sans gaps entre les blocs
// ============================================
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

  // IMMEUBLE : un seul bloc continu, pas de sections empilées
  flat(g) {
    // Socle
    const soc=box3(2.2,.3,2.2,0xccbbaa); soc.position.y=.15; g.add(soc);
    // Corps unique — un seul mesh pour éviter tout gap
    const main=box3(2.,6.,2.,0xf0e0c0); main.position.y=3.3; g.add(main);
    wins(main,2.,6.,2.,7,2,0xaad4f5);
    // Balcons intégrés (légèrement en avant, pas de gap)
    for(let f=0;f<4;f++){
      const bal=box3(2.1,.08,.42,0xccbbaa); bal.position.set(0,.7+f*1.45,1.21); g.add(bal);
      const rail=box3(2.1,.18,.03,0xbbaa99); rail.position.set(0,.86+f*1.45,1.42); g.add(rail);
    }
    // Couronnement toit
    const roof=box3(2.2,.2,2.2,0xbbaa99); roof.position.y=6.4; g.add(roof);
    const parapet=box3(2.2,.3,2.2,0xccbbaa); parapet.position.y=6.65; g.add(parapet);
    // Antenne
    const ant=box3(.04,.8,.04,0x888); ant.position.set(.5,7.1,.5); g.add(ant);
    // Porte
    const door=box3(.36,.62,.04,0x5a4a3a); door.position.set(0,.31,1.01); g.add(door);
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
    const steps2=box3(3.8,.15,4.0,0xe8d8aa); steps2.position.set(0,.0,0); g.add(steps2);
    const steps=box3(3.2,.15,3.4,0xeeddbb); steps.position.set(0,.15,0); g.add(steps);
    const base=box3(3.6,2.8,3.2,0xf8ecc8); base.position.y=1.55; g.add(base);
    wins(base,3.6,2.8,3.2,2,3,0xfff5aa);
    [-1.3,-.42,.42,1.3].forEach(ox=>{
      const col=box3(.2,2.8,.2,0xfffaee); col.position.set(ox,1.55,1.7); g.add(col);
      const cap=box3(.32,.12,.32,0xeedd99); cap.position.set(ox,3.01,1.7); g.add(cap);
    });
    // Fronton plat — triangulaire avec 3 boites
    const pedBase=box3(3.9,.1,.1,0xeedd99); pedBase.position.set(0,3.06,1.7); g.add(pedBase);
    const pedL=box3(.08,.9,.08,0xeedd99); pedL.position.set(-1.85,3.52,1.7); pedL.rotation.z=.44; g.add(pedL);
    const pedR=box3(.08,.9,.08,0xeedd99); pedR.position.set( 1.85,3.52,1.7); pedR.rotation.z=-.44; g.add(pedR);
    // Attique continu avec le corps
    const attic=box3(3.6,.45,3.2,0xf0ddb0); attic.position.y=3.18; g.add(attic);
    // Tambour + dôme collés
    const drum=new THREE.Mesh(new THREE.CylinderGeometry(.72,.72,.5,16),new THREE.MeshLambertMaterial({color:0xeedd99}));
    drum.position.y=3.65; drum.castShadow=true; g.add(drum);
    const dome=new THREE.Mesh(new THREE.SphereGeometry(.72,16,12,0,Math.PI*2,0,Math.PI/2),new THREE.MeshLambertMaterial({color:0x4ecdc4}));
    dome.position.y=4.15; dome.castShadow=true; g.add(dome);
    const lantern=box3(.22,.28,.22,0xeedd99); lantern.position.y=4.87; g.add(lantern);
    const pole=box3(.04,.9,.04,0x888); pole.position.set(0,5.3,0); g.add(pole);
    const flag=box3(.44,.28,.03,0xff3333); flag.position.set(.24,5.6,0); g.add(flag);
    const door=box3(.5,.75,.05,0x8B4513); door.position.set(0,.37,1.61); g.add(door);
  },

  // TOUR : un seul corps continu qui s'affine par setScale, pas de sections séparées
  tower(g) {
    // Podium large
    const pod=box3(2.6,.6,2.6,0xc0b8b0); pod.position.y=.3; g.add(pod);
    // Corps principal unique — un seul mesh haut
    const body=box3(1.8,11.,1.8,0xe0d8d0); body.position.y=6.1; g.add(body);
    wins(body,1.8,11.,1.8,10,2,0xaad4f5);
    // Bandes horizontales décoratives (pas de gap, juste des reliefs)
    [2.1, 4.6, 7.1, 9.6].forEach(y=>{
      const band=box3(1.9,.1,1.9,0xc8c0b8); band.position.y=y; g.add(band);
    });
    // Couronnement
    const crown=box3(2.0,.25,2.0,0xb8b0a8); crown.position.y=11.72; g.add(crown);
    const top=box3(1.4,.4,1.4,0xaaa8a0); top.position.y=12.1; g.add(top);
    // Antenne + lumière
    const ant=box3(.05,2.2,.05,0x999); ant.position.y=13.4; g.add(ant);
    const light=new THREE.Mesh(new THREE.SphereGeometry(.1,8,8),new THREE.MeshLambertMaterial({color:0xff2222,emissive:new THREE.Color(0xff0000),emissiveIntensity:1.5}));
    light.position.y=14.55; g.add(light);
    // Porte
    const door=box3(.4,.7,.04,0x5a4a3a); door.position.set(0,.35,.91); g.add(door);
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

// ============================================
//  Placement des bâtiments
// ============================================
const buildingGroups = [];
const buildingBoxes = [];
const LABELS = {tent:'Tente',house:'Maison',shop:'Épicerie',flat:'Immeuble',factory:'Usine',town_hall:'Mairie',tower:'Tour',cathedral:'Cathédrale'};

const SPACING = 10.0; // taille d'une case (route + bâtiment)

BUILDINGS_DATA.forEach((b) => {
  const g = new THREE.Group();
  if (BUILDERS[b.type]) BUILDERS[b.type](g);
  g.scale.setScalar(2.2);
  g.position.set(b.pos_x * SPACING + 5, 0, b.pos_z * SPACING + 5); // centré dans sa case
  g.userData.label = LABELS[b.type] || b.type;
  scene.add(g);
  buildingGroups.push(g);

  // Boîtes de collision au bon espacement
  const sizes = {
    tent:[3.,3.,3.3], house:[4.4,4.4,4.8], shop:[5.3,4.8,4.4],
    flat:[4.8,4.8,15.4], factory:[7.5,6.6,6.6], town_hall:[8.8,7.9,12.1],
    tower:[6.2,6.2,33.], cathedral:[7.5,14.,24.2]
  };
  const sz = sizes[b.type] || [4,4,5];
  const cx = b.pos_x * SPACING + 5, cz = b.pos_z * SPACING + 5;
  buildingBoxes.push(new THREE.Box3(
    new THREE.Vector3(cx - sz[0]/2, 0,      cz - sz[1]/2),
    new THREE.Vector3(cx + sz[0]/2, sz[2],  cz + sz[1]/2)
  ));
});

// ============================================
//  MODE ORBITAL
// ============================================
let drag=false, lx=0, ly=0, theta=0.78, phi=0.82, radius=26;
const orbitTarget = new THREE.Vector3(40, 0, 40);

function updateOrbit() {
  camera.position.set(
    orbitTarget.x + radius*Math.sin(phi)*Math.sin(theta),
    orbitTarget.y + radius*Math.cos(phi),
    orbitTarget.z + radius*Math.sin(phi)*Math.cos(theta)
  );
  camera.lookAt(orbitTarget);
}

// ============================================
//  MODE FPS — physique, bob, collisions
// ============================================
let fpsMode = false;
let fpsYaw = 0, fpsPitch = 0;
const fpsPos    = new THREE.Vector3(7, 1.7, 18);
const fpsVel    = new THREE.Vector3(0, 0, 0);   // vélocité (gravité)
const GRAVITY   = -18;
const JUMP_V    = 6.5;
const EYE_H     = 1.7;  // hauteur des yeux
let onGround    = false;
let bobTime     = 0;

const keys = {};
window.addEventListener('keydown', e => { keys[e.code] = true; });
window.addEventListener('keyup',   e => { keys[e.code] = false; });

// Pointer lock
renderer.domElement.addEventListener('click', () => {
  if (fpsMode) renderer.domElement.requestPointerLock();
});
document.addEventListener('mousemove', e => {
  if (!fpsMode || !document.pointerLockElement) return;
  fpsYaw   -= e.movementX * 0.002;
  fpsPitch  = Math.max(-1.1, Math.min(1.1, fpsPitch - e.movementY * 0.002));
});

// Touche F pour basculer
window.addEventListener('keydown', e => {
  if (e.code !== 'KeyF') return;
  fpsMode = !fpsMode;
  const hint   = document.getElementById('hint');
  const fpshint = document.getElementById('fps-hint');
  if (fpsMode) {
    fpsPos.set(5, EYE_H, -5);  // sur la route, face à la ville
    fpsVel.set(0,0,0);
    fpsYaw = 0; fpsPitch = 0;  // face à la ville
    renderer.domElement.requestPointerLock();
    camera.fov = 75; camera.updateProjectionMatrix();
    hint.style.display = 'none';
    if (fpshint) fpshint.style.display = 'block';
  } else {
    document.exitPointerLock();
    camera.fov = 60; camera.updateProjectionMatrix();
    updateOrbit();
    hint.style.display = 'block';
    if (fpshint) fpshint.style.display = 'none';
  }
});

// Saut à la barre espace
window.addEventListener('keydown', e => {
  if (e.code === 'Space' && fpsMode && onGround) fpsVel.y = JUMP_V;
});

function updateFPS(dt) {
  const running = keys['ShiftLeft'] || keys['ShiftRight'];
  const speed   = running ? 7.5 : 4.0;  // unités/sec (plus naturel qu'un ratio fixe)

  // Directions horizontales (ignore composante Y du regard)
  const forward = new THREE.Vector3(-Math.sin(fpsYaw), 0, -Math.cos(fpsYaw));
  const right   = new THREE.Vector3( Math.cos(fpsYaw), 0, -Math.sin(fpsYaw));

  const move = new THREE.Vector3();
  if (keys['KeyW'] || keys['ArrowUp'])    move.addScaledVector(forward,  1);
  if (keys['KeyS'] || keys['ArrowDown'])  move.addScaledVector(forward, -1);
  if (keys['KeyA'] || keys['ArrowLeft'])  move.addScaledVector(right,   -1);
  if (keys['KeyD'] || keys['ArrowRight']) move.addScaledVector(right,    1);
  if (move.lengthSq() > 0) move.normalize().multiplyScalar(speed * dt);

  // --- Gravité ---
  fpsVel.y += GRAVITY * dt;
  const nextPos = fpsPos.clone().add(move).add(new THREE.Vector3(0, fpsVel.y * dt, 0));

  // --- Sol ---
  if (nextPos.y <= EYE_H) {
    nextPos.y  = EYE_H;
    fpsVel.y   = 0;
    onGround   = true;
  } else {
    onGround = false;
  }

  // --- Collisions bâtiments (AABB simple, rayon joueur = 0.4) ---
  const PLAYER_R = 0.4;
  const playerBox = new THREE.Box3(
    new THREE.Vector3(nextPos.x - PLAYER_R, nextPos.y - EYE_H,        nextPos.z - PLAYER_R),
    new THREE.Vector3(nextPos.x + PLAYER_R, nextPos.y - EYE_H + 2.2,  nextPos.z + PLAYER_R)
  );

  let blocked = false;
  for (const bb of buildingBoxes) {
    if (playerBox.intersectsBox(bb)) { blocked = true; break; }
  }

  if (!blocked) {
    fpsPos.copy(nextPos);
  } else {
    // Essaie de glisser sur X seulement
    const slideX = fpsPos.clone().add(new THREE.Vector3(move.x, fpsVel.y * dt, 0));
    slideX.y = Math.max(slideX.y, EYE_H);
    const boxX = new THREE.Box3(
      new THREE.Vector3(slideX.x - PLAYER_R, slideX.y - EYE_H,       slideX.z - PLAYER_R),
      new THREE.Vector3(slideX.x + PLAYER_R, slideX.y - EYE_H + 2.2, slideX.z + PLAYER_R)
    );
    let blockedX = buildingBoxes.some(bb => boxX.intersectsBox(bb));
    if (!blockedX) { fpsPos.copy(slideX); fpsVel.y = slideX.y === EYE_H ? 0 : fpsVel.y; }
    else {
      // Essaie de glisser sur Z seulement
      const slideZ = fpsPos.clone().add(new THREE.Vector3(0, fpsVel.y * dt, move.z));
      slideZ.y = Math.max(slideZ.y, EYE_H);
      const boxZ = new THREE.Box3(
        new THREE.Vector3(slideZ.x - PLAYER_R, slideZ.y - EYE_H,       slideZ.z - PLAYER_R),
        new THREE.Vector3(slideZ.x + PLAYER_R, slideZ.y - EYE_H + 2.2, slideZ.z + PLAYER_R)
      );
      let blockedZ = buildingBoxes.some(bb => boxZ.intersectsBox(bb));
      if (!blockedZ) { fpsPos.copy(slideZ); fpsVel.y = slideZ.y === EYE_H ? 0 : fpsVel.y; }
      // Sinon on reste sur place
    }
  }

  // --- Bob de caméra ---
  const isMoving = move.lengthSq() > 0 && onGround;
  if (isMoving) bobTime += dt * (running ? 12 : 7);
  const bobY = isMoving ? Math.sin(bobTime) * 0.055 : 0;
  const bobX = isMoving ? Math.sin(bobTime * 0.5) * 0.025 : 0;

  // --- FOV dynamique (course) ---
  const targetFov = running && isMoving ? 88 : 75;
  camera.fov += (targetFov - camera.fov) * 0.12;
  camera.updateProjectionMatrix();

  // --- Applique à la caméra ---
  camera.position.set(fpsPos.x + bobX, fpsPos.y + bobY, fpsPos.z);
  camera.rotation.order = 'YXZ';
  camera.rotation.y = fpsYaw;
  camera.rotation.x = fpsPitch;
}

// ============================================
//  Contrôles orbital souris / touch
// ============================================
renderer.domElement.addEventListener('mousedown', e=>{if(!fpsMode){drag=true;lx=e.clientX;ly=e.clientY;}});
window.addEventListener('mouseup', ()=>drag=false);
window.addEventListener('mousemove', e=>{
  if(fpsMode||!drag)return;
  theta-=(e.clientX-lx)*.005; phi=Math.max(.18,Math.min(1.3,phi-(e.clientY-ly)*.005));
  lx=e.clientX;ly=e.clientY; updateOrbit();
});
renderer.domElement.addEventListener('wheel', e=>{if(fpsMode)return;radius=Math.max(7,Math.min(60,radius+e.deltaY*.04));updateOrbit();},{passive:true});
renderer.domElement.addEventListener('touchstart',e=>{if(!fpsMode){drag=true;lx=e.touches[0].clientX;ly=e.touches[0].clientY;}});
window.addEventListener('touchend',()=>drag=false);
window.addEventListener('touchmove',e=>{if(fpsMode||!drag)return;theta-=(e.touches[0].clientX-lx)*.005;phi=Math.max(.18,Math.min(1.3,phi-(e.touches[0].clientY-ly)*.005));lx=e.touches[0].clientX;ly=e.touches[0].clientY;updateOrbit();});

// Tooltip raycaster (orbital uniquement)
const raycaster=new THREE.Raycaster(), mouse=new THREE.Vector2();
const tooltip=document.getElementById('tooltip');
window.addEventListener('mousemove', e=>{
  if(fpsMode)return;
  mouse.x=(e.clientX/W)*2-1; mouse.y=-(e.clientY/H)*2+1;
  tooltip.style.left=(e.clientX+15)+'px'; tooltip.style.top=(e.clientY-10)+'px';
});

updateOrbit();

// ============================================
//  Boucle de rendu
// ============================================
const clock = new THREE.Clock();
function animate() {
  requestAnimationFrame(animate);
  const dt = clock.getDelta();
  clouds.forEach((c,i)=>{ c.position.x+=.018*(1+i*.08); if(c.position.x>45) c.position.x=-45; });

  if (fpsMode) {
    updateFPS(dt);
    tooltip.classList.remove('visible');
  } else {
    raycaster.setFromCamera(mouse, camera);
    const meshes=[];
    buildingGroups.forEach(g=>g.traverse(c=>{if(c.isMesh)meshes.push(c);}));
    const hits=raycaster.intersectObjects(meshes);
    if(hits.length){
      let o=hits[0].object; while(o.parent&&!o.userData.label)o=o.parent;
      if(o.userData.label){tooltip.textContent=o.userData.label;tooltip.classList.add('visible');}
    } else { tooltip.classList.remove('visible'); }
  }

  renderer.render(scene, camera);
}
animate();

setTimeout(()=>{ const t=document.getElementById('toast'); if(t)t.classList.add('hidden'); }, 4500);
window.addEventListener('resize',()=>{camera.aspect=window.innerWidth/window.innerHeight;camera.updateProjectionMatrix();renderer.setSize(window.innerWidth,window.innerHeight);});

});