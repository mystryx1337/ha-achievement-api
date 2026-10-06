<?php
/**
 * Uninstallation routine for HA Achievement API.
 *
 * Fired when the plugin is uninstalled via WordPress admin.
 *
 * SICHERHEIT: Standardmäßig sicher!
 * Alle Benutzer-XP, Stufen, Aufgabenfortschritte, Badges und vergebenen Urkunden
 * bleiben standardmäßig in der Datenbank erhalten.
 * Nur wenn der Administrator dies vorab in den Plugin-Einstellungen unter
 * „Gefahrenzone: Deinstallation & Datenbereinigung“ explizit aktiviert hat,
 * werden Daten gelöscht.
 *
 * @package HA_Achievement_API
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Einstellungen abfragen
$settings = get_option( 'ha_achievement_settings', [] );

// SICHERHEITSPRÜFUNG: Standardmäßig sicher!
// Daten werden NUR gelöscht, wenn der Administrator dies zuvor explizit aktiviert hat.
if ( empty( $settings ) || ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) || 1 !== (int) $settings['delete_data_on_uninstall'] ) {
	return;
}

// 1. Alle Transients löschen
delete_transient( 'ha_leaderboard_top50' );
delete_transient( 'ha_leaderboard_top10' );
delete_transient( 'ha_leaderboard_all_10' );
delete_transient( 'ha_leaderboard_all_50' );

// 2. Plugin-Optionen löschen
delete_option( 'ha_achievement_settings' );
delete_option( 'ha_achievement_task_catalog' );

// 3. User-Meta bereinigen
delete_metadata( 'user', 0, 'ha_total_xp', '', true );
delete_metadata( 'user', 0, 'ha_level', '', true );
delete_metadata( 'user', 0, 'ha_rank_title', '', true );
delete_metadata( 'user', 0, 'ha_streak_days', '', true );
delete_metadata( 'user', 0, 'ha_last_activity', '', true );
delete_metadata( 'user', 0, 'ha_unlocked_achievements', '', true );
delete_metadata( 'user', 0, 'ha_tasks_progress', '', true );
delete_metadata( 'user', 0, 'ha_activity_log', '', true );

// 4. Posts der CPTs löschen
$post_types = [ 'ha_achievement', 'ha_credential' ];
foreach ( $post_types as $pt ) {
	$posts = get_posts( [
		'post_type'   => $pt,
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	] );

	if ( ! empty( $posts ) ) {
		foreach ( $posts as $p_id ) {
			wp_delete_post( $p_id, true );
		}
	}
}
