const express = require('express');
const { db } = require('../db');
const { requireSetup, notFound } = require('../middleware');
const { str, toInt } = require('../helpers');

const router = express.Router();
router.use(requireSetup);

function getQuestion(id) {
  return db.prepare('SELECT q.*, u.name AS user_name FROM questions q LEFT JOIN users u ON u.id = q.user_id WHERE q.id = ?')
    .get(toInt(id));
}

router.get('/', (req, res) => {
  const questions = db.prepare(`
    SELECT q.*, u.name AS user_name, (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id) AS answer_count
    FROM questions q LEFT JOIN users u ON u.id = q.user_id
    ORDER BY q.solved, q.created_at DESC`).all();
  res.render('questions', { title: 'Vragen & antwoorden', questions });
});

router.post('/', (req, res) => {
  const title = str(req.body.title, 200);
  const body = str(req.body.body, 5000);
  if (!title) {
    req.flash('error', 'Typ je vraag in het vraagveld.');
    return res.redirect('/vragen');
  }
  const r = db.prepare('INSERT INTO questions (user_id, title, body) VALUES (?, ?, ?)').run(req.user.id, title, body);
  req.flash('success', 'Je vraag is geplaatst. Collega\'s kunnen nu reageren.');
  return res.redirect(`/vragen/${r.lastInsertRowid}`);
});

router.get('/:id', (req, res, next) => {
  const question = getQuestion(req.params.id);
  if (!question) return next(notFound());
  const answers = db.prepare(`SELECT a.*, u.name AS user_name FROM answers a LEFT JOIN users u ON u.id = a.user_id
    WHERE a.question_id = ? ORDER BY a.created_at`).all(question.id);
  return res.render('question', { title: question.title, question, answers });
});

router.post('/:id/antwoord', (req, res, next) => {
  const question = getQuestion(req.params.id);
  if (!question) return next(notFound());
  const body = str(req.body.body, 5000);
  if (body) {
    db.prepare('INSERT INTO answers (question_id, user_id, body) VALUES (?, ?, ?)').run(question.id, req.user.id, body);
    req.flash('success', 'Bedankt voor je antwoord!');
  }
  return res.redirect(`/vragen/${question.id}`);
});

router.post('/:id/opgelost', (req, res, next) => {
  const question = getQuestion(req.params.id);
  if (!question) return next(notFound());
  if (question.user_id === req.user.id || req.user.is_admin) {
    db.prepare('UPDATE questions SET solved = 1 - solved WHERE id = ?').run(question.id);
  }
  return res.redirect(`/vragen/${question.id}`);
});

router.post('/:id/verwijderen', (req, res, next) => {
  const question = getQuestion(req.params.id);
  if (!question) return next(notFound());
  if (question.user_id === req.user.id || req.user.is_admin) {
    db.prepare('DELETE FROM questions WHERE id = ?').run(question.id);
    req.flash('success', 'De vraag is verwijderd.');
    return res.redirect('/vragen');
  }
  return res.redirect(`/vragen/${question.id}`);
});

router.post('/antwoord/:id/verwijderen', (req, res, next) => {
  const a = db.prepare('SELECT * FROM answers WHERE id = ?').get(toInt(req.params.id));
  if (!a) return next(notFound());
  if (a.user_id === req.user.id || req.user.is_admin) {
    db.prepare('DELETE FROM answers WHERE id = ?').run(a.id);
  }
  return res.redirect(`/vragen/${a.question_id}`);
});

module.exports = router;
