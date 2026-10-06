<?php
/**
 * Benutzer-Fortschritt & Meta-Verwaltung für HA Achievement API
 *
 * Verwaltet XP, Stufen, Lernserien (Streaks), gelöste Teilaufgaben und Abzeichen
 * über Standard WordPress User Meta.
 *
 * @package HA_Achievement_API
 */

namespace HA_Achievement;

defined( 'ABSPATH' ) || exit;

class User {

    /**
     * Standard-Level-Schwellenwerte (Stufe => Mindest-XP)
     *
     * @var array<int, int>
     */
    /**
     * Standard-Rangtitel für ein offenes, domänenunabhängiges Stufensystem.
     * Universell passend für alle angeschlossenen Plugins (BPMN, Lernmodule, Kurse etc.).
     *
     * @var array<int, string>
     */
    private static $default_titles = [
        1   => 'Novize',
        2   => 'Einsteiger',
        3   => 'Entdecker',
        4   => 'Lernender',
        5   => 'Anwärter',
        6   => 'Praktikant',
        7   => 'Assistent',
        8   => 'Anwender',
        9   => 'Wissender',
        10  => 'Kenner',
        12  => 'Fortgeschrittener',
        15  => 'Praktiker',
        18  => 'Routinier',
        20  => 'Könner',
        25  => 'Spezialist',
        30  => 'Fachanwender',
        35  => 'Experte',
        40  => 'Senior Experte',
        45  => 'Stratege',
        50  => 'Master',
        60  => 'Großmeister',
        70  => 'Koryphäe',
        80  => 'Vordenker',
        90  => 'Souverän',
        100 => 'Legende 👑',
    ];

    /**
     * Ermittelt die Schrittweite für Stufenaufstiege (standardmäßig linear 100 XP).
     *
     * @return int XP pro Stufe
     */
    public static function get_xp_per_level(): int {
        $settings = get_option( 'ha_achievement_settings', [] );
        $step     = isset( $settings['xp_per_level'] ) ? (int) $settings['xp_per_level'] : 100;
        return max( 10, apply_filters( 'ha_achievement_xp_per_level', $step ) );
    }

    /**
     * Ermittelt die Stufe anhand der Gesamt-XP.
     * Verwendet die lineare Formel: Stufe = floor(XP / step) + 1.
     *
     * @param int $xp Gesamtpunkte
     * @return int Erreichte Stufe (mindestens 1)
     */
    public static function calculate_level( int $xp ): int {
        // Fall 1: Falls Filter-Hooks benutzerdefinierte Schwellenwerte übergeben
        $custom_thresholds = apply_filters( 'ha_achievement_level_thresholds', null );
        if ( is_array( $custom_thresholds ) && ! empty( $custom_thresholds ) ) {
            krsort( $custom_thresholds );
            foreach ( $custom_thresholds as $level => $min_xp ) {
                if ( $xp >= $min_xp ) {
                    return (int) $level;
                }
            }
            return 1;
        }

        // Fall 2: Lineare Berechnung (z. B. 100 XP pro Stufe)
        $step = self::get_xp_per_level();
        return max( 1, (int) floor( $xp / $step ) + 1 );
    }

    /**
     * Liefert den passenden Rangtitel für eine Stufe.
     * Universell und domänenunabhängig.
     *
     * @param int $level Stufe
     * @return string Rangtitel
     */
    public static function get_rank_title( int $level ): string {
        $titles = apply_filters( 'ha_achievement_rank_titles', self::$default_titles );
        if ( isset( $titles[ $level ] ) ) {
            return $titles[ $level ];
        }

        // Finde den nächstgelegenen kleineren oder gleichen Rang-Titel
        krsort( $titles );
        foreach ( $titles as $lvl => $title ) {
            if ( $level >= $lvl ) {
                return $title;
            }
        }

        return __( 'Novize', 'ha-achievement-api' );
    }

