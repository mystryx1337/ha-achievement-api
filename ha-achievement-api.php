<?php
/**
 * Plugin Name: HA Achievement API
 * Plugin URI:  https://github.com/alexander/ha-achievement-api
 * Description: Zentrales, modulares Gamification- und Leistungsnachweis-System für WordPress. Verwaltet XP, Stufen, Lernaufgaben, Badges und öffentlich verifizierbare Diplome für ha-bpmn-manager und Erweiterungen. 100% native WordPress-Architektur (Custom Post Types, User Meta, Transients).
 * Version:     1.0.0
 * Author:      Alexander
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ha-achievement-api
 * Domain Path: /languages
 *
 * @package HA_Achievement_API
 */

defined( 'ABSPATH' ) || exit;

/**
 * Initialize Plugin Update Checker for automatic updates from GitHub repository.
 *
 * @return \YahnisElsts\PluginUpdateChecker\v5\Vcs\PluginUpdateChecker|null
 */
function ha_achievement_api_get_updater() {
    static $update_checker = null;
    if ( null !== $update_checker ) {
        return $update_checker;
    }

    $puc_file = plugin_dir_path( __FILE__ ) . 'vendor/plugin-update-checker/plugin-update-checker.php';
    if ( ! file_exists( $puc_file ) ) {
        return null;
    }

    require_once $puc_file;

    $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/mystryx1337/ha-achievement-api/',
        __FILE__,
        'ha-achievement-api'
    );

    // Set default branch to main
    $update_checker->setBranch( 'main' );

    // Set GitHub Access Token (if defined/available)
    $token = defined( 'HA_GITHUB_UPDATE_TOKEN' ) ? HA_GITHUB_UPDATE_TOKEN : '';
    if ( empty( $token ) && defined( 'GITHUB_ACCESS_TOKEN' ) ) {
        $token = GITHUB_ACCESS_TOKEN;
    }
    $token = apply_filters( 'ha_github_update_token', $token, 'ha-achievement-api' );

    if ( ! empty( $token ) ) {
        $update_checker->setAuthentication( $token );
    }

    return $update_checker;
}
ha_achievement_api_get_updater();


