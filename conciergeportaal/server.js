const path = require('node:path');
const crypto = require('node:crypto');
const express = require('express');
const session = require('express-session');
const { db } = require('./db');
const SqliteStore = require('./session-store')(session);
const { DAYS, formatDate, formatDateTime, timeAgo, todayISO, initials, icon, json } = require('./helpers');

const app = express();
const PORT = process.env.PORT || 3000;
const isProd = process.env.NODE_ENV === 'production';

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));
if (isProd) app.set('trust proxy', 1);

// Beveiligingsheaders
app.use((req, res, next) => {
  res.set('X-Content-Type-Options', 'nosniff');
  res.set('X-Frame-Options', 'DENY');
  res.set('Referrer-Policy', 'same-origin');
  next();
});

app.use(express.static(path.join(__dirname, 'public'), { maxAge: isProd ? '7d' : 0 }));
app.use('/vendor/leaflet', express.static(path.join(__dirname, 'node_modules', 'leaflet', 'dist'), { maxAge: '30d' }));
app.use(express.urlencoded({ extended: false, limit: '200kb' }));

// Sessiegeheim: zet SESSION_SECRET in productie. Anders een vast geheim in data/.
const secret = process.env.SESSION_SECRET || require('./helpers').localSecret();

app.use(session({
  store: new SqliteStore(db),
  secret,
  name: 'portaal.sid',
  resave: false,
  saveUninitialized: false,
  rolling: true,
  cookie: {
    httpOnly: true,
    sameSite: 'lax',
    secure: isProd,
    maxAge: 1000 * 60 * 60 * 24 * 30, // 30 dagen ingelogd blijven
  },
}));

// Ingelogde gebruiker, CSRF-token, flash-meldingen en helpers voor alle pagina's
app.use((req, res, next) => {
  if (!req.session.csrf) req.session.csrf = crypto.randomBytes(24).toString('hex');
  res.locals.csrfToken = req.session.csrf;
  res.locals.flash = req.session.flash || [];
  delete req.session.flash;
  res.locals.path = req.path;
  res.locals.user = null;
  if (req.session.userId) {
    const user = db.prepare('SELECT id, email, name, phone, is_admin, setup_done FROM users WHERE id = ?')
      .get(req.session.userId);
    if (user) {
      req.user = user;
      res.locals.user = user;
    } else {
      delete req.session.userId;
    }
  }
  Object.assign(res.locals, { DAYS, formatDate, formatDateTime, timeAgo, todayISO, initials, icon, json });
  req.flash = (type, message) => {
    req.session.flash = req.session.flash || [];
    req.session.flash.push({ type, message });
  };
  next();
});

// CSRF-controle voor alle formulieren (uploads controleren dit zelf na het verwerken van het bestand)
const { verifyCsrf } = require('./middleware');
app.use((req, res, next) => {
  if (req.method !== 'POST') return next();
  if ((req.headers['content-type'] || '').startsWith('multipart/form-data')) return next();
  return verifyCsrf(req, res, next);
});

app.use(require('./routes/auth'));
app.use(require('./routes/setup'));
app.use(require('./routes/main'));
app.use('/materialen', require('./routes/materials'));
app.use('/meetings', require('./routes/meetings'));
app.use('/vragen', require('./routes/qa'));
app.use('/tutorials', require('./routes/tutorials'));
app.use('/admin', require('./routes/admin'));

app.use((req, res) => {
  res.status(404).render('error', { title: 'Niet gevonden', message: 'Deze pagina bestaat niet (meer).' });
});

// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  console.error(err);
  const message = err.userMessage || 'Er ging iets mis. Probeer het opnieuw.';
  res.status(err.status || 500).render('error', { title: 'Oeps', message });
});

app.listen(PORT, () => {
  console.log(`Conciërgeportaal draait op http://localhost:${PORT}`);
});
