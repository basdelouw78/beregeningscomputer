<div class="page-head">
  <div><h1>Tutorials</h1><p>Handleidingen en oplossingen van collega's: zo repareer je het.</p></div>
</div>

<div class="chip-scroll" role="navigation" aria-label="Categorieën">
  <a class="chip <?= !$category ? 'active' : '' ?>" href="<?= e(url('/tutorials')) ?>">Alles</a>
  <?php foreach ($categories as $c): ?>
    <a class="chip <?= $category === $c ? 'active' : '' ?>" href="<?= e(url('/tutorials?categorie=' . rawurlencode($c))) ?>"><?= e($c) ?></a>
  <?php endforeach ?>
</div>

<div class="search">
  <?= icon('search') ?>
  <input type="search" placeholder="Zoek een tutorial…" data-filter=".tut-card" aria-label="Zoek een tutorial">
</div>

<?php if (!$tutorials): ?>
  <div class="card empty"><?= icon('tool') ?><p>Nog geen tutorials<?= $category ? ' in deze categorie' : '' ?>. Deel jouw kennis!</p>
    <a class="btn" href="<?= e(url('/tutorials/nieuw')) ?>"><?= icon('upload') ?> Tutorial uploaden</a></div>
<?php endif ?>

<div class="tut-grid">
  <?php foreach ($tutorials as $t): $n = (int) $t['file_count']; ?>
    <a class="card tut-card" href="<?= e(url('/tutorials/' . $t['id'])) ?>" data-text="<?= e(mb_strtolower($t['title'] . ' ' . $t['description'] . ' ' . $t['category'])) ?>">
      <div class="tut-cover">
        <?php if ($t['cover']): ?><img src="<?= e(url('/tutorials/bestand/' . $t['cover'])) ?>" alt="" loading="lazy"><?php else: ?><?= icon($t['video_url'] ? 'video' : 'tool') ?><?php endif ?>
      </div>
      <div class="tut-body">
        <span><span class="badge primary"><?= e($t['category']) ?></span></span>
        <h3><?= e($t['title']) ?></h3>
        <?php if ($t['description']): ?><p class="small muted" style="margin:0"><?= e(mb_strlen($t['description']) > 110 ? mb_substr($t['description'], 0, 110) . '…' : $t['description']) ?></p><?php endif ?>
        <span class="meta"><?= e($t['user_name'] ?? 'Onbekend') ?> · <?= e(time_ago($t['created_at'])) ?><?= $n ? ' · ' . $n . ' bijlage' . ($n === 1 ? '' : 'n') : '' ?></span>
      </div>
    </a>
  <?php endforeach ?>
</div>

<a class="fab" href="<?= e(url('/tutorials/nieuw')) ?>"><?= icon('upload') ?> Uploaden</a>
