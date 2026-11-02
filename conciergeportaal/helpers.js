const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const DAYS = [
  { n: 1, short: 'ma', long: 'maandag' },
  { n: 2, short: 'di', long: 'dinsdag' },
  { n: 3, short: 'wo', long: 'woensdag' },
  { n: 4, short: 'do', long: 'donderdag' },
  { n: 5, short: 'vr', long: 'vrijdag' },
  { n: 6, short: 'za', long: 'zaterdag' },
  { n: 7, short: 'zo', long: 'zondag' },
];

const MONTHS = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

// Lokale datum als JJJJ-MM-DD
function todayISO(offsetDays = 0) {
  const d = new Date();
  d.setDate(d.getDate() + offsetDays);
  const pad = (x) => String(x).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

// Dag van de week volgens ISO: 1 = maandag ... 7 = zondag
function isoWeekday(date = new Date()) {
  return ((date.getDay() + 6) % 7) + 1;
}

function formatDate(iso, withWeekday = true) {
  if (!iso) return '';
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  const date = new Date(y, m - 1, d);
  const wd = DAYS[isoWeekday(date) - 1].short;
  return `${withWeekday ? wd + ' ' : ''}${d} ${MONTHS[m - 1]}${y !== new Date().getFullYear() ? ' ' + y : ''}`;
}

// SQLite datetime('now') is UTC
function parseUtc(sqlDate) {
  return new Date(sqlDate.replace(' ', 'T') + 'Z');
}

function formatDateTime(sqlDate) {
  if (!sqlDate) return '';
  const d = parseUtc(sqlDate);
  const pad = (x) => String(x).padStart(2, '0');
  return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}, ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function timeAgo(sqlDate) {
  if (!sqlDate) return '';
  const sec = Math.max(0, (Date.now() - parseUtc(sqlDate).getTime()) / 1000);
  if (sec < 60) return 'zojuist';
  if (sec < 3600) return `${Math.floor(sec / 60)} min geleden`;
  if (sec < 86400) return `${Math.floor(sec / 3600)} uur geleden`;
  if (sec < 86400 * 7) {
    const n = Math.floor(sec / 86400);
    return n === 1 ? 'gisteren' : `${n} dagen geleden`;
  }
  return formatDateTime(sqlDate).split(',')[0];
}

function initials(name = '') {
  return name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('') || '?';
}

function localSecret() {
  const { dataDir } = require('./db');
  const file = path.join(dataDir, '.session-secret');
  if (!fs.existsSync(file)) fs.writeFileSync(file, crypto.randomBytes(48).toString('hex'), { mode: 0o600 });
  return fs.readFileSync(file, 'utf8').trim();
}

// Hulpjes om formulierinvoer netjes te houden
const str = (v, max = 200) => (typeof v === 'string' ? v.trim().slice(0, max) : '');
const isTime = (v) => /^([01]\d|2[0-3]):[0-5]\d$/.test(v);
const isDate = (v) => /^\d{4}-\d{2}-\d{2}$/.test(v) && !Number.isNaN(Date.parse(v));
const toInt = (v) => {
  const n = Number.parseInt(v, 10);
  return Number.isFinite(n) ? n : null;
};

// Inline SVG-icoon uit de sprite in views/partials/icons.ejs
const icon = (name, cls = '') => `<svg class="ic ${cls}" aria-hidden="true"><use href="#i-${name}"/></svg>`;

// Veilig JSON in een <script>-tag zetten
const json = (v) => JSON.stringify(v).replace(/</g, '\\u003c');

module.exports = {
  json, icon, DAYS, todayISO, isoWeekday, formatDate, formatDateTime, timeAgo, initials, localSecret,
  str, isTime, isDate, toInt,
};
