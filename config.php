<?php
// ====== Chargement des variables d'environnement (.env) ======
require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/.env');

// ====== Mode debug : APP_DEBUG dans .env (false en production) ======
$debug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

// Fuseau horaire
date_default_timezone_set('Africa/Casablanca');

// Cookie de session plus sûr
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ====== Paramètres de connexion (depuis .env) ======
$host     = env('DB_HOST', '127.0.0.1');
$ports    = array_map('intval', explode(',', env('DB_PORTS', '3306'))); // ex : 3308,3306
$dbname   = env('DB_NAME', 'voiture');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');

$pdo = null;
$derniereErreur = '';
$erreursPorts = [];
foreach ($ports as $port) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,

            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        break;
    } catch (PDOException $e) {
        $derniereErreur = $e->getMessage();
        $erreursPorts[$port] = $derniereErreur;
    }
}
if (!$pdo && !defined('DIAGNOSTIC')) {
    error_log($derniereErreur);
    die($debug ? "Erreur de connexion : " . htmlspecialchars($derniereErreur, ENT_QUOTES, 'UTF-8')
                 . "<br>Vérifiez que MySQL est démarré dans XAMPP et que la base « $dbname » existe (importez database.sql dans phpMyAdmin)."
               : "Service momentanément indisponible.");
}

// ====== Mot de passe de la page admin.php (défini dans .env) ======
$adminPassword = env('ADMIN_PASSWORD');

// ====== Paramètres EMAIL (envoi par Gmail via PHPMailer) ======
$mailConfig = [
    'societe'   => env('MAIL_SOCIETE', 'Location de voitures'),
    'admin'     => env('MAIL_ADMIN', ''),            // '' = pas de copie à l'admin
    'smtp_host' => env('SMTP_HOST', 'smtp.gmail.com'),
    'smtp_port' => (int) env('SMTP_PORT', 587),
    'smtp_user' => env('SMTP_USER', ''),
    'smtp_pass' => env('SMTP_PASS', ''),             // mot de passe d'application Gmail
];

// ====== Paramètres SMS (Twilio) ======
$smsConfig = [
    'actif' => filter_var(env('SMS_ACTIF', 'false'), FILTER_VALIDATE_BOOLEAN),
    'sid'   => env('TWILIO_SID', ''),
    'token' => env('TWILIO_TOKEN', ''),
    'from'  => env('TWILIO_FROM', ''),
];

// ====== Fonctions utilitaires ======

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function dateValide($d) {
    if (!is_string($d)) return false;
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

// Nombre de jours (début et fin inclus)
function nbJours($debut, $fin) {
    return (new DateTime($debut))->diff(new DateTime($fin))->days + 1;
}

// Nombre de voitures encore disponibles (stock - réservations qui chevauchent [$debut, $fin])
function nbDisponibles(PDO $pdo, $voitureId, $debut, $fin) {
    $st = $pdo->prepare("SELECT quantite FROM voitures WHERE id = ?");
    $st->execute([$voitureId]);
    $stock = (int)$st->fetchColumn();

    $st = $pdo->prepare(

        "SELECT COUNT(*) FROM reservations
         WHERE voiture_id = ? AND date_debut <= ? AND date_fin >= ?
           AND statut IN ('confirmee','sortie')"
    );
    $st->execute([$voitureId, $fin, $debut]);
    return max(0, $stock - (int)$st->fetchColumn());
}

// Vrai s'il reste au moins une voiture de ce modèle sur la période
function estLibre(PDO $pdo, $voitureId, $debut, $fin) {
    return nbDisponibles($pdo, $voitureId, $debut, $fin) > 0;
}

// ====== SMS : normalisation du numéro (Maroc → format international) ======
// 06 12 34 56 78 / 0612345678 / 00212612345678 / 212612345678  →  +212612345678
function normaliserTel(string $tel): string {
    $t = preg_replace('/[^0-9+]/', '', $tel);
    if ($t === '') return '';
    if (strpos($t, '00') === 0) $t = '+' . substr($t, 2);
    if ($t[0] === '+') return $t;
    if (preg_match('/^0[5-7][0-9]{8}$/', $t)) return '+212' . substr($t, 1);
    if (preg_match('/^212[0-9]{9}$/', $t))    return '+' . $t;
    return '+' . $t;
}

// ====== SMS : envoi via Twilio. Retourne true si accepté par le fournisseur ======
function envoyerSms(string $to, string $message): bool {
    global $smsConfig;
    if (empty($smsConfig['actif']) || $to === '') return false;
    if (!function_exists('curl_init')) {
        error_log('SMS : extension cURL non activée (php.ini → extension=curl).');
        return false;

    }

    $url  = 'https://api.twilio.com/2010-04-01/Accounts/' . $smsConfig['sid'] . '/Messages.json';
    $data = ['To' => $to, 'Body' => $message];
    if (strpos($smsConfig['from'], 'MG') === 0) $data['MessagingServiceSid'] = $smsConfig['from'];
    else                                         $data['From'] = $smsConfig['from'];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_USERPWD        => $smsConfig['sid'] . ':' . $smsConfig['token'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $rep  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($rep === false) error_log('SMS curl: ' . curl_error($ch));
    curl_close($ch);

    if ($code !== 201) error_log("SMS erreur HTTP $code : $rep");
    return $code === 201;
}

// ====== Envoi d'emails : fonction envoyerEmail() définie dans mailer.php ======
if (file_exists(__DIR__ . '/mailer.php')) {
    require_once __DIR__ . '/mailer.php';
} else {
    // mailer.php absent : le site fonctionne, mais aucun email n'est envoyé
    function envoyerEmail(string $to, string $sujet, string $html, ?string $replyTo = null): bool { return false; }
}


// ====== Jeton CSRF ======
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
