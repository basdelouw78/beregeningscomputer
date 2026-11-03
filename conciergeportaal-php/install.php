<?php
// Installatie: databasegegevens invullen, tabellen aanmaken en config.php schrijven.
// Na de installatie is deze pagina geblokkeerd (zolang config.php bestaat).

define('APP_ROOT', __DIR__);
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/db.php';

$installed = is_file(__DIR__ . '/config.php');
$errors = [];
$done = false;
$manualConfig = null;
$v = [
    'db_host' => 'rdbms.strato.de', 'db_name' => '', 'db_user' => '', 'db_prefix' => 'cp_', 'register_code' => '',
];

$checks = [
    ['PHP 8.1 of nieuwer', version_compare(PHP_VERSION, '8.1.0', '>='), 'Je hebt PHP ' . PHP_VERSION . '. Kies in je hostingpakket een nieuwere PHP-versie.'],
    ['PDO MySQL', extension_loaded('pdo_mysql'), 'De PHP-extensie pdo_mysql ontbreekt.'],
    ['Fileinfo (controle uploads)', extension_loaded('fileinfo'), 'De PHP-extensie fileinfo ontbreekt.'],
    ['Map uploads/ is schrijfbaar', is_writable(__DIR__ . '/uploads'), 'Geef de map uploads schrijfrechten (bijv. 755 of 775).'],
];
$checksOk = !in_array(false, array_column($checks, 1), true);

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST' && $checksOk) {
    foreach ($v as $k => $_) $v[$k] = trim((string) ($_POST[$k] ?? ''));
    $pass = (string) ($_POST['db_pass'] ?? '');
    if (!preg_match('/^[a-z0-9_]{0,20}$/i', $v['db_prefix'])) $errors[] = 'Het voorvoegsel mag alleen letters, cijfers en _ bevatten.';
    if ($v['db_host'] === '' || $v['db_name'] === '' || $v['db_user'] === '') $errors[] = 'Vul server, databasenaam en gebruikersnaam in.';
    if (!$errors) {
        $cfg = [
            'db_host' => $v['db_host'], 'db_name' => $v['db_name'], 'db_user' => $v['db_user'], 'db_pass' => $pass,
            'db_prefix' => $v['db_prefix'], 'register_code' => $v['register_code'], 'timezone' => 'Europe/Amsterdam',
        ];
        try {
            $GLOBALS['__config'] = $cfg;
            $GLOBALS['__db'] = db_connect($cfg);
            db_install();
            $php = "<?php\n// Aangemaakt door install.php op " . date('Y-m-d H:i') . "\nreturn " . var_export($cfg, true) . ";\n";
            if (@file_put_contents(__DIR__ . '/config.php', $php) === false) {
                $manualConfig = $php;
            } else {
                @chmod(__DIR__ . '/config.php', 0640);
            }
            $done = true;
        } catch (PDOException $ex) {
            $errors[] = 'Kan niet verbinden met de database: ' . $ex->getMessage();
        }
    }
}
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
?><!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Installatie · Conciërgeportaal</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<?php require __DIR__ . '/app/views/partials/icons.php' ?>
<header class="topbar">
  <span class="brand"><span class="brand-mark"><?= icon('school') ?></span><span>Conciërgeportaal</span></span>
</header>
<main class="content" style="max-width:640px">
  <div class="auth-hero" style="margin-top:1rem">
    <div class="brand-mark"><?= icon('shield') ?></div>
    <h1>Installatie</h1>
    <p>Koppel het portaal aan je MySQL-database. Dit hoef je maar één keer te doen.</p>
  </div>

<?php if ($installed && !$done): ?>
  <div class="card">
    <div class="flash success"><?= icon('check') ?> <span>Het portaal is al geïnstalleerd.</span></div>
    <p>Wil je opnieuw installeren? Verwijder dan eerst <code>config.php</code> via FTP.</p>
    <a class="btn block" href="<?= e($base . '/') ?>">Naar het portaal</a>
  </div>

<?php elseif ($done): ?>
  <div class="card">
    <div class="flash success"><?= icon('check') ?> <span>De database is klaar!</span></div>
    <?php if ($manualConfig): ?>
      <p><strong>Nog één stap:</strong> het bestand <code>config.php</code> kon niet automatisch worden opgeslagen.
        Maak op je computer een bestand <code>config.php</code> met de tekst hieronder en upload het naar dezelfde map als <code>index.php</code>.</p>
      <textarea readonly style="font-family:monospace;font-size:13px;min-height:220px"><?= e($manualConfig) ?></textarea>
    <?php endif ?>
    <p>Maak nu het eerste account aan. <strong>Dat account wordt automatisch beheerder.</strong></p>
    <a class="btn block" href="<?= e($base . '/registreren') ?>">Eerste account aanmaken</a>
  </div>

<?php else: ?>
  <section class="card">
    <h2>1. Controle van je hosting</h2>
    <ul class="list">
      <?php foreach ($checks as [$label, $ok, $help]): ?>
        <li><span class="badge <?= $ok ? 'ok' : 'danger' ?>"><?= $ok ? 'OK' : 'Probleem' ?></span>
          <span class="grow"><span class="title"><?= e($label) ?></span><?php if (!$ok): ?><span class="small muted"><?= e($help) ?></span><?php endif ?></span></li>
      <?php endforeach ?>
    </ul>
  </section>

  <?php if ($checksOk): ?>
  <section class="card">
    <h2>2. Databasegegevens</h2>
    <p class="small muted">Deze gegevens vind je in je Strato-klantenlogin onder <em>Databases en webspace → Database beheren</em>.</p>
    <?php if ($errors): ?>
      <div class="flash error" role="alert"><?= icon('alert') ?><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div>
    <?php endif ?>
    <form method="post">
      <div class="field"><label for="db_host">Databaseserver (host)</label>
        <input id="db_host" name="db_host" type="text" required value="<?= e($v['db_host']) ?>">
        <p class="hint">Bij Strato meestal <code>rdbms.strato.de</code>.</p></div>
      <div class="field"><label for="db_name">Databasenaam</label>
        <input id="db_name" name="db_name" type="text" required value="<?= e($v['db_name']) ?>" placeholder="bijv. dbs1234567"></div>
      <div class="field"><label for="db_user">Gebruikersnaam</label>
        <input id="db_user" name="db_user" type="text" required value="<?= e($v['db_user']) ?>" placeholder="bijv. dbu1234567" autocomplete="off"></div>
      <div class="field"><label for="db_pass">Wachtwoord</label>
        <input id="db_pass" name="db_pass" type="password" autocomplete="new-password"></div>
      <div class="field"><label for="db_prefix">Voorvoegsel voor tabellen</label>
        <input id="db_prefix" name="db_prefix" type="text" value="<?= e($v['db_prefix']) ?>">
        <p class="hint">Handig als er al andere dingen (zoals WordPress) in dezelfde database staan.</p></div>
      <div class="field"><label for="register_code">Registratiecode <span class="muted small">(aanrader)</span></label>
        <input id="register_code" name="register_code" type="text" value="<?= e($v['register_code']) ?>" autocomplete="off">
        <p class="hint">Alleen wie deze code kent kan een account aanmaken. Laat leeg om iedereen toe te laten.</p></div>
      <button class="btn block" type="submit"><?= icon('check') ?> Installeren</button>
    </form>
  </section>
  <?php endif ?>
<?php endif ?>
</main>
</body>
</html>
