<?php
// Hoofdpagina, collega's en profiel.

function schedules_with_names(string $where = '', array $params = []): array
{
    return all("SELECT sc.*, u.name AS user_name, u.phone, u.email, s.name AS school_name
        FROM {schedules} sc
        JOIN {users} u ON u.id = sc.user_id
        JOIN {schools} s ON s.id = sc.school_id
        $where
        ORDER BY s.name, sc.day, sc.start_time", $params);
}

function group_by_school(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[$r['school_id']] ??= ['school_id' => $r['school_id'], 'school_name' => $r['school_name'], 'days' => []];
        $out[$r['school_id']]['days'][] = $r;
    }
    return array_values($out);
}

function schedule_summary(array $days): string
{
    return implode(' · ', array_map(fn($d) => DAYS[$d['day']]['short'] . ' ' . $d['start_time'] . '–' . $d['end_time'], $days));
}

function dashboard(): void
{
    $user = require_setup();
    $today = iso_weekday();
    $stats = [
        'available' => (int) val('SELECT COUNT(*) FROM {materials} m WHERE NOT EXISTS
            (SELECT 1 FROM {loans} l WHERE l.material_id = m.id AND l.returned_at IS NULL)'),
        'myLoans' => (int) val('SELECT COUNT(*) FROM {loans} WHERE borrower_id = ? AND returned_at IS NULL', [$user['id']]),
        'meetings' => (int) val('SELECT COUNT(*) FROM {meetings} WHERE date >= ?', [today_iso()]),
        'openQuestions' => (int) val('SELECT COUNT(*) FROM {questions} WHERE solved = 0'),
        'tutorials' => (int) val('SELECT COUNT(*) FROM {tutorials}'),
    ];
    render('dashboard', [
        'today' => $today,
        'mySchedule' => group_by_school(schedules_with_names('WHERE sc.user_id = ?', [$user['id']])),
        'presentToday' => schedules_with_names('WHERE sc.day = ?', [$today]),
        'stats' => $stats,
        'nextMeeting' => one('SELECT * FROM {meetings} WHERE date >= ? ORDER BY date, start_time LIMIT 1', [today_iso()]),
        'latestQuestions' => all('SELECT q.*, u.name AS user_name,
            (SELECT COUNT(*) FROM {answers} a WHERE a.question_id = q.id) AS answer_count
            FROM {questions} q LEFT JOIN {users} u ON u.id = q.user_id ORDER BY q.created_at DESC, q.id DESC LIMIT 3'),
    ], ['title' => 'Home']);
}

function colleagues(): void
{
    require_setup();
    $schools = all('SELECT * FROM {schools} ORDER BY name');
    $rows = schedules_with_names();
    $users = all('SELECT id, name, email, phone FROM {users} WHERE setup_done = 1 ORDER BY name');
    $people = array_map(function ($u) use ($rows) {
        $u['locations'] = group_by_school(array_filter($rows, fn($r) => $r['user_id'] === $u['id']));
        return $u;
    }, $users);
    $bySchool = array_map(function ($s) use ($rows) {
        $names = array_values(array_unique(array_map(fn($r) => $r['user_name'], array_filter($rows, fn($r) => $r['school_id'] === $s['id']))));
        return ['id' => $s['id'], 'name' => $s['name'], 'lat' => (float) $s['lat'], 'lng' => (float) $s['lng'], 'people' => $names];
    }, $schools);
    render('colleagues', compact('people', 'bySchool'), ['title' => "Collega's", 'useMap' => true]);
}

function profile_form(): void
{
    require_setup();
    render('profile', [], ['title' => 'Mijn profiel']);
}

function profile_submit(): void
{
    $user = require_setup();
    $name = input('name', 80);
    if ($name === '') {
        flash('error', 'Je naam mag niet leeg zijn.');
        redirect('/profiel');
    }
    q('UPDATE {users} SET name = ?, phone = ? WHERE id = ?', [$name, input('phone', 30), $user['id']]);
    $newPw = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    if ($newPw !== '') {
        $hash = val('SELECT password_hash FROM {users} WHERE id = ?', [$user['id']]);
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            flash('error', 'Je huidige wachtwoord klopt niet; het wachtwoord is niet gewijzigd.');
            redirect('/profiel');
        }
        if (mb_strlen($newPw) < 8) {
            flash('error', 'Het nieuwe wachtwoord moet minimaal 8 tekens zijn.');
            redirect('/profiel');
        }
        q('UPDATE {users} SET password_hash = ? WHERE id = ?', [password_hash($newPw, PASSWORD_DEFAULT), $user['id']]);
    }
    flash('success', 'Je profiel is bijgewerkt.');
    redirect('/profiel');
}
