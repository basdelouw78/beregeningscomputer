<?php
$schoolOptions = function ($selected) use ($schools) {
    $out = '';
    foreach ($schools as $s) $out .= '<option value="' . $s['id'] . '"' . ($s['id'] === $selected ? ' selected' : '') . '>' . e($s['name']) . '</option>';
    return $out;
};
?>
<div class="page-head">
  <div><h1>Materialen uitlenen</h1><p>Leen gereedschap en spullen van collega's, of bied zelf iets aan.</p></div>
</div>

<details class="expand" id="nieuw">
  <summary><?= icon('plus') ?> Materiaal aanbieden <?= icon('chev-down', 'chev') ?></summary>
  <div class="body">
    <form method="post" action="<?= e(url('/materialen')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label for="m-name">Wat is het?</label>
        <input id="m-name" name="name" type="text" required maxlength="100" placeholder="Bijv. boormachine, ladder, hogedrukreiniger"></div>
      <div class="field"><label for="m-desc">Omschrijving <span class="muted small">(optioneel)</span></label>
        <textarea id="m-desc" name="description" maxlength="1000" placeholder="Merk, staat, wat er bij zit…"></textarea></div>
      <div class="field"><label for="m-school">Staat op locatie</label>
        <select id="m-school" name="school_id"><?= $schoolOptions($mySchool) ?></select></div>
      <button class="btn block" type="submit">Toevoegen</button>
    </form>
  </div>
</details>

<div class="search">
  <?= icon('search') ?>
  <input type="search" placeholder="Zoek materiaal…" data-filter=".item-card" aria-label="Zoek materiaal">
</div>

<?php if (!$materials): ?>
  <div class="card empty"><?= icon('box') ?><p>Er staat nog geen materiaal in de lijst. Bied als eerste iets aan!</p></div>
<?php endif ?>

<div class="grid grid-2">
  <?php foreach ($materials as $m):
    $lent = $m['loan_id'] !== null;
    $overdue = $lent && $m['due_date'] && $m['due_date'] < today_iso();
    $mine = $m['borrower_id'] === $user['id'];
  ?>
    <article class="card item-card" data-text="<?= e(mb_strtolower($m['name'] . ' ' . $m['description'] . ' ' . ($m['school_name'] ?? ''))) ?>">
      <div class="item-head">
        <h3><?= e($m['name']) ?></h3>
        <?php if (!$lent): ?><span class="badge ok">Beschikbaar</span>
        <?php elseif ($overdue): ?><span class="badge danger">Te laat</span>
        <?php else: ?><span class="badge warn">Uitgeleend</span><?php endif ?>
      </div>
      <?php if ($m['description']): ?><p class="small post-body" style="margin:0"><?= e($m['description']) ?></p><?php endif ?>
      <div class="item-meta">
        <span><?= icon('school') ?> <?= e($m['school_name'] ?? 'Onbekende locatie') ?></span>
        <span><?= icon('user') ?> <?= e($m['owner_name'] ?? 'Onbekend') ?></span>
      </div>
      <?php if ($lent): ?>
        <div class="small" style="padding:.6rem .75rem;border-radius:10px;background:var(--surface-2)">
          <?= icon('swap') ?> Geleend door <strong><?= $mine ? 'jou' : e($m['borrower_name'] ?? 'onbekend') ?></strong>
          <?php if ($m['loan_school']): ?>naar <?= e($m['loan_school']) ?><?php endif ?>
          <?php if ($m['due_date']): ?>· terug op <?= e(format_date($m['due_date'])) ?><?php endif ?>
        </div>
      <?php endif ?>
      <div class="btn-row">
        <?php if (!$lent): ?>
          <button class="btn sm" type="button" data-toggle="loan-<?= $m['id'] ?>"><?= icon('swap') ?> Lenen</button>
        <?php elseif ($mine || can_edit($m['owner_id'], $user)): ?>
          <form class="inline" method="post" action="<?= e(url('/materialen/' . $m['id'] . '/terug')) ?>">
            <?= csrf_field() ?>
            <button class="btn secondary sm" type="submit"><?= icon('check') ?> Teruggebracht</button>
          </form>
        <?php endif ?>
        <?php if (can_edit($m['owner_id'], $user)): ?>
          <form class="inline" method="post" action="<?= e(url('/materialen/' . $m['id'] . '/verwijderen')) ?>" data-confirm="Weet je zeker dat je '<?= e($m['name']) ?>' wilt verwijderen?">
            <?= csrf_field() ?>
            <button class="btn danger sm" type="submit" aria-label="Verwijderen"><?= icon('trash') ?></button>
          </form>
        <?php endif ?>
      </div>
      <?php if (!$lent): ?>
        <form class="loan-form" id="loan-<?= $m['id'] ?>" method="post" action="<?= e(url('/materialen/' . $m['id'] . '/lenen')) ?>" hidden>
          <?= csrf_field() ?>
          <div class="row-2">
            <div><label for="due-<?= $m['id'] ?>">Terug op</label>
              <input id="due-<?= $m['id'] ?>" type="date" name="due_date" min="<?= today_iso() ?>" value="<?= today_iso(7) ?>"></div>
            <div><label for="to-<?= $m['id'] ?>">Naar locatie</label>
              <select id="to-<?= $m['id'] ?>" name="school_id"><?= $schoolOptions($mySchool) ?></select></div>
          </div>
          <input type="text" name="note" maxlength="300" placeholder="Opmerking (optioneel)" aria-label="Opmerking">
          <button class="btn block" type="submit">Bevestig lenen</button>
        </form>
      <?php endif ?>
    </article>
  <?php endforeach ?>
</div>
