(function () {
  'use strict';
  const cfg = window.REDVECINAL || {baseUrl: '', csrf: ''};
  const base = (cfg.baseUrl || '').replace(/\/$/, '');
  const banner = document.getElementById('offlineBanner');
  const isLocalHost = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
  if (isLocalHost && banner) banner.remove();

  function connectionAvailable() {
    return isLocalHost || navigator.onLine;
  }

  function updateOnline() {
    const available = connectionAvailable();
    if (banner) banner.hidden = available;
    if (available) { syncQueue(); syncPanicQueue(); }
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

  document.querySelectorAll('[data-table-search]').forEach((input) => {
    input.addEventListener('input', () => {
      const table = document.getElementById(input.dataset.tableSearch);
      if (!table) return;
      const term = input.value.trim().toLocaleLowerCase('es');
      table.querySelectorAll('tbody tr').forEach((row) => {
        row.hidden = term !== '' && !row.textContent.toLocaleLowerCase('es').includes(term);
      });
    });
  });

  function getQueue() { try { return JSON.parse(localStorage.getItem('redvecinal_offline_reports') || '[]'); } catch (error) { return []; } }
  function saveQueue(items) { localStorage.setItem('redvecinal_offline_reports', JSON.stringify(items)); }
  function getPanicQueue() { try { return JSON.parse(localStorage.getItem('redvecinal_offline_panics') || '[]'); } catch (error) { return []; } }
  function savePanicQueue(items) { localStorage.setItem('redvecinal_offline_panics', JSON.stringify(items)); }

  document.querySelectorAll('[data-offline-form]').forEach((form) => form.addEventListener('submit', (event) => {
    if (connectionAvailable()) return;
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

  async function syncPanicQueue() {
    const items = getPanicQueue(); if (!items.length || !connectionAvailable()) return;
    const remaining = [];
    for (const item of items) {
      try {
        const response = await fetch(base + '/mi-app/panico', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf}, credentials: 'same-origin', body: JSON.stringify(item)});
        if (!response.ok) remaining.push(item);
      } catch (error) { remaining.push(item); }
    }
    savePanicQueue(remaining);
  }

  const panicButton = document.querySelector('[data-panic-trigger]');
  if (panicButton) {
    const form = panicButton.closest('[data-panic-form]');
    const status = form.querySelector('[data-panic-status]');
    let holdTimer = null;
    let activated = false;
    const resetHold = () => { if (holdTimer) clearTimeout(holdTimer); holdTimer = null; panicButton.classList.remove('holding'); };
    const sendPanic = () => {
      if (activated) return; activated = true; resetHold(); panicButton.disabled = true; panicButton.classList.add('activated');
      status.textContent = 'Obteniendo ubicación y enviando alerta…';
      const finish = (position) => {
        if (position) { form.querySelector('[name=latitude]').value=position.coords.latitude; form.querySelector('[name=longitude]').value=position.coords.longitude; }
        if (!connectionAvailable()) {
          const data=Object.fromEntries(new FormData(form).entries()); data._queued_at=new Date().toISOString(); const items=getPanicQueue(); items.push(data); savePanicQueue(items);
          status.textContent='Sin conexión: alerta guardada. Se enviará automáticamente. Llama al 133, 132 o 131 si existe riesgo inmediato.'; panicButton.querySelector('strong').textContent='ALERTA EN ESPERA'; return;
        }
        status.textContent='Enviando alerta crítica a la central…'; form.submit();
      };
      if (navigator.geolocation) navigator.geolocation.getCurrentPosition(finish,()=>finish(null),{enableHighAccuracy:true,timeout:8000,maximumAge:0}); else finish(null);
    };
    const startHold = (event) => { event.preventDefault(); if(activated)return; panicButton.classList.add('holding'); holdTimer=setTimeout(sendPanic,2000); };
    panicButton.addEventListener('pointerdown',startHold);
    ['pointerup','pointercancel','pointerleave'].forEach(name=>panicButton.addEventListener(name,resetHold));
  }

  let installPrompt = null;
  const installButton = document.querySelector('[data-install-app]');
  window.addEventListener('beforeinstallprompt', (event) => { event.preventDefault(); installPrompt=event; if(installButton)installButton.hidden=false; });
  if (installButton) installButton.addEventListener('click', async () => { if(!installPrompt)return; installPrompt.prompt(); await installPrompt.userChoice; installPrompt=null; installButton.hidden=true; });

  const mapElement=document.getElementById('communeMap');
  const mapPayload=document.getElementById('dashboardMapData');
  if(mapElement&&mapPayload&&window.L){
    try{
      const data=JSON.parse(mapPayload.textContent); const map=L.map(mapElement,{zoomControl:true}).setView([data.config.lat,data.config.lng],data.config.zoom||13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
      const escapeHtml=(value)=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
      const markers=data.reports.map(report=>{
        const marker=L.circleMarker([report.lat,report.lng],{radius:8,color:'#fff',weight:2,fillColor:report.color||'#d63f45',fillOpacity:.95});
        marker.bindPopup(`<div class="map-popup"><small>${escapeHtml(report.code)}</small><strong>${escapeHtml(report.title)}</strong><span>${escapeHtml(report.type)} · ${escapeHtml(report.status).replaceAll('_',' ')}</span><a href="${escapeHtml(report.url)}">Abrir reporte</a></div>`); marker.addTo(map); return {marker,report};
      });
      document.querySelectorAll('[data-map-filter]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-map-filter]').forEach(item=>item.classList.remove('active'));button.classList.add('active');const filter=button.dataset.mapFilter;markers.forEach(({marker,report})=>{const show=filter==='all'||(filter==='critica'&&report.priority==='critica')||(filter==='open'&&!['resuelto','cerrado','rechazado'].includes(report.status));if(show&&!map.hasLayer(marker))marker.addTo(map);if(!show&&map.hasLayer(marker))map.removeLayer(marker);});}));
      setTimeout(()=>map.invalidateSize(),100);
    }catch(error){mapElement.innerHTML='<div class="map-unavailable">No fue posible cargar el mapa.</div>';}
  } else if(mapElement) { mapElement.innerHTML='<div class="map-unavailable">El mapa requiere conexión la primera vez que se abre.</div>'; }

  if(connectionAvailable())syncPanicQueue();

  if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register(base + '/service-worker.js', {scope: base + '/'}).catch(() => {}));
}());
