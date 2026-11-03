<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-hero">
      <div class="brand-mark"><?= icon('school') ?></div>
      <h1>Welkom terug</h1>
      <p>Log in op het portaal voor conciërges.</p>
    </div>
    <div class="card">
      <?php if (!empty($error)): ?>
        <div class="flash error" role="alert"><?= icon('alert') ?> <span><?= e($error) ?></span></div>
      <?php endif ?>
      <form method="post" action="<?= e(url('/login')) ?>">
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">E-mailadres</label>
          <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required value="<?= e($email) ?>" autofocus>
        </div>
        <div class="field">
          <label for="password">Wachtwoord</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn block" type="submit">Inloggen</button>
      </form>
    </div>
    <p class="auth-switch">Nog geen account? <a href="<?= e(url('/registreren')) ?>">Account aanmaken</a></p>
  </div>
</div>
