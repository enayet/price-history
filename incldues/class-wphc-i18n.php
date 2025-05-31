<?php
/**
 * Define the internationalization functionality
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 * @author     Your Name <email@example.com>
 */
class WPHC_i18n {

    /**
     * The text domain of the plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $text_domain    The text domain of the plugin.
     */
    private $text_domain;

    /**
     * The path to the language files.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $languages_path    The path to the language files.
     */
    private $languages_path;

    /**
     * Array of loaded language files.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $loaded_languages    Array of loaded language files.
     */
    private $loaded_languages;

    /**
     * Default locale for fallback.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $default_locale    Default locale for fallback.
     */
    private $default_locale;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string $text_domain    The text domain of this plugin.
     */
    public function __construct($text_domain = 'woocommerce-price-history-compliance') {
        $this->text_domain = $text_domain;
        $this->languages_path = WPHC_PLUGIN_DIR . 'languages/';
        $this->loaded_languages = array();
        $this->default_locale = 'en_US';
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {
        $locale = apply_filters('plugin_locale', get_locale(), $this->text_domain);
        
        // Try to load from WordPress languages directory first (for automatic translations)
        $wp_lang_dir = WP_LANG_DIR . '/plugins/' . $this->text_domain . '-' . $locale . '.mo';
        if (file_exists($wp_lang_dir)) {
            $loaded = load_textdomain($this->text_domain, $wp_lang_dir);
            if ($loaded) {
                $this->loaded_languages[$locale] = $wp_lang_dir;
                wphc_log("Loaded translation from WP lang dir: {$locale}", 'info');
                return true;
            }
        }

        // Fallback to plugin's language directory
        $plugin_lang_file = $this->languages_path . $this->text_domain . '-' . $locale . '.mo';
        if (file_exists($plugin_lang_file)) {
            $loaded = load_textdomain($this->text_domain, $plugin_lang_file);
            if ($loaded) {
                $this->loaded_languages[$locale] = $plugin_lang_file;
                wphc_log("Loaded translation from plugin dir: {$locale}", 'info');
                return true;
            }
        }

        // Try to load using WordPress load_plugin_textdomain function
        $loaded = load_plugin_textdomain(
            $this->text_domain,
            false,
            dirname(WPHC_PLUGIN_BASENAME) . '/languages/'
        );

        if ($loaded) {
            $this->loaded_languages[$locale] = 'wp_loaded';
            wphc_log("Loaded translation using load_plugin_textdomain: {$locale}", 'info');
            return true;
        }

        // Log if no translation was loaded
        wphc_log("No translation file found for locale: {$locale}", 'warning');
        return false;
    }

    /**
     * Set the text domain for the plugin.
     *
     * @since    1.0.0
     * @param    string $text_domain    The text domain to set.
     */
    public function set_text_domain($text_domain) {
        $this->text_domain = sanitize_key($text_domain);
    }

    /**
     * Get the current text domain.
     *
     * @since    1.0.0
     * @return   string    The current text domain.
     */
    public function get_text_domain() {
        return $this->text_domain;
    }

    /**
     * Check if a specific locale is loaded.
     *
     * @since    1.0.0
     * @param    string $locale    The locale to check.
     * @return   bool              True if locale is loaded, false otherwise.
     */
    public function is_locale_loaded($locale) {
        return isset($this->loaded_languages[$locale]);
    }

    /**
     * Get all loaded languages.
     *
     * @since    1.0.0
     * @return   array    Array of loaded languages.
     */
    public function get_loaded_languages() {
        return $this->loaded_languages;
    }

    /**
     * Get available language files in the plugin directory.
     *
     * @since    1.0.0
     * @return   array    Array of available language files.
     */
    public function get_available_languages() {
        $available_languages = array();
        
        if (!is_dir($this->languages_path)) {
            return $available_languages;
        }

        $files = glob($this->languages_path . $this->text_domain . '-*.mo');
        
        foreach ($files as $file) {
            $basename = basename($file, '.mo');
            $locale = str_replace($this->text_domain . '-', '', $basename);
            
            if ($locale !== $basename) { // Make sure we extracted a locale
                $available_languages[$locale] = array(
                    'file' => $file,
                    'locale' => $locale,
                    'name' => $this->get_language_name($locale),
                    'native_name' => $this->get_language_native_name($locale),
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                );
            }
        }

        return $available_languages;
    }

    /**
     * Get language name from locale code.
     *
     * @since    1.0.0
     * @param    string $locale    The locale code.
     * @return   string            The language name.
     */
    public function get_language_name($locale) {
        $language_names = array(
            'en_US' => 'English (United States)',
            'en_GB' => 'English (United Kingdom)', 
            'en_CA' => 'English (Canada)',
            'en_AU' => 'English (Australia)',
            'de_DE' => 'German',
            'de_AT' => 'German (Austria)',
            'de_CH' => 'German (Switzerland)',
            'fr_FR' => 'French',
            'fr_CA' => 'French (Canada)',
            'fr_BE' => 'French (Belgium)',
            'es_ES' => 'Spanish',
            'es_MX' => 'Spanish (Mexico)',
            'es_AR' => 'Spanish (Argentina)',
            'it_IT' => 'Italian',
            'pt_PT' => 'Portuguese',
            'pt_BR' => 'Portuguese (Brazil)',
            'nl_NL' => 'Dutch',
            'nl_BE' => 'Dutch (Belgium)',
            'da_DK' => 'Danish',
            'sv_SE' => 'Swedish',
            'no_NO' => 'Norwegian',
            'fi_FI' => 'Finnish',
            'pl_PL' => 'Polish',
            'cs_CZ' => 'Czech',
            'sk_SK' => 'Slovak',
            'hu_HU' => 'Hungarian',
            'ro_RO' => 'Romanian',
            'bg_BG' => 'Bulgarian',
            'hr_HR' => 'Croatian',
            'sl_SI' => 'Slovenian',
            'et_EE' => 'Estonian',
            'lv_LV' => 'Latvian',
            'lt_LT' => 'Lithuanian',
            'ru_RU' => 'Russian',
            'uk_UA' => 'Ukrainian',
            'el_GR' => 'Greek',
            'tr_TR' => 'Turkish',
            'ar_SA' => 'Arabic',
            'he_IL' => 'Hebrew',
            'ja_JP' => 'Japanese',
            'ko_KR' => 'Korean',
            'zh_CN' => 'Chinese (Simplified)',
            'zh_TW' => 'Chinese (Traditional)',
            'hi_IN' => 'Hindi',
            'th_TH' => 'Thai',
            'vi_VN' => 'Vietnamese',
            'id_ID' => 'Indonesian',
            'ms_MY' => 'Malay',
            'tl_PH' => 'Filipino',
        );

        return isset($language_names[$locale]) ? $language_names[$locale] : $locale;
    }

    /**
     * Get native language name from locale code.
     *
     * @since    1.0.0
     * @param    string $locale    The locale code.
     * @return   string            The native language name.
     */
    public function get_language_native_name($locale) {
        $native_names = array(
            'en_US' => 'English',
            'en_GB' => 'English', 
            'en_CA' => 'English',
            'en_AU' => 'English',
            'de_DE' => 'Deutsch',
            'de_AT' => 'Deutsch',
            'de_CH' => 'Deutsch',
            'fr_FR' => 'Français',
            'fr_CA' => 'Français',
            'fr_BE' => 'Français',
            'es_ES' => 'Español',
            'es_MX' => 'Español',
            'es_AR' => 'Español',
            'it_IT' => 'Italiano',
            'pt_PT' => 'Português',
            'pt_BR' => 'Português',
            'nl_NL' => 'Nederlands',
            'nl_BE' => 'Nederlands',
            'da_DK' => 'Dansk',
            'sv_SE' => 'Svenska',
            'no_NO' => 'Norsk',
            'fi_FI' => 'Suomi',
            'pl_PL' => 'Polski',
            'cs_CZ' => 'Čeština',
            'sk_SK' => 'Slovenčina',
            'hu_HU' => 'Magyar',
            'ro_RO' => 'Română',
            'bg_BG' => 'Български',
            'hr_HR' => 'Hrvatski',
            'sl_SI' => 'Slovenščina',
            'et_EE' => 'Eesti',
            'lv_LV' => 'Latviešu',
            'lt_LT' => 'Lietuvių',
            'ru_RU' => 'Русский',
            'uk_UA' => 'Українська',
            'el_GR' => 'Ελληνικά',
            'tr_TR' => 'Türkçe',
            'ar_SA' => 'العربية',
            'he_IL' => 'עברית',
            'ja_JP' => '日本語',
            'ko_KR' => '한국어',
            'zh_CN' => '简体中文',
            'zh_TW' => '繁體中文',
            'hi_IN' => 'हिन्दी',
            'th_TH' => 'ไทย',
            'vi_VN' => 'Tiếng Việt',
            'id_ID' => 'Bahasa Indonesia',
            'ms_MY' => 'Bahasa Malaysia',
            'tl_PH' => 'Filipino',
        );

        return isset($native_names[$locale]) ? $native_names[$locale] : $this->get_language_name($locale);
    }

    /**
     * Create the languages directory if it doesn't exist.
     *
     * @since    1.0.0
     * @return   bool    True if directory exists or was created, false otherwise.
     */
    public function create_languages_directory() {
        if (!is_dir($this->languages_path)) {
            if (!wp_mkdir_p($this->languages_path)) {
                wphc_log("Failed to create languages directory: {$this->languages_path}", 'error');
                return false;
            }
            wphc_log("Created languages directory: {$this->languages_path}", 'info');
        }
        return true;
    }

    /**
     * Load a specific locale manually.
     *
     * @since    1.0.0
     * @param    string $locale    The locale to load.
     * @return   bool              True if loaded successfully, false otherwise.
     */
    public function load_locale($locale) {
        $locale = sanitize_text_field($locale);
        
        // Check if already loaded
        if ($this->is_locale_loaded($locale)) {
            return true;
        }

        // Try WordPress languages directory first
        $wp_lang_file = WP_LANG_DIR . '/plugins/' . $this->text_domain . '-' . $locale . '.mo';
        if (file_exists($wp_lang_file)) {
            $loaded = load_textdomain($this->text_domain, $wp_lang_file);
            if ($loaded) {
                $this->loaded_languages[$locale] = $wp_lang_file;
                return true;
            }
        }

        // Try plugin directory
        $plugin_lang_file = $this->languages_path . $this->text_domain . '-' . $locale . '.mo';
        if (file_exists($plugin_lang_file)) {
            $loaded = load_textdomain($this->text_domain, $plugin_lang_file);
            if ($loaded) {
                $this->loaded_languages[$locale] = $plugin_lang_file;
                return true;
            }
        }

        return false;
    }

    /**
     * Unload a specific locale.
     *
     * @since    1.0.0
     * @param    string $locale    The locale to unload.
     * @return   bool              True if unloaded successfully, false otherwise.
     */
    public function unload_locale($locale) {
        if (!$this->is_locale_loaded($locale)) {
            return false;
        }

        $result = unload_textdomain($this->text_domain);
        if ($result) {
            unset($this->loaded_languages[$locale]);
        }

        return $result;
    }

    /**
     * Get the current WordPress locale.
     *
     * @since    1.0.0
     * @return   string    The current locale.
     */
    public function get_current_locale() {
        return get_locale();
    }

    /**
     * Get user's locale preference.
     *
     * @since    1.0.0
     * @param    int $user_id    Optional. User ID. Defaults to current user.
     * @return   string          The user's locale preference.
     */
    public function get_user_locale($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return $this->get_current_locale();
        }

        return get_user_locale($user_id);
    }

