<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-hero">
      <div class="brand-mark"><?= icon('school') ?></div>
      <h1>Account aanmaken</h1>
      <p>Daarna kies je op de kaart op welke school of scholen je werkt.</p>
    </div>
    <div class="card">
      <?php if ($errors): ?>
        <div class="flash error" role="alert"><?= icon('alert') ?>
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul>
        </div>
      <?php endif ?>
      <form method="post" action="<?= e(url('/registreren')) ?>">
        <?= csrf_field() ?>
        <div class="field">
          <label for="name">Volledige naam</label>
          <input id="name" name="name" type="text" autocomplete="name" required value="<?= e($values['name'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="email">E-mailadres</label>
          <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required value="<?= e($values['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="phone">Telefoonnummer <span class="muted small">(optioneel)</span></label>
          <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" value="<?= e($values['phone'] ?? '') ?>">
          <p class="hint">Handig zodat collega's je snel kunnen bereiken.</p>
        </div>
        <div class="field">
          <label for="password">Wachtwoord</label>
          <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
          <p class="hint">Minimaal 8 tekens.</p>
        </div>
        <div class="field">
          <label for="password2">Herhaal wachtwoord</label>
          <input id="password2" name="password2" type="password" autocomplete="new-password" minlength="8" required>
        </div>
        <?php if ($needsCode): ?>
        <div class="field">
          <label for="code">Registratiecode</label>
          <input id="code" name="code" type="text" autocomplete="off" required>
          <p class="hint">Deze code krijg je van je leidinggevende.</p>
        </div>
        <?php endif ?>
        <button class="btn block" type="submit">Account aanmaken</button>
      </form>
    </div>
    <p class="auth-switch">Heb je al een account? <a href="<?= e(url('/login')) ?>">Inloggen</a></p>
  </div>
</div>
