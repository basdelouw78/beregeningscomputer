const express = require('express');
const { db } = require('../db');
const { requireSetup, notFound } = require('../middleware');
const { str, isDate, toInt } = require('../helpers');

const router = express.Router();
router.use(requireSetup);

function getMaterial(id) {
  return db.prepare('SELECT * FROM materials WHERE id = ?').get(toInt(id));
}
function activeLoan(materialId) {
  return db.prepare('SELECT * FROM loans WHERE material_id = ? AND returned_at IS NULL').get(materialId);
}

router.get('/', (req, res) => {
  const materials = db.prepare(`
    SELECT m.*, s.name AS school_name, o.name AS owner_name,
      l.id AS loan_id, l.due_date, l.borrower_id, b.name AS borrower_name, ls.name AS loan_school
    FROM materials m
    LEFT JOIN schools s ON s.id = m.school_id
    LEFT JOIN users o ON o.id = m.owner_id
    LEFT JOIN loans l ON l.material_id = m.id AND l.returned_at IS NULL
    LEFT JOIN users b ON b.id = l.borrower_id
    LEFT JOIN schools ls ON ls.id = l.school_id
    ORDER BY m.name COLLATE NOCASE`).all();
  const schools = db.prepare('SELECT id, name FROM schools ORDER BY name').all();
  const mySchools = db.prepare('SELECT DISTINCT school_id FROM schedules WHERE user_id = ?')
    .all(req.user.id).map((r) => r.school_id);
  res.render('materials', { title: 'Materialen uitlenen', materials, schools, mySchools });
});

router.post('/', (req, res) => {
  const name = str(req.body.name, 100);
  const description = str(req.body.description, 1000);
  const schoolId = toInt(req.body.school_id);
  if (!name) {
    req.flash('error', 'Geef het materiaal een naam.');
    return res.redirect('/materialen');
  }
  db.prepare('INSERT INTO materials (name, description, school_id, owner_id) VALUES (?, ?, ?, ?)')
    .run(name, description, schoolId, req.user.id);
  req.flash('success', `"${name}" staat nu in de lijst.`);
  return res.redirect('/materialen');
});

router.post('/:id/lenen', (req, res, next) => {
  const m = getMaterial(req.params.id);
  if (!m) return next(notFound());
  if (activeLoan(m.id)) {
    req.flash('error', 'Dit materiaal is al uitgeleend.');
    return res.redirect('/materialen');
  }
  const due = isDate(req.body.due_date) ? req.body.due_date : null;
  db.prepare('INSERT INTO loans (material_id, borrower_id, school_id, due_date, note) VALUES (?, ?, ?, ?, ?)')
    .run(m.id, req.user.id, toInt(req.body.school_id), due, str(req.body.note, 300));
  req.flash('success', `Je hebt "${m.name}" geleend.`);
  return res.redirect('/materialen');
});

router.post('/:id/terug', (req, res, next) => {
  const m = getMaterial(req.params.id);
  if (!m) return next(notFound());
  const loan = activeLoan(m.id);
  if (loan && (loan.borrower_id === req.user.id || m.owner_id === req.user.id || req.user.is_admin)) {
    db.prepare("UPDATE loans SET returned_at = datetime('now') WHERE id = ?").run(loan.id);
    req.flash('success', `"${m.name}" is teruggebracht.`);
  }
  return res.redirect('/materialen');
});

router.post('/:id/verwijderen', (req, res, next) => {
  const m = getMaterial(req.params.id);
  if (!m) return next(notFound());
  if (m.owner_id === req.user.id || req.user.is_admin) {
    db.prepare('DELETE FROM materials WHERE id = ?').run(m.id);
    req.flash('success', `"${m.name}" is verwijderd.`);
  }
  return res.redirect('/materialen');
});

module.exports = router;
