<div class="page-head">
  <div><h1>Collega's</h1><p>Wie werkt waar, en wanneer?</p></div>
</div>

<div id="colleagues-map" class="map map-sm" aria-label="Kaart met scholen en conciërges"></div>

<div class="search" style="margin-top:1rem">
  <?= icon('search') ?>
  <input type="search" placeholder="Zoek op naam of school…" data-filter=".person" aria-label="Zoeken">
</div>

<div class="grid grid-2">
  <?php foreach ($people as $p): ?>
    <div class="card person" data-text="<?= e(mb_strtolower($p['name'] . ' ' . implode(' ', array_column($p['locations'], 'school_name')))) ?>">
      <div class="card-title" style="justify-content:flex-start;gap:.75rem">
        <span class="avatar"><?= e(initials($p['name'])) ?></span>
        <div class="grow"><h3 style="margin:0"><?= e($p['name']) ?></h3>
          <span class="small muted"><?= e($p['email']) ?></span></div>
      </div>
      <?php foreach ($p['locations'] as $loc): ?>
        <div class="small" style="margin-bottom:.35rem"><strong><?= e($loc['school_name']) ?></strong><br>
          <span class="muted"><?= e(schedule_summary($loc['days'])) ?></span></div>
      <?php endforeach ?>
      <div class="btn-row" style="margin-top:.6rem">
        <?php if ($p['phone']): ?><a class="btn secondary sm" href="<?= e(tel_link($p['phone'])) ?>"><?= icon('phone') ?> Bellen</a><?php endif ?>
        <a class="btn secondary sm" href="mailto:<?= e($p['email']) ?>"><?= icon('mail') ?> Mailen</a>
      </div>
    </div>
  <?php endforeach ?>
</div>
<script type="application/json" id="schools-data"><?= json_script($bySchool) ?></script>
