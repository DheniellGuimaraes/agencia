(function($){
$(document).on('submit','#ad-import-modal form',function(){ const p=$('#ad-import-progress'); let v=0; const timer=setInterval(()=>{v=Math.min(95,v+7);p.val(v);},120); setTimeout(()=>clearInterval(timer),2000);});
})(jQuery);
