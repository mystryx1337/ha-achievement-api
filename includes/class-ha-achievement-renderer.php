<?php
/**
 * Frontend-Renderer, Shortcodes & Widget-Ausgabe für HA Achievement API
 *
 * Stellt interaktive Shortcodes bereit:
 * - [ha_achievement_learning_dashboard] (Lern-Missions-Dashboard)
 * - [ha_achievement_profile_card] (Benutzer-Profilkarte)
 * - [ha_achievement_badges] (Medaillen-Vitrine)
 * - [ha_achievement_leaderboard] (Bestenliste / Rangliste)
 * - [ha_achievement_certificate_verify] (Urkunden-Prüfmaske)
 *
 * @package HA_Achievement_API
 */

namespace HA_Achievement;

defined( 'ABSPATH' ) || exit;

class Renderer {

    /**
     * Initialisiert alle Shortcodes.
     */
    public static function init() {
        add_shortcode( 'ha_achievement_learning_dashboard', [ __CLASS__, 'render_learning_dashboard' ] );
        add_shortcode( 'ha_achievement_profile_card', [ __CLASS__, 'render_profile_card' ] );
        add_shortcode( 'ha_achievement_badges', [ __CLASS__, 'render_badges' ] );
        add_shortcode( 'ha_achievement_leaderboard', [ __CLASS__, 'render_leaderboard' ] );
        add_shortcode( 'ha_achievement_certificate_verify', [ __CLASS__, 'render_certificate_verify' ] );
    }

