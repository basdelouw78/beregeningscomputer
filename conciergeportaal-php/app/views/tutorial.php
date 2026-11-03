<?php
$images = array_values(array_filter($files, fn($f) => str_starts_with($f['mimetype'], 'image/') && !str_starts_with($f['mimetype'], 'image/hei')));
$heic = array_values(array_filter($files, fn($f) => str_starts_with($f['mimetype'], 'image/hei')));
$videos = array_values(array_filter($files, fn($f) => str_starts_with($f['mimetype'], 'video/')));
$docs = array_values(array_filter($files, fn($f) => $f['mimetype'] === 'application/pdf'));
$docs = array_merge($docs, $heic);
$fileUrl = fn($f) => url('/tutorials/bestand/' . $f['filename']);
?>
<a class="back" href="<?= e(url('/tutorials')) ?>"><?= icon('chev-left') ?> Tutorials</a>

<article class="stack">
  <header>
    <span class="badge primary"><?= e($tutorial['category']) ?></span>
    <h1 style="margin-top:.5rem;overflow-wrap:anywhere"><?= e($tutorial['title']) ?></h1>
    <p class="small muted"><?= icon('user') ?> <?= e($tutorial['user_name'] ?? 'Onbekend') ?> · <?= e(format_datetime($tutorial['created_at'])) ?></p>
  </header>

  <?php if ($images): ?>
    <a href="<?= e($fileUrl($images[0])) ?>" target="_blank" rel="noopener">
      <img src="<?= e($fileUrl($images[0])) ?>" alt="<?= e($images[0]['original_name']) ?>" style="width:100%;max-height:420px;object-fit:cover;border-radius:var(--radius)">
    </a>
  <?php endif ?>

  <?php if ($tutorial['description']): ?>
    <section class="card"><h2>Waar gaat het over?</h2><p class="post-body" style="margin:0"><?= e($tutorial['description']) ?></p></section>
  <?php endif ?>

  <?php if ($steps): ?>
    <section class="card">
      <h2>Stappen</h2>
      <ol class="steps"><?php foreach ($steps as $s): ?><li><?= e($s) ?></li><?php endforeach ?></ol>
    </section>
  <?php endif ?>

  <?php if ($videos || $tutorial['video_url']): ?>
    <section class="card video-wrap"><h2>Video</h2>
      <?php if ($embed): ?>
        <div style="position:relative;aspect-ratio:16/9;margin-bottom:.75rem">
          <iframe src="<?= e($embed) ?>" title="Video" style="position:absolute;inset:0;width:100%;height:100%;border:0;border-radius:12px" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
        </div>
      <?php elseif ($tutorial['video_url']): ?>
        <p><a class="btn secondary" href="<?= e($tutorial['video_url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('video') ?> Video bekijken</a></p>
      <?php endif ?>
      <?php foreach ($videos as $v): ?>
        <video controls playsinline preload="metadata" src="<?= e($fileUrl($v)) ?>"></video>
      <?php endforeach ?>
    </section>
  <?php endif ?>

  <?php if (count($images) > 1): ?>
    <section class="card"><h2>Foto's</h2>
      <div class="gallery">
        <?php foreach ($images as $i): ?>
          <a href="<?= e($fileUrl($i)) ?>" target="_blank" rel="noopener"><img src="<?= e($fileUrl($i)) ?>" alt="<?= e($i['original_name']) ?>" loading="lazy"></a>
        <?php endforeach ?>
      </div>
    </section>
  <?php endif ?>

  <?php if ($docs): ?>
    <section class="card"><h2>Bestanden</h2>
      <ul class="list">
        <?php foreach ($docs as $d): ?>
          <li><a class="row-link" href="<?= e($fileUrl($d)) ?>" target="_blank" rel="noopener"><?= icon('file') ?><span class="grow title"><?= e($d['original_name']) ?></span><?= icon('chev-right', 'muted') ?></a></li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endif ?>

  <?php if (can_edit($tutorial['user_id'], $user)): ?>
    <form method="post" action="<?= e(url('/tutorials/' . $tutorial['id'] . '/verwijderen')) ?>" data-confirm="Deze tutorial verwijderen?">
      <?= csrf_field() ?>
      <button class="btn danger" type="submit"><?= icon('trash') ?> Tutorial verwijderen</button>
    </form>
  <?php endif ?>
</article>
