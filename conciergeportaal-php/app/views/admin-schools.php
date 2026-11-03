<div class="page-head">
  <div><h1>Beheer</h1><p>Scholen op de kaart en gebruikers.</p></div>
</div>

<section class="card">
  <h2>School toevoegen</h2>
  <p class="small muted">Tik op de kaart om de locatie te kiezen, of sleep de oranje speld.</p>
  <div id="admin-map" class="map map-sm"></div>
  <form method="post" action="<?= e(url('/admin/scholen')) ?>" style="margin-top:1rem" id="school-form">
    <?= csrf_field() ?>
    <div class="field"><label for="s-name">Naam</label><input id="s-name" name="name" type="text" required maxlength="120"></div>
    <div class="field"><label for="s-addr">Adres</label><input id="s-addr" name="address" type="text" maxlength="200"></div>
    <div class="row-2 field">
      <div><label for="s-lat">Breedtegraad</label><input id="s-lat" name="lat" type="text" inputmode="decimal" required></div>
      <div><label for="s-lng">Lengtegraad</label><input id="s-lng" name="lng" type="text" inputmode="decimal" required></div>
    </div>
    <button class="btn block" type="submit"><?= icon('plus') ?> School toevoegen</button>
  </form>
</section>

<section class="card">
  <h2>Scholen (<?= count($schools) ?>)</h2>
  <div class="stack">
    <?php foreach ($schools as $s): $n = (int) $s['people']; ?>
      <details class="expand" style="box-shadow:none;margin:0">
        <summary style="color:var(--text)"><?= icon('school') ?> <span class="grow"><?= e($s['name']) ?> <span class="small muted">· <?= $n ?> conciërge<?= $n === 1 ? '' : 's' ?></span></span> <?= icon('chev-down', 'chev') ?></summary>
        <div class="body">
          <form method="post" action="<?= e(url('/admin/scholen/' . $s['id'])) ?>">
            <?= csrf_field() ?>
            <div class="field"><label>Naam</label><input name="name" type="text" value="<?= e($s['name']) ?>" required maxlength="120"></div>
            <div class="field"><label>Adres</label><input name="address" type="text" value="<?= e($s['address']) ?>" maxlength="200"></div>
            <div class="row-2 field">
              <div><label>Breedtegraad</label><input name="lat" type="text" inputmode="decimal" value="<?= e($s['lat']) ?>"></div>
              <div><label>Lengtegraad</label><input name="lng" type="text" inputmode="decimal" value="<?= e($s['lng']) ?>"></div>
            </div>
            <button class="btn sm" type="submit">Opslaan</button>
          </form>
          <form method="post" action="<?= e(url('/admin/scholen/' . $s['id'] . '/verwijderen')) ?>" style="margin-top:.5rem" data-confirm="<?= e($s['name']) ?> verwijderen? Roosters van conciërges op deze school worden ook verwijderd.">
            <?= csrf_field() ?>
            <button class="btn danger sm" type="submit"><?= icon('trash') ?> Verwijderen</button>
          </form>
        </div>
      </details>
    <?php endforeach ?>
  </div>
</section>

<section class="card">
  <h2>Gebruikers (<?= count($users) ?>)</h2>
  <ul class="list">
    <?php foreach ($users as $u): ?>
      <li style="flex-wrap:wrap">
        <span class="avatar sm"><?= e(initials($u['name'])) ?></span>
        <span class="grow"><span class="title"><?= e($u['name']) ?> <?php if ($u['is_admin']): ?><span class="badge primary">beheerder</span><?php endif ?></span><span class="small muted"><?= e($u['email']) ?></span></span>
        <?php if ($u['id'] !== $user['id']): ?>
          <span class="btn-row">
            <form class="inline" method="post" action="<?= e(url('/admin/gebruikers/' . $u['id'] . '/admin')) ?>">
              <?= csrf_field() ?>
              <button class="btn secondary sm" type="submit"><?= $u['is_admin'] ? 'Rechten intrekken' : 'Maak beheerder' ?></button>
            </form>
            <form class="inline" method="post" action="<?= e(url('/admin/gebruikers/' . $u['id'] . '/verwijderen')) ?>" data-confirm="Het account van <?= e($u['name']) ?> verwijderen? Diegene kan dan niet meer inloggen.">
              <?= csrf_field() ?>
              <button class="btn danger sm" type="submit" aria-label="Account verwijderen"><?= icon('trash') ?></button>
            </form>
          </span>
        <?php endif ?>
      </li>
    <?php endforeach ?>
  </ul>
</section>
<script type="application/json" id="schools-data"><?= json_script(array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name'], 'lat' => (float) $s['lat'], 'lng' => (float) $s['lng']], $schools)) ?></script>
