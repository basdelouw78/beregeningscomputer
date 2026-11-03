<div class="card empty">
  <?= icon('alert') ?>
  <h1><?= e($heading ?? 'Oeps') ?></h1>
  <p><?= e($message) ?></p>
  <a class="btn" href="<?= e(url('/')) ?>">Naar de hoofdpagina</a>
</div>
