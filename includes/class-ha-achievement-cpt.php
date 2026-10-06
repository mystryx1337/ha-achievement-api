<?php
/**
 * Custom Post Types & Taxonomien für HA Achievement API
 *
 * Registriert 'ha_achievement' (Badges/Errungenschaften) und
 * 'ha_credential' (Verifizierbare Diplome & Zertifikate) sowie Meta-Boxen
 * und Admin-Spalten.
 *
 * @package HA_Achievement_API
 */

namespace HA_Achievement;

defined( 'ABSPATH' ) || exit;

class CPT {

    /**
     * Initialisiert Hooks für CPTs und Taxonomien.
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_types' ] );
        add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );

        // Meta-Boxen
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post', [ __CLASS__, 'save_meta_boxes' ], 10, 2 );

        // Admin-Spalten für ha_achievement
        add_filter( 'manage_ha_achievement_posts_columns', [ __CLASS__, 'filter_achievement_columns' ] );
        add_action( 'manage_ha_achievement_posts_custom_column', [ __CLASS__, 'render_achievement_column' ], 10, 2 );

        // Admin-Spalten für ha_credential
        add_filter( 'manage_ha_credential_posts_columns', [ __CLASS__, 'filter_credential_columns' ] );
        add_action( 'manage_ha_credential_posts_custom_column', [ __CLASS__, 'render_credential_column' ], 10, 2 );

        // Template Loader für öffentliche Urkunden-Ansicht
        add_filter( 'template_include', [ __CLASS__, 'load_credential_template' ] );
    }

    /**
     * Registriert die beiden Custom Post Types.
     */
    public static function register_post_types() {
        // 1. CPT ha_achievement (Badges, Erfolge)
        $achievement_labels = [
            'name'               => _x( 'Errungenschaften & Badges', 'post type general name', 'ha-achievement-api' ),
            'singular_name'      => _x( 'Errungenschaft', 'post type singular name', 'ha-achievement-api' ),
            'menu_name'          => _x( 'Badges', 'admin menu', 'ha-achievement-api' ),
            'add_new'            => __( 'Neue Errungenschaft', 'ha-achievement-api' ),
            'add_new_item'       => __( 'Neue Errungenschaft hinzufügen', 'ha-achievement-api' ),
            'edit_item'          => __( 'Errungenschaft bearbeiten', 'ha-achievement-api' ),
            'new_item'           => __( 'Neue Errungenschaft', 'ha-achievement-api' ),
            'view_item'          => __( 'Errungenschaft ansehen', 'ha-achievement-api' ),
            'search_items'       => __( 'Errungenschaften durchsuchen', 'ha-achievement-api' ),
            'not_found'          => __( 'Keine Errungenschaften gefunden', 'ha-achievement-api' ),
            'not_found_in_trash' => __( 'Keine Errungenschaften im Papierkorb', 'ha-achievement-api' ),
        ];

        register_post_type( 'ha_achievement', [
            'labels'             => $achievement_labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // Eigener Menübaum
            'query_var'          => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
            'show_in_rest'       => true,
        ] );

        // 2. CPT ha_credential (Offizielle Zertifikate & Diplome)
        $settings = get_option( 'ha_achievement_settings', [] );
        $slug = ! empty( $settings['verification_slug'] ) ? sanitize_title( $settings['verification_slug'] ) : 'zertifikat';

        $credential_labels = [
            'name'               => _x( 'Diplome & Zertifikate', 'post type general name', 'ha-achievement-api' ),
            'singular_name'      => _x( 'Diplom', 'post type singular name', 'ha-achievement-api' ),
            'menu_name'          => _x( 'Diplome', 'admin menu', 'ha-achievement-api' ),
            'add_new'            => __( 'Neues Diplom ausstellen', 'ha-achievement-api' ),
            'add_new_item'       => __( 'Neues Diplom erfassen', 'ha-achievement-api' ),
            'edit_item'          => __( 'Diplom bearbeiten / einsehen', 'ha-achievement-api' ),
            'new_item'           => __( 'Neues Diplom', 'ha-achievement-api' ),
            'view_item'          => __( 'Diplom öffentlich prüfen', 'ha-achievement-api' ),
            'search_items'       => __( 'Diplome durchsuchen', 'ha-achievement-api' ),
            'not_found'          => __( 'Keine Diplome gefunden', 'ha-achievement-api' ),
            'not_found_in_trash' => __( 'Keine Diplome im Papierkorb', 'ha-achievement-api' ),
        ];

        register_post_type( 'ha_credential', [
            'labels'             => $credential_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => $slug, 'with_front' => false ],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => [ 'title', 'author' ],
            'show_in_rest'       => true,
        ] );
    }

    /**
     * Registriert Taxonomien (Kategorien für Badges).
     */
    public static function register_taxonomies() {
        register_taxonomy( 'ha_achievement_cat', [ 'ha_achievement' ], [
            'labels'            => [
                'name'          => __( 'Badge-Kategorien', 'ha-achievement-api' ),
                'singular_name' => __( 'Badge-Kategorie', 'ha-achievement-api' ),
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
        ] );
    }

    /**
     * Registriert Meta-Boxen für CPTs.
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'ha_achievement_details',
            __( 'Errungenschafts-Details & Regeln', 'ha-achievement-api' ),
            [ __CLASS__, 'render_achievement_metabox' ],
            'ha_achievement',
            'normal',
            'high'
        );

        add_meta_box(
            'ha_credential_details',
            __( 'Offizieller Prüfnachweis & Zertifikats-Snapshot', 'ha-achievement-api' ),
            [ __CLASS__, 'render_credential_metabox' ],
            'ha_credential',
            'normal',
            'high'
        );
    }

    /**
     * Rendert die Meta-Box für ha_achievement.
     */
    public static function render_achievement_metabox( $post ) {
        wp_nonce_field( 'ha_achievement_save_meta', 'ha_achievement_nonce' );

        $slug        = get_post_meta( $post->ID, '_ha_slug', true );
        $source      = get_post_meta( $post->ID, '_ha_source', true ) ?: 'ha-bpmn-manager';
        $tier        = get_post_meta( $post->ID, '_ha_tier', true ) ?: 'bronze';
        $xp_reward   = get_post_meta( $post->ID, '_ha_xp_reward', true );
        $xp_reward   = ( '' !== $xp_reward ) ? (int) $xp_reward : 50;
        $is_secret   = get_post_meta( $post->ID, '_ha_is_secret', true );
        $rules_json  = get_post_meta( $post->ID, '_ha_rules', true );
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="ha_slug"><?php esc_html_e( 'Eindeutiger Slug', 'ha-achievement-api' ); ?></label></th>
                <td>
                    <input type="text" id="ha_slug" name="ha_slug" value="<?php echo esc_attr( $slug ); ?>" class="regular-text" placeholder="z. B. bpmn_cross_lane_master" />
                    <p class="description"><?php esc_html_e( 'Wird von Quell-Plugins zur Freischaltung referenziert.', 'ha-achievement-api' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ha_source"><?php esc_html_e( 'Quell-Plugin (Source)', 'ha-achievement-api' ); ?></label></th>
                <td>
                    <input type="text" id="ha_source" name="ha_source" value="<?php echo esc_attr( $source ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'z. B. ha-bpmn-manager, core, quiz-engine', 'ha-achievement-api' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ha_tier"><?php esc_html_e( 'Medaillen-Stufe (Tier)', 'ha-achievement-api' ); ?></label></th>
                <td>
                    <select id="ha_tier" name="ha_tier">
                        <option value="bronze" <?php selected( $tier, 'bronze' ); ?>>🥉 Bronze</option>
                        <option value="silver" <?php selected( $tier, 'silver' ); ?>>🥈 Silber</option>
                        <option value="gold" <?php selected( $tier, 'gold' ); ?>>🥇 Gold</option>
                        <option value="platinum" <?php selected( $tier, 'platinum' ); ?>>💎 Platin</option>
                        <option value="titan" <?php selected( $tier, 'titan' ); ?>>👑 Titan</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ha_xp_reward"><?php esc_html_e( 'XP Belohnung', 'ha-achievement-api' ); ?></label></th>
                <td>
                    <input type="number" id="ha_xp_reward" name="ha_xp_reward" value="<?php echo esc_attr( $xp_reward ); ?>" min="0" step="5" class="small-text" /> XP
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Geheime Errungenschaft', 'ha-achievement-api' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="ha_is_secret" value="1" <?php checked( $is_secret, '1' ); ?> />
                        <?php esc_html_e( 'Bis zur Freischaltung vor dem Benutzer verbergen (wird als ??? angezeigt)', 'ha-achievement-api' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ha_rules"><?php esc_html_e( 'Kriterien-Regeln (JSON)', 'ha-achievement-api' ); ?></label></th>
                <td>
                    <textarea id="ha_rules" name="ha_rules" rows="4" class="large-text code"><?php echo esc_textarea( $rules_json ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Optionale Regelbedingung, z. B. {"required_tasks": ["c6_cross_flow", "c6_finish_grandmaster"]}', 'ha-achievement-api' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Rendert die Meta-Box für ha_credential.
     */
    public static function render_credential_metabox( $post ) {
        wp_nonce_field( 'ha_credential_save_meta', 'ha_credential_nonce' );

        $code        = get_post_meta( $post->ID, '_ha_verification_code', true );
        $hash        = get_post_meta( $post->ID, '_ha_credential_hash', true );
        $source      = get_post_meta( $post->ID, '_ha_source', true );
        $type        = get_post_meta( $post->ID, '_ha_credential_type', true );
        $grade       = get_post_meta( $post->ID, '_ha_honor_grade', true );
        $xp          = get_post_meta( $post->ID, '_ha_total_xp', true );
        $issued_at   = get_post_meta( $post->ID, '_ha_issued_at', true );
        $is_revoked  = get_post_meta( $post->ID, '_ha_is_revoked', true );
        $rev_reason  = get_post_meta( $post->ID, '_ha_revocation_reason', true );
        $svg         = get_post_meta( $post->ID, '_ha_certificate_svg', true );

        $verify_url  = get_permalink( $post->ID );
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Verifizierungs-Code', 'ha-achievement-api' ); ?></th>
                <td>
                    <strong style="font-size: 1.3em; font-family: monospace; color: #1e1e2f; background: #eef2ff; padding: 4px 10px; border-radius: 4px; border: 1px solid #c7d2fe;">
                        <?php echo esc_html( $code ?: 'NOCH NICHT GENERIERT' ); ?>
                    </strong>
                    <?php if ( $verify_url ) : ?>
                        <a href="<?php echo esc_url( $verify_url ); ?>" target="_blank" class="button button-secondary" style="margin-left: 10px;">
                            <?php esc_html_e( 'Öffentliche Prüfseite öffnen ↗', 'ha-achievement-api' ); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Kryptografischer Hash (SHA-256)', 'ha-achievement-api' ); ?></th>
                <td>
                    <code style="word-break: break-all; font-size: 11px;"><?php echo esc_html( $hash ?: '-' ); ?></code>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ha_honor_grade"><?php esc_html_e( 'Akademisches Prädikat / Note', 'ha-achievement-api' ); ?></label></th>
                <td>
                    <input type="text" id="ha_honor_grade" name="ha_honor_grade" value="<?php echo esc_attr( $grade ); ?>" class="regular-text" placeholder="z. B. EGREGIA CUM LAUDE" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ha_total_xp"><?php esc_html_e( 'Punkte bei Verleihung (XP)', 'ha-achievement-api' ); ?></label></th>
                <td>
                    <input type="number" id="ha_total_xp" name="ha_total_xp" value="<?php echo esc_attr( $xp ); ?>" class="small-text" /> XP
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Ausgestellt am', 'ha-achievement-api' ); ?></th>
                <td>
                    <span><?php echo esc_html( $issued_at ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $issued_at ) ) : '-' ); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Gültigkeits-Status', 'ha-achievement-api' ); ?></th>
                <td>
                    <label style="color: #b91c1c; font-weight: bold;">
                        <input type="checkbox" name="ha_is_revoked" value="1" <?php checked( $is_revoked, '1' ); ?> />
                        <?php esc_html_e( 'Dieses Diplom widerrufen / als ungültig erklären', 'ha-achievement-api' ); ?>
                    </label>
                    <div style="margin-top: 6px;">
                        <input type="text" name="ha_revocation_reason" value="<?php echo esc_attr( $rev_reason ); ?>" placeholder="<?php esc_attr_e( 'Grund des Widerrufs (z. B. Prüfungsanfechtung)', 'ha-achievement-api' ); ?>" class="regular-text" />
                    </div>
                </td>
            </tr>
            <?php if ( ! empty( $svg ) ) : ?>
            <tr>
                <th scope="row"><?php esc_html_e( 'Diplom-Vorschau (SVG)', 'ha-achievement-api' ); ?></th>
                <td>
                    <div style="max-width: 650px; background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                        <?php echo $svg; // SVG darf direkt ausgegeben werden ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Speichert die Meta-Box-Werte.
     */
    public static function save_meta_boxes( $post_id, $post ) {
        // Autosave oder Revisionen ignorieren
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // ha_achievement speichern
        if ( 'ha_achievement' === $post->post_type && isset( $_POST['ha_achievement_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['ha_achievement_nonce'], 'ha_achievement_save_meta' ) ) {
                return;
            }
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            if ( isset( $_POST['ha_slug'] ) ) {
                update_post_meta( $post_id, '_ha_slug', sanitize_title( $_POST['ha_slug'] ) );
            }
            if ( isset( $_POST['ha_source'] ) ) {
                update_post_meta( $post_id, '_ha_source', sanitize_text_field( $_POST['ha_source'] ) );
            }
            if ( isset( $_POST['ha_tier'] ) ) {
                update_post_meta( $post_id, '_ha_tier', sanitize_key( $_POST['ha_tier'] ) );
            }
            if ( isset( $_POST['ha_xp_reward'] ) ) {
                update_post_meta( $post_id, '_ha_xp_reward', absint( $_POST['ha_xp_reward'] ) );
            }
            update_post_meta( $post_id, '_ha_is_secret', ! empty( $_POST['ha_is_secret'] ) ? '1' : '0' );
            if ( isset( $_POST['ha_rules'] ) ) {
                update_post_meta( $post_id, '_ha_rules', wp_unslash( $_POST['ha_rules'] ) );
            }
        }

        // ha_credential speichern
        if ( 'ha_credential' === $post->post_type && isset( $_POST['ha_credential_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['ha_credential_nonce'], 'ha_credential_save_meta' ) ) {
                return;
            }
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            if ( isset( $_POST['ha_honor_grade'] ) ) {
                update_post_meta( $post_id, '_ha_honor_grade', sanitize_text_field( $_POST['ha_honor_grade'] ) );
            }
            if ( isset( $_POST['ha_total_xp'] ) ) {
                update_post_meta( $post_id, '_ha_total_xp', absint( $_POST['ha_total_xp'] ) );
            }
            update_post_meta( $post_id, '_ha_is_revoked', ! empty( $_POST['ha_is_revoked'] ) ? '1' : '0' );
            if ( isset( $_POST['ha_revocation_reason'] ) ) {
                update_post_meta( $post_id, '_ha_revocation_reason', sanitize_text_field( $_POST['ha_revocation_reason'] ) );
            }
        }
    }

    /**
     * Admin-Spalten für ha_achievement anpassen.
     */
    public static function filter_achievement_columns( $columns ) {
        $new_cols = [];
        $new_cols['cb']         = $columns['cb'];
        $new_cols['badge_icon'] = __( 'Icon', 'ha-achievement-api' );
        $new_cols['title']      = __( 'Abzeichen', 'ha-achievement-api' );
        $new_cols['badge_slug'] = __( 'Slug', 'ha-achievement-api' );
        $new_cols['badge_tier'] = __( 'Stufe / Rang', 'ha-achievement-api' );
        $new_cols['xp_reward']  = __( 'XP Belohnung', 'ha-achievement-api' );
        $new_cols['source']     = __( 'Quelle', 'ha-achievement-api' );
        $new_cols['date']       = $columns['date'];
        return $new_cols;
    }

    /**
     * Admin-Spalten-Inhalte für ha_achievement rendern.
     */
    public static function render_achievement_column( $column, $post_id ) {
        switch ( $column ) {
            case 'badge_icon':
                if ( has_post_thumbnail( $post_id ) ) {
                    echo get_the_post_thumbnail( $post_id, [ 40, 40 ], [ 'style' => 'border-radius: 8px; vertical-align: middle;' ] );
                } else {
                    $tier = get_post_meta( $post_id, '_ha_tier', true ) ?: 'bronze';
                    $icons = [
                        'bronze'   => '🥉',
                        'silver'   => '🥈',
                        'gold'     => '🥇',
                        'platinum' => '💎',
                        'titan'    => '👑',
                    ];
                    echo '<span style="font-size: 24px; vertical-align: middle;">' . ( $icons[ $tier ] ?? '🎖️' ) . '</span>';
                }
                break;

            case 'badge_slug':
                $slug = get_post_meta( $post_id, '_ha_slug', true );
                echo '<code>' . esc_html( $slug ?: '-' ) . '</code>';
                break;

            case 'badge_tier':
                $tier = get_post_meta( $post_id, '_ha_tier', true ) ?: 'bronze';
                $colors = [
                    'bronze'   => '#b45309; background: #fef3c7',
                    'silver'   => '#475569; background: #f1f5f9',
                    'gold'     => '#a16207; background: #fef9c3',
                    'platinum' => '#0369a1; background: #e0f2fe',
                    'titan'    => '#701a75; background: #fae8ff',
                ];
                $style = $colors[ $tier ] ?? '#333; background: #eee';
                echo '<span style="display:inline-block; padding: 2px 8px; border-radius: 12px; font-weight:600; font-size:11px; text-transform:uppercase; color:' . $style . ';">' . esc_html( $tier ) . '</span>';
                break;

            case 'xp_reward':
                $xp = get_post_meta( $post_id, '_ha_xp_reward', true );
                echo '<strong style="color: #4338ca;">+' . esc_html( $xp ?: '0' ) . ' XP</strong>';
                break;

            case 'source':
                $source = get_post_meta( $post_id, '_ha_source', true );
                echo '<span class="description">' . esc_html( $source ?: '-' ) . '</span>';
                break;
        }
    }

    /**
     * Admin-Spalten für ha_credential anpassen.
     */
    public static function filter_credential_columns( $columns ) {
        $new_cols = [];
        $new_cols['cb']          = $columns['cb'];
        $new_cols['cert_code']   = __( 'Code', 'ha-achievement-api' );
        $new_cols['title']       = __( 'Urkunde / Titel', 'ha-achievement-api' );
        $new_cols['recipient']   = __( 'Empfänger / User', 'ha-achievement-api' );
        $new_cols['honor_grade'] = __( 'Prädikat', 'ha-achievement-api' );
        $new_cols['total_xp']    = __( 'Gesamt-XP', 'ha-achievement-api' );
        $new_cols['status']      = __( 'Gültigkeit', 'ha-achievement-api' );
        $new_cols['date']        = __( 'Ausstellungsdatum', 'ha-achievement-api' );
        $new_cols['verify_link'] = __( 'Verifikation', 'ha-achievement-api' );
        return $new_cols;
    }

    /**
     * Admin-Spalten-Inhalte für ha_credential rendern.
     */
    public static function render_credential_column( $column, $post_id ) {
        switch ( $column ) {
            case 'cert_code':
                $code = get_post_meta( $post_id, '_ha_verification_code', true );
                echo '<strong style="font-family:monospace; color:#312e81;">' . esc_html( $code ?: '-' ) . '</strong>';
                break;

            case 'recipient':
                $post = get_post( $post_id );
                $user = get_userdata( $post->post_author );
                if ( $user ) {
                    echo '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a>';
                } else {
                    echo '-';
                }
                break;

            case 'honor_grade':
                $grade = get_post_meta( $post_id, '_ha_honor_grade', true );
                echo '<span style="font-weight:600; color:#d97706;">' . esc_html( $grade ?: '-' ) . '</span>';
                break;

            case 'total_xp':
                $xp = get_post_meta( $post_id, '_ha_total_xp', true );
                echo '<strong>' . esc_html( $xp ?: '0' ) . ' XP</strong>';
                break;

            case 'status':
                $is_revoked = get_post_meta( $post_id, '_ha_is_revoked', true );
                if ( $is_revoked ) {
                    echo '<span style="color:#b91c1c; background:#fee2e2; padding:2px 8px; border-radius:12px; font-weight:bold; font-size:11px;">' . esc_html__( 'Widerrufen', 'ha-achievement-api' ) . '</span>';
                } else {
                    echo '<span style="color:#15803d; background:#dcfce7; padding:2px 8px; border-radius:12px; font-weight:bold; font-size:11px;">' . esc_html__( 'Gültig', 'ha-achievement-api' ) . '</span>';
                }
                break;

            case 'verify_link':
                $link = get_permalink( $post_id );
                echo '<a href="' . esc_url( $link ) . '" target="_blank" class="button button-small">' . esc_html__( 'Prüfen ↗', 'ha-achievement-api' ) . '</a>';
                break;
        }
    }

    /**
     * Lädt das öffentliche Zertifikats-Template, falls kein Theme-Template existiert.
     */
    public static function load_credential_template( $template ) {
        if ( is_singular( 'ha_credential' ) ) {
            $plugin_template = HA_ACHIEVEMENT_PATH . 'templates/single-ha-credential.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
        return $template;
    }
}
