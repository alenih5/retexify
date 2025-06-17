<?php
/**
 * Plugin Name: ReTexify
 * Description: WordPress Text Export/Import Plugin mit WPBakery/Salient Support
 * Version: 2.4.0
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
        add_action('wp_ajax_retexify_preview', array($this, 'preview_export'));
        add_action('wp_ajax_retexify_get_stats', array($this, 'get_enhanced_stats'));
        add_action('wp_ajax_retexify_get_counts', array($this, 'get_content_counts'));
        add_action('wp_ajax_retexify_debug_export', array($this, 'debug_export'));
        add_action('wp_ajax_retexify_check_wpbakery', array($this, 'check_wpbakery_status'));
        
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
        ?>
        <div class="retexify-admin-wrap">
            <h1 class="retexify-title">
                <span class="dashicons dashicons-text"></span>
                ReTexify v2.4.0 📝
            </h1>
            
            <div class="retexify-description">
                <p><strong>📝 ReTexify:</strong> Content ohne WPBakery-Shortcodes • WPBakery Meta-Titel & Meta-Content • ID numerisch • Saubere Trennung</p>
            </div>
            
            <!-- System-Status -->
            <div class="retexify-debug-container">
                <h2>🔍 System-Status</h2>
                <div id="retexify-system-status">
                    <?php $this->show_system_status(); ?>
                </div>
                <button type="button" id="retexify-debug-btn" class="button">
                    <span class="dashicons dashicons-admin-tools"></span> Debug Export
                </button>
            </div>
            
            <!-- Dashboard -->
            <div class="retexify-dashboard-container">
                <h2>📊 Content-Dashboard</h2>
                <div id="retexify-enhanced-dashboard">
                    <div class="retexify-loading-dashboard">🔄 Lade Dashboard...</div>
                </div>
                <button type="button" id="retexify-refresh-stats" class="button">
                    <span class="dashicons dashicons-update"></span> Aktualisieren
                </button>
            </div>
            
            <div class="retexify-main-container">
                <!-- Export Card -->
                <div class="retexify-card retexify-export-card">
                    <div class="retexify-card-header">
                        <h2><span class="dashicons dashicons-download"></span> Export</h2>
                    </div>
                    <div class="retexify-card-content">
                        
                        <!-- Post-Typen -->
                        <div class="retexify-selection-section">
                            <h4><span class="dashicons dashicons-admin-post"></span> Post-Typen</h4>
                            <div class="retexify-checkbox-grid" id="retexify-post-types-grid">
                                <!-- Wird per JavaScript gefüllt -->
                            </div>
                        </div>
                        
                        <!-- Post-Status -->
                        <div class="retexify-selection-section">
                            <h4><span class="dashicons dashicons-visibility"></span> Status</h4>
                            <div class="retexify-checkbox-grid">
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" class="retexify-status-checkbox" name="post_status[]" value="publish" checked>
                                    <span class="retexify-checkbox-label">Veröffentlicht (<span class="retexify-count" id="count-publish">0</span>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" class="retexify-status-checkbox" name="post_status[]" value="draft">
                                    <span class="retexify-checkbox-label">Entwürfe (<span class="retexify-count" id="count-draft">0</span>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" class="retexify-status-checkbox" name="post_status[]" value="private">
                                    <span class="retexify-checkbox-label">Privat (<span class="retexify-count" id="count-private">0</span>)</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Content-Typen -->
                        <div class="retexify-selection-section">
                            <h4><span class="dashicons dashicons-edit"></span> Inhalte</h4>
                            <div class="retexify-checkbox-grid">
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" class="retexify-content-checkbox" name="content_types[]" value="title" checked>
                                    <span class="retexify-checkbox-label">📝 Titel (<span class="retexify-count" id="count-title">0</span>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" class="retexify-content-checkbox" name="content_types[]" value="content">
                                    <span class="retexify-checkbox-label">📄 Content (<span class="retexify-count" id="count-content">0</span>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" class="retexify-content-checkbox" name="content_types[]" value="meta_title" checked>
                                    <span class="retexify-checkbox-label">🎯 Meta-Titel (<span class="retexify-count" id="count-meta-title">0</span>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" class="retexify-content-checkbox" name="content_types[]" value="meta_description" checked>
                                    <span class="retexify-checkbox-label">📊 Meta-Beschreibung (<span class="retexify-count" id="count-meta-desc">0</span>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" class="retexify-content-checkbox" name="content_types[]" value="focus_keyphrase" checked>
                                    <span class="retexify-checkbox-label">🔑 Focus Keyphrase (<span class="retexify-count" id="count-focus">0</span>)</span>
                                </label>
                                <!-- WPBakery Optionen - nur anzeigen wenn erkannt -->
                                <label class="retexify-checkbox-item" id="retexify-wpbakery-option" style="display: none;">
                                    <input type="checkbox" class="retexify-content-checkbox" name="content_types[]" value="wpbakery_text" checked>
                                    <span class="retexify-checkbox-label">🏗️ WPBakery Text (<span class="retexify-count" id="count-wpbakery">0</span>)</span>
                                </label>
                                <label class="retexify-checkbox-item" id="retexify-wpbakery-meta-title-option" style="display: none;">
                                    <input type="checkbox" class="retexify-content-checkbox" name="content_types[]" value="wpbakery_meta_title" checked>
                                    <span class="retexify-checkbox-label">🎯 WPBakery Meta-Titel (<span class="retexify-count" id="count-wpbakery-meta-title">0</span>)</span>
                                </label>
                                <label class="retexify-checkbox-item" id="retexify-wpbakery-meta-content-option" style="display: none;">
                                    <input type="checkbox" class="retexify-content-checkbox" name="content_types[]" value="wpbakery_meta_content" checked>
                                    <span class="retexify-checkbox-label">📊 WPBakery Meta-Content (<span class="retexify-count" id="count-wpbakery-meta-content">0</span>)</span>
                                </label>
                                <label class="retexify-checkbox-item">
                                    <input type="checkbox" class="retexify-content-checkbox" name="content_types[]" value="alt_texts">
                                    <span class="retexify-checkbox-label">🖼️ Alt-Texte (<span class="retexify-count" id="count-images">0</span>)</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Export Info -->
                        <div class="retexify-export-info">
                            <h4>✨ ReTexify CSV-Format:</h4>
                            <ul>
                                <li><span class="dashicons dashicons-yes-alt"></span> <strong>ID-Spalte:</strong> Immer numerisch (Post-ID)</li>
                                <li><span class="dashicons dashicons-yes-alt"></span> <strong>Content:</strong> Bereinigt ohne WPBakery-Shortcodes</li>
                                <li><span class="dashicons dashicons-yes-alt"></span> <strong>WPBakery:</strong> Text, Meta-Titel & Meta-Content getrennt</li>
                                <li><span class="dashicons dashicons-edit"></span> <strong>(Neu)-Spalten:</strong> Hier Ihre neuen Texte eintragen</li>
                                <li id="retexify-wpbakery-info" style="display: none;"><span class="dashicons dashicons-yes-alt"></span> WPBakery/Salient Integration aktiv</li>
                            </ul>
                        </div>
                        
                        <!-- Vorschau -->
                        <div class="retexify-preview-section">
                            <button type="button" id="retexify-preview-btn" class="button">
                                <span class="dashicons dashicons-visibility"></span> Export-Vorschau
                            </button>
                            <div id="retexify-preview-result" class="retexify-preview-result"></div>
                        </div>
                        
                        <!-- Export-Button -->
                        <div class="retexify-action-area">
                            <button type="button" id="retexify-export-btn" class="button button-primary button-hero">
                                <span class="dashicons dashicons-download"></span> Export starten
                            </button>
                        </div>
                        
                        <div id="retexify-export-result"></div>
                    </div>
                </div>
                
                <!-- Import Card -->
                <div class="retexify-card retexify-import-card">
                    <div class="retexify-card-header">
                        <h2><span class="dashicons dashicons-upload"></span> Import</h2>
                    </div>
                    <div class="retexify-card-content">
                        
                        <!-- Datei-Upload -->
                        <div class="retexify-file-upload">
                            <input type="file" id="retexify-import-file" accept=".csv" style="display: none;">
                            <button type="button" id="retexify-select-file-btn" class="button button-large">
                                <span class="dashicons dashicons-media-default"></span> CSV-Datei auswählen
                            </button>
                            <span id="retexify-file-name" class="retexify-file-name"></span>
                        </div>
                        
                        <!-- Import Info -->
                        <div class="retexify-import-info">
                            <h4>⚡ Import-Features:</h4>
                            <ul>
                                <li><span class="dashicons dashicons-info"></span> Nur (Neu)-Spalten werden importiert</li>
                                <li><span class="dashicons dashicons-info"></span> (Original)-Spalten bleiben unverändert</li>
                                <li><span class="dashicons dashicons-info"></span> ID-Validierung vor Import</li>
                                <li><span class="dashicons dashicons-warning"></span> Nur ausgefüllte Felder überschreiben</li>
                                <li id="retexify-wpbakery-import-info" style="display: none;"><span class="dashicons dashicons-info"></span> WPBakery-Texte werden intelligent ersetzt</li>
                            </ul>
                        </div>
                        
                        <!-- Import-Button -->
                        <div class="retexify-action-area">
                            <button type="button" id="retexify-import-btn" class="button button-primary button-hero" disabled>
                                <span class="dashicons dashicons-upload"></span> Import starten
                            </button>
                        </div>
                        
                        <div id="retexify-import-result"></div>
                    </div>
                </div>
            </div>
            
            <!-- Test-Bereich -->
            <div class="retexify-stats-container">
                <h2>🧪 System-Tests</h2>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <button type="button" id="retexify-test-btn" class="button">
                        <span class="dashicons dashicons-search"></span> System testen
                    </button>
                    <button type="button" id="retexify-wpbakery-btn" class="button">
                        <span class="dashicons dashicons-admin-tools"></span> WPBakery/Salient prüfen
                    </button>
                </div>
                <div id="retexify-test-result" style="margin-top: 15px;"></div>
            </div>
        </div>
        <?php
    }
    
    // System-Status direkt anzeigen
    private function show_system_status() {
        global $wpdb;
        
        // WPBakery/Salient Detection
        $is_salient = (get_template() === 'salient' || get_stylesheet() === 'salient');
        $wpbakery_plugin = is_plugin_active('js_composer/js_composer.php');
        $wpbakery_functions = function_exists('vc_map');
        $wpbakery_constant = defined('WPB_VC_VERSION');
        
        $wpbakery_detected = $wpbakery_plugin || $wpbakery_functions || $wpbakery_constant;
        
        // SEO Plugin Detection
        $yoast_active = is_plugin_active('wordpress-seo/wp-seo.php');
        $rankmath_active = is_plugin_active('seo-by-rank-math/rank-math.php');
        $aioseo_active = is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php');
        
        // Quick Stats
        $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'");
        $posts_with_vc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE (post_content LIKE '%[vc_%' OR post_content LIKE '%[nectar_%') AND post_status = 'publish'");
        $posts_with_meta_titles = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_title', 'rank_math_title', '_aioseop_title') AND meta_value != ''");
        
        echo '<div class="retexify-system-status-grid">';
        
        // Theme Status
        echo '<div class="retexify-status-item">';
        echo '<div class="retexify-status-icon">' . ($is_salient ? '✅' : '❌') . '</div>';
        echo '<div class="retexify-status-content">';
        echo '<div class="retexify-status-title">Salient Theme</div>';
        echo '<div class="retexify-status-detail">' . ($is_salient ? 'Erkannt' : 'Nicht erkannt') . '</div>';
        echo '</div></div>';
        
        // WPBakery Status
        echo '<div class="retexify-status-item">';
        echo '<div class="retexify-status-icon">' . ($wpbakery_detected ? '✅' : '❌') . '</div>';
        echo '<div class="retexify-status-content">';
        echo '<div class="retexify-status-title">WPBakery</div>';
        if ($wpbakery_plugin) {
            echo '<div class="retexify-status-detail">Plugin aktiv</div>';
        } elseif ($wpbakery_functions) {
            echo '<div class="retexify-status-detail">Theme-integriert</div>';
        } else {
            echo '<div class="retexify-status-detail">Nicht verfügbar</div>';
        }
        echo '</div></div>';
        
        // SEO Plugin Status
        echo '<div class="retexify-status-item">';
        echo '<div class="retexify-status-icon">' . ($yoast_active || $rankmath_active || $aioseo_active ? '✅' : '❌') . '</div>';
        echo '<div class="retexify-status-content">';
        echo '<div class="retexify-status-title">SEO Plugin</div>';
        if ($yoast_active) {
            echo '<div class="retexify-status-detail">Yoast SEO</div>';
        } elseif ($rankmath_active) {
            echo '<div class="retexify-status-detail">Rank Math</div>';
        } elseif ($aioseo_active) {
            echo '<div class="retexify-status-detail">All in One SEO</div>';
        } else {
            echo '<div class="retexify-status-detail">Keines aktiv</div>';
        }
        echo '</div></div>';
        
        // Content Status
        echo '<div class="retexify-status-item">';
        echo '<div class="retexify-status-icon">📊</div>';
        echo '<div class="retexify-status-content">';
        echo '<div class="retexify-status-title">Content</div>';
        echo '<div class="retexify-status-detail">' . $total_posts . ' Posts/Seiten</div>';
        echo '</div></div>';
        
        echo '</div>';
        
        // WPBakery Details
        if ($wpbakery_detected) {
            echo '<div class="retexify-wpbakery-details">';
            echo '<h4>🏗️ WPBakery Details:</h4>';
            echo '<div class="retexify-wpbakery-grid">';
            echo '<span><strong>Posts mit VC-Shortcodes:</strong> ' . $posts_with_vc . '</span>';
            if ($wpbakery_constant) {
                echo '<span><strong>Version:</strong> ' . WPB_VC_VERSION . '</span>';
            }
            echo '<span><strong>Functions verfügbar:</strong> ' . ($wpbakery_functions ? 'Ja' : 'Nein') . '</span>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    // WPBakery Status Check für JavaScript
    public function check_wpbakery_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        $is_salient = (get_template() === 'salient' || get_stylesheet() === 'salient');
        $wpbakery_plugin = is_plugin_active('js_composer/js_composer.php');
        $wpbakery_functions = function_exists('vc_map');
        $wpbakery_constant = defined('WPB_VC_VERSION');
        
        $wpbakery_detected = $wpbakery_plugin || $wpbakery_functions || $wpbakery_constant;
        
        wp_send_json_success(array(
            'wpbakery_detected' => $wpbakery_detected,
            'method' => $wpbakery_plugin ? 'plugin' : ($wpbakery_functions ? 'theme' : 'none')
        ));
    }
    
    // Debug Export
    public function debug_export() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        try {
            global $wpdb;
            
            $debug_info = array();
            
            // WordPress Basis-Info
            $debug_info[] = '=== WORDPRESS INFO ===';
            $debug_info[] = 'WordPress Version: ' . get_bloginfo('version');
            $debug_info[] = 'PHP Version: ' . phpversion();
            $debug_info[] = 'Active Theme: ' . get_template() . ' (Child: ' . get_stylesheet() . ')';
            
            // Plugin/Theme WPBakery Detection
            $debug_info[] = '';
            $debug_info[] = '=== WPBAKERY DETECTION ===';
            
            // Standard Plugin Check
            $wpbakery_plugin = is_plugin_active('js_composer/js_composer.php');
            $debug_info[] = 'WPBakery Plugin: ' . ($wpbakery_plugin ? 'AKTIV' : 'NICHT AKTIV');
            
            // Salient Theme Check
            $is_salient = (get_template() === 'salient' || get_stylesheet() === 'salient');
            $debug_info[] = 'Salient Theme: ' . ($is_salient ? 'ERKANNT' : 'NICHT ERKANNT');
            
            // Function Checks
            $debug_info[] = 'vc_map function: ' . (function_exists('vc_map') ? 'VERFÜGBAR' : 'NICHT VERFÜGBAR');
            $debug_info[] = 'WPBakery constant: ' . (defined('WPB_VC_VERSION') ? 'DEFINIERT (' . WPB_VC_VERSION . ')' : 'NICHT DEFINIERT');
            
            // Content Analysis
            $debug_info[] = '';
            $debug_info[] = '=== CONTENT ANALYSIS ===';
            
            $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'");
            $debug_info[] = 'Total Posts/Pages: ' . $total_posts;
            
            $posts_with_vc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%[vc_%' AND post_status = 'publish'");
            $debug_info[] = 'Posts mit [vc_ Shortcodes: ' . $posts_with_vc;
            
            $posts_with_nectar = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%[nectar_%' AND post_status = 'publish'");
            $debug_info[] = 'Posts mit [nectar_ Shortcodes: ' . $posts_with_nectar;
            
            // Sample Content
            $sample_post = $wpdb->get_row("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE (post_content LIKE '%[vc_%' OR post_content LIKE '%[nectar_%') AND post_status = 'publish' ORDER BY post_modified DESC LIMIT 1");
            
            if ($sample_post) {
                $debug_info[] = '';
                $debug_info[] = '=== SAMPLE POST ===';
                $debug_info[] = 'ID: ' . $sample_post->ID;
                $debug_info[] = 'Title: ' . $sample_post->post_title;
                $debug_info[] = 'Content (first 500 chars): ' . substr($sample_post->post_content, 0, 500) . '...';
                
                // WPBakery Text Extraction Test
                $extracted_text = $this->extract_wpbakery_text_enhanced($sample_post->post_content);
                $debug_info[] = 'Extracted WPBakery Text: ' . ($extracted_text ? substr($extracted_text, 0, 200) . '...' : 'NOTHING EXTRACTED');
            } else {
                $debug_info[] = 'No WPBakery content found';
            }
            
            // SEO Plugin Detection
            $debug_info[] = '';
            $debug_info[] = '=== SEO PLUGINS ===';
            $debug_info[] = 'Yoast SEO: ' . (is_plugin_active('wordpress-seo/wp-seo.php') ? 'AKTIV' : 'NICHT AKTIV');
            $debug_info[] = 'Rank Math: ' . (is_plugin_active('seo-by-rank-math/rank-math.php') ? 'AKTIV' : 'NICHT AKTIV');
            $debug_info[] = 'All in One SEO: ' . (is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') ? 'AKTIV' : 'NICHT AKTIV');
            
            // Meta Data Count
            $meta_titles = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_title', 'rank_math_title', '_aioseop_title') AND meta_value != ''");
            $debug_info[] = 'Posts mit Meta-Titeln: ' . $meta_titles;
            
            // CSV STRUCTURE VALIDATION
            $debug_info[] = '';
            $debug_info[] = '=== CSV STRUCTURE TEST (ReTexify v2.4.0) ===';
            
            // Test der Datensammlung
            $test_selections = array(
                'post_types' => array('post'),
                'post_status' => array('publish'),
                'content_types' => array('title', 'content', 'wpbakery_text', 'wpbakery_meta_title', 'wpbakery_meta_content')
            );
            
            $test_data = $this->collect_enhanced_export_data_fixed($test_selections);
            $debug_info[] = 'Test Data Items: ' . count($test_data);
            
            if (!empty($test_data)) {
                $first_item = $test_data[0];
                $debug_info[] = 'First Item ID: ' . (isset($first_item['id']) ? $first_item['id'] : 'MISSING');
                $debug_info[] = 'First Item Type: ' . (isset($first_item['type']) ? $first_item['type'] : 'MISSING');
                
                // Content-Bereinigung testen
                if (isset($first_item['content'])) {
                    $content_has_shortcodes = (strpos($first_item['content'], '[vc_') !== false || strpos($first_item['content'], '[nectar_') !== false);
                    $debug_info[] = 'Content has WPBakery Shortcodes: ' . ($content_has_shortcodes ? 'YES - PROBLEM!' : 'NO - CLEAN!');
                }
                
                if (isset($first_item['wpbakery_text'])) {
                    $wpbakery_text_extracted = !empty($first_item['wpbakery_text']);
                    $debug_info[] = 'WPBakery Text Extracted: ' . ($wpbakery_text_extracted ? 'YES - SUCCESS!' : 'NO - No WPBakery content');
                }
                
                if (isset($first_item['wpbakery_meta_title'])) {
                    $wpbakery_meta_title_extracted = !empty($first_item['wpbakery_meta_title']);
                    $debug_info[] = 'WPBakery Meta-Titel Extracted: ' . ($wpbakery_meta_title_extracted ? 'YES - SUCCESS!' : 'NO - No Meta-Titel');
                }
                
                if (isset($first_item['wpbakery_meta_content'])) {
                    $wpbakery_meta_content_extracted = !empty($first_item['wpbakery_meta_content']);
                    $debug_info[] = 'WPBakery Meta-Content Extracted: ' . ($wpbakery_meta_content_extracted ? 'YES - SUCCESS!' : 'NO - No Meta-Content');
                }
            }
            
            $result_html = '<div style="background: #f0f6fc; padding: 15px; border-radius: 6px; border: 1px solid #c3dcf0; max-height: 400px; overflow-y: auto;">';
            $result_html .= '<h4 style="margin: 0 0 10px 0;">🔍 Debug-Export-Information (ULTRA-FIXED):</h4>';
            $result_html .= '<pre style="font-family: monospace; font-size: 12px; line-height: 1.4; margin: 0; white-space: pre-wrap;">';
            $result_html .= implode("\n", $debug_info);
            $result_html .= '</pre>';
            $result_html .= '</div>';
            
            wp_send_json_success($result_html);
            
        } catch (Exception $e) {
            wp_send_json_error('Debug-Fehler: ' . $e->getMessage());
        }
    }
    
    // REPARIERTE SEO-Score-Berechnung
    private function calculate_realistic_seo_score($stats) {
        $total_posts = max($stats['total_posts'], 1);
        
        // Verhältnisse berechnen (0-1)
        $meta_title_ratio = min(1.0, $stats['posts_with_meta_titles'] / $total_posts);
        $meta_desc_ratio = min(1.0, $stats['posts_with_meta_descriptions'] / $total_posts);
        $keyphrase_ratio = min(1.0, $stats['posts_with_focus_keyphrases'] / $total_posts);
        
        $alt_ratio = 0;
        if ($stats['total_images'] > 0) {
            $alt_ratio = min(1.0, $stats['images_with_alt'] / $stats['total_images']);
        } else {
            $alt_ratio = 1.0; // Volle Punkte wenn keine Bilder
        }
        
        $content_ratio = 0;
        if ($stats['avg_content_length'] > 500) {
            $content_ratio = 1.0;
        } elseif ($stats['avg_content_length'] > 300) {
            $content_ratio = 0.8;
        } elseif ($stats['avg_content_length'] > 150) {
            $content_ratio = 0.6;
        } elseif ($stats['avg_content_length'] > 50) {
            $content_ratio = 0.3;
        }
        
        // Gewichtete Berechnung (Gesamt: 100 Punkte)
        $score = 0;
        $score += $meta_title_ratio * 25;      // 25%
        $score += $meta_desc_ratio * 25;       // 25%  
        $score += $keyphrase_ratio * 20;       // 20%
        $score += $alt_ratio * 15;             // 15%
        $score += $content_ratio * 15;         // 15%
        
        // Score NIEMALS über 100
        return min(100, max(0, round($score)));
    }
    
    // AJAX-Handler für erweiterte Statistiken
    public function get_enhanced_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        try {
            global $wpdb;
            
            // Erweiterte Statistiken
            $stats = array();
            $stats['total_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'");
            $stats['posts_with_titles'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish' AND post_title != ''");
            
            // SEO-Daten aus allen Plugins
            $stats['posts_with_meta_titles'] = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_title', 'rank_math_title', '_aioseop_title') AND meta_value != ''");
            $stats['posts_with_meta_descriptions'] = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_metadesc', 'rank_math_description', '_aioseop_description') AND meta_value != ''");
            $stats['posts_with_focus_keyphrases'] = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_focuskw', 'rank_math_focus_keyword') AND meta_value != ''");
            
            // WPBakery/Salient erweiterte Erkennung
            $stats['posts_with_wpbakery'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE (post_content LIKE '%[vc_%' OR post_content LIKE '%[nectar_%') 
                AND post_status = 'publish'
            ");
            
            // Bilder
            $stats['total_images'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image%'");
            $stats['images_with_alt'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_image_alt' AND meta_value != ''");
            
            // Content-Länge
            $avg_length = $wpdb->get_var("SELECT AVG(LENGTH(post_content)) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'");
            $stats['avg_content_length'] = $avg_length ?: 0;
            
            // SEO-Score-Berechnung
            $seo_score = $this->calculate_realistic_seo_score($stats);
            
            // Theme/Plugin Detection
            $is_salient = (get_template() === 'salient' || get_stylesheet() === 'salient');
            $wpbakery_method = '';
            if (is_plugin_active('js_composer/js_composer.php')) {
                $wpbakery_method = 'Plugin';
            } elseif ($is_salient || function_exists('vc_map')) {
                $wpbakery_method = 'Theme-integriert';
            } else {
                $wpbakery_method = 'Nicht erkannt';
            }
            
            // Dashboard HTML
            $dashboard_html = '<div class="retexify-enhanced-dashboard">';
            
            // SEO Score mit korrekter Farbe
            $score_color = '#10b981'; // Grün
            if ($seo_score < 60) $score_color = '#ef4444'; // Rot
            elseif ($seo_score < 80) $score_color = '#f59e0b'; // Orange
            
            $dashboard_html .= '<div class="retexify-seo-score-container">';
            $dashboard_html .= '<div class="retexify-seo-score-circle" style="background: conic-gradient(' . $score_color . ' ' . ($seo_score * 3.6) . 'deg, #e5e7eb 0deg);">';
            $dashboard_html .= '<div class="retexify-seo-score-inner">';
            $dashboard_html .= '<span class="retexify-seo-score-number">' . $seo_score . '</span>';
            $dashboard_html .= '<span class="retexify-seo-score-label">SEO Score</span>';
            $dashboard_html .= '</div></div></div>';
            
            // Statistik-Grid
            $dashboard_html .= '<div class="retexify-stats-grid-enhanced">';
            
            // Posts
            $dashboard_html .= '<div class="retexify-stat-card">';
            $dashboard_html .= '<div class="retexify-stat-icon">📝</div>';
            $dashboard_html .= '<div class="retexify-stat-content">';
            $dashboard_html .= '<div class="retexify-stat-number">' . $stats['total_posts'] . '</div>';
            $dashboard_html .= '<div class="retexify-stat-label">Posts/Seiten</div>';
            $dashboard_html .= '<div class="retexify-stat-detail">Veröffentlicht</div>';
            $dashboard_html .= '</div></div>';
            
            // Meta-Titel
            $meta_title_percent = $stats['total_posts'] > 0 ? min(100, round(($stats['posts_with_meta_titles'] / $stats['total_posts']) * 100)) : 0;
            $dashboard_html .= '<div class="retexify-stat-card">';
            $dashboard_html .= '<div class="retexify-stat-icon">🎯</div>';
            $dashboard_html .= '<div class="retexify-stat-content">';
            $dashboard_html .= '<div class="retexify-stat-number">' . $stats['posts_with_meta_titles'] . '</div>';
            $dashboard_html .= '<div class="retexify-stat-label">Meta-Titel</div>';
            $dashboard_html .= '<div class="retexify-stat-detail">' . $meta_title_percent . '% Abdeckung</div>';
            $dashboard_html .= '</div></div>';
            
            // Meta-Beschreibungen
            $meta_desc_percent = $stats['total_posts'] > 0 ? min(100, round(($stats['posts_with_meta_descriptions'] / $stats['total_posts']) * 100)) : 0;
            $dashboard_html .= '<div class="retexify-stat-card">';
            $dashboard_html .= '<div class="retexify-stat-icon">📊</div>';
            $dashboard_html .= '<div class="retexify-stat-content">';
            $dashboard_html .= '<div class="retexify-stat-number">' . $stats['posts_with_meta_descriptions'] . '</div>';
            $dashboard_html .= '<div class="retexify-stat-label">Meta-Beschreibungen</div>';
            $dashboard_html .= '<div class="retexify-stat-detail">' . $meta_desc_percent . '% Abdeckung</div>';
            $dashboard_html .= '</div></div>';
            
            // Focus Keyphrases
            $keyphrase_percent = $stats['total_posts'] > 0 ? min(100, round(($stats['posts_with_focus_keyphrases'] / $stats['total_posts']) * 100)) : 0;
            $dashboard_html .= '<div class="retexify-stat-card">';
            $dashboard_html .= '<div class="retexify-stat-icon">🔑</div>';
            $dashboard_html .= '<div class="retexify-stat-content">';
            $dashboard_html .= '<div class="retexify-stat-number">' . $stats['posts_with_focus_keyphrases'] . '</div>';
            $dashboard_html .= '<div class="retexify-stat-label">Focus Keyphrases</div>';
            $dashboard_html .= '<div class="retexify-stat-detail">' . $keyphrase_percent . '% Abdeckung</div>';
            $dashboard_html .= '</div></div>';
            
            // WPBakery
            $dashboard_html .= '<div class="retexify-stat-card">';
            $dashboard_html .= '<div class="retexify-stat-icon">🏗️</div>';
            $dashboard_html .= '<div class="retexify-stat-content">';
            $dashboard_html .= '<div class="retexify-stat-number">' . $stats['posts_with_wpbakery'] . '</div>';
            $dashboard_html .= '<div class="retexify-stat-label">WPBakery Posts</div>';
            $dashboard_html .= '<div class="retexify-stat-detail">' . $wpbakery_method . '</div>';
            $dashboard_html .= '</div></div>';
            
            // Bilder
            $alt_percent = $stats['total_images'] > 0 ? min(100, round(($stats['images_with_alt'] / $stats['total_images']) * 100)) : 0;
            $dashboard_html .= '<div class="retexify-stat-card">';
            $dashboard_html .= '<div class="retexify-stat-icon">🖼️</div>';
            $dashboard_html .= '<div class="retexify-stat-content">';
            $dashboard_html .= '<div class="retexify-stat-number">' . $stats['images_with_alt'] . '/' . $stats['total_images'] . '</div>';
            $dashboard_html .= '<div class="retexify-stat-label">Bilder mit Alt-Text</div>';
            $dashboard_html .= '<div class="retexify-stat-detail">' . $alt_percent . '% Abdeckung</div>';
            $dashboard_html .= '</div></div>';
            
            $dashboard_html .= '</div>';
            
            // System-Status
            $dashboard_html .= '<div class="retexify-system-info">';
            $dashboard_html .= '<h4>🖥️ System-Info:</h4>';
            $dashboard_html .= '<div class="retexify-system-grid">';
            $dashboard_html .= '<span><strong>Theme:</strong> ' . get_template() . ($is_salient ? ' (Salient erkannt)' : '') . '</span>';
            $dashboard_html .= '<span><strong>WPBakery:</strong> ' . $wpbakery_method . '</span>';
            $dashboard_html .= '<span><strong>WordPress:</strong> ' . get_bloginfo('version') . '</span>';
            $dashboard_html .= '<span><strong>PHP:</strong> ' . phpversion() . '</span>';
            $dashboard_html .= '</div></div>';
            
            $dashboard_html .= '</div>';
            
            wp_send_json_success($dashboard_html);
            
        } catch (Exception $e) {
            wp_send_json_error('Statistik-Fehler: ' . $e->getMessage());
        }
    }
    
    // AJAX-Handler für Live Content-Counts
    public function get_content_counts() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        try {
            global $wpdb;
            
            $counts = array();
            
            // Post-Typ-Counts
            $post_types = get_post_types(array('public' => true), 'names');
            $counts['post_types'] = array();
            foreach ($post_types as $post_type) {
                $counts['post_types'][$post_type] = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$wpdb->posts} 
                    WHERE post_type = %s AND post_status = 'publish'
                ", $post_type));
            }
            
            // Status-Counts
            $counts['status'] = array();
            $counts['status']['publish'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'");
            $counts['status']['draft'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'draft'");
            $counts['status']['private'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'private'");
            
            // Content-Typ-Counts
            $counts['content'] = array();
            $counts['content']['title'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish' AND post_title != ''");
            $counts['content']['content'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish' AND post_content != ''");
            $counts['content']['meta_title'] = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_title', 'rank_math_title', '_aioseop_title') AND meta_value != ''");
            $counts['content']['meta_description'] = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_metadesc', 'rank_math_description', '_aioseop_description') AND meta_value != ''");
            $counts['content']['focus_keyphrase'] = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_focuskw', 'rank_math_focus_keyword') AND meta_value != ''");
            
            // WPBakery/Salient erweiterte Erkennung
            $counts['content']['wpbakery_text'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE (post_content LIKE '%[vc_%' OR post_content LIKE '%[nectar_%') 
                AND post_status = 'publish'
            ");
            
            // WPBakery Meta-Titel (Custom Headings, CTAs, etc.)
            $counts['content']['wpbakery_meta_title'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE (post_content LIKE '%vc_custom_heading%' OR post_content LIKE '%nectar_cta%' OR post_content LIKE '%vc_text_separator%') 
                AND post_status = 'publish'
            ");
            
            // WPBakery Meta-Content (CTA Content, Message Boxes, etc.)
            $counts['content']['wpbakery_meta_content'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE (post_content LIKE '%[vc_cta %' OR post_content LIKE '%[vc_message%' OR post_content LIKE '%[nectar_quote%') 
                AND post_status = 'publish'
            ");
            
            // Bilder
            $counts['content']['alt_texts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image%'");
            
            wp_send_json_success($counts);
            
        } catch (Exception $e) {
            wp_send_json_error('Count-Fehler: ' . $e->getMessage());
        }
    }
    
    // Erweiterte WPBakery-Text-Extraktion für Salient (VERBESSERT - HTML-Bereinigung)
    private function extract_wpbakery_text_enhanced($content) {
        if (empty($content)) {
            return '';
        }
        
        $extracted_texts = array();
        
        // Standard VC Elements für normalen Text
        $patterns = array(
            // VC Column Text
            '/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/s',
            // Nectar Text Elements (Salient spezifisch)
            '/\[nectar_highlighted_text[^>]*highlight_color="[^"]*"[^>]*\]([^[]*)\[\/nectar_highlighted_text\]/s',
            // Standard Button Text
            '/\[vc_btn[^>]*title="([^"]*)"[^\]]*\]/s',
            '/\[nectar_btn[^>]*text="([^"]*)"[^\]]*\]/s'
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $text = isset($match[1]) ? $match[1] : '';
                if (empty($text) && isset($match[2])) {
                    $text = $match[2];
                }
                
                // Text-Bereinigung (ohne WPBakery-Shortcode-Entfernung)
                $clean_text = wp_strip_all_tags($text);
                $clean_text = html_entity_decode($clean_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $clean_text = preg_replace('/\s+/', ' ', $clean_text);
                $clean_text = trim($clean_text);
                
                if (!empty($clean_text) && strlen($clean_text) > 3) {
                    $extracted_texts[] = $clean_text;
                }
            }
        }
        
        return implode(' | ', array_unique($extracted_texts));
    }
    
    // NEUE Funktion: WPBakery Meta-Titel extrahieren
    private function extract_wpbakery_meta_title($content) {
        if (empty($content)) {
            return '';
        }
        
        $extracted_titles = array();
        
        // Meta-Titel Patterns für WPBakery/Salient
        $patterns = array(
            // VC Custom Heading
            '/\[vc_custom_heading[^>]*text="([^"]*)"[^\]]*\]/s',
            // VC Text Separator
            '/\[vc_text_separator[^>]*title="([^"]*)"[^\]]*\]/s',
            // Nectar CTA Heading
            '/\[nectar_cta[^>]*heading="([^"]*)"[^\]]*\]/s',
            // Nectar Page Header
            '/\[nectar_page_header[^>]*title="([^"]*)"[^\]]*\]/s',
            // Standard Heading Shortcodes
            '/\[heading[^>]*title="([^"]*)"[^\]]*\]/s'
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $title = isset($match[1]) ? trim($match[1]) : '';
                
                if (!empty($title) && strlen($title) > 2) {
                    // HTML-Entitäten dekodieren
                    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $extracted_titles[] = $title;
                }
            }
        }
        
        return implode(' | ', array_unique($extracted_titles));
    }
    
    // NEUE Funktion: WPBakery Meta-Content extrahieren  
    private function extract_wpbakery_meta_content($content) {
        if (empty($content)) {
            return '';
        }
        
        $extracted_content = array();
        
        // Meta-Content Patterns für WPBakery/Salient
        $patterns = array(
            // CTA Content/Description
            '/\[vc_cta[^>]*h2="[^"]*"[^>]*\](.*?)\[\/vc_cta\]/s',
            '/\[nectar_cta[^>]*heading="[^"]*"[^>]*\](.*?)\[\/nectar_cta\]/s',
            // Message Box Content
            '/\[vc_message[^>]*\](.*?)\[\/vc_message\]/s',
            // Custom Box Content
            '/\[vc_custom_box[^>]*\](.*?)\[\/vc_custom_box\]/s',
            // Nectar Quote
            '/\[nectar_quote[^>]*\](.*?)\[\/nectar_quote\]/s',
            // Icon Box Content
            '/\[vc_icon_box[^>]*\](.*?)\[\/vc_icon_box\]/s'
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $text = isset($match[1]) ? $match[1] : '';
                
                // Nested Shortcodes entfernen und Text bereinigen
                $text = wp_strip_all_tags($text);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = preg_replace('/\s+/', ' ', $text);
                $text = trim($text);
                
                if (!empty($text) && strlen($text) > 5) {
                    $extracted_content[] = $text;
                }
            }
        }
        
        return implode(' | ', array_unique($extracted_content));
    }
    
    // Test-Handler
    public function test_all_fields() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        global $wpdb;
        
        try {
            $result_html = '<div style="background: #f0f6fc; padding: 15px; border-radius: 6px; border: 1px solid #c3dcf0;">';
            $result_html .= '<h4 style="margin: 0 0 10px 0;">🧪 ReTexify System-Test:</h4>';
            
            // WordPress & PHP
            $result_html .= '<p><strong>WordPress:</strong> ' . get_bloginfo('version') . '</p>';
            $result_html .= '<p><strong>PHP:</strong> ' . phpversion() . '</p>';
            
            // Theme Detection
            $theme = get_template();
            $child_theme = get_stylesheet();
            $is_salient = ($theme === 'salient' || $child_theme === 'salient');
            $result_html .= '<p><strong>Active Theme:</strong> ' . $theme . ($child_theme !== $theme ? ' (Child: ' . $child_theme . ')' : '') . '</p>';
            $result_html .= '<p><strong>Salient Detected:</strong> ' . ($is_salient ? '✅ JA' : '❌ NEIN') . '</p>';
            
            // WPBakery Detection
            $wpbakery_plugin = is_plugin_active('js_composer/js_composer.php');
            $wpbakery_functions = function_exists('vc_map');
            $wpbakery_constant = defined('WPB_VC_VERSION');
            
            $result_html .= '<p><strong>WPBakery Plugin:</strong> ' . ($wpbakery_plugin ? '✅ Aktiv' : '❌ Nicht aktiv') . '</p>';
            $result_html .= '<p><strong>WPBakery Functions:</strong> ' . ($wpbakery_functions ? '✅ Verfügbar' : '❌ Nicht verfügbar') . '</p>';
            $result_html .= '<p><strong>WPBakery Constants:</strong> ' . ($wpbakery_constant ? '✅ Definiert' : '❌ Nicht definiert') . '</p>';
            
            // Content Analysis
            $posts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'");
            $posts_with_vc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%[vc_%' AND post_status = 'publish'");
            $posts_with_nectar = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%[nectar_%' AND post_status = 'publish'");
            
            $result_html .= '<p><strong>Posts/Seiten:</strong> ' . $posts_count . '</p>';
            $result_html .= '<p><strong>Posts mit VC-Shortcodes:</strong> ' . $posts_with_vc . '</p>';
            $result_html .= '<p><strong>Posts mit Nectar-Shortcodes:</strong> ' . $posts_with_nectar . '</p>';
            
            // Sample Content Test
            $sample_post = $wpdb->get_row("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE (post_content LIKE '%[vc_%' OR post_content LIKE '%[nectar_%') AND post_status = 'publish' ORDER BY post_modified DESC LIMIT 1");
            
            if ($sample_post) {
                $extracted_text = $this->extract_wpbakery_text_enhanced($sample_post->post_content);
                $result_html .= '<p><strong>WPBakery Text Extraction Test:</strong></p>';
                $result_html .= '<div style="background: #fff; padding: 10px; border-radius: 4px; margin: 10px 0;">';
                $result_html .= '<small>Post: "' . esc_html($sample_post->post_title) . '" (ID: ' . $sample_post->ID . ')</small><br>';
                $result_html .= '<strong>Extrahierter Text:</strong> ' . ($extracted_text ? esc_html(substr($extracted_text, 0, 200)) . '...' : '<em>Kein Text extrahiert</em>');
                $result_html .= '</div>';
            }
            
            // Upload-Verzeichnis
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/retexify-temp/';
            $writable = wp_mkdir_p($temp_dir) && is_writable($temp_dir);
            $result_html .= '<p><strong>Upload-Verzeichnis:</strong> ' . ($writable ? '✅ Beschreibbar' : '❌ Nicht beschreibbar') . '</p>';
            
            // CSV-Struktur-Test
            $result_html .= '<p><strong>CSV-Struktur-Test (ReTexify v2.4.0):</strong></p>';
            $test_selections = array(
                'post_types' => array('post'),
                'post_status' => array('publish'),
                'content_types' => array('title', 'content', 'wpbakery_meta_title')
            );
            
            $test_data = $this->collect_enhanced_export_data_fixed($test_selections);
            $result_html .= '<div style="background: #fff; padding: 10px; border-radius: 4px; margin: 10px 0;">';
            $result_html .= '<small>Test-Export mit ' . count($test_data) . ' Elementen erstellt</small><br>';
            if (!empty($test_data)) {
                $first_item = $test_data[0];
                $result_html .= '<strong>Erste Zeile ID:</strong> ' . (isset($first_item['id']) && is_numeric($first_item['id']) ? '✅ ' . $first_item['id'] . ' (numerisch)' : '❌ Nicht numerisch oder fehlt') . '<br>';
                
                // Content-Bereinigung prüfen
                if (isset($first_item['content'])) {
                    $content_has_shortcodes = (strpos($first_item['content'], '[vc_') !== false || strpos($first_item['content'], '[nectar_') !== false);
                    $result_html .= '<strong>Content bereinigt:</strong> ' . ($content_has_shortcodes ? '❌ Enthält noch Shortcodes' : '✅ Sauber, keine Shortcodes') . '<br>';
                }
                
                // WPBakery Meta-Titel prüfen
                if (isset($first_item['wpbakery_meta_title'])) {
                    $has_meta_title = !empty($first_item['wpbakery_meta_title']);
                    $result_html .= '<strong>WPBakery Meta-Titel:</strong> ' . ($has_meta_title ? '✅ Extrahiert: ' . substr($first_item['wpbakery_meta_title'], 0, 50) . '...' : '⚪ Kein Meta-Titel gefunden');
                }
            }
            $result_html .= '</div>';
            
            // Status
            if ($is_salient || $wpbakery_plugin || $wpbakery_functions) {
                $result_html .= '<p style="color: green; font-weight: bold;">✅ WPBakery/Salient System erkannt und bereit!</p>';
            } else {
                $result_html .= '<p style="color: orange; font-weight: bold;">⚠️ WPBakery nicht erkannt - Standard-Export verfügbar</p>';
            }
            
            $result_html .= '</div>';
            
            wp_send_json_success($result_html);
            
        } catch (Exception $e) {
            wp_send_json_error('Test-Fehler: ' . $e->getMessage());
        }
    }
    
    // Export-Vorschau
    public function preview_export() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        try {
            $selections = json_decode(stripslashes($_POST['selections']), true);
            
            if (!$selections || empty($selections['post_types']) || empty($selections['content_types'])) {
                wp_send_json_error('Keine gültige Auswahl');
            }
            
            // Standard-Status setzen falls leer
            if (empty($selections['post_status'])) {
                $selections['post_status'] = array('publish');
            }
            
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
            if (in_array('alt_texts', $selections['content_types'])) {
                global $wpdb;
                $images_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image%'");
            }
            
            // WPBakery Posts zählen
            $wpbakery_count = 0;
            if (in_array('wpbakery_text', $selections['content_types'])) {
                global $wpdb;
                $wpbakery_count = $wpdb->get_var("
                    SELECT COUNT(*) FROM {$wpdb->posts} 
                    WHERE (post_content LIKE '%[vc_%' OR post_content LIKE '%[nectar_%')
                    AND post_type IN ('" . implode("','", array_map('esc_sql', $selections['post_types'])) . "')
                    AND post_status IN ('" . implode("','", array_map('esc_sql', $selections['post_status'])) . "')
                ");
            }
            
            $total_items = $posts_count + $images_count;
            
            $preview_html = '<div style="background: #e7f3ff; padding: 12px; border-radius: 4px; border: 1px solid #b8daff; margin-top: 10px;">';
            $preview_html .= '<h4 style="margin: 0 0 8px 0; color: #004085;">📋 Export-Vorschau:</h4>';
            $preview_html .= '<ul style="margin: 0; color: #004085; font-size: 14px;">';
            $preview_html .= '<li><strong>' . $posts_count . '</strong> Posts/Seiten (Content bereinigt)</li>';
            if ($images_count > 0) {
                $preview_html .= '<li><strong>' . $images_count . '</strong> Bilder mit Alt-Texten</li>';
            }
            if ($wpbakery_count > 0) {
                $preview_html .= '<li><strong>' . $wpbakery_count . '</strong> WPBakery-Inhalte (separate Spalten)</li>';
            }
            $preview_html .= '</ul>';
            $preview_html .= '<p style="margin: 8px 0 0 0; font-weight: bold;">CSV mit WPBakery Meta-Feldern: ' . $total_items . ' Einträge</p>';
            $preview_html .= '</div>';
            
            wp_send_json_success($preview_html);
            
        } catch (Exception $e) {
            wp_send_json_error('Vorschau-Fehler: ' . $e->getMessage());
        }
    }
    
    // Export-Handler - MAIN FUNCTION
    public function handle_export() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        try {
            $selections = json_decode(stripslashes($_POST['selections']), true);
            
            if (!$selections || empty($selections['post_types']) || empty($selections['content_types'])) {
                wp_send_json_error('Keine gültige Auswahl getroffen');
            }
            
            // Standard-Status setzen falls leer
            if (empty($selections['post_status'])) {
                $selections['post_status'] = array('publish');
            }
            
            // FIXED: Verwende die reparierte Datensammlung
            $all_data = $this->collect_enhanced_export_data_fixed($selections);
            
            if (empty($all_data)) {
                wp_send_json_error('Keine Daten zum Exportieren gefunden');
            }
            
            // FIXED: Verwende die reparierte CSV-Erstellung
            $csv_data = $this->create_enhanced_csv_data_fixed($all_data, $selections);
            
            // Datei speichern
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/retexify-temp/';
            wp_mkdir_p($temp_dir);
            
            $filename = 'retexify-export-' . date('Y-m-d-H-i-s') . '.csv';
            $file_path = $temp_dir . $filename;
            
            $file = fopen($file_path, 'w');
            if (!$file) {
                wp_send_json_error('CSV-Datei konnte nicht erstellt werden');
            }
            
            // UTF-8 BOM für Excel
            fwrite($file, "\xEF\xBB\xBF");
            
            // CSV schreiben
            foreach ($csv_data as $row) {
                fputcsv($file, $row, ';');
            }
            fclose($file);
            
            $download_url = admin_url('tools.php?page=retexify&action=download&file=' . $filename . '&nonce=' . wp_create_nonce('download_file'));
            
            $posts_exported = count(array_filter($all_data, function($item) { return $item['type'] !== 'image'; }));
            $images_exported = count(array_filter($all_data, function($item) { return $item['type'] === 'image'; }));
            
            wp_send_json_success(array(
                'message' => 'Export erfolgreich erstellt!',
                'download_url' => $download_url,
                'filename' => $filename,
                'posts_exported' => $posts_exported,
                'images_exported' => $images_exported,
                'total_items' => count($csv_data) - 1 // -1 für Header
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Export-Fehler: ' . $e->getMessage());
        }
    }
    
    // FIXED: Erweiterte Datensammlung für Salient - KORREKTE ID-BEHANDLUNG + CONTENT-BEREINIGUNG
    private function collect_enhanced_export_data_fixed($selections) {
        $all_data = array();
        
        // Posts sammeln mit verbesserter Logik
        $posts = get_posts(array(
            'post_type' => $selections['post_types'],
            'post_status' => $selections['post_status'],
            'numberposts' => -1,
            'suppress_filters' => false
        ));
        
        foreach ($posts as $post) {
            // FIXED: Klare Datenstruktur mit IMMER numerischer ID
            $post_data = array(
                'id' => intval($post->ID), // IMMER numerisch!
                'type' => $post->post_type,
                'url' => get_permalink($post->ID),
                'title' => '',
                'content' => '',
                'meta_title' => '',
                'meta_description' => '',
                'focus_keyphrase' => '',
                'wpbakery_text' => '',
                'wpbakery_meta_title' => '',
                'wpbakery_meta_content' => '',
                'image_id' => '', // Leer für Posts
                'alt_text' => '', // Leer für Posts
                'image_type' => '' // Leer für Posts
            );
            
            // Nur ausgewählte Content-Typen sammeln
            if (in_array('title', $selections['content_types'])) {
                $post_data['title'] = $this->clean_text($post->post_title);
            }
            
            // VERBESSERT: Content wird von WPBakery-Shortcodes bereinigt
            if (in_array('content', $selections['content_types'])) {
                // Zuerst WPBakery-Shortcodes entfernen, dann bereinigen
                $clean_content = $this->remove_wpbakery_shortcodes($post->post_content);
                $post_data['content'] = $this->clean_text($clean_content);
            }
            
            if (in_array('meta_title', $selections['content_types'])) {
                $post_data['meta_title'] = $this->clean_text($this->get_meta_title($post->ID));
            }
            
            if (in_array('meta_description', $selections['content_types'])) {
                $post_data['meta_description'] = $this->clean_text($this->get_meta_description($post->ID));
            }
            
            if (in_array('focus_keyphrase', $selections['content_types'])) {
                $post_data['focus_keyphrase'] = $this->clean_text($this->get_focus_keyphrase($post->ID));
            }
            
            // WPBakery-Text separat extrahieren (NICHT bereinigen!)
            if (in_array('wpbakery_text', $selections['content_types'])) {
                $post_data['wpbakery_text'] = $this->extract_wpbakery_text_enhanced($post->post_content);
            }
            
            // WPBakery Meta-Titel extrahieren
            if (in_array('wpbakery_meta_title', $selections['content_types'])) {
                $post_data['wpbakery_meta_title'] = $this->extract_wpbakery_meta_title($post->post_content);
            }
            
            // WPBakery Meta-Content extrahieren
            if (in_array('wpbakery_meta_content', $selections['content_types'])) {
                $post_data['wpbakery_meta_content'] = $this->extract_wpbakery_meta_content($post->post_content);
            }
            
            $all_data[] = $post_data;
        }
        
        // Bilder sammeln - ALLE Bilder wenn ausgewählt
        if (in_array('alt_texts', $selections['content_types'])) {
            global $wpdb;
            
            $images = $wpdb->get_results("
                SELECT p.ID, p.post_title, pm.meta_value as alt_text, p.post_parent
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                WHERE p.post_type = 'attachment' 
                AND p.post_mime_type LIKE 'image%'
                AND p.post_status = 'inherit'
                ORDER BY p.ID
                LIMIT 2000
            ");
            
            foreach ($images as $image) {
                // FIXED: Klare Bild-Datenstruktur
                $image_data = array(
                    'id' => '', // LEER für Bilder!
                    'type' => 'image',
                    'url' => wp_get_attachment_url($image->ID),
                    'title' => '', // Leer für Bilder
                    'content' => '', // Leer für Bilder
                    'meta_title' => '', // Leer für Bilder
                    'meta_description' => '', // Leer für Bilder
                    'focus_keyphrase' => '', // Leer für Bilder
                    'wpbakery_text' => '', // Leer für Bilder
                    'wpbakery_meta_title' => '', // Leer für Bilder
                    'wpbakery_meta_content' => '', // Leer für Bilder
                    'image_id' => intval($image->ID), // Numerisch!
                    'alt_text' => $this->clean_text($image->alt_text ?: ''),
                    'image_type' => $image->post_parent ? 'content_image' : 'media_library'
                );
                
                $all_data[] = $image_data;
            }
        }
        
        return $all_data;
    }
    
    // FIXED: CSV-Erstellung mit korrekter ID-Spalten-Behandlung
    private function create_enhanced_csv_data_fixed($all_data, $selections) {
        $csv_data = array();
        
        // Header erstellen - ALLE möglichen Spalten
        $header = array('ID', 'Typ', 'URL');
        
        if (in_array('title', $selections['content_types'])) {
            $header[] = 'Titel (Original)';
            $header[] = 'Titel (Neu)';
        }
        
        if (in_array('meta_title', $selections['content_types'])) {
            $header[] = 'Meta Titel (Original)';
            $header[] = 'Meta Titel (Neu)';
        }
        
        if (in_array('meta_description', $selections['content_types'])) {
            $header[] = 'Meta Beschreibung (Original)';
            $header[] = 'Meta Beschreibung (Neu)';
        }
        
        if (in_array('focus_keyphrase', $selections['content_types'])) {
            $header[] = 'Focus Keyphrase (Original)';
            $header[] = 'Focus Keyphrase (Neu)';
        }
        
        if (in_array('content', $selections['content_types'])) {
            $header[] = 'Content (Original)';
            $header[] = 'Content (Neu)';
        }
        
        if (in_array('wpbakery_text', $selections['content_types'])) {
            $header[] = 'WPBakery Text (Original)';
            $header[] = 'WPBakery Text (Neu)';
        }
        
        if (in_array('wpbakery_meta_title', $selections['content_types'])) {
            $header[] = 'WPBakery Meta-Titel (Original)';
            $header[] = 'WPBakery Meta-Titel (Neu)';
        }
        
        if (in_array('wpbakery_meta_content', $selections['content_types'])) {
            $header[] = 'WPBakery Meta-Content (Original)';
            $header[] = 'WPBakery Meta-Content (Neu)';
        }
        
        if (in_array('alt_texts', $selections['content_types'])) {
            $header[] = 'Image ID';
            $header[] = 'Alt Text (Original)';
            $header[] = 'Alt Text (Neu)';
            $header[] = 'Image Type';
        }
        
        $csv_data[] = $header;
        
        // Daten hinzufügen - NUR nicht-leere Einträge
        foreach ($all_data as $data) {
            // FIXED: ID-Spalte IMMER korrekt behandeln
            $row = array();
            
            // ID - NIEMALS Content, immer numerisch oder leer
            if ($data['type'] === 'image') {
                $row[] = ''; // Leer für Bilder
            } else {
                $row[] = isset($data['id']) && is_numeric($data['id']) ? intval($data['id']) : '';
            }
            
            // Typ und URL
            $row[] = isset($data['type']) ? $data['type'] : '';
            $row[] = isset($data['url']) ? $data['url'] : '';
            
            // Prüfen ob Zeile Inhalt hat (außer Basis-Felder)
            $has_content = false;
            
            if (in_array('title', $selections['content_types'])) {
                $title = isset($data['title']) ? $this->clean_text($data['title']) : '';
                $row[] = $title;
                $row[] = ''; // Neu-Spalte leer lassen
                if (!empty($title)) $has_content = true;
            }
            
            if (in_array('meta_title', $selections['content_types'])) {
                $meta_title = isset($data['meta_title']) ? $this->clean_text($data['meta_title']) : '';
                $row[] = $meta_title;
                $row[] = ''; // Neu-Spalte leer lassen
                if (!empty($meta_title)) $has_content = true;
            }
            
            if (in_array('meta_description', $selections['content_types'])) {
                $meta_desc = isset($data['meta_description']) ? $this->clean_text($data['meta_description']) : '';
                $row[] = $meta_desc;
                $row[] = ''; // Neu-Spalte leer lassen
                if (!empty($meta_desc)) $has_content = true;
            }
            
            if (in_array('focus_keyphrase', $selections['content_types'])) {
                $focus = isset($data['focus_keyphrase']) ? $this->clean_text($data['focus_keyphrase']) : '';
                $row[] = $focus;
                $row[] = ''; // Neu-Spalte leer lassen
                if (!empty($focus)) $has_content = true;
            }
            
            if (in_array('content', $selections['content_types'])) {
                $content = isset($data['content']) ? $this->clean_text($data['content']) : '';
                $row[] = $content;
                $row[] = ''; // Neu-Spalte leer lassen
                if (!empty($content)) $has_content = true;
            }
            
            if (in_array('wpbakery_text', $selections['content_types'])) {
                // WPBakery-Text NIEMALS in ID-Spalte!
                if (isset($data['wpbakery_text']) && !empty($data['wpbakery_text'])) {
                    $wpbakery = $this->clean_text($data['wpbakery_text']);
                    $row[] = $wpbakery; // Original
                    $row[] = ''; // Neu-Spalte leer lassen
                    if (!empty($wpbakery)) $has_content = true;
                } else {
                    $row[] = ''; // Original
                    $row[] = ''; // Neu
                }
            }
            
            if (in_array('alt_texts', $selections['content_types'])) {
                // Bild-spezifische Felder
                if ($data['type'] === 'image') {
                    $alt_text = isset($data['alt_text']) ? $this->clean_text($data['alt_text']) : '';
                    $row[] = isset($data['image_id']) && is_numeric($data['image_id']) ? intval($data['image_id']) : '';
                    $row[] = $alt_text;
                    $row[] = ''; // Neu-Spalte leer lassen
                    $row[] = isset($data['image_type']) ? $data['image_type'] : '';
                    if (!empty($alt_text) || !empty($data['image_id'])) $has_content = true;
                } else {
                    $row[] = ''; // Image ID
                    $row[] = ''; // Alt Text Original
                    $row[] = ''; // Alt Text Neu
                    $row[] = ''; // Image Type
                }
            }
            
            // NUR Zeilen mit Inhalt hinzufügen (oder Bilder)
            if ($has_content || $data['type'] === 'image') {
                $csv_data[] = $row;
            }
        }
        
        return $csv_data;
    }
    
    // VERBESSERTE Text-Bereinigung - entfernt auch WPBakery-Shortcodes
    private function clean_text($text) {
        if (empty($text)) {
            return '';
        }
        
        // WPBakery/VC Shortcodes KOMPLETT entfernen (für Content-Spalte)
        $text = $this->remove_wpbakery_shortcodes($text);
        
        // HTML-Tags entfernen
        $text = wp_strip_all_tags($text);
        
        // HTML-Entitäten dekodieren
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Mehrfache Leerzeichen und Zeilenumbrüche normalisieren
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim und zurückgeben
        return trim($text);
    }
    
    // NEUE Funktion: WPBakery-Shortcodes komplett entfernen
    private function remove_wpbakery_shortcodes($content) {
        if (empty($content)) {
            return '';
        }
        
        // Alle WPBakery/VC Shortcodes entfernen (auch verschachtelte)
        $patterns = array(
            // Standard VC Shortcodes
            '/\[vc_[^\]]*\].*?\[\/vc_[^\]]*\]/s',
            '/\[vc_[^\]]*\]/s',
            // Nectar/Salient Shortcodes
            '/\[nectar_[^\]]*\].*?\[\/nectar_[^\]]*\]/s',
            '/\[nectar_[^\]]*\]/s',
            // Visual Composer Row/Column Structure
            '/\[vc_row[^\]]*\].*?\[\/vc_row\]/s',
            '/\[vc_column[^\]]*\].*?\[\/vc_column\]/s',
            '/\[vc_section[^\]]*\].*?\[\/vc_section\]/s'
        );
        
        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }
        
        // Mehrfache Durchläufe für verschachtelte Shortcodes
        $max_iterations = 3;
        for ($i = 0; $i < $max_iterations; $i++) {
            $old_content = $content;
            foreach ($patterns as $pattern) {
                $content = preg_replace($pattern, '', $content);
            }
            // Wenn sich nichts mehr ändert, brechen wir ab
            if ($old_content === $content) {
                break;
            }
        }
        
        return $content;
    }
    
    // SEO-Meta-Daten-Helfer
    private function get_meta_title($post_id) {
        // Yoast SEO
        $title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        if ($title) return $title;
        
        // Rank Math
        $title = get_post_meta($post_id, 'rank_math_title', true);
        if ($title) return $title;
        
        // All in One SEO
        $title = get_post_meta($post_id, '_aioseop_title', true);
        if ($title) return $title;
        
        return '';
    }
    
    private function get_meta_description($post_id) {
        // Yoast SEO
        $desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if ($desc) return $desc;
        
        // Rank Math
        $desc = get_post_meta($post_id, 'rank_math_description', true);
        if ($desc) return $desc;
        
        // All in One SEO
        $desc = get_post_meta($post_id, '_aioseop_description', true);
        if ($desc) return $desc;
        
        return '';
    }
    
    private function get_focus_keyphrase($post_id) {
        // Yoast SEO
        $keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if ($keyword) return $keyword;
        
        // Rank Math
        $keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if ($keyword) return $keyword;
        
        return '';
    }
    
    // Import-Handler
    public function handle_import() {
        if (!wp_verify_nonce($_POST['nonce'], 'retexify_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Sicherheitsfehler');
        }
        
        try {
            if (!isset($_FILES['import_file'])) {
                wp_send_json_error('Keine Datei hochgeladen');
            }
            
            $file = $_FILES['import_file'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error('Upload-Fehler: ' . $file['error']);
            }
            
            $data = $this->process_csv_import($file['tmp_name']);
            $result = $this->import_enhanced_data($data);
            
            wp_send_json_success(array(
                'message' => 'Import erfolgreich!',
                'posts_updated' => $result['posts_updated'],
                'meta_updated' => $result['meta_updated'],
                'content_updated' => $result['content_updated'],
                'wpbakery_updated' => $result['wpbakery_updated'],
                'alt_texts_updated' => $result['alt_texts_updated']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Import-Fehler: ' . $e->getMessage());
        }
    }
    
    private function process_csv_import($file_path) {
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
        
        $header = null;
        $row_index = 0;
        
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if ($row_index === 0) {
                $header = $row;
            } else {
                if (count($row) >= 3) {
                    $data[] = array_combine($header, array_pad($row, count($header), ''));
                }
            }
            $row_index++;
        }
        
        fclose($handle);
        return $data;
    }
    
    private function import_enhanced_data($data) {
        $result = array(
            'posts_updated' => 0,
            'meta_updated' => 0,
            'content_updated' => 0,
            'wpbakery_updated' => 0,
            'alt_texts_updated' => 0
        );
        
        foreach ($data as $row) {
            try {
                $post_id = !empty($row['ID']) ? intval($row['ID']) : 0;
                $type = isset($row['Typ']) ? trim($row['Typ']) : '';
                
                // Bilder verarbeiten
                if ($type === 'image' && !empty($row['Image ID'])) {
                    $image_id = intval($row['Image ID']);
                    
                    if (!empty(trim($row['Alt Text (Neu)'] ?? ''))) {
                        $new_alt_text = sanitize_text_field(trim($row['Alt Text (Neu)']));
                        update_post_meta($image_id, '_wp_attachment_image_alt', $new_alt_text);
                        $result['alt_texts_updated']++;
                    }
                    continue;
                }
                
                // Posts/Seiten verarbeiten
                if (!$post_id) continue;
                
                $post = get_post($post_id);
                if (!$post) continue;
                
                $has_updates = false;
                
                // Titel (Neu)
                if (!empty(trim($row['Titel (Neu)'] ?? ''))) {
                    $new_title = sanitize_text_field(trim($row['Titel (Neu)']));
                    wp_update_post(array('ID' => $post_id, 'post_title' => $new_title));
                    $has_updates = true;
                }
                
                // Meta-Titel (Neu)
                if (!empty(trim($row['Meta Titel (Neu)'] ?? ''))) {
                    $new_meta_title = sanitize_text_field(trim($row['Meta Titel (Neu)']));
                    update_post_meta($post_id, '_yoast_wpseo_title', $new_meta_title);
                    $result['meta_updated']++;
                    $has_updates = true;
                }
                
                // Meta-Beschreibung (Neu)
                if (!empty(trim($row['Meta Beschreibung (Neu)'] ?? ''))) {
                    $new_meta_desc = sanitize_textarea_field(trim($row['Meta Beschreibung (Neu)']));
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', $new_meta_desc);
                    $result['meta_updated']++;
                    $has_updates = true;
                }
                
                // Focus Keyphrase (Neu)
                if (!empty(trim($row['Focus Keyphrase (Neu)'] ?? ''))) {
                    $new_focus_keyphrase = sanitize_text_field(trim($row['Focus Keyphrase (Neu)']));
                    update_post_meta($post_id, '_yoast_wpseo_focuskw', $new_focus_keyphrase);
                    $result['meta_updated']++;
                    $has_updates = true;
                }
                
                // Content (Neu)
                if (!empty(trim($row['Content (Neu)'] ?? ''))) {
                    $new_content = wp_kses_post(trim($row['Content (Neu)']));
                    wp_update_post(array('ID' => $post_id, 'post_content' => $new_content));
                    $result['content_updated']++;
                    $has_updates = true;
                }
                
                // WPBakery Text (Neu)
                if (!empty(trim($row['WPBakery Text (Neu)'] ?? ''))) {
                    $new_wpbakery_text = trim($row['WPBakery Text (Neu)']);
                    $updated_content = $this->replace_wpbakery_text_enhanced($post->post_content, $new_wpbakery_text);
                    
                    if ($updated_content !== $post->post_content) {
                        wp_update_post(array('ID' => $post_id, 'post_content' => $updated_content));
                        $result['wpbakery_updated']++;
                        $has_updates = true;
                    }
                }
                
                // WPBakery Meta-Titel (Neu)
                if (!empty(trim($row['WPBakery Meta-Titel (Neu)'] ?? ''))) {
                    $new_wpbakery_meta_title = trim($row['WPBakery Meta-Titel (Neu)']);
                    $updated_content = $this->replace_wpbakery_meta_title($post->post_content, $new_wpbakery_meta_title);
                    
                    if ($updated_content !== $post->post_content) {
                        wp_update_post(array('ID' => $post_id, 'post_content' => $updated_content));
                        $result['wpbakery_updated']++;
                        $has_updates = true;
                    }
                }
                
                // WPBakery Meta-Content (Neu)
                if (!empty(trim($row['WPBakery Meta-Content (Neu)'] ?? ''))) {
                    $new_wpbakery_meta_content = trim($row['WPBakery Meta-Content (Neu)']);
                    $updated_content = $this->replace_wpbakery_meta_content($post->post_content, $new_wpbakery_meta_content);
                    
                    if ($updated_content !== $post->post_content) {
                        wp_update_post(array('ID' => $post_id, 'post_content' => $updated_content));
                        $result['wpbakery_updated']++;
                        $has_updates = true;
                    }
                }
                
                if ($has_updates) {
                    $result['posts_updated']++;
                }
                
            } catch (Exception $e) {
                continue;
            }
        }
        
        return $result;
    }
    
    private function replace_wpbakery_text_enhanced($content, $new_text) {
        // Ersetzt Text in verschiedenen WPBakery/Salient Elementen
        $patterns = array(
            '/(\[vc_column_text[^\]]*\]).*?(\[\/vc_column_text\])/s',
            '/(\[nectar_highlighted_text[^>]*highlight_color="[^"]*"[^>]*\])([^[]*?)(\[\/nectar_highlighted_text\])/s'
        );
        
        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '$1' . $new_text . '$2', $content, 1);
        }
        
        return $content;
    }
    
    // NEUE Funktion: WPBakery Meta-Titel ersetzen
    private function replace_wpbakery_meta_title($content, $new_title) {
        // Ersetzt Meta-Titel in WPBakery/Salient Elementen
        $patterns = array(
            '/(\[vc_custom_heading[^>]*text=")([^"]*)("[^\]]*\])/s',
            '/(\[vc_text_separator[^>]*title=")([^"]*)("[^\]]*\])/s',
            '/(\[nectar_cta[^>]*heading=")([^"]*)("[^\]]*\])/s',
            '/(\[nectar_page_header[^>]*title=")([^"]*)("[^\]]*\])/s'
        );
        
        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '$1' . $new_title . '$3', $content, 1);
        }
        
        return $content;
    }
    
    // NEUE Funktion: WPBakery Meta-Content ersetzen
    private function replace_wpbakery_meta_content($content, $new_content) {
        // Ersetzt Meta-Content in WPBakery/Salient Elementen
        $patterns = array(
            '/(\[vc_cta[^>]*h2="[^"]*"[^>]*\]).*?(\[\/vc_cta\])/s',
            '/(\[nectar_cta[^>]*heading="[^"]*"[^>]*\]).*?(\[\/nectar_cta\])/s',
            '/(\[vc_message[^>]*\]).*?(\[\/vc_message\])/s',
            '/(\[nectar_quote[^>]*\]).*?(\[\/nectar_quote\])/s'
        );
        
        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '$1' . $new_content . '$2', $content, 1);
        }
        
        return $content;
    }
    
    // Download-Handler
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
        unlink($file_path);
        exit;
    }
    
    // CSS (KOMPLETT)
    private function get_admin_css() {
        return '
        .retexify-admin-wrap { margin: 20px 20px 0 0; max-width: 1400px; }
        .retexify-title { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; color: #1d2327; font-size: 24px; font-weight: 600; }
        .retexify-title .dashicons { color: #2271b1; font-size: 28px; }
        .retexify-description { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; padding: 16px; margin-bottom: 30px; color: #155724; }
        
        .retexify-debug-container, .retexify-dashboard-container { margin-bottom: 30px; }
        .retexify-enhanced-dashboard { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 30px; }
        .retexify-loading-dashboard { text-align: center; padding: 40px; color: #646970; font-style: italic; }
        
        /* System-Status Grid */
        .retexify-system-status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .retexify-status-item { display: flex; align-items: center; gap: 12px; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; }
        .retexify-status-icon { font-size: 18px; }
        .retexify-status-content { flex: 1; }
        .retexify-status-title { font-size: 14px; font-weight: 600; color: #1d2327; margin-bottom: 2px; }
        .retexify-status-detail { font-size: 12px; color: #646970; }
        
        .retexify-wpbakery-details { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; margin-top: 15px; }
        .retexify-wpbakery-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px; font-size: 13px; }
        
        .retexify-seo-score-container { text-align: center; margin-bottom: 30px; }
        .retexify-seo-score-circle { width: 120px; height: 120px; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center; }
        .retexify-seo-score-inner { width: 90px; height: 90px; background: #fff; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .retexify-seo-score-number { font-size: 28px; font-weight: bold; color: #1d2327; }
        .retexify-seo-score-label { font-size: 12px; color: #646970; }
        
        .retexify-stats-grid-enhanced { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .retexify-stat-card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 15px; transition: all 0.2s ease; }
        .retexify-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .retexify-stat-icon { font-size: 32px; }
        .retexify-stat-content { flex: 1; }
        .retexify-stat-number { font-size: 24px; font-weight: bold; color: #2271b1; margin-bottom: 4px; }
        .retexify-stat-label { font-size: 14px; font-weight: 600; color: #1d2327; margin-bottom: 2px; }
        .retexify-stat-detail { font-size: 12px; color: #646970; }
        
        .retexify-system-info { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; margin-top: 20px; }
        .retexify-system-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px; font-size: 13px; }
        
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
        .retexify-checkbox-item { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px 12px; border-radius: 4px; transition: all 0.2s ease; }
        .retexify-checkbox-item:hover { background-color: rgba(34, 113, 177, 0.05); transform: translateY(-1px); }
        .retexify-checkbox-item input[type="checkbox"] { margin: 0; transform: scale(1.1); accent-color: #2271b1; }
        .retexify-checkbox-label { font-size: 13px; color: #1d2327; }
        .retexify-count { font-weight: bold; color: #2271b1; }
        
        .retexify-export-info, .retexify-import-info { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 16px; margin: 16px 0; }
        .retexify-export-info ul, .retexify-import-info ul { margin: 0; padding: 0; list-style: none; }
        .retexify-export-info li, .retexify-import-info li { display: flex; align-items: center; gap: 8px; padding: 4px 0; color: #1d2327; font-size: 13px; }
        
        .retexify-preview-section { margin: 20px 0; text-align: center; }
        .retexify-action-area { margin-top: 20px; text-align: center; }
        .button-hero { padding: 12px 24px !important; font-size: 16px !important; height: auto !important; display: inline-flex !important; align-items: center !important; gap: 8px !important; }
        .retexify-file-upload { margin-bottom: 20px; display: flex; align-items: center; gap: 15px; justify-content: center; }
        .retexify-stats-container { margin-top: 30px; }
        .retexify-preview-result { margin-top: 10px; display: none; }
        .retexify-preview-result.show { display: block; }
        
        @media (max-width: 1024px) { 
            .retexify-main-container { grid-template-columns: 1fr; } 
            .retexify-stats-grid-enhanced { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
            .retexify-system-status-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        }
        ';
    }
    
    // JavaScript (KOMPLETT)
    private function get_admin_js() {
        return '
        jQuery(document).ready(function($) {
            // Sofort beim Laden initialisieren
            checkWPBakeryStatus();
            loadDashboard();
            updateContentCounts();
            loadPostTypes();
            
            function checkWPBakeryStatus() {
                $.post(retexify_ajax.ajax_url, {
                    action: "retexify_check_wpbakery",
                    nonce: retexify_ajax.nonce
                }, function(response) {
                    if (response.success && response.data.wpbakery_detected) {
                        // WPBakery-Optionen anzeigen
                        $("#retexify-wpbakery-option").show();
                        $("#retexify-wpbakery-meta-title-option").show();
                        $("#retexify-wpbakery-meta-content-option").show();
                        $("#retexify-wpbakery-info").show();
                        $("#retexify-wpbakery-import-info").show();
                    } else {
                        // WPBakery-Optionen verstecken
                        $("#retexify-wpbakery-option").hide();
                        $("#retexify-wpbakery-meta-title-option").hide();
                        $("#retexify-wpbakery-meta-content-option").hide();
                        $("#retexify-wpbakery-info").hide();
                        $("#retexify-wpbakery-import-info").hide();
                    }
                });
            }
            
            function loadDashboard() {
                $.post(retexify_ajax.ajax_url, {
                    action: "retexify_get_stats",
                    nonce: retexify_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $("#retexify-enhanced-dashboard").html(response.data);
                    } else {
                        $("#retexify-enhanced-dashboard").html("<div class=\"retexify-loading-dashboard\">❌ Fehler: " + response.data + "</div>");
                    }
                }).fail(function() {
                    $("#retexify-enhanced-dashboard").html("<div class=\"retexify-loading-dashboard\">❌ Verbindungsfehler</div>");
                });
            }
            
            function updateContentCounts() {
                $.post(retexify_ajax.ajax_url, {
                    action: "retexify_get_counts",
                    nonce: retexify_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        var counts = response.data;
                        
                        // Status-Counts
                        $("#count-publish").text(counts.status.publish || 0);
                        $("#count-draft").text(counts.status.draft || 0);
                        $("#count-private").text(counts.status.private || 0);
                        
                        // Content-Counts
                        $("#count-title").text(counts.content.title || 0);
                        $("#count-content").text(counts.content.content || 0);
                        $("#count-meta-title").text(counts.content.meta_title || 0);
                        $("#count-meta-desc").text(counts.content.meta_description || 0);
                        $("#count-focus").text(counts.content.focus_keyphrase || 0);
                        $("#count-wpbakery").text(counts.content.wpbakery_text || 0);
                        $("#count-wpbakery-meta-title").text(counts.content.wpbakery_meta_title || 0);
                        $("#count-wpbakery-meta-content").text(counts.content.wpbakery_meta_content || 0);
                        $("#count-images").text(counts.content.alt_texts || 0);
                        
                        // Post-Typ-Counts
                        if (counts.post_types) {
                            $("#retexify-post-types-grid .retexify-count").each(function() {
                                var postType = $(this).data("type");
                                if (postType && counts.post_types[postType] !== undefined) {
                                    $(this).text(counts.post_types[postType]);
                                }
                            });
                        }
                    }
                });
            }
            
            function loadPostTypes() {
                // Post-Typen dynamisch laden
                var postTypesHtml = "";
                var defaultTypes = ["post", "page"];
                
                defaultTypes.forEach(function(type, index) {
                    var checked = defaultTypes.includes(type) ? "checked" : "";
                    var label = type === "post" ? "Beiträge" : "Seiten";
                    
                    postTypesHtml += "<label class=\"retexify-checkbox-item\">";
                    postTypesHtml += "<input type=\"checkbox\" class=\"retexify-post-type-checkbox\" name=\"post_types[]\" value=\"" + type + "\" " + checked + ">";
                    postTypesHtml += "<span class=\"retexify-checkbox-label\">" + label + " (<span class=\"retexify-count\" data-type=\"" + type + "\">0</span>)</span>";
                    postTypesHtml += "</label>";
                });
                
                $("#retexify-post-types-grid").html(postTypesHtml);
            }
            
            $("#retexify-refresh-stats").on("click", function() {
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop("disabled", true).html("<span class=\"dashicons dashicons-update\" style=\"animation: spin 1s linear infinite;\"></span> Aktualisiere...");
                
                checkWPBakeryStatus();
                loadDashboard();
                updateContentCounts();
                
                setTimeout(function() {
                    $btn.prop("disabled", false).html(originalText);
                }, 2000);
            });
            
            $("#retexify-debug-btn").on("click", function() {
                executeTest("retexify_debug_export", $(this), "Erstelle Debug-Info...");
            });
            
            $("#retexify-preview-btn").on("click", function() {
                var selections = getSelections();
                if (!selections) {
                    alert("Bitte treffen Sie eine Auswahl.");
                    return;
                }
                
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
                        result += "<p>Posts: " + response.data.posts_exported + " • Bilder: " + response.data.images_exported + " • CSV-Zeilen: " + response.data.total_items + "</p>";
                        result += "<a href=\"" + response.data.download_url + "\" class=\"button button-primary\" style=\"margin-top: 10px;\">📥 " + response.data.filename + " herunterladen</a>";
                        result += "</div>";
                        $("#retexify-export-result").html(result);
                        
                        // ReTexify-Hinweis anzeigen
                        setTimeout(function() {
                            var formatHint = "<div style=\"background: #d4edda; border: 1px solid #c3e6cb; padding: 12px; border-radius: 4px; margin-top: 10px; font-size: 13px; color: #155724;\">";
                            formatHint += "📝 <strong>ReTexify:</strong> Content ohne WPBakery-Shortcodes • WPBakery Meta-Titel & Meta-Content getrennt<br>";
                            formatHint += "📊 <strong>Import-Regel:</strong> Nur \"(Neu)\"-Spalten werden beim Import verwendet";
                            formatHint += "</div>";
                            $("#retexify-export-result").append(formatHint);
                        }, 1000);
                    } else {
                        $("#retexify-export-result").html("<div style=\"background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; padding: 15px; border-radius: 6px;\">❌ Export-Fehler: " + response.data + "</div>");
                    }
                }).always(function() {
                    $btn.prop("disabled", false).html(originalText);
                });
            });
            
            $("#retexify-select-file-btn").on("click", function() {
                $("#retexify-import-file").click();
            });
            
            $("#retexify-import-file").on("change", function() {
                var fileName = this.files[0] ? this.files[0].name : "";
                $("#retexify-file-name").text(fileName);
                $("#retexify-import-btn").prop("disabled", !fileName);
            });
            
            $("#retexify-import-btn").on("click", function() {
                var fileInput = $("#retexify-import-file")[0];
                if (!fileInput.files[0]) {
                    alert("Bitte wählen Sie zuerst eine CSV-Datei aus.");
                    return;
                }
                
                if (!confirm("⚠️ WARNUNG: Dieser Import überschreibt bestehende Texte!\\n\\nMöchten Sie den Import durchführen?")) {
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
                    timeout: 180000,
                    success: function(response) {
                        if (response.success) {
                            var result = "<div style=\"background: #d1e7dd; border: 1px solid #badbcc; color: #0f5132; padding: 15px; border-radius: 6px; text-align: center;\">";
                            result += "<h4 style=\"margin: 0 0 10px 0;\">✅ " + response.data.message + "</h4>";
                            result += "<p>Posts: " + response.data.posts_updated + " • Meta: " + response.data.meta_updated + " • Content: " + response.data.content_updated + " • WPBakery: " + response.data.wpbakery_updated + " • Alt-Texte: " + response.data.alt_texts_updated + "</p>";
                            result += "</div>";
                            $("#retexify-import-result").html(result);
                            $("#retexify-import-file").val("");
                            $("#retexify-file-name").text("");
                            $("#retexify-import-btn").prop("disabled", true);
                            
                            setTimeout(function() {
                                loadDashboard();
                                updateContentCounts();
                            }, 1000);
                        } else {
                            $("#retexify-import-result").html("<div style=\"background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; padding: 15px; border-radius: 6px;\">❌ Import-Fehler: " + response.data + "</div>");
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMsg = "Import-Fehler: " + (status === "timeout" ? "Timeout" : error);
                        $("#retexify-import-result").html("<div style=\"background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; padding: 15px; border-radius: 6px;\">❌ " + errorMsg + "</div>");
                    }
                }).always(function() {
                    $btn.prop("disabled", false).html(originalText);
                });
            });
            
            $("#retexify-test-btn").on("click", function() {
                executeTest("retexify_test", $(this), "System wird getestet...");
            });
            
            $("#retexify-wpbakery-btn").on("click", function() {
                executeTest("retexify_test", $(this), "WPBakery/Salient wird analysiert...");
            });
            
            function executeTest(action, $btn, loadingText) {
                var originalText = $btn.html();
                $btn.prop("disabled", true).html("<span class=\"dashicons dashicons-update\" style=\"animation: spin 1s linear infinite;\"></span> " + loadingText);
                
                $.post(retexify_ajax.ajax_url, {
                    action: action,
                    nonce: retexify_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $("#retexify-test-result").html(response.data);
                    } else {
                        $("#retexify-test-result").html("<div style=\"background: #f8d7da; padding: 15px; border-radius: 6px;\">❌ Fehler: " + response.data + "</div>");
                    }
                }).always(function() {
                    $btn.prop("disabled", false).html(originalText);
                });
            }
            
            function getSelections() {
                var postTypes = [];
                $(".retexify-post-type-checkbox:checked").each(function() {
                    postTypes.push($(this).val());
                });
                
                var postStatus = [];
                $(".retexify-status-checkbox:checked").each(function() {
                    postStatus.push($(this).val());
                });
                
                var contentTypes = [];
                $(".retexify-content-checkbox:checked").each(function() {
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
            
            // Auto-Update der Counts alle 30 Sekunden
            setInterval(function() {
                if (!document.hidden) {
                    updateContentCounts();
                }
            }, 30000);
            
            // CSS für Animationen
            $("<style>").text("@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }").appendTo("head");
        });
        ';
    }
}

// Plugin initialisieren
new ReTexify();
?>