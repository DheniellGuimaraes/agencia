(function($){
const modal=$('#ad-import-modal');
$(document).on('click','#ad-open-import',()=>modal.prop('hidden',false));
$(document).on('click','#ad-close-import',()=>modal.prop('hidden',true));
})(jQuery);
