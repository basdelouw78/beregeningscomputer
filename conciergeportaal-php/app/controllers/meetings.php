<?php
// Meetings plannen.

function with_attendees(array $meetings, int $userId): array
{
    foreach ($meetings as &$m) {
        $m['attendees'] = all('SELECT u.id, u.name FROM {meeting_attendees} a JOIN {users} u ON u.id = a.user_id
            WHERE a.meeting_id = ? ORDER BY u.name', [$m['id']]);
        $m['attending'] = in_array($userId, array_column($m['attendees'], 'id'), true);
    }
    return $meetings;
}

function meetings_index(): void
{
    $user = require_setup();
    $base = 'SELECT m.*, u.name AS organizer_name FROM {meetings} m LEFT JOIN {users} u ON u.id = m.organizer_id';
    render('meetings', [
        'upcoming' => with_attendees(all("$base WHERE m.date >= ? ORDER BY m.date, m.start_time", [today_iso()]), $user['id']),
        'past' => with_attendees(all("$base WHERE m.date < ? ORDER BY m.date DESC LIMIT 10", [today_iso()]), $user['id']),
        'schools' => all('SELECT name FROM {schools} ORDER BY name'),
    ], ['title' => 'Meeting plannen']);
}

function meetings_create(): void
{
    $user = require_setup();
    $title = input('title', 120);
    $date = $_POST['date'] ?? '';
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    if ($title === '' || !is_date($date) || !is_time($start) || ($end !== '' && !is_time($end))) {
        flash('error', 'Vul minimaal een titel, datum en begintijd in.');
        redirect('/meetings');
    }
    if ($end !== '' && $end <= $start) {
        flash('error', 'De eindtijd moet na de begintijd liggen.');
        redirect('/meetings');
    }
    q('INSERT INTO {meetings} (title, description, date, start_time, end_time, place, organizer_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$title, input('description', 2000), $date, $start, $end, input('place', 120), $user['id'], now()]);
    // Organisator is automatisch aanwezig
    q('INSERT INTO {meeting_attendees} (meeting_id, user_id) VALUES (?, ?)', [insert_id(), $user['id']]);
    flash('success', 'De meeting is gepland.');
    redirect('/meetings');
}

function meetings_toggle($id): void
{
    $user = require_setup();
    $m = one('SELECT id FROM {meetings} WHERE id = ?', [(int) $id]);
    if (!$m) abort(404, 'Deze meeting bestaat niet (meer).');
    if (val('SELECT 1 FROM {meeting_attendees} WHERE meeting_id = ? AND user_id = ?', [$m['id'], $user['id']])) {
        q('DELETE FROM {meeting_attendees} WHERE meeting_id = ? AND user_id = ?', [$m['id'], $user['id']]);
        flash('success', 'Je bent afgemeld.');
    } else {
        q('INSERT INTO {meeting_attendees} (meeting_id, user_id) VALUES (?, ?)', [$m['id'], $user['id']]);
        flash('success', 'Je bent aangemeld.');
    }
    redirect('/meetings');
}

function meetings_delete($id): void
{
    $user = require_setup();
    $m = one('SELECT * FROM {meetings} WHERE id = ?', [(int) $id]);
    if (!$m) abort(404, 'Deze meeting bestaat niet (meer).');
    if (can_edit($m['organizer_id'], $user)) {
        q('DELETE FROM {meetings} WHERE id = ?', [$m['id']]);
        flash('success', 'De meeting is geannuleerd.');
    }
    redirect('/meetings');
}
