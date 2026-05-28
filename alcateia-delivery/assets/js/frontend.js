(function($){
const debounce=(fn,w=220)=>{let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),w)}};
$(document.body).on('input change','.alcateia-delivery-simulator input,.alcateia-delivery-simulator select',debounce(function(){
const f=$(this).closest('form');const out=f.find('.alcateia-result');out.html('<div class="ad-skeleton" style="height:56px"></div>');
$.post(alcateiaDelivery.ajaxUrl,{action:'alcateia_delivery_simulate',nonce:alcateiaDelivery.nonce,qty:f.find('[name=qty]').val(),weight:f.find('[name=weight]').val(),region:f.find('[name=region]').val()},function(resp){ if(resp.success&&resp.data.cost){out.html(`<div class='ok'>Entrega Express — Receba em até ${resp.data.days} dias úteis<br><strong>Valor estimado: R$ ${parseFloat(resp.data.cost).toFixed(2)}</strong></div>`);} else {out.html('<div class="warn">No momento, não encontramos cobertura para este perfil de entrega.</div>');}});
},200));
})(jQuery);