    /**
     * Liefert die Mindest-XP für die nächste Stufe und den Fortschritt in Prozent.
     *
     * @param int $xp Aktuelle XP
     * @param int $level Aktuelle Stufe
     * @return array{current_level_min: int, next_level_min: int, percent: int, remaining_xp: int, xp_in_level: int, level_span: int}
     */
    public static function get_level_progress( int $xp, int $level ): array {
        $custom_thresholds = apply_filters( 'ha_achievement_level_thresholds', null );
        if ( is_array( $custom_thresholds ) && ! empty( $custom_thresholds ) ) {
            ksort( $custom_thresholds );
            $current_min = isset( $custom_thresholds[ $level ] ) ? $custom_thresholds[ $level ] : 0;
            $next_level  = $level + 1;
            $next_min    = isset( $custom_thresholds[ $next_level ] ) ? $custom_thresholds[ $next_level ] : ( $current_min + 1000 );
        } else {
            $step        = self::get_xp_per_level();
            $current_min = ( $level - 1 ) * $step;
            $next_min    = $level * $step;
        }

        $span      = max( 1, $next_min - $current_min );
        $progress  = max( 0, $xp - $current_min );
        $percent   = min( 100, max( 0, (int) round( ( $progress / $span ) * 100 ) ) );
        $remaining = max( 0, $next_min - $xp );

        return [
            'current_level_min' => $current_min,
            'next_level_min'    => $next_min,
            'percent'           => $percent,
            'remaining_xp'      => $remaining,
            'xp_in_level'       => $progress,
            'level_span'        => $span,
        ];
    }

    /**
     * Gibt die Gesamt-XP eines Nutzers zurück.
     *
     * @param int $user_id Benutzer-ID
     * @return int Gesamt-XP
     */
    public static function get_xp( int $user_id ): int {
        return (int) get_user_meta( $user_id, 'ha_total_xp', true );
    }

    /**
     * Schreibt XP gut, aktualisiert Stufe und Rangtitel und registriert die Aktivität.
     *
     * @param int    $user_id Benutzer-ID
     * @param int    $amount  Punkteanzahl (darf auch negativ sein für manuelle Korrekturen)
     * @param string $source  Quelle (z. B. 'ha-bpmn-manager')
     * @param string $reason  Begründung
     * @param array  $meta    Zusatzdaten
     * @return array Status des Aufstiegs
     */
    public static function add_xp( int $user_id, int $amount, string $source = 'core', string $reason = '', array $meta = [] ): array {
        $old_xp    = self::get_xp( $user_id );
        $new_xp    = max( 0, $old_xp + $amount );
        $old_level = (int) get_user_meta( $user_id, 'ha_level', true ) ?: self::calculate_level( $old_xp );
        $new_level = self::calculate_level( $new_xp );

        // Usermeta aktualisieren
        update_user_meta( $user_id, 'ha_total_xp', $new_xp );
        update_user_meta( $user_id, 'ha_level', $new_level );
        $new_title = self::get_rank_title( $new_level );
        update_user_meta( $user_id, 'ha_rank_title', $new_title );

        // Streak aktualisieren
        self::update_streak( $user_id );

        // In Activity-Log einfügen
        self::log_activity( $user_id, [
            'type'      => 'xp_award',
            'amount'    => $amount,
            'new_total' => $new_xp,
            'source'    => $source,
            'reason'    => $reason,
            'time'      => time(),
        ] );

        $leveled_up = ( $new_level > $old_level );

        // Transient-Cache des Leaderboards invalidieren bei relevanten Änderungen
        delete_transient( 'ha_leaderboard_top10' );
        delete_transient( 'ha_leaderboard_top50' );

        $result = [
            'user_id'    => $user_id,
            'old_xp'     => $old_xp,
            'new_xp'     => $new_xp,
            'old_level'  => $old_level,
            'new_level'  => $new_level,
            'leveled_up' => $leveled_up,
            'rank_title' => $new_title,
        ];

        do_action( 'ha_achievement_xp_awarded', $user_id, $amount, $result, $source );

        if ( $leveled_up ) {
            do_action( 'ha_achievement_leveled_up', $user_id, $new_level, $old_level, $result );
        }

        return $result;
    }

