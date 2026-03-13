# City Builder 3D — Guide d'installation

## Prérequis
- XAMPP (Apache + MySQL + PHP 8.x)

## Installation en 3 étapes

### 1. Base de données
- Ouvre phpMyAdmin → http://localhost/phpmyadmin
- Crée une base `city_builder`
- Importe le fichier `schema.sql`

### 2. Fichiers
Copie tout le dossier dans :
```
C:\xampp\htdocs\city_builder\
```

### 3. Vérifie config.php
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'city_builder');
define('DB_USER', 'root');
define('DB_PASS', '');   // vide par défaut sur XAMPP
```

---

## URLs
| Page | URL |
|------|-----|
| Classement | http://localhost/city_builder/ |
| Voir une ville | http://localhost/city_builder/ville.php?id=1 |
| Inscription | http://localhost/city_builder/register.php |

---

## Bâtiments et seuils de déblocage
| Bâtiment | Visites requises | Bonus habitants |
|----------|-----------------|-----------------|
| Tente | 1 | +2 |
| Maison | 3 | +5 |
| Épicerie | 6 | 0 |
| Immeuble | 10 | +20 |
| Usine | 15 | +10 |
| Mairie | 20 | 0 |
| Tour | 30 | +80 |
| Cathédrale | 50 | 0 |

---

## Fichiers
```
city_builder/
├── config.php      ← Connexion BDD
├── schema.sql      ← Importer en premier
├── index.php       ← Classement
├── ville.php       ← Vue 3D de la ville (Three.js)
└── register.php    ← À créer : inscription utilisateur
```

## Prochaine étape : register.php
C'est la page d'inscription à coder. Elle doit :
1. Formulaire : username, email, mot de passe
2. `password_hash()` pour hasher le mdp
3. INSERT dans `users` + INSERT dans `cities`
4. Redirect vers `ville.php?id=...`

Tu veux que je la génère aussi ?
