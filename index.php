<?php
// ============================================
//  index.php — Classement + inscription rapide
// ============================================
require_once 'config.php';
$pdo = getPDO();

// Classement top 10
$top = $pdo->query("SELECT * FROM leaderboard LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>City Builder — Classement</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { background:#1a1a2e; color:#eee; font-family: 'Segoe UI', sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; padding: 2rem; }
  .container { max-width: 700px; width: 100%; }
  h1 { font-size: 2rem; font-weight: 300; letter-spacing: 0.1em; text-align:center; margin-bottom: 0.4rem; color: #7ec8e3; }
  .sub { text-align:center; color:#666; font-size:14px; margin-bottom:2rem; }
  table { width:100%; border-collapse: collapse; }
  th { text-align:left; font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.08em; padding: 8px 12px; border-bottom: 1px solid #2a2a4a; }
  td { padding: 12px; border-bottom: 1px solid #1e1e3a; font-size: 14px; }
  tr:hover td { background: rgba(126,200,227,0.05); }
  td a { color: #7ec8e3; text-decoration: none; }
  td a:hover { text-decoration: underline; }
  .rank { color: #555; font-size: 12px; width: 30px; }
  .pop { color: #7ec8e3; font-weight: 600; }
  .cta { margin-top: 2rem; text-align:center; }
  .cta a { display:inline-block; padding: 10px 28px; border: 1px solid rgba(126,200,227,0.4); border-radius:6px; color:#7ec8e3; text-decoration:none; font-size:14px; }
  .cta a:hover { background: rgba(126,200,227,0.1); }
</style>
</head>
<body>
<div class="container">
  <h1>🏙 City Builder</h1>
  <p class="sub">Partage ton lien. Chaque visiteur fait grandir ta ville.</p>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Ville</th>
        <th>Propriétaire</th>
        <th>Visites</th>
        <th>Habitants</th>
        <th>Bâtiments</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($top as $i => $row): ?>
      <tr>
        <td class="rank"><?= $i+1 ?></td>
        <td><a href="ville.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['city_name']) ?></a></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= number_format($row['visits']) ?></td>
        <td class="pop"><?= number_format($row['population']) ?></td>
        <td><?= $row['building_count'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($top)): ?>
      <tr><td colspan="6" style="color:#555;text-align:center;padding:2rem">Aucune ville pour l'instant.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <div class="cta">
    <a href="register.php">Créer ma ville →</a>
  </div>
</div>
</body>
</html>