    /**
     * Markiert eine Teilaufgabe als abgeschlossen.
     *
     * @param int    $user_id  Benutzer-ID
     * @param string $task_key Aufgaben-Schlüssel (z. B. 'c1_toggle_sim')
     * @param string $source   Quell-Plugin
     * @param array  $meta     Zusatzdaten
     * @return bool True, wenn neu abgeschlossen, False falls bereits erledigt
     */
    public static function complete_subtask( int $user_id, string $task_key, string $source = 'ha-bpmn-manager', array $meta = [] ): bool {
        $tasks = self::get_user_tasks( $user_id );

        // Bereits abgeschlossen?
        if ( isset( $tasks[ $task_key ] ) && ! empty( $tasks[ $task_key ]['completed'] ) ) {
            return false;
        }

        $tasks[ $task_key ] = [
            'completed'    => true,
            'completed_at' => time(),
            'source'       => $source,
            'meta'         => $meta,
        ];

        update_user_meta( $user_id, 'ha_tasks_progress', $tasks );

        // Standard-XP für Subtask gutschreiben
        $settings = get_option( 'ha_achievement_settings', [] );
        $subtask_xp = isset( $settings['xp_per_subtask'] ) ? (int) $settings['xp_per_subtask'] : 10;
        if ( $subtask_xp > 0 ) {
            self::add_xp( $user_id, $subtask_xp, $source, sprintf( __( 'Teilaufgabe gelöst: %s', 'ha-achievement-api' ), $task_key ) );
        }

        // Streak aktualisieren
        self::update_streak( $user_id );

        // Loggen
        self::log_activity( $user_id, [
            'type'     => 'task_completed',
            'task_key' => $task_key,
            'source'   => $source,
            'time'     => time(),
        ] );

        do_action( 'ha_achievement_task_completed', $user_id, $task_key, $source, $meta );

        return true;
    }

    /**
     * Prüft, ob eine Teilaufgabe abgeschlossen wurde.
     *
     * @param int    $user_id  Benutzer-ID
     * @param string $task_key Aufgaben-Schlüssel
     * @return bool
     */
    public static function is_task_completed( int $user_id, string $task_key ): bool {
        $tasks = self::get_user_tasks( $user_id );
        return ! empty( $tasks[ $task_key ]['completed'] );
    }

    /**
     * Gibt alle erledigten Aufgaben eines Nutzers zurück.
     *
     * @param int $user_id Benutzer-ID
     * @return array
     */
    public static function get_user_tasks( int $user_id ): array {
        $tasks = get_user_meta( $user_id, 'ha_tasks_progress', true );
        return is_array( $tasks ) ? $tasks : [];
    }

    /**
     * Schaltet ein Abzeichen für den Benutzer frei.
     *
     * @param int    $user_id        Benutzer-ID
     * @param string $achievement_id Slug oder Post-ID
     * @param int    $xp_reward      Zusätzliche Belohnungs-XP
     * @param string $source         Quelle
     * @return bool True bei Neu-Freischaltung
     */
    public static function unlock_achievement( int $user_id, string $achievement_id, int $xp_reward = 0, string $source = 'core' ): bool {
        $achievements = (array) get_user_meta( $user_id, 'ha_unlocked_achievements', true );

        if ( isset( $achievements[ $achievement_id ] ) ) {
            return false;
        }

        $achievements[ $achievement_id ] = [
            'unlocked_at' => time(),
            'xp_reward'   => $xp_reward,
            'source'      => $source,
        ];

        update_user_meta( $user_id, 'ha_unlocked_achievements', $achievements );

        if ( $xp_reward > 0 ) {
            self::add_xp( $user_id, $xp_reward, $source, sprintf( __( 'Abzeichen freigeschaltet: %s', 'ha-achievement-api' ), $achievement_id ) );
        }

        self::log_activity( $user_id, [
            'type'           => 'badge_unlock',
            'achievement_id' => $achievement_id,
            'source'         => $source,
            'time'           => time(),
        ] );

        do_action( 'ha_achievement_unlocked', $user_id, $achievement_id, $source );

        return true;
    }

    /**
     * Prüft, ob ein Abzeichen freigeschaltet ist.
     *
     * @param int    $user_id        Benutzer-ID
     * @param string $achievement_id Abzeichen-Identifikator
     * @return bool
     */
    public static function has_achievement( int $user_id, string $achievement_id ): bool {
        $achievements = (array) get_user_meta( $user_id, 'ha_unlocked_achievements', true );
        return isset( $achievements[ $achievement_id ] );
    }

