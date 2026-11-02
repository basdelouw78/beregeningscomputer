// Eenvoudige SQLite-database (ingebouwd in Node.js, geen extra installatie nodig).
const fs = require('node:fs');
const path = require('node:path');
const { DatabaseSync } = require('node:sqlite');

const dataDir = process.env.DATA_DIR || path.join(__dirname, 'data');
fs.mkdirSync(dataDir, { recursive: true });

const db = new DatabaseSync(path.join(dataDir, 'portaal.db'));
db.exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON;');

db.exec(`
CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  email         TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  name          TEXT NOT NULL,
  phone         TEXT DEFAULT '',
  is_admin      INTEGER NOT NULL DEFAULT 0,
  setup_done    INTEGER NOT NULL DEFAULT 0,
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS schools (
  id      INTEGER PRIMARY KEY AUTOINCREMENT,
  name    TEXT NOT NULL,
  address TEXT DEFAULT '',
  lat     REAL NOT NULL,
  lng     REAL NOT NULL
);

-- Op welke dagen/uren een conciërge op welke school werkt
CREATE TABLE IF NOT EXISTS schedules (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  school_id  INTEGER NOT NULL REFERENCES schools(id) ON DELETE CASCADE,
  day        INTEGER NOT NULL CHECK (day BETWEEN 1 AND 7),
  start_time TEXT NOT NULL,
  end_time   TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS materials (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT NOT NULL,
  description TEXT DEFAULT '',
  school_id   INTEGER REFERENCES schools(id) ON DELETE SET NULL,
  owner_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS loans (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  material_id INTEGER NOT NULL REFERENCES materials(id) ON DELETE CASCADE,
  borrower_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  school_id   INTEGER REFERENCES schools(id) ON DELETE SET NULL,
  due_date    TEXT,
  note        TEXT DEFAULT '',
  created_at  TEXT NOT NULL DEFAULT (datetime('now')),
  returned_at TEXT
);

CREATE TABLE IF NOT EXISTS meetings (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  title        TEXT NOT NULL,
  description  TEXT DEFAULT '',
  date         TEXT NOT NULL,
  start_time   TEXT NOT NULL,
  end_time     TEXT DEFAULT '',
  place        TEXT DEFAULT '',
  organizer_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS meeting_attendees (
  meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  PRIMARY KEY (meeting_id, user_id)
);

CREATE TABLE IF NOT EXISTS questions (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  title      TEXT NOT NULL,
  body       TEXT DEFAULT '',
  solved     INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS answers (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
  user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
  body        TEXT NOT NULL,
  created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS tutorials (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
  title       TEXT NOT NULL,
  category    TEXT NOT NULL DEFAULT 'Overig',
  description TEXT DEFAULT '',
  steps       TEXT DEFAULT '',
  created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS tutorial_files (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  tutorial_id   INTEGER NOT NULL REFERENCES tutorials(id) ON DELETE CASCADE,
  filename      TEXT NOT NULL,
  original_name TEXT NOT NULL,
  mimetype      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
  sid     TEXT PRIMARY KEY,
  sess    TEXT NOT NULL,
  expires INTEGER NOT NULL
);
`);

// Voorbeeldscholen bij de eerste start. Pas ze aan via "Scholen beheren" (admin).
const schoolCount = db.prepare('SELECT COUNT(*) AS n FROM schools').get().n;
if (schoolCount === 0) {
  const insert = db.prepare('INSERT INTO schools (name, address, lat, lng) VALUES (?, ?, ?, ?)');
  [
    ['Basisschool De Regenboog', 'Voorbeeldstraat 1', 52.0990, 5.1050],
    ['OBS De Linde', 'Lindelaan 12', 52.0855, 5.1290],
    ['CBS Het Kompas', 'Kompasweg 3', 52.1080, 5.1400],
    ['Montessorischool De Wilg', 'Wilgenhof 8', 52.0760, 5.0980],
    ['Daltonschool Het Anker', 'Havenkade 20', 52.0930, 5.1600],
    ['KBS De Vlinder', 'Vlinderpad 5', 52.1150, 5.0900],
  ].forEach((s) => insert.run(...s));
}

function transaction(fn) {
  db.exec('BEGIN');
  try {
    const result = fn();
    db.exec('COMMIT');
    return result;
  } catch (err) {
    db.exec('ROLLBACK');
    throw err;
  }
}

module.exports = { db, transaction, dataDir };
