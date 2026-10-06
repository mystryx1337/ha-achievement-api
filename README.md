# HA Achievement API 🏆

> Zentrales, modulares Gamification- und Leistungsnachweis-System für WordPress.
> Entwickelt für [`ha-bpmn-manager`](https://github.com/alexander/ha-bpmn-manager) und alle künftigen Prozess-, Quiz- und Workflow-Erweiterungen.

📖 **Ausführlicher Entwickler- & Integrations-Leitfaden:** Siehe [`DEVELOPER_GUIDE.md`](DEVELOPER_GUIDE.md) sowie die Muster-Vorlage [`examples/class-sample-achievement-integration.php`](examples/class-sample-achievement-integration.php) für die Anbindung neuer Plugins.

---

## 🌟 Highlights & Architektur

- **100 % Native WordPress-Architektur**: Keine fehleranfälligen Custom MySQL-Tabellen!
  - **CPT `ha_achievement`**: Verwaltet Badges, Trophäen, XP-Belohnungen und Kriterien.
  - **CPT `ha_credential`**: Verwaltet offizielle, kryptografisch verifizierbare Diplome & Urkunden mit eigener öffentlicher URL (z.&nbsp;B. `/zertifikat/HA-BPMN-8X9K2M`).
  - **`wp_usermeta`**: Speichert XP (`ha_total_xp`), Stufen (`ha_level`), Ränge, tägliche Streaks und die 27 gelösten Aufgaben in einem einzigen kompakten JSON-Blob.
  - **Transients-Caching**: Leaderboards und Bestenlisten belasten den Server bei Abruf mit **0 ms** Datenbankzeit.
- **Offenes Source-Ökosystem**: Jedes Plugin registriert sich als eigenständige *Source* und steuert eigene Aufgaben und Badges bei.
- **Volle Backup- & DSGVO-Kompatibilität**: Funktioniert standardmäßig mit UpdraftPlus, WP-CLI, Duplicator und den WordPress-Datenschutz-Export-/Löschwerkzeugen.
- **Echtzeit-Feedback**: Integrierte Konfetti- und Toast-Engine ohne externe JavaScript-Bibliotheken.

---

## 🧩 Verfügbare Frontend-Shortcodes

| Shortcode | Beschreibung |
|---|---|
| `[ha_achievement_learning_dashboard]` | **Das zentrale Missions-Widget**: Zeigt dem Nutzer seine erledigten und noch offenen Aufgaben gruppiert nach Kapiteln mit Fortschrittsbalken, Praxistipps und direkten **„Jetzt lösen 🚀“**-Aktions-Buttons. |
| `[ha_achievement_profile_card]` | Zeigt das Benutzer-Avatar, den aktuellen Rangtitel (z.&nbsp;B. *👑 Großmeister-Titan*), das Level und den animierten XP-Fortschrittsbalken zur nächsten Stufe. |
| `[ha_achievement_badges]` | Medaillen-Vitrine: Freigeschaltete Badges glänzend hervorgehoben, gesperrte transparent mit Schlosssymbol. |
| `[ha_achievement_leaderboard]` | Bestenliste mit interaktivem Top-3-Podest (Gold, Silber, Bronze) und tabellarischer Platzierung. |
| `[ha_achievement_certificate_verify]` | Öffentliche Suchmaske zur Prüfung von Prüfcodes und SHA-256 Hashes durch Kunden, HR oder Auditoren. |

---

## 💻 PHP API Entwickler-Dokumentation (`\HA_Achievement\API`)

Jedes Plugin kann ohne Netzwerklatenz direkt mit dem Backend interagieren:

```php
// 1. XP gutschreiben
\HA_Achievement\API::award_xp( $user_id, 50, 'ha-bpmn-manager', 'Kapitel 1 gelöst' );

// 2. Teilaufgabe als abgeschlossen markieren
\HA_Achievement\API::complete_task( $user_id, 'c1_toggle_sim', 'ha-bpmn-manager' );

// 3. Eigene Lernaufgaben im Katalog registrieren
\HA_Achievement\API::register_tasks( 'mein_plugin', [
    [
        'id'            => 'step_1',
        'chapter_key'   => 'basics',
        'chapter_title' => 'Grundlagen-Modul',
        'chapter_icon'  => '🚀',
        'title'         => 'Erstes Formular absenden',
        'hint'          => 'Fülle alle Pflichtfelder aus und klicke auf Senden.',
        'action_url'    => home_url( '/formular/' ),
        'order_num'     => 1,
        'xp_value'      => 15,
    ]
] );

// 4. Diplom ausstellen
$code = \HA_Achievement\API::issue_credential( $user_id, 'ha-bpmn-manager', 'bpmn_grandmaster', [
    'title'       => 'BPMN 2.0 Process Practitioner',
    'honor_grade' => 'EGREGIA CUM LAUDE',
    'total_xp'    => 2450,
    'svg'         => $diploma_svg_string,
] );

// 5. Diplom verifizieren
$result = \HA_Achievement\API::verify_credential( 'HA-BPMN-8X9K2M' );
if ( $result['valid'] ) {
    echo "Echtes Diplom von: " . esc_html( $result['recipient'] );
}
```

### Lose Kopplung via WordPress Action Hooks:
```php
// Funktioniert auch, wenn ha-achievement-api optional ist:
do_action( 'ha_achievement_award_xp', $user_id, 100, 'mein_plugin', 'Quiz bestanden' );
do_action( 'ha_achievement_complete_task', $user_id, 'quiz_aufgabe_1', 'mein_plugin' );
```

---

## 🌐 REST API Endpunkte

Basis-URL: `/wp-json/ha-achievement/v1/`

- `GET /user/progress`: Liefert XP, Level, Aufgaben- und Badge-Status des eingeloggten Benutzers.
- `POST /tasks/complete`: Meldet eine gelöste Teilaufgabe (`task_key`, `source`).
- `POST /xp/award`: Schreibt Punkte gut (`xp`, `source`, `reason`).
- `POST /credentials/issue`: Speichert ein errungenes Diplom serverweit ab.
- `GET /credentials/verify/{code}`: Öffentliche Verifikation eines Prüfcodes.
- `GET /leaderboard`: Liefert die aktuelle Bestenliste aus dem Cache.
- `GET /tasks/catalog`: Liefert alle registrierten Aufgaben.

---

## 📄 Lizenz & Konformität

- **GPL-2.0+**
- **ISO 19510**: Standardkonforme Begriffsdefinitionen und Audit-Trail-Archivierung.
- **DSGVO**: Unterstützt die WordPress Core Privacy Tools (Daten-Export und Lösch-Anfragen).