    /**
     * Rendert das interaktive Lern-Missions-Dashboard.
     * [ha_achievement_learning_dashboard source="ha-bpmn-manager"]
     */
    public static function render_learning_dashboard( $atts ) {
        wp_enqueue_style( 'ha-achievement-frontend' );
        wp_enqueue_script( 'ha-achievement-frontend' );

        $atts = shortcode_atts( [
            'source'       => '',
            'user_id'      => get_current_user_id(),
            'show_filters' => 'yes',
            'editor_url'   => '',
            'collapsed'    => 'yes',
        ], $atts, 'ha_achievement_learning_dashboard' );

        $user_id = (int) $atts['user_id'];
        if ( ! $user_id ) {
            return '<div class="ha-achievement-notice">' . esc_html__( 'Bitte melde dich an, um deine persönlichen Lernaufgaben und Missionen zu sehen.', 'ha-achievement-api' ) . '</div>';
        }

        $source = ! empty( $atts['source'] ) ? sanitize_text_field( $atts['source'] ) : null;
        $tasks  = API::get_user_tasks( $user_id, $source );

        if ( empty( $tasks ) ) {
            return '<div class="ha-achievement-notice">' . esc_html__( 'Aktuell sind noch keine Lernaufgaben registriert.', 'ha-achievement-api' ) . '</div>';
        }

        // Editor URL Fallback ermitteln
        $editor_url = $atts['editor_url'];
        if ( empty( $editor_url ) ) {
            $editor_url = admin_url( 'admin.php?page=ha-bpmn-manager-editor' );
        }

        // Berechne Gesamtzahlen
        $total_count     = count( $tasks );
        $completed_count = 0;
        $total_xp_pool   = 0;
        $earned_xp       = 0;

        // Quell-Informationen ermitteln
        $source_label = '';
        $source_icon  = '';

        // Nach Kapiteln gruppieren
        $chapters = [];

        foreach ( $tasks as $key => $task ) {
            $total_xp_pool += $task['xp_value'];
            if ( ! empty( $task['is_completed'] ) ) {
                $completed_count++;
                $earned_xp += $task['xp_value'];
            }

            if ( ! $source_label && ! empty( $task['source_label'] ) ) {
                $source_label = $task['source_label'];
            }
            if ( ! $source_icon && ! empty( $task['source_icon'] ) ) {
                $source_icon = $task['source_icon'];
            }

            $chap_key = $task['chapter_key'] ?: 'general';
            if ( ! isset( $chapters[ $chap_key ] ) ) {
                $chapters[ $chap_key ] = [
                    'key'   => $chap_key,
                    'title' => $task['chapter_title'] ?: __( 'Allgemeine Aufgaben', 'ha-achievement-api' ),
                    'icon'  => $task['chapter_icon'] ?: '📋',
                    'tasks' => [],
                ];
            }
            $chapters[ $chap_key ]['tasks'][] = $task;
        }

        if ( ! $source_label ) {
            $source_label = __( 'BPMN 2.0 Prozess-Studio', 'ha-achievement-api' );
        }
        if ( ! $source_icon ) {
            $source_icon = '📐';
        }

        $percent = $total_count > 0 ? (int) round( ( $completed_count / $total_count ) * 100 ) : 0;

        // Finde die erste offene Aufgabe für den Fokus-Teaser
        $next_task = null;
        foreach ( $tasks as $t ) {
            if ( empty( $t['is_completed'] ) ) {
                $next_task = $t;
                break;
            }
        }

        $total_user_xp = User::get_xp( $user_id );
        $user_level    = User::calculate_level( $total_user_xp );
        $user_progress = User::get_level_progress( $total_user_xp, $user_level );

        $is_collapsed = ( 'no' !== $atts['collapsed'] );

        ob_start();
        ?>
        <div class="ha-missions-container <?php echo ( ! $is_collapsed ) ? 'is-expanded' : ''; ?>" data-user-id="<?php echo esc_attr( $user_id ); ?>">
            <!-- 1. REDUZIERTE TEASER-KARTE (Ideal für Seitenleisten & Neugier-Weckung) -->
            <div class="ha-teaser-card">
                <div class="ha-teaser-top">
                    <div class="ha-teaser-brand">
                        <div class="ha-beacon-wrap" title="<?php esc_attr_e( 'Interaktive Lernmission aktiv', 'ha-achievement-api' ); ?>">
                            <span class="ha-beacon-ring"></span>
                            <span class="ha-beacon-dot"></span>
                            <span class="ha-beacon-icon"><?php echo esc_html( $source_icon ); ?></span>
                        </div>
                        <div class="ha-teaser-titles">
                            <div class="ha-teaser-kicker-row">
                                <span class="ha-teaser-kicker"><?php esc_html_e( 'Lern-Zertifizierung', 'ha-achievement-api' ); ?></span>
                                <span class="ha-teaser-lvl-tag"><?php printf( esc_html__( 'Stufe %d', 'ha-achievement-api' ), $user_level ); ?></span>
                            </div>
                            <strong class="ha-teaser-source"><?php echo esc_html( $source_label ); ?></strong>
                        </div>
                    </div>

                    <div class="ha-teaser-xp-pill" title="<?php printf( esc_attr__( '%d von %d XP gesammelt', 'ha-achievement-api' ), $earned_xp, $total_xp_pool ); ?>">
                        <span class="ha-xp-val">+<?php echo esc_html( $earned_xp ); ?></span><span class="ha-xp-sep">/</span><span class="ha-xp-max"><?php echo esc_html( $total_xp_pool ); ?> XP</span>
                    </div>
                </div>

                <div class="ha-progress-block">
                    <div class="ha-progress-meta">
                        <span class="ha-progress-text"><strong><?php echo esc_html( $completed_count ); ?></strong>/<?php echo esc_html( $total_count ); ?> gelöst (<?php echo esc_html( $percent ); ?>%)</span>
                        <span class="ha-progress-next-lvl" title="<?php esc_attr_e( 'Fortschritt zur nächsten Stufe', 'ha-achievement-api' ); ?>">
                            <?php printf( esc_html__( 'Noch %d XP bis Stufe %d', 'ha-achievement-api' ), $user_progress['remaining_xp'], $user_level + 1 ); ?>
                        </span>
                    </div>
                    <div class="ha-progress-track">
                        <div class="ha-progress-bar" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
                    </div>
                </div>

                <?php if ( $next_task ) : 
                    $next_url        = ! empty( $next_task['action_url'] ) ? $next_task['action_url'] : $editor_url;
                    $next_action_lbl = ! empty( $next_task['action_label'] ) ? $next_task['action_label'] : __( 'Im BPMN-Studio fortsetzen ▶', 'ha-achievement-api' );
                ?>
                    <div class="ha-teaser-focus" role="button" tabindex="0" title="<?php esc_attr_e( 'Klicken, um alle Aufgaben aufzuklappen', 'ha-achievement-api' ); ?>">
                        <div class="ha-focus-lead">
                            <span class="ha-focus-tag"><?php esc_html_e( 'Nächster Schritt', 'ha-achievement-api' ); ?></span>
                            <span class="ha-focus-xp">+<?php echo esc_html( $next_task['xp_value'] ); ?> XP</span>
                        </div>
                        <div class="ha-focus-title-row">
                            <div class="ha-focus-title"><?php echo esc_html( $next_task['title'] ); ?></div>
                            <span class="ha-focus-chevron">▾</span>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="ha-teaser-focus ha-focus-done">
                        <div class="ha-focus-lead">
                            <span class="ha-focus-tag ha-tag-done">✓ <?php esc_html_e( 'Abschluss erreicht', 'ha-achievement-api' ); ?></span>
                        </div>
                        <div class="ha-focus-title"><?php esc_html_e( 'Alle Lernaufgaben erfolgreich abgeschlossen!', 'ha-achievement-api' ); ?></div>
                    </div>
                <?php endif; ?>

                <div class="ha-teaser-actions">
                    <?php if ( $next_task ) : ?>
                        <a href="<?php echo esc_url( $next_url ); ?>" class="ha-btn-primary ha-btn-continue" title="<?php esc_attr_e( 'Im BPMN-Studio öffnen', 'ha-achievement-api' ); ?>">
                            <span><?php echo esc_html( $next_action_lbl ); ?></span>
                        </a>
                    <?php endif; ?>

                    <button type="button" class="ha-btn-secondary ha-dropdown-toggle" aria-expanded="<?php echo $is_collapsed ? 'false' : 'true'; ?>" data-expand-text="<?php esc_attr_e( 'Aufgaben ▾', 'ha-achievement-api' ); ?>" data-collapse-text="<?php esc_attr_e( 'Schließen ▴', 'ha-achievement-api' ); ?>">
                        <span class="ha-toggle-sparkle">✦</span>
                        <span class="ha-toggle-text"><?php echo $is_collapsed ? esc_html__( 'Aufgaben ▾', 'ha-achievement-api' ) : esc_html__( 'Schließen ▴', 'ha-achievement-api' ); ?></span>
                    </button>
                </div>
            </div>

            <!-- 2. AUSKLAPPBARES DROPDOWN (Alle Kapitel & Details) -->
            <div class="ha-missions-dropdown-drawer">
                <div class="ha-drawer-inner">
                    <?php if ( 'yes' === $atts['show_filters'] ) : ?>
                    <div class="ha-missions-filters">
                        <button type="button" class="ha-filter-btn active" data-filter="all"><?php esc_html_e( 'Alle', 'ha-achievement-api' ); ?> (<?php echo esc_html( $total_count ); ?>)</button>
                        <button type="button" class="ha-filter-btn" data-filter="pending"><?php esc_html_e( 'Offen', 'ha-achievement-api' ); ?> (<?php echo esc_html( $total_count - $completed_count ); ?>)</button>
                        <button type="button" class="ha-filter-btn" data-filter="completed"><?php esc_html_e( 'Erledigt', 'ha-achievement-api' ); ?> (<?php echo esc_html( $completed_count ); ?>)</button>
                    </div>
                    <?php endif; ?>

                    <div class="ha-chapters-accordion">
                        <?php foreach ( $chapters as $chap ) : 
                            $chap_total = count( $chap['tasks'] );
                            $chap_done  = count( array_filter( $chap['tasks'], function( $t ) { return ! empty( $t['is_completed'] ); } ) );
                            $chap_is_finished = ( $chap_total > 0 && $chap_done === $chap_total );

                            // Erste offene Aufgabe des Kapitels
                            $chap_next_url   = null;
                            $chap_action_lbl = __( 'Im BPMN-Studio fortsetzen ▶', 'ha-achievement-api' );
                            $chap_source_lbl = '';
                            $chap_source_icn = '📐';

                            foreach ( $chap['tasks'] as $ct ) {
                                if ( ! $chap_source_lbl && ! empty( $ct['source_label'] ) ) {
                                    $chap_source_lbl = $ct['source_label'];
                                    $chap_source_icn = $ct['source_icon'] ?? '📐';
                                }
                                if ( empty( $ct['is_completed'] ) && ! $chap_next_url ) {
                                    $chap_next_url = ! empty( $ct['action_url'] ) ? $ct['action_url'] : $editor_url;
                                    if ( ! empty( $ct['action_label'] ) ) {
                                        $chap_action_lbl = $ct['action_label'];
                                    }
                                }
                            }
                        ?>
                            <div class="ha-chapter-block <?php echo $chap_is_finished ? 'is-complete' : ''; ?>" data-chapter="<?php echo esc_attr( $chap['key'] ); ?>">
                                <div class="ha-chapter-header">
                                    <span class="ha-chapter-icon"><?php echo esc_html( $chap['icon'] ); ?></span>
                                    <div class="ha-chapter-info">
                                        <span class="ha-chapter-name"><?php echo esc_html( $chap['title'] ); ?></span>
                                        <?php if ( $chap_source_lbl ) : ?>
                                            <span class="ha-source-pill-mini" title="<?php echo esc_attr( $chap_source_lbl ); ?>">
                                                <?php echo esc_html( $chap_source_icn ); ?> <?php echo esc_html( $chap_source_lbl ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="ha-chapter-right">
                                        <?php if ( ! $chap_is_finished && $chap_next_url ) : ?>
                                            <a href="<?php echo esc_url( $chap_next_url ); ?>" class="ha-chapter-quick-cta" onclick="event.stopPropagation();" title="<?php esc_attr_e( 'Im BPMN-Studio öffnen', 'ha-achievement-api' ); ?>">
                                                <?php echo esc_html( $chap_action_lbl ); ?>
                                            </a>
                                        <?php endif; ?>
                                        <span class="ha-chapter-badge <?php echo $chap_is_finished ? 'badge-success' : 'badge-pending'; ?>">
                                            <?php echo esc_html( $chap_done ); ?> / <?php echo esc_html( $chap_total ); ?>
                                        </span>
                                        <span class="ha-accordion-toggle">▼</span>
                                    </div>
                                </div>

                                <div class="ha-chapter-body">
                                    <ul class="ha-task-list">
                                        <?php foreach ( $chap['tasks'] as $t ) : 
                                            $is_done    = ! empty( $t['is_completed'] );
                                            $action_url = ! empty( $t['action_url'] ) ? $t['action_url'] : $editor_url;
                                            $action_lbl = ! empty( $t['action_label'] ) ? $t['action_label'] : __( 'Im BPMN-Studio fortsetzen ▶', 'ha-achievement-api' );
                                            $source_lbl = ! empty( $t['source_label'] ) ? $t['source_label'] : __( 'BPMN Studio', 'ha-achievement-api' );
                                            $source_icn = ! empty( $t['source_icon'] ) ? $t['source_icon'] : '📐';
                                        ?>
                                            <li class="ha-task-item <?php echo $is_done ? 'task-completed' : 'task-pending'; ?>" data-status="<?php echo $is_done ? 'completed' : 'pending'; ?>">
                                                <div class="ha-task-status-icon">
                                                    <?php if ( $is_done ) : ?>
                                                        <span class="ha-icon-check" title="<?php esc_attr_e( 'Erledigt', 'ha-achievement-api' ); ?>">✓</span>
                                                    <?php else : ?>
                                                        <span class="ha-icon-circle" title="<?php esc_attr_e( 'Noch offen', 'ha-achievement-api' ); ?>">○</span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="ha-task-content">
                                                    <div class="ha-task-title-row">
                                                        <strong class="ha-task-title"><?php echo esc_html( $t['title'] ); ?></strong>
                                                        <span class="ha-source-pill-micro" title="<?php echo esc_attr( $source_lbl ); ?>">
                                                            <?php echo esc_html( $source_icn ); ?> <?php echo esc_html( $source_lbl ); ?>
                                                        </span>
                                                        <span class="ha-task-xp">+<?php echo esc_html( $t['xp_value'] ); ?> XP</span>
                                                    </div>

                                                    <?php if ( ! empty( $t['hint'] ) ) : ?>
                                                        <div class="ha-task-hint">
                                                            <span class="ha-hint-icon">💡</span> <?php echo esc_html( $t['hint'] ); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ( $is_done && ! empty( $t['completed_at'] ) ) : ?>
                                                        <div class="ha-task-timestamp">
                                                            <?php printf( esc_html__( 'Erledigt am %s', 'ha-achievement-api' ), esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t['completed_at'] ) ) ); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="ha-task-action">
                                                    <?php if ( ! $is_done && $action_url ) : ?>
                                                        <a href="<?php echo esc_url( $action_url ); ?>" class="ha-action-btn" title="<?php esc_attr_e( 'Im BPMN-Studio fortsetzen', 'ha-achievement-api' ); ?>">
                                                            <?php echo esc_html( $action_lbl ); ?>
                                                        </a>
                                                    <?php elseif ( $is_done ) : ?>
                                                        <span class="ha-done-pill"><?php esc_html_e( '✓ Gelöst', 'ha-achievement-api' ); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Rendert die Profilkarte eines Benutzers.
     * [ha_achievement_profile_card]
     */
    public static function render_profile_card( $atts ) {
        wp_enqueue_style( 'ha-achievement-frontend' );

        $atts = shortcode_atts( [
            'user_id' => get_current_user_id(),
        ], $atts, 'ha_achievement_profile_card' );

        $user_id = (int) $atts['user_id'];
        if ( ! $user_id ) {
            return '';
        }

        $profile = API::get_user_profile( $user_id );
        if ( empty( $profile ) ) {
            return '';
        }

        $progress = $profile['level_progress'];

        ob_start();
        ?>
        <div class="ha-profile-card">
            <div class="ha-profile-card-header">
                <img src="<?php echo esc_url( $profile['avatar_url'] ); ?>" alt="<?php echo esc_attr( $profile['display_name'] ); ?>" class="ha-profile-avatar" />
                <div class="ha-profile-main-info">
                    <h4 class="ha-profile-name"><?php echo esc_html( $profile['display_name'] ); ?></h4>
                    <div class="ha-profile-rank-badge">
                        <span class="ha-rank-icon">👑</span> <?php echo esc_html( $profile['rank_title'] ); ?>
                    </div>
                </div>
                <div class="ha-profile-level-bubble">
                    <span class="ha-bubble-label"><?php esc_html_e( 'STUFE', 'ha-achievement-api' ); ?></span>
                    <span class="ha-bubble-val"><?php echo esc_html( $profile['level'] ); ?></span>
                </div>
            </div>

            <div class="ha-profile-card-body">
                <div class="ha-xp-info-row">
                    <span><strong><?php echo esc_html( number_format_i18n( $profile['total_xp'] ) ); ?></strong> XP gesamt</span>
                    <span class="ha-next-level"><?php
                        printf(
                            esc_html__( 'Noch %d XP bis Stufe %d', 'ha-achievement-api' ),
                            (int) $progress['remaining_xp'],
                            $profile['level'] + 1
                        );
                    ?></span>
                </div>
                <div class="ha-progress-track">
                    <div class="ha-progress-bar" style="width: <?php echo esc_attr( $progress['percent'] ); ?>%;"></div>
                </div>

                <div class="ha-profile-stats-grid">
                    <div class="ha-stat-box">
                        <span class="ha-stat-icon">🔥</span>
                        <strong class="ha-stat-num"><?php echo esc_html( $profile['streak_days'] ); ?></strong>
                        <span class="ha-stat-lbl"><?php esc_html_e( 'Tage Streak', 'ha-achievement-api' ); ?></span>
                    </div>
                    <div class="ha-stat-box">
                        <span class="ha-stat-icon">🎯</span>
                        <strong class="ha-stat-num"><?php echo esc_html( $profile['completed_tasks'] ); ?></strong>
                        <span class="ha-stat-lbl"><?php esc_html_e( 'Aufgaben', 'ha-achievement-api' ); ?></span>
                    </div>
                    <div class="ha-stat-box">
                        <span class="ha-stat-icon">🎖️</span>
                        <strong class="ha-stat-num"><?php echo esc_html( $profile['unlocked_badges'] ); ?></strong>
                        <span class="ha-stat-lbl"><?php esc_html_e( 'Abzeichen', 'ha-achievement-api' ); ?></span>
                    </div>
                    <div class="ha-stat-box">
                        <span class="ha-stat-icon">📜</span>
                        <strong class="ha-stat-num"><?php echo esc_html( $profile['issued_diplomas'] ); ?></strong>
                        <span class="ha-stat-lbl"><?php esc_html_e( 'Diplome', 'ha-achievement-api' ); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Rendert die Medaillen-Vitrine (Badges).
     * [ha_achievement_badges]
     */
    public static function render_badges( $atts ) {
        wp_enqueue_style( 'ha-achievement-frontend' );

        $atts = shortcode_atts( [
            'user_id' => get_current_user_id(),
        ], $atts, 'ha_achievement_badges' );

        $user_id = (int) $atts['user_id'];
        $unlocked = (array) get_user_meta( $user_id, 'ha_unlocked_achievements', true );

        $badges = get_posts( [
            'post_type'   => 'ha_achievement',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'meta_value_num',
            'meta_key'    => '_ha_xp_reward',
            'order'       => 'ASC',
        ] );

        if ( empty( $badges ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="ha-badges-showcase">
            <h3 class="ha-badges-heading">🎖️ <?php esc_html_e( 'Errungenschaften & Auszeichnungen', 'ha-achievement-api' ); ?></h3>
            <div class="ha-badges-grid">
                <?php foreach ( $badges as $badge ) : 
                    $slug      = get_post_meta( $badge->ID, '_ha_slug', true ) ?: sanitize_title( $badge->post_title );
                    $tier      = get_post_meta( $badge->ID, '_ha_tier', true ) ?: 'bronze';
                    $xp        = (int) get_post_meta( $badge->ID, '_ha_xp_reward', true );
                    $is_secret = get_post_meta( $badge->ID, '_ha_is_secret', true );
                    $has_it    = isset( $unlocked[ $slug ] );

                    if ( $is_secret && ! $has_it ) {
                        $display_title = '???';
                        $display_desc  = __( 'Geheime Errungenschaft. Wird erst nach Erfüllung enthüllt!', 'ha-achievement-api' );
                    } else {
                        $display_title = $badge->post_title;
                        $display_desc  = $badge->post_content;
                    }
                ?>
                    <div class="ha-badge-card <?php echo $has_it ? 'badge-unlocked' : 'badge-locked'; ?> tier-<?php echo esc_attr( $tier ); ?>">
                        <div class="ha-badge-icon-wrap">
                            <?php if ( has_post_thumbnail( $badge->ID ) ) : ?>
                                <?php echo get_the_post_thumbnail( $badge->ID, [ 64, 64 ], [ 'class' => 'ha-badge-img' ] ); ?>
                            <?php else : ?>
                                <span class="ha-badge-emoji">
                                    <?php 
                                    $emojis = [ 'bronze' => '🥉', 'silver' => '🥈', 'gold' => '🥇', 'platinum' => '💎', 'titan' => '👑' ];
                                    echo $emojis[ $tier ] ?? '🎖️';
                                    ?>
                                </span>
                            <?php endif; ?>

                            <?php if ( ! $has_it ) : ?>
                                <span class="ha-lock-overlay" title="<?php esc_attr_e( 'Noch gesperrt', 'ha-achievement-api' ); ?>">🔒</span>
                            <?php endif; ?>
                        </div>

                        <div class="ha-badge-info">
                            <strong class="ha-badge-title"><?php echo esc_html( $display_title ); ?></strong>
                            <p class="ha-badge-desc"><?php echo esc_html( $display_desc ); ?></p>
                            <div class="ha-badge-footer">
                                <span class="ha-badge-tier-tag"><?php echo esc_html( strtoupper( $tier ) ); ?></span>
                                <span class="ha-badge-xp">+<?php echo esc_html( $xp ); ?> XP</span>
                            </div>
                            <?php if ( $has_it && ! empty( $unlocked[ $slug ]['unlocked_at'] ) ) : ?>
                                <div class="ha-badge-unlocked-date">
                                    <?php printf( esc_html__( 'Freigeschaltet am %s', 'ha-achievement-api' ), esc_html( date_i18n( get_option( 'date_format' ), $unlocked[ $slug ]['unlocked_at'] ) ) ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Rendert die Rangliste (Leaderboard).
     * [ha_achievement_leaderboard limit="10"]
     */
    public static function render_leaderboard( $atts ) {
        wp_enqueue_style( 'ha-achievement-frontend' );

        $atts = shortcode_atts( [
            'limit' => 10,
        ], $atts, 'ha_achievement_leaderboard' );

        $limit = max( 3, min( 50, (int) $atts['limit'] ) );
        $board = API::get_leaderboard( $limit );

        if ( empty( $board ) ) {
            return '<div class="ha-achievement-notice">' . esc_html__( 'Die Rangliste wird aktuell noch aufgebaut.', 'ha-achievement-api' ) . '</div>';
        }

        ob_start();
        ?>
        <div class="ha-leaderboard-container">
            <h3 class="ha-leaderboard-title">🏆 <?php esc_html_e( 'Ruhmeshalle & Rangliste', 'ha-achievement-api' ); ?></h3>

            <!-- Top 3 Podest -->
            <?php if ( count( $board ) >= 3 ) : 
                $first  = $board[0];
                $second = $board[1];
                $third  = $board[2];
            ?>
                <div class="ha-podium-wrap">
                    <!-- Platz 2 (Silber) -->
                    <div class="ha-podium-slot slot-2">
                        <img src="<?php echo esc_url( $second['avatar_url'] ); ?>" class="ha-podium-avatar" />
                        <span class="ha-podium-medal">🥈</span>
                        <strong class="ha-podium-name"><?php echo esc_html( $second['display_name'] ); ?></strong>
                        <span class="ha-podium-xp"><?php echo esc_html( $second['total_xp'] ); ?> XP</span>
                        <div class="ha-podium-bar bar-2">2</div>
                    </div>

                    <!-- Platz 1 (Gold) -->
                    <div class="ha-podium-slot slot-1">
                        <span class="ha-crown">👑</span>
                        <img src="<?php echo esc_url( $first['avatar_url'] ); ?>" class="ha-podium-avatar" />
                        <span class="ha-podium-medal">🥇</span>
                        <strong class="ha-podium-name"><?php echo esc_html( $first['display_name'] ); ?></strong>
                        <span class="ha-podium-xp"><?php echo esc_html( $first['total_xp'] ); ?> XP</span>
                        <div class="ha-podium-bar bar-1">1</div>
                    </div>

                    <!-- Platz 3 (Bronze) -->
                    <div class="ha-podium-slot slot-3">
                        <img src="<?php echo esc_url( $third['avatar_url'] ); ?>" class="ha-podium-avatar" />
                        <span class="ha-podium-medal">🥉</span>
                        <strong class="ha-podium-name"><?php echo esc_html( $third['display_name'] ); ?></strong>
                        <span class="ha-podium-xp"><?php echo esc_html( $third['total_xp'] ); ?> XP</span>
                        <div class="ha-podium-bar bar-3">3</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tabellarische Liste -->
            <table class="ha-leaderboard-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Rang', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Teilnehmer', 'ha-achievement-api' ); ?></th>
                        <th><?php esc_html_e( 'Stufe & Rang', 'ha-achievement-api' ); ?></th>
                        <th style="text-align:right;"><?php esc_html_e( 'Punkte (XP)', 'ha-achievement-api' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $board as $row ) : ?>
                        <tr class="<?php echo ( get_current_user_id() === $row['user_id'] ) ? 'is-current-user' : ''; ?>">
                            <td class="col-rank">
                                <span class="ha-rank-badge rank-<?php echo esc_attr( $row['rank'] ); ?>">
                                    #<?php echo esc_html( $row['rank'] ); ?>
                                </span>
                            </td>
                            <td class="col-user">
                                <div class="ha-table-user">
                                    <img src="<?php echo esc_url( $row['avatar_url'] ); ?>" class="ha-table-avatar" />
                                    <span><?php echo esc_html( $row['display_name'] ); ?></span>
                                </div>
                            </td>
                            <td class="col-title">
                                <span class="ha-level-tag">Lvl <?php echo esc_html( $row['level'] ); ?></span>
                                <span class="ha-title-desc"><?php echo esc_html( $row['rank_title'] ); ?></span>
                            </td>
                            <td class="col-xp" style="text-align:right;">
                                <strong><?php echo esc_html( number_format_i18n( $row['total_xp'] ) ); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Rendert die Urkunden-Prüfmaske für Personalabteilungen und Auditoren.
     * [ha_achievement_certificate_verify]
     */
    public static function render_certificate_verify( $atts ) {
        wp_enqueue_style( 'ha-achievement-frontend' );

        $code = isset( $_GET['cert'] ) ? sanitize_text_field( $_GET['cert'] ) : '';
        $verification = null;

        if ( ! empty( $code ) ) {
            $verification = API::verify_credential( $code );
        }

        ob_start();
        ?>
        <div class="ha-verify-container">
            <h3 class="ha-verify-title">🛡️ <?php esc_html_e( 'Offizielles Zertifikats- und Diplom-Register', 'ha-achievement-api' ); ?></h3>
            <p class="ha-verify-subtitle"><?php esc_html_e( 'Geben Sie den Verifizierungs-Code oder den kryptografischen SHA-256 Hash ein, um die Echtheit und Gültigkeit eines ausgestellten Abschlusses zu prüfen.', 'ha-achievement-api' ); ?></p>

            <form method="get" class="ha-verify-form">
                <div class="ha-input-group">
                    <input type="text" name="cert" value="<?php echo esc_attr( $code ); ?>" placeholder="z. B. HA-BPMN-8X9K2M" required class="ha-verify-input" />
                    <button type="submit" class="ha-verify-submit"><?php esc_html_e( 'Echtheit prüfen 🔍', 'ha-achievement-api' ); ?></button>
                </div>
            </form>

            <?php if ( $verification ) : ?>
                <div class="ha-verify-result <?php echo $verification['valid'] ? 'is-valid' : 'is-invalid'; ?>">
                    <?php if ( $verification['valid'] ) : ?>
                        <div class="ha-seal-header">
                            <span class="ha-verified-seal">✅</span>
                            <div class="ha-seal-text">
                                <h4><?php esc_html_e( 'Verifiziertes Original-Zertifikat', 'ha-achievement-api' ); ?></h4>
                                <span class="ha-seal-sub"><?php esc_html_e( 'Konformitätsprüfung nach ISO 19510 bestanden', 'ha-achievement-api' ); ?></span>
                            </div>
                        </div>

                        <div class="ha-cert-details-grid">
                            <div class="ha-detail-item">
                                <span class="ha-detail-lbl"><?php esc_html_e( 'Inhaber / Absolvent:', 'ha-achievement-api' ); ?></span>
                                <strong class="ha-detail-val"><?php echo esc_html( $verification['recipient'] ); ?></strong>
                            </div>
                            <div class="ha-detail-item">
                                <span class="ha-detail-lbl"><?php esc_html_e( 'Prüfcode:', 'ha-achievement-api' ); ?></span>
                                <strong class="ha-detail-val code"><?php echo esc_html( $verification['code'] ); ?></strong>
                            </div>
                            <div class="ha-detail-item">
                                <span class="ha-detail-lbl"><?php esc_html_e( 'Akademisches Prädikat:', 'ha-achievement-api' ); ?></span>
                                <strong class="ha-detail-val grade"><?php echo esc_html( $verification['honor_grade'] ); ?></strong>
                            </div>
                            <div class="ha-detail-item">
                                <span class="ha-detail-lbl"><?php esc_html_e( 'Erreichte Gesamt-XP:', 'ha-achievement-api' ); ?></span>
                                <strong class="ha-detail-val"><?php echo esc_html( $verification['total_xp'] ); ?> XP</strong>
                            </div>
                            <div class="ha-detail-item">
                                <span class="ha-detail-lbl"><?php esc_html_e( 'Ausstellungsdatum:', 'ha-achievement-api' ); ?></span>
                                <strong class="ha-detail-val"><?php echo esc_html( $verification['formatted_date'] ); ?></strong>
                            </div>
                            <div class="ha-detail-item">
                                <span class="ha-detail-lbl"><?php esc_html_e( 'Status:', 'ha-achievement-api' ); ?></span>
                                <strong class="ha-detail-val status-ok">● <?php esc_html_e( 'Gültig & Unwiderrufen', 'ha-achievement-api' ); ?></strong>
                            </div>
                        </div>

                        <?php if ( ! empty( $verification['svg'] ) ) : ?>
                            <div class="ha-cert-svg-preview">
                                <h5><?php esc_html_e( 'Original Vektor-Urkunde', 'ha-achievement-api' ); ?></h5>
                                <div class="ha-svg-box">
                                    <?php echo $verification['svg']; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else : ?>
                        <div class="ha-invalid-box">
                            <span class="ha-invalid-icon">❌</span>
                            <h4><?php echo esc_html( $verification['message'] ); ?></h4>
                            <?php if ( ! empty( $verification['reason'] ) ) : ?>
                                <p><?php printf( esc_html__( 'Begründung: %s', 'ha-achievement-api' ), esc_html( $verification['reason'] ) ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
