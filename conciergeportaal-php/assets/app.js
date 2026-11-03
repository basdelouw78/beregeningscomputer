/* Conciërgeportaal — kleine scripts voor kaart, filters en formulieren */
(function () {
  'use strict';

  // ---------- Bevestigen voor verwijderen ----------
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!window.confirm(form.dataset.confirm)) e.preventDefault();
    });
  });

  // ---------- Uitklappen (bijv. leenformulier) ----------
  document.querySelectorAll('[data-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.toggle);
      if (!target) return;
      target.hidden = !target.hidden;
      if (!target.hidden) target.querySelector('input, select')?.focus();
    });
  });

  // ---------- Zoeken/filteren in lijsten ----------
  document.querySelectorAll('input[data-filter]').forEach((input) => {
    const items = document.querySelectorAll(input.dataset.filter);
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      items.forEach((el) => {
        el.style.display = !q || (el.dataset.text || '').includes(q) ? '' : 'none';
      });
    });
  });

  // ---------- Formulier met #nieuw in de URL direct openen ----------
  if (location.hash === '#nieuw') {
    const d = document.getElementById('nieuw');
    if (d && d.tagName === 'DETAILS') d.open = true;
  }

  // ---------- Kaart-hulpjes ----------
  const dataEl = document.getElementById('schools-data');
  const schools = dataEl ? JSON.parse(dataEl.textContent) : [];

  function makeMap(id) {
    const el = document.getElementById(id);
    if (!el || !window.L) return null;
    const map = L.map(el, { scrollWheelZoom: false, tap: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    if (schools.length) {
      map.fitBounds(L.latLngBounds(schools.map((s) => [s.lat, s.lng])), { padding: [36, 36], maxZoom: 15 });
    } else {
      map.setView([52.1, 5.1], 8);
    }
    // Kaart goed tekenen als de container van grootte verandert
    new ResizeObserver(() => map.invalidateSize()).observe(el);
    return map;
  }

  function pinIcon(label, selected) {
    return L.divIcon({
      className: '',
      html: `<div class="pin${selected ? ' selected' : ''}"><span>${label}</span></div>`,
      iconSize: [34, 34], iconAnchor: [17, 34], popupAnchor: [0, -32],
    });
  }

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const abbr = (name) => name.split(/\s+/).filter((w) => /^[A-Z]/.test(w) && !/^(OBS|CBS|KBS|PCB|RKBS)$/.test(w)).slice(-2).map((w) => w[0]).join('') || name[0];

  // ---------- Setup: scholen kiezen ----------
  const setupForm = document.getElementById('setup-form');
  if (setupForm) {
    const map = makeMap('setup-map');
    const boxes = [...setupForm.querySelectorAll('input[data-school]')];
    const markers = {};

    function refresh() {
      let count = 0;
      boxes.forEach((box) => {
        const id = box.dataset.school;
        const on = box.checked;
        if (on) count += 1;
        const block = setupForm.querySelector(`[data-block="${id}"]`);
        if (block) block.hidden = !on;
        if (markers[id]) {
          const s = schools.find((x) => String(x.id) === id);
          markers[id].setIcon(pinIcon(abbr(s.name), on));
          markers[id].setPopupContent(popupHtml(s, on));
        }
      });
      document.getElementById('sel-count').textContent = `${count} gekozen`;
      document.getElementById('no-school-hint').hidden = count > 0;
    }

    function popupHtml(s, on) {
      return `<strong>${esc(s.name)}</strong>${s.address ? `<br><span style="color:#5f6d68">${esc(s.address)}</span>` : ''}
        <br><button type="button" class="btn sm" data-pick="${s.id}">${on ? 'Niet meer kiezen' : 'Ik werk hier'}</button>`;
    }

    if (map) {
      schools.forEach((s) => {
        const box = boxes.find((b) => b.dataset.school === String(s.id));
        const m = L.marker([s.lat, s.lng], { icon: pinIcon(abbr(s.name), box && box.checked), title: s.name, alt: s.name })
          .addTo(map).bindPopup(popupHtml(s, box && box.checked));
        markers[s.id] = m;
      });
      map.on('popupopen', (e) => {
        const btn = e.popup.getElement().querySelector('[data-pick]');
        if (!btn) return;
        btn.addEventListener('click', () => {
          const box = boxes.find((b) => b.dataset.school === btn.dataset.pick);
          box.checked = !box.checked;
          refresh();
          map.closePopup();
          if (box.checked) {
            const block = setupForm.querySelector(`[data-block="${box.dataset.school}"]`);
            // Kies alvast maandag t/m vrijdag als er nog niets aan staat
            const toggles = block.querySelectorAll('[data-day-toggle]');
            if (![...toggles].some((t) => t.checked)) {
              [...toggles].slice(0, 5).forEach((t) => { t.checked = true; t.dispatchEvent(new Event('change')); });
            }
          }
        });
      });
    }

    boxes.forEach((box) => box.addEventListener('change', () => {
      refresh();
      if (box.checked && map && markers[box.dataset.school]) {
        map.panTo(markers[box.dataset.school].getLatLng());
      }
    }));

    setupForm.querySelectorAll('[data-day-toggle]').forEach((t) => {
      t.addEventListener('change', () => {
        t.closest('.day-row').querySelector('.day-times').hidden = !t.checked;
      });
    });
    refresh();
  }

  // ---------- Collega's-kaart ----------
  const colMap = makeMap('colleagues-map');
  if (colMap) {
    schools.forEach((s) => {
      const people = s.people.length ? s.people.map(esc).join('<br>') : '<em>Nog niemand</em>';
      L.marker([s.lat, s.lng], { icon: pinIcon(abbr(s.name), s.people.length > 0), title: s.name })
        .addTo(colMap).bindPopup(`<strong>${esc(s.name)}</strong><br>${people}`);
    });
  }

  // ---------- Beheer: school prikken op de kaart ----------
  const adminMap = makeMap('admin-map');
  if (adminMap) {
    schools.forEach((s) => {
      L.marker([s.lat, s.lng], { icon: pinIcon(abbr(s.name), false), title: s.name }).addTo(adminMap).bindPopup(esc(s.name));
    });
    const lat = document.getElementById('s-lat');
    const lng = document.getElementById('s-lng');
    let newMarker = null;
    function place(latlng) {
      lat.value = latlng.lat.toFixed(6);
      lng.value = latlng.lng.toFixed(6);
      if (!newMarker) {
        newMarker = L.marker(latlng, { icon: pinIcon('+', true), draggable: true }).addTo(adminMap);
        newMarker.on('dragend', () => place(newMarker.getLatLng()));
      } else {
        newMarker.setLatLng(latlng);
      }
    }
    adminMap.on('click', (e) => place(e.latlng));
  }

  // ---------- Tutorial-upload: gekozen bestanden tonen ----------
  const fileInput = document.getElementById('t-files');
  if (fileInput) {
    const list = document.getElementById('file-list');
    const zone = document.getElementById('dropzone');
    const fmt = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.ceil(b / 1024)} kB`);
    const maxBytes = Number(document.getElementById('tutorial-form').dataset.maxBytes) || 100 * 1048576;
    const show = () => {
      list.innerHTML = '';
      [...fileInput.files].forEach((f) => {
        const li = document.createElement('li');
        const kind = f.type.startsWith('image/') ? '🖼️' : f.type.startsWith('video/') ? '🎬' : '📄';
        li.textContent = `${kind} ${f.name} · ${fmt(f.size)}`;
        if (f.size > maxBytes) { li.style.color = 'var(--danger)'; li.textContent += ' — te groot'; }
        list.appendChild(li);
      });
    };
    fileInput.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add('drag'); }));
    ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, () => zone.classList.remove('drag')));
    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      fileInput.files = e.dataTransfer.files;
      show();
    });
    document.getElementById('tutorial-form').addEventListener('submit', () => {
      const btn = document.getElementById('t-submit');
      btn.disabled = true;
      btn.textContent = 'Bezig met uploaden…';
    });
  }
})();
