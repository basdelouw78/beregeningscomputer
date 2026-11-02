const crypto = require('node:crypto');

function verifyCsrf(req, res, next) {
  const sent = (req.body && req.body._csrf) || req.get('x-csrf-token') || '';
  const expected = req.session.csrf || '';
  const ok = sent.length === expected.length && expected.length > 0 &&
    crypto.timingSafeEqual(Buffer.from(sent), Buffer.from(expected));
  if (ok) return next();
  const err = new Error('CSRF-token ongeldig');
  err.status = 403;
  err.userMessage = 'Je sessie is verlopen. Ga terug, ververs de pagina en probeer het opnieuw.';
  return next(err);
}

// Alleen voor ingelogde gebruikers
function requireLogin(req, res, next) {
  if (!req.user) {
    if (req.method === 'GET') req.session.returnTo = req.originalUrl;
    return res.redirect('/login');
  }
  return next();
}

// Ingelogd én de locatie-setup afgerond
function requireSetup(req, res, next) {
  return requireLogin(req, res, () => {
    if (!req.user.setup_done) return res.redirect('/setup');
    return next();
  });
}

function requireAdmin(req, res, next) {
  return requireSetup(req, res, () => {
    if (!req.user.is_admin) {
      const err = new Error('Geen beheerder');
      err.status = 403;
      err.userMessage = 'Deze pagina is alleen voor beheerders.';
      return next(err);
    }
    return next();
  });
}

function notFound() {
  const err = new Error('Niet gevonden');
  err.status = 404;
  err.userMessage = 'Dit item bestaat niet (meer).';
  return err;
}

module.exports = { verifyCsrf, requireLogin, requireSetup, requireAdmin, notFound };
