<?php
// Tutorials: handleidingen met stappen, foto's, video's en PDF's.

const TUTORIAL_CATEGORIES = ['Elektra', 'Sanitair', 'Verwarming', 'Sloten & deuren', 'Meubilair', 'ICT & AV', 'Buiten & tuin', 'Schoonmaak', 'Veiligheid', 'Overig'];

const ALLOWED_UPLOADS = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
    'image/heic' => 'heic', 'image/heif' => 'heic',
    'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm',
    'application/pdf' => 'pdf',
];

// Hoeveel mag er per bestand geüpload worden volgens de PHP-instellingen van de hosting?
function upload_limit_bytes(): int
{
    $toBytes = function ($v) {
        $v = trim((string) $v);
        $n = (float) $v;
        return (int) match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    };
    $limits = array_filter([$toBytes(ini_get('upload_max_filesize')), $toBytes(ini_get('post_max_size'))]);
    return $limits ? min($limits) : 8 * 1048576;
}

function format_bytes(int $b): string
{
    return $b >= 1048576 ? round($b / 1048576) . ' MB' : ceil($b / 1024) . ' kB';
}

// YouTube/Vimeo-link omzetten naar een insluitbare speler
function video_embed(string $link): ?string
{
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $link, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $link, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return null;
}

function tutorials_index(): void
{
    require_setup();
    $category = in_array($_GET['categorie'] ?? '', TUTORIAL_CATEGORIES, true) ? $_GET['categorie'] : '';
    $tutorials = all("SELECT t.*, u.name AS user_name,
            (SELECT filename FROM {tutorial_files} f WHERE f.tutorial_id = t.id AND f.mimetype LIKE 'image/%' AND f.mimetype NOT LIKE 'image/hei%' ORDER BY f.id LIMIT 1) AS cover,
            (SELECT COUNT(*) FROM {tutorial_files} f WHERE f.tutorial_id = t.id) AS file_count
        FROM {tutorials} t LEFT JOIN {users} u ON u.id = t.user_id
        " . ($category ? 'WHERE t.category = ?' : '') . '
        ORDER BY t.created_at DESC, t.id DESC', $category ? [$category] : []);
    render('tutorials', ['tutorials' => $tutorials, 'categories' => TUTORIAL_CATEGORIES, 'category' => $category], ['title' => 'Tutorials']);
}

function tutorials_new(): void
{
    require_setup();
    render('tutorial-new', ['categories' => TUTORIAL_CATEGORIES, 'maxBytes' => upload_limit_bytes()], ['title' => 'Tutorial uploaden']);
}

function tutorials_create(): void
{
    $user = require_setup();
    $title = input('title', 150);
    if ($title === '') {
        flash('error', 'Geef je tutorial een titel.');
        redirect('/tutorials/nieuw');
    }
    $category = in_array($_POST['category'] ?? '', TUTORIAL_CATEGORIES, true) ? $_POST['category'] : 'Overig';
    $videoUrl = input('video_url', 300);
    if ($videoUrl !== '' && !preg_match('~^https?://~i', $videoUrl)) $videoUrl = 'https://' . $videoUrl;
    if ($videoUrl !== '' && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        flash('error', 'De videolink is geen geldige link.');
        redirect('/tutorials/nieuw');
    }

    // Bestanden controleren en opslaan
    $saved = [];
    $problems = [];
    $files = $_FILES['files'] ?? null;
    if ($files && is_array($files['name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $count = min(count($files['name']), 8);
        for ($i = 0; $i < $count; $i++) {
            $err = $files['error'][$i];
            $orig = mb_substr(basename((string) $files['name'][$i]), 0, 200);
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                $problems[] = "$orig is te groot (max. " . format_bytes(upload_limit_bytes()) . ').';
                continue;
            }
            if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($files['tmp_name'][$i])) {
                $problems[] = "$orig kon niet worden geüpload.";
                continue;
            }
            $mime = $finfo->file($files['tmp_name'][$i]) ?: '';
            if (!isset(ALLOWED_UPLOADS[$mime])) {
                $problems[] = "$orig is geen foto, video of PDF.";
                continue;
            }
            $name = bin2hex(random_bytes(16)) . '.' . ALLOWED_UPLOADS[$mime];
            if (!move_uploaded_file($files['tmp_name'][$i], UPLOAD_DIR . '/' . $name)) {
                $problems[] = "$orig kon niet worden opgeslagen.";
                continue;
            }
            $saved[] = [$name, $orig, $mime, (int) $files['size'][$i]];
        }
    }

    $id = transaction(function () use ($user, $title, $category, $videoUrl, $saved) {
        q('INSERT INTO {tutorials} (user_id, title, category, description, steps, video_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$user['id'], $title, $category, input('description', 2000), input('steps', 20000), $videoUrl, now()]);
        $id = insert_id();
        foreach ($saved as $f) {
            q('INSERT INTO {tutorial_files} (tutorial_id, filename, original_name, mimetype, size) VALUES (?, ?, ?, ?, ?)', [$id, ...$f]);
        }
        return $id;
    });
    flash('success', 'Je tutorial is geüpload. Bedankt voor het delen!');
    if ($problems) flash('error', 'Niet alle bestanden zijn toegevoegd: ' . implode(' ', $problems));
    redirect("/tutorials/$id");
}

// Bestanden alleen voor ingelogde collega's; met ondersteuning voor video op iPhone (Range-verzoeken)
function tutorials_file(string $name): void
{
    require_setup();
    $file = one('SELECT * FROM {tutorial_files} WHERE filename = ?', [$name]);
    $path = UPLOAD_DIR . '/' . $name;
    if (!$file || !is_file($path)) abort(404, 'Dit bestand bestaat niet (meer).');

    session_write_close();
    while (ob_get_level()) ob_end_clean();

    $size = filesize($path);
    $start = 0;
    $end = $size - 1;
    header('Content-Type: ' . $file['mimetype']);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=86400');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
    $disp = str_starts_with($file['mimetype'], 'image/') || str_starts_with($file['mimetype'], 'video/') || $file['mimetype'] === 'application/pdf' ? 'inline' : 'attachment';
    header("Content-Disposition: $disp; filename*=UTF-8''" . rawurlencode($file['original_name']));

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] === '' && $m[2] !== '') {
            $start = max(0, $size - (int) $m[2]);
        } else {
            $start = (int) $m[1];
            if ($m[2] !== '') $end = min((int) $m[2], $size - 1);
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            return;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    $length = $end - $start + 1;
    header('Content-Length: ' . $length);

    $fp = fopen($path, 'rb');
    fseek($fp, $start);
    while ($length > 0 && !feof($fp) && connection_status() === CONNECTION_NORMAL) {
        $chunk = fread($fp, (int) min(1048576, $length));
        echo $chunk;
        flush();
        $length -= strlen($chunk);
    }
    fclose($fp);
}

function tutorials_show($id): void
{
    require_setup();
    $tutorial = one('SELECT t.*, u.name AS user_name FROM {tutorials} t LEFT JOIN {users} u ON u.id = t.user_id WHERE t.id = ?', [(int) $id]);
    if (!$tutorial) abort(404, 'Deze tutorial bestaat niet (meer).');
    $files = all('SELECT * FROM {tutorial_files} WHERE tutorial_id = ? ORDER BY id', [$tutorial['id']]);
    $steps = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $tutorial['steps'])), 'strlen'));
    $embed = $tutorial['video_url'] ? video_embed($tutorial['video_url']) : null;
    render('tutorial', compact('tutorial', 'files', 'steps', 'embed'), ['title' => $tutorial['title']]);
}

function tutorials_delete($id): void
{
    $user = require_setup();
    $t = one('SELECT * FROM {tutorials} WHERE id = ?', [(int) $id]);
    if (!$t) abort(404, 'Deze tutorial bestaat niet (meer).');
    if (!can_edit($t['user_id'], $user)) redirect("/tutorials/{$t['id']}");
    $files = all('SELECT filename FROM {tutorial_files} WHERE tutorial_id = ?', [$t['id']]);
    q('DELETE FROM {tutorials} WHERE id = ?', [$t['id']]);
    foreach ($files as $f) @unlink(UPLOAD_DIR . '/' . $f['filename']);
    flash('success', 'De tutorial is verwijderd.');
    redirect('/tutorials');
}