    /**
     * Check if the current locale is RTL (Right-to-Left).
     *
     * @since    1.0.0
     * @return   bool    True if RTL, false otherwise.
     */
    public function is_rtl() {
        return is_rtl();
    }

    /**
     * Get RTL language codes.
     *
     * @since    1.0.0
     * @return   array    Array of RTL language codes.
     */
    public function get_rtl_languages() {
        return array(
            'ar',    // Arabic
            'he_IL', // Hebrew
            'fa_IR', // Persian/Farsi
            'ur',    // Urdu
            'ps',    // Pashto
            'sd_PK', // Sindhi
            'ug_CN', // Uyghur
            'yi',    // Yiddish
            'ku',    // Kurdish
            'dv',    // Divehi
        );
    }

    /**
     * Check if a specific locale is RTL.
     *
     * @since    1.0.0
     * @param    string $locale    The locale to check.
     * @return   bool              True if RTL, false otherwise.
     */
    public function is_locale_rtl($locale) {
        $rtl_languages = $this->get_rtl_languages();
        $language_code = substr($locale, 0, 2);
        
        return in_array($locale, $rtl_languages, true) || in_array($language_code, $rtl_languages, true);
    }

    /**
     * Format a translatable string with placeholders.
     *
     * @since    1.0.0
     * @param    string $string    The string to format.
     * @param    array  $args      Array of arguments to replace placeholders.
     * @return   string            The formatted string.
     */
    public function format_string($string, $args = array()) {
        if (empty($args)) {
            return $string;
        }

        // Support both sprintf style and named placeholders
        if (is_array($args) && !empty($args)) {
            // Check if we have named placeholders like {name}
            if (preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $string)) {
                foreach ($args as $key => $value) {
                    $string = str_replace('{' . $key . '}', $value, $string);
                }
            } else {
                // Use sprintf for numbered placeholders
                $string = vsprintf($string, $args);
            }
        }

