# 🚗 Location de voitures

![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![PHP Lint](https://github.com/HAMIDSADIKI01/location-voiture/actions/workflows/php-lint.yml/badge.svg)

Application web de location de voitures en **PHP / MySQL** : le client choisit ses dates, voit les voitures disponibles, réserve, puis reçoit une **confirmation par email (Gmail / PHPMailer)** et par **SMS (Twilio)**.

## ✨ Fonctionnalités

- Recherche de disponibilité par dates (validation côté client et serveur)
- Affichage du nombre de voitures disponibles, du nombre de jours et du prix total
- Réservation avec validation du nom, de l'email, du téléphone et des dates
- Protection contre la double réservation : transaction SQL + verrou `FOR UPDATE`
- Emails de confirmation (client + copie optionnelle à l'admin)
- SMS récapitulatif (numéros marocains normalisés en `+212…`)
- Sécurité : requêtes préparées PDO, échappement HTML, jeton CSRF, cookie de session `HttpOnly` + `SameSite=Lax`
- Une panne d'email ou de SMS n'annule pas la réservation

## 🧰 Technologies

PHP 8+ · MySQL / MariaDB · PDO · Apache (XAMPP) · PHPMailer · Twilio API

## 📁 Structure

```
location-voiture/
├── index.php            # Recherche + liste des voitures disponibles
├── reserver.php         # Formulaire, enregistrement, email + SMS
├── config.php           # Connexion PDO, session, CSRF, fonctions utilitaires
├── env.php              # Chargeur du fichier .env
├── database.sql         # Base, tables et données de départ
├── .env.example         # Modèle de configuration (à copier en .env)
├── .htaccess            # Bloque l'accès web au .env
├── images/voitures/     # Photos : P1.jpeg, P2.jpeg… (P + id de la voiture)
└── .github/workflows/   # Vérification de syntaxe PHP (CI)
```

`mailer.php` (PHPMailer, fonction `envoyerEmail()`) est optionnel et non versionné : sans lui, aucun email n'est envoyé.

## 🚀 Installation

**Prérequis** : XAMPP (PHP 8+, Apache, MySQL/MariaDB), extension PHP `curl` activée pour les SMS, Git.

```bash
# 1. Cloner dans le dossier web de XAMPP
cd C:\xampp\htdocs
git clone https://github.com/HAMIDSADIKI01/location-voiture.git voiture
cd voiture

# 2. Créer le fichier de configuration
copy .env.example .env
```

3. Démarrer **Apache** et **MySQL** dans XAMPP.
4. phpMyAdmin → **Importer** → `database.sql`.
5. Ajouter les photos dans `images/voitures/` (`P1.jpeg`, `P2.jpeg`…).
6. Remplir `.env` (voir ci-dessous), puis ouvrir <http://localhost/voiture/>.

## ⚙️ Configuration (`.env`)

| Variable | Description |
|---|---|
| `APP_DEBUG` | `true` en local, `false` en production |
| `DB_HOST`, `DB_PORTS`, `DB_NAME`, `DB_USER`, `DB_PASS` | Connexion MySQL (`DB_PORTS=3308,3306` essaie les ports dans l'ordre) |
| `ADMIN_PASSWORD` | Mot de passe de la page admin |
| `MAIL_SOCIETE`, `MAIL_ADMIN` | Nom affiché dans les emails, copie optionnelle à l'admin |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` | Gmail avec **mot de passe d'application** |
| `SMS_ACTIF`, `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` | Compte Twilio |

> ⚠️ Le fichier `.env` contient des secrets : il est dans `.gitignore` et ne doit **jamais** être publié.
> Le mot de passe d'application Gmail se crée dans *Compte Google → Sécurité → Validation en deux étapes → Mots de passe des applications*.

## 🗄️ Base de données

- **`voitures`** : marque, modèle, prix/jour, places, boîte, carburant, `quantite` (nombre d'exemplaires du modèle)
- **`reservations`** : client, dates, total, `statut` (`confirmee` → `sortie` → `retournee` / `annulee`)

Disponibilité d'un modèle sur une période = `quantite` − réservations actives (`confirmee`, `sortie`) qui chevauchent la période.

Voitures de départ : Dacia Logan (250 MAD, stock 5), Renault Clio 5 (300 MAD, stock 3), Peugeot 208 (320 MAD, stock 2).

> Si vous aviez une ancienne version avec des triggers de stock, `database.sql` les supprime. Remettez ensuite `quantite` à sa valeur d'origine, par exemple :
> `UPDATE voitures SET quantite = 5 WHERE marque = 'Dacia' AND modele = 'Logan';`

## 🔒 Passage en production

- [ ] `APP_DEBUG=false`
- [ ] Changer `ADMIN_PASSWORD`
- [ ] Utilisateur MySQL dédié (pas de `root` sans mot de passe)
- [ ] Vrais identifiants Gmail / Twilio, ou `SMS_ACTIF=false`
- [ ] HTTPS + `'secure' => true` dans les options du cookie de session
- [ ] Vérifier que `/.env` renvoie bien une erreur 403

## 🗺️ Pistes d'amélioration

- Page d'administration (`admin.php`) : gestion des réservations et du parc
- Intégration de PHPMailer via Composer
- Tests automatisés et déploiement continu

## 🤝 Contribuer

1. Forker le dépôt, puis créer une branche : `git checkout -b feature/ma-fonctionnalite`
2. Commits clairs (`feat:`, `fix:`, `docs:`…)
3. Ouvrir une Pull Request

## 📜 Licence

Distribué sous licence [MIT](LICENSE).
