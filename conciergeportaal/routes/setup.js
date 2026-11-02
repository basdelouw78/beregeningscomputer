const express = require('express');
const { db, transaction } = require('../db');
const { requireLogin } = require('../middleware');
const { DAYS, isTime, toInt } = require('../helpers');

const router = express.Router();

router.get('/setup', requireLogin, (req, res) => {
  const schools = db.prepare('SELECT * FROM schools ORDER BY name').all();
  const rows = db.prepare('SELECT school_id, day, start_time, end_time FROM schedules WHERE user_id = ?')
    .all(req.user.id);
  // { schoolId: { day: {start, end} } }
  const current = {};
  rows.forEach((r) => {
    current[r.school_id] = current[r.school_id] || {};
    current[r.school_id][r.day] = { start: r.start_time, end: r.end_time };
  });
  res.render('setup', { title: 'Mijn locaties', schools, current, errors: [] });
});

router.post('/setup', requireLogin, (req, res) => {
  const schools = db.prepare('SELECT * FROM schools ORDER BY name').all();
  const validIds = new Set(schools.map((s) => s.id));
  const selected = [...new Set([].concat(req.body.schools || []).map(toInt))]
    .filter((id) => validIds.has(id));

  const errors = [];
  const entries = [];
  const current = {};
  selected.forEach((sid) => {
    current[sid] = {};
    let any = false;
    DAYS.forEach(({ n, long }) => {
      if (!req.body[`s${sid}_d${n}_on`]) return;
      const start = req.body[`s${sid}_d${n}_start`];
      const end = req.body[`s${sid}_d${n}_end`];
      current[sid][n] = { start, end };
      any = true;
      const school = schools.find((s) => s.id === sid).name;
      if (!isTime(start) || !isTime(end)) {
        errors.push(`Vul begin- en eindtijd in voor ${long} bij ${school}.`);
      } else if (start >= end) {
        errors.push(`De eindtijd moet na de begintijd liggen (${long}, ${school}).`);
      } else {
        entries.push([sid, n, start, end]);
      }
    });
    if (!any) {
      errors.push(`Kies minstens één dag voor ${schools.find((s) => s.id === sid).name}.`);
    }
  });
  if (!selected.length) errors.push('Kies op de kaart minstens één school waar je werkt.');

  if (errors.length) {
    return res.status(400).render('setup', { title: 'Mijn locaties', schools, current, errors });
  }

  const firstTime = !req.user.setup_done;
  transaction(() => {
    db.prepare('DELETE FROM schedules WHERE user_id = ?').run(req.user.id);
    const insert = db.prepare(
      'INSERT INTO schedules (user_id, school_id, day, start_time, end_time) VALUES (?, ?, ?, ?, ?)');
    entries.forEach((e) => insert.run(req.user.id, ...e));
    db.prepare('UPDATE users SET setup_done = 1 WHERE id = ?').run(req.user.id);
  });
  req.flash('success', firstTime ? 'Top, je bent helemaal klaar! Welkom in het portaal.' : 'Je locaties en tijden zijn opgeslagen.');
  return res.redirect('/');
});

module.exports = router;
