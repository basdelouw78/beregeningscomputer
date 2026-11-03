<?php
// Eerste keer: kies op de kaart waar je werkt + dagen en tijden.

function setup_form(): void
{
    $user = require_login();
    $schools = all('SELECT * FROM {schools} ORDER BY name');
    $current = [];
    foreach (all('SELECT school_id, day, start_time, end_time FROM {schedules} WHERE user_id = ?', [$user['id']]) as $r) {
        $current[$r['school_id']][$r['day']] = ['start' => $r['start_time'], 'end' => $r['end_time']];
    }
    render('setup', ['schools' => $schools, 'current' => $current, 'errors' => []], setup_layout($user));
}

function setup_layout(array $user): array
{
    return ['title' => 'Mijn locaties', 'useMap' => true, 'bodyClass' => $user['setup_done'] ? '' : 'setup-page'];
}

function setup_submit(): void
{
    $user = require_login();
    $schools = all('SELECT * FROM {schools} ORDER BY name');
    $byId = array_column($schools, null, 'id');
    $selected = [];
    foreach ((array) ($_POST['schools'] ?? []) as $v) {
        $id = to_int($v);
        if ($id !== null && isset($byId[$id])) $selected[$id] = true;
    }
    $selected = array_keys($selected);

    $errors = [];
    $entries = [];
    $current = [];
    foreach ($selected as $sid) {
        $name = $byId[$sid]['name'];
        $current[$sid] = [];
        $any = false;
        foreach (DAYS as $n => $d) {
            if (empty($_POST["s{$sid}_d{$n}_on"])) continue;
            $start = $_POST["s{$sid}_d{$n}_start"] ?? '';
            $end = $_POST["s{$sid}_d{$n}_end"] ?? '';
            $current[$sid][$n] = ['start' => is_string($start) ? $start : '', 'end' => is_string($end) ? $end : ''];
            $any = true;
            if (!is_time($start) || !is_time($end)) {
                $errors[] = "Vul begin- en eindtijd in voor {$d['long']} bij $name.";
            } elseif ($start >= $end) {
                $errors[] = "De eindtijd moet na de begintijd liggen ({$d['long']}, $name).";
            } else {
                $entries[] = [$sid, $n, $start, $end];
            }
        }
        if (!$any) $errors[] = "Kies minstens één dag voor $name.";
    }
    if (!$selected) $errors[] = 'Kies op de kaart minstens één school waar je werkt.';

    if ($errors) {
        http_response_code(400);
        render('setup', compact('schools', 'current', 'errors'), setup_layout($user));
        return;
    }

    $firstTime = !$user['setup_done'];
    transaction(function () use ($user, $entries) {
        q('DELETE FROM {schedules} WHERE user_id = ?', [$user['id']]);
        foreach ($entries as $e) {
            q('INSERT INTO {schedules} (user_id, school_id, day, start_time, end_time) VALUES (?, ?, ?, ?, ?)', [$user['id'], ...$e]);
        }
        q('UPDATE {users} SET setup_done = 1 WHERE id = ?', [$user['id']]);
    });
    flash('success', $firstTime ? 'Top, je bent helemaal klaar! Welkom in het portaal.' : 'Je locaties en tijden zijn opgeslagen.');
    redirect('/');
}
