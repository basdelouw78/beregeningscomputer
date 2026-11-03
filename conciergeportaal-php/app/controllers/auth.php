<?php
// Inloggen, registreren en uitloggen.

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// Max. 10 pogingen per 15 minuten per IP-adres
function too_many_attempts(): bool
{
    $ip = client_ip();
    $row = one('SELECT attempts, first_at FROM {login_attempts} WHERE ip = ?', [$ip]);
    if (!$row || time() - $row['first_at'] > 900) {
        q('REPLACE INTO {login_attempts} (ip, attempts, first_at) VALUES (?, 1, ?)', [$ip, time()]);
        return false;
    }
    q('UPDATE {login_attempts} SET attempts = attempts + 1 WHERE ip = ?', [$ip]);
    return $row['attempts'] + 1 > 10;
}

function start_user_session(int $userId): never
{
    $returnTo = $_SESSION['return_to'] ?? null;
    session_regenerate_id(true);
    $_SESSION = ['user_id' => $userId];
    $setup = (int) val('SELECT setup_done FROM {users} WHERE id = ?', [$userId]);
    if (!$setup) redirect('/setup');
    redirect(is_string($returnTo) && str_starts_with($returnTo, '/') ? $returnTo : '/');
}

function login_form(): void
{
    if (current_user()) redirect('/');
    render('login', ['email' => ''], ['title' => 'Inloggen', 'bodyClass' => 'auth-page']);
}

function login_submit(): void
{
    $email = mb_strtolower(input('email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $layout = ['title' => 'Inloggen', 'bodyClass' => 'auth-page'];
    if (too_many_attempts()) {
        http_response_code(429);
        render('login', ['email' => $email, 'error' => 'Te veel pogingen. Wacht een kwartier en probeer het opnieuw.'], $layout);
        return;
    }
    $user = one('SELECT id, password_hash FROM {users} WHERE email = ?', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        render('login', ['email' => $email, 'error' => 'E-mailadres of wachtwoord klopt niet.'], $layout);
        return;
    }
    q('DELETE FROM {login_attempts} WHERE ip = ?', [client_ip()]);
    start_user_session((int) $user['id']);
}

function register_form(): void
{
    if (current_user()) redirect('/');
    render('register', ['values' => [], 'errors' => [], 'needsCode' => config('register_code') !== ''],
        ['title' => 'Account aanmaken', 'bodyClass' => 'auth-page']);
}

function register_submit(): void
{
    $values = [
        'name' => input('name', 80),
        'email' => mb_strtolower(input('email')),
        'phone' => input('phone', 30),
    ];
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $needsCode = config('register_code', '') !== '';
    $errors = [];
    if ($values['name'] === '') $errors[] = 'Vul je naam in.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Vul een geldig e-mailadres in.';
    if (mb_strlen($password) < 8) $errors[] = 'Kies een wachtwoord van minimaal 8 tekens.';
    if ($password !== ($_POST['password2'] ?? '')) $errors[] = 'De wachtwoorden zijn niet gelijk.';
    if ($needsCode && !hash_equals((string) config('register_code'), input('code'))) {
        $errors[] = 'De registratiecode klopt niet. Vraag deze na bij je leidinggevende.';
    }
    if (!$errors && val('SELECT 1 FROM {users} WHERE email = ?', [$values['email']])) {
        $errors[] = 'Er bestaat al een account met dit e-mailadres.';
    }
    if ($errors) {
        http_response_code(400);
        render('register', compact('values', 'errors', 'needsCode'), ['title' => 'Account aanmaken', 'bodyClass' => 'auth-page']);
        return;
    }
    // De allereerste gebruiker wordt automatisch beheerder
    $isFirst = (int) val('SELECT COUNT(*) FROM {users}') === 0;
    q('INSERT INTO {users} (email, password_hash, name, phone, is_admin, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$values['email'], password_hash($password, PASSWORD_DEFAULT), $values['name'], $values['phone'], $isFirst ? 1 : 0, now()]);
    start_user_session(insert_id());
}

function logout(): never
{
    $_SESSION = [];
    session_destroy();
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    redirect('/login');
}
