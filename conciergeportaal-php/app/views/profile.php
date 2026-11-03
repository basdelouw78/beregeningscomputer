<div class="page-head">
  <div style="display:flex;gap:.9rem;align-items:center">
    <span class="avatar" style="width:56px;height:56px;font-size:1.1rem"><?= e(initials($user['name'])) ?></span>
    <div><h1 style="margin:0"><?= e($user['name']) ?></h1><p><?= e($user['email']) ?><?= $user['is_admin'] ? ' · beheerder' : '' ?></p></div>
  </div>
</div>

<div class="grid grid-2">
  <section class="card">
    <nav class="menu-list">
      <a href="<?= e(url('/setup')) ?>"><?= icon('pin') ?> Mijn locaties &amp; tijden <?= icon('chev-right', 'chev-r') ?></a>
      <a href="<?= e(url('/collegas')) ?>"><?= icon('users') ?> Collega's <?= icon('chev-right', 'chev-r') ?></a>
      <?php if ($user['is_admin']): ?><a href="<?= e(url('/admin/scholen')) ?>"><?= icon('shield') ?> Beheer: scholen &amp; gebruikers <?= icon('chev-right', 'chev-r') ?></a><?php endif ?>
      <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="logout" type="submit"><?= icon('logout') ?> Uitloggen</button>
      </form>
    </nav>
  </section>

  <section class="card">
    <h2>Gegevens wijzigen</h2>
    <form method="post" action="<?= e(url('/profiel')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label for="name">Naam</label>
        <input id="name" name="name" type="text" required value="<?= e($user['name']) ?>" autocomplete="name"></div>
      <div class="field"><label for="phone">Telefoonnummer</label>
        <input id="phone" name="phone" type="tel" value="<?= e($user['phone']) ?>" autocomplete="tel" inputmode="tel"></div>
      <details class="expand" style="box-shadow:none">
        <summary><?= icon('shield') ?> Wachtwoord wijzigen <?= icon('chev-down', 'chev') ?></summary>
        <div class="body">
          <div class="field"><label for="current_password">Huidig wachtwoord</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password"></div>
          <div class="field"><label for="new_password">Nieuw wachtwoord</label>
            <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password"></div>
        </div>
      </details>
      <button class="btn block" type="submit">Opslaan</button>
    </form>
  </section>
</div>
