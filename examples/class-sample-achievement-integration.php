<?php
/**
 * Muster-Vorlage (Boilerplate): Gamification- & Aufgaben-Integration
 *
 * Verwenden Sie diese Klasse in jedem neuen Plugin (z. B. ha-workflow-forms,
 * ha-quiz, Schulungs-Module), um es nahtlos, fehlertolerant und ohne feste
 * Abhängigkeiten an die ha-achievement-api anzubinden.
 *
 * Speicherort im Ziel-Plugin: z. B. includes/class-my-plugin-achievements.php
 *
 * @package My_Plugin
 */

namespace My_Plugin;

defined( 'ABSPATH' ) || exit;

class Sample_Achievement_Integration {

    /**
     * Eindeutiger Quell-Schlüssel Ihres Plugins.
     */
    const SOURCE_SLUG = 'my-plugin-slug';

    /**
     * Initialisiert Hooks.
     * Sollte in der init-Phase oder beim Laden des Plugins aufgerufen werden.
     */
    public static function init() {
        // 1. Quell-Identität & Branding registrieren
        add_filter( 'ha_achievement_registered_sources', [ __CLASS__, 'register_source' ] );

        // 2. Aufgabenkatalog dynamisch übergeben
        add_filter( 'ha_achievement_registered_tasks', [ __CLASS__, 'register_tasks' ] );

        // 3. Eintrag im WP-Admin unter „Verbundene Plugins“ anzeigen
        add_filter( 'ha_achievement_connected_plugins', [ __CLASS__, 'register_admin_card' ] );

        // 4. (Optional) Auf Ereignisse wie Level-Ups reagieren
        add_action( 'ha_achievement_leveled_up', [ __CLASS__, 'on_user_leveled_up' ], 10, 4 );
    }

    /**
     * Registriert das optische Erscheinungsbild Ihres Plugins im Dashboard.
     *
     * @param array $sources Bestehende Quellen
     * @return array
     */
    public static function register_source( array $sources ): array {
        $sources[ self::SOURCE_SLUG ] = [
            'slug'         => self::SOURCE_SLUG,
            'name'         => 'Mein Plugin-Titel',
            'label'        => 'Prozess- & Arbeitsbereich',
            'icon'         => '⚡',                // Emoji oder SVG
            'color'        => '#4f46e5',             // Primärfarbe
            'bg'           => '#eef2ff',             // Heller Hintergrund
            'action_label' => __( 'Im Modul fortsetzen ▶', 'my-plugin' ),
        ];
        return $sources;
    }

    /**
     * Definiert die Lern- oder Aufgabenmissionen Ihres Plugins.
     *
     * @param array $catalog Gesamtkatalog
     * @return array
     */
    public static function register_tasks( array $catalog ): array {
        $catalog[ self::SOURCE_SLUG ] = [
            'step_1_create_record' => [
                'task_key'      => 'step_1_create_record',
                'source'        => self::SOURCE_SLUG,
                'source_label'  => 'Mein Plugin',
                'source_icon'   => '⚡',
                'source_color'  => '#4f46e5',
                'chapter_key'   => 'chapter1',
                'chapter_title' => __( 'Kapitel 1: Der Einstieg', 'my-plugin' ),
                'chapter_icon'  => '📖',
                'title'         => __( 'Ersten Vorgang anlegen', 'my-plugin' ),
                'hint'          => __( 'Klicke auf „Neu anlegen“ und fülle die Pflichtangaben aus.', 'my-plugin' ),
                'action_url'    => admin_url( 'admin.php?page=my-plugin-new' ),
                'action_label'  => __( 'Jetzt anlegen ▶', 'my-plugin' ),
                'order_num'     => 1,
                'xp_value'      => 15,
            ],
            'step_2_export_report' => [
                'task_key'      => 'step_2_export_report',
                'source'        => self::SOURCE_SLUG,
                'source_label'  => 'Mein Plugin',
                'source_icon'   => '⚡',
                'source_color'  => '#4f46e5',
                'chapter_key'   => 'chapter1',
                'chapter_title' => __( 'Kapitel 1: Der Einstieg', 'my-plugin' ),
                'chapter_icon'  => '📖',
                'title'         => __( 'Ersten Bericht exportieren', 'my-plugin' ),
                'hint'          => __( 'Wähle den Vorgang aus und lade das PDF herunter.', 'my-plugin' ),
                'action_url'    => admin_url( 'admin.php?page=my-plugin-reports' ),
                'action_label'  => __( 'Zum Bericht ▶', 'my-plugin' ),
                'order_num'     => 2,
                'xp_value'      => 25,
            ],
        ];
        return $catalog;
    }

    /**
     * Stellt eine Informationskarte im WordPress-Backend unter „Auszeichnungen -> Verbundene Plugins“ bereit.
     *
     * @param array $plugins Liste verbundener Plugins
     * @return array
     */
    public static function register_admin_card( array $plugins ): array {
        $plugins[ self::SOURCE_SLUG ] = [
            'name'             => 'Mein Plugin (Workflow & Reports)',
            'slug'             => self::SOURCE_SLUG,
            'status'           => 'active',
            'version'          => '1.0.0',
            'description'      => __( 'Automatisiert Prozesse und integriert sich in das zentrale Gamification-System.', 'my-plugin' ),
            'registered_tasks' => 2,
            'features'         => [
                __( 'Schritt-für-Schritt Onboarding-Aufgaben', 'my-plugin' ),
                __( 'Automatische Belohnungspunkte für Produktivität', 'my-plugin' ),
            ],
        ];
        return $plugins;
    }

    /**
     * Bequemlichkeits-Methode: Eine Aufgabe für einen Benutzer als erledigt markieren.
     *
     * @param int    $user_id  Benutzer-ID
     * @param string $task_key Aufgaben-Schlüssel
     * @param array  $meta     Optionale Metadaten
     */
    public static function mark_completed( int $user_id, string $task_key, array $meta = [] ) {
        do_action( 'ha_achievement_complete_task', $user_id, $task_key, self::SOURCE_SLUG, $meta );
    }

    /**
     * Bequemlichkeits-Methode: Erfahrungspunkte vergeben.
     *
     * @param int    $user_id Benutzer-ID
     * @param int    $xp      Punkte
     * @param string $reason  Begründung für das Aktivitätenlog
     */
    public static function give_xp( int $user_id, int $xp, string $reason = '' ) {
        do_action( 'ha_achievement_award_xp', $user_id, $xp, self::SOURCE_SLUG, $reason );
    }

    /**
     * Beispiel-Event-Listener: Wird aufgerufen, wenn der Nutzer im Gesamtsystem ein Level aufsteigt.
     */
    public static function on_user_leveled_up( int $user_id, int $new_level, int $old_level, array $result ) {
        // Hier können bei Bedarf plugin-spezifische Aktionen ausgeführt werden.
    }
}