    /**
     * Aktualisiert die tägliche Lernserie (Streak).
     *
     * @param int $user_id Benutzer-ID
     */
    public static function update_streak( int $user_id ) {
        $last_activity = (int) get_user_meta( $user_id, 'ha_last_activity', true );
        $now = time();
        $streak = (int) get_user_meta( $user_id, 'ha_streak_days', true );

        if ( ! $last_activity ) {
            $streak = 1;
        } else {
            $last_day = (int) date( 'Ymd', $last_activity );
            $today    = (int) date( 'Ymd', $now );
            $yesterday = (int) date( 'Ymd', strtotime( '-1 day', $now ) );

            if ( $last_day === $today ) {
                // Selber Tag, Streak bleibt unverändert
            } elseif ( $last_day === $yesterday ) {
                // Nahtloser Folgetag: Streak wächst!
                $streak++;
            } else {
                // Mehr als ein Tag vergangen: Streak zurückgesetzt
                $streak = 1;
            }
        }

        update_user_meta( $user_id, 'ha_streak_days', $streak );
        update_user_meta( $user_id, 'ha_last_activity', $now );
    }

    /**
     * Fügt einen Eintrag in das persönliche Aktivitäts-Protokoll (Ringpuffer: max 30) ein.
     *
     * @param int   $user_id Benutzer-ID
     * @param array $entry   Aktivitätsdaten
     */
    public static function log_activity( int $user_id, array $entry ) {
        $log = (array) get_user_meta( $user_id, 'ha_activity_log', true );
        array_unshift( $log, $entry );
        if ( count( $log ) > 30 ) {
            $log = array_slice( $log, 0, 30 );
        }
        update_user_meta( $user_id, 'ha_activity_log', $log );
    }

    /**
     * Erstellt das vollständige Profil eines Benutzers für Frontend-Widgets und REST API.
     *
     * @param int $user_id Benutzer-ID
     * @return array
     */
    public static function get_profile( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }

        $xp       = self::get_xp( $user_id );
        $level    = (int) get_user_meta( $user_id, 'ha_level', true ) ?: self::calculate_level( $xp );
        $title    = (string) get_user_meta( $user_id, 'ha_rank_title', true ) ?: self::get_rank_title( $level );
        $progress = self::get_level_progress( $xp, $level );
        $tasks    = self::get_user_tasks( $user_id );
        $badges   = (array) get_user_meta( $user_id, 'ha_unlocked_achievements', true );
        $streak   = (int) get_user_meta( $user_id, 'ha_streak_days', true );

        // Vergebene Zertifikate für diesen Nutzer zählen
        $cert_count = count( get_posts( [
            'post_type'   => 'ha_credential',
            'author'      => $user_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ] ) );

        return [
            'user_id'          => $user_id,
            'display_name'     => $user->display_name,
            'avatar_url'       => get_avatar_url( $user_id, [ 'size' => 128 ] ),
            'total_xp'         => $xp,
            'level'            => $level,
            'rank_title'       => $title,
            'streak_days'      => $streak,
            'level_progress'   => $progress,
            'completed_tasks'  => count( array_filter( $tasks, function( $t ) { return ! empty( $t['completed'] ); } ) ),
            'unlocked_badges'  => count( $badges ),
            'issued_diplomas'  => $cert_count,
            'recent_activity'  => (array) get_user_meta( $user_id, 'ha_activity_log', true ),
        ];
    }

    /**
     * Setzt alle Fortschritte eines Benutzers zurück (z. B. auf Wunsch des Nutzers oder durch Admin).
     *
     * @param int $user_id Benutzer-ID
     */
    public static function reset_progress( int $user_id ) {
        delete_user_meta( $user_id, 'ha_total_xp' );
        delete_user_meta( $user_id, 'ha_level' );
        delete_user_meta( $user_id, 'ha_rank_title' );
        delete_user_meta( $user_id, 'ha_streak_days' );
        delete_user_meta( $user_id, 'ha_last_activity' );
        delete_user_meta( $user_id, 'ha_unlocked_achievements' );
        delete_user_meta( $user_id, 'ha_tasks_progress' );
        delete_user_meta( $user_id, 'ha_activity_log' );

        delete_transient( 'ha_leaderboard_top10' );
        delete_transient( 'ha_leaderboard_top50' );

        do_action( 'ha_achievement_user_reset', $user_id );
    }
}
