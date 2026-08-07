<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_BoldBuilder_Adapter {
    public function wrap($html, $uses_boldbuilder = true) {
        $classes = 'bt_bb_section bt_bb_layout_boxed_1400 bt_bb_top_spacing_large bt_bb_bottom_spacing_large privilege-semantic-indexer psi-ai-section';
        return '<section class="'.esc_attr($classes).'" data-psi-ai-version="'.esc_attr(PSI_AI_VERSION).'"><div class="bt_bb_port"><div class="bt_bb_cell"><div class="bt_bb_cell_inner"><div class="bt_bb_row"><div class="bt_bb_column col-xl-12"><div class="bt_bb_column_content"><div class="bt_bb_column_content_inner">'.$html.'</div></div></div></div></div></div></div></section>';
    }
}
