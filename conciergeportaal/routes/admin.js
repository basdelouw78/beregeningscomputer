const express = require('express');
const { db } = require('../db');
const { requireAdmin, notFound } = require('../middleware');
const { str, toInt } = require('../helpers');

const router = express.Router();
router.use(requireAdmin);

function coords(body) {
  const lat = Number.parseFloat(body.lat);
  const lng = Number.parseFloat(body.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
  return { lat, lng };
}

router.get('/scholen', (req, res) => {
  const schools = db.prepare(`SELECT s.*, (SELECT COUNT(DISTINCT user_id) FROM schedules sc WHERE sc.school_id = s.id) AS people
    FROM schools s ORDER BY name`).all();
  const users = db.prepare('SELECT id, name, email, is_admin FROM users ORDER BY name').all();
  res.render('admin-schools', { title: 'Beheer', schools, users });
});

router.post('/scholen', (req, res) => {
  const name = str(req.body.name, 120);
  const c = coords(req.body);
  if (!name || !c) {
    req.flash('error', 'Vul een naam in en tik op de kaart om de locatie te kiezen.');
    return res.redirect('/admin/scholen');
  }
  db.prepare('INSERT INTO schools (name, address, lat, lng) VALUES (?, ?, ?, ?)').run(name, str(req.body.address, 200), c.lat, c.lng);
  req.flash('success', `${name} is toegevoegd.`);
  return res.redirect('/admin/scholen');
});

router.post('/scholen/:id', (req, res, next) => {
  const s = db.prepare('SELECT * FROM schools WHERE id = ?').get(toInt(req.params.id));
  if (!s) return next(notFound());
  const name = str(req.body.name, 120) || s.name;
  const c = coords(req.body) || s;
  db.prepare('UPDATE schools SET name = ?, address = ?, lat = ?, lng = ? WHERE id = ?')
    .run(name, str(req.body.address, 200), c.lat, c.lng, s.id);
  req.flash('success', `${name} is bijgewerkt.`);
  return res.redirect('/admin/scholen');
});

router.post('/scholen/:id/verwijderen', (req, res, next) => {
  const s = db.prepare('SELECT * FROM schools WHERE id = ?').get(toInt(req.params.id));
  if (!s) return next(notFound());
  db.prepare('DELETE FROM schools WHERE id = ?').run(s.id);
  // Conciërges zonder locatie moeten hun setup opnieuw doen
  db.prepare('UPDATE users SET setup_done = 0 WHERE setup_done = 1 AND id NOT IN (SELECT DISTINCT user_id FROM schedules)').run();
  req.flash('success', `${s.name} is verwijderd.`);
  return res.redirect('/admin/scholen');
});

router.post('/gebruikers/:id/admin', (req, res, next) => {
  const u = db.prepare('SELECT * FROM users WHERE id = ?').get(toInt(req.params.id));
  if (!u) return next(notFound());
  if (u.id === req.user.id) {
    req.flash('error', 'Je kunt je eigen beheerrechten niet intrekken.');
  } else {
    db.prepare('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?').run(u.id);
    req.flash('success', `Beheerrechten van ${u.name} aangepast.`);
  }
  return res.redirect('/admin/scholen');
});

module.exports = router;
