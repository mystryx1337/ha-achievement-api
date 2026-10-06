<?php
/**
 * Admin-Management & Dashboard für HA Achievement API
 *
 * Verwaltet Menüs, Statistiken, Aufgaben-Inspektion, Benutzer-XP-Korrektur
 * und Einstellungsseiten im WordPress-Backend.
 *
 * @package HA_Achievement_API
 */

namespace HA_Achievement;

defined( 'ABSPATH' ) || exit;

class Admin {

    /**
     * Initialisiert Admin-Hooks.
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_menus' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

        // Post-Actions für manuelle Admin-Eingriffe
        add_action( 'admin_post_ha_achievement_adjust_xp', [ __CLASS__, 'handle_adjust_xp' ] );
        add_action( 'admin_post_ha_achievement_reset_user', [ __CLASS__, 'handle_reset_user' ] );
        add_action( 'admin_post_ha_achievement_flush_cache', [ __CLASS__, 'handle_flush_cache' ] );

        // AJAX Action für Deaktivierungs-Dialog
        add_action( 'wp_ajax_ha_achievement_save_uninstall_setting', [ __CLASS__, 'ajax_save_uninstall_setting' ] );
    }

    /**
     * Registriert das Hauptmenü und alle Untermenüpunkte.
     */
    public static function register_admin_menus() {
        // Hauptmenü mit Auszeichnungs-Icon
        add_menu_page(
            __( 'Auszeichnungen & Gamification', 'ha-achievement-api' ),
            __( 'Auszeichnungen', 'ha-achievement-api' ),
            'manage_options',
            'ha-achievement',
            [ __CLASS__, 'render_dashboard' ],
            'dashicons-awards',
            28
        );

        // 1. Dashboard
        add_submenu_page(
            'ha-achievement',
            __( 'Auszeichnungen &bull; Dashboard', 'ha-achievement-api' ),
            __( '📊 Dashboard', 'ha-achievement-api' ),
            'manage_options',
            'ha-achievement',
            [ __CLASS__, 'render_dashboard' ]
        );

        // 2. Badges (Direktlink auf CPT)
        add_submenu_page(
            'ha-achievement',
            __( 'Errungenschaften & Badges', 'ha-achievement-api' ),
            __( '🎖️ Badges & Medaillen', 'ha-achievement-api' ),
            'manage_options',
            'edit.php?post_type=ha_achievement'
        );

        // 3. Diplome & Zertifikate (Direktlink auf CPT)
        add_submenu_page(
            'ha-achievement',
            __( 'Diplome & Zertifikate', 'ha-achievement-api' ),
            __( '📜 Diplome & Urkunden', 'ha-achievement-api' ),
            'manage_options',
            'edit.php?post_type=ha_credential'
        );

        // 4. Lern-Aufgaben & Missionen
        add_submenu_page(
            'ha-achievement',
            __( 'Lern-Aufgaben & Missionen', 'ha-achievement-api' ),
            __( '📋 Lern-Aufgaben', 'ha-achievement-api' ),
            'manage_options',
            'ha-achievement-tasks',
            [ __CLASS__, 'render_tasks_screen' ]
        );

        // 5. Benutzer & Punktestände
        add_submenu_page(
            'ha-achievement',
            __( 'Benutzer & Punktestände', 'ha-achievement-api' ),
            __( '👥 Benutzer & XP', 'ha-achievement-api' ),
            'manage_options',
            'ha-achievement-users',
            [ __CLASS__, 'render_users_screen' ]
        );

        // 6. Rangliste / Leaderboard
        add_submenu_page(
            'ha-achievement',
            __( 'Rangliste / Leaderboard', 'ha-achievement-api' ),
            __( '🏆 Rangliste', 'ha-achievement-api' ),
            'manage_options',
            'ha-achievement-leaderboard',
            [ __CLASS__, 'render_leaderboard_screen' ]
        );

        // 7. Verbundene Plugins
        add_submenu_page(
            'ha-achievement',
            __( 'Verbundene Plugins', 'ha-achievement-api' ),
            __( '🔌 Verbundene Plugins', 'ha-achievement-api' ),
            'manage_options',
            'ha-achievement-plugins',
            [ __CLASS__, 'render_plugins_screen' ]
        );

        // 8. Einstellungen
        add_submenu_page(
            'ha-achievement',
            __( 'Einstellungen', 'ha-achievement-api' ),
            __( '⚙️ Einstellungen', 'ha-achievement-api' ),
            'manage_options',
            'ha-achievement-settings',
            [ __CLASS__, 'render_settings_screen' ]
        );
    }

