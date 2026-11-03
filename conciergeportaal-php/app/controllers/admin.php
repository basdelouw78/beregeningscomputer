<?php
// Beheer: scholen en gebruikers (alleen beheerders).

function coords_from_post(): ?array
{
    $lat = filter_var(str_replace(',', '.', (string) ($_POST['lat'] ?? '')), FILTER_VALIDATE_FLOAT);
    $lng = filter_var(str_replace(',', '.', (string) ($_POST['lng'] ?? '')), FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) return null;
    return [$lat, $lng];
}

function admin_index(): void
{
    require_admin();
    $schools = all('SELECT s.*, (SELECT COUNT(DISTINCT user_id) FROM {schedules} sc WHERE sc.school_id = s.id) AS people
        FROM {schools} s ORDER BY name');
    $users = all('SELECT id, name, email, is_admin FROM {users} ORDER BY name');
    render('admin-schools', compact('schools', 'users'), ['title' => 'Beheer', 'useMap' => true]);
}

function admin_school_create(): void
{
    require_admin();
    $name = input('name', 120);
    $c = coords_from_post();
    if ($name === '' || !$c) {
        flash('error', 'Vul een naam in en tik op de kaart om de locatie te kiezen.');
        redirect('/admin/scholen');
    }
    q('INSERT INTO {schools} (name, address, lat, lng) VALUES (?, ?, ?, ?)', [$name, input('address', 200), ...$c]);
    flash('success', "$name is toegevoegd.");
    redirect('/admin/scholen');
}

function admin_school_update($id): void
{
    require_admin();
    $s = one('SELECT * FROM {schools} WHERE id = ?', [(int) $id]);
    if (!$s) abort(404, 'Deze school bestaat niet (meer).');
    $name = input('name', 120) ?: $s['name'];
    $c = coords_from_post() ?? [$s['lat'], $s['lng']];
    q('UPDATE {schools} SET name = ?, address = ?, lat = ?, lng = ? WHERE id = ?', [$name, input('address', 200), $c[0], $c[1], $s['id']]);
    flash('success', "$name is bijgewerkt.");
    redirect('/admin/scholen');
}

function admin_school_delete($id): void
{
    require_admin();
    $s = one('SELECT * FROM {schools} WHERE id = ?', [(int) $id]);
    if (!$s) abort(404, 'Deze school bestaat niet (meer).');
    q('DELETE FROM {schools} WHERE id = ?', [$s['id']]);
    // Conciërges zonder locatie moeten hun setup opnieuw doen
    q('UPDATE {users} SET setup_done = 0 WHERE setup_done = 1 AND id NOT IN (SELECT DISTINCT user_id FROM {schedules})');
    flash('success', "{$s['name']} is verwijderd.");
    redirect('/admin/scholen');
}

function admin_toggle_admin($id): void
{
    $me = require_admin();
    $u = one('SELECT * FROM {users} WHERE id = ?', [(int) $id]);
    if (!$u) abort(404, 'Deze gebruiker bestaat niet (meer).');
    if ($u['id'] === $me['id']) {
        flash('error', 'Je kunt je eigen beheerrechten niet intrekken.');
    } else {
        q('UPDATE {users} SET is_admin = 1 - is_admin WHERE id = ?', [$u['id']]);
        flash('success', "Beheerrechten van {$u['name']} aangepast.");
    }
    redirect('/admin/scholen');
}

function admin_user_delete($id): void
{
    $me = require_admin();
    $u = one('SELECT * FROM {users} WHERE id = ?', [(int) $id]);
    if (!$u) abort(404, 'Deze gebruiker bestaat niet (meer).');
    if ($u['id'] === $me['id']) {
        flash('error', 'Je kunt je eigen account hier niet verwijderen.');
    } else {
        q('DELETE FROM {users} WHERE id = ?', [$u['id']]);
        flash('success', "Het account van {$u['name']} is verwijderd. Wat diegene geplaatst heeft blijft staan.");
    }
    redirect('/admin/scholen');
}
