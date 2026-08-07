(function ($) {
  'use strict';

  function resultBox($el) {
    var $box = $el.closest('.mpap-card').find('.mpap-ajax-result').first();
    if (!$box.length) {
      $box = $('.mpap-ajax-result').first();
    }
    return $box;
  }

  function runAction($button, action, data) {
    var $box = resultBox($button);
    var original = $button.text();
    $button.prop('disabled', true).addClass('is-loading').text(MPAP_Admin.i18n.syncing);
    $box.removeClass('success error warning').addClass('info').html('<pre>Processando...</pre>');

    return $.post(MPAP_Admin.ajax_url, $.extend({ action: action, nonce: MPAP_Admin.nonce }, data || {}))
      .done(function (response) {
        if (response && response.success) {
          var msg = response.data && response.data.message ? response.data.message : MPAP_Admin.i18n.done;
          var details = response.data && response.data.checks ? response.data : (response.data && response.data.result ? response.data.result : response.data);
          var cls = response.data && response.data.result && response.data.result.warning ? 'warning' : 'success';
          $box.removeClass('info error warning success').addClass(cls).html('<strong>' + msg + '</strong><pre>' + escapeHtml(JSON.stringify(details, null, 2)) + '</pre>');
        } else {
          var err = response && response.data && response.data.message ? response.data.message : MPAP_Admin.i18n.error;
          $box.removeClass('info success warning').addClass('error').html('<strong>' + escapeHtml(err) + '</strong><pre>' + escapeHtml(JSON.stringify(response, null, 2)) + '</pre>');
        }
      })
      .fail(function (xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : MPAP_Admin.i18n.error;
        $box.removeClass('info success warning').addClass('error').html('<strong>' + escapeHtml(msg) + '</strong><pre>' + escapeHtml(xhr.responseText || '') + '</pre>');
      })
      .always(function () {
        $button.prop('disabled', false).removeClass('is-loading').text(original);
      });
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderCategoryResults($scope, response, query) {
    var suggestions = response && response.data && response.data.suggestions ? response.data.suggestions : [];
    var $target = $scope.closest('.mpap-card').find('.mpap-category-results').first();
    if (!$target.length) {
      $target = $('.mpap-category-results').first();
    }
    if (!$target.length) {
      return;
    }
    if (!suggestions.length) {
      $target.html('<p>Nenhuma categoria sugerida. Tente um título mais específico.</p>');
      return;
    }

    var html = '<div class="mpap-category-list">';
    suggestions.forEach(function (item, index) {
      var required = item.required || [];
      var listingTypes = item.listing_types || [];
      html += '<div class="mpap-category-item">';
      html += '<div class="mpap-category-main"><span class="mpap-badge ' + (index === 0 ? 'success' : 'neutral') + '">' + (index === 0 ? 'mais provável' : 'opção ' + (index + 1)) + '</span>';
      html += '<h3>' + escapeHtml(item.category_id) + ' — ' + escapeHtml(item.category_name || item.domain_name || 'Categoria Mercado Livre') + '</h3>';
      html += '<p><strong>Caminho:</strong> ' + escapeHtml(item.path || item.category_name || '—') + '</p>';
      html += '<p><strong>Domínio:</strong> ' + escapeHtml(item.domain_id || '—') + (item.domain_name ? ' — ' + escapeHtml(item.domain_name) : '') + '</p>';
      if (item.probability !== null && item.probability !== undefined) {
        html += '<p><strong>Probabilidade:</strong> ' + escapeHtml(String(Math.round(Number(item.probability) * 10000) / 100)) + '%</p>';
      }
      if (required.length) {
        html += '<details><summary>Atributos obrigatórios (' + required.length + ')</summary><ul>';
        required.forEach(function (attr) {
          html += '<li><code>' + escapeHtml(attr.id) + '</code> — ' + escapeHtml(attr.name || '') + (attr.value_type ? ' <small>(' + escapeHtml(attr.value_type) + ')</small>' : '') + '</li>';
        });
        html += '</ul></details>';
      } else {
        html += '<p><span class="mpap-badge success">sem obrigatórios extras detectados</span></p>';
      }
      if (listingTypes.length) {
        html += '<p><strong>Tipos aceitos:</strong> ' + escapeHtml(listingTypes.map(function (lt) { return lt.id; }).join(', ')) + '</p>';
      }
      html += '</div><div class="mpap-category-actions">';
      html += '<button type="button" class="mpap-button mpap-button-primary mpap-use-category" data-category-id="' + escapeHtml(item.category_id) + '" data-category-name="' + escapeHtml(item.category_name || '') + '" data-category-path="' + escapeHtml(item.path || '') + '" data-domain-id="' + escapeHtml(item.domain_id || '') + '" data-query="' + escapeHtml(query || '') + '">Usar como categoria padrão</button> ';
      html += '<button type="button" class="mpap-button mpap-copy-button" data-copy="' + escapeHtml(item.category_id) + '">Copiar ID</button>';
      html += '</div></div>';
    });
    html += '</div>';
    $target.html(html);
  }

  $(document).on('click', '.mpap-sync-all', function (e) {
    e.preventDefault();
    runAction($(this), 'mpap_sync_all');
  });

  $(document).on('click', '.mpap-update-stock-all', function (e) {
    e.preventDefault();
    runAction($(this), 'mpap_update_stock_all');
  });

  $(document).on('click', '.mpap-sync-product', function (e) {
    e.preventDefault();
    runAction($(this), 'mpap_sync_product', { product_id: $(this).data('product-id') });
  });

  $(document).on('click', '.mpap-import-order', function (e) {
    e.preventDefault();
    var id = $('.mpap-order-id').val();
    runAction($(this), 'mpap_import_order', { order_id: id });
  });

  $(document).on('click', '.mpap-run-diagnostics', function (e) {
    e.preventDefault();
    runAction($(this), 'mpap_run_diagnostics');
  });

  $(document).on('click', '.mpap-test-public-api', function (e) {
    e.preventDefault();
    runAction($(this), 'mpap_test_public_api');
  });

  $(document).on('click', '.mpap-test-oauth-readiness', function (e) {
    e.preventDefault();
    runAction($(this), 'mpap_test_oauth_readiness');
  });

  $(document).on('click', '.mpap-test-connection', function (e) {
    e.preventDefault();
    runAction($(this), 'mpap_test_connection');
  });

  $(document).on('click', '.mpap-test-log', function (e) {
    e.preventDefault();
    runAction($(this), 'mpap_test_log');
  });

  $(document).on('click', '.mpap-predict-category', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $scope = $btn.closest('.mpap-card');
    var query = $scope.find('.mpap-category-query').first().val() || $('.mpap-category-query').first().val();
    var limit = $scope.find('.mpap-category-limit').first().val() || 8;
    var $box = resultBox($btn);
    var original = $btn.text();
    $btn.prop('disabled', true).addClass('is-loading').text(MPAP_Admin.i18n.syncing);
    $box.removeClass('success error warning').addClass('info').html('<pre>Consultando preditor de categoria...</pre>');

    $.post(MPAP_Admin.ajax_url, { action: 'mpap_predict_category', nonce: MPAP_Admin.nonce, title: query, limit: limit })
      .done(function (response) {
        if (response && response.success) {
          $box.removeClass('info error warning').addClass('success').html('<strong>Categorias encontradas.</strong><pre>Use a sugestão mais provável ou copie o ID desejado.</pre>');
          renderCategoryResults($scope, response, query);
        } else {
          var err = response && response.data && response.data.message ? response.data.message : MPAP_Admin.i18n.error;
          $box.removeClass('info success warning').addClass('error').html('<strong>' + escapeHtml(err) + '</strong><pre>' + escapeHtml(JSON.stringify(response, null, 2)) + '</pre>');
        }
      })
      .fail(function (xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : MPAP_Admin.i18n.error;
        $box.removeClass('info success warning').addClass('error').html('<strong>' + escapeHtml(msg) + '</strong><pre>' + escapeHtml(xhr.responseText || '') + '</pre>');
      })
      .always(function () {
        $btn.prop('disabled', false).removeClass('is-loading').text(original);
      });
  });

  $(document).on('click', '.mpap-fill-category-query', function (e) {
    e.preventDefault();
    var query = $(this).data('query') || '';
    $(this).closest('.mpap-card').find('.mpap-category-query').first().val(query).focus();
  });

  $(document).on('click', '.mpap-use-category', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var categoryId = $btn.data('category-id') || '';
    var payload = {
      category_id: categoryId,
      category_name: $btn.data('category-name') || '',
      category_path: $btn.data('category-path') || '',
      domain_id: $btn.data('domain-id') || '',
      query: $btn.data('query') || ''
    };

    $('input[name="default_category_id"]').val(categoryId).trigger('change');
    var original = $btn.text();
    $btn.prop('disabled', true).text('Salvando...');
    $.post(MPAP_Admin.ajax_url, $.extend({ action: 'mpap_set_category', nonce: MPAP_Admin.nonce }, payload))
      .done(function (response) {
        if (response && response.success) {
          $('.mpap-default-category-id').text(categoryId);
          $('.mpap-default-category-name').text(payload.category_name || '—');
          $('.mpap-default-category-path').text(payload.category_path || '—');
          $('.mpap-default-category-domain').text(payload.domain_id || '—');
          resultBox($btn).removeClass('info error warning').addClass('success').html('<strong>Categoria padrão atualizada: ' + escapeHtml(categoryId) + '</strong>');
        } else {
          var err = response && response.data && response.data.message ? response.data.message : MPAP_Admin.i18n.error;
          resultBox($btn).removeClass('info success warning').addClass('error').html('<strong>' + escapeHtml(err) + '</strong>');
        }
      })
      .fail(function (xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : MPAP_Admin.i18n.error;
        resultBox($btn).removeClass('info success warning').addClass('error').html('<strong>' + escapeHtml(msg) + '</strong><pre>' + escapeHtml(xhr.responseText || '') + '</pre>');
      })
      .always(function () {
        $btn.prop('disabled', false).text(original);
      });
  });


  $(document).on('click', '.mpap-dry-run-sync-all', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var original = $btn.text();
    resultBox($btn).removeClass('success error warning').addClass('info').html('<pre>Executando dry-run...</pre>');
    $btn.prop('disabled', true).text('Processando...');
    $.post(MPAP_Admin.ajax_url, { action: 'mpap_dry_run_sync_all', nonce: MPAP_Admin.nonce })
      .done(function (response) {
        var cls = response && response.success ? 'success' : 'error';
        resultBox($btn).removeClass('info success error warning').addClass(cls).html('<pre>' + escapeHtml(JSON.stringify(response.data || response, null, 2)) + '</pre>');
      })
      .fail(function (xhr) { resultBox($btn).removeClass('info success warning').addClass('error').html('<pre>' + escapeHtml(xhr.responseText || '') + '</pre>'); })
      .always(function () { $btn.prop('disabled', false).text(original); });
  });

  $(document).on('click', '.mpap-close-all-ml-dry-run, .mpap-close-all-ml-execute', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var execute = $btn.hasClass('mpap-close-all-ml-execute');
    var confirmWord = '';
    if (execute) {
      confirmWord = window.prompt('Ação destrutiva: encerrar TODOS os anúncios Mercado Livre vinculados. Digite exatamente ENCERAR para confirmar. Anúncio encerrado no Mercado Livre pode não ser restaurável.');
      if (confirmWord !== 'ENCERAR') { return; }
    }
    var original = $btn.text();
    resultBox($btn).removeClass('success error warning').addClass('info').html('<pre>' + (execute ? 'Executando encerramento...' : 'Gerando prévia sem alterar Mercado Livre...') + '</pre>');
    $btn.prop('disabled', true).text('Processando...');
    $.post(MPAP_Admin.ajax_url, { action: 'mpap_close_all_ml_items', nonce: MPAP_Admin.nonce, execute: execute ? 1 : 0, confirm: confirmWord })
      .done(function (response) {
        var cls = response && response.success ? (execute ? 'warning' : 'success') : 'error';
        resultBox($btn).removeClass('info success error warning').addClass(cls).html('<pre>' + escapeHtml(JSON.stringify(response.data || response, null, 2)) + '</pre>');
      })
      .fail(function (xhr) { resultBox($btn).removeClass('info success warning').addClass('error').html('<pre>' + escapeHtml(xhr.responseText || '') + '</pre>'); })
      .always(function () { $btn.prop('disabled', false).text(original); });
  });

  $(document).on('click', '.mpap-copy-button, .mpap-copy-value', function (e) {
    e.preventDefault();
    var text = $(this).data('copy') || $(this).text();
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text);
    } else {
      var $tmp = $('<textarea>').val(text).appendTo('body').select();
      document.execCommand('copy');
      $tmp.remove();
    }
    var $el = $(this);
    var original = $el.text();
    $el.addClass('copied').text(MPAP_Admin.i18n.copied);
    setTimeout(function () { $el.removeClass('copied').text(original); }, 1200);
  });
})(jQuery);
