(function () {
  'use strict';
  const cfg = window.NEIGHBOR_APP || {};
  let installPrompt = null;
  const installButton = document.querySelector('[data-install-neighbor]');
  window.addEventListener('beforeinstallprompt', (event) => { event.preventDefault(); installPrompt = event; if (installButton) installButton.hidden = false; });
  if (installButton) installButton.addEventListener('click', async () => { if (!installPrompt) return; installPrompt.prompt(); await installPrompt.userChoice; installPrompt = null; installButton.hidden = true; });

  document.querySelectorAll('[data-priority]').forEach((input) => input.addEventListener('change', () => {
    const priority = document.getElementById('neighborPriority');
    if (priority) priority.value = input.dataset.priority;
  }));

  const locationButton = document.querySelector('[data-neighbor-location]');
  if (locationButton) locationButton.addEventListener('click', () => {
    if (!navigator.geolocation) { locationButton.textContent = 'Ubicación no disponible'; return; }
    locationButton.disabled = true; locationButton.textContent = 'Obteniendo ubicación…';
    navigator.geolocation.getCurrentPosition((position) => {
      const form = locationButton.closest('form');
      form.querySelector('[name=latitude]').value = position.coords.latitude;
      form.querySelector('[name=longitude]').value = position.coords.longitude;
      locationButton.textContent = '✓ Ubicación guardada'; locationButton.disabled = false;
    }, () => { locationButton.textContent = 'No fue posible obtener ubicación'; locationButton.disabled = false; }, {enableHighAccuracy: true, timeout: 10000});
  });

  const reportQueueKey = 'neighbor_report_queue';
  const getReportQueue = () => { try { return JSON.parse(localStorage.getItem(reportQueueKey) || '[]'); } catch (error) { return []; } };
  const saveReportQueue = (queue) => localStorage.setItem(reportQueueKey, JSON.stringify(queue));
  const reportForm = document.querySelector('form[action="?action=report"]');
  if (reportForm) reportForm.addEventListener('submit', (event) => {
    if (navigator.onLine) return;
    event.preventDefault();
    const queue = getReportQueue(); queue.push(Object.fromEntries(new FormData(reportForm).entries())); saveReportQueue(queue);
    const message = document.createElement('div'); message.className = 'neighbor-flash'; message.textContent = 'Denuncia guardada en este teléfono. Se enviará al recuperar la conexión.';
    reportForm.prepend(message); reportForm.reset(); window.scrollTo({top: 0, behavior: 'smooth'});
  });

  async function syncReports() {
    if (!navigator.onLine) return;
    const queue = getReportQueue(); if (!queue.length) return;
    const remaining = [];
    for (const payload of queue) {
      try {
        const response = await fetch('?action=report', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams(payload).toString()});
        if (!response.ok) remaining.push(payload);
      } catch (error) { remaining.push(payload); }
    }
    saveReportQueue(remaining);
  }

  const panic = document.querySelector('[data-neighbor-panic]');
  if (panic) {
    let timer = null; let sent = false; const message = document.querySelector('[data-panic-message]');
    const stop = () => { if (timer) clearTimeout(timer); timer = null; panic.classList.remove('holding'); };
    const send = () => {
      if (sent) return; sent = true; stop(); panic.disabled = true; message.textContent = 'Obteniendo ubicación…';
      const finish = async (position) => {
        const payload = {latitude: position ? position.coords.latitude : null, longitude: position ? position.coords.longitude : null};
        if (!navigator.onLine) {
          const queue = JSON.parse(localStorage.getItem('neighbor_panic_queue') || '[]'); queue.push(payload); localStorage.setItem('neighbor_panic_queue', JSON.stringify(queue));
          message.textContent = 'Alerta guardada sin conexión. Llama al 133, 132 o 131 si hay riesgo inmediato.'; return;
        }
        try {
          const response = await fetch('?action=panic', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf}, body: JSON.stringify(payload)});
          const data = await response.json(); message.textContent = data.message || 'Alerta enviada.'; panic.textContent = 'ALERTA ENVIADA';
        } catch (error) { message.textContent = 'No se pudo enviar. Llama al 133, 132 o 131.'; }
      };
      if (navigator.geolocation) navigator.geolocation.getCurrentPosition(finish, () => finish(null), {enableHighAccuracy: true, timeout: 8000}); else finish(null);
    };
    panic.addEventListener('pointerdown', (event) => { event.preventDefault(); panic.classList.add('holding'); timer = setTimeout(send, 2000); });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach((name) => panic.addEventListener(name, stop));
  }

  async function syncPanics() {
    if (!navigator.onLine) return;
    const queue = JSON.parse(localStorage.getItem('neighbor_panic_queue') || '[]'); if (!queue.length) return;
    const pending = [];
    for (const item of queue) {
      try {
        const response = await fetch('?action=panic', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf}, body: JSON.stringify(item)});
        if (!response.ok) pending.push(item);
      } catch (error) { pending.push(item); }
    }
    localStorage.setItem('neighbor_panic_queue', JSON.stringify(pending));
  }

  const syncAll = () => { syncReports(); syncPanics(); };
  window.addEventListener('online', syncAll); syncAll();
  if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('service-worker.js').catch(() => {}));
}());
