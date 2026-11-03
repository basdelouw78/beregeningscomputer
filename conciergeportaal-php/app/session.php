<?php
// Sessies in de database, zodat je ingelogd blijft (30 dagen) ook op gedeelde hosting.

class DbSessionHandler implements SessionHandlerInterface
{
    public function __construct(private int $lifetime) {}

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $data = val('SELECT data FROM {sessions} WHERE id = ? AND expires > ?', [$id, time()]);
        return $data === null ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        q('INSERT INTO {sessions} (id, data, expires) VALUES (?, ?, ?)
           ON DUPLICATE KEY UPDATE data = VALUES(data), expires = VALUES(expires)',
          [$id, $data, time() + $this->lifetime]);
        return true;
    }

    public function destroy(string $id): bool
    {
        q('DELETE FROM {sessions} WHERE id = ?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return q('DELETE FROM {sessions} WHERE expires <= ?', [time()])->rowCount();
    }
}

function start_session(): void
{
    $lifetime = 60 * 60 * 24 * 30;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.use_strict_mode', '1');
    session_set_save_handler(new DbSessionHandler($lifetime), true);
    session_name('portaal_sid');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => base_path() === '' ? '/' : base_path() . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
