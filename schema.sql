-- ============================================
--  CITY BUILDER - Schéma MySQL
--  À importer via phpMyAdmin ou la CLI MySQL
-- ============================================

CREATE DATABASE IF NOT EXISTS city_builder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE city_builder;

-- -----------------------------------------------
-- Table : users
-- Un compte = une ville
-- -----------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(32)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------
-- Table : cities
-- Chaque utilisateur possède une ville
-- -----------------------------------------------
CREATE TABLE cities (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL UNIQUE,
    name        VARCHAR(64)  NOT NULL DEFAULT 'Ma Ville',
    visits      INT UNSIGNED NOT NULL DEFAULT 0,
    population  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- Table : buildings
-- Bâtiments débloqués dans une ville
-- -----------------------------------------------
CREATE TABLE buildings (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city_id      INT UNSIGNED NOT NULL,
    type         VARCHAR(32)  NOT NULL,  -- 'tent', 'house', 'shop', 'tower', etc.
    pos_x        TINYINT      NOT NULL DEFAULT 0,  -- position sur la grille 3D
    pos_z        TINYINT      NOT NULL DEFAULT 0,
    unlocked_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE,
    INDEX idx_city (city_id)
) ENGINE=InnoDB;

-- -----------------------------------------------
-- Table : visits
-- Anti-spam : 1 visite par IP par jour et par ville
-- -----------------------------------------------
CREATE TABLE visits (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city_id      INT UNSIGNED NOT NULL,
    visitor_ip   VARCHAR(45)  NOT NULL,  -- IPv4 ou IPv6
    visited_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Empêche la même IP de voter deux fois le même jour pour la même ville
    UNIQUE KEY uq_daily_visit (city_id, visitor_ip, (DATE(visited_at))),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE,
    INDEX idx_city_date (city_id, visited_at)
) ENGINE=InnoDB;

-- -----------------------------------------------
-- Définition des bâtiments et leurs seuils
-- (table de référence, pas modifiée en jeu)
-- -----------------------------------------------
CREATE TABLE building_types (
    type         VARCHAR(32)  PRIMARY KEY,
    label        VARCHAR(64)  NOT NULL,
    visit_threshold INT UNSIGNED NOT NULL,  -- nb de visites pour débloquer
    population_bonus INT UNSIGNED NOT NULL DEFAULT 0,
    description  VARCHAR(255)
) ENGINE=InnoDB;

INSERT INTO building_types (type, label, visit_threshold, population_bonus, description) VALUES
('tent',       'Tente',       1,  2,  'Les premiers aventuriers campent ici.'),
('house',      'Maison',      3,  5,  'Une famille s\'installe.'),
('shop',       'Épicerie',    6,  0,  'Le commerce commence.'),
('flat',       'Immeuble',    10, 20, 'La ville grandit en hauteur.'),
('factory',    'Usine',       15, 10, 'L\'industrie arrive.'),
('town_hall',  'Mairie',      20, 0,  'La ville s\'organise.'),
('tower',      'Tour',        30, 80, 'Un symbole de modernité.'),
('cathedral',  'Cathédrale',  50, 0,  'Un chef-d\'œuvre architectural.');

-- -----------------------------------------------
-- Vue pratique : classement des villes
-- -----------------------------------------------
CREATE VIEW leaderboard AS
SELECT
    c.id,
    c.name        AS city_name,
    u.username,
    c.visits,
    c.population,
    COUNT(b.id)   AS building_count,
    c.updated_at
FROM cities c
JOIN users u        ON u.id = c.user_id
LEFT JOIN buildings b ON b.city_id = c.id
GROUP BY c.id, c.name, u.username, c.visits, c.population, c.updated_at
ORDER BY c.population DESC, c.visits DESC;
