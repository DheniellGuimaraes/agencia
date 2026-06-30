(function () {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const log = (m) => { const box = $('#sap-log'); if (!box) return; const p = document.createElement('p'); p.textContent = '[' + new Date().toLocaleTimeString() + '] ' + m; box.appendChild(p); box.scrollTop = box.scrollHeight; };
  const post = async (action, data = new FormData($('#sap-form'))) => { data.append('action', 'sap_' + action); data.append('nonce', sapSitemap.nonce); const r = await fetch(sapSitemap.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data }); return r.json(); };
  const setProgress = (processed, estimated, current, message) => { const pct = estimated > 0 ? Math.min(100, Math.round((processed / estimated) * 100)) : 0; $('#sap-progress-bar').style.width = pct + '%'; $('#sap-percent').textContent = pct + '%'; $('#sap-processed').textContent = processed.toLocaleString() + ' processados'; $('#sap-current').textContent = current ? 'Atual: ' + current : 'Aguardando'; $('#sap-status').textContent = message || 'Processando'; };
  const renderFiles = (files) => { const wrap = $('#sap-files'); if (!files || !files.length) { wrap.innerHTML = '<p class="sap-empty">Nenhum arquivo físico gerado ainda.</p>'; return; } wrap.innerHTML = '<div class="sap-file-list">' + files.map(f => `<div class="sap-file"><strong>${esc(f.name)}</strong><span>${esc(String(f.urls))} URLs</span><span>${esc(f.size)}</span><span>${esc(f.created)}</span><a class="sap-btn sap-mini" target="_blank" rel="noopener" href="${esc(f.url)}">Abrir</a><button class="sap-btn sap-mini sap-copy-url" data-url="${esc(f.url)}">Copiar</button></div>`).join('') + '</div>'; };
  const esc = (v) => String(v).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const pause = (ms) => new Promise(resolve => setTimeout(resolve, ms));
  async function generate() {
    const btn = $('#sap-generate'); btn.disabled = true; log('Preparando geração.'); setProgress(0, 0, '', 'Preparando geração');
    try {
      let res = await post('start'); if (!res.success) throw new Error(res.data.message); log(res.data.message);
      let finished = false;
      while (!finished) {
        res = await post('process', new FormData()); if (!res.success) throw new Error(res.data.message);
        const d = res.data; finished = !!d.finished; setProgress(d.processed, d.estimated, d.current_file, d.message); renderFiles(d.generated_files); log(d.message + ' Total: ' + d.processed);
        if (!finished) await pause(parseInt(new FormData($('#sap-form')).get('batch_pause') || '250', 10));
      }
      res = await post('finalize', new FormData()); if (!res.success) throw new Error(res.data.message);
      setProgress(res.data.processed, res.data.estimated || res.data.processed, 'sitemap.xml', 'Concluído'); renderFiles(res.data.generated_files); log('Sucesso: ' + res.data.main_url);
    } catch (e) { $('#sap-status').textContent = 'Erro'; log('ERRO: ' + e.message); }
    btn.disabled = false;
  }
  document.addEventListener('click', async (e) => {
    if (e.target.closest('#sap-generate')) generate();
    if (e.target.closest('#sap-clean')) { const r = await post('clean', new FormData()); if (r.success) { renderFiles(r.data.files); log(r.data.message); } }
    if (e.target.closest('#sap-permissions')) { const r = await post('permissions', new FormData()); if (r.success) log(r.data.message + ' Escrita: ' + (r.data.diagnostics.writable ? 'ok' : 'bloqueada')); }
    const copy = e.target.closest('.sap-copy'); if (copy) { const input = $(copy.dataset.copy); if (input) navigator.clipboard.writeText(input.value).then(() => log('URL copiada.')); }
    const copyUrl = e.target.closest('.sap-copy-url'); if (copyUrl) navigator.clipboard.writeText(copyUrl.dataset.url).then(() => log('URL do arquivo copiada.'));
  });
})();
