<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Dashboard {
    public static function card($label, $value, $tone = 'blue') {
        echo '<div class="ses-card ses-card-' . esc_attr($tone) . '"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
    }
}
