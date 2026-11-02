const express = require('express');
const bcrypt = require('bcryptjs');
const { db } = require('../db');
const { str } = require('../helpers');

const router = express.Router();

// Eenvoudige bescherming tegen wachtwoord-raden: max 10 pogingen per 15 min per IP
const attempts = new Map();
function tooManyAttempts(ip) {
  const now = Date.now();
  const entry = attempts.get(ip);
  if (!entry || now - entry.start > 15 * 60 * 1000) {
    attempts.set(ip, { start: now, count: 1 });
    return false;
  }
  entry.count += 1;
  return entry.count > 10;
}

const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);

function startSession(req, res, userId) {
  const returnTo = req.session.returnTo;
  req.session.regenerate((err) => {
    if (err) throw err;
    req.session.userId = userId;
    const user = db.prepare('SELECT setup_done FROM users WHERE id = ?').get(userId);
    res.redirect(user.setup_done ? (returnTo || '/') : '/setup');
  });
}

router.get('/login', (req, res) => {
  if (req.user) return res.redirect('/');
  res.render('login', { title: 'Inloggen', email: '' });
});

router.post('/login', async (req, res, next) => {
  try {
    const email = str(req.body.email, 200).toLowerCase();
    const password = typeof req.body.password === 'string' ? req.body.password : '';
    if (tooManyAttempts(req.ip)) {
      return res.status(429).render('login', {
        title: 'Inloggen', email,
        error: 'Te veel pogingen. Wacht een kwartier en probeer het opnieuw.',
      });
    }
    const user = db.prepare('SELECT id, password_hash FROM users WHERE email = ?').get(email);
    const ok = user && await bcrypt.compare(password, user.password_hash);
    if (!ok) {
      return res.status(401).render('login', {
        title: 'Inloggen', email, error: 'E-mailadres of wachtwoord klopt niet.',
      });
    }
    attempts.delete(req.ip);
    return startSession(req, res, user.id);
  } catch (err) { return next(err); }
});

router.get('/registreren', (req, res) => {
  if (req.user) return res.redirect('/');
  res.render('register', {
    title: 'Account aanmaken', values: {}, needsCode: Boolean(process.env.REGISTRATIE_CODE),
  });
});

router.post('/registreren', async (req, res, next) => {
  try {
    const values = {
      name: str(req.body.name, 80),
      email: str(req.body.email, 200).toLowerCase(),
      phone: str(req.body.phone, 30),
    };
    const password = typeof req.body.password === 'string' ? req.body.password : '';
    const needsCode = Boolean(process.env.REGISTRATIE_CODE);
    const errors = [];
    if (!values.name) errors.push('Vul je naam in.');
    if (!isEmail(values.email)) errors.push('Vul een geldig e-mailadres in.');
    if (password.length < 8) errors.push('Kies een wachtwoord van minimaal 8 tekens.');
    if (password !== req.body.password2) errors.push('De wachtwoorden zijn niet gelijk.');
    if (needsCode && str(req.body.code) !== process.env.REGISTRATIE_CODE) {
      errors.push('De registratiecode klopt niet. Vraag deze na bij je leidinggevende.');
    }
    if (!errors.length && db.prepare('SELECT 1 FROM users WHERE email = ?').get(values.email)) {
      errors.push('Er bestaat al een account met dit e-mailadres.');
    }
    if (errors.length) {
      return res.status(400).render('register', { title: 'Account aanmaken', values, errors, needsCode });
    }
    const hash = await bcrypt.hash(password, 12);
    // De allereerste gebruiker wordt automatisch beheerder
    const isFirst = db.prepare('SELECT COUNT(*) AS n FROM users').get().n === 0;
    const result = db.prepare(
      'INSERT INTO users (email, password_hash, name, phone, is_admin) VALUES (?, ?, ?, ?, ?)',
    ).run(values.email, hash, values.name, values.phone, isFirst ? 1 : 0);
    return startSession(req, res, Number(result.lastInsertRowid));
  } catch (err) { return next(err); }
});

router.post('/logout', (req, res) => {
  req.session.destroy(() => {
    res.clearCookie('portaal.sid');
    res.redirect('/login');
  });
});

module.exports = router;
