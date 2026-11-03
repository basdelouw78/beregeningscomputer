<?php
// Algemene hulpfuncties voor alle pagina's.

const DAYS = [
    1 => ['short' => 'ma', 'long' => 'maandag'],
    2 => ['short' => 'di', 'long' => 'dinsdag'],
    3 => ['short' => 'wo', 'long' => 'woensdag'],
    4 => ['short' => 'do', 'long' => 'donderdag'],
    5 => ['short' => 'vr', 'long' => 'vrijdag'],
    6 => ['short' => 'za', 'long' => 'zaterdag'],
    7 => ['short' => 'zo', 'long' => 'zondag'],
];
const MONTHS = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

class HttpError extends Exception
{
    public int $status;
    public function __construct(int $status, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

// ---------- Uitvoer ----------

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }
    return $base;
}

function url(string $path = '/'): string
{
    return base_path() . $path;
}

function asset(string $file): string
{
    $full = APP_ROOT . '/assets/' . $file;
    $v = is_file($full) ? filemtime($full) : 0;
    return url('/assets/' . $file) . '?v=' . $v;
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function icon(string $name, string $cls = ''): string
{
    return '<svg class="ic ' . e($cls) . '" aria-hidden="true"><use href="#i-' . e($name) . '"/></svg>';
}

// JSON veilig in een <script>-blok zetten
function json_script($v): string
{
    return json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

function render(string $view, array $data = [], array $layout = []): void
{
    $data += ['user' => current_user()];
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/app/views/' . $view . '.php';
    $content = ob_get_clean();
    $title = $layout['title'] ?? '';
    $useMap = $layout['useMap'] ?? false;
    $bodyClass = $layout['bodyClass'] ?? '';
    $flash = take_flash();
    require APP_ROOT . '/app/views/partials/layout.php';
}

function abort(int $status, string $message): never
{
    throw new HttpError($status, $message);
}

// ---------- Datum en tijd ----------

function today_iso(int $offsetDays = 0): string
{
    return date('Y-m-d', strtotime(($offsetDays >= 0 ? '+' : '') . $offsetDays . ' days'));
}

function iso_weekday(?string $date = null): int
{
    return (int) date('N', $date ? strtotime($date) : time());
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function format_date(?string $iso, bool $withWeekday = true): string
{
    if (!$iso) return '';
    $ts = strtotime(substr($iso, 0, 10));
    $s = ($withWeekday ? DAYS[(int) date('N', $ts)]['short'] . ' ' : '') . date('j', $ts) . ' ' . MONTHS[(int) date('n', $ts) - 1];
    if (date('Y', $ts) !== date('Y')) $s .= ' ' . date('Y', $ts);
    return $s;
}

function format_datetime(?string $dt): string
{
    if (!$dt) return '';
    $ts = strtotime($dt);
    return date('j', $ts) . ' ' . MONTHS[(int) date('n', $ts) - 1] . ' ' . date('Y, H:i', $ts);
}

function time_ago(?string $dt): string
{
    if (!$dt) return '';
    $sec = max(0, time() - strtotime($dt));
    if ($sec < 60) return 'zojuist';
    if ($sec < 3600) return floor($sec / 60) . ' min geleden';
    if ($sec < 86400) return floor($sec / 3600) . ' uur geleden';
    if ($sec < 86400 * 7) {
        $n = (int) floor($sec / 86400);
        return $n === 1 ? 'gisteren' : $n . ' dagen geleden';
    }
    return explode(',', format_datetime($dt))[0];
}

function initials(?string $name): string
{
    $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1));
    return $out !== '' ? $out : '?';
}

function tel_link(string $phone): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $phone);
}

// ---------- Invoer ----------

function input(string $key, int $max = 200): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function to_int($v): ?int
{
    return (is_string($v) || is_int($v)) && preg_match('/^\d+$/', (string) $v) ? (int) $v : null;
}

function is_time($v): bool
{
    return is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) === 1;
}

function is_date($v): bool
{
    if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return false;
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

// ---------- Sessie, flash en CSRF ----------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        abort(403, 'Je sessie is verlopen. Ga terug, ververs de pagina en probeer het opnieuw.');
    }
}

// ---------- Gebruiker en toegang ----------

function current_user(): ?array
{
    static $loaded = false, $user = null;
    if (!$loaded) {
        $loaded = true;
        if (!empty($_SESSION['user_id'])) {
            $user = one('SELECT id, email, name, phone, is_admin, setup_done FROM {users} WHERE id = ?', [$_SESSION['user_id']]);
            if (!$user) unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') $_SESSION['return_to'] = current_path();
        redirect('/login');
    }
    return $user;
}

function require_setup(): array
{
    $user = require_login();
    if (!$user['setup_done']) redirect('/setup');
    return $user;
}

function require_admin(): array
{
    $user = require_setup();
    if (!$user['is_admin']) abort(403, 'Deze pagina is alleen voor beheerders.');
    return $user;
}

function can_edit(?int $ownerId, array $user): bool
{
    return ($ownerId !== null && $ownerId === (int) $user['id']) || (bool) $user['is_admin'];
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
    if (str_starts_with($path, '/index.php')) $path = substr($path, 10);
    $path = '/' . trim($path, '/');
    return $path;
}

function is_active(string $href): bool
{
    $p = current_path();
    return $href === '/' ? $p === '/' : str_starts_with($p, $href);
}