// Plugin-Konstanten definieren
define( 'HA_ACHIEVEMENT_VERSION', '1.0.0' );
define( 'HA_ACHIEVEMENT_FILE', __FILE__ );
define( 'HA_ACHIEVEMENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'HA_ACHIEVEMENT_URL', plugin_dir_url( __FILE__ ) );
define( 'HA_ACHIEVEMENT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Hauptklasse des Plugins
 */
final class HA_Achievement_Plugin {

    /**
     * Singleton-Instanz
     *
     * @var HA_Achievement_Plugin|null
     */
    private static $instance = null;

    /**
     * Gibt die Singleton-Instanz zurück.
     *
     * @return HA_Achievement_Plugin
     */
    public static function instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor: Lädt Abhängigkeiten und registriert Hooks.
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Lädt alle benötigten Klassen.
     */
    private function includes() {
        require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-user.php';
        require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-cpt.php';
        require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-api.php';
        require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-verifier.php';
        require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-renderer.php';
        require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-rest.php';
        require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-integrations.php';

        if ( is_admin() ) {
            require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-admin.php';
        }
    }

    /**
     * Registriert WordPress-Hooks.
     */
    private function init_hooks() {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Initialisiere Subsysteme
        HA_Achievement\CPT::init();
        HA_Achievement\API::init();
        HA_Achievement\Renderer::init();
        HA_Achievement\REST::init();
        HA_Achievement\Verifier::init();
        HA_Achievement\Integrations::init();

        if ( is_admin() ) {
            HA_Achievement\Admin::init();
        }
    }

    /**
     * Lädt Sprachdateien für Mehrsprachigkeit.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ha-achievement-api',
            false,
            dirname( HA_ACHIEVEMENT_BASENAME ) . '/languages'
        );
    }

    /**
     * Lädt CSS und JavaScript im Frontend.
     */
    public function enqueue_frontend_assets() {
        wp_register_style(
            'ha-achievement-frontend',
            HA_ACHIEVEMENT_URL . 'assets/css/frontend.css',
            [],
            HA_ACHIEVEMENT_VERSION
        );

        wp_register_script(
            'ha-achievement-celebration',
            HA_ACHIEVEMENT_URL . 'assets/js/celebration.js',
            [],
            HA_ACHIEVEMENT_VERSION,
            true
        );

        wp_register_script(
            'ha-achievement-frontend',
            HA_ACHIEVEMENT_URL . 'assets/js/frontend.js',
            [ 'ha-achievement-celebration' ],
            HA_ACHIEVEMENT_VERSION,
            true
        );

        wp_localize_script( 'ha-achievement-frontend', 'haAchievementConfig', [
            'restUrl'  => esc_url_raw( rest_url( 'ha-achievement/v1/' ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'userId'   => get_current_user_id(),
            'i18n'     => [
                'completed'   => __( 'Erledigt!', 'ha-achievement-api' ),
                'pending'     => __( 'Offen', 'ha-achievement-api' ),
                'solveNow'    => __( 'Jetzt lösen 🚀', 'ha-achievement-api' ),
                'levelUp'     => __( 'Stufenaufstieg! Glückwunsch!', 'ha-achievement-api' ),
                'badgeEarned' => __( 'Neues Abzeichen errungen!', 'ha-achievement-api' ),
                'allTasks'    => __( 'Alle Aufgaben', 'ha-achievement-api' ),
                'onlyPending' => __( 'Nur offene Aufgaben', 'ha-achievement-api' ),
            ],
        ] );
    }

    /**
     * Lädt CSS und JavaScript im WP-Admin.
     */
    public function enqueue_admin_assets( $hook ) {
        // Deaktivierungs-Dialog auf plugins.php laden
        if ( 'plugins.php' === $hook ) {
            $this->enqueue_deactivation_dialog();
            return;
        }

        // Nur auf unseren Adminseiten laden
        if ( strpos( $hook, 'ha-achievement' ) !== false || in_array( get_post_type(), [ 'ha_achievement', 'ha_credential' ], true ) ) {
            wp_enqueue_style(
                'ha-achievement-admin',
                HA_ACHIEVEMENT_URL . 'assets/css/admin.css',
                [],
                HA_ACHIEVEMENT_VERSION
            );

            wp_enqueue_script(
                'ha-achievement-admin',
                HA_ACHIEVEMENT_URL . 'assets/js/admin.js',
                [ 'jquery' ],
                HA_ACHIEVEMENT_VERSION,
                true
            );
        }
    }

    /**
     * Lädt den Deaktivierungs-Dialog auf plugins.php.
     */
    private function enqueue_deactivation_dialog() {
        wp_enqueue_style(
            'ha-achievement-deactivate-dialog',
            HA_ACHIEVEMENT_URL . 'assets/css/deactivation-dialog.css',
            [],
            HA_ACHIEVEMENT_VERSION
        );

        wp_enqueue_script(
            'ha-achievement-deactivate-dialog',
            HA_ACHIEVEMENT_URL . 'assets/js/deactivation-dialog.js',
            [],
            HA_ACHIEVEMENT_VERSION,
            true
        );

        $settings = get_option( 'ha_achievement_settings', [] );

        wp_localize_script(
            'ha-achievement-deactivate-dialog',
            'haAchievementDeactivateConfig',
            [
                'slug'           => 'ha-achievement-api',
                'pluginFile'     => plugin_basename( HA_ACHIEVEMENT_FILE ),
                'currentSetting' => ! empty( $settings['delete_data_on_uninstall'] ) ? 1 : 0,
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'ha_achievement_deactivate_nonce' ),
                'action'         => 'ha_achievement_save_uninstall_setting',
                'i18n'           => [
                    'modalTitle'       => __( 'HA Achievement API: Deaktivierung & Daten', 'ha-achievement-api' ),
                    'closeBtn'         => __( 'Schließen', 'ha-achievement-api' ),
                    'introText'        => __( 'Sie sind dabei, <strong>HA Achievement API</strong> zu deaktivieren. Bitte legen Sie fest, was mit Ihren Gamification-Daten geschehen soll, falls das Plugin später gelöscht wird:', 'ha-achievement-api' ),
                    'keepTitle'        => __( 'Daten in der Datenbank behalten', 'ha-achievement-api' ),
                    'recommendedBadge' => __( 'Empfohlen', 'ha-achievement-api' ),
                    'keepDesc'         => __( 'Alle Benutzer-XP, Stufen, Aufgabenfortschritte, Badges und vergebenen Urkunden bleiben sicher in der Datenbank erhalten. Bei erneuter Aktivierung ist alles sofort wieder da.', 'ha-achievement-api' ),
                    'deleteTitle'      => __( 'Daten bei Löschung restlos entfernen', 'ha-achievement-api' ),
                    'dangerBadge'      => __( 'DSGVO / Gefahr', 'ha-achievement-api' ),
                    'deleteDesc'       => __( 'Achtung: Sollten Sie das Plugin nach der Deaktivierung über WordPress löschen, werden alle Gamification-Daten, XP, Badges und Urkunden unwiderruflich entfernt.', 'ha-achievement-api' ),
                    'hintText'         => __( '💡 <strong>Hinweis:</strong> Diese Einstellung kann auch jederzeit im Reiter <em>Einstellungen</em> der Auszeichnungen geändert werden.', 'ha-achievement-api' ),
                    'cancelBtn'        => __( 'Abbrechen', 'ha-achievement-api' ),
                    'confirmBtn'       => __( 'Speichern & Deaktivieren', 'ha-achievement-api' ),
                    'savingText'       => __( 'Wird gespeichert & deaktiviert…', 'ha-achievement-api' ),
                ],
            ]
        );
    }

    /**
     * Plugin-Aktivierungs-Routine
     */
    public static function activate() {
        // CPTs registrieren und Permalinks flashen
        require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-cpt.php';
        HA_Achievement\CPT::register_post_types();
        HA_Achievement\CPT::register_taxonomies();
        flush_rewrite_rules();

        // Standard-Optionen setzen
        if ( false === get_option( 'ha_achievement_settings' ) ) {
            update_option( 'ha_achievement_settings', [
                'xp_per_subtask'        => 10,
                'xp_per_chapter'        => 50,
                'xp_for_diploma'        => 250,
                'leaderboard_cache_min' => 15,
                'verification_slug'     => 'zertifikat',
                'celebration_effects'   => 1,
            ] );
        }

        // Standard-Badges und Aufgabenkatalog initialisieren
        require_once HA_ACHIEVEMENT_PATH . 'includes/class-ha-achievement-integrations.php';
        HA_Achievement\Integrations::seed_initial_data();
    }

    /**
     * Plugin-Deaktivierungs-Routine
     */
    public static function deactivate() {
        flush_rewrite_rules();
        delete_transient( 'ha_leaderboard_top50' );
        delete_transient( 'ha_leaderboard_top10' );
    }
}

// Hooks für Aktivierung und Deaktivierung
register_activation_hook( __FILE__, [ 'HA_Achievement_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'HA_Achievement_Plugin', 'deactivate' ] );

// Plugin starten
function ha_achievement() {
    return HA_Achievement_Plugin::instance();
}
ha_achievement();
