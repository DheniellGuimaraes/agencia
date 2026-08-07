(function () {
  'use strict';

  const $ = (selector, context = document) => context.querySelector(selector);

  const log = (message) => {
    const box = $('#sap-log');
    if (!box) return;
    const line = document.createElement('p');
    line.textContent = '[' + new Date().toLocaleTimeString() + '] ' + message;
    box.appendChild(line);
    box.scrollTop = box.scrollHeight;
  };

  const post = async (action, data = new FormData($('#sap-form'))) => {
    data.append('action', 'sap_' + action);
    data.append('nonce', sapSitemap.nonce);

    const response = await fetch(sapSitemap.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: data,
    });

    if (!response.ok) {
      throw new Error('Falha HTTP ' + response.status + ' ao chamar ' + action + '.');
    }

    return response.json();
  };

  const setProgress = (processed, estimated, current, message) => {
    const percent = estimated > 0 ? Math.min(100, Math.round((processed / estimated) * 100)) : 0;
    $('#sap-progress-bar').style.width = percent + '%';
    $('#sap-percent').textContent = percent + '%';
    $('#sap-processed').textContent = processed.toLocaleString() + ' processados';
    $('#sap-current').textContent = current ? 'Atual: ' + current : 'Aguardando';
    $('#sap-status').textContent = message || 'Processando';
  };

  const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;',
  }[char]));

  const renderFiles = (files) => {
    const wrap = $('#sap-files');
    if (!files || !files.length) {
      wrap.innerHTML = '<p class="sap-empty">Nenhum arquivo físico gerado ainda.</p>';
      return;
    }

    wrap.innerHTML = '<div class="sap-file-list">' + files.map((file) => (
      `<div class="sap-file"><strong>${escapeHtml(file.name)}</strong><span>${escapeHtml(String(file.urls))} URLs</span><span>${escapeHtml(file.size)}</span><span>${escapeHtml(file.created)}</span><a class="sap-btn sap-mini" target="_blank" rel="noopener" href="${escapeHtml(file.url)}">Abrir</a></div>`
    )).join('') + '</div>';
  };

  const pause = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

  const openDownload = (url, reservedWindow) => {
    const finalUrl = url + (url.includes('?') ? '&' : '?') + 'sap_download=' + Date.now();

    if (reservedWindow && !reservedWindow.closed) {
      reservedWindow.location.href = finalUrl;
      return;
    }

    const link = document.createElement('a');
    link.href = finalUrl;
    link.download = 'sitemap.xml';
    link.target = '_blank';
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
  };

  async function generate(event) {
    if (event) event.preventDefault();

    const button = $('#sap-generate');
    const reservedWindow = window.open('about:blank', '_blank');

    if (reservedWindow) {
      reservedWindow.document.write('<p style="font-family:system-ui;padding:24px">Gerando sitemap Fodão. O download abrirá automaticamente quando finalizar...</p>');
    }

    button.disabled = true;
    button.classList.add('is-loading');
    log('sitemap Fodão acionado: verificando permissões, preparando rastreamento e geração física.');
    setProgress(0, 0, '', 'Preparando geração');

    try {
      const permissions = await post('permissions', new FormData());
      if (!permissions.success || !permissions.data.diagnostics.writable) {
        throw new Error('A pasta de destino não tem permissão de escrita.');
      }
      log('Permissões OK. Iniciando rastreamento em lotes.');

      let result = await post('start');
      if (!result.success) throw new Error(result.data.message || 'Não foi possível iniciar.');
      log(result.data.message);

      let finished = false;
      while (!finished) {
        result = await post('process', new FormData());
        if (!result.success) throw new Error(result.data.message || 'Falha ao processar lote.');

        const data = result.data;
        finished = !!data.finished;
        setProgress(data.processed, data.estimated, data.current_file, data.message);
        renderFiles(data.generated_files);
        log(data.message + ' Total: ' + data.processed);

        if (!finished) {
          await pause(parseInt(new FormData($('#sap-form')).get('batch_pause') || '250', 10));
        }
      }

      result = await post('finalize', new FormData());
      if (!result.success) throw new Error(result.data.message || 'Falha ao finalizar índice.');

      setProgress(result.data.processed, result.data.estimated || result.data.processed, 'sitemap.xml', 'Concluído');
      renderFiles(result.data.generated_files);
      $('#sap-main-url').value = result.data.main_url;
      log('Sitemap Fodão concluído. Abrindo download: ' + result.data.main_url);
      openDownload(result.data.main_url, reservedWindow);
    } catch (error) {
      $('#sap-status').textContent = 'Erro';
      log('ERRO: ' + error.message);
      if (reservedWindow && !reservedWindow.closed) {
        reservedWindow.document.body.innerHTML = '<p style="font-family:system-ui;padding:24px;color:#b00020">Erro ao gerar sitemap: ' + escapeHtml(error.message) + '</p>';
      }
    }

    button.disabled = false;
    button.classList.remove('is-loading');
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('#sap-generate')) {
      generate(event);
    }
  });
})();
