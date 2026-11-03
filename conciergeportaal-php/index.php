<?php
// Conciërgeportaal — alle pagina's lopen via dit bestand.

require __DIR__ . '/app/bootstrap.php';

foreach (glob(APP_ROOT . '/app/controllers/*.php') as $file) require $file;

// [methode, pad (regex), functie]
$routes = [
    ['GET',  '#^/login$#', 'login_form'],
    ['POST', '#^/login$#', 'login_submit'],
    ['GET',  '#^/registreren$#', 'register_form'],
    ['POST', '#^/registreren$#', 'register_submit'],
    ['POST', '#^/logout$#', 'logout'],

    ['GET',  '#^/setup$#', 'setup_form'],
    ['POST', '#^/setup$#', 'setup_submit'],

    ['GET',  '#^/$#', 'dashboard'],
    ['GET',  '#^/collegas$#', 'colleagues'],
    ['GET',  '#^/profiel$#', 'profile_form'],
    ['POST', '#^/profiel$#', 'profile_submit'],

    ['GET',  '#^/materialen$#', 'materials_index'],
    ['POST', '#^/materialen$#', 'materials_create'],
    ['POST', '#^/materialen/(\d+)/lenen$#', 'materials_borrow'],
    ['POST', '#^/materialen/(\d+)/terug$#', 'materials_return'],
    ['POST', '#^/materialen/(\d+)/verwijderen$#', 'materials_delete'],

    ['GET',  '#^/meetings$#', 'meetings_index'],
    ['POST', '#^/meetings$#', 'meetings_create'],
    ['POST', '#^/meetings/(\d+)/aanmelden$#', 'meetings_toggle'],
    ['POST', '#^/meetings/(\d+)/verwijderen$#', 'meetings_delete'],

    ['GET',  '#^/vragen$#', 'qa_index'],
    ['POST', '#^/vragen$#', 'qa_create'],
    ['GET',  '#^/vragen/(\d+)$#', 'qa_show'],
    ['POST', '#^/vragen/(\d+)/antwoord$#', 'qa_answer'],
    ['POST', '#^/vragen/(\d+)/opgelost$#', 'qa_solved'],
    ['POST', '#^/vragen/(\d+)/verwijderen$#', 'qa_delete'],
    ['POST', '#^/vragen/antwoord/(\d+)/verwijderen$#', 'qa_delete_answer'],

    ['GET',  '#^/tutorials$#', 'tutorials_index'],
    ['GET',  '#^/tutorials/nieuw$#', 'tutorials_new'],
    ['POST', '#^/tutorials$#', 'tutorials_create'],
    ['GET',  '#^/tutorials/bestand/([a-f0-9]{32}\.[a-z0-9]{2,5})$#', 'tutorials_file'],
    ['GET',  '#^/tutorials/(\d+)$#', 'tutorials_show'],
    ['POST', '#^/tutorials/(\d+)/verwijderen$#', 'tutorials_delete'],

    ['GET',  '#^/admin/scholen$#', 'admin_index'],
    ['POST', '#^/admin/scholen$#', 'admin_school_create'],
    ['POST', '#^/admin/scholen/(\d+)$#', 'admin_school_update'],
    ['POST', '#^/admin/scholen/(\d+)/verwijderen$#', 'admin_school_delete'],
    ['POST', '#^/admin/gebruikers/(\d+)/admin$#', 'admin_toggle_admin'],
    ['POST', '#^/admin/gebruikers/(\d+)/verwijderen$#', 'admin_user_delete'],
];

$method = $_SERVER['REQUEST_METHOD'];
$path = current_path();

try {
    // Te grote upload: PHP gooit dan het hele formulier weg
    if ($method === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'Wat je probeerde te versturen is te groot. Probeer minder of kleinere bestanden.');
        redirect($path === '/tutorials' ? '/tutorials/nieuw' : $path);
    }
    if ($method === 'POST') verify_csrf();

    foreach ($routes as [$m, $pattern, $handler]) {
        if ($m === $method && preg_match($pattern, $path, $match)) {
            array_shift($match);
            $handler(...$match);
            exit;
        }
    }
    abort(404, 'Deze pagina bestaat niet (meer).');
} catch (HttpError $err) {
    http_response_code($err->status);
    $heading = $err->status === 404 ? 'Niet gevonden' : 'Oeps';
    render('error', ['message' => $err->getMessage(), 'heading' => $heading], ['title' => $heading]);
} catch (Throwable $err) {
    error_log('[conciergeportaal] ' . $err);
    http_response_code(500);
    render('error', ['message' => 'Er ging iets mis. Probeer het opnieuw.', 'heading' => 'Oeps'], ['title' => 'Oeps']);
}
