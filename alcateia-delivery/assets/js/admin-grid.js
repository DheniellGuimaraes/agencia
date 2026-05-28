(function($){
let xhr=null; const debounce=(fn,wait=260)=>{let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),wait)}};
function render(rows){ if(!rows.length){$('#alcateia-grid').html('<div class="ad-empty">Nenhuma regra encontrada.</div>');return;} let h='<table class="ad-grid"><thead><tr><th>ID</th><th>Região</th><th>Peso</th><th>Qtd</th><th>Frete</th><th>Status</th></tr></thead><tbody>'; rows.forEach(r=>{h+=`<tr><td>${r.id}</td><td>${r.region}</td><td>${r.weight_from} - ${r.weight_to}</td><td>${r.qty_from} - ${r.qty_to}</td><td>R$ ${parseFloat(r.shipping_cost).toFixed(2)}</td><td><span class="ad-badge ${r.active==1?'success':''}">${r.active==1?'Ativa':'Inativa'}</span></td></tr>`}); h+='</tbody></table>'; $('#alcateia-grid').html(h); }
function load(q=''){ if(xhr){xhr.abort();} $('#alcateia-grid').html('<div class="ad-skeleton"></div>'); xhr=$.get(alcateiaAdmin.ajaxUrl,{action:'alcateia_delivery_grid',nonce:alcateiaAdmin.nonce,q},r=>{if(r.success){render(r.data);}}); }
$(document).on('click','#alcateia-refresh-grid',()=>load($('#ad-grid-search').val()||''));
$(document).on('input','#ad-grid-search',debounce(function(){load(this.value||'');},220));
$(load);
})(jQuery);
