<?php

if (!defined('ABSPATH')) exit;

$dashboard_brand_name = 'Agência Privilége Chat';

?><article class="elfsight-admin-page-welcome elfsight-admin-page" data-elfsight-admin-page-id="welcome">
    <h1><?php /* translators: %s: Plugin Name */ printf(esc_html__('Welcome to %s', $this->textDomain), $dashboard_brand_name); ?></h1>

    <p class="elfsight-admin-page-welcome-subheading">
        <?php esc_html_e($this->description); ?><br>
        <strong><?php esc_html_e('Let’s create your first widget!', $this->textDomain); ?></strong>
    </p>

    <a class="elfsight-admin-page-welcome-button elfsight-admin-button-large elfsight-admin-button-green elfsight-admin-button" href="#/add-widget/" data-elfsight-admin-page="add-widget"><?php esc_html_e('Create widget', $this->textDomain); ?></a>
</article>