<?php if (!$user['setup_done']): ?>
  <div class="steps-bar" aria-hidden="true"><div class="on"></div><div class="on"></div><div></div></div>
<?php endif ?>
<div class="page-head">
  <div>
    <h1><?= $user['setup_done'] ? 'Mijn locaties &amp; tijden' : 'Waar werk jij?' ?></h1>
    <p>Tik op de kaart (of in de lijst) op de school of scholen waar je werkt. Geef daarna per school aan op welke dagen en tijden je er bent.</p>
  </div>
</div>

<?php if ($errors): ?>
  <div class="flash error" role="alert"><?= icon('alert') ?>
    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul>
  </div>
<?php endif ?>

<form method="post" action="<?= e(url('/setup')) ?>" id="setup-form">
  <?= csrf_field() ?>
  <section class="card">
    <div class="card-title"><h2>1. Kies je school</h2><span class="badge primary" id="sel-count">0 gekozen</span></div>
    <div id="setup-map" class="map" role="application" aria-label="Kaart met alle scholen"></div>
    <div class="school-pick">
      <?php foreach ($schools as $s): ?>
        <label class="school-option">
          <input type="checkbox" name="schools[]" value="<?= $s['id'] ?>" data-school="<?= $s['id'] ?>" <?= isset($current[$s['id']]) ? 'checked' : '' ?>>
          <span class="check"><?= icon('check') ?></span>
          <span><?= e($s['name']) ?><?php if ($s['address']): ?><span class="addr"><?= e($s['address']) ?></span><?php endif ?></span>
        </label>
      <?php endforeach ?>
    </div>
  </section>

  <section class="card">
    <h2>2. Dagen en tijden</h2>
    <p class="muted small" id="no-school-hint">Kies eerst een school hierboven.</p>
    <?php foreach ($schools as $s): $cur = $current[$s['id']] ?? []; ?>
      <div class="schedule-block" data-block="<?= $s['id'] ?>" <?= isset($current[$s['id']]) ? '' : 'hidden' ?>>
        <h3><?= icon('school') ?> <?= e($s['name']) ?></h3>
        <?php foreach (DAYS as $n => $d): $on = isset($cur[$n]); $t = $cur[$n] ?? ['start' => '07:30', 'end' => '16:30']; ?>
          <div class="day-row">
            <label class="day-toggle">
              <span class="switch">
                <input type="checkbox" name="s<?= $s['id'] ?>_d<?= $n ?>_on" value="1" data-day-toggle <?= $on ? 'checked' : '' ?>>
                <span></span>
              </span>
              <span style="text-transform: capitalize"><?= e($d['long']) ?></span>
            </label>
            <div class="day-times" <?= $on ? '' : 'hidden' ?>>
              <input type="time" name="s<?= $s['id'] ?>_d<?= $n ?>_start" value="<?= e($t['start']) ?>" aria-label="Begintijd <?= e($d['long']) ?>" step="300">
              <span class="muted">tot</span>
              <input type="time" name="s<?= $s['id'] ?>_d<?= $n ?>_end" value="<?= e($t['end']) ?>" aria-label="Eindtijd <?= e($d['long']) ?>" step="300">
            </div>
          </div>
        <?php endforeach ?>
      </div>
    <?php endforeach ?>
  </section>

  <div class="sticky-actions">
    <button class="btn block" type="submit"><?= icon('check') ?> <?= $user['setup_done'] ? 'Opslaan' : 'Klaar, naar de hoofdpagina' ?></button>
  </div>
</form>
<?php if (!$user['setup_done']): ?>
<form method="post" action="<?= e(url('/logout')) ?>" style="text-align:center;margin-top:1rem">
  <?= csrf_field() ?>
  <button class="btn ghost sm" type="submit"><?= icon('logout') ?> Uitloggen</button>
</form>
<?php endif ?>
<script type="application/json" id="schools-data"><?= json_script(array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name'], 'address' => $s['address'], 'lat' => (float) $s['lat'], 'lng' => (float) $s['lng']], $schools)) ?></script>
