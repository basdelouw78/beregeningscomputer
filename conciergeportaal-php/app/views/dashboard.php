<?php
$hour = (int) date('G');
$greet = $hour < 12 ? 'Goedemorgen' : ($hour < 18 ? 'Goedemiddag' : 'Goedenavond');
$todayLong = DAYS[$today]['long'];
$firstName = explode(' ', trim($user['name']))[0];
?>
<div class="hello">
  <h1><?= e($greet) ?>, <?= e($firstName) ?> 👋</h1>
  <p>Het is <?= e($todayLong) ?> <?= e(format_date(today_iso(), false)) ?>.</p>
</div>

<div class="tiles">
  <a class="tile t-teal" href="<?= e(url('/materialen')) ?>">
    <span class="tile-ic"><?= icon('box') ?></span>
    <strong>Materialen uitlenen</strong>
    <span class="meta"><?= $stats['available'] ?> beschikbaar<?= $stats['myLoans'] ? ' · ' . $stats['myLoans'] . ' door jou geleend' : '' ?></span>
  </a>
  <a class="tile t-blue" href="<?= e(url('/meetings')) ?>">
    <span class="tile-ic"><?= icon('calendar') ?></span>
    <strong>Meeting plannen</strong>
    <span class="meta"><?= $stats['meetings'] ?> gepland</span>
  </a>
  <a class="tile t-amber" href="<?= e(url('/vragen')) ?>">
    <span class="tile-ic"><?= icon('help') ?></span>
    <strong>Vragen &amp; antwoorden</strong>
    <span class="meta"><?= $stats['openQuestions'] ?> open vra<?= $stats['openQuestions'] === 1 ? 'ag' : 'gen' ?></span>
  </a>
  <a class="tile t-rose" href="<?= e(url('/tutorials')) ?>">
    <span class="tile-ic"><?= icon('tool') ?></span>
    <strong>Tutorials</strong>
    <span class="meta"><?= $stats['tutorials'] ?> handleiding<?= $stats['tutorials'] === 1 ? '' : 'en' ?></span>
  </a>
  <a class="tile t-violet" href="<?= e(url('/collegas')) ?>">
    <span class="tile-ic"><?= icon('users') ?></span>
    <strong>Collega's</strong>
    <span class="meta">Wie werkt waar?</span>
  </a>
  <a class="tile t-slate" href="<?= e(url('/setup')) ?>">
    <span class="tile-ic"><?= icon('pin') ?></span>
    <strong>Mijn locaties</strong>
    <span class="meta">Dagen &amp; tijden wijzigen</span>
  </a>
</div>

<div class="grid grid-2">
  <section class="card">
    <div class="card-title"><h2>Vandaag aanwezig</h2><span class="badge primary"><?= e($todayLong) ?></span></div>
    <?php if (!$presentToday): ?>
      <p class="muted">Vandaag staat er niemand ingeroosterd.</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($presentToday as $p): ?>
          <li>
            <span class="avatar sm"><?= e(initials($p['user_name'])) ?></span>
            <span class="grow">
              <span class="title"><?= e($p['user_name']) ?><?= $p['user_id'] === $user['id'] ? ' (jij)' : '' ?></span>
              <span class="small muted"><?= e($p['school_name']) ?> · <?= e($p['start_time']) ?>–<?= e($p['end_time']) ?></span>
            </span>
            <?php if ($p['phone'] && $p['user_id'] !== $user['id']): ?>
              <a class="btn secondary sm" href="<?= e(tel_link($p['phone'])) ?>" aria-label="Bel <?= e($p['user_name']) ?>"><?= icon('phone') ?></a>
            <?php endif ?>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>

  <section class="card">
    <div class="card-title"><h2>Mijn rooster</h2><a class="small" href="<?= e(url('/setup')) ?>">Wijzigen</a></div>
    <ul class="list">
      <?php foreach ($mySchedule as $loc): $byDay = array_column($loc['days'], null, 'day'); ?>
        <li style="flex-direction:column;align-items:stretch;gap:.4rem">
          <span class="title"><?= icon('school') ?> <?= e($loc['school_name']) ?></span>
          <div class="day-pills">
            <?php foreach (DAYS as $n => $d): if ($n > 5 && !isset($byDay[$n])) continue; ?>
              <span class="day-pill <?= isset($byDay[$n]) ? 'on' : '' ?> <?= $n === $today ? 'today' : '' ?>" title="<?= isset($byDay[$n]) ? e($byDay[$n]['start_time'] . '–' . $byDay[$n]['end_time']) : 'vrij' ?>"><?= e($d['short']) ?></span>
            <?php endforeach ?>
          </div>
          <span class="small muted"><?= e(schedule_summary($loc['days'])) ?></span>
        </li>
      <?php endforeach ?>
    </ul>
  </section>

  <section class="card">
    <div class="card-title"><h2>Volgende meeting</h2><a class="small" href="<?= e(url('/meetings')) ?>">Alles</a></div>
    <?php if ($nextMeeting): ?>
      <div class="meeting">
        <div class="date-box"><span class="d"><?= (int) substr($nextMeeting['date'], 8, 2) ?></span><span class="m"><?= MONTHS[(int) substr($nextMeeting['date'], 5, 2) - 1] ?></span></div>
        <div>
          <strong><?= e($nextMeeting['title']) ?></strong>
          <div class="small muted"><?= e(format_date($nextMeeting['date'])) ?> · <?= e($nextMeeting['start_time']) ?><?= $nextMeeting['end_time'] ? '–' . e($nextMeeting['end_time']) : '' ?></div>
          <?php if ($nextMeeting['place']): ?><div class="small muted"><?= icon('pin') ?> <?= e($nextMeeting['place']) ?></div><?php endif ?>
        </div>
      </div>
    <?php else: ?>
      <p class="muted">Er is nog niets gepland.</p>
      <a class="btn secondary sm" href="<?= e(url('/meetings#nieuw')) ?>"><?= icon('plus') ?> Meeting plannen</a>
    <?php endif ?>
  </section>

  <section class="card">
    <div class="card-title"><h2>Nieuwste vragen</h2><a class="small" href="<?= e(url('/vragen')) ?>">Alles</a></div>
    <?php if (!$latestQuestions): ?>
      <p class="muted">Nog geen vragen gesteld.</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($latestQuestions as $q): ?>
          <li><a class="row-link" href="<?= e(url('/vragen/' . $q['id'])) ?>">
            <span class="grow"><span class="title"><?= e($q['title']) ?></span>
              <span class="small muted"><?= e($q['user_name'] ?? 'Onbekend') ?> · <?= e(time_ago($q['created_at'])) ?></span></span>
            <?php if ($q['solved']): ?><span class="badge ok">Opgelost</span><?php else: ?><span class="badge"><?= (int) $q['answer_count'] ?> antw.</span><?php endif ?>
          </a></li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>
</div>
