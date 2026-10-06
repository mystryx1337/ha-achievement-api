# Entwickler-Dokumentation & Integrations-Leitfaden: HA Achievement API 🏆

> **Zweck dieses Dokuments:** Dieses Handbuch erklärt Schritt für Schritt, wie **beliebige bestehende und künftige WordPress-Plugins** (z. B. `ha-workflow-forms`, `ha-epost`, LMS-, Quiz- oder Onboarding-Plugins) an die zentrale **`ha-achievement-api`** angebunden werden. Es dient sowohl Entwicklern als auch KI-Assistenten als verbindliche Referenz für konsistente, robuste und fehlertolerante Integrationen.

---

## Inhaltsverzeichnis

1. [Architektur & Kernprinzipien](#1-architektur--kernprinzipien)
2. [Die 3 Integrations-Stufen (Übersicht)](#2-die-3-integrations-stufen-übersicht)
3. [5-Minuten-Quickstart: Punkte vergeben & Aufgaben abhaken](#3-5-minuten-quickstart-punkte-vergeben--aufgaben-abhaken)
4. [Schritt-für-Schritt: Eigenes Plugin als Gamification-Quelle registrieren](#4-schritt-für-schritt-eigenes-plugin-als-gamification-quelle-registrieren)
   - [4.1 Quell-Identität & Branding definieren (`ha_achievement_registered_sources`)](#41-quell-identität--branding-definieren)
   - [4.2 Lern- & Aufgabenkatalog hinterlegen (`ha_achievement_registered_tasks`)](#42-lern--aufgabenkatalog-hinterlegen)
   - [4.3 Aufgaben per Code als erledigt markieren](#43-aufgaben-per-code-als-erledigt-markieren)
5. [Eigene Badges, Medaillen & Freischalt-Regeln erstellen](#5-eigene-badges-medaillen--freischalt-regeln-erstellen)
6. [Offiziell verifizierbare Diplome & Zertifikate ausstellen (`ha_credential`)](#6-offiziell-verifizierbare-diplome--zertifikate-ausstellen)
7. [Anzeige im WP-Admin: „Verbundene Plugins“ erweitern](#7-anzeige-im-wp-admin-verbundene-plugins-erweitern)
8. [Auf Ereignisse reagieren (Event-Listener für Belohnungen & Webhooks)](#8-auf-ereignisse-reagieren)
9. [Frontend-Einbindung: Shortcodes & Attribute](#9-frontend-einbindung-shortcodes--attribute)
10. [REST-API Referenz (JavaScript / Single-Page-Apps)](#10-rest-api-referenz)
11. [Vollständige Boilerplate-Klasse zum Kopieren (`class-sample-achievement-integration.php`)](#11-vollständige-boilerplate-klasse-zum-kopieren)

---

## 1. Architektur & Kernprinzipien

Die **`ha-achievement-api`** fungiert als **Single Source of Truth** für Gamification, Lernfortschritte, Benutzer-XP und Abschluss-Urkunden über alle ha-Plugins hinweg:

- **100 % Native WordPress-Architektur**:
  - Keine eigenen MySQL-Tabellen!
  - CPT `ha_achievement` für Badges & Trophäen.
  - CPT `ha_credential` für verifizierbare Zertifikate (inkl. Prüfcode, SHA-256-Hash, SVG-Vektor-Snapshot).
  - Standard `wp_usermeta` für Benutzerdaten: `ha_total_xp`, `ha_level`, `ha_rank_title`, `ha_tasks_progress` (kompakter JSON-Blob aller erledigten Aufgaben), `ha_streak_days`.
  - Transients (`_transient_ha_leaderboard_...`) für **0 ms** Datenbankzeit bei Leaderboard-Aufrufen.
- **Resilienz & Graceful Degradation**:
  - Angeschlossene Plugins müssen **immer** fehlerfrei funktionieren, selbst wenn `ha-achievement-api` nicht installiert oder deaktiviert ist.
  - Integrationen nutzen primär **WordPress Action- und Filter-Hooks** (`do_action()`, `apply_filters()`), um fatale PHP-Fehler auszuschließen.
- **Multi-Source Skalierbarkeit**:
  - Jedes Plugin hat einen eindeutigen Quell-Identifikator (z. B. `'ha-bpmn-manager'`, `'ha-workflow-forms'`, `'ha-quiz'`).
  - Aufgaben, XP und Diplome bleiben quellenspezifisch zuordenbar, fließen aber in den globalen Gesamt-Level des Nutzers ein.

---

## 2. Die 3 Integrations-Stufen (Übersicht)

Je nach Art Ihres Plugins stehen drei Wege zur Verfügung:

| Stufe | Integrations-Typ | Wann verwenden? | Fatale Fehler bei Fehlen von ha-achievement-api? |
|---|---|---|---|
| **Stufe 1** | **Action-Hooks** (`do_action()`) | Standard-PHP-Code (Formular abgesendet, Post gespeichert, Quiz gelöst). | **Ausgeschlossen** (WordPress ignoriert unregistrierte Actions). |
| **Stufe 2** | **Direkter Service-Layer** (`\HA_Achievement\API::*`) | Tiefe PHP-Integration, wenn Rückgabewerte (z. B. neuer Level, Diplom-Prüfcode) sofort benötigt werden. | Verhindert durch `class_exists( '\HA_Achievement\API' )`. |
| **Stufe 3** | **REST-API / Client-Side** (`/wp-json/ha-achievement/v1/...`) | JavaScript Single-Page-Apps, Canvas-Editoren, Gutenberg-Blöcke oder Frontend-AJAX. | Verhindert durch HTTP-Statusprüfungen (Fallback auf `localStorage`). |

---

## 3. 5-Minuten-Quickstart: Punkte vergeben & Aufgaben abhaken

### 3.1 Erfahrungspunkte (XP) gutschreiben (Stufe 1 – Empfohlen)
Einfach an beliebiger Stelle in Ihrem Plugin aufrufen:

```php
/**
 * Schreibt einem Benutzer XP gut.
 *
 * Parameter:
 * 1. int    $user_id  - ID des WordPress-Benutzers
 * 2. int    $xp       - Anzahl der Punkte (z. B. 25)
 * 3. string $source   - Slug Ihres Plugins (z. B. 'ha-workflow-forms')
 * 4. string $reason   - Begründung für das Aktivitäten-Log
 * 5. array  $meta     - Optionale Metadaten
 */
do_action( 'ha_achievement_award_xp', $user_id, 25, 'ha-workflow-forms', 'Erstes Workflow-Formular angelegt' );
```

### 3.2 Eine Teilaufgabe als gelöst abhaken
```php
/**
 * Hakt eine Aufgabe im Benutzer-Fortschritt ab.
 * Schreibt automatisch die Standard-XP gut und prüft verknüpfte Badges!
 *
 * Parameter:
 * 1. int    $user_id   - Benutzer-ID
 * 2. string $task_key  - Aufgaben-ID (z. B. 'wf_create_approval_flow')
 * 3. string $source    - Slug Ihres Plugins
 * 4. array  $meta      - Optionale Zusatzdaten
 */
do_action( 'ha_achievement_complete_task', $user_id, 'wf_create_approval_flow', 'ha-workflow-forms' );
```

---

## 4. Schritt-für-Schritt: Eigenes Plugin als Gamification-Quelle registrieren

Damit Ihre Aufgaben im interaktiven **Missions-Dashboard** (`[ha_achievement_learning_dashboard]`) mit eigenem Logo, Firmenfarbe und direkten Klick-Buttons zum Weitermachen erscheinen:

### 4.1 Quell-Identität & Branding definieren
Klinken Sie sich in den Filter `ha_achievement_registered_sources` ein:

```php
add_filter( 'ha_achievement_registered_sources', function( array $sources ) {
    $sources['ha-workflow-forms'] = [
        'slug'        => 'ha-workflow-forms',
        'name'        => 'HA Workflow Forms',
        'label'       => 'Workflow- & Formular-Studio',
        'icon'        => '📝',                // Emoji oder SVG
        'color'       => '#059669',             // Hauptfarbe (Hex)
        'bg'          => '#ecfdf5',             // Heller Hintergrund für Badges/Pills
        'action_label'=> 'Im Formular-Editor fortsetzen ▶',
    ];
    return $sources;
} );
```

### 4.2 Lern- & Aufgabenkatalog hinterlegen
Es gibt zwei Wege, Aufgaben zu registrieren:

#### Weg A: Dynamisch via Filter `ha_achievement_registered_tasks` (Empfohlen)
```php
add_filter( 'ha_achievement_registered_tasks', function( array $catalog ) {
    $catalog['ha-workflow-forms'] = [
        // Aufgabe 1
        'wf_create_form' => [
            'task_key'      => 'wf_create_form',
            'source'        => 'ha-workflow-forms',
            'source_label'  => 'Workflow Forms',
            'source_icon'   => '📝',
            'source_color'  => '#059669',
            'chapter_key'   => 'chapter1',
            'chapter_title' => 'Kapitel 1: Grundlagen der Formularerstellung',
            'chapter_icon'  => '📋',
            'title'         => 'Erstes Freigabeformular anlegen',
            'hint'          => 'Gehe in das Formularmenü und füge mindestens zwei Pflichtfelder hinzu.',
            'action_url'    => admin_url( 'admin.php?page=ha-workflow-forms-builder' ),
            'action_label'  => 'Formular anlegen ▶',
            'order_num'     => 1,
            'xp_value'      => 15,
        ],
        // Aufgabe 2
        'wf_setup_routing' => [
            'task_key'      => 'wf_setup_routing',
            'source'        => 'ha-workflow-forms',
            'source_label'  => 'Workflow Forms',
            'source_icon'   => '📝',
            'source_color'  => '#059669',
            'chapter_key'   => 'chapter1',
            'chapter_title' => 'Kapitel 1: Grundlagen der Formularerstellung',
            'chapter_icon'  => '📋',
            'title'         => 'Bedingte E-Mail-Weiterleitung aktivieren',
            'hint'          => 'Definiere eine Regel für den Vorgesetzten-Genehmigungsschritt.',
            'action_url'    => admin_url( 'admin.php?page=ha-workflow-forms-routing' ),
            'action_label'  => 'Routing einrichten ▶',
            'order_num'     => 2,
            'xp_value'      => 20,
        ],
    ];
    return $catalog;
} );
```

#### Weg B: Dauerhaft in der Datenbank persistieren (`register_tasks`)
```php
if ( class_exists( '\HA_Achievement\API' ) ) {
    \HA_Achievement\API::register_tasks( 'ha-workflow-forms', [
        [
            'id'            => 'wf_create_form',
            'chapter_key'   => 'chapter1',
            'chapter_title' => 'Kapitel 1: Grundlagen',
            'chapter_icon'  => '📋',
            'title'         => 'Erstes Freigabeformular anlegen',
            'hint'          => 'Mindestens 2 Pflichtfelder definieren.',
            'action_url'    => admin_url( 'admin.php?page=ha-workflow-forms-builder' ),
            'order_num'     => 1,
            'xp_value'      => 15,
        ],
    ] );
}
```

---

## 5. Eigene Badges, Medaillen & Freischalt-Regeln erstellen

Badges sind native WordPress-Posts des CPT `ha_achievement`. Sie können programmatisch registriert werden:

```php
/**
 * Registriert ein automatisches Badge für Ihr Plugin, falls noch nicht vorhanden.
 */
function myplugin_register_badge() {
    // Prüfen, ob CPT existiert und ob Badge bereits angelegt ist
    if ( ! post_type_exists( 'ha_achievement' ) ) {
        return;
    }

    $slug = 'wf_master_builder';
    $existing = get_posts( [
        'post_type'   => 'ha_achievement',
        'meta_key'    => '_ha_slug',
        'meta_value'  => $slug,
        'post_status' => 'any',
        'numberposts' => 1,
    ] );

    if ( ! empty( $existing ) ) {
        return; // Bereits registriert
    }

    $post_id = wp_insert_post( [
        'post_title'   => '📝 Workflow-Architekt',
        'post_content' => 'Erstelle dein erstes vollständiges Mehrstufen-Freigabeformular und richte Routing ein.',
        'post_type'    => 'ha_achievement',
        'post_status'  => 'publish',
    ] );

    if ( $post_id && ! is_wp_error( $post_id ) ) {
        update_post_meta( $post_id, '_ha_slug', $slug );
        update_post_meta( $post_id, '_ha_source', 'ha-workflow-forms' );
        update_post_meta( $post_id, '_ha_tier', 'silver' ); // bronze | silver | gold | platinum | titan
        update_post_meta( $post_id, '_ha_xp_reward', 100 );
        update_post_meta( $post_id, '_ha_is_secret', 0 );

        // Automatische Freischalt-Regel: Wird freigeschaltet, wenn beide Aufgaben erledigt sind!
        $rules = [
            'required_tasks' => [ 'wf_create_form', 'wf_setup_routing' ],
        ];
        update_post_meta( $post_id, '_ha_rules', wp_json_encode( $rules ) );
    }
}
add_action( 'init', 'myplugin_register_badge', 20 );
```

### Manuelles Freischalten eines Badges per Code:
```php
if ( class_exists( '\HA_Achievement\User' ) ) {
    \HA_Achievement\User::unlock_achievement( $user_id, 'wf_master_builder', 100, 'ha-workflow-forms' );
}
```

---

## 6. Offiziell verifizierbare Diplome & Zertifikate ausstellen

Wenn der Nutzer einen vollständigen Kurs oder Workflow meistert, können Sie ein **kryptografisch gesichertes Abschlussdiplom** erstellen:

```php
if ( class_exists( '\HA_Achievement\API' ) ) {
    /**
     * Stellt ein Diplom aus.
     * Erzeugt automatisch einen Prüfcode (z. B. 'HA-WORK-8X9K2M') und SHA-256-Hash.
     */
    $code = \HA_Achievement\API::issue_credential(
        $user_id,
        'ha-workflow-forms',        // Quell-Slug (ergibt z. B. Prefix HA-WORK-)
        'workflow_specialist',      // Typ / Kennung
        [
            'title'       => 'Workflow & Forms Specialist',
            'honor_grade' => 'MAGNA CUM LAUDE', // Akademisches Prädikat
            'total_xp'    => 1850,
            'svg'         => $certificate_svg,  // Optionaler SVG-Snapshot
            'metadata'    => [
                'courses_completed' => [ 'basics', 'routing', 'security' ],
                'score_percent'     => 98,
            ],
        ]
    );

    // Die öffentliche Verifikations-URL lautet:
    $verify_url = home_url( '/zertifikat/' . $code . '/' );
}
```

---

## 7. Anzeige im WP-Admin: „Verbundene Plugins“ erweitern

Im WordPress-Backend unter **🏆 Auszeichnungen $\rightarrow$ Verbundene Plugins** wird der Status Ihres Plugins übersichtlich dargestellt:

```php
add_filter( 'ha_achievement_connected_plugins', function( array $plugins ) {
    $is_active = true; // z. B. defined('HA_WORKFLOW_VERSION')

    $plugins['ha-workflow-forms'] = [
        'name'             => 'HA Workflow Forms (Genehmigungs- & Formular-Studio)',
        'slug'             => 'ha-workflow-forms',
        'status'           => $is_active ? 'active' : 'inactive',
        'version'          => '1.0.0',
        'description'      => 'Enterprise-Formulare mit rollenbasiertem Freigabe-Routing.',
        'registered_tasks' => 12,
        'features'         => [
            '12 interaktive Gestaltungs- und Routing-Aufgaben',
            'Automatische XP-Gutschriften bei Formular-Genehmigungen',
            'Verifizierbare Urkunde für Audit-Beauftragte',
        ],
    ];

    return $plugins;
} );
```

---

## 8. Auf Ereignisse reagieren (Event-Listener)

Wenn die `ha-achievement-api` Aktivitäten verbucht, feuert sie saubere WordPress Action-Hooks. Ihr Plugin kann darauf reagieren:

```php
// 1. Reagieren, wenn einem Benutzer XP gutgeschrieben wurden
add_action( 'ha_achievement_xp_awarded', function( $user_id, $amount, $result, $source ) {
    // $result enthält: ['old_xp', 'new_xp', 'old_level', 'new_level', 'leveled_up', 'rank_title']
    if ( $result['leveled_up'] ) {
        // Z. B. Glückwunsch-E-Mail senden oder Slack-Webhook aufrufen
    }
}, 10, 4 );

// 2. Reagieren, wenn ein Benutzer eine Stufe aufsteigt
add_action( 'ha_achievement_leveled_up', function( $user_id, $new_level, $old_level, $result ) {
    // Z. B. Neue Rechte oder Menüpunkte freischalten
}, 10, 4 );

// 3. Reagieren, wenn eine Teilaufgabe abgeschlossen wurde
add_action( 'ha_achievement_task_completed', function( $user_id, $task_key, $source, $meta ) {
    // Logging oder Synchronisation mit externem CRM
}, 10, 4 );

// 4. Reagieren, wenn ein Badge freigeschaltet wurde
add_action( 'ha_achievement_unlocked', function( $user_id, $achievement_slug, $source ) {
    // Audio-Feedback oder Benachrichtigung
}, 10, 3 );

// 5. Reagieren, wenn ein Diplom ausgestellt wurde
add_action( 'ha_achievement_credential_issued', function( $post_id, $code, $user_id, $data ) {
    // Z. B. PDF-Generierung für HR
}, 10, 4 );
```

---

## 9. Frontend-Einbindung: Shortcodes & Attribute

Alle Widgets sind styleneutral, voll responsive und können auf beliebigen WordPress-Seiten eingebunden werden:

### 9.1 `[ha_achievement_learning_dashboard]` (Missions-Widget)
Zeigt dem Benutzer seine offenen und gelösten Aufgaben gruppiert nach Kapiteln an:
- `source`: Optional. Filtert auf ein bestimmtes Quell-Plugin (z. B. `source="ha-workflow-forms"`). Bleibt das Attribut leer, werden **alle** Aufgaben über alle Plugins hinweg angezeigt!
- `collapsed`: `'yes'` (Standard: kompakte 115px-Seitenleistenkarte mit Dropdown) oder `'no'` (vollständig aufgeklappt).
- `show_filters`: `'yes'` (Standard) oder `'no'`.
- `editor_url`: Eigene Zieladresse für die Buttons.

```html
<!-- In der Seitenleiste: -->
[ha_achievement_learning_dashboard source="ha-workflow-forms" collapsed="yes"]

<!-- Auf einer dedizierten Schulungs-Hauptseite: -->
[ha_achievement_learning_dashboard source="ha-workflow-forms" collapsed="no"]
```

### 9.2 `[ha_achievement_profile_card]`
Zeigt Avatar, Name, Rangtitel, Stufen-Badge und XP-Fortschrittsbalken:
```html
[ha_achievement_profile_card]
<!-- Für einen spezifischen Nutzer: -->
[ha_achievement_profile_card user_id="42"]
```

### 9.3 `[ha_achievement_badges]`
Medaillen-Vitrine aller registrierten Abzeichen:
```html
[ha_achievement_badges]
```

### 9.4 `[ha_achievement_leaderboard]`
Bestenliste mit Top-3-Podest und Platzierungen:
```html
[ha_achievement_leaderboard limit="10"]
```

### 9.5 `[ha_achievement_certificate_verify]`
Öffentliches Prüfformular zur Validierung von Prüfcodes und SHA-256-Hashes:
```html
[ha_achievement_certificate_verify]
```

---

## 10. REST-API Referenz (JavaScript / Single-Page-Apps)

Basis-URL: `/wp-json/ha-achievement/v1/`  
Authentifizierung: WordPress Cookie-Nonce (`X-WP-Nonce: wpApiSettings.nonce`).

| Methode | Endpunkt | Body / Parameter | Beschreibung |
|---|---|---|---|
| `GET` | `/user/progress` | - | Liefert XP, Level, Aufgaben- & Badgestatus des eingeloggten Nutzers. |
| `POST` | `/tasks/complete` | `{ "task_key": "wf_create_form", "source": "ha-workflow-forms" }` | Markiert eine Teilaufgabe als erledigt. |
| `POST` | `/xp/award` | `{ "xp": 50, "source": "ha-workflow-forms", "reason": "Text" }` | Schreibt Erfahrungspunkte gut. |
| `POST` | `/credentials/issue` | `{ "source": "...", "type": "...", "title": "...", "honor_grade": "...", "svg": "..." }` | Archiviert ein neues Diplom. |
| `GET` | `/credentials/verify/{code}` | - | Öffentliche Prüfung eines Prüfcodes (ohne Auth). |
| `GET` | `/leaderboard` | `?limit=10` | Liefert die Top-Bestenliste aus dem Cache. |
| `GET` | `/tasks/catalog` | `?source=ha-workflow-forms` | Liefert alle registrierten Aufgaben. |

---

## 11. Vollständige Boilerplate-Klasse zum Kopieren

Kopieren Sie die Datei [`examples/class-sample-achievement-integration.php`](file:///G:/Meine%20Ablage/Aktuelle%20Projekte/Antigravity/wp-public/ha-achievement-api/examples/class-sample-achievement-integration.php) direkt in Ihr neues Plugin:

```php
<?php
/**
 * Boilerplate: Gamification-Integration für ein neues Plugin
 *
 * Datei z. B. ablegen unter: my-plugin/includes/class-my-plugin-achievements.php
 */

namespace My_Plugin;

defined( 'ABSPATH' ) || exit;

class Achievements {

    const SOURCE_SLUG = 'my-plugin-slug';

    public static function init() {
        // 1. Branding der Quelle registrieren
        add_filter( 'ha_achievement_registered_sources', [ __CLASS__, 'register_source' ] );

        // 2. Aufgabenkatalog bereitstellen
        add_filter( 'ha_achievement_registered_tasks', [ __CLASS__, 'register_tasks' ] );

        // 3. Im Admin-Dashboard „Verbundene Plugins“ anzeigen
        add_filter( 'ha_achievement_connected_plugins', [ __CLASS__, 'register_admin_integration' ] );
    }

    public static function register_source( array $sources ): array {
        $sources[ self::SOURCE_SLUG ] = [
            'slug'         => self::SOURCE_SLUG,
            'name'         => 'Mein Tolles Plugin',
            'label'        => 'Prozess- & Wissensstudio',
            'icon'         => '⚡',
            'color'        => '#4f46e5',
            'bg'           => '#eef2ff',
            'action_label' => __( 'Jetzt starten ▶', 'my-plugin' ),
        ];
        return $sources;
    }

    public static function register_tasks( array $catalog ): array {
        $catalog[ self::SOURCE_SLUG ] = [
            'my_task_1' => [
                'task_key'      => 'my_task_1',
                'source'        => self::SOURCE_SLUG,
                'source_label'  => 'Wissensstudio',
                'source_icon'   => '⚡',
                'source_color'  => '#4f46e5',
                'chapter_key'   => 'basics',
                'chapter_title' => __( 'Kapitel 1: Grundlagen', 'my-plugin' ),
                'chapter_icon'  => '📖',
                'title'         => __( 'Ersten Datensatz anlegen', 'my-plugin' ),
                'hint'          => __( 'Öffne das Modul und erstelle deinen ersten Eintrag.', 'my-plugin' ),
                'action_url'    => admin_url( 'admin.php?page=my-plugin-start' ),
                'action_label'  => __( 'Eintrag anlegen ▶', 'my-plugin' ),
                'order_num'     => 1,
                'xp_value'      => 15,
            ],
        ];
        return $catalog;
    }

    public static function register_admin_integration( array $plugins ): array {
        $plugins[ self::SOURCE_SLUG ] = [
            'name'             => 'Mein Tolles Plugin',
            'slug'             => self::SOURCE_SLUG,
            'status'           => 'active',
            'version'          => '1.0.0',
            'description'      => 'Automatisiert Aufgaben und belohnt Nutzer.',
            'registered_tasks' => 1,
            'features'         => [
                'Voll integriert in die zentrale ha-achievement-api',
                'Automatische XP-Belohnungen',
            ],
        ];
        return $plugins;
    }

    /**
     * Hilfsfunktion: Aufgabe abhaken
     */
    public static function complete_task( int $user_id, string $task_key ) {
        do_action( 'ha_achievement_complete_task', $user_id, $task_key, self::SOURCE_SLUG );
    }

    /**
     * Hilfsfunktion: XP gutschreiben
     */
    public static function award_xp( int $user_id, int $xp, string $reason = '' ) {
        do_action( 'ha_achievement_award_xp', $user_id, $xp, self::SOURCE_SLUG, $reason );
    }
}

// In Ihrem Haupt-Plugin initialisieren:
// \My_Plugin\Achievements::init();
```
