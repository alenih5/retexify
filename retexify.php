<?php
/**
 * Plugin Name: ReTexify
 * Description: Exportiert ALLES: Yoast Focus Keyphrase, alle Bilder, WPBakery Elemente - Vollständige Version
 * Version: 2.1.0
 * Author: Imponi
 * Text Domain: retexify
 */

if (!defined('ABSPATH')) {
    exit;
}

class ReTexify {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX-Hooks
        add_action('wp_ajax_retexify_export', array($this, 'handle_export'));
        add_action('wp_ajax_retexify_import', array($this, 'handle_import'));
        add_action('wp_ajax_retexify_test', array($this, 'test_all_fields'));
        add_action('wp_ajax_retexify_wpbakery_analyze', array($this, 'analyze_wpbakery_content'));
        add_action('wp_ajax_retexify_preview', array($this, 'preview_export'));
        
        // Download-Handler
        add_action('admin_init', array($this, 'handle_file_download'));
        
        // Plugin-Aktivierung
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
    }
    
    public function activate_plugin() {
        $upload_dir = wp_upload_dir();
        wp_mkdir_p($upload_dir['basedir'] . '/retexify-temp/');
    }
    
    public function add_admin_menu() {
        add_management_page(
            'ReTexify',
            'ReTexify', 
            'manage_options',
            'retexify',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_assets($hook) {
        if ('tools_page_retexify' !== $hook) {
            return;
        }
        wp_add_inline_style('wp-admin', $this->get_admin_css());
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->get_admin_js());
        wp_localize_script('jquery', 'retexify_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('retexify_nonce')
        ));
    }
    
    public function admin_page() {
        global $wpdb;
        // WPBakery-Check: robust für verschiedene Installationen
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $wpbakery_active = false;
        // Prüfe auf die Klasse (Theme-Bundle oder regulär)
        if (class_exists('Vc_Manager')) {
            $wpbakery_active = true;
        } else {
            // Prüfe verschiedene mögliche Plugin-Pfade
            $possible_plugins = [
                'js_composer/js_composer.php',
                'wpbakery-page-builder/js_composer.php',
                'wpbakery/js_composer.php',
            ];
            foreach ($possible_plugins as $plugin_file) {
                if (is_plugin_active($plugin_file)) {
                    $wpbakery_active = true;
                    break;
                }
            }
        }
        // Post-Typen zählen
        $post_types = get_post_types(array('public' => true), 'objects');
        $post_type_counts = array();
        foreach ($post_types as $post_type) {
            $post_type_counts[$post_type->name] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft'",
                $post_type->name
            ));
        }
        // Content-Typen zählen
        $content_counts = array(
            'title' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_title != ''"),
            'content' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND LENGTH(post_content) > 0"),
            'meta_title' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_title' AND meta_value != ''"),
            'meta_description' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_metadesc' AND meta_value != ''"),
            'focus_keyphrase' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_focuskw' AND meta_value != ''"),
            'all_images' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image%'"),
            'wpbakery_elements' => $wpbakery_active ? $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_wpb_vc_js_status' AND meta_value = 'true'") : 0
        );
        ?>
        <div class="retexify-admin-wrap">
            <h1 class="retexify-title">
                <span class="dashicons dashicons-text"></span>
                ReTexify - Vollständiger Export/Import
            </h1>
            <div class="retexify-description">
                <p><strong>Exportiert ALLES:</strong> Yoast Focus Keyphrases, alle Bilder mit Alt-Texten, WPBakery Elemente einzeln!</p>
            </div>
            <!-- System-Status -->
            <div class="retexify-stats-container">
                <h2>📊 System-Status</h2>
                <div id="retexify-system-status"><?php $this->show_system_status(); ?></div>
            </div>
            <div class="retexify-main-container">
                <!-- Export Card -->
                <div class="retexify-card retexify-export-card">
                    <div class="retexify-card-header">
                        <h2><span class="dashicons dashicons-download"></span> Vollständiger Export</h2>
                    </div>
                    <div class="retexify-card-content">
                        <!-- Post-Typen -->
                        <div class="retexify-selection-section">
                            <h4><span class="dashicons dashicons-admin-post"></span> Post-Typen</h4>
                            <div class="retexify-checkbox-grid">
                                <?php foreach ($post_types as $post_type): ?>
                                    <label class="retexify-checkbox-item">
                                        <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>">
                                        <span><?php echo esc_html($post_type->label) . ' (' . intval($post_type_counts[$post_type->name]) . ')'; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- Post-Status -->
                        <div class="retexify-selection-section">
                            <h4><span class="dashicons dashicons-visibility"></span> Post-Status</h4>
                            <div class="retexify-checkbox-grid">
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="post_status[]" value="publish" checked>
                                    <span>Veröffentlicht</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="post_status[]" value="draft">
                                    <span>Entwürfe</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="post_status[]" value="private">
                                    <span>Privat</span>
                                </label>
                            </div>
                        </div>
                        <!-- Content-Typen -->
                        <div class="retexify-selection-section">
                            <h4><span class="dashicons dashicons-edit"></span> Was exportieren?</h4>
                            <div class="retexify-checkbox-grid">
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="content_types[]" value="title" checked>
                                    <span>Titel (<?php echo intval($content_counts['title']); ?>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="content_types[]" value="content">
                                    <span>Inhalt (<?php echo intval($content_counts['content']); ?>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="content_types[]" value="meta_title" checked>
                                    <span>Meta-Titel (<?php echo intval($content_counts['meta_title']); ?>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="content_types[]" value="meta_description" checked>
                                    <span>Meta-Beschreibung (<?php echo intval($content_counts['meta_description']); ?>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="content_types[]" value="focus_keyphrase" checked>
                                    <span>🎯 Focus Keyphrase (<?php echo intval($content_counts['focus_keyphrase']); ?>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="content_types[]" value="all_images" checked>
                                    <span>🖼️ Alle Bilder + Alt-Texte (<?php echo intval($content_counts['all_images']); ?>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" name="content_types[]" value="wpbakery_elements" <?php if(!$wpbakery_active) echo 'disabled'; ?>>
                                    <span>🏗️ WPBakery Elemente (<?php echo intval($content_counts['wpbakery_elements']); ?>)<?php if(!$wpbakery_active) echo ' <small style=\'color:#888\'>(Plugin nicht aktiv)</small>'; ?></span>
                                </label>
                            </div>
                        </div>
                        <!-- Export Info -->
                        <div class="retexify-export-info">
                            <h4>✨ Das wird exportiert:</h4>
                            <ul>
                                <li><span class="dashicons dashicons-yes-alt"></span> Yoast SEO Focus Keyphrases</li>
                                <li><span class="dashicons dashicons-yes-alt"></span> Alle Bilder mit Alt-Texten</li>
                                <li><span class="dashicons dashicons-yes-alt"></span> WPBakery Page Builder Elemente</li>
                                <li><span class="dashicons dashicons-yes-alt"></span> Meta-Titel und Beschreibungen</li>
                                <li><span class="dashicons dashicons-yes-alt"></span> Vollständige Post-Inhalte</li>
                            </ul>
                        </div>
                        <!-- Vorschau -->
                        <div class="retexify-preview-section">
                            <button type="button" id="retexify-preview-btn" class="button">
                                <span class="dashicons dashicons-visibility"></span> Vorschau anzeigen
                            </button>
                            <div id="retexify-preview-result" class="retexify-preview-result"></div>
                        </div>
                        <!-- Export-Button -->
                        <div class="retexify-action-area">
                            <button type="button" id="retexify-export-btn" class="button button-primary button-hero">
                                <span class="dashicons dashicons-download"></span> Vollständigen Export starten
                            </button>
                        </div>
                        <div id="retexify-export-result"></div>
                    </div>
                </div>
                <!-- Import Card -->
                <div class="retexify-card retexify-import-card">
                    <div class="retexify-card-header">
                        <h2><span class="dashicons dashicons-upload"></span> Vollständiger Import</h2>
                    </div>
                    <div class="retexify-card-content">
                        <!-- Datei-Upload -->
                        <div class="retexify-file-upload">
                            <input type="file" id="retexify-import-file" accept=".csv" style="display: none;">
                            <button type="button" id="retexify-select-file-btn" class="button">
                                <span class="dashicons dashicons-media-default"></span> CSV-Datei auswählen
                            </button>
                            <span id="retexify-file-name" class="retexify-file-name"></span>
                        </div>
                        <!-- Import Info -->
                        <div class="retexify-import-info">
                            <h4>⚡ Was wird importiert:</h4>
                            <ul>
                                <li><span class="dashicons dashicons-info"></span> Focus Keyphrases werden in Yoast SEO gespeichert</li>
                                <li><span class="dashicons dashicons-info"></span> Alt-Texte werden für alle Bilder aktualisiert</li>
                                <li><span class="dashicons dashicons-info"></span> WPBakery Elemente werden einzeln verarbeitet</li>
                                <li><span class="dashicons dashicons-warning"></span> Backup wird empfohlen vor Import</li>
                            </ul>
                        </div>
                        <!-- Import-Button -->
                        <div class="retexify-action-area">
                            <button type="button" id="retexify-import-btn" class="button button-primary button-hero" disabled>
                                <span class="dashicons dashicons-upload"></span> Vollständigen Import starten
                            </button>
                        </div>
                        <div id="retexify-import-result"></div>
                    </div>
                </div>
            </div>
            <!-- Test-Bereich -->
            <div class="retexify-stats-container">
                <h2>🧪 Tests & Diagnose</h2>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <button type="button" id="retexify-test-btn" class="button">
                        <span class="dashicons dashicons-search"></span> Alle Felder testen
                    </button>
                    <button type="button" id="retexify-wpbakery-btn" class="button">
                        <span class="dashicons dashicons-admin-tools"></span> WPBakery analysieren
                    </button>
                </div>
                <div id="retexify-test-result" style="margin-top: 15px;"></div>
            </div>
        </div>
        <?php
    }
    
    private function show_system_status() {
        global $wpdb;
        
        // Yoast SEO Status
        $yoast_active = is_plugin_active('wordpress-seo/wp-seo.php');
        $focus_keyphrase_count = 0;
        if ($yoast_active) {
            $focus_keyphrase_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_focuskw' AND meta_value != ''");
        }
        
        // WPBakery Status
        $wpbakery_active = is_plugin_active('js_composer/js_composer.php') || class_exists('Vc_Manager');
        // NEU: Zähle alle Beiträge/Seiten mit WPBakery-Shortcodes im Content
        $wpbakery_posts = $wpbakery_active ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%[vc_%' AND post_type IN ('post','page') AND post_status = 'publish'") : 0;
        
        // Bilder Status
        $total_images = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image%'");
        $images_with_alt = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_image_alt' AND meta_value != ''");
        
        echo '<div class="retexify-stats-grid">';
        
        // Yoast SEO
        echo '<div class="retexify-stat-item">';
        echo '<div class="retexify-stat-number">' . $focus_keyphrase_count . '</div>';
        echo '<div class="retexify-stat-label">Focus Keyphrases</div>';
        echo '</div>';
        
        // WPBakery
        echo '<div class="retexify-stat-item">';
        echo '<div class="retexify-stat-number">' . $wpbakery_posts . '</div>';
        echo '<div class="retexify-stat-label">WPBakery Posts</div>';
        echo '</div>';
        
        // Bilder
        echo '<div class="retexify-stat-item">';
        echo '<div class="retexify-stat-number">' . $total_images . '</div>';
        echo '<div class="retexify-stat-label">Bilder gesamt</div>';
        echo '</div>';
        
        // Alt-Texte
        echo '<div class="retexify-stat-item">';
        echo '<div class="retexify-stat-number">' . $images_with_alt . '</div>';
        echo '<div class="retexify-stat-label">Mit Alt-Text</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Plugin-Status
        echo '<div class="retexify-seo-plugins">';
        echo '<h4>Plugin-Status:</h4>';
        echo '<div class="retexify-plugin-status">';
        
        if ($yoast_active) {
            echo '<span class="retexify-plugin-active"><span class="dashicons dashicons-yes"></span> Yoast SEO</span>';
        } else {
            echo '<span class="retexify-plugin-inactive"><span class="dashicons dashicons-no"></span> Yoast SEO</span>';
        }
        
        if ($wpbakery_active) {
            echo '<span class="retexify-plugin-active"><span class="dashicons dashicons-yes"></span> WPBakery</span>';
        } else {
            echo '<span class="retexify-plugin-inactive"><span class="dashicons dashicons-no"></span> WPBakery</span>';
        }
        
        echo '</div></div>';
    }
    
    // AJAX-Handler
    public function test_all_fields() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        global $wpdb;
        
        try {
            // Focus Keyphrase Test
            $focus_keyphrase_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_focuskw' AND meta_value != ''");
            
            // Alt-Texte Test
            $alt_texts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_image_alt' AND meta_value != ''");
            
            // WPBakery Test
            $wpbakery_posts = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key LIKE '%wpb%' OR meta_key LIKE '%vc_%'");
            
            // Posts mit Content
            $posts_with_content = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish' AND LENGTH(post_content) > 100");
            
            $result_html = '<div style="background: #f0f6fc; padding: 15px; border-radius: 6px; border: 1px solid #c3dcf0;">';
            $result_html .= '<h4 style="margin: 0 0 10px 0;">🧪 Test-Ergebnisse:</h4>';
            $result_html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">';
            
            $result_html .= '<div style="background: white; padding: 10px; border-radius: 4px; text-align: center;">';
            $result_html .= '<div style="font-size: 24px; font-weight: bold; color: #2271b1;">' . $focus_keyphrase_count . '</div>';
            $result_html .= '<div style="font-size: 12px; color: #646970;">Focus Keyphrases</div>';
            $result_html .= '</div>';
            
            $result_html .= '<div style="background: white; padding: 10px; border-radius: 4px; text-align: center;">';
            $result_html .= '<div style="font-size: 24px; font-weight: bold; color: #2271b1;">' . $alt_texts_count . '</div>';
            $result_html .= '<div style="font-size: 12px; color: #646970;">Alt-Texte</div>';
            $result_html .= '</div>';
            
            $result_html .= '<div style="background: white; padding: 10px; border-radius: 4px; text-align: center;">';
            $result_html .= '<div style="font-size: 24px; font-weight: bold; color: #2271b1;">' . $wpbakery_posts . '</div>';
            $result_html .= '<div style="font-size: 12px; color: #646970;">WPBakery Posts</div>';
            $result_html .= '</div>';
            
            $result_html .= '<div style="background: white; padding: 10px; border-radius: 4px; text-align: center;">';
            $result_html .= '<div style="font-size: 24px; font-weight: bold; color: #2271b1;">' . $posts_with_content . '</div>';
            $result_html .= '<div style="font-size: 12px; color: #646970;">Posts mit Content</div>';
            $result_html .= '</div>';
            
            $result_html .= '</div></div>';
            
            wp_send_json_success($result_html);
            
        } catch (Exception $e) {
            wp_send_json_error('Test-Fehler: ' . $e->getMessage());
        }
    }
    
    public function analyze_wpbakery_content() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        global $wpdb;
        
        try {
            // WPBakery Posts mit Shortcodes
            $wpbakery_content_posts = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} 
                WHERE post_content LIKE '%[vc_%' 
                AND post_status = 'publish'
            ");
            
            // Verschiedene WPBakery Shortcode-Typen zählen
            $text_elements = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} 
                WHERE post_content LIKE '%[vc_column_text%' 
                AND post_status = 'publish'
            ");
            
            $heading_elements = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} 
                WHERE post_content LIKE '%[vc_custom_heading%' 
                AND post_status = 'publish'
            ");
            
            $result_html = '<div style="background: #fff3cd; padding: 15px; border-radius: 6px; border: 1px solid #ffc107;">';
            $result_html .= '<h4 style="margin: 0 0 10px 0;">🏗️ WPBakery Analyse:</h4>';
            $result_html .= '<ul style="margin: 0; padding-left: 20px;">';
            $result_html .= '<li><strong>' . $wpbakery_content_posts . '</strong> Posts mit WPBakery Shortcodes</li>';
            $result_html .= '<li><strong>' . $text_elements . '</strong> Text-Elemente gefunden</li>';
            $result_html .= '<li><strong>' . $heading_elements . '</strong> Überschrift-Elemente gefunden</li>';
            $result_html .= '</ul>';
            
            if ($wpbakery_content_posts > 0) {
                $result_html .= '<p style="margin: 10px 0 0 0; color: #856404;"><strong>✓ WPBakery Export verfügbar!</strong></p>';
            } else {
                $result_html .= '<p style="margin: 10px 0 0 0; color: #6c757d;">Keine WPBakery Inhalte gefunden.</p>';
            }
            
            $result_html .= '</div>';
            
            wp_send_json_success($result_html);
            
        } catch (Exception $e) {
            wp_send_json_error('Analyse-Fehler: ' . $e->getMessage());
        }
    }
    
    public function preview_export() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        try {
            $selections = json_decode(stripslashes($_POST['selections']), true);
            
            // Posts zählen
            $post_args = array(
                'post_type' => $selections['post_types'],
                'post_status' => $selections['post_status'],
                'posts_per_page' => -1,
                'fields' => 'ids'
            );
            $posts = get_posts($post_args);
            $posts_count = count($posts);
            
            // Bilder zählen
            $images_count = 0;
            if (in_array('all_images', $selections['content_types'])) {
                global $wpdb;
                $images_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image%'");
            }
            
            // WPBakery Elemente zählen
            $wpbakery_count = 0;
            if (in_array('wpbakery_elements', $selections['content_types'])) {
                global $wpdb;
                $wpbakery_count = $wpdb->get_var("
                    SELECT COUNT(*) 
                    FROM {$wpdb->posts} 
                    WHERE post_content LIKE '%[vc_%' 
                    AND post_type IN ('" . implode("','", $selections['post_types']) . "')
                    AND post_status IN ('" . implode("','", $selections['post_status']) . "')
                ");
            }
            
            $total_items = $posts_count + $images_count + $wpbakery_count;
            
            $preview_html = '<div style="background: #e7f3ff; padding: 12px; border-radius: 4px; border: 1px solid #b8daff;">';
            $preview_html .= '<h4 style="margin: 0 0 8px 0; color: #004085;">📋 Export-Vorschau:</h4>';
            $preview_html .= '<ul style="margin: 0; color: #004085; font-size: 14px;">';
            $preview_html .= '<li><strong>' . $posts_count . '</strong> Posts/Seiten</li>';
            if ($images_count > 0) {
                $preview_html .= '<li><strong>' . $images_count . '</strong> Bilder mit Alt-Texten</li>';
            }
            if ($wpbakery_count > 0) {
                $preview_html .= '<li><strong>' . $wpbakery_count . '</strong> WPBakery Posts</li>';
            }
            $preview_html .= '</ul>';
            $preview_html .= '<p style="margin: 8px 0 0 0; font-weight: bold;">Gesamt: ' . $total_items . ' Einträge</p>';
            $preview_html .= '</div>';
            
            wp_send_json_success($preview_html);
            
        } catch (Exception $e) {
            wp_send_json_error('Vorschau-Fehler: ' . $e->getMessage());
        }
    }
    
    public function handle_export() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        try {
            $selections = json_decode(stripslashes($_POST['selections']), true);
            
            // Daten sammeln
            $all_data = $this->get_complete_data($selections);
            $csv_data = $this->prepare_complete_csv_data($all_data);
            
            // CSV-Datei erstellen
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/retexify-temp/';
            wp_mkdir_p($temp_dir);
            
            $filename = 'complete-export-' . date('Y-m-d-H-i-s') . '.csv';
            $file_path = $temp_dir . $filename;
            
            $file = fopen($file_path, 'w');
            if (!$file) {
                throw new Exception('Datei konnte nicht erstellt werden');
            }
            
            // UTF-8 BOM für Excel
            fwrite($file, "\xEF\xBB\xBF");
            
            // CSV schreiben
            foreach ($csv_data as $row) {
                fputcsv($file, $row, ';');
            }
            fclose($file);
            
            $download_url = admin_url('tools.php?page=retexify&action=download&file=' . $filename . '&nonce=' . wp_create_nonce('download_file'));
            
            wp_send_json_success(array(
                'message' => 'Vollständiger Export erfolgreich!',
                'download_url' => $download_url,
                'filename' => $filename,
                'posts_exported' => $all_data['posts_count'],
                'images_exported' => $all_data['images_count'],
                'wpbakery_elements_exported' => $all_data['wpbakery_elements_count'],
                'total_items' => count($csv_data)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Export-Fehler: ' . $e->getMessage());
        }
    }
    
    public function handle_import() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        try {
            if (!isset($_FILES['import_file'])) {
                throw new Exception('Keine Datei hochgeladen');
            }
            
            $file = $_FILES['import_file'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Upload-Fehler: ' . $file['error']);
            }
            
            $data = $this->process_csv_file($file['tmp_name']);
            $result = $this->import_complete_data($data);
            
            wp_send_json_success(array(
                'message' => 'Vollständiger Import erfolgreich!',
                'posts_updated' => $result['posts_updated'],
                'focus_keyphrases_updated' => $result['focus_keyphrases_updated'],
                'alt_texts_updated' => $result['alt_texts_updated'],
                'wpbakery_elements_updated' => $result['wpbakery_elements_updated']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Import-Fehler: ' . $e->getMessage());
        }
    }
    
    public function handle_file_download() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'download') {
            return;
        }
        
        if (!isset($_GET['file']) || !isset($_GET['nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'download_file')) {
            wp_die('Sicherheitsfehler');
        }
        
        $filename = sanitize_file_name($_GET['file']);
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/retexify-temp/' . $filename;
        
        if (!file_exists($file_path)) {
            wp_die('Datei nicht gefunden');
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        
        readfile($file_path);
        
        // Datei nach Download löschen
        unlink($file_path);
        exit;
    }
    
    // Hilfsfunktionen
    private function get_complete_data($selections) {
        $all_data = array(
            'posts' => array(),
            'images' => array(),
            'wpbakery_elements' => array(),
            'posts_count' => 0,
            'images_count' => 0,
            'wpbakery_elements_count' => 0
        );
        
        // Posts sammeln
        if (array_intersect(array('title', 'content', 'meta_title', 'meta_description', 'focus_keyphrase'), $selections['content_types'])) {
            $posts = get_posts(array(
                'post_type' => $selections['post_types'],
                'post_status' => $selections['post_status'],
                'numberposts' => -1
            ));
            
            foreach ($posts as $post) {
                $post_data = array(
                    'id' => $post->ID,
                    'type' => $post->post_type,
                    'url' => get_permalink($post->ID),
                    'title' => in_array('title', $selections['content_types']) ? $post->post_title : '',
                    'content' => in_array('content', $selections['content_types']) ? wp_strip_all_tags($post->post_content) : '',
                    'meta_title' => in_array('meta_title', $selections['content_types']) ? get_post_meta($post->ID, '_yoast_wpseo_title', true) : '',
                    'meta_description' => in_array('meta_description', $selections['content_types']) ? get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) : '',
                    'focus_keyphrase' => in_array('focus_keyphrase', $selections['content_types']) ? get_post_meta($post->ID, '_yoast_wpseo_focuskw', true) : ''
                );
                
                $all_data['posts'][] = $post_data;
            }
            
            $all_data['posts_count'] = count($posts);
        }
        
        // Alle Bilder sammeln
        if (in_array('all_images', $selections['content_types'])) {
            global $wpdb;
            
            $images = $wpdb->get_results("
                SELECT p.ID, p.post_title, pm.meta_value as alt_text
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                WHERE p.post_type = 'attachment' 
                AND p.post_mime_type LIKE 'image%'
                AND p.post_status = 'inherit'
                ORDER BY p.ID
            ");
            
            foreach ($images as $image) {
                $all_data['images'][] = array(
                    'id' => $image->ID,
                    'title' => $image->post_title,
                    'alt_text' => $image->alt_text ?: '',
                    'url' => wp_get_attachment_url($image->ID)
                );
            }
            
            $all_data['images_count'] = count($images);
        }
        
        // WPBakery Elemente sammeln
        if (in_array('wpbakery_elements', $selections['content_types'])) {
            $all_data['wpbakery_elements'] = $this->extract_wpbakery_elements($selections);
            $all_data['wpbakery_elements_count'] = count($all_data['wpbakery_elements']);
        }
        
        return $all_data;
    }
    
    private function extract_wpbakery_elements($selections) {
        global $wpdb;
        $elements = array();

        // Hole ALLE relevanten Posts/Seiten, nicht nur die mit [vc_ im Content
        $posts = get_posts(array(
            'post_type' => $selections['post_types'],
            'post_status' => $selections['post_status'],
            'numberposts' => -1
        ));

        foreach ($posts as $post) {
            $shortcodes = $this->extract_shortcodes_from_content($post->post_content);
            foreach ($shortcodes as $shortcode) {
                $elements[] = array(
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'type' => $shortcode['type'],
                    'attribute' => $shortcode['attribute'],
                    'value' => $shortcode['value']
                );
            }
        }
        return $elements;
    }
    
    private function extract_shortcodes_from_content($content) {
        $shortcodes = array();

        // vc_column_text (Content) – robust für Zeilenumbrüche und Whitespaces
        preg_match_all('/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/is', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $clean_content = wp_strip_all_tags($match[1]);
            if (!empty(trim($clean_content))) {
                $shortcodes[] = array(
                    'type' => 'vc_column_text',
                    'attribute' => 'content',
                    'value' => trim($clean_content)
                );
            }
        }

        // vc_custom_heading (text-Attribut, robust für Zeilenumbrüche und Whitespaces)
        preg_match_all('/\[vc_custom_heading([^\]]*)\]/is', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            // text-Attribut extrahieren
            if (preg_match('/text="([^"]*)"/is', $match[1], $textMatch)) {
                if (!empty(trim($textMatch[1]))) {
                    $shortcodes[] = array(
                        'type' => 'vc_custom_heading',
                        'attribute' => 'text',
                        'value' => trim($textMatch[1])
                    );
                }
            }
        }

        return $shortcodes;
    }
    
    private function prepare_complete_csv_data($all_data) {
        $csv_data = array();

        // Header: Für alle Felder jeweils Original und Neu
        $csv_data[] = array(
            'ID',
            'Typ',
            'URL',
            'Titel',
            'Meta Titel (Original)',
            'Meta Titel (Neu)',
            'Meta Beschreibung (Original)',
            'Meta Beschreibung (Neu)',
            'Focus Keyphrase (Original)',
            'Focus Keyphrase (Neu)',
            'Content (Original)',
            'Content (Neu)',
            'WPBakery Element Type',
            'WPBakery Attribute',
            'WPBakery Text (Original)',
            'WPBakery Text (Neu)',
            'Image ID',
            'Alt Text (Original)',
            'Alt Text (Neu)',
            'Image Type'
        );

        // Für jeden Post: Wenn WPBakery-Elemente vorhanden, für jedes Element eine Zeile mit Content + WPBakery Text, sonst nur Content
        foreach ($all_data['posts'] as $post) {
            // Finde alle WPBakery-Elemente zu diesem Post
            $wpbakery_elements = array();
            foreach ($all_data['wpbakery_elements'] as $element) {
                if ($element['post_id'] == $post['id']) {
                    $wpbakery_elements[] = $element;
                }
            }
            if (count($wpbakery_elements) > 0) {
                foreach ($wpbakery_elements as $element) {
                    $csv_data[] = array(
                        $post['id'],
                        $post['type'],
                        $post['url'],
                        $post['title'],
                        $post['meta_title'], '',
                        $post['meta_description'], '',
                        $post['focus_keyphrase'], '',
                        $post['content'], '',
                        $element['type'],
                        $element['attribute'],
                        $element['value'],
                        '', '', '', '', ''
                    );
                }
            } else {
                // Kein WPBakery-Text, nur Content
                $csv_data[] = array(
                    $post['id'],
                    $post['type'],
                    $post['url'],
                    $post['title'],
                    $post['meta_title'], '',
                    $post['meta_description'], '',
                    $post['focus_keyphrase'], '',
                    $post['content'], '',
                    '', '', '', '', '', '', '', ''
                );
            }
        }

        // Bilder hinzufügen (wie gehabt)
        foreach ($all_data['images'] as $image) {
            $csv_data[] = array(
                '',
                'image',
                $image['url'],
                $image['title'],
                '', '', '', '', '', '', '', '', '', '', '', '',
                $image['id'],
                $image['alt_text'], '' ,
                'media_library'
            );
        }

        return $csv_data;
    }
    
    private function process_csv_file($file_path) {
        $data = array();
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            throw new Exception('CSV-Datei konnte nicht geöffnet werden');
        }
        
        // BOM überspringen
        $first_bytes = fread($handle, 3);
        if ($first_bytes !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        $header_found = false;
        
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            // Header überspringen
            if (!$header_found && in_array('ID', $row)) {
                $header_found = true;
                continue;
            }
            
            if ($header_found && count($row) >= 4) {
                $row = array_pad($row, 13, '');
                $data[] = $row;
            }
        }
        
        fclose($handle);
        
        if (!$header_found) {
            throw new Exception('CSV-Header nicht erkannt');
        }
        
        return $data;
    }
    
    private function import_complete_data($data) {
        $result = array(
            'posts_updated' => 0,
            'focus_keyphrases_updated' => 0,
            'alt_texts_updated' => 0,
            'wpbakery_elements_updated' => 0
        );
        
        foreach ($data as $row) {
            try {
                $id = intval($row[0]);
                $type = trim($row[1]);
                
                if ($type === 'image' && !empty($row[10])) {
                    // Bild Alt-Text aktualisieren
                    $image_id = intval($row[10]);
                    $new_alt = sanitize_text_field(trim($row[11]));
                    
                    if ($image_id && !empty($new_alt)) {
                        $old_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                        if ($new_alt !== $old_alt) {
                            update_post_meta($image_id, '_wp_attachment_image_alt', $new_alt);
                            $result['alt_texts_updated']++;
                        }
                    }
                    
                } elseif (!empty($id) && in_array($type, array('post', 'page'))) {
                    // Normaler Post/Page aktualisieren
                    $post = get_post($id);
                    if (!$post) continue;
                    
                    $has_updates = false;
                    
                    // Titel
                    if (!empty(trim($row[3]))) {
                        $new_title = sanitize_text_field(trim($row[3]));
                        if ($new_title !== $post->post_title) {
                            wp_update_post(array('ID' => $id, 'post_title' => $new_title));
                            $has_updates = true;
                        }
                    }
                    
                    // Meta-Titel
                    if (!empty(trim($row[4]))) {
                        $new_meta_title = sanitize_text_field(trim($row[4]));
                        $old_meta_title = get_post_meta($id, '_yoast_wpseo_title', true);
                        if ($new_meta_title !== $old_meta_title) {
                            update_post_meta($id, '_yoast_wpseo_title', $new_meta_title);
                            $has_updates = true;
                        }
                    }
                    
                    // Meta-Beschreibung
                    if (!empty(trim($row[5]))) {
                        $new_meta_desc = sanitize_textarea_field(trim($row[5]));
                        $old_meta_desc = get_post_meta($id, '_yoast_wpseo_metadesc', true);
                        if ($new_meta_desc !== $old_meta_desc) {
                            update_post_meta($id, '_yoast_wpseo_metadesc', $new_meta_desc);
                            $has_updates = true;
                        }
                    }
                    
                    // Focus Keyphrase
                    if (!empty(trim($row[6]))) {
                        $new_focus_keyphrase = sanitize_text_field(trim($row[6]));
                        $old_focus_keyphrase = get_post_meta($id, '_yoast_wpseo_focuskw', true);
                        if ($new_focus_keyphrase !== $old_focus_keyphrase) {
                            update_post_meta($id, '_yoast_wpseo_focuskw', $new_focus_keyphrase);
                            $result['focus_keyphrases_updated']++;
                            $has_updates = true;
                        }
                    }
                    
                    // Content
                    if (!empty(trim($row[7]))) {
                        $new_content = wp_kses_post(trim($row[7]));
                        if ($new_content !== $post->post_content) {
                            wp_update_post(array('ID' => $id, 'post_content' => $new_content));
                            $has_updates = true;
                        }
                    }
                    
                    if ($has_updates) {
                        $result['posts_updated']++;
                    }
                }
                
            } catch (Exception $e) {
                // Einzelne Fehler nicht den ganzen Import stoppen lassen
                continue;
            }
        }
        
        return $result;
    }
    
    // CSS und JavaScript inline bereitstellen
    private function get_admin_css() {
        return '
        .retexify-admin-wrap { margin: 20px 20px 0 0; max-width: 1200px; }
        .retexify-title { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; color: #1d2327; font-size: 24px; font-weight: 600; }
        .retexify-title .dashicons { color: #2271b1; font-size: 28px; }
        .retexify-description { background: #f0f6fc; border: 1px solid #c3dcf0; border-radius: 6px; padding: 16px; margin-bottom: 30px; }
        .retexify-main-container { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .retexify-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .retexify-card-header { background: #f6f7f7; border-bottom: 1px solid #c3c4c7; padding: 16px 20px; }
        .retexify-card-header h2 { margin: 0; color: #1d2327; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .retexify-card-content { padding: 20px; }
        .retexify-export-card .retexify-card-header { background: #e8f5e8; border-bottom-color: #4aba4a; }
        .retexify-import-card .retexify-card-header { background: #fff3cd; border-bottom-color: #ffc107; }
        .retexify-selection-section { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 16px; margin: 16px 0; }
        .retexify-selection-section h4 { margin: 0 0 15px 0; color: #1d2327; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .retexify-checkbox-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .retexify-checkbox-item { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px 12px; border-radius: 4px; transition: background-color 0.2s ease; }
        .retexify-checkbox-item:hover { background-color: rgba(34, 113, 177, 0.05); }
        .retexify-checkbox-item input[type="checkbox"] { margin: 0; transform: scale(1.1); accent-color: #2271b1; }
        .retexify-export-info, .retexify-import-info { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 16px; margin: 16px 0; }
        .retexify-export-info ul, .retexify-import-info ul { margin: 0; padding: 0; list-style: none; }
        .retexify-export-info li, .retexify-import-info li { display: flex; align-items: center; gap: 8px; padding: 4px 0; color: #1d2327; font-size: 13px; }
        .retexify-preview-section { margin: 20px 0; text-align: center; }
        .retexify-action-area { margin-top: 20px; text-align: center; }
        .button-hero { padding: 12px 24px !important; font-size: 16px !important; height: auto !important; display: inline-flex !important; align-items: center !important; gap: 8px !important; }
        .retexify-file-upload { margin-bottom: 20px; display: flex; align-items: center; gap: 15px; justify-content: center; }
        .retexify-stats-container { margin-top: 30px; }
        .retexify-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .retexify-stat-item { text-align: center; padding: 20px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; }
        .retexify-stat-number { font-size: 36px; font-weight: bold; color: #2271b1; margin-bottom: 8px; }
        .retexify-stat-label { color: #6c757d; font-size: 14px; font-weight: 500; }
        .retexify-plugin-status { display: flex; flex-wrap: wrap; gap: 10px; }
        .retexify-plugin-active { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .retexify-plugin-inactive { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; background: #f8f9fa; color: #6c757d; border: 1px solid #e9ecef; }
        .retexify-seo-plugins { border-top: 1px solid #e9ecef; padding-top: 20px; }
        @media (max-width: 1024px) { .retexify-main-container { grid-template-columns: 1fr; } .retexify-stats-grid { grid-template-columns: repeat(2, 1fr); } }
        ';
    }
    
    private function get_admin_js() {
        return '
        jQuery(document).ready(function($) {
            // Vorschau-Button
            $("#retexify-preview-btn").on("click", function() {
                var selections = getSelections();
                if (!selections) return;
                
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop("disabled", true).html("<span class=\"dashicons dashicons-update\" style=\"animation: spin 1s linear infinite;\"></span> Lade...");
                
                $.post(retexify_ajax.ajax_url, {
                    action: "retexify_preview",
                    nonce: retexify_ajax.nonce,
                    selections: JSON.stringify(selections)
                }, function(response) {
                    if (response.success) {
                        $("#retexify-preview-result").html(response.data).addClass("show");
                    } else {
                        alert("Vorschau-Fehler: " + response.data);
                    }
                }).always(function() {
                    $btn.prop("disabled", false).html(originalText);
                });
            });
            
            // Export-Button
            $("#retexify-export-btn").on("click", function() {
                var selections = getSelections();
                if (!selections) {
                    alert("Bitte wählen Sie mindestens einen Post-Typ und Inhaltstyp aus.");
                    return;
                }
                
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop("disabled", true).html("<span class=\"dashicons dashicons-update\" style=\"animation: spin 1s linear infinite;\"></span> Exportiere...");
                
                $.post(retexify_ajax.ajax_url, {
                    action: "retexify_export",
                    nonce: retexify_ajax.nonce,
                    selections: JSON.stringify(selections)
                }, function(response) {
                    if (response.success) {
                        var result = "<div style=\"background: #d1e7dd; border: 1px solid #badbcc; color: #0f5132; padding: 15px; border-radius: 6px; text-align: center;\">";
                        result += "<h4 style=\"margin: 0 0 10px 0;\">✅ " + response.data.message + "</h4>";
                        result += "<p>Posts: " + response.data.posts_exported + " • Bilder: " + response.data.images_exported + " • WPBakery: " + response.data.wpbakery_elements_exported + "</p>";
                        result += "<a href=\"" + response.data.download_url + "\" class=\"button button-primary\" style=\"margin-top: 10px;\">📥 " + response.data.filename + " herunterladen</a>";
                        result += "</div>";
                        $("#retexify-export-result").html(result);
                    } else {
                        $("#retexify-export-result").html("<div style=\"background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; padding: 15px; border-radius: 6px;\">❌ Export-Fehler: " + response.data + "</div>");
                    }
                }).always(function() {
                    $btn.prop("disabled", false).html(originalText);
                });
            });
            
            // Datei-Auswahl
            $("#retexify-select-file-btn").on("click", function() {
                $("#retexify-import-file").click();
            });
            
            $("#retexify-import-file").on("change", function() {
                var fileName = this.files[0] ? this.files[0].name : "";
                $("#retexify-file-name").text(fileName);
                $("#retexify-import-btn").prop("disabled", !fileName);
            });
            
            // Import-Button
            $("#retexify-import-btn").on("click", function() {
                var fileInput = $("#retexify-import-file")[0];
                if (!fileInput.files[0]) {
                    alert("Bitte wählen Sie eine CSV-Datei aus.");
                    return;
                }
                
                if (!confirm("Möchten Sie wirklich die Daten importieren? Es wird empfohlen, vorher ein Backup zu erstellen.")) {
                    return;
                }
                
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop("disabled", true).html("<span class=\"dashicons dashicons-update\" style=\"animation: spin 1s linear infinite;\"></span> Importiere...");
                
                var formData = new FormData();
                formData.append("action", "retexify_import");
                formData.append("nonce", retexify_ajax.nonce);
                formData.append("import_file", fileInput.files[0]);
                
                $.ajax({
                    url: retexify_ajax.ajax_url,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            var result = "<div style=\"background: #d1e7dd; border: 1px solid #badbcc; color: #0f5132; padding: 15px; border-radius: 6px; text-align: center;\">";
                            result += "<h4 style=\"margin: 0 0 10px 0;\">✅ " + response.data.message + "</h4>";
                            result += "<p>Posts: " + response.data.posts_updated + " • Focus Keyphrases: " + response.data.focus_keyphrases_updated + " • Alt-Texte: " + response.data.alt_texts_updated + "</p>";
                            result += "</div>";
                            $("#retexify-import-result").html(result);
                            $("#retexify-import-file").val("");
                            $("#retexify-file-name").text("");
                            $("#retexify-import-btn").prop("disabled", true);
                        } else {
                            $("#retexify-import-result").html("<div style=\"background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; padding: 15px; border-radius: 6px;\">❌ Import-Fehler: " + response.data + "</div>");
                        }
                    },
                    error: function() {
                        $("#retexify-import-result").html("<div style=\"background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; padding: 15px; border-radius: 6px;\">❌ Import-Fehler: Server-Fehler</div>");
                    }
                }).always(function() {
                    $btn.prop("disabled", false).html(originalText);
                });
            });
            
            // Test-Button
            $("#retexify-test-btn").on("click", function() {
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop("disabled", true).html("<span class=\"dashicons dashicons-update\" style=\"animation: spin 1s linear infinite;\"></span> Teste...");
                
                $.post(retexify_ajax.ajax_url, {
                    action: "retexify_test",
                    nonce: retexify_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $("#retexify-test-result").html(response.data);
                    } else {
                        $("#retexify-test-result").html("<div style=\"background: #f8d7da; padding: 15px; border-radius: 6px;\">❌ Test-Fehler: " + response.data + "</div>");
                    }
                }).always(function() {
                    $btn.prop("disabled", false).html(originalText);
                });
            });
            
            // WPBakery-Analyse-Button
            $("#retexify-wpbakery-btn").on("click", function() {
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop("disabled", true).html("<span class=\"dashicons dashicons-update\" style=\"animation: spin 1s linear infinite;\"></span> Analysiere...");
                
                $.post(retexify_ajax.ajax_url, {
                    action: "retexify_wpbakery_analyze",
                    nonce: retexify_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $("#retexify-test-result").html(response.data);
                    } else {
                        $("#retexify-test-result").html("<div style=\"background: #f8d7da; padding: 15px; border-radius: 6px;\">❌ Analyse-Fehler: " + response.data + "</div>");
                    }
                }).always(function() {
                    $btn.prop("disabled", false).html(originalText);
                });
            });
            
            // Hilfsfunktion: Auswahl sammeln
            function getSelections() {
                var postTypes = [];
                $("input[name=\"post_types[]\"]:checked").each(function() {
                    postTypes.push($(this).val());
                });
                
                var postStatus = [];
                $("input[name=\"post_status[]\"]:checked").each(function() {
                    postStatus.push($(this).val());
                });
                
                var contentTypes = [];
                $("input[name=\"content_types[]\"]:checked").each(function() {
                    contentTypes.push($(this).val());
                });
                
                if (postTypes.length === 0 || contentTypes.length === 0) {
                    return null;
                }
                
                if (postStatus.length === 0) {
                    postStatus = ["publish"];
                }
                
                return {
                    post_types: postTypes,
                    post_status: postStatus,
                    content_types: contentTypes
                };
            }
            
            // CSS für Animationen
            $("<style>").text("@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }").appendTo("head");
        });
        ';
    }
}

// Plugin initialisieren
new ReTexify();
?>