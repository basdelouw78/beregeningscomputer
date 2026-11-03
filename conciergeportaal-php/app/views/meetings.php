<?php
$meetingCard = function (array $m, bool $past) use ($user) { ?>
  <article class="card meeting">
    <div class="date-box"><span class="d"><?= (int) substr($m['date'], 8, 2) ?></span><span class="m"><?= MONTHS[(int) substr($m['date'], 5, 2) - 1] ?></span></div>
    <div class="stack" style="min-width:0">
      <div>
        <h3 style="margin:0"><?= e($m['title']) ?></h3>
        <div class="item-meta" style="margin-top:.2rem">
          <span><?= icon('clock') ?> <?= e(format_date($m['date'])) ?>, <?= e($m['start_time']) ?><?= $m['end_time'] ? '–' . e($m['end_time']) : '' ?></span>
          <?php if ($m['place']): ?><span><?= icon('pin') ?> <?= e($m['place']) ?></span><?php endif ?>
          <span><?= icon('user') ?> <?= e($m['organizer_name'] ?? 'Onbekend') ?></span>
        </div>
      </div>
      <?php if ($m['description']): ?><p class="small post-body" style="margin:0"><?= e($m['description']) ?></p><?php endif ?>
      <div class="attendees" title="<?= e(implode(', ', array_column($m['attendees'], 'name'))) ?>">
        <?php foreach (array_slice($m['attendees'], 0, 6) as $a): ?><span class="avatar sm"><?= e(initials($a['name'])) ?></span><?php endforeach ?>
        <span class="small muted" style="margin-left:.4rem"><?= count($m['attendees']) ?> aanwezig</span>
      </div>
      <?php if (!$past): ?>
      <div class="btn-row">
        <form class="inline" method="post" action="<?= e(url('/meetings/' . $m['id'] . '/aanmelden')) ?>">
          <?= csrf_field() ?>
          <?php if ($m['attending']): ?>
            <button class="btn secondary sm" type="submit"><?= icon('check') ?> Je bent erbij · afmelden</button>
          <?php else: ?>
            <button class="btn sm" type="submit"><?= icon('plus') ?> Ik ben erbij</button>
          <?php endif ?>
        </form>
        <?php if (can_edit($m['organizer_id'], $user)): ?>
          <form class="inline" method="post" action="<?= e(url('/meetings/' . $m['id'] . '/verwijderen')) ?>" data-confirm="Deze meeting annuleren?">
            <?= csrf_field() ?>
            <button class="btn danger sm" type="submit"><?= icon('trash') ?> Annuleren</button>
          </form>
        <?php endif ?>
      </div>
      <?php endif ?>
    </div>
  </article>
<?php }; ?>

<div class="page-head">
  <div><h1>Meeting plannen</h1><p>Overleg, teamdag of samen een klus? Plan het hier.</p></div>
</div>

<details class="expand" id="nieuw">
  <summary><?= icon('plus') ?> Nieuwe meeting <?= icon('chev-down', 'chev') ?></summary>
  <div class="body">
    <form method="post" action="<?= e(url('/meetings')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label for="mt-title">Onderwerp</label>
        <input id="mt-title" name="title" type="text" required maxlength="120" placeholder="Bijv. Conciërge-overleg"></div>
      <div class="field"><label for="mt-date">Datum</label>
        <input id="mt-date" name="date" type="date" required min="<?= today_iso() ?>" value="<?= today_iso(1) ?>"></div>
      <div class="row-2 field">
        <div><label for="mt-start">Begin</label><input id="mt-start" name="start_time" type="time" required value="10:00" step="300"></div>
        <div><label for="mt-end">Einde</label><input id="mt-end" name="end_time" type="time" value="11:00" step="300"></div>
      </div>
      <div class="field"><label for="mt-place">Locatie</label>
        <input id="mt-place" name="place" type="text" list="school-list" maxlength="120" placeholder="School, lokaal of online">
        <datalist id="school-list"><?php foreach ($schools as $s): ?><option value="<?= e($s['name']) ?>"><?php endforeach ?></datalist></div>
      <div class="field"><label for="mt-desc">Agenda / toelichting <span class="muted small">(optioneel)</span></label>
        <textarea id="mt-desc" name="description" maxlength="2000"></textarea></div>
      <button class="btn block" type="submit">Meeting plannen</button>
    </form>
  </div>
</details>

<h2>Gepland</h2>
<?php if (!$upcoming): ?>
  <div class="card empty"><?= icon('calendar') ?><p>Er zijn geen meetings gepland.</p></div>
<?php endif ?>
<div class="stack"><?php foreach ($upcoming as $m) $meetingCard($m, false) ?></div>

<?php if ($past): ?>
  <details class="expand" style="margin-top:1.5rem">
    <summary class="muted"><?= icon('clock') ?> Afgelopen meetings <?= icon('chev-down', 'chev') ?></summary>
    <div class="body stack"><?php foreach ($past as $m) $meetingCard($m, true) ?></div>
  </details>
<?php endif ?>
