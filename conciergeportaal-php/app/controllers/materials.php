<?php
// Materialen uitlenen.

function material_or_404($id): array
{
    $m = one('SELECT * FROM {materials} WHERE id = ?', [(int) $id]);
    if (!$m) abort(404, 'Dit materiaal bestaat niet (meer).');
    return $m;
}

function active_loan(int $materialId): ?array
{
    return one('SELECT * FROM {loans} WHERE material_id = ? AND returned_at IS NULL', [$materialId]);
}

function materials_index(): void
{
    $user = require_setup();
    $materials = all('SELECT m.*, s.name AS school_name, o.name AS owner_name,
            l.id AS loan_id, l.due_date, l.borrower_id, b.name AS borrower_name, ls.name AS loan_school
        FROM {materials} m
        LEFT JOIN {schools} s ON s.id = m.school_id
        LEFT JOIN {users} o ON o.id = m.owner_id
        LEFT JOIN {loans} l ON l.material_id = m.id AND l.returned_at IS NULL
        LEFT JOIN {users} b ON b.id = l.borrower_id
        LEFT JOIN {schools} ls ON ls.id = l.school_id
        ORDER BY m.name');
    $schools = all('SELECT id, name FROM {schools} ORDER BY name');
    $mySchool = (int) val('SELECT school_id FROM {schedules} WHERE user_id = ? ORDER BY id LIMIT 1', [$user['id']]);
    render('materials', compact('materials', 'schools', 'mySchool'), ['title' => 'Materialen uitlenen']);
}

function materials_create(): void
{
    $user = require_setup();
    $name = input('name', 100);
    if ($name === '') {
        flash('error', 'Geef het materiaal een naam.');
        redirect('/materialen');
    }
    $schoolId = to_int($_POST['school_id'] ?? null);
    if ($schoolId !== null && !val('SELECT 1 FROM {schools} WHERE id = ?', [$schoolId])) $schoolId = null;
    q('INSERT INTO {materials} (name, description, school_id, owner_id, created_at) VALUES (?, ?, ?, ?, ?)',
        [$name, input('description', 1000), $schoolId, $user['id'], now()]);
    flash('success', "\"$name\" staat nu in de lijst.");
    redirect('/materialen');
}

function materials_borrow($id): void
{
    $user = require_setup();
    $m = material_or_404($id);
    if (active_loan($m['id'])) {
        flash('error', 'Dit materiaal is al uitgeleend.');
        redirect('/materialen');
    }
    $due = is_date($_POST['due_date'] ?? null) ? $_POST['due_date'] : null;
    $schoolId = to_int($_POST['school_id'] ?? null);
    if ($schoolId !== null && !val('SELECT 1 FROM {schools} WHERE id = ?', [$schoolId])) $schoolId = null;
    q('INSERT INTO {loans} (material_id, borrower_id, school_id, due_date, note, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$m['id'], $user['id'], $schoolId, $due, input('note', 300), now()]);
    flash('success', "Je hebt \"{$m['name']}\" geleend.");
    redirect('/materialen');
}

function materials_return($id): void
{
    $user = require_setup();
    $m = material_or_404($id);
    $loan = active_loan($m['id']);
    if ($loan && ($loan['borrower_id'] === $user['id'] || can_edit($m['owner_id'], $user))) {
        q('UPDATE {loans} SET returned_at = ? WHERE id = ?', [now(), $loan['id']]);
        flash('success', "\"{$m['name']}\" is teruggebracht.");
    }
    redirect('/materialen');
}

function materials_delete($id): void
{
    $user = require_setup();
    $m = material_or_404($id);
    if (can_edit($m['owner_id'], $user)) {
        q('DELETE FROM {materials} WHERE id = ?', [$m['id']]);
        flash('success', "\"{$m['name']}\" is verwijderd.");
    }
    redirect('/materialen');
}
