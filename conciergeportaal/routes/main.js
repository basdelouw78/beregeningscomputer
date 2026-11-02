const express = require('express');
const bcrypt = require('bcryptjs');
const { db } = require('../db');
const { requireSetup } = require('../middleware');
const { isoWeekday, todayISO, str } = require('../helpers');

const router = express.Router();

// Alle roosters met naam van conciërge en school
function schedulesWithNames(where = '', ...params) {
  return db.prepare(`
    SELECT sc.*, u.name AS user_name, u.phone, u.email, s.name AS school_name
    FROM schedules sc
    JOIN users u ON u.id = sc.user_id
    JOIN schools s ON s.id = sc.school_id
    ${where}
    ORDER BY s.name, sc.day, sc.start_time`).all(...params);
}

// Groepeer roosterregels per school: [{school_id, school_name, days:[...]}]
function groupBySchool(rows) {
  const map = new Map();
  rows.forEach((r) => {
    if (!map.has(r.school_id)) map.set(r.school_id, { school_id: r.school_id, school_name: r.school_name, days: [] });
    map.get(r.school_id).days.push(r);
  });
  return [...map.values()];
}

router.get('/', requireSetup, (req, res) => {
  const today = isoWeekday();
  const mySchedule = groupBySchool(schedulesWithNames('WHERE sc.user_id = ?', req.user.id));
  const presentToday = schedulesWithNames('WHERE sc.day = ?', today);

  const stats = {
    available: db.prepare(`SELECT COUNT(*) AS n FROM materials m
      WHERE NOT EXISTS (SELECT 1 FROM loans l WHERE l.material_id = m.id AND l.returned_at IS NULL)`).get().n,
    myLoans: db.prepare('SELECT COUNT(*) AS n FROM loans WHERE borrower_id = ? AND returned_at IS NULL')
      .get(req.user.id).n,
    meetings: db.prepare('SELECT COUNT(*) AS n FROM meetings WHERE date >= ?').get(todayISO()).n,
    openQuestions: db.prepare('SELECT COUNT(*) AS n FROM questions WHERE solved = 0').get().n,
    tutorials: db.prepare('SELECT COUNT(*) AS n FROM tutorials').get().n,
  };
  const nextMeeting = db.prepare(`SELECT * FROM meetings WHERE date >= ? ORDER BY date, start_time LIMIT 1`)
    .get(todayISO());
  const latestQuestions = db.prepare(`
    SELECT q.*, u.name AS user_name, (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id) AS answer_count
    FROM questions q LEFT JOIN users u ON u.id = q.user_id ORDER BY q.created_at DESC LIMIT 3`).all();

  res.render('dashboard', {
    title: 'Home', mySchedule, presentToday, today, stats, nextMeeting, latestQuestions,
  });
});

router.get('/collegas', requireSetup, (req, res) => {
  const schools = db.prepare('SELECT * FROM schools ORDER BY name').all();
  const rows = schedulesWithNames();
  const users = db.prepare('SELECT id, name, email, phone FROM users WHERE setup_done = 1 ORDER BY name').all();
  const people = users.map((u) => ({ ...u, locations: groupBySchool(rows.filter((r) => r.user_id === u.id)) }));
  // Per school: wie werkt er
  const bySchool = schools.map((s) => ({
    ...s,
    people: [...new Set(rows.filter((r) => r.school_id === s.id).map((r) => r.user_name))],
  }));
  res.render('colleagues', { title: "Collega's", people, bySchool });
});

router.get('/profiel', requireSetup, (req, res) => {
  res.render('profile', { title: 'Mijn profiel' });
});

router.post('/profiel', requireSetup, async (req, res, next) => {
  try {
    const name = str(req.body.name, 80);
    const phone = str(req.body.phone, 30);
    if (!name) {
      req.flash('error', 'Je naam mag niet leeg zijn.');
      return res.redirect('/profiel');
    }
    db.prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?').run(name, phone, req.user.id);

    const newPw = typeof req.body.new_password === 'string' ? req.body.new_password : '';
    if (newPw) {
      const { password_hash: hash } = db.prepare('SELECT password_hash FROM users WHERE id = ?').get(req.user.id);
      if (!(await bcrypt.compare(req.body.current_password || '', hash))) {
        req.flash('error', 'Je huidige wachtwoord klopt niet; het wachtwoord is niet gewijzigd.');
        return res.redirect('/profiel');
      }
      if (newPw.length < 8) {
        req.flash('error', 'Het nieuwe wachtwoord moet minimaal 8 tekens zijn.');
        return res.redirect('/profiel');
      }
      db.prepare('UPDATE users SET password_hash = ? WHERE id = ?').run(await bcrypt.hash(newPw, 12), req.user.id);
    }
    req.flash('success', 'Je profiel is bijgewerkt.');
    return res.redirect('/profiel');
  } catch (err) { return next(err); }
});

module.exports = router;
