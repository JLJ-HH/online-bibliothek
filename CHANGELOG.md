# Changelog

Alle relevanten Änderungen an der **Online-Bibliothek** werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/) und dieses Projekt hält sich an [Semantic Versioning](https://semver.org/lang/de/).

## [1.2.1] - 2026-09-04

### Geändert
- **KI-Modell-Upgrade auf `gemma4:31b`:** Umstellung des Ollama-Cloud-Modells von dem eingestellten `gemma3:12b` auf das Nachfolgemodell `gemma4:31b` in [index.php](file:///c:/github/online-bibliothek/index.php), [buch_anlegen.php](file:///c:/github/online-bibliothek/buch_anlegen.php) und [buch_bearbeiten.php](file:///c:/github/online-bibliothek/buch_bearbeiten.php).
- **Token-Optimierung:** 
  - Begrenzung der KI-Antwortlänge via `max_tokens => 500` für strukturierte 3-Absatz-Antworten bei minimalem Tokenverbrauch.
  - Verschlankung des RAG-Kontexts durch Kompression von Buchzusammenfassungen und Aussparen roher Inhaltsverzeichnisse (bis zu 80% weniger Input-Tokens).

### Hinzugefügt
- **Antwort-Caching (`ki_cache`):** Einführung einer SQLite-Cache-Tabelle für KI-Antworten; identische Fragen verbrauchen 0 Tokens und antworten in 0,01 Sekunden.
- **Graceful Degradation (Ausfallschutz):** Automatischer Heuristik-Fallback auf eine integrierte Katalogsuche bei API-Ausfall oder Rate-Limits für ausfallsichere Live-Demos.

---

## [1.2.0] - 2026-09-04

### Geändert
- **Live-Demo & Weiterleitung aktualisiert:** Aktualisierung der Demo- und Weiterleitungs-URLs in `readme.md` und `verbindung-zu meinen-projekt.html` auf die aktive Hosting-Adresse ([jljuarez.de/bibliothek/login](https://jljuarez.de/bibliothek/login)) über die Strato-Flask-Portfolio-Blueprint-Anbindung.

### Hinzugefügt
- **Changelog:** Einführung der `CHANGELOG.md` zur lückenlosen Dokumentation der Versionshistorie und Erweiterungen.

---

## [1.1.0] - 2026-06-29

### Hinzugefügt
- **RAG-KI-Bibliothekar:** Anbindung an Ollama (`gemma3:12b`) zur interaktiven Buchempfehlung und kontextbezogenen Beratung basierend auf Bestand, Buchzusammenfassungen und Inhaltsverzeichnissen (Retrieval-Augmented Generation).
- **KI-gestützte PDF-Analyse & Sammel-Import:** Automatisches Auslesen hochgeladener E-Books (PDFs) über integrierten Parser; automatische Erfassung von Buchmetadaten (Titel, Autor, ISBN) und KI-Generierung von Zusammenfassungen und Inhaltsverzeichnissen. Unterstützung von Sammel-Imports mehrerer Bücher in einem Durchlauf.
- **In-Browser E-Book-Reader:** Direktes Öffnen und Lesen von digitalen E-Books (PDF) im Browser für verifizierte Benutzer.
- **Architektur- & Entwurfsdokumentation:** Bereitstellung von ER-Diagramm (Chen-Notation) und Use-Case-Diagramm zur Visualisierung der Daten- und Berechtigungsstrukturen.

### Sicherheit
- **SQL-Injection-Prävention:** Konsequenter Einsatz vorbereiteter Anweisungen (Prepared Statements via PDO) für alle Datenbankoperationen.
- **XSS-Schutz:** Vollständige Filterung und Maskierung dynamischer Inhalte und KI-Antworten mittels `htmlspecialchars()`.
- **Passwortsicherheit:** Sicheres Passwort-Hashing mit BCRYPT (`password_hash()`) und Validierung via `password_verify()`.
- **Session- & Cache-Handling:** Zuverlässiges Beenden von Sessions bei Logout sowie restriktive `Cache-Control`-Header zur Absicherung gegen Browser-Verlaufsangriffe.

---

## [1.0.0] - 2026-06-29

### Hinzugefügt
- **Basis-Bibliotheksverwaltung (PHP 8 & SQLite3):**
  - **Rollen- & Rechtesystem:** Differenzierung zwischen Rollen `mitarbeiter` (Admin / Bibliothekar) und `user` (Bibliothekskunde).
  - **Kundenverwaltung (CRUD):** Erstellen, Einsehen, Aktualisieren, Sperren und Löschen von Kundenkonten.
  - **Medienverwaltung (CRUD):** Bestandsverwaltung physischer Bücher und digitaler Medien (E-Books).
  - **Ausleih- und Rückgabeprozess:** Ausleihe physischer Bücher (Bestandsverfolgung) und digitaler E-Books (Lizenzverwaltung) inklusive Rückgabeerfassung.
  - **Registrierungs- & Verifizierungsworkflow:** Zwei-Schritt-Registrierung mit 6-stelligem E-Mail-Verifizierungstoken vor individueller Passwortvergabe.
  - **Automatisiertes Setup-Skript:** `setup.php` zur initialen Erstellung der SQLite-Datenbank (`bibliothek_02.sqlite`), Tabellenschemata (`nutzer`, `buecher`, `ausleihen`, `buch_analysen`) und Beispieldatensätzen.
