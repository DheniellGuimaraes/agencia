(function($){
function badge(v){return `<div class='ad-health-item'><strong>${v.label}</strong><div>${v.value}</div></div>`}
function loadHealth(){ $.get(alcateiaAdmin.ajaxUrl,{action:'alcateia_delivery_health',nonce:alcateiaAdmin.nonce},r=>{ if(!r.success)return; const h=r.data; const items=[{label:'REST API',value:'Online'},{label:'AJAX',value:'Online'},{label:'Cache',value:h.cache_version},{label:'Banco',value:h.db_table},{label:'Licença',value:'scaffold'},{label:'HPOS',value:'Compatível'}]; $('#ad-health-grid').html(items.map(badge).join('')); }); }
$(loadHealth);
})(jQuery);
