<?php
/**
 * Zentrale Service- und Datenbankschnittstelle für HA Achievement API
 *
 * Stellt die Hauptklasse \HA_Achievement\API bereit, über die ha-bpmn-manager
 * und beliebige Dritt-Plugins Punkte gutschreiben, Aufgaben verwalten,
 * Urkunden ausstellen und Bestenlisten abrufen können.
 *
 * @package HA_Achievement_API
 */

namespace HA_Achievement;

defined( 'ABSPATH' ) || exit;

class API {

    /**
     * Initialisiert WordPress-Aktions- und Filter-Hooks.
     */
    public static function init() {
        add_action( 'ha_achievement_award_xp', [ __CLASS__, 'award_xp' ], 10, 5 );
        add_action( 'ha_achievement_complete_task', [ __CLASS__, 'complete_task' ], 10, 4 );
        add_action( 'ha_achievement_issue_credential', [ __CLASS__, 'issue_credential' ], 10, 4 );
        add_action( 'ha_achievement_register_tasks', [ __CLASS__, 'register_tasks' ], 10, 2 );
    }

    /**
     * Schreibt einem Benutzer Erfahrungspunkte (XP) gut.
     *
     * @param int    $user_id Benutzer-ID
     * @param int    $xp      Punktzahl
     * @param string $source  Quell-Plugin (z. B. 'ha-bpmn-manager')
     * @param string $reason  Begründung (z. B. 'Kapitel 1 gelöst')
     * @param array  $meta    Optionale Zusatzparameter
     * @return array Ergebnis mit neuem Level, Rangtitel und XP
     */
    public static function award_xp( int $user_id, int $xp, string $source = 'core', string $reason = '', array $meta = [] ): array {
        return User::add_xp( $user_id, $xp, $source, $reason, $meta );
    }

    /**
     * Hakt eine spezifische Teilaufgabe für einen Benutzer als erledigt ab.
     *
     * @param int    $user_id  Benutzer-ID
     * @param string $task_key Aufgaben-Schlüssel (z. B. 'c1_toggle_sim')
     * @param string $source   Quell-Plugin
     * @param array  $meta     Zusatzdaten
     * @return bool True bei erfolgreichem Abschluss
     */
    public static function complete_task( int $user_id, string $task_key, string $source = 'ha-bpmn-manager', array $meta = [] ): bool {
        $completed = User::complete_subtask( $user_id, $task_key, $source, $meta );

        if ( $completed ) {
            // Prüfe, ob damit verknüpfte Badges freigeschaltet werden
            self::evaluate_badge_rules( $user_id );
        }

        return $completed;
    }

    /**
     * Registriert oder aktualisiert einen Aufgabenkatalog (z. B. 27 BPMN-Quest-Subtasks).
     *
     * @param string $source Quell-Plugin (z. B. 'ha-bpmn-manager')
     * @param array  $tasks  Array von Aufgaben-Definitionen
     * @return bool True bei Erfolg
     */
    public static function register_tasks( string $source, array $tasks ): bool {
        if ( empty( $tasks ) ) {
            return false;
        }

        $catalog = (array) get_option( 'ha_achievement_task_catalog', [] );
        if ( ! isset( $catalog[ $source ] ) ) {
            $catalog[ $source ] = [];
        }

        foreach ( $tasks as $task ) {
            $key = sanitize_key( $task['id'] ?? $task['task_key'] ?? '' );
            if ( empty( $key ) ) {
                continue;
            }

            $catalog[ $source ][ $key ] = [
                'task_key'      => $key,
                'source'        => $source,
                'chapter_key'   => sanitize_key( $task['chapter_key'] ?? 'general' ),
                'chapter_title' => sanitize_text_field( $task['chapter_title'] ?? '' ),
                'chapter_icon'  => sanitize_text_field( $task['chapter_icon'] ?? '📋' ),
                'title'         => sanitize_text_field( $task['title'] ?? $task['text'] ?? $key ),
                'hint'          => sanitize_text_field( $task['hint'] ?? '' ),
                'action_url'    => esc_url_raw( $task['action_url'] ?? '' ),
                'order_num'     => (int) ( $task['order_num'] ?? 0 ),
                'xp_value'      => (int) ( $task['xp_value'] ?? 10 ),
            ];
        }

        return update_option( 'ha_achievement_task_catalog', $catalog );
    }

    /**
     * Ruft alle registrierten Aufgaben ab.
     *
     * @param string|null $source Optionaler Filter nach Quell-Plugin
     * @return array
     */
    public static function get_registered_tasks( ?string $source = null ): array {
        $catalog = (array) get_option( 'ha_achievement_task_catalog', [] );
        $catalog = apply_filters( 'ha_achievement_registered_tasks', $catalog );

        if ( $source ) {
            return $catalog[ $source ] ?? [];
        }

        $flat = [];
        foreach ( $catalog as $src => $src_tasks ) {
            foreach ( $src_tasks as $k => $t ) {
                $flat[ $k ] = $t;
            }
        }
        return $flat;
    }

