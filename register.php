<?php
// ============================================
//  register.php — Inscription + création ville
// ============================================
require_once 'config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';
    $city_name = trim($_POST['city_name'] ?? 'Ma Ville');

    // --- Validation ---
    if (strlen($username) < 3 || strlen($username) > 32) {
        $errors[] = "Le pseudo doit faire entre 3 et 32 caractères.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email est invalide.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit faire au moins 6 caractères.";
    }
    if (strlen($city_name) < 2 || strlen($city_name) > 64) {
        $errors[] = "Le nom de ta ville doit faire entre 2 et 64 caractères.";
    }

    if (empty($errors)) {
        $pdo = getPDO();

        // Vérifie unicité username / email
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            $errors[] = "Ce pseudo ou cet email est déjà utilisé.";
        } else {
            // --- Insertion ---
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO users (username, password_hash, email) VALUES (?, ?, ?)")
                    ->execute([$username, password_hash($password, PASSWORD_BCRYPT), $email]);

                $user_id = (int)$pdo->lastInsertId();

                $pdo->prepare("INSERT INTO cities (user_id, name) VALUES (?, ?)")
                    ->execute([$user_id, $city_name]);

                $city_id = (int)$pdo->lastInsertId();

                $pdo->commit();

                // Redirige directement vers la ville
                header("Location: ville.php?id={$city_id}");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Erreur serveur, réessaie.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Créer ma ville — City Builder</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }

  body {
    background: #1a1a2e;
    color: #eee;
    font-family: 'Segoe UI', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
  }

  /* Fond animé */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
      radial-gradient(ellipse at 20% 50%, rgba(126,200,227,0.05) 0%, transparent 60%),
      radial-gradient(ellipse at 80% 20%, rgba(176,106,138,0.05) 0%, transparent 60%);
    pointer-events: none;
  }

  .card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 2.5rem;
    width: 100%;
    max-width: 440px;
  }

  .logo {
    text-align: center;
    margin-bottom: 2rem;
  }
  .logo h1 {
    font-size: 1.6rem;
    font-weight: 300;
    letter-spacing: 0.12em;
    color: #7ec8e3;
  }
  .logo p {
    font-size: 13px;
    color: #555;
    margin-top: 4px;
  }

  .form-group {
    margin-bottom: 1.2rem;
  }
  label {
    display: block;
    font-size: 12px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin-bottom: 6px;
  }
  input {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: #eee;
    font-size: 14px;
    padding: 10px 14px;
    outline: none;
    transition: border-color 0.2s;
  }
  input:focus {
    border-color: rgba(126,200,227,0.5);
    background: rgba(126,200,227,0.04);
  }
  input::placeholder { color: #444; }

  .separator {
    border: none;
    border-top: 1px solid rgba(255,255,255,0.06);
    margin: 1.5rem 0;
  }

  .separator-label {
    text-align: center;
    font-size: 11px;
    color: #444;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin: -0.6rem 0 1.2rem;
    position: relative;
  }
  .separator-label span {
    background: #1a1a2e;
    padding: 0 10px;
  }

  .errors {
    background: rgba(255,80,80,0.08);
    border: 1px solid rgba(255,80,80,0.2);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 1.2rem;
    font-size: 13px;
    color: #f88;
  }
  .errors ul { padding-left: 1rem; }
  .errors li { margin-bottom: 4px; }

  .btn {
    width: 100%;
    padding: 12px;
    background: rgba(126,200,227,0.1);
    border: 1px solid rgba(126,200,227,0.3);
    border-radius: 8px;
    color: #7ec8e3;
    font-size: 15px;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    margin-top: 0.5rem;
  }
  .btn:hover  { background: rgba(126,200,227,0.18); }
  .btn:active { transform: scale(0.98); }

  .login-link {
    text-align: center;
    margin-top: 1.2rem;
    font-size: 13px;
    color: #555;
  }
  .login-link a { color: #7ec8e3; text-decoration: none; }
  .login-link a:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="card">
  <div class="logo">
    <h1>🏙 CITY BUILDER</h1>
    <p>Crée ta ville. Partage. Regarde-la grandir.</p>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="errors">
    <ul>
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form method="POST" action="register.php">

    <div class="form-group">
      <label for="username">Pseudo</label>
      <input type="text" id="username" name="username"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
             placeholder="ex : SuperMaire" maxlength="32" required>
    </div>

    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             placeholder="toi@exemple.fr" required>
    </div>

    <div class="form-group">
      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password"
             placeholder="6 caractères minimum" required>
    </div>

    <hr class="separator">
    <div class="separator-label"><span>Ta ville</span></div>

    <div class="form-group">
      <label for="city_name">Nom de ta ville</label>
      <input type="text" id="city_name" name="city_name"
             value="<?= htmlspecialchars($_POST['city_name'] ?? '') ?>"
             placeholder="ex : Villebeau, Rochefort-les-Pins..." maxlength="64" required>
    </div>

    <button type="submit" class="btn">Fonder ma ville →</button>
  </form>

  <p class="login-link">
    Déjà un compte ? <a href="index.php">Voir le classement</a>
  </p>
</div>

</body>
</html>
