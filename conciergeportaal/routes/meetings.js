const express = require('express');
const { db } = require('../db');
const { requireSetup, notFound } = require('../middleware');
const { str, isDate, isTime, toInt, todayISO } = require('../helpers');

const router = express.Router();
router.use(requireSetup);

function withAttendees(meetings, userId) {
  const stmt = db.prepare(`SELECT u.id, u.name FROM meeting_attendees a JOIN users u ON u.id = a.user_id
    WHERE a.meeting_id = ? ORDER BY u.name`);
  return meetings.map((m) => {
    const attendees = stmt.all(m.id);
    return { ...m, attendees, attending: attendees.some((a) => a.id === userId) };
  });
}

router.get('/', (req, res) => {
  const base = `SELECT m.*, u.name AS organizer_name FROM meetings m LEFT JOIN users u ON u.id = m.organizer_id`;
  const upcoming = db.prepare(`${base} WHERE m.date >= ? ORDER BY m.date, m.start_time`).all(todayISO());
  const past = db.prepare(`${base} WHERE m.date < ? ORDER BY m.date DESC LIMIT 10`).all(todayISO());
  const schools = db.prepare('SELECT name FROM schools ORDER BY name').all();
  res.render('meetings', {
    title: 'Meeting plannen',
    upcoming: withAttendees(upcoming, req.user.id),
    past: withAttendees(past, req.user.id),
    schools,
  });
});

router.post('/', (req, res) => {
  const v = {
    title: str(req.body.title, 120),
    description: str(req.body.description, 2000),
    date: req.body.date,
    start: req.body.start_time,
    end: req.body.end_time || '',
    place: str(req.body.place, 120),
  };
  if (!v.title || !isDate(v.date) || !isTime(v.start) || (v.end && !isTime(v.end))) {
    req.flash('error', 'Vul minimaal een titel, datum en begintijd in.');
    return res.redirect('/meetings');
  }
  if (v.end && v.end <= v.start) {
    req.flash('error', 'De eindtijd moet na de begintijd liggen.');
    return res.redirect('/meetings');
  }
  const r = db.prepare(`INSERT INTO meetings (title, description, date, start_time, end_time, place, organizer_id)
    VALUES (?, ?, ?, ?, ?, ?, ?)`).run(v.title, v.description, v.date, v.start, v.end, v.place, req.user.id);
  // Organisator is automatisch aanwezig
  db.prepare('INSERT INTO meeting_attendees (meeting_id, user_id) VALUES (?, ?)').run(r.lastInsertRowid, req.user.id);
  req.flash('success', 'De meeting is gepland.');
  return res.redirect('/meetings');
});

router.post('/:id/aanmelden', (req, res, next) => {
  const m = db.prepare('SELECT id FROM meetings WHERE id = ?').get(toInt(req.params.id));
  if (!m) return next(notFound());
  const exists = db.prepare('SELECT 1 FROM meeting_attendees WHERE meeting_id = ? AND user_id = ?').get(m.id, req.user.id);
  if (exists) {
    db.prepare('DELETE FROM meeting_attendees WHERE meeting_id = ? AND user_id = ?').run(m.id, req.user.id);
    req.flash('success', 'Je bent afgemeld.');
  } else {
    db.prepare('INSERT INTO meeting_attendees (meeting_id, user_id) VALUES (?, ?)').run(m.id, req.user.id);
    req.flash('success', 'Je bent aangemeld.');
  }
  return res.redirect('/meetings');
});

router.post('/:id/verwijderen', (req, res, next) => {
  const m = db.prepare('SELECT * FROM meetings WHERE id = ?').get(toInt(req.params.id));
  if (!m) return next(notFound());
  if (m.organizer_id === req.user.id || req.user.is_admin) {
    db.prepare('DELETE FROM meetings WHERE id = ?').run(m.id);
    req.flash('success', 'De meeting is geannuleerd.');
  }
  return res.redirect('/meetings');
});

module.exports = router;
