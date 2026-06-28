jQuery(function($){
  const AUTO_SAVE_MS = 10000;

  function rsbMessage(resp, fallback){
    if(resp && resp.data && resp.data.message){ return resp.data.message; }
    if(resp && typeof resp.data === 'string'){ return resp.data; }
    return fallback;
  }

  function rsbPayload(form, action){
    return form.serialize() + '&action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(RSB_PUBLIC.nonce) + '&current_url=' + encodeURIComponent(window.location.href);
  }

  function rsbPost(form, action, successFallback){
    const btn = form.find('button[type="submit"]');
    const original = btn.text();
    btn.prop('disabled', true).text('Aguarde...');
    $.ajax({
      url: RSB_PUBLIC.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: rsbPayload(form, action)
    }).done(function(resp){
      if(resp && resp.success){
        alert(rsbMessage(resp, successFallback));
        if(resp.data && resp.data.redirect){
          window.location.href = resp.data.redirect;
          return;
        }
        location.reload();
        return;
      }
      alert(rsbMessage(resp, 'Erro ao processar. Tente novamente.'));
    }).fail(function(xhr){
      const body = (xhr.responseText || '').trim();
      alert(body === '0' ? 'Sessao expirada ou acao invalida. Recarregue a pagina e tente novamente.' : 'Nao foi possivel comunicar com o servidor. Tente novamente.');
    }).always(function(){
      btn.prop('disabled', false).text(original);
    });
  }

  function betStatus(form, kind, text){
    const el = form.find('.rsb-save-status');
    el.removeClass('rsb-status-dirty rsb-status-saving rsb-status-saved rsb-status-error');
    if(kind){ el.addClass('rsb-status-' + kind); }
    el.text(text || '');
  }

  function globalBetStatus(text, kind){
    const el = $('.rsb-save-all-status');
    el.removeClass('rsb-status-dirty rsb-status-saving rsb-status-saved rsb-status-error');
    if(kind){ el.addClass('rsb-status-' + kind); }
    el.text(text || 'Autosave ativo a cada 10s.');
  }

  function isBetComplete(form){
    return form.find('[name="gols_mandante"]').val() !== '' && form.find('[name="gols_visitante"]').val() !== '';
  }

  function isBetEditable(form){
    return !form.find('[name="gols_mandante"]').prop('disabled') && !form.find('[name="gols_visitante"]').prop('disabled');
  }

  function saveBetForm(form, options){
    const opts = $.extend({silent:false, manual:false}, options || {});
    const btn = form.find('button[type="submit"]');
    const original = btn.data('rsb-original-text') || btn.text();

    if(form.data('rsb-saving')){
      return $.Deferred().resolve({skipped:true}).promise();
    }

    if(!isBetEditable(form)){
      return $.Deferred().resolve({skipped:true}).promise();
    }

    if(!isBetComplete(form)){
      if(opts.manual){
        betStatus(form, 'error', 'Preencha os dois placares.');
      }
      return $.Deferred().reject({incomplete:true}).promise();
    }

    form.data('rsb-saving', true).addClass('rsb-saving');
    betStatus(form, 'saving', opts.silent ? 'Salvando...' : 'Salvando agora...');
    btn.data('rsb-original-text', original).prop('disabled', true).text('Salvando...');

    const dfd = $.Deferred();

    $.ajax({
      url: RSB_PUBLIC.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: rsbPayload(form, 'rsb_save_bet')
    }).done(function(resp){
      if(resp && resp.success){
        form.removeClass('rsb-dirty rsb-saving rsb-save-error').addClass('rsb-saved');
        betStatus(form, 'saved', 'Salvo agora');
        btn.text('Atualizar palpite');
        setTimeout(function(){
          if(!form.hasClass('rsb-dirty')){
            betStatus(form, 'saved', 'Salvo');
          }
        }, 2400);
        dfd.resolve(resp);
        return;
      }
      form.addClass('rsb-save-error');
      betStatus(form, 'error', rsbMessage(resp, 'Nao foi possivel salvar.'));
      dfd.reject(resp);
    }).fail(function(xhr){
      const body = (xhr.responseText || '').trim();
      form.addClass('rsb-save-error');
      betStatus(form, 'error', body === '0' ? 'Sessao expirada. Recarregue.' : 'Falha de conexao. Tentaremos de novo.');
      dfd.reject(xhr);
    }).always(function(){
      form.data('rsb-saving', false).removeClass('rsb-saving');
      btn.prop('disabled', false);
      if(btn.text() === 'Salvando...'){ btn.text(original); }
    });

    return dfd.promise();
  }

  $('.rsb-bet-form').on('submit',function(e){
    e.preventDefault();
    saveBetForm($(this), {manual:true});
  });

  $(document).on('input change', '.rsb-bet-form input[name="gols_mandante"], .rsb-bet-form input[name="gols_visitante"]', function(){
    const form = $(this).closest('.rsb-bet-form');
    if(!isBetEditable(form)){ return; }
    form.removeClass('rsb-saved rsb-save-error').addClass('rsb-dirty');
    betStatus(form, 'dirty', isBetComplete(form) ? 'Pendente' : 'Preencha os dois placares');
  });

  $('.rsb-save-all-bets').on('click', function(){
    const btn = $(this);
    const original = btn.text();
    const forms = $('.rsb-bet-form').filter(function(){
      const form = $(this);
      return isBetEditable(form) && isBetComplete(form);
    });

    if(!forms.length){
      globalBetStatus('Nenhum palpite preenchido para salvar.', 'error');
      return;
    }

    btn.prop('disabled', true).text('Salvando...');
    globalBetStatus('Salvando ' + forms.length + ' palpites...', 'saving');

    const requests = forms.map(function(){
      return saveBetForm($(this), {silent:true, manual:true});
    }).get();

    $.when.apply($, requests).done(function(){
      globalBetStatus('Tudo salvo agora.', 'saved');
    }).fail(function(){
      globalBetStatus('Alguns palpites precisam de atencao.', 'error');
    }).always(function(){
      btn.prop('disabled', false).text(original);
      setTimeout(function(){
        globalBetStatus('Autosave ativo a cada 10s.', '');
      }, 3200);
    });
  });

  if($('.rsb-bet-form').length){
    window.setInterval(function(){
      const dirtyForms = $('.rsb-bet-form.rsb-dirty').filter(function(){
        const form = $(this);
        return isBetEditable(form) && isBetComplete(form);
      });
      if(!dirtyForms.length){ return; }
      globalBetStatus('Autosave salvando...', 'saving');
      const requests = dirtyForms.map(function(){
        return saveBetForm($(this), {silent:true});
      }).get();
      $.when.apply($, requests).done(function(){
        globalBetStatus('Autosave concluido.', 'saved');
      }).fail(function(){
        globalBetStatus('Autosave encontrou pendencias.', 'error');
      });
    }, AUTO_SAVE_MS);

    window.addEventListener('beforeunload', function(event){
      if($('.rsb-bet-form.rsb-dirty').length){
        event.preventDefault();
        event.returnValue = '';
      }
    });
  }

  $('.rsb-profile-form').on('submit',function(e){
    e.preventDefault();
    rsbPost($(this), 'rsb_update_profile', 'Dados atualizados.');
  });

  function showAuth(target){
    $('.rsb-auth-card').removeClass('rsb-auth-visible');
    $(target).addClass('rsb-auth-visible');
    document.querySelector(target)?.scrollIntoView({behavior:'smooth', block:'center'});
  }

  $(document).on('click','.rsb-show-register',function(e){
    e.preventDefault();
    showAuth('#rsb-register-form');
  });

  $(document).on('click','.rsb-show-login',function(e){
    e.preventDefault();
    showAuth('#rsb-login-form');
  });

  $('.rsb-plugin-register-form').on('submit',function(e){
    e.preventDefault();
    rsbPost($(this), 'rsb_plugin_register', 'Conta criada com sucesso.');
  });

  $('.rsb-plugin-login-form').on('submit',function(e){
    e.preventDefault();
    rsbPost($(this), 'rsb_plugin_login', 'Login realizado com sucesso.');
  });
});
