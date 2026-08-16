(function () {
  'use strict';
  const cfg = window.REDVECINAL || {baseUrl: '', csrf: ''};
  const base = (cfg.baseUrl || '').replace(/\/$/, '');
  const banner = document.getElementById('offlineBanner');

  function updateOnline() {
    if (banner) banner.hidden = navigator.onLine;
    if (navigator.onLine) syncQueue();
  }
  window.addEventListener('online', updateOnline);
  window.addEventListener('offline', updateOnline);
  updateOnline();

  const sidebar = document.getElementById('sidebar');
  document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => button.addEventListener('click', () => sidebar && sidebar.classList.toggle('open')));

  document.querySelectorAll('[data-geolocate]').forEach((button) => button.addEventListener('click', () => {
    if (!navigator.geolocation) { alert('Tu navegador no permite obtener la ubicación.'); return; }
    button.disabled = true; button.textContent = 'Obteniendo ubicación...';
    navigator.geolocation.getCurrentPosition((position) => {
      const form = button.closest('form');
      form.querySelector('[name=latitude]').value = position.coords.latitude;
      form.querySelector('[name=longitude]').value = position.coords.longitude;
      button.textContent = '✓ Ubicación guardada'; button.disabled = false;
    }, () => { button.textContent = 'No fue posible obtener ubicación'; button.disabled = false; }, {enableHighAccuracy: true, timeout: 10000});
  }));

  document.querySelectorAll('[name=report_type_id]').forEach((input) => input.addEventListener('change', () => {
    const priority = document.getElementById('priority');
    if (priority && input.dataset.priority) priority.value = input.dataset.priority;
  }));

  function getQueue() { try { return JSON.parse(localStorage.getItem('redvecinal_offline_reports') || '[]'); } catch (error) { return []; } }
  function saveQueue(items) { localStorage.setItem('redvecinal_offline_reports', JSON.stringify(items)); }

  document.querySelectorAll('[data-offline-form]').forEach((form) => form.addEventListener('submit', (event) => {
    if (navigator.onLine) return;
    event.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    delete data.evidence;
    data._queued_at = new Date().toISOString();
    const items = getQueue(); items.push(data); saveQueue(items); form.reset();
    const note = document.createElement('div'); note.className = 'offline-queued';
    note.textContent = 'Reporte guardado en este dispositivo. Se enviará automáticamente al recuperar conexión.';
    form.prepend(note); setTimeout(() => note.remove(), 7000);
  }));

  async function syncQueue() {
    const items = getQueue(); if (!items.length) return;
    const remaining = [];
    for (const item of items) {
      try {
        const response = await fetch(base + '/reportes/sincronizar', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf}, credentials: 'same-origin', body: JSON.stringify(item)});
        if (!response.ok) remaining.push(item);
      } catch (error) { remaining.push(item); }
    }
    saveQueue(remaining);
    if (items.length && !remaining.length && document.querySelector('[data-offline-form]')) {
      const note = document.createElement('div'); note.className = 'alert alert-success'; note.textContent = 'Los reportes pendientes fueron sincronizados.';
      document.querySelector('[data-offline-form]').prepend(note);
    }
  }

  if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register(base + '/service-worker.js', {scope: base + '/'}).catch(() => {}));
}());

