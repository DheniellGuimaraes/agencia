<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Semantic_Engine {
    public function build($context, $settings) {
        return (new SES_Content_Matrix())->generate($context, $settings);
    }
}
