<?php
/**
 * Integrations- und Seed-Engine für HA Achievement API
 *
 * Verwaltet die automatische Initialisierung von Badges und Aufgabenkatalogen
 * für ha-bpmn-manager und erkennt verbundene Partner-Plugins.
 *
 * @package HA_Achievement_API
 */

namespace HA_Achievement;

defined( 'ABSPATH' ) || exit;

class Integrations {

    /**
     * Initialisiert Hooks.
     */
    public static function init() {
        // Horcht auf Hooks von ha-bpmn-manager
        add_action( 'ha_bpmn_subtask_completed', [ __CLASS__, 'on_bpmn_subtask_completed' ], 10, 3 );
        add_action( 'ha_bpmn_diploma_awarded', [ __CLASS__, 'on_bpmn_diploma_awarded' ], 10, 3 );
    }

    /**
     * Erkennt alle installierten und aktiven Integrations-Plugins.
     *
     * @return array
     */
    public static function get_connected_plugins(): array {
        $plugins = [];

        // 1. ha-bpmn-manager
        $is_bpmn_active = defined( 'HA_BPMN_VERSION' ) || class_exists( 'HA_BPMN_CPT' ) || in_array( 'ha-bpmn-manager/ha-bpmn-manager.php', apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) ), true );
        $catalog = API::get_registered_tasks( 'ha-bpmn-manager' );

        $plugins['ha-bpmn-manager'] = [
            'name'             => 'HA BPMN Manager (Prozess-Studio & Quest-Adventure)',
            'slug'             => 'ha-bpmn-manager',
            'status'           => $is_bpmn_active ? 'active' : 'inactive',
            'version'          => defined( 'HA_BPMN_VERSION' ) ? HA_BPMN_VERSION : ( $is_bpmn_active ? 'Erkannt' : '-' ),
            'description'      => __( 'Interaktives BPMN 2.0 Modellierungsstudio mit integriertem Gamification-Quest-Modus.', 'ha-achievement-api' ),
            'registered_tasks' => count( $catalog ),
            'features'         => [
                __( '27 interaktive Schritt-für-Schritt Modellierungsaufgaben', 'ha-achievement-api' ),
                __( 'Echtzeit-Tokensimulation & Linter-Prüfung nach ISO 19510', 'ha-achievement-api' ),
                __( 'Offizielles Diplom & Vektor-Zertifikat mit Auszeichnung', 'ha-achievement-api' ),
            ],
        ];

        return apply_filters( 'ha_achievement_connected_plugins', $plugins );
    }

    /**
     * Initialisiert die Standard-Badges und den vollständigen 27-Aufgaben-Katalog von ha-bpmn-manager.
     */
    public static function seed_initial_data() {
        self::seed_bpmn_badges();
        self::seed_bpmn_tasks();
    }

    /**
     * Legt Standard-Badges in CPT ha_achievement an, falls noch keine existieren.
     */
    public static function seed_bpmn_badges() {
        $existing = get_posts( [
            'post_type'   => 'ha_achievement',
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
        ] );

        if ( ! empty( $existing ) ) {
            return; // Bereits initialisiert
        }

        $default_badges = [
            [
                'title'       => '🟡 Token-Pionier',
                'slug'        => 'bpmn_token_pioneer',
                'tier'        => 'bronze',
                'xp'          => 50,
                'description' => 'Aktiviere das Token-Game und schicke deinen ersten goldenen Simulationstoken durch das BPMN-Diagramm.',
                'rules'       => [ 'required_tasks' => [ 'c1_toggle_sim', 'c1_start_event' ] ],
            ],
            [
                'title'       => '✖ Weichensteller & Fluchtweg-Pionier',
                'slug'        => 'bpmn_xor_navigator',
                'tier'        => 'silver',
                'xp'          => 75,
                'description' => 'Meistere die XOR-Entscheidungsweiche, löse die Ausnahme-Falle aus und sichere den Prozess mit einem Notfallpfad ab.',
                'rules'       => [ 'required_tasks' => [ 'c2_switch_xor', 'c2_trigger_trap', 'c2_model_end' ] ],
            ],
            [
                'title'       => '➕ Portal der Parallelität',
                'slug'        => 'bpmn_parallel_architect',
                'tier'        => 'gold',
                'xp'          => 100,
                'description' => 'Verbinde parallele Ablaufpfade, spalte den Kontrollfluss per AND-Split und synchronisiere ihn am AND-Join.',
                'rules'       => [ 'required_tasks' => [ 'c3_model_connect', 'c3_and_split', 'c3_and_join' ] ],
            ],
            [
                'title'       => '🛢️ Hüter des Datentresors',
                'slug'        => 'bpmn_vault_keeper',
                'tier'        => 'platinum',
                'xp'          => 125,
                'description' => 'Modelliere Timer-Events und platziere persistente BPMN-Datenspeicher für das dauerhafte Behalten von Prozessdaten.',
                'rules'       => [ 'required_tasks' => [ 'c4_campfire_timer', 'c4_model_datastore', 'c4_data_assoc' ] ],
            ],
            [
                'title'       => '📜 Prozess-Auditor nach ISO 19510',
                'slug'        => 'bpmn_quality_auditor',
                'tier'        => 'titan',
                'xp'          => 150,
                'description' => 'Schließe das Gilden-Audit ohne einen einzigen Linter-Regelverstoß ab und beweise höchste Modellierungsqualität.',
                'rules'       => [ 'required_tasks' => [ 'c5_run_audit', 'c5_zero_errors', 'c5_claim_title' ] ],
            ],
            [
                'title'       => '🏛️ Großmeister-Architekt',
                'slug'        => 'bpmn_grandmaster',
                'tier'        => 'titan',
                'xp'          => 250,
                'description' => 'Erfolgreicher Abschluss der Architekten-Meisterprüfung mit vollendeter Cross-Lane-Modellierung und Diplomverleihung.',
                'rules'       => [ 'required_tasks' => [ 'c6_model_lane3', 'c6_cross_flow', 'c6_finish_grandmaster', 'c6_open_diploma' ] ],
            ],
        ];

        foreach ( $default_badges as $b ) {
            $post_id = wp_insert_post( [
                'post_title'   => $b['title'],
                'post_content' => $b['description'],
                'post_type'    => 'ha_achievement',
                'post_status'  => 'publish',
            ] );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_ha_slug', $b['slug'] );
                update_post_meta( $post_id, '_ha_source', 'ha-bpmn-manager' );
                update_post_meta( $post_id, '_ha_tier', $b['tier'] );
                update_post_meta( $post_id, '_ha_xp_reward', $b['xp'] );
                update_post_meta( $post_id, '_ha_is_secret', '0' );
                update_post_meta( $post_id, '_ha_rules', wp_json_encode( $b['rules'] ) );
            }
        }
    }

    /**
     * Registriert die 27 BPMN-Quest-Aufgaben fest im Aufgabenkatalog.
     */
    public static function seed_bpmn_tasks() {
        $tasks = [
            // Kapitel 1
            [
                'id'            => 'c1_toggle_sim',
                'chapter_key'   => 'chapter1',
                'chapter_title' => 'Kapitel 1: Das Erwachen (Start & Erste Modellierung)',
                'chapter_icon'  => '🏰',
                'title'         => 'Token-Game aktivieren (Play-Symbol unten links)',
                'hint'          => 'Klicke unten links auf das Play-Symbol oder drücke die Tastenkombination T.',
                'order_num'     => 1,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c1_start_event',
                'chapter_key'   => 'chapter1',
                'chapter_title' => 'Kapitel 1: Das Erwachen (Start & Erste Modellierung)',
                'chapter_icon'  => '🏰',
                'title'         => 'Start-Event auslösen (Token starten ▶)',
                'hint'          => 'Klicke im aktiven Token Game auf den grünen Kreis des Start-Events.',
                'order_num'     => 2,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c1_model_task',
                'chapter_key'   => 'chapter1',
                'chapter_title' => 'Kapitel 1: Das Erwachen (Start & Erste Modellierung)',
                'chapter_icon'  => '🏰',
                'title'         => '🔨 Bauauftrag: Aufgabe erstellen & benennen',
                'hint'          => 'Klicke in der linken Palette auf das Rechteck (Task 🔲), platziere es und benenne es per Doppelklick.',
                'order_num'     => 3,
                'xp_value'      => 15,
            ],
            [
                'id'            => 'c1_color_task',
                'chapter_key'   => 'chapter1',
                'chapter_title' => 'Kapitel 1: Das Erwachen (Start & Erste Modellierung)',
                'chapter_icon'  => '🏰',
                'title'         => '🎨 Farbe bekennen: Aufgabe einfärben (Farbpalette 🎨)',
                'hint'          => 'Klicke auf deine erstellte Aufgabe und wähle in der Kontext-Palette eine Farbe aus.',
                'order_num'     => 4,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c1_open_properties',
                'chapter_key'   => 'chapter1',
                'chapter_title' => 'Kapitel 1: Das Erwachen (Start & Erste Modellierung)',
                'chapter_icon'  => '🏰',
                'title'         => '⚙️ Eigenschaften & Dokumentation öffnen',
                'hint'          => 'Klicke oben in der Werkzeugleiste auf das Eigenschaften-Symbol.',
                'order_num'     => 5,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c1_add_annotation',
                'chapter_key'   => 'chapter1',
                'chapter_title' => 'Kapitel 1: Das Erwachen (Start & Erste Modellierung)',
                'chapter_icon'  => '🏰',
                'title'         => '📝 Kommentar anheften: Text-Annotation ergänzen',
                'hint'          => 'Klicke auf ein Element und wähle das Notiz-Symbol [ im runden Kontext-Menü.',
                'order_num'     => 6,
                'xp_value'      => 10,
            ],

            // Kapitel 2
            [
                'id'            => 'c2_switch_xor',
                'chapter_key'   => 'chapter2',
                'chapter_title' => 'Kapitel 2: Weggabelung des Schicksals (XOR & Fluchtweg)',
                'chapter_icon'  => '⚔️',
                'title'         => 'Weiche umstellen (Klick auf XOR-Gateway ✖ oder Pfad-Schalter)',
                'hint'          => 'Klicke im Simulationsmodus auf das XOR-Gateway ✖, um den Gefahrenpfad einzuschalten.',
                'order_num'     => 7,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c2_trigger_trap',
                'chapter_key'   => 'chapter2',
                'chapter_title' => 'Kapitel 2: Weggabelung des Schicksals (XOR & Fluchtweg)',
                'chapter_icon'  => '⚔️',
                'title'         => 'Ausnahme-Falle testen (Blitz-Symbol ⚡ Trollhöhle)',
                'hint'          => 'Error Boundary Events fangen Fehler ab und leiten sofort auf den Notfallpfad um.',
                'order_num'     => 8,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c2_model_end',
                'chapter_key'   => 'chapter2',
                'chapter_title' => 'Kapitel 2: Weggabelung des Schicksals (XOR & Fluchtweg)',
                'chapter_icon'  => '⚔️',
                'title'         => '🔨 Bauauftrag: Fluchtweg mit End-Event verbinden',
                'hint'          => 'Verbinde die Notfall-Aufgabe per Sequenzfluss-Pfeil mit einem End-Event 🔴.',
                'order_num'     => 9,
                'xp_value'      => 15,
            ],
            [
                'id'            => 'c2_color_hazard',
                'chapter_key'   => 'chapter2',
                'chapter_title' => 'Kapitel 2: Weggabelung des Schicksals (XOR & Fluchtweg)',
                'chapter_icon'  => '⚔️',
                'title'         => '🎨 Signalfarbe setzen: Notfallpfad rot einfärben',
                'hint'          => 'Färbe das End-Event oder die Notfall-Aufgabe rot ein.',
                'order_num'     => 10,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c2_study_linter',
                'chapter_key'   => 'chapter2',
                'chapter_title' => 'Kapitel 2: Weggabelung des Schicksals (XOR & Fluchtweg)',
                'chapter_icon'  => '⚔️',
                'title'         => 'Linter-Regeln studieren (Linter unten mittig aktivieren 📜)',
                'hint'          => 'Klicke unten mittig auf die Pille mit der Fehleranzeige, um die 3 goldenen Regeln zu öffnen.',
                'order_num'     => 11,
                'xp_value'      => 10,
            ],

            // Kapitel 3
            [
                'id'            => 'c3_model_connect',
                'chapter_key'   => 'chapter3',
                'chapter_title' => 'Kapitel 3: Portal der Parallelität (AND-Split & Schmiede)',
                'chapter_icon'  => '🔮',
                'title'         => '🔨 Bauauftrag: Schmiede per Pfeil mit dem AND-Join (➕) verbinden',
                'hint'          => 'Klicke auf die Schmiede und ziehe einen Sequenzfluss zum AND-Gateway.',
                'order_num'     => 12,
                'xp_value'      => 15,
            ],
            [
                'id'            => 'c3_and_split',
                'chapter_key'   => 'chapter3',
                'chapter_title' => 'Kapitel 3: Portal der Parallelität (AND-Split & Schmiede)',
                'chapter_icon'  => '🔮',
                'title'         => 'AND-Split auslösen (Klick auf AND-Gateway ➕)',
                'hint'          => 'Erzeugt 2 Tokens gleichzeitig in Lane 1 und Lane 3.',
                'order_num'     => 13,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c3_and_join',
                'chapter_key'   => 'chapter3',
                'chapter_title' => 'Kapitel 3: Portal der Parallelität (AND-Split & Schmiede)',
                'chapter_icon'  => '🔮',
                'title'         => 'AND-Join Synchronisation beobachten',
                'hint'          => 'Das AND-Join wartet, bis Tokens aus beiden Pfaden angekommen sind.',
                'order_num'     => 14,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c3_color_lane3',
                'chapter_key'   => 'chapter3',
                'chapter_title' => 'Kapitel 3: Portal der Parallelität (AND-Split & Schmiede)',
                'chapter_icon'  => '🔮',
                'title'         => '🎨 Werkstatt gestalten: Schmiede einfärben',
                'hint'          => 'Färbe die Aufgabe in Lane 3 ein, um Ordnung im Prozess zu schaffen.',
                'order_num'     => 15,
                'xp_value'      => 10,
            ],

            // Kapitel 4
            [
                'id'            => 'c4_campfire_timer',
                'chapter_key'   => 'chapter4',
                'chapter_title' => 'Kapitel 4: Halle der Zeit & Das Schatzgewölbe',
                'chapter_icon'  => '⏱️',
                'title'         => 'Raste 3 Sekunden am Lagerfeuer (Timer-Event ⏱️)',
                'hint'          => 'Klicke im Token Game auf das Zwischen-Timer-Event, um die Wartezeit zu starten.',
                'order_num'     => 16,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c4_model_datastore',
                'chapter_key'   => 'chapter4',
                'chapter_title' => 'Kapitel 4: Halle der Zeit & Das Schatzgewölbe',
                'chapter_icon'  => '⏱️',
                'title'         => '🔨 Bauauftrag: Datenspeicher (🛢️ Tresor) beim Gewölbe platzieren',
                'hint'          => 'Wähle in der linken Palette den Datenspeicher (Tonne 🛢️) und platziere ihn in Lane 2.',
                'order_num'     => 17,
                'xp_value'      => 15,
            ],
            [
                'id'            => 'c4_data_assoc',
                'chapter_key'   => 'chapter4',
                'chapter_title' => 'Kapitel 4: Halle der Zeit & Das Schatzgewölbe',
                'chapter_icon'  => '⏱️',
                'title'         => 'Daten-Assoziation ziehen (Pfeil zur Schatztruhe)',
                'hint'          => 'Verknüpfe die Gewölbe-Aufgabe per Daten-Assoziation (gestrichelter Pfeil) mit dem Speicher.',
                'order_num'     => 18,
                'xp_value'      => 15,
            ],
            [
                'id'            => 'c4_loot_relic',
                'chapter_key'   => 'chapter4',
                'chapter_title' => 'Kapitel 4: Halle der Zeit & Das Schatzgewölbe',
                'chapter_icon'  => '⏱️',
                'title'         => 'BPMN-Relikt bergen & Token durchleiten',
                'hint'          => 'Lass den Token die Schatzkammer durchlaufen.',
                'order_num'     => 19,
                'xp_value'      => 10,
            ],

            // Kapitel 5
            [
                'id'            => 'c5_data_read',
                'chapter_key'   => 'chapter5',
                'chapter_title' => 'Kapitel 5: Gilde der Prozessmeister (Datenfluss & Audit)',
                'chapter_icon'  => '👑',
                'title'         => 'Quest-Logbuch per Lese-Assoziation verknüpfen',
                'hint'          => 'Verbinde das Datenobjekt mit der Gilden-Prüfung.',
                'order_num'     => 20,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c5_run_audit',
                'chapter_key'   => 'chapter5',
                'chapter_title' => 'Kapitel 5: Gilde der Prozessmeister (Datenfluss & Audit)',
                'chapter_icon'  => '👑',
                'title'         => 'Gilden-Audit durchführen (Linter-Prüfung)',
                'hint'          => 'Öffne das Linter-Panel und prüfe die Regelkonformität nach ISO 19510.',
                'order_num'     => 21,
                'xp_value'      => 10,
            ],
            [
                'id'            => 'c5_zero_errors',
                'chapter_key'   => 'chapter5',
                'chapter_title' => 'Kapitel 5: Gilde der Prozessmeister (Datenfluss & Audit)',
                'chapter_icon'  => '👑',
                'title'         => '0 Fehler erreichen (BPMN-Validierung)',
                'hint'          => 'Behebe alle offenen Pfeile oder unbenannten Elemente.',
                'order_num'     => 22,
                'xp_value'      => 20,
            ],
            [
                'id'            => 'c5_claim_title',
                'chapter_key'   => 'chapter5',
                'chapter_title' => 'Kapitel 5: Gilde der Prozessmeister (Datenfluss & Audit)',
                'chapter_icon'  => '👑',
                'title'         => 'Gildentitel entgegennehmen',
                'hint'          => 'Schließe das Kapitel ab, um zur Architektenprüfung zugelassen zu werden.',
                'order_num'     => 23,
                'xp_value'      => 15,
            ],

            // Kapitel 6
            [
                'id'            => 'c6_model_lane3',
                'chapter_key'   => 'chapter6',
                'chapter_title' => 'Kapitel 6: Die Architekten-Prüfung (Cross-Lane Modellierung)',
                'chapter_icon'  => '🏛️',
                'title'         => '🔨 Bauauftrag: Aufgabe in der Werkstatt-Lane (Lane 3) erstellen',
                'hint'          => 'Platziere eine neue Aufgabe direkt in der 3. Swimlane.',
                'order_num'     => 24,
                'xp_value'      => 15,
            ],
            [
                'id'            => 'c6_cross_flow',
                'chapter_key'   => 'chapter6',
                'chapter_title' => 'Kapitel 6: Die Architekten-Prüfung (Cross-Lane Modellierung)',
                'chapter_icon'  => '🏛️',
                'title'         => 'Sequenzfluss über Lane-Grenzen hinweg ziehen',
                'hint'          => 'Verbinde Aufgaben aus unterschiedlichen Lanes miteinander.',
                'order_num'     => 25,
                'xp_value'      => 20,
            ],
            [
                'id'            => 'c6_finish_grandmaster',
                'chapter_key'   => 'chapter6',
                'chapter_title' => 'Kapitel 6: Die Architekten-Prüfung (Cross-Lane Modellierung)',
                'chapter_icon'  => '🏛️',
                'title'         => 'Meisterprüfung bei der Gilde abschließen',
                'hint'          => 'Führe den Kontrollfluss zum finalen Meister-End-Event.',
                'order_num'     => 26,
                'xp_value'      => 25,
            ],
            [
                'id'            => 'c6_open_diploma',
                'chapter_key'   => 'chapter6',
                'chapter_title' => 'Kapitel 6: Die Architekten-Prüfung (Cross-Lane Modellierung)',
                'chapter_icon'  => '🏛️',
                'title'         => 'Offizielles Diplom öffnen & Urkunde empfangen',
                'hint'          => 'Klicke auf den goldenen Urkunden-Button, um das Diplom zu generieren.',
                'order_num'     => 27,
                'xp_value'      => 50,
            ],
        ];

        API::register_tasks( 'ha-bpmn-manager', $tasks );
    }

    /**
     * Reagiert auf einen von ha-bpmn-manager gemeldeten Subtask-Abschluss.
     */
    public static function on_bpmn_subtask_completed( $user_id, $task_key, $meta = [] ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( $user_id ) {
            API::complete_task( (int) $user_id, (string) $task_key, 'ha-bpmn-manager', (array) $meta );
        }
    }

    /**
     * Reagiert auf ein verliehenes Diplom von ha-bpmn-manager.
     */
    public static function on_bpmn_diploma_awarded( $user_id, $diploma_data, $svg_snapshot = '' ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( $user_id ) {
            $data = is_array( $diploma_data ) ? $diploma_data : [];
            $data['svg'] = $svg_snapshot;
            API::issue_credential( (int) $user_id, 'ha-bpmn-manager', 'bpmn_grandmaster', $data );
        }
    }
}
