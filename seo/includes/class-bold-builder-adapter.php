<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Bold_Builder_Adapter {
    public static function wrap($html) {
        if (!$html) {
            return '';
        }
        return '<div class="ses-enriched-content" data-ses-version="' . esc_attr(SES_VERSION) . '">' . wp_kses_post($html) . '</div>';
    }

    public static function append_to_content($content, $html, $mode = 'safe') {
        if ('shortcode' === $mode) {
            return rtrim($content) . "\n\n[ses_enriched_content]";
        }
        if ('written' === $mode) {
            return rtrim($content) . "\n\n" . self::wrap($html);
        }
        return $content;
    }
}
