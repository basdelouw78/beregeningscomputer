<a class="back" href="<?= e(url('/vragen')) ?>"><?= icon('chev-left') ?> Alle vragen</a>

<article class="card">
  <div class="item-head">
    <h1 style="font-size:1.35rem;overflow-wrap:anywhere"><?= e($question['title']) ?></h1>
    <?php if ($question['solved']): ?><span class="badge ok"><?= icon('check') ?> Opgelost</span><?php else: ?><span class="badge warn">Open</span><?php endif ?>
  </div>
  <p class="small muted"><?= e($question['user_name'] ?? 'Onbekend') ?> · <?= e(format_datetime($question['created_at'])) ?></p>
  <?php if ($question['body']): ?><p class="post-body"><?= e($question['body']) ?></p><?php endif ?>
  <?php if (can_edit($question['user_id'], $user)): ?>
    <div class="btn-row">
      <form class="inline" method="post" action="<?= e(url('/vragen/' . $question['id'] . '/opgelost')) ?>">
        <?= csrf_field() ?>
        <button class="btn secondary sm" type="submit"><?= icon('check') ?> <?= $question['solved'] ? 'Toch nog open' : 'Markeer als opgelost' ?></button>
      </form>
      <form class="inline" method="post" action="<?= e(url('/vragen/' . $question['id'] . '/verwijderen')) ?>" data-confirm="Deze vraag en alle antwoorden verwijderen?">
        <?= csrf_field() ?>
        <button class="btn danger sm" type="submit"><?= icon('trash') ?> Verwijderen</button>
      </form>
    </div>
  <?php endif ?>
</article>

<h2 style="margin-top:1.5rem"><?= count($answers) ?> antwoord<?= count($answers) === 1 ? '' : 'en' ?></h2>
<div class="stack">
  <?php foreach ($answers as $a): ?>
    <div class="card answer">
      <span class="avatar sm"><?= e(initials($a['user_name'] ?? '?')) ?></span>
      <div style="min-width:0">
        <div class="answer-head"><strong><?= e($a['user_name'] ?? 'Onbekend') ?></strong><span class="small muted"><?= e(time_ago($a['created_at'])) ?></span></div>
        <div class="post-body"><?= e($a['body']) ?></div>
        <?php if (can_edit($a['user_id'], $user)): ?>
          <form method="post" action="<?= e(url('/vragen/antwoord/' . $a['id'] . '/verwijderen')) ?>" data-confirm="Dit antwoord verwijderen?" style="margin-top:.4rem">
            <?= csrf_field() ?>
            <button class="btn ghost sm" type="submit" style="padding-left:0"><?= icon('trash') ?> Verwijderen</button>
          </form>
        <?php endif ?>
      </div>
    </div>
  <?php endforeach ?>
</div>

<section class="card" style="margin-top:1rem">
  <h2>Jouw antwoord</h2>
  <form method="post" action="<?= e(url('/vragen/' . $question['id'] . '/antwoord')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label class="sr-only" for="a-body">Antwoord</label>
      <textarea id="a-body" name="body" required maxlength="5000" placeholder="Deel je oplossing of tip…"></textarea></div>
    <button class="btn block" type="submit">Antwoord plaatsen</button>
  </form>
</section>