    /**
     * Liefert alle Aufgaben (sowohl erledigte als auch noch offene) für einen Benutzer.
     * Ideal für das Missions-Dashboard-Widget!
     *
     * @param int         $user_id Benutzer-ID
     * @param string|null $source  Optionaler Quell-Filter
     * @return array Strukturierte Aufgabenliste mit Status
     */
    public static function get_user_tasks( int $user_id, ?string $source = null ): array {
        $registered   = self::get_registered_tasks( $source );
        $user_progress = User::get_user_tasks( $user_id );

        $result = [];
        foreach ( $registered as $key => $def ) {
            $is_done = ! empty( $user_progress[ $key ]['completed'] );
            $done_at = $is_done ? ( $user_progress[ $key ]['completed_at'] ?? 0 ) : 0;

            $result[ $key ] = array_merge( $def, [
                'is_completed' => $is_done,
                'completed_at' => $done_at,
                'status'       => $is_done ? 'completed' : 'pending',
            ] );
        }

        return $result;
    }

    /**
     * Stellt ein neues verifizierbares Diplom als CPT 'ha_credential' aus.
     *
     * @param int    $user_id Benutzer-ID
     * @param string $source  Quell-Plugin (z. B. 'ha-bpmn-manager')
     * @param string $type    Zertifikatstyp (z. B. 'bpmn_grandmaster')
     * @param array  $data    Zertifikatsdaten (title, honor_grade, total_xp, svg, etc.)
     * @return string Generierter Verifizierungs-Code (z. B. 'HA-BPMN-8X9K2M')
     */
    public static function issue_credential( int $user_id, string $source, string $type, array $data ): string {
        $prefix = ( 'ha-bpmn-manager' === $source ) ? 'BPMN' : strtoupper( substr( sanitize_key( $source ), 0, 4 ) );
        $code = Verifier::generate_code( $prefix );

        $user = get_userdata( $user_id );
        $user_name = $user ? $user->display_name : 'Teilnehmer';

        $title       = ! empty( $data['title'] ) ? sanitize_text_field( $data['title'] ) : sprintf( 'Diplom: %s', $user_name );
        $honor_grade = ! empty( $data['honor_grade'] ) ? sanitize_text_field( $data['honor_grade'] ) : 'CUM LAUDE';
        $total_xp    = ! empty( $data['total_xp'] ) ? (int) $data['total_xp'] : User::get_xp( $user_id );
        $issued_at   = current_time( 'mysql' );
        $svg         = ! empty( $data['svg'] ) ? wp_unslash( $data['svg'] ) : '';

        // Berechne SHA-256 Prüfwert
        $hash = Verifier::compute_hash( $user_id, $code, $honor_grade, $total_xp, $issued_at );

        // Erstelle Post in CPT ha_credential
        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_name'    => sanitize_title( $code ),
            'post_author'  => $user_id,
            'post_type'    => 'ha_credential',
            'post_status'  => 'publish',
        ] );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return '';
        }

        // Post-Metadaten speichern
        update_post_meta( $post_id, '_ha_verification_code', $code );
        update_post_meta( $post_id, '_ha_credential_hash', $hash );
        update_post_meta( $post_id, '_ha_source', sanitize_text_field( $source ) );
        update_post_meta( $post_id, '_ha_credential_type', sanitize_key( $type ) );
        update_post_meta( $post_id, '_ha_honor_grade', $honor_grade );
        update_post_meta( $post_id, '_ha_total_xp', $total_xp );
        update_post_meta( $post_id, '_ha_issued_at', $issued_at );
        update_post_meta( $post_id, '_ha_is_revoked', '0' );

        if ( ! empty( $svg ) ) {
            update_post_meta( $post_id, '_ha_certificate_svg', $svg );
        }

        if ( ! empty( $data['metadata'] ) ) {
            update_post_meta( $post_id, '_ha_metadata', wp_json_encode( $data['metadata'] ) );
        }

        // Diplom-Bonus-XP gutschreiben
        $settings = get_option( 'ha_achievement_settings', [] );
        $diploma_xp = isset( $settings['xp_for_diploma'] ) ? (int) $settings['xp_for_diploma'] : 250;
        if ( $diploma_xp > 0 ) {
            User::add_xp( $user_id, $diploma_xp, $source, sprintf( __( 'Offizielles Diplom verliehen: %s', 'ha-achievement-api' ), $title ) );
        }

        // Aktivitäts-Eintrag
        User::log_activity( $user_id, [
            'type'        => 'credential_issued',
            'code'        => $code,
            'title'       => $title,
            'honor_grade' => $honor_grade,
            'source'      => $source,
            'time'        => time(),
        ] );

        do_action( 'ha_achievement_credential_issued', $post_id, $code, $user_id, $data );

        return $code;
    }

    /**
     * Prüft einen Urkunden-Code oder Hash auf Echtheit und Gültigkeit.
     *
     * @param string $code_or_hash Verifikations-Code oder Hash
     * @return array
     */
    public static function verify_credential( string $code_or_hash ): array {
        return Verifier::verify( $code_or_hash );
    }

    /**
     * Liefert Gesamtrang, XP, Stufe und Badges eines Benutzers.
     *
     * @param int $user_id Benutzer-ID
     * @return array
     */
    public static function get_user_profile( int $user_id ): array {
        return User::get_profile( $user_id );
    }

    /**
     * Liefert die Bestenliste (Highscore-Tabelle) aus dem Transient-Cache.
     *
     * @param int    $limit  Anzahl der Plätze (z. B. 10 oder 50)
     * @param string $period Zeitraum ('all', 'month', 'week')
     * @return array
     */
    public static function get_leaderboard( int $limit = 10, string $period = 'all' ): array {
        $transient_key = sprintf( 'ha_leaderboard_%s_%d', sanitize_key( $period ), $limit );
        $cached = get_transient( $transient_key );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $users = get_users( [
            'meta_key'     => 'ha_total_xp',
            'orderby'      => 'meta_value_num',
            'order'        => 'DESC',
            'number'       => $limit,
            'fields'       => [ 'ID', 'display_name', 'user_email' ],
        ] );

        $leaderboard = [];
        $rank = 1;

        foreach ( $users as $u ) {
            $xp    = User::get_xp( $u->ID );
            if ( $xp <= 0 ) {
                continue; // Nur Nutzer mit mindestens 1 Punkt aufnehmen
            }

            $level = (int) get_user_meta( $u->ID, 'ha_level', true ) ?: User::calculate_level( $xp );
            $title = (string) get_user_meta( $u->ID, 'ha_rank_title', true ) ?: User::get_rank_title( $level );

            $leaderboard[] = [
                'rank'         => $rank++,
                'user_id'      => $u->ID,
                'display_name' => $u->display_name,
                'avatar_url'   => get_avatar_url( $u->ID, [ 'size' => 64 ] ),
                'total_xp'     => $xp,
                'level'        => $level,
                'rank_title'   => $title,
            ];
        }

        $settings = get_option( 'ha_achievement_settings', [] );
        $cache_min = isset( $settings['leaderboard_cache_min'] ) ? (int) $settings['leaderboard_cache_min'] : 15;
        set_transient( $transient_key, $leaderboard, max( 60, $cache_min * MINUTE_IN_SECONDS ) );

        return $leaderboard;
    }

    /**
     * Prüft serverseitig alle Badges auf Erfüllung ihrer Kriterien-Regeln.
     *
     * @param int $user_id Benutzer-ID
     */
    private static function evaluate_badge_rules( int $user_id ) {
        $badges = get_posts( [
            'post_type'   => 'ha_achievement',
            'post_status' => 'publish',
            'numberposts' => -1,
        ] );

        $user_tasks = User::get_user_tasks( $user_id );

        foreach ( $badges as $badge ) {
            $slug = get_post_meta( $badge->ID, '_ha_slug', true ) ?: sanitize_title( $badge->post_title );
            if ( User::has_achievement( $user_id, $slug ) ) {
                continue; // Bereits freigeschaltet
            }

            $rules_raw = get_post_meta( $badge->ID, '_ha_rules', true );
            if ( empty( $rules_raw ) ) {
                continue;
            }

            $rules = json_decode( $rules_raw, true );
            if ( ! is_array( $rules ) ) {
                continue;
            }

            // Kriterium: required_tasks
            if ( ! empty( $rules['required_tasks'] ) && is_array( $rules['required_tasks'] ) ) {
                $all_done = true;
                foreach ( $rules['required_tasks'] as $req_task ) {
                    if ( empty( $user_tasks[ $req_task ]['completed'] ) ) {
                        $all_done = false;
                        break;
                    }
                }

                if ( $all_done ) {
                    $xp = (int) get_post_meta( $badge->ID, '_ha_xp_reward', true );
                    $source = get_post_meta( $badge->ID, '_ha_source', true ) ?: 'ha-bpmn-manager';
                    User::unlock_achievement( $user_id, $slug, $xp, $source );
                }
            }
        }
    }
}
