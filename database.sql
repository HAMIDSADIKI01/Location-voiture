-- ============================================================
-- database.sql : à importer dans phpMyAdmin (onglet "Importer")
-- Compatible MySQL ET MariaDB. Sans danger si la base existe déjà
-- (aucune donnée n'est supprimée).
--
-- Stock : "quantite" = nombre d'exemplaires du modèle (valeur fixe).
-- Les disponibilités sont calculées par dates côté PHP.
-- ============================================================
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS voiture CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE voiture;

-- Table des voitures (quantite = nombre d'exemplaires de ce modèle)
CREATE TABLE IF NOT EXISTS voitures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  marque VARCHAR(50) NOT NULL,
  modele VARCHAR(50) NOT NULL,
  prix_jour DECIMAL(8,2) NOT NULL,
  places TINYINT NOT NULL DEFAULT 5,
  boite ENUM('Manuelle','Automatique') NOT NULL DEFAULT 'Manuelle',
  carburant ENUM('Essence','Diesel','Hybride','Électrique') NOT NULL DEFAULT 'Diesel',
  image VARCHAR(255) DEFAULT NULL,
  quantite INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des réservations
CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  voiture_id INT NOT NULL,
  nom VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL,
  telephone VARCHAR(30) NOT NULL,
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  statut ENUM('confirmee','sortie','retournee','annulee') NOT NULL DEFAULT 'confirmee',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dispo (voiture_id, date_debut, date_fin),
  CONSTRAINT fk_resa_voiture FOREIGN KEY (voiture_id) REFERENCES voitures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Ajout des colonnes manquantes si vos anciennes tables en avaient moins
-- (méthode compatible MySQL et MariaDB)
-- ------------------------------------------------------------
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE voitures ADD COLUMN quantite INT NOT NULL DEFAULT 1', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'voitures' AND COLUMN_NAME = 'quantite');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE reservations ADD COLUMN statut ENUM(''confirmee'',''sortie'',''retournee'',''annulee'') NOT NULL DEFAULT ''confirmee''', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'statut');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE reservations ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'created_at');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- Voitures de départ : insérées seulement si la table est vide
-- ------------------------------------------------------------
INSERT INTO voitures (marque, modele, prix_jour, places, boite, carburant, quantite)
SELECT * FROM (
  SELECT 'Dacia' AS marque, 'Logan' AS modele, 250 AS prix_jour, 5 AS places, 'Manuelle' AS boite, 'Diesel' AS carburant, 5 AS quantite
  UNION ALL SELECT 'Renault', 'Clio 5', 300, 5, 'Manuelle',    'Essence', 3
  UNION ALL SELECT 'Peugeot', '208',    320, 5, 'Automatique', 'Essence', 2
) AS t
WHERE NOT EXISTS (SELECT 1 FROM voitures);

-- ------------------------------------------------------------
-- Nettoyage : anciens triggers de stock (supprimés)
-- La disponibilité est calculée par dates : quantite - réservations
-- actives qui chevauchent la période. Garder les triggers comptait
-- chaque réservation deux fois.
-- ------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_resa_avant_insert;
DROP TRIGGER IF EXISTS trg_resa_apres_insert;
DROP TRIGGER IF EXISTS trg_resa_apres_update;
DROP TRIGGER IF EXISTS trg_resa_apres_delete;

-- Pour changer un stock plus tard (exemple) :
-- UPDATE voitures SET quantite = 5 WHERE marque = 'Dacia' AND modele = 'Logan';
