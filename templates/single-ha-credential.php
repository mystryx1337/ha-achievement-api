<?php
/**
 * Template für die öffentliche Zertifikats-Verifikationsseite (CPT ha_credential)
 *
 * @package HA_Achievement_API
 */

get_header();

$post_id   = get_the_ID();
$code      = get_post_meta( $post_id, '_ha_verification_code', true );
$hash      = get_post_meta( $post_id, '_ha_credential_hash', true );
$grade     = get_post_meta( $post_id, '_ha_honor_grade', true );
$xp        = (int) get_post_meta( $post_id, '_ha_total_xp', true );
$issued_at = get_post_meta( $post_id, '_ha_issued_at', true );
$is_revoked= (bool) get_post_meta( $post_id, '_ha_is_revoked', true );
$rev_reason= get_post_meta( $post_id, '_ha_revocation_reason', true );
$svg       = get_post_meta( $post_id, '_ha_certificate_svg', true );

$user      = get_userdata( get_post_field( 'post_author', $post_id ) );
$user_name = $user ? $user->display_name : __( 'Absolvent', 'ha-achievement-api' );
?>

<div class="ha-credential-public-wrapper" style="max-width: 960px; margin: 40px auto; padding: 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    
    <!-- Header Banner -->
    <div style="text-align: center; margin-bottom: 30px;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; background: #e0e7ff; border-radius: 50%; margin-bottom: 12px; font-size: 32px;">
            🛡️
        </div>
        <h1 style="font-size: 28px; font-weight: 800; color: #0f172a; margin: 0 0 8px;">
            <?php esc_html_e( 'Offizieller Prüfnachweis', 'ha-achievement-api' ); ?>
        </h1>
        <p style="font-size: 15px; color: #64748b; margin: 0;">
            <?php esc_html_e( 'Verifiziertes Register für ISO 19510 BPMN-Auszeichnungen & Diplome', 'ha-achievement-api' ); ?>
        </p>
    </div>

    <!-- Prüf-Ergebnis-Box -->
    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); overflow: hidden; margin-bottom: 35px;">
        
        <!-- Status-Balken -->
        <?php if ( ! $is_revoked ) : ?>
            <div style="background: #ecfdf5; border-bottom: 1px solid #a7f3d0; padding: 18px 24px; display: flex; align-items: center; gap: 14px;">
                <span style="font-size: 28px;">✅</span>
                <div>
                    <h3 style="margin: 0; color: #065f46; font-size: 18px; font-weight: 700;">
                        <?php esc_html_e( 'Gültiges und echtheitsgeprüftes Original-Diplom', 'ha-achievement-api' ); ?>
                    </h3>
                    <span style="color: #047857; font-size: 13px;">
                        <?php esc_html_e( 'Dieser Abschluss ist im offiziellen Verzeichnis registriert und unwiderrufen gültig.', 'ha-achievement-api' ); ?>
                    </span>
                </div>
            </div>
        <?php else : ?>
            <div style="background: #fef2f2; border-bottom: 1px solid #fecaca; padding: 18px 24px; display: flex; align-items: center; gap: 14px;">
                <span style="font-size: 28px;">❌</span>
                <div>
                    <h3 style="margin: 0; color: #991b1b; font-size: 18px; font-weight: 700;">
                        <?php esc_html_e( 'Dieses Diplom wurde offiziell widerrufen', 'ha-achievement-api' ); ?>
                    </h3>
                    <span style="color: #b91c1c; font-size: 13px;">
                        <?php printf( esc_html__( 'Grund des Widerrufs: %s', 'ha-achievement-api' ), esc_html( $rev_reason ?: __( 'Prüfung aberkannt', 'ha-achievement-api' ) ) ); ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Details Grid -->
        <div style="padding: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; background: #fafafa;">
            <div>
                <span style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e( 'Name des Absolventen', 'ha-achievement-api' ); ?>
                </span>
                <strong style="font-size: 17px; color: #0f172a;"><?php echo esc_html( $user_name ); ?></strong>
            </div>

            <div>
                <span style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e( 'Verifizierungs-Code', 'ha-achievement-api' ); ?>
                </span>
                <strong style="font-family: monospace; font-size: 16px; background: #e0e7ff; color: #3730a3; padding: 3px 8px; border-radius: 4px;">
                    <?php echo esc_html( $code ?: get_post_field( 'post_name', $post_id ) ); ?>
                </strong>
            </div>

            <div>
                <span style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e( 'Akademisches Prädikat', 'ha-achievement-api' ); ?>
                </span>
                <strong style="font-size: 16px; color: #d97706; font-weight: 700;">
                    <?php echo esc_html( $grade ?: 'CUM LAUDE' ); ?>
                </strong>
            </div>

            <div>
                <span style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e( 'Erreichte Gesamt-XP', 'ha-achievement-api' ); ?>
                </span>
                <strong style="font-size: 16px; color: #4338ca; font-weight: 700;">
                    <?php echo esc_html( number_format_i18n( $xp ) ); ?> XP
                </strong>
            </div>

            <div>
                <span style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e( 'Ausstellungsdatum', 'ha-achievement-api' ); ?>
                </span>
                <strong style="font-size: 15px; color: #1e293b;">
                    <?php echo esc_html( $issued_at ? date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime( $issued_at ) ) : get_the_date() ); ?>
                </strong>
            </div>

            <div>
                <span style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e( 'Zertifizierungsstandard', 'ha-achievement-api' ); ?>
                </span>
                <strong style="font-size: 15px; color: #1e293b;">ISO 19510 / BPMN 2.0</strong>
            </div>
        </div>

        <?php if ( $hash ) : ?>
            <div style="padding: 12px 24px; background: #f1f5f9; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">
                <span style="font-weight: 600;"><?php esc_html_e( 'Kryptografischer Hash (SHA-256):', 'ha-achievement-api' ); ?></span>
                <code style="word-break: break-all; color: #475569; margin-left: 6px;"><?php echo esc_html( $hash ); ?></code>
            </div>
        <?php endif; ?>
    </div>

    <!-- Original-Urkunden-Vorschau -->
    <?php if ( ! empty( $svg ) ) : ?>
        <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;">
                    📜 <?php esc_html_e( 'Eingelagerte Original Vektor-Urkunde', 'ha-achievement-api' ); ?>
                </h3>
                <div>
                    <button type="button" onclick="window.print();" style="padding: 6px 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; cursor: pointer; color: #334155;">
                        🖨️ <?php esc_html_e( 'Drucken', 'ha-achievement-api' ); ?>
                    </button>
                </div>
            </div>

            <div style="width: 100%; max-width: 860px; margin: 0 auto; overflow: hidden; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                <?php echo $svg; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
get_footer();
