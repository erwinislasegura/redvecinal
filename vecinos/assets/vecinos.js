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
      const finish = async (position, locationError = '') => {
        const payload = {latitude: position ? position.coords.latitude : null, longitude: position ? position.coords.longitude : null, accuracy: position ? position.coords.accuracy : null, captured_at: new Date().toISOString(), location_error: locationError};
        if (!navigator.onLine) {
          const queue = JSON.parse(localStorage.getItem('neighbor_panic_queue') || '[]'); queue.push(payload); localStorage.setItem('neighbor_panic_queue', JSON.stringify(queue));
          message.textContent = 'Alerta guardada sin conexión. Llama al 133, 132 o 131 si hay riesgo inmediato.'; return;
        }
        try {
          const response = await fetch('?action=panic', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf}, body: JSON.stringify(payload)});
          const data = await response.json(); message.textContent = data.message || 'Alerta enviada.'; panic.textContent = data.location_shared ? 'ALERTA + GPS ENVIADOS' : 'ALERTA ENVIADA';
        } catch (error) { message.textContent = 'No se pudo enviar. Llama al 133, 132 o 131.'; }
      };
      if (navigator.geolocation) navigator.geolocation.getCurrentPosition((position)=>finish(position), (error) => finish(null,error.message||'Permiso de ubicación denegado'), {enableHighAccuracy: true, timeout: 12000, maximumAge: 0}); else finish(null,'Geolocalización no disponible');
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
  const trackingCards = document.querySelectorAll('[data-report-track]');
  if (trackingCards.length) {
    const updateTracking = async () => {
      if (!navigator.onLine) return;
      try {
        const response = await fetch(`${cfg.base}?action=tracking`, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
        if (!response.ok) return; const data = await response.json();
        (data.reports || []).forEach((report) => {
          const card = document.querySelector(`[data-report-track="${report.id}"]`); if (!card) return;
          const panel = card.querySelector('[data-track-response]'); const status = card.querySelector('[data-track-report-status]');
          status.textContent = String(report.status || '').replaceAll('_', ' '); status.className = `status-${report.status}`; panel.classList.toggle('has-assignment', Boolean(report.assigned_name)); panel.classList.toggle('has-dispatch', Boolean(report.dispatch));
          card.querySelector('[data-track-summary]').textContent = report.dispatch ? 'Servicio despachado' : (report.assigned_name ? 'Responsable asignado' : 'Pendiente de asignación');
          card.querySelector('[data-track-assigned]').textContent = report.assigned_name || 'Aún sin asignar';
          card.querySelector('[data-track-unit]').textContent = report.dispatch ? (report.dispatch.unit || report.dispatch.service_label) : 'Aún no despachado';
          card.querySelector('[data-track-dispatch-status]').textContent = report.dispatch ? report.dispatch.status_label : 'En espera';
        });
      } catch (error) {}
    };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) updateTracking(); }); setInterval(updateTracking, 15000); updateTracking();
  }
  if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('service-worker.js').catch(() => {}));
}());
