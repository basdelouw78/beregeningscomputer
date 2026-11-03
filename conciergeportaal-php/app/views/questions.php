<div class="page-head">
  <div><h1>Vragen &amp; antwoorden</h1><p>Loop je ergens tegenaan? Grote kans dat een collega het antwoord weet.</p></div>
</div>

<details class="expand" id="nieuw">
  <summary><?= icon('plus') ?> Stel een vraag <?= icon('chev-down', 'chev') ?></summary>
  <div class="body">
    <form method="post" action="<?= e(url('/vragen')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label for="q-title">Je vraag</label>
        <input id="q-title" name="title" type="text" required maxlength="200" placeholder="Bijv. Hoe reset ik het inbraakalarm?"></div>
      <div class="field"><label for="q-body">Toelichting <span class="muted small">(optioneel)</span></label>
        <textarea id="q-body" name="body" maxlength="5000" placeholder="Wat heb je al geprobeerd? Welk merk/type?"></textarea></div>
      <button class="btn block" type="submit">Vraag plaatsen</button>
    </form>
  </div>
</details>

<div class="search">
  <?= icon('search') ?>
  <input type="search" placeholder="Zoek in vragen…" data-filter=".q-item" aria-label="Zoek in vragen">
</div>

<?php if (!$questions): ?>
  <div class="card empty"><?= icon('help') ?><p>Nog geen vragen. Stel de eerste!</p></div>
<?php else: ?>
  <div class="card">
    <ul class="list">
      <?php foreach ($questions as $q): ?>
        <li class="q-item" data-text="<?= e(mb_strtolower($q['title'] . ' ' . $q['body'])) ?>">
          <a class="row-link" href="<?= e(url('/vragen/' . $q['id'])) ?>">
            <span class="count <?= $q['solved'] ? 'solved' : '' ?>">
              <?php if ($q['solved']): ?><?= icon('check') ?>opgelost<?php else: ?><b><?= (int) $q['answer_count'] ?></b>antw.<?php endif ?>
            </span>
            <span class="grow">
              <span class="title"><?= e($q['title']) ?></span>
              <span class="small muted"><?= e($q['user_name'] ?? 'Onbekend') ?> · <?= e(time_ago($q['created_at'])) ?></span>
            </span>
            <?= icon('chev-right', 'muted') ?>
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  </div>
<?php endif ?>
