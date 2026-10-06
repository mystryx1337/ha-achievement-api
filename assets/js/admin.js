/**
 * HA Achievement API - Admin JavaScript
 *
 * @package HA_Achievement_API
 */

jQuery(document).ready(function ($) {
    // Bestätigung vor Reset-Aktionen
    $('.button-link-delete').on('click', function () {
        return confirm('Möchtest du diese Aktion wirklich ausführen?');
    });
});
