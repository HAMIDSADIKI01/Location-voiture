# 🚗 Location de voitures

![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![PHP Lint](https://github.com/HAMIDSADIKI01/Location-voiture/actions/workflows/php-lint.yml/badge.svg)

Application web de location de voitures en **PHP / MySQL** : le client choisit ses dates, voit les voitures disponibles, reserve, puis recoit une **confirmation par email** et par **SMS (Twilio)**.

## ✨ Fonctionnalites

- Recherche de disponibilite par dates (validation cote client et serveur)
- Nombre de voitures disponibles, nombre de jours et prix total
- Reservation avec validation du nom, de l'email, du telephone et des dates
- Protection contre la double reservation : transaction SQL + verrou `FOR UPDATE`
- Emails de confirmation et SMS recapitulatif (numeros marocains en `+212…`)
- Securite : requetes preparees PDO, echappement HTML, jeton CSRF, cookie de session `HttpOnly` + `SameSite=Lax`
- Une panne d'email ou de SMS n'annule pas la reservation

## 🧰 Technologies

PHP 8+ · MySQL / MariaDB · PDO · Docker · GitHub Actions · Twilio API

## 🚀 Demarrage rapide

### Option 1 : Docker

```bash
git clone https://github.com/HAMIDSADIKI01/Location-voiture.git
cd Location-voiture
docker compose up --build
```
Ouvrez <http://localhost:8000>. La base est creee automatiquement.

### Option 2 : sans Docker (PHP + MySQL installes)

```bash
copy .env.example .env          # puis remplir .env
mysql -u root < database.sql    # cree la base et les tables
php -S localhost:8000 router.php
```

## ⚙️ Configuration (`.env`)

| Variable | Description |
|---|---|
| `APP_DEBUG` | `true` en local, `false` en production |
| `DB_HOST`, `DB_PORTS`, `DB_NAME`, `DB_USER`, `DB_PASS` | Connexion MySQL |
| `ADMIN_PASSWORD` | Mot de passe administrateur |
| `MAIL_SOCIETE`, `MAIL_ADMIN` | Nom affiche dans les emails, copie optionnelle a l'admin |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` | Gmail avec mot de passe d'application |
| `SMS_ACTIF`, `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` | Compte Twilio |

> ⚠️ Le fichier `.env` contient des secrets : il est dans `.gitignore` et ne doit **jamais** etre publie.

## 📁 Structure

## 🗄️ Base de donnees

- **`voitures`** : marque, modele, prix/jour, places, boite, carburant, `quantite`
- **`reservations`** : client, dates, total, `statut` (`confirmee` → `sortie` → `retournee` / `annulee`)

Disponibilite = `quantite` − reservations actives qui chevauchent la periode.

## 🔒 Passage en production

- [ ] `APP_DEBUG=false`
- [ ] Changer `ADMIN_PASSWORD`
- [ ] Utilisateur MySQL dedie (pas de `root`)
- [ ] HTTPS + `'secure' => true` pour le cookie de session
- [ ] Verifier que `/.env` renvoie une erreur 403

## 🗺️ Feuille de route

- [ ] Page d'administration (`admin.php`)
- [ ] PHPMailer via Composer
- [ ] Tests automatises

## 🤝 Contribuer

Voir [CONTRIBUTING.md](CONTRIBUTING.md). Historique des versions : [CHANGELOG.md](CHANGELOG.md).

## 📜 Licence

Distribue sous licence [MIT](LICENSE).