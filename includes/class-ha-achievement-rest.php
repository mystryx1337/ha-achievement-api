<?php
/**
 * REST API Controller für HA Achievement API
 *
 * Stellt sichere, standardisierte Endpunkte für Echtzeit-Kommunikation
 * mit dem Quest-HUD und Frontend-Widgets bereit.
 *
 * @package HA_Achievement_API
 */

namespace HA_Achievement;

defined( 'ABSPATH' ) || exit;

class REST {

    const REST_NAMESPACE = 'ha-achievement/v1';

    /**
     * Initialisiert REST-Routen.
     */
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /**
     * Registriert alle REST-API-Endpunkte.
     */
    public static function register_routes() {
        // 1. Benutzer-Fortschritt abfragen
        register_rest_route( self::REST_NAMESPACE, '/user/progress', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_user_progress' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ] );

        // 2. Aufgabe als erledigt melden
        register_rest_route( self::REST_NAMESPACE, '/tasks/complete', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'complete_task' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => [
                'task_key' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'source'   => [
                    'type'              => 'string',
                    'default'           => 'ha-bpmn-manager',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        // 3. XP gutschreiben
        register_rest_route( self::REST_NAMESPACE, '/xp/award', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'award_xp' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => [
                'xp'     => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'source' => [
                    'type'              => 'string',
                    'default'           => 'ha-bpmn-manager',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'reason' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        // 4. Diplom / Zertifikat ausstellen
        register_rest_route( self::REST_NAMESPACE, '/credentials/issue', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'issue_credential' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ] );

        // 5. Öffentliche Diplom-Verifikation
        register_rest_route( self::REST_NAMESPACE, '/credentials/verify/(?P<code>[a-zA-Z0-9\-_]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'verify_credential' ],
            'permission_callback' => '__return_true',
        ] );

        // 6. Bestenliste abfragen
        register_rest_route( self::REST_NAMESPACE, '/leaderboard', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_leaderboard' ],
            'permission_callback' => '__return_true',
        ] );

        // 7. Aufgabenkatalog abrufen
        register_rest_route( self::REST_NAMESPACE, '/tasks/catalog', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_task_catalog' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Liefert den aktuellen Gamification-Zustand des eingeloggten Nutzers.
     */
    public static function get_user_progress( \WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $profile = API::get_user_profile( $user_id );
        $tasks   = API::get_user_tasks( $user_id );
        $badges  = (array) get_user_meta( $user_id, 'ha_unlocked_achievements', true );

        return rest_ensure_response( [
            'success' => true,
            'profile' => $profile,
            'tasks'   => $tasks,
            'badges'  => $badges,
        ] );
    }

    /**
     * Markiert eine Aufgabe als erledigt.
     */
    public static function complete_task( \WP_REST_Request $request ) {
        $user_id  = get_current_user_id();
        $task_key = $request->get_param( 'task_key' );
        $source   = $request->get_param( 'source' ) ?: 'ha-bpmn-manager';
        $meta     = (array) $request->get_param( 'meta' );

        $is_new = API::complete_task( $user_id, $task_key, $source, $meta );
        $profile = API::get_user_profile( $user_id );

        return rest_ensure_response( [
            'success'        => true,
            'newly_complete' => $is_new,
            'task_key'       => $task_key,
            'profile'        => $profile,
        ] );
    }

    /**
     * Schreibt XP gut.
     */
    public static function award_xp( \WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $xp      = (int) $request->get_param( 'xp' );
        $source  = $request->get_param( 'source' ) ?: 'ha-bpmn-manager';
        $reason  = (string) $request->get_param( 'reason' );
        $meta    = (array) $request->get_param( 'meta' );

        // Plausibilitätsprüfung: max 1000 XP pro einzelnem Call gegen Missbrauch
        if ( $xp > 1000 && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'ha_rate_limit', __( 'XP-Gutschrift übersteigt das Sicherheitslimit.', 'ha-achievement-api' ), [ 'status' => 400 ] );
        }

        $result = API::award_xp( $user_id, $xp, $source, $reason, $meta );

        return rest_ensure_response( [
            'success' => true,
            'result'  => $result,
        ] );
    }

    /**
     * Stellt ein Diplom aus.
     */
    public static function issue_credential( \WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $source  = $request->get_param( 'source' ) ?: 'ha-bpmn-manager';
        $type    = $request->get_param( 'type' ) ?: 'bpmn_grandmaster';

        $data = [
            'title'       => sanitize_text_field( $request->get_param( 'title' ) ),
            'honor_grade' => sanitize_text_field( $request->get_param( 'honor_grade' ) ),
            'total_xp'    => (int) $request->get_param( 'total_xp' ),
            'svg'         => $request->get_param( 'svg' ),
            'metadata'    => (array) $request->get_param( 'metadata' ),
        ];

        $code = API::issue_credential( $user_id, $source, $type, $data );

        if ( empty( $code ) ) {
            return new \WP_Error( 'ha_cert_failed', __( 'Diplom konnte nicht gespeichert werden.', 'ha-achievement-api' ), [ 'status' => 500 ] );
        }

        $verification = API::verify_credential( $code );

        return rest_ensure_response( [
            'success'           => true,
            'verification_code' => $code,
            'verification_url'  => $verification['permalink'] ?? '',
            'data'              => $verification,
        ] );
    }

    /**
     * Öffentliche Prüfung eines Codes.
     */
    public static function verify_credential( \WP_REST_Request $request ) {
        $code = $request->get_param( 'code' );
        $res  = API::verify_credential( $code );

        return rest_ensure_response( $res );
    }

    /**
     * Liefert die Bestenliste.
     */
    public static function get_leaderboard( \WP_REST_Request $request ) {
        $limit = max( 3, min( 50, (int) $request->get_param( 'limit' ) ?: 10 ) );
        $board = API::get_leaderboard( $limit );

        return rest_ensure_response( [
            'success'     => true,
            'leaderboard' => $board,
        ] );
    }

    /**
     * Liefert den registrierten Aufgabenkatalog.
     */
    public static function get_task_catalog( \WP_REST_Request $request ) {
        $source = $request->get_param( 'source' );
        $tasks  = API::get_registered_tasks( $source ? sanitize_text_field( $source ) : null );

        return rest_ensure_response( [
            'success' => true,
            'tasks'   => $tasks,
        ] );
    }
}