        return $string;
    }

    /**
     * Get translation statistics for the plugin.
     *
     * @since    1.0.0
     * @return   array    Array of translation statistics.
     */
    public function get_translation_stats() {
        $stats = array(
            'total_languages' => 0,
            'loaded_languages' => count($this->loaded_languages),
            'available_languages' => 0,
            'default_locale' => $this->default_locale,
            'current_locale' => $this->get_current_locale(),
            'is_rtl' => $this->is_rtl(),
            'textdomain' => $this->text_domain,
            'languages_path' => $this->languages_path,
        );

        $available = $this->get_available_languages();
        $stats['available_languages'] = count($available);
        $stats['total_languages'] = $stats['available_languages'];

        return $stats;
    }

    /**
     * Generate a .pot template file for translations.
     *
     * @since    1.0.0
     * @return   bool    True if generated successfully, false otherwise.
     */
    public function generate_pot_file() {
        if (!$this->create_languages_directory()) {
            return false;
        }

        $pot_file = $this->languages_path . $this->text_domain . '.pot';
        
        // Basic POT file header
        $pot_header = sprintf(
            '# Copyright (C) %1$s WooCommerce Price History & Sale Compliance
# This file is distributed under the same license as the WooCommerce Price History & Sale Compliance package.
msgid ""
msgstr ""
"Project-Id-Version: WooCommerce Price History & Sale Compliance %2$s\\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/woocommerce-price-history-compliance\\n"
"POT-Creation-Date: %3$s\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"PO-Revision-Date: %3$s\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"X-Generator: WooCommerce Price History & Sale Compliance %2$s\\n"

',
            gmdate('Y'),
            WPHC_VERSION,
            gmdate('Y-m-d H:i+0000')
        );

        // Write the POT file
        $result = file_put_contents($pot_file, $pot_header);
        
        if ($result !== false) {
            wphc_log("Generated POT file: {$pot_file}", 'info');
            return true;
        }

        wphc_log("Failed to generate POT file: {$pot_file}", 'error');
        return false;
    }

    /**
     * Validate translation files.
     *
     * @since    1.0.0
     * @return   array    Array of validation results.
     */
    public function validate_translation_files() {
        $validation_results = array();
        $available_languages = $this->get_available_languages();

        foreach ($available_languages as $locale => $language_data) {
            $validation = array(
                'locale' => $locale,
                'file_exists' => file_exists($language_data['file']),
                'file_readable' => is_readable($language_data['file']),
                'file_size' => $language_data['size'],
                'is_valid' => false,
                'errors' => array(),
            );

            // Check if file is not empty
            if ($validation['file_size'] === 0) {
                $validation['errors'][] = esc_html__('Translation file is empty', 'woocommerce-price-history-compliance');
            }

            // Try to load the translation to check validity
            if ($validation['file_exists'] && $validation['file_readable'] && $validation['file_size'] > 0) {
                $test_domain = $this->text_domain . '_test_' . wp_rand(1000, 9999);
                $loaded = load_textdomain($test_domain, $language_data['file']);
                
                if ($loaded) {
                    $validation['is_valid'] = true;
                    unload_textdomain($test_domain);
                } else {
                    $validation['errors'][] = esc_html__('Translation file could not be loaded', 'woocommerce-price-history-compliance');
                }
            }

            $validation_results[$locale] = $validation;
        }

        return $validation_results;
    }

    /**
     * Get plugin's translatable strings for analysis.
     *
     * @since    1.0.0
     * @return   array    Array of translatable strings found in the plugin.
     */
    public function extract_translatable_strings() {
        $strings = array();
        $plugin_files = array();

        // Get all PHP files in the plugin
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(WPHC_PLUGIN_DIR)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $plugin_files[] = $file->getPathname();
            }
        }

        // Extract strings from each file
        foreach ($plugin_files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Find translation function calls
            $patterns = array(
                '/(?:__|_e|_x|_ex|_n|_nx|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]+)[\'"])?\s*(?:,\s*[\'"]' . preg_quote($this->text_domain, '/') . '[\'"])?\s*\)/',
            );

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $string = $match[1];
                        $context = isset($match[2]) ? $match[2] : '';
                        
                        $strings[] = array(
                            'string' => $string,
                            'context' => $context,
                            'file' => str_replace(WPHC_PLUGIN_DIR, '', $file),
                        );
                    }
                }
            }
        }

        return $strings;
    }

    /**
     * Clean up unused translation files.
     *
     * @since    1.0.0
     * @param    array $keep_locales    Optional. Array of locales to keep.
     * @return   int                    Number of files removed.
     */
    public function cleanup_unused_translations($keep_locales = array()) {
        if (empty($keep_locales)) {
            $keep_locales = array($this->get_current_locale(), $this->default_locale);
        }

        $removed_count = 0;
        $available_languages = $this->get_available_languages();

        foreach ($available_languages as $locale => $language_data) {
            if (!in_array($locale, $keep_locales, true)) {
                if (wp_delete_file($language_data['file'])) {
                    $removed_count++;
                    wphc_log("Removed unused translation file: {$locale}", 'info');
                    
                    // Also remove .po file if it exists
                    $po_file = str_replace('.mo', '.po', $language_data['file']);
                    if (file_exists($po_file)) {
                        wp_delete_file($po_file);
                    }
                }
            }
        }

        return $removed_count;
    }

    /**
     * Get translation loading priority.
     *
     * @since    1.0.0
     * @return   int    The priority for translation loading.
     */
    public function get_loading_priority() {
        return apply_filters('wphc_i18n_loading_priority', 10);
    }

    /**
     * Register internationalization hooks.
     *
     * @since    1.0.0
     * @param    WPHC_Loader $loader    The loader instance.
     */
    public function register_hooks($loader) {
        $loader->add_action('plugins_loaded', $this, 'load_plugin_textdomain', $this->get_loading_priority());
        $loader->add_action('init', $this, 'init_translation_features');
        
        // Admin hooks for translation management
        if (is_admin()) {
            $loader->add_action('admin_init', $this, 'check_translation_updates');
        }
    }

    /**
     * Initialize translation features.
     *
     * @since    1.0.0
     */
    public function init_translation_features() {
        // Set up any translation-related features
        $this->setup_translation_cache();
        
        // Log translation status in debug mode
        if (wphc_is_development_mode()) {
            $stats = $this->get_translation_stats();
            wphc_log('Translation stats: ' . print_r($stats, true), 'info'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
        }
    }

    /**
     * Set up translation caching.
     *
     * @since    1.0.0
     */
    private function setup_translation_cache() {
        // Enable translation caching if not already enabled
        if (!wp_using_ext_object_cache() && function_exists('wp_cache_add_global_groups')) {
            wp_cache_add_global_groups(array('translations'));
        }
    }

    /**
     * Check for translation updates.
     *
     * @since    1.0.0
     */
    public function check_translation_updates() {
        // This method can be extended to check for translation updates
        // from WordPress.org or other translation services
        $last_check = get_option('wphc_translation_last_check', 0);
        $check_interval = DAY_IN_SECONDS; // Check once per day
        
        if ((time() - $last_check) > $check_interval) {
            // Update the last check time
            update_option('wphc_translation_last_check', time());
            
            // Log the check
            wphc_log('Checked for translation updates', 'info');
        }
    }

    /**
     * Get debug information about translations.
     *
     * @since    1.0.0
     * @return   array    Debug information.
     */
    public function get_debug_info() {
        return array(
            'text_domain' => $this->text_domain,
            'languages_path' => $this->languages_path,
            'current_locale' => $this->get_current_locale(),
            'loaded_languages' => $this->loaded_languages,
            'available_languages' => array_keys($this->get_available_languages()),
            'is_rtl' => $this->is_rtl(),
            'translation_stats' => $this->get_translation_stats(),
        );
    }
}