    /**
     * Registriert Plugin-Optionen über die WordPress Settings API.
     */
    public static function register_settings() {
        register_setting( 'ha_achievement_settings_group', 'ha_achievement_settings', [
            'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
        ] );
    }

    /**
     * Bereinigt Formulareingaben der Einstellungen.
     */
    public static function sanitize_settings( $input ) {
        $clean['xp_per_subtask']        = absint( $input['xp_per_subtask'] ?? 10 );
        $clean['xp_per_chapter']        = absint( $input['xp_per_chapter'] ?? 50 );
        $clean['xp_for_diploma']        = absint( $input['xp_for_diploma'] ?? 250 );
        $clean['xp_per_level']          = max( 10, absint( $input['xp_per_level'] ?? 100 ) );
        $clean['leaderboard_cache_min'] = max( 1, absint( $input['leaderboard_cache_min'] ?? 15 ) );
        $clean['verification_slug']     = sanitize_title( $input['verification_slug'] ?? 'zertifikat' );
        $clean['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] ) ? 1 : 0;

        // Cache bei Einstellungs-Änderungen leeren
        delete_transient( 'ha_leaderboard_top10' );
        delete_transient( 'ha_leaderboard_top50' );

        return $clean;
    }

    /**
     * Rendert das Haupt-Dashboard.
     */
    public static function render_dashboard() {
        // Kennzahlen ermitteln
        $total_certs = count( get_posts( [
            'post_type'   => 'ha_credential',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ] ) );

        $total_badges = count( get_posts( [
            'post_type'   => 'ha_achievement',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ] ) );

        $registered_tasks = count( API::get_registered_tasks() );

        // Aktive User mit XP zählen
        global $wpdb;
        $active_users = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'ha_total_xp' AND meta_value > 0" );
        $total_xp_sum = (int) $wpdb->get_var( "SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE meta_key = 'ha_total_xp'" );

        // Letzte Zertifikate abrufen
        $recent_certs = get_posts( [
            'post_type'   => 'ha_credential',
            'post_status' => 'publish',
            'numberposts' => 5,
        ] );
        ?>
        <div class="wrap ha-admin-wrap">
            <h1 class="ha-admin-title">
                <span>🏆</span> <?php esc_html_e( 'Auszeichnungen &bull; Dashboard', 'ha-achievement-api' ); ?>
                <span class="ha-version-tag">v<?php echo esc_html( HA_ACHIEVEMENT_VERSION ); ?></span>
            </h1>

            <p class="description">
                <?php esc_html_e( 'Zentrale Schaltzentrale für Gamification, Erfahrungspunkte, Schritt-für-Schritt Missionen und offizielle Abschluss-Urkunden.', 'ha-achievement-api' ); ?>
            </p>

            <!-- Kacheln -->
            <div class="ha-stats-grid">
                <div class="ha-stat-card">
                    <span class="ha-stat-card-icon">⚡</span>
                    <div class="ha-stat-card-data">
                        <h3><?php echo esc_html( number_format_i18n( $total_xp_sum ) ); ?></h3>
                        <p><?php esc_html_e( 'Vergebene Gesamt-XP', 'ha-achievement-api' ); ?></p>
                    </div>
                </div>

                <div class="ha-stat-card">
                    <span class="ha-stat-card-icon">👥</span>
                    <div class="ha-stat-card-data">
                        <h3><?php echo esc_html( number_format_i18n( $active_users ) ); ?></h3>
                        <p><?php esc_html_e( 'Aktive Lernende', 'ha-achievement-api' ); ?></p>
                    </div>
                </div>

                <div class="ha-stat-card">
                    <span class="ha-stat-card-icon">📜</span>
                    <div class="ha-stat-card-data">
                        <h3><?php echo esc_html( number_format_i18n( $total_certs ) ); ?></h3>
                        <p><?php esc_html_e( 'Ausgestellte Diplome', 'ha-achievement-api' ); ?></p>
                    </div>
                </div>

                <div class="ha-stat-card">
                    <span class="ha-stat-card-icon">📋</span>
                    <div class="ha-stat-card-data">
                        <h3><?php echo esc_html( $registered_tasks ); ?></h3>
                        <p><?php esc_html_e( 'Registrierte Lernaufgaben', 'ha-achievement-api' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="ha-dashboard-columns">
                <!-- Spalte 1: Zuletzt vergebene Urkunden -->
                <div class="ha-dash-col">
                    <div class="ha-panel">
                        <h2>📜 <?php esc_html_e( 'Zuletzt ausgestellte Diplome', 'ha-achievement-api' ); ?></h2>
                        <?php if ( empty( $recent_certs ) ) : ?>
                            <p class="description"><?php esc_html_e( 'Bislang wurden noch keine Diplome vergeben.', 'ha-achievement-api' ); ?></p>
                        <?php else : ?>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Code', 'ha-achievement-api' ); ?></th>
                                        <th><?php esc_html_e( 'Teilnehmer', 'ha-achievement-api' ); ?></th>
                                        <th><?php esc_html_e( 'Prädikat', 'ha-achievement-api' ); ?></th>
                                        <th><?php esc_html_e( 'Aktion', 'ha-achievement-api' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $recent_certs as $cert ) : 
                                        $code  = get_post_meta( $cert->ID, '_ha_verification_code', true );
                                        $grade = get_post_meta( $cert->ID, '_ha_honor_grade', true );
                                        $user  = get_userdata( $cert->post_author );
                                    ?>
                                        <tr>
                                            <td><strong><code><?php echo esc_html( $code ?: '-' ); ?></code></strong></td>
                                            <td><?php echo esc_html( $user ? $user->display_name : 'Unbekannt' ); ?></td>
                                            <td><span style="color:#d97706; font-weight:600;"><?php echo esc_html( $grade ?: '-' ); ?></span></td>
                                            <td>
                                                <a href="<?php echo esc_url( get_permalink( $cert->ID ) ); ?>" target="_blank" class="button button-small">
                                                    <?php esc_html_e( 'Prüfen ↗', 'ha-achievement-api' ); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Spalte 2: Verfügbare Shortcodes & Integration -->
                <div class="ha-dash-col">
                    <div class="ha-panel">
                        <h2>🧩 <?php esc_html_e( 'Verfügbare Frontend-Shortcodes', 'ha-achievement-api' ); ?></h2>
                        <ul class="ha-shortcode-list">
                            <li>
                                <strong><code>[ha_achievement_learning_dashboard]</code></strong>
                                <p><?php esc_html_e( 'Interaktives Aufgaben-Dashboard: Zeigt erfüllte und offene Schritte mit direkten Aktions-Links.', 'ha-achievement-api' ); ?></p>
                            </li>
                            <li>
                                <strong><code>[ha_achievement_profile_card]</code></strong>
                                <p><?php esc_html_e( 'Profilkarte mit Avatar, Rangtitel, Stufen-Fortschrittsbalken und Statistiken.', 'ha-achievement-api' ); ?></p>
                            </li>
                            <li>
                                <strong><code>[ha_achievement_badges]</code></strong>
                                <p><?php esc_html_e( 'Medaillen-Vitrine: Freigeschaltete Badges glänzend, gesperrte transparent mit Schlosssymbol.', 'ha-achievement-api' ); ?></p>
                            </li>
                            <li>
                                <strong><code>[ha_achievement_leaderboard]</code></strong>
                                <p><?php esc_html_e( 'Bestenliste mit Gold-, Silber- und Bronze-Podest sowie tabellarischer Platzierung.', 'ha-achievement-api' ); ?></p>
                            </li>
                            <li>
                                <strong><code>[ha_achievement_certificate_verify]</code></strong>
                                <p><?php esc_html_e( 'Öffentliche Prüfmaske zur Eingabe eines Codes oder Hash-Werts.', 'ha-achievement-api' ); ?></p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert die Aufgaben-Katalog-Übersicht.
     */
    public static function render_tasks_screen() {
        $catalog = API::get_registered_tasks();
        ?>
        <div class="wrap ha-admin-wrap">
            <h1>📋 <?php esc_html_e( 'Registrierte Lern-Aufgaben & Missionen', 'ha-achievement-api' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Übersicht aller Aufgaben, die von ha-bpmn-manager oder anderen Erweiterungen angemeldet wurden.', 'ha-achievement-api' ); ?>
            </p>

            <table class="widefat striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php esc_html_e( 'Nr.', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Kapitel / Modul', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Aufgabe & Anweisung', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Praxistipp / Hinweis', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Belohnung', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Quelle', 'ha-achievement-api' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $catalog ) ) : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'Noch keine Aufgaben registriert.', 'ha-achievement-api' ); ?></td></tr>
                    <?php else : 
                        $i = 1;
                        foreach ( $catalog as $t ) : ?>
                            <tr>
                                <td><?php echo esc_html( $i++ ); ?></td>
                                <td>
                                    <span><?php echo esc_html( $t['chapter_icon'] ?? '📋' ); ?></span>
                                    <strong><?php echo esc_html( $t['chapter_title'] ?: $t['chapter_key'] ); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $t['title'] ); ?></strong><br />
                                    <code><?php echo esc_html( $t['task_key'] ); ?></code>
                                </td>
                                <td><span class="description"><?php echo esc_html( $t['hint'] ?: '-' ); ?></span></td>
                                <td><strong style="color: #4338ca;">+<?php echo esc_html( $t['xp_value'] ); ?> XP</strong></td>
                                <td><span class="badge"><?php echo esc_html( $t['source'] ); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Rendert die Benutzer- & XP-Verwaltung.
     */
    public static function render_users_screen() {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

        $args = [
            'number'  => 50,
            'orderby' => 'display_name',
        ];

        if ( ! empty( $search ) ) {
            $args['search'] = '*' . $search . '*';
        }

        $users = get_users( $args );
        ?>
        <div class="wrap ha-admin-wrap">
            <h1>👥 <?php esc_html_e( 'Benutzer & Punktestände', 'ha-achievement-api' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Übersicht aller Lernenden mit ihren aktuellen XP, Stufen und Schnellkorrektur-Optionen.', 'ha-achievement-api' ); ?></p>

            <form method="get" style="margin: 15px 0;">
                <input type="hidden" name="page" value="ha-achievement-users" />
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Benutzer suchen...', 'ha-achievement-api' ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( 'Suchen', 'ha-achievement-api' ); ?></button>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Benutzer', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Stufe & Rang', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Gesamt-XP', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Erledigte Aufgaben', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Badges', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Aktionen (Admin)', 'ha-achievement-api' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $users as $u ) : 
                        $profile = API::get_user_profile( $u->ID );
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $u->display_name ); ?></strong><br />
                                <span class="description"><?php echo esc_html( $u->user_email ); ?></span>
                            </td>
                            <td>
                                <span class="ha-level-tag">Lvl <?php echo esc_html( $profile['level'] ); ?></span>
                                <span><?php echo esc_html( $profile['rank_title'] ); ?></span>
                            </td>
                            <td><strong style="font-size: 1.1em; color: #3730a3;"><?php echo esc_html( $profile['total_xp'] ); ?> XP</strong></td>
                            <td><?php echo esc_html( $profile['completed_tasks'] ); ?></td>
                            <td><?php echo esc_html( $profile['unlocked_badges'] ); ?></td>
                            <td>
                                <!-- XP anpassen Formular -->
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right: 10px;">
                                    <?php wp_nonce_field( 'ha_adjust_xp_nonce' ); ?>
                                    <input type="hidden" name="action" value="ha_achievement_adjust_xp" />
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>" />
                                    <input type="number" name="amount" value="50" step="10" style="width: 70px;" />
                                    <button type="submit" class="button button-small" title="<?php esc_attr_e( 'XP hinzufügen', 'ha-achievement-api' ); ?>">+ XP</button>
                                </form>

                                <!-- Reset Formular -->
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;" onsubmit="return confirm('<?php esc_attr_e( 'Möchtest du die Gamification-Daten dieses Nutzers wirklich komplett zurücksetzen?', 'ha-achievement-api' ); ?>');">
                                    <?php wp_nonce_field( 'ha_reset_user_nonce' ); ?>
                                    <input type="hidden" name="action" value="ha_achievement_reset_user" />
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>" />
                                    <button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Reset', 'ha-achievement-api' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Rendert die Leaderboard-Ansicht im Admin mit Cache-Steuerung.
     */
    public static function render_leaderboard_screen() {
        $board = API::get_leaderboard( 20 );
        ?>
        <div class="wrap ha-admin-wrap">
            <h1>🏆 <?php esc_html_e( 'Rangliste / Leaderboard', 'ha-achievement-api' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Live-Vorschau der Bestenliste für die Top 20 Lernenden.', 'ha-achievement-api' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 15px 0;">
                <?php wp_nonce_field( 'ha_flush_cache_nonce' ); ?>
                <input type="hidden" name="action" value="ha_achievement_flush_cache" />
                <button type="submit" class="button button-secondary">🔄 <?php esc_html_e( 'Cache sofort leeren & aktualisieren', 'ha-achievement-api' ); ?></button>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e( 'Rang', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Lernender', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Stufe & Titel', 'ha-achievement-api' ); ?></th>
                        <th style="text-align:right;"><?php esc_html_e( 'Gesamt-Punkte (XP)', 'ha-achievement-api' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $board ) ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'Keine Einträge vorhanden.', 'ha-achievement-api' ); ?></td></tr>
                    <?php else : 
                        foreach ( $board as $row ) : ?>
                            <tr>
                                <td>
                                    <?php 
                                    $medals = [ 1 => '🥇', 2 => '🥈', 3 => '🥉' ];
                                    echo $medals[ $row['rank'] ] ?? '#' . $row['rank'];
                                    ?>
                                </td>
                                <td>
                                    <img src="<?php echo esc_url( $row['avatar_url'] ); ?>" style="width: 28px; height: 28px; border-radius: 50%; vertical-align: middle; margin-right: 8px;" />
                                    <strong><?php echo esc_html( $row['display_name'] ); ?></strong>
                                </td>
                                <td>
                                    <span class="ha-level-tag">Lvl <?php echo esc_html( $row['level'] ); ?></span>
                                    <span><?php echo esc_html( $row['rank_title'] ); ?></span>
                                </td>
                                <td style="text-align:right;">
                                    <strong style="color: #4338ca;"><?php echo esc_html( number_format_i18n( $row['total_xp'] ) ); ?> XP</strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Rendert die Übersicht der verbundenen Plugins.
     */
    public static function render_plugins_screen() {
        $plugins = Integrations::get_connected_plugins();
        ?>
        <div class="wrap ha-admin-wrap">
            <h1>🔌 <?php esc_html_e( 'Verbundene Plugins & Quellen', 'ha-achievement-api' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Status aller installierten Module, die Lernaufgaben, Badges und Urkunden in dieses System einspeisen.', 'ha-achievement-api' ); ?></p>

            <div class="ha-plugins-grid" style="margin-top: 20px;">
                <?php foreach ( $plugins as $p ) : 
                    $is_active = ( 'active' === $p['status'] );
                ?>
                    <div class="ha-panel" style="border-left: 4px solid <?php echo $is_active ? '#22c55e' : '#94a3b8'; ?>;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <h2 style="margin:0;"><?php echo esc_html( $p['name'] ); ?></h2>
                            <span style="background:<?php echo $is_active ? '#dcfce7' : '#f1f5f9'; ?>; color:<?php echo $is_active ? '#15803d' : '#475569'; ?>; padding:4px 12px; border-radius:20px; font-weight:bold; font-size:12px;">
                                <?php echo $is_active ? '● ' . esc_html__( 'Aktiv & Verbunden', 'ha-achievement-api' ) : esc_html__( 'Nicht aktiv', 'ha-achievement-api' ); ?>
                            </span>
                        </div>
                        <p style="margin: 10px 0;"><?php echo esc_html( $p['description'] ); ?></p>
                        
                        <div style="background: #f8fafc; padding: 12px; border-radius: 6px; margin-top: 15px;">
                            <strong><?php esc_html_e( 'Registrierte Aufgaben:', 'ha-achievement-api' ); ?></strong> <?php echo esc_html( $p['registered_tasks'] ); ?> Aufgabenkatalog-Einträge
                            <ul style="margin: 8px 0 0 18px; list-style-type: disc;">
                                <?php foreach ( $p['features'] as $feat ) : ?>
                                    <li><?php echo esc_html( $feat ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Rendert die Einstellungsseite.
     */
    public static function render_settings_screen() {
        $settings = get_option( 'ha_achievement_settings', [] );
        ?>
        <div class="wrap ha-admin-wrap">
            <h1>⚙️ <?php esc_html_e( 'Auszeichnungen &bull; Einstellungen', 'ha-achievement-api' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Konfiguriere XP-Belohnungen, Cache-Dauer und Permalinks für Urkunden.', 'ha-achievement-api' ); ?></p>

            <form method="post" action="options.php" style="margin-top: 20px;">
                <?php
                settings_fields( 'ha_achievement_settings_group' );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="xp_per_subtask"><?php esc_html_e( 'Standard-XP pro Teilaufgabe', 'ha-achievement-api' ); ?></label></th>
                        <td>
                            <input type="number" id="xp_per_subtask" name="ha_achievement_settings[xp_per_subtask]" value="<?php echo esc_attr( $settings['xp_per_subtask'] ?? 10 ); ?>" min="0" step="5" class="small-text" /> XP
                            <p class="description"><?php esc_html_e( 'Punkte, die ein Benutzer beim Erledigen einer einzelnen Modellierungs- oder Quizaufgabe erhält.', 'ha-achievement-api' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="xp_for_diploma"><?php esc_html_e( 'Bonus-XP bei Diplom-Abschluss', 'ha-achievement-api' ); ?></label></th>
                        <td>
                            <input type="number" id="xp_for_diploma" name="ha_achievement_settings[xp_for_diploma]" value="<?php echo esc_attr( $settings['xp_for_diploma'] ?? 250 ); ?>" min="0" step="25" class="small-text" /> XP
                            <p class="description"><?php esc_html_e( 'Einmaliger großer XP-Schub beim erfolgreichen Bestehen aller Kapitel und Ausstellung des Diploms.', 'ha-achievement-api' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="xp_per_level"><?php esc_html_e( 'Punkte (XP) pro Stufe (linear)', 'ha-achievement-api' ); ?></label></th>
                        <td>
                            <input type="number" id="xp_per_level" name="ha_achievement_settings[xp_per_level]" value="<?php echo esc_attr( $settings['xp_per_level'] ?? 100 ); ?>" min="10" step="10" class="small-text" /> XP
                            <p class="description"><?php esc_html_e( 'Punktebedarf für jeden Stufenaufstieg (Standard: 100 XP). Ermöglicht ein offenes, modulares Stufensystem, das dynamisch mit allen angeschlossenen Plugins mitwächst.', 'ha-achievement-api' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="verification_slug"><?php esc_html_e( 'URL-Präfix für Zertifikate', 'ha-achievement-api' ); ?></label></th>
                        <td>
                            <code><?php echo esc_url( home_url( '/' ) ); ?></code>
                            <input type="text" id="verification_slug" name="ha_achievement_settings[verification_slug]" value="<?php echo esc_attr( $settings['verification_slug'] ?? 'zertifikat' ); ?>" class="regular-text" style="width: 150px;" />
                            <code>/HA-BPMN-8X9K2M/</code>
                            <p class="description"><?php esc_html_e( 'Slug für die öffentlich erreichbare Verifikations-URL ausgestellter Diplome.', 'ha-achievement-api' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="leaderboard_cache_min"><?php esc_html_e( 'Cache-Dauer der Rangliste', 'ha-achievement-api' ); ?></label></th>
                        <td>
                            <input type="number" id="leaderboard_cache_min" name="ha_achievement_settings[leaderboard_cache_min]" value="<?php echo esc_attr( $settings['leaderboard_cache_min'] ?? 15 ); ?>" min="1" step="1" class="small-text" /> <?php esc_html_e( 'Minuten', 'ha-achievement-api' ); ?>
                            <p class="description"><?php esc_html_e( 'Verhindert Datenbanklast bei vielen gleichzeitigen Seitenaufrufen.', 'ha-achievement-api' ); ?></p>
                        </td>
                    </tr>
                </table>

                <div class="ha-admin-card ha-danger-zone-card" style="margin-top: 24px; margin-bottom: 24px; border: 1px solid #fecaca; background: #ffffff; border-radius: 8px; overflow: hidden; max-width: 900px;">
                    <div style="border-bottom: 1px solid #fee2e2; background: #fef2f2; padding: 14px 20px;">
                        <h3 style="color: #991b1b; display: flex; align-items: center; gap: 8px; margin: 0 0 4px; font-size: 15px;">
                            <span>⚠️</span>
                            <?php esc_html_e( 'Gefahrenzone: Deinstallation & Datenbereinigung', 'ha-achievement-api' ); ?>
                        </h3>
                        <p style="color: #7f1d1d; margin: 0; font-size: 13px;">
                            <?php esc_html_e( 'Bestimme das Verhalten der Datenbankbereinigung beim endgültigen Löschen des Plugins.', 'ha-achievement-api' ); ?>
                        </p>
                    </div>
                    <div style="padding: 16px 20px;">
                        <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                            <input type="checkbox" name="ha_achievement_settings[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> style="margin-top: 3px;" />
                            <div>
                                <strong style="color: #991b1b; font-size: 14px; display: block; margin-bottom: 4px;">
                                    <?php esc_html_e( 'Alle Daten (XP, Level, Aufgaben-Fortschritte, Badges, Zertifikate) beim Löschen des Plugins unwiderruflich entfernen', 'ha-achievement-api' ); ?>
                                </strong>
                                <p style="color: #475569; font-size: 13px; line-height: 1.5; margin: 0 0 8px;">
                                    <strong><?php esc_html_e( '🛡️ Standardmäßig sicher (Empfohlen):', 'ha-achievement-api' ); ?></strong>
                                    <?php esc_html_e( 'Solange diese Option deaktiviert ist, bleiben alle Punktestände, Fortschritte der Lernenden und vergebenen Urkunden in der Datenbank sicher gespeichert. Das Plugin kann gefahrlos deaktiviert, aktualisiert oder vorübergehend deinstalliert werden.', 'ha-achievement-api' ); ?>
                                </p>
                                <p style="color: #b91c1c; font-size: 12.5px; line-height: 1.4; margin: 0;">
                                    <strong><?php esc_html_e( '⚠️ Nur bei endgültiger Datenbereinigung (DSGVO):', 'ha-achievement-api' ); ?></strong>
                                    <?php esc_html_e( 'Aktiviere diese Option nur dann, wenn du das System dauerhaft von deiner Website entfernst und alle Leistungsdaten aller Benutzer restlos löschen möchtest.', 'ha-achievement-api' ); ?>
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <?php submit_button( __( 'Einstellungen speichern', 'ha-achievement-api' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Verarbeitet manuelle XP-Gutschrift durch den Admin.
     */
    public static function handle_adjust_xp() {
        check_admin_referer( 'ha_adjust_xp_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        $user_id = absint( $_POST['user_id'] ?? 0 );
        $amount  = (int) ( $_POST['amount'] ?? 0 );

        if ( $user_id && $amount ) {
            API::award_xp( $user_id, $amount, 'admin', __( 'Manuelle Anpassung durch Administrator', 'ha-achievement-api' ) );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'ha-achievement-users', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Setzt den Lernfortschritt eines Benutzers zurück.
     */
    public static function handle_reset_user() {
        check_admin_referer( 'ha_reset_user_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        $user_id = absint( $_POST['user_id'] ?? 0 );
        if ( $user_id ) {
            User::reset_progress( $user_id );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'ha-achievement-users', 'reset' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Leert den Transient-Cache der Rangliste manuell.
     */
    public static function handle_flush_cache() {
        check_admin_referer( 'ha_flush_cache_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        delete_transient( 'ha_leaderboard_all_10' );
        delete_transient( 'ha_leaderboard_all_20' );
        delete_transient( 'ha_leaderboard_all_50' );
        delete_transient( 'ha_leaderboard_top10' );
        delete_transient( 'ha_leaderboard_top50' );

        wp_safe_redirect( add_query_arg( [ 'page' => 'ha-achievement-leaderboard', 'flushed' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * AJAX-Handler zum Speichern der Deinstallations-Einstellung aus dem Deaktivierungs-Dialog.
     */
    public static function ajax_save_uninstall_setting() {
        check_ajax_referer( 'ha_achievement_deactivate_nonce', 'nonce' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'ha-achievement-api' ) ], 403 );
        }

        $delete_data = ! empty( $_POST['delete_data'] ) ? 1 : 0;
        $settings = get_option( 'ha_achievement_settings', [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        $settings['delete_data_on_uninstall'] = $delete_data;
        update_option( 'ha_achievement_settings', $settings );

        wp_send_json_success( [ 'saved' => $delete_data ] );
    }
}
