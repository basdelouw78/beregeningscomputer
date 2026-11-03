<?php
// Vragen & antwoorden.

function question_or_404($id): array
{
    $q = one('SELECT q.*, u.name AS user_name FROM {questions} q LEFT JOIN {users} u ON u.id = q.user_id WHERE q.id = ?', [(int) $id]);
    if (!$q) abort(404, 'Deze vraag bestaat niet (meer).');
    return $q;
}

function qa_index(): void
{
    require_setup();
    $questions = all('SELECT q.*, u.name AS user_name,
            (SELECT COUNT(*) FROM {answers} a WHERE a.question_id = q.id) AS answer_count
        FROM {questions} q LEFT JOIN {users} u ON u.id = q.user_id
        ORDER BY q.solved, q.created_at DESC, q.id DESC');
    render('questions', compact('questions'), ['title' => 'Vragen & antwoorden']);
}

function qa_create(): void
{
    $user = require_setup();
    $title = input('title', 200);
    if ($title === '') {
        flash('error', 'Typ je vraag in het vraagveld.');
        redirect('/vragen');
    }
    q('INSERT INTO {questions} (user_id, title, body, created_at) VALUES (?, ?, ?, ?)', [$user['id'], $title, input('body', 5000), now()]);
    $id = insert_id();
    flash('success', "Je vraag is geplaatst. Collega's kunnen nu reageren.");
    redirect("/vragen/$id");
}

function qa_show($id): void
{
    require_setup();
    $question = question_or_404($id);
    $answers = all('SELECT a.*, u.name AS user_name FROM {answers} a LEFT JOIN {users} u ON u.id = a.user_id
        WHERE a.question_id = ? ORDER BY a.created_at, a.id', [$question['id']]);
    render('question', compact('question', 'answers'), ['title' => $question['title']]);
}

function qa_answer($id): void
{
    $user = require_setup();
    $question = question_or_404($id);
    $body = input('body', 5000);
    if ($body !== '') {
        q('INSERT INTO {answers} (question_id, user_id, body, created_at) VALUES (?, ?, ?, ?)', [$question['id'], $user['id'], $body, now()]);
        flash('success', 'Bedankt voor je antwoord!');
    }
    redirect("/vragen/{$question['id']}");
}

function qa_solved($id): void
{
    $user = require_setup();
    $question = question_or_404($id);
    if (can_edit($question['user_id'], $user)) {
        q('UPDATE {questions} SET solved = 1 - solved WHERE id = ?', [$question['id']]);
    }
    redirect("/vragen/{$question['id']}");
}

function qa_delete($id): void
{
    $user = require_setup();
    $question = question_or_404($id);
    if (can_edit($question['user_id'], $user)) {
        q('DELETE FROM {questions} WHERE id = ?', [$question['id']]);
        flash('success', 'De vraag is verwijderd.');
        redirect('/vragen');
    }
    redirect("/vragen/{$question['id']}");
}

function qa_delete_answer($id): void
{
    $user = require_setup();
    $a = one('SELECT * FROM {answers} WHERE id = ?', [(int) $id]);
    if (!$a) abort(404, 'Dit antwoord bestaat niet (meer).');
    if (can_edit($a['user_id'], $user)) q('DELETE FROM {answers} WHERE id = ?', [$a['id']]);
    redirect("/vragen/{$a['question_id']}");
}
