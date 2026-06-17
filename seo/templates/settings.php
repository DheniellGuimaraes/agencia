<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap ses-admin">
    <h1>Configurações do SEO Enrichment Studio</h1>
    <?php if (isset($_GET['updated'])) : ?><div class="notice notice-success"><p>Configurações salvas.</p></div><?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ses-card">
        <?php wp_nonce_field('ses_save_settings'); ?>
        <input type="hidden" name="action" value="ses_save_settings">
        <h2>Fontes e escopo</h2>
        <p><label>Post types<br><input type="text" name="ses[post_types]" value="<?php echo esc_attr(implode(',', (array) ($settings['post_types'] ?? array('page')))); ?>" class="regular-text"></label></p>
        <p><label>Padrões de slug<br><textarea name="ses[slug_patterns]" rows="5" class="large-text code"><?php echo esc_textarea(implode("\n", (array) ($settings['slug_patterns'] ?? array()))); ?></textarea></label></p>
        <p><label>Sitemaps para enriquecer<br><textarea name="ses[sitemap_sources]" rows="7" class="large-text code"><?php echo esc_textarea(implode("\n", (array) ($settings['sitemap_sources'] ?? array()))); ?></textarea></label></p>
        <p><label><input type="checkbox" name="ses[enrich_from_sitemaps]" value="1" <?php checked(!empty($settings['enrich_from_sitemaps'])); ?>> Enriquecer apenas URLs presentes nos sitemaps</label></p>
        <p><label><input type="checkbox" name="ses[sync_sitemaps_before_enrich]" value="1" <?php checked(!empty($settings['sync_sitemaps_before_enrich'])); ?>> Sincronizar sitemaps automaticamente antes de enriquecer</label></p>

        <h2>Proteções</h2>
        <p><label>Slugs protegidos<br><textarea name="ses[protected_slugs]" rows="6" class="large-text code"><?php echo esc_textarea(implode("\n", (array) ($settings['protected_slugs'] ?? array()))); ?></textarea></label></p>
        <p><label>Caminhos protegidos<br><textarea name="ses[protected_paths]" rows="8" class="large-text code"><?php echo esc_textarea(implode("\n", (array) ($settings['protected_paths'] ?? array()))); ?></textarea></label></p>
        <p><label>Contém no slug/caminho<br><textarea name="ses[protected_contains]" rows="4" class="large-text code"><?php echo esc_textarea(implode("\n", (array) ($settings['protected_contains'] ?? array()))); ?></textarea></label></p>

        <h2>Qualidade e renderização</h2>
        <p><label>Score mínimo <input type="number" name="ses[minimum_score]" value="<?php echo esc_attr(absint($settings['minimum_score'] ?? 75)); ?>" min="0" max="100"></label></p>
        <p><label>Tamanho mínimo do conteúdo <input type="number" name="ses[minimum_content_length]" value="<?php echo esc_attr(absint($settings['minimum_content_length'] ?? 3000)); ?>" min="3000"></label></p>
        <p><label>Limite de similaridade <input type="number" name="ses[similarity_limit]" value="<?php echo esc_attr(absint($settings['similarity_limit'] ?? 75)); ?>" min="1" max="100"></label></p>
        <p><label>Modo de renderização
            <select name="ses[render_mode]">
                <option value="safe" <?php selected(($settings['render_mode'] ?? 'safe'), 'safe'); ?>>Seguro: metadado + append no frontend</option>
                <option value="shortcode" <?php selected(($settings['render_mode'] ?? 'safe'), 'shortcode'); ?>>Shortcode</option>
                <option value="written" <?php selected(($settings['render_mode'] ?? 'safe'), 'written'); ?>>Escrever HTML no conteúdo</option>
            </select>
        </label></p>
        <p><label><input type="checkbox" name="ses[yoast_enabled]" value="1" <?php checked(!empty($settings['yoast_enabled'])); ?>> Atualizar Yoast SEO</label></p>
        <p><label><input type="checkbox" name="ses[schema_faq_enabled]" value="1" <?php checked(!empty($settings['schema_faq_enabled'])); ?>> Schema FAQ</label></p>
        <p><label><input type="checkbox" name="ses[internal_links_enabled]" value="1" <?php checked(!empty($settings['internal_links_enabled'])); ?>> Links internos</label></p>
        <p><label><input type="checkbox" name="ses[write_to_post_content]" value="1" <?php checked(!empty($settings['write_to_post_content'])); ?>> Também gravar no post_content</label></p>

        <h2>Empresa</h2>
        <p><label>Nome da empresa<br><input type="text" name="ses[company_name]" value="<?php echo esc_attr($settings['company_name'] ?? ''); ?>" class="regular-text"></label></p>
        <p><label>WhatsApp<br><input type="text" name="ses[whatsapp]" value="<?php echo esc_attr($settings['whatsapp'] ?? ''); ?>" class="regular-text"></label></p>
        <p><label>URL de contato<br><input type="url" name="ses[contact_url]" value="<?php echo esc_attr($settings['contact_url'] ?? ''); ?>" class="regular-text"></label></p>
        <?php submit_button('Salvar configurações'); ?>
    </form>
</div>
