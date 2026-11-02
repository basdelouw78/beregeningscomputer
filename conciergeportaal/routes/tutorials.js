const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const express = require('express');
const multer = require('multer');
const { db, transaction } = require('../db');
const { requireSetup, notFound, verifyCsrf } = require('../middleware');
const { str, toInt } = require('../helpers');

const router = express.Router();
router.use(requireSetup);

const CATEGORIES = ['Elektra', 'Sanitair', 'Verwarming', 'Sloten & deuren', 'Meubilair', 'ICT & AV', 'Buiten & tuin', 'Schoonmaak', 'Veiligheid', 'Overig'];

const uploadDir = process.env.UPLOAD_DIR || path.join(__dirname, '..', 'uploads');
fs.mkdirSync(uploadDir, { recursive: true });

// Toegestane bestanden: foto's, video's en PDF
const ALLOWED = {
  'image/jpeg': '.jpg', 'image/png': '.png', 'image/webp': '.webp', 'image/gif': '.gif',
  'image/heic': '.heic', 'video/mp4': '.mp4', 'video/quicktime': '.mov', 'video/webm': '.webm',
  'application/pdf': '.pdf',
};

const upload = multer({
  storage: multer.diskStorage({
    destination: uploadDir,
    filename: (req, file, cb) => cb(null, crypto.randomBytes(16).toString('hex') + ALLOWED[file.mimetype]),
  }),
  limits: { fileSize: 100 * 1024 * 1024, files: 8 },
  fileFilter: (req, file, cb) => cb(null, Boolean(ALLOWED[file.mimetype])),
});

function removeFiles(files = []) {
  files.forEach((f) => fs.rm(path.join(uploadDir, f.filename), { force: true }, () => {}));
}

router.get('/', (req, res) => {
  const category = CATEGORIES.includes(req.query.categorie) ? req.query.categorie : '';
  const tutorials = db.prepare(`
    SELECT t.*, u.name AS user_name,
      (SELECT filename FROM tutorial_files f WHERE f.tutorial_id = t.id AND f.mimetype LIKE 'image/%' ORDER BY f.id LIMIT 1) AS cover,
      (SELECT COUNT(*) FROM tutorial_files f WHERE f.tutorial_id = t.id) AS file_count
    FROM tutorials t LEFT JOIN users u ON u.id = t.user_id
    ${category ? 'WHERE t.category = ?' : ''}
    ORDER BY t.created_at DESC`).all(...(category ? [category] : []));
  res.render('tutorials', { title: 'Tutorials', tutorials, categories: CATEGORIES, category });
});

router.get('/nieuw', (req, res) => {
  res.render('tutorial-new', { title: 'Tutorial uploaden', categories: CATEGORIES });
});

router.post('/', (req, res, next) => {
  upload.array('files', 8)(req, res, (err) => {
    if (err) {
      req.flash('error', err.code === 'LIMIT_FILE_SIZE'
        ? 'Een bestand is te groot (max. 100 MB per bestand).'
        : 'Uploaden is mislukt. Maximaal 8 bestanden.');
      removeFiles(req.files);
      return res.redirect('/tutorials/nieuw');
    }
    return verifyCsrf(req, res, (csrfErr) => {
      if (csrfErr) { removeFiles(req.files); return next(csrfErr); }
      const title = str(req.body.title, 150);
      if (!title) {
        removeFiles(req.files);
        req.flash('error', 'Geef je tutorial een titel.');
        return res.redirect('/tutorials/nieuw');
      }
      const category = CATEGORIES.includes(req.body.category) ? req.body.category : 'Overig';
      const id = transaction(() => {
        const r = db.prepare('INSERT INTO tutorials (user_id, title, category, description, steps) VALUES (?, ?, ?, ?, ?)')
          .run(req.user.id, title, category, str(req.body.description, 2000), str(req.body.steps, 20000));
        const ins = db.prepare('INSERT INTO tutorial_files (tutorial_id, filename, original_name, mimetype) VALUES (?, ?, ?, ?)');
        (req.files || []).forEach((f) => ins.run(r.lastInsertRowid, f.filename, f.originalname.slice(0, 200), f.mimetype));
        return r.lastInsertRowid;
      });
      req.flash('success', 'Je tutorial is geüpload. Bedankt voor het delen!');
      return res.redirect(`/tutorials/${id}`);
    });
  });
});

// Bestanden alleen voor ingelogde collega's
router.get('/bestand/:name', (req, res, next) => {
  const file = db.prepare('SELECT * FROM tutorial_files WHERE filename = ?').get(req.params.name);
  if (!file) return next(notFound());
  res.set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'");
  return res.sendFile(path.join(uploadDir, file.filename), {
    headers: { 'Content-Type': file.mimetype, 'Cache-Control': 'private, max-age=86400' },
  });
});

router.get('/:id', (req, res, next) => {
  const tutorial = db.prepare('SELECT t.*, u.name AS user_name FROM tutorials t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?')
    .get(toInt(req.params.id));
  if (!tutorial) return next(notFound());
  const files = db.prepare('SELECT * FROM tutorial_files WHERE tutorial_id = ? ORDER BY id').all(tutorial.id);
  const steps = (tutorial.steps || '').split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
  return res.render('tutorial', { title: tutorial.title, tutorial, files, steps });
});

router.post('/:id/verwijderen', (req, res, next) => {
  const t = db.prepare('SELECT * FROM tutorials WHERE id = ?').get(toInt(req.params.id));
  if (!t) return next(notFound());
  if (t.user_id !== req.user.id && !req.user.is_admin) return res.redirect(`/tutorials/${t.id}`);
  const files = db.prepare('SELECT filename FROM tutorial_files WHERE tutorial_id = ?').all(t.id);
  db.prepare('DELETE FROM tutorials WHERE id = ?').run(t.id);
  removeFiles(files);
  req.flash('success', 'De tutorial is verwijderd.');
  return res.redirect('/tutorials');
});

module.exports = router;
