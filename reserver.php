<?php
require 'config.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM voitures WHERE id = ?");
$st->execute([$id]);
$voiture = $st->fetch();
if (!$voiture) { http_response_code(404); exit('Voiture introuvable.'); }

$debut = trim((string)($_POST['date_debut'] ?? $_GET['date_debut'] ?? ''));
$fin   = trim((string)($_POST['date_fin']   ?? $_GET['date_fin']   ?? ''));
$erreurs = [];
$succes = false;
$total = 0;
$nbJours = 0;
$reservationId = 0;
$emailClientOk = false;
$smsOk = false;
$nom = $email = $tel = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Requête invalide.');
    }

    $nom   = trim((string)($_POST['nom'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $tel   = trim((string)($_POST['telephone'] ?? ''));

    if ($nom === '' || mb_strlen($nom) > 100) $erreurs[] = "Nom obligatoire (100 caractères max).";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) $erreurs[] = "Email invalide.";

    if (!preg_match('/^[0-9 +().-]{8,20}$/', $tel)) $erreurs[] = "Téléphone invalide.";
    if (!dateValide($debut) || !dateValide($fin) || $debut < date('Y-m-d') || $fin < $debut) {
        $erreurs[] = "Dates invalides.";
    }

    if (!$erreurs) {
        // ====== 1) Enregistrement en base (transaction + verrou) ======
        try {
            $pdo->beginTransaction();

            $lock = $pdo->prepare("SELECT id FROM voitures WHERE id = ? FOR UPDATE");
            $lock->execute([$id]);

            if (!estLibre($pdo, $id, $debut, $fin)) {
                $pdo->rollBack();
                $erreurs[] = "Cette voiture n'est plus disponible à ces dates.";
            } else {
                $nbJours = nbJours($debut, $fin);
                $total   = round($nbJours * (float)$voiture['prix_jour'], 2);

                $pdo->prepare("INSERT INTO reservations (voiture_id,nom,email,telephone,date_debut,date_fin,total)
                               VALUES (?,?,?,?,?,?,?)")
                    ->execute([$id, $nom, $email, $tel, $debut, $fin, $total]);
                $reservationId = (int)$pdo->lastInsertId();
                $pdo->commit();
                $succes = true;
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($ex->getMessage());
            $erreurs[] = $debug ? $ex->getMessage() : "Erreur serveur, veuillez réessayer.";
        }


        // ====== 2) Emails + SMS (une panne n'annule pas la réservation) ======
        if ($succes) {
            $voitureNom = $voiture['marque'] . ' ' . $voiture['modele'];
            $prixJour   = number_format((float)$voiture['prix_jour'], 2, ',', ' ');
            $totalFmt   = number_format($total, 2, ',', ' ');
            $d1 = (new DateTime($debut))->format('d/m/Y');
            $d2 = (new DateTime($fin))->format('d/m/Y');

            // ---- Emails ----
            try {
                $lignes = [
                    'Référence'       => '#' . $reservationId,
                    'Voiture'         => $voitureNom,
                    'Nom complet'     => $nom,
                    'Email'           => $email,
                    'Téléphone'       => $tel,
                    'Date de début'   => $d1,
                    'Date de fin'     => $d2,
                    'Nombre de jours' => $nbJours,
                    'Prix par jour'   => $prixJour . ' MAD',
                ];
                $tableau = '<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-family:Arial,sans-serif">';
                foreach ($lignes as $k => $v) {
                    $tableau .= '<tr><td style="border:1px solid #ddd;background:#f4f6f8"><b>' . e($k)
                             . '</b></td><td style="border:1px solid #ddd">' . e($v) . '</td></tr>';
                }
                $tableau .= '<tr><td style="border:1px solid #ddd;background:#fff3e0"><b>TOTAL (' . $nbJours
                         . ' jour(s) × ' . e($prixJour) . ')</b></td><td style="border:1px solid #ddd;background:#fff3e0"><b>'
                         . e($totalFmt) . ' MAD</b></td></tr></table>';

                $htmlClient = '<div style="font-family:Arial,sans-serif">'

                    . '<h2>✅ Réservation confirmée</h2>'
                    . '<p>Bonjour ' . e($nom) . ', merci pour votre réservation. Voici le récapitulatif :</p>'
                    . $tableau
                    . '<p>À bientôt !<br>' . e($mailConfig['societe']) . '</p></div>';
                $emailClientOk = envoyerEmail($email, "Confirmation de réservation #$reservationId - $voitureNom", $htmlClient);

                if ($mailConfig['admin'] !== '') {
                    $htmlAdmin = '<div style="font-family:Arial,sans-serif">'
                        . '<h2>🚗 Nouvelle réservation #' . $reservationId . '</h2>'
                        . $tableau . '</div>';
                    envoyerEmail($mailConfig['admin'], "Nouvelle réservation #$reservationId - $voitureNom ($nom)", $htmlAdmin, $email);
                }
            } catch (Throwable $ex) {
                error_log($ex->getMessage());
                $emailClientOk = false;
            }

            // ---- SMS au numéro saisi par le client ----
            try {
                $sms  = "Reservation #$reservationId confirmee\n";
                $sms .= "Voiture : $voitureNom\n";
                foreach (['annee' => 'Annee', 'carburant' => 'Carburant', 'boite' => 'Boite', 'places' => 'Places'] as $col => $lib) {
                    if (!empty($voiture[$col])) $sms .= "$lib : {$voiture[$col]}\n";
                }
                $sms .= "Du $d1 au $d2\n";
                $sms .= "Prix/jour : $prixJour MAD\n";
                $sms .= "Jours : $nbJours\n";
                $sms .= "TOTAL : $totalFmt MAD\n";
                $sms .= "Merci, " . $mailConfig['societe'];

                $smsOk = envoyerSms(normaliserTel($tel), $sms);
            } catch (Throwable $ex) {

                error_log($ex->getMessage());
                $smsOk = false;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Réserver</title>
<style>
 body{font-family:Arial,sans-serif;background:#f4f6f8;padding:20px}
 .box{max-width:480px;margin:auto;background:#fff;padding:24px;border-radius:10px;box-shadow:0 2px 8px #0001}
 input{width:100%;padding:10px;margin:6px 0 14px;box-sizing:border-box;border:1px solid #ccc;border-radius:6px}
 button{background:#f39c12;color:#fff;border:0;padding:12px;width:100%;border-radius:6px;cursor:pointer}
 .err{color:#c0392b}.ok{color:#27ae60}.warn{color:#e67e22}
</style>
</head>
<body>
<div class="box">
<h2>Réserver : <?= e($voiture['marque'].' '.$voiture['modele']) ?></h2>
<p><?= number_format((float)$voiture['prix_jour'], 0, ',', ' ') ?> MAD / jour</p>

<?php if ($succes): ?>
  <p class="ok">✅ Réservation #<?= (int)$reservationId ?> confirmée !<br>
  Total : <strong><?= number_format($total, 2, ',', ' ') ?> MAD</strong>
  (<?= (int)$nbJours ?> jour(s)).</p>

  <?php if ($emailClientOk): ?>

    <p class="ok">📧 Un email de confirmation a été envoyé à <?= e($email) ?>.</p>
  <?php else: ?>
    <p class="warn">⚠️ Réservation enregistrée, mais l'email n'a pas pu être envoyé pour le moment.</p>
  <?php endif; ?>

  <?php if ($smsOk): ?>
    <p class="ok">📱 Un SMS récapitulatif a été envoyé au <?= e($tel) ?>.</p>
  <?php else: ?>
    <p class="warn">⚠️ Le SMS n'a pas pu être envoyé pour le moment.</p>
  <?php endif; ?>

  <a href="index.php">← Retour</a>
<?php else: ?>
  <?php foreach ($erreurs as $m): ?><p class="err"><?= e($m) ?></p><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <label>Date de début</label>
    <input type="date" name="date_debut" id="date_debut" value="<?= e($debut) ?>" min="<?= date('Y-m-d') ?>" required>
    <label>Date de fin</label>
    <input type="date" name="date_fin" id="date_fin" value="<?= e($fin) ?>" min="<?= e($debut ?: date('Y-m-d')) ?>" required>
    <label>Nom complet</label>
    <input type="text" name="nom" maxlength="100" value="<?= e($_POST['nom'] ?? '') ?>" required>
    <label>Email</label>
    <input type="email" name="email" maxlength="120" value="<?= e($_POST['email'] ?? '') ?>" required>
    <label>Téléphone</label>
    <input type="tel" name="telephone" maxlength="20" value="<?= e($_POST['telephone'] ?? '') ?>" placeholder="06 12 34 56 78" required>
    <button>Confirmer la réservation</button>
  </form>
  <p><a href="index.php">← Retour</a></p>
<?php endif; ?>
</div>


<script>
const d = document.getElementById('date_debut');
const f = document.getElementById('date_fin');
if (d && f) {
  d.addEventListener('change', () => {
    f.min = d.value;
    if (f.value && f.value < d.value) f.value = d.value;
  });
}
</script>
</body>
</html>
