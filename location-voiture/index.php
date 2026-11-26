<?php
require 'config.php';

$debut   = trim($_GET['date_debut'] ?? '');
$fin     = trim($_GET['date_fin'] ?? '');
$erreur  = '';
$datesOk = false;

$voitures = [];
try {
    $voitures = $pdo->query("SELECT * FROM voitures ORDER BY prix_jour")->fetchAll();

    if ($debut !== '' || $fin !== '') {
        if (!dateValide($debut) || !dateValide($fin)
            || $debut < date('Y-m-d') || $fin < $debut) {
            $erreur   = "Dates invalides.";
            $voitures = [];
        } else {
            $datesOk = true;
            // Nombre de réservations actives (confirmée / sortie) qui chevauchent la période, par voiture
            $st = $pdo->prepare(
                "SELECT voiture_id, COUNT(*) FROM reservations
                 WHERE date_debut <= ? AND date_fin >= ?
                   AND statut IN ('confirmee','sortie')
                 GROUP BY voiture_id"
            );
            $st->execute([$fin, $debut]);
            $prises = $st->fetchAll(PDO::FETCH_KEY_PAIR);   // voiture_id => nb réservées

            $voitures = array_values(array_filter(array_map(function ($v) use ($prises) {
                $v['dispo'] = (int)($v['quantite'] ?? 1) - (int)($prises[$v['id']] ?? 0);
                return $v;
            }, $voitures), function ($v) { return $v['dispo'] > 0; }));
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $datesOk  = false;
    $voitures = [];
    $erreur   = $debug
        ? "Erreur base de données : " . $ex->getMessage() . " (importez database.sql dans phpMyAdmin)"
        : "Service momentanément indisponible.";
}

// Retourne le chemin de la photo d'une voiture : P1.jpeg, P2.jpeg, P3.jpeg, ...
// (P + id). Cherche dans images/voitures/ puis à la racine du site.
// Si aucune photo n'existe pour cette voiture : P1.jpeg par défaut.
function photoVoiture(array $v): string
{
    $dossiers   = ['images/voitures/', ''];
    $extensions = ['jpeg', 'jpg', 'png', 'webp', 'JPEG', 'JPG', 'PNG'];
    $nom        = 'P' . (int)$v['id'];

    foreach ($dossiers as $dossier) {
        foreach ($extensions as $ext) {
            $chemin = $dossier . $nom . '.' . $ext;
            if (file_exists(__DIR__ . '/' . $chemin)) {
                return $chemin;
            }
        }
    }

    // Photo par défaut
    foreach ($dossiers as $dossier) {
        if (file_exists(__DIR__ . '/' . $dossier . 'P1.jpeg')) {
            return $dossier . 'P1.jpeg';
        }
    }
    return 'images/voitures/P1.jpeg';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Location de voitures</title>
<style>
 body{font-family:Arial,sans-serif;margin:0;background:#f4f6f8;color:#222}
 header{background:#0b3d91;color:#fff;padding:20px;text-align:center}
 form.search{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:20px}
 input,button{padding:10px;border:1px solid #ccc;border-radius:6px}
 button,.btn{background:#f39c12;color:#fff;border:0;cursor:pointer;text-decoration:none;padding:10px 16px;border-radius:6px;display:inline-block}

 /* Voitures centrées */
 .grid{
   display:flex;
   flex-wrap:wrap;
   justify-content:center;   /* centre les cartes horizontalement */
   gap:20px;
   padding:20px;
   max-width:1100px;
   margin:auto;
 }
 .card{
   width:260px;
   background:#fff;
   border-radius:10px;
   padding:0 0 16px;
   box-shadow:0 2px 8px #0001;
   overflow:hidden;
   text-align:center;        /* texte centré dans la carte */
 }
 .card img{width:100%;height:160px;object-fit:cover;display:block;background:#e9ecef}
 .card .contenu{padding:0 16px}
 .prix{font-size:1.4em;color:#0b3d91;font-weight:bold}
 .err{color:#c0392b;text-align:center}
 .vide,.info{width:100%;text-align:center}
 .info{color:#666;font-size:.9em}


   
       body{
         background-image : url("https://img.freepik.com/photos-premium/generique-suv-noir_2227-851.jpg?size=626&ext=jpg&uid=R90682470&ga=GA1.2.1411820154.1674344732&semt=ais.jpg");


         }




</style>
</head>
<body>
<header>
  <h1>🚗 Location de voitures</h1>
  <p>Trouvez la voiture idéale au meilleur prix</p>
</header>

<form class="search" method="get">
  <input type="date" name="date_debut" id="date_debut"
         min="<?= date('Y-m-d') ?>" value="<?= e($debut) ?>" required>
  <input type="date" name="date_fin" id="date_fin"
         min="<?= e($debut ?: date('Y-m-d')) ?>" value="<?= e($fin) ?>" required>
  <button type="submit">Rechercher</button>
</form>

<?php if ($erreur): ?>
  <p class="err"><?= e($erreur) ?></p>
<?php endif; ?>

<div class="grid">
<?php if (!$datesOk && !$erreur && $voitures): ?>
  <p class="info" style="color: white">Choisissez vos dates puis cliquez sur « Rechercher » pour réserver.</p>
<?php endif; ?>

<?php foreach ($voitures as $v): ?>
  <div class="card">
    <img src="<?= e(photoVoiture($v)) ?>"
         alt="<?= e($v['marque'] . ' ' . $v['modele']) ?>"
         loading="lazy">
    <div class="contenu">
      <h3><?= e($v['marque'] . ' ' . $v['modele']) ?></h3>
      <p><?= (int)$v['places'] ?> places · <?= e($v['boite']) ?> · <?= e($v['carburant']) ?></p>
      <p class="prix"><?= number_format((float)$v['prix_jour'], 0, ',', ' ') ?> MAD / jour</p>
      <?php if ($datesOk): ?>
        <p class="info"><?= (int)$v['dispo'] ?> disponible(s)</p>
        <p class="info"><?= nbJours($debut, $fin) ?> jour(s) :
          <strong><?= number_format(nbJours($debut, $fin) * (float)$v['prix_jour'], 0, ',', ' ') ?> MAD</strong></p>
        <a class="btn"
           href="reserver.php?id=<?= (int)$v['id'] ?>&amp;date_debut=<?= urlencode($debut) ?>&amp;date_fin=<?= urlencode($fin) ?>">
           Réserver
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($datesOk && !$voitures): ?>
  <p class="vide">Aucune voiture disponible pour ces dates.</p>
<?php endif; ?>
</div>

<script>
// La date de fin ne peut pas être avant la date de début
const d = document.getElementById('date_debut');
const f = document.getElementById('date_fin');
d.addEventListener('change', () => {
  f.min = d.value;
  if (f.value && f.value < d.value) f.value = d.value;
});
if (d.value) f.min = d.value;
</script>
</body>
</html>
