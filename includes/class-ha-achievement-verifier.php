<?php
/**
 * Verifikations- und Hash-Engine für HA Achievement API
 *
 * Verwaltet eindeutige Prüfcodes, SHA-256 Hashes und die Echtheitsprüfung
 * ausgestellter Zertifikate und Diplome.
 *
 * @package HA_Achievement_API
 */

namespace HA_Achievement;

defined( 'ABSPATH' ) || exit;

class Verifier {

    /**
     * Initialisiert Hooks.
     */
    public static function init() {
        // Rewrite Rules für kurze Verifikations-URLs
        add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
    }

    /**
     * Fügt zusätzliche Rewrite-Regeln hinzu.
     */
    public static function add_rewrite_rules() {
        $settings = get_option( 'ha_achievement_settings', [] );
        $slug = ! empty( $settings['verification_slug'] ) ? sanitize_title( $settings['verification_slug'] ) : 'zertifikat';

        add_rewrite_rule(
            '^' . $slug . '/([A-Za-z0-9\-_]+)/?$',
            'index.php?ha_credential=$matches[1]',
            'top'
        );
    }

    /**
     * Generiert einen kryptografisch sicheren, kollisionsfreien Verifikations-Code.
     *
     * @param string $prefix Quell-Präfix (z. B. 'BPMN')
     * @return string Z. B. 'HA-BPMN-8X9K2M'
     */
    public static function generate_code( string $prefix = 'BPMN' ): string {
        $prefix = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $prefix ) );
        if ( empty( $prefix ) ) {
            $prefix = 'CERT';
        }

        $max_attempts = 10;
        $attempt = 0;

        do {
            $random_bytes = wp_generate_password( 6, false, false );
            $code = sprintf( 'HA-%s-%s', $prefix, strtoupper( $random_bytes ) );

            // Prüfe auf Eindeutigkeit
            $existing = get_posts( [
                'post_type'      => 'ha_credential',
                'name'           => sanitize_title( $code ),
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ] );

            $attempt++;
        } while ( ! empty( $existing ) && $attempt < $max_attempts );

        return $code;
    }

    /**
     * Erzeugt einen manipulationssicheren SHA-256-Hash für ein Diplom.
     *
     * @param int    $user_id   Benutzer-ID
     * @param string $code      Verifikations-Code
     * @param string $grade     Prädikat
     * @param int    $total_xp  Punkte
     * @param string $issued_at Zeitstempel
     * @return string SHA-256 Hash
     */
    public static function compute_hash( int $user_id, string $code, string $grade, int $total_xp, string $issued_at ): string {
        $salt = wp_salt( 'auth' );
        $payload = implode( '|', [ $user_id, $code, $grade, $total_xp, $issued_at, $salt ] );
        return hash( 'sha256', $payload );
    }

    /**
     * Prüft einen Verifikations-Code oder Hash auf Echtheit.
     *
     * @param string $code_or_hash Verifikations-Code (z. B. 'HA-BPMN-A7X9') oder SHA-256 Hash
     * @return array{valid: bool, status: string, data?: array}
     */
    public static function verify( string $code_or_hash ): array {
        $code_or_hash = sanitize_text_field( trim( $code_or_hash ) );

        if ( empty( $code_or_hash ) ) {
            return [
                'valid'   => false,
                'status'  => 'empty_code',
                'message' => __( 'Kein Verifikationscode angegeben.', 'ha-achievement-api' ),
            ];
        }

        // Suche nach post_name (Code) oder meta_value (_ha_credential_hash / _ha_verification_code)
        $args = [
            'post_type'      => 'ha_credential',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
        ];

        if ( 64 === strlen( $code_or_hash ) && ctype_xdigit( $code_or_hash ) ) {
            // Nach Hash suchen
            $args['meta_query'] = [
                [
                    'key'     => '_ha_credential_hash',
                    'value'   => $code_or_hash,
                    'compare' => '=',
                ],
            ];
        } else {
            // Nach Code suchen
            $args['name'] = sanitize_title( $code_or_hash );
        }

        $query = new \WP_Query( $args );

        if ( ! $query->have_posts() ) {
            // Fallback: Suche in Meta-Feld _ha_verification_code
            $fallback_query = new \WP_Query( [
                'post_type'      => 'ha_credential',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => [
                    [
                        'key'     => '_ha_verification_code',
                        'value'   => $code_or_hash,
                        'compare' => '=',
                    ],
                ],
            ] );

            if ( ! $fallback_query->have_posts() ) {
                return [
                    'valid'   => false,
                    'status'  => 'not_found',
                    'message' => __( 'Dieses Diplom konnte nicht im offiziellen Prüfregister gefunden werden.', 'ha-achievement-api' ),
                ];
            }
            $post = $fallback_query->posts[0];
        } else {
            $post = $query->posts[0];
        }

        $is_revoked = (bool) get_post_meta( $post->ID, '_ha_is_revoked', true );
        $rev_reason = get_post_meta( $post->ID, '_ha_revocation_reason', true );
        $code       = get_post_meta( $post->ID, '_ha_verification_code', true );
        $hash       = get_post_meta( $post->ID, '_ha_credential_hash', true );
        $grade      = get_post_meta( $post->ID, '_ha_honor_grade', true );
        $total_xp   = (int) get_post_meta( $post->ID, '_ha_total_xp', true );
        $issued_at  = get_post_meta( $post->ID, '_ha_issued_at', true );
        $svg        = get_post_meta( $post->ID, '_ha_certificate_svg', true );

        $author = get_userdata( $post->post_author );
        $recipient_name = $author ? $author->display_name : __( 'Unbekannter Teilnehmer', 'ha-achievement-api' );

        if ( $is_revoked ) {
            return [
                'valid'       => false,
                'status'      => 'revoked',
                'message'     => __( 'Dieses Zertifikat wurde offiziell widerrufen.', 'ha-achievement-api' ),
                'revoked_at'  => get_the_modified_date( 'Y-m-d H:i:s', $post->ID ),
                'reason'      => $rev_reason ?: __( 'Prüfung nicht bestanden oder zurückgezogen.', 'ha-achievement-api' ),
                'recipient'   => $recipient_name,
                'code'        => $code,
            ];
        }

        return [
            'valid'          => true,
            'status'         => 'valid',
            'message'        => __( '✅ Offiziell verifiziertes und gültiges Original-Diplom.', 'ha-achievement-api' ),
            'post_id'        => $post->ID,
            'title'          => $post->post_title,
            'code'           => $code,
            'hash'           => $hash,
            'recipient'      => $recipient_name,
            'honor_grade'    => $grade,
            'total_xp'       => $total_xp,
            'issued_at'      => $issued_at,
            'formatted_date' => date_i18n( get_option( 'date_format' ), strtotime( $issued_at ) ),
            'permalink'      => get_permalink( $post->ID ),
            'svg'            => $svg,
        ];
    }
}
