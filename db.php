<?php
// Cache-Control-Header setzen, um Browser-Caching auf allen Seiten zu verhindern (z. B. nach dem Logout)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Verbindung zur SQLite-Datenbank herstellen
try {
    $db = new PDO('sqlite:bibliothek_02.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fremdschlüssel-Unterstützung für SQLite aktivieren
    $db->exec("PRAGMA foreign_keys = ON;");

    // 1. Tabelle für Nutzer (Erweitert um Timestamps)
    $db->exec("CREATE TABLE IF NOT EXISTS nutzer (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        adresse TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        passwort TEXT NOT NULL,
        rolle TEXT NOT NULL DEFAULT 'user',
        ist_aktiv INTEGER DEFAULT 0,
        bestaetigungstoken TEXT DEFAULT NULL,
        angelegt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
        aktualisiert_am DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Tabelle für den Buchbestand (Erweitert um Timestamps)
    $db->exec("CREATE TABLE IF NOT EXISTS buecher (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titel TEXT NOT NULL,
        autor TEXT NOT NULL,
        isbn TEXT UNIQUE NOT NULL,
        bestand INTEGER NOT NULL DEFAULT 1,
        typ TEXT NOT NULL DEFAULT 'physisch',
        pdf_pfad TEXT DEFAULT NULL,
        angelegt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
        aktualisiert_am DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Verknüpfungstabelle für die Ausleihen
    $db->exec("CREATE TABLE IF NOT EXISTS ausleihen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nutzer_id INTEGER,
        buch_id INTEGER,
        ausgeliehen_am DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (nutzer_id) REFERENCES nutzer(id) ON DELETE CASCADE,
        FOREIGN KEY (buch_id) REFERENCES buecher(id) ON DELETE CASCADE
    )");

    // 4. Tabelle für KI-generierte Inhaltsverzeichnisse und Analysen (Ollama)
    $db->exec("CREATE TABLE IF NOT EXISTS buch_analysen (
        buch_id INTEGER PRIMARY KEY,
        zusammenfassung TEXT,
        inhaltsverzeichnis TEXT,
        aktualisiert_am DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (buch_id) REFERENCES buecher(id) ON DELETE CASCADE
    )");

    // 5. Tabelle für KI-Antwort-Cache (spart Tokens und sichert Demos bei wiederholten Fragen ab)
    $db->exec("CREATE TABLE IF NOT EXISTS ki_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        frage_hash TEXT UNIQUE NOT NULL,
        frage TEXT NOT NULL,
        antwort TEXT NOT NULL,
        angelegt_am DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}
?>

<!-- Die Datei db.php
 ist das Herzstück der Datenhaltung und Sicherheit in deiner Anwendung. Da sie mit include_once in fast alle anderen PHP-Dateien eingebunden wird, stellt sie sicher, dass auf jeder Seite eine aktive Datenbankverbindung existiert und bestimmte Sicherheitsstandards eingehalten werden.

Hier ist eine ausführliche, schrittweise Erklärung, was in dieser Datei passiert:

1. Cache-Control: Schutz nach dem Logout (Zeilen 2–5)
Was passiert hier? Diese Zeilen senden HTTP-Header an den Webbrowser des Benutzers. Sie verbieten dem Browser explizit, die aufgerufene Seite im lokalen Speicher (Cache) zwischenzuspeichern.
Warum ist das wichtig? Wenn sich ein Benutzer (z. B. ein Mitarbeiter) ausloggt, verhindert dieser Code, dass eine andere Person danach am selben Computer auf den „Zurück“-Pfeil des Browsers klickt und so sensible Daten (wie Kundenlisten oder Passwörter) wieder sehen kann. Der Browser wird gezwungen, die Seite frisch vom Server anzufordern, was aufgrund der gelöschten Session direkt zum Login umleitet.

2. Aufbau der Datenbankverbindung via PDO (Zeilen 8–10)
PDO (PHP Data Objects): Dies ist eine standardisierte Schnittstelle in PHP, um auf Datenbanken zuzugreifen. Der Vorteil von PDO ist, dass es die Verwendung von sogenannten Prepared Statements (vorbereiteten Abfragen) erzwingt, was die Anwendung immun gegen SQL-Injections (SQL-Einschleusung) macht.
sqlite:bibliothek_02.sqlite: Hierdurch verbindet sich PHP mit einer lokalen SQLite-Datenbankdatei namens bibliothek_02.sqlite. SQLite benötigt keinen separaten Datenbankserver (wie MySQL/MariaDB) – die gesamte Datenbank ist eine einzige Datei im Projektverzeichnis. Falls sie noch nicht existiert, wird sie beim ersten Aufruf automatisch erstellt.
ATTR_ERRMODE & ERRMODE_EXCEPTION: Dies stellt den Fehlermodus so ein, dass bei Datenbankfehlern (z. B. Syntaxfehler im SQL, UNIQUE-Konflikte) eine PHP-Ausnahme (PDOException) ausgelöst wird. Dadurch stürzt das Skript nicht stillschweigend ab, sondern wir können Fehler im catch-Block kontrolliert abfangen.

3. Aktivierung von Fremdschlüsseln (Zeile 13)
Warum das nötig ist: SQLite unterstützt zwar Fremdschlüssel (Verknüpfungen zwischen Tabellen), schaltet diese jedoch standardmäßig aus Kompatibilitätsgründen ab. Durch diesen Befehl wird die Fremdschlüssel-Validierung für die aktuelle Sitzung aktiviert. Nur so greifen Regeln wie ON DELETE CASCADE (automatisches Löschen verknüpfter Daten).

4. Tabellenstruktur (Zeilen 16–59)
Die Datei enthält vier CREATE TABLE IF NOT EXISTS-Befehle. Das IF NOT EXISTS sorgt dafür, dass die Tabellen nur angelegt werden, wenn sie nicht schon vorhanden sind (verhindert Datenverlust bei erneutem Aufruf).

A. Tabelle nutzer (Kunden & Mitarbeiter)
email TEXT UNIQUE NOT NULL: Jede E-Mail-Adresse darf nur einmal im System vorkommen. Ein Registrierungsversuch mit einer doppelten E-Mail wirft einen Fehler auf.
rolle TEXT DEFAULT 'user': Steuert den Zugriff. Standardmäßig ist jeder Nutzer ein normaler Kunde (user). Mitarbeiter erhalten die Rolle mitarbeiter.
ist_aktiv INTEGER DEFAULT 0: Ein neu registrierter Nutzer ist erst inaktiv (0). Er wird erst auf 1 gesetzt, sobald er seine E-Mail verifiziert hat.
bestaetigungstoken: Speichert den 6-stelligen Code zur E-Mail-Verifizierung. Nach erfolgreicher Aktivierung wird das Feld geleert (NULL).
angelegt_am & aktualisiert_am: Speichern vollautomatisch den Erstellungs- und Änderungszeitpunkt des Datensatzes.

B. Tabelle buecher (Medienbestand)
isbn TEXT UNIQUE NOT NULL: Jedes Buch wird eindeutig über seine ISBN-Nummer identifiziert.
typ & pdf_pfad: Wenn typ den Wert 'pdf' hat (E-Book), wird in pdf_pfad der Dateipfad zur hochgeladenen Datei in uploads/ gespeichert. Bei 'physisch' bleibt das Feld leer.
bestand: Gibt bei physischen Büchern die Stückzahl an, bei digitalen E-Books die Anzahl der maximal gleichzeitig verfügbaren Lizenzen.

C. Tabelle ausleihen (M:N-Verknüpfung)
Verknüpfung: Diese Tabelle verbindet die Tabelle nutzer und buecher miteinander. Sie hält fest, welcher Nutzer welches Buch ausgeliehen hat.
ON DELETE CASCADE (Kaskadierendes Löschen): Das ist ein extrem wichtiges Sicherheits- und Integritätsfeature! Wenn ein Nutzer aus dem System gelöscht wird, werden all seine aktiven Ausleih-Einträge in dieser Tabelle automatisch mitgelöscht. Gleiches gilt, wenn ein Buch gelöscht wird. Es entstehen keine „toten“ Verweise in der Datenbank.

D. Tabelle buch_analysen (1:1-Verknüpfung)
1:1 Beziehung: Die buch_id ist hier gleichzeitig der Primärschlüssel (PRIMARY KEY) und der Fremdschlüssel (FOREIGN KEY). Dadurch kann es für jedes Buch maximal einen Eintrag in dieser Tabelle geben.
Verwendung: Hier werden die von Ollama generierten Inhaltsverzeichnisse und Kurzzusammenfassungen gespeichert, auf die der KI-Bibliothekar (RAG) zugreift.
ON DELETE CASCADE: Löscht man ein Buch, wird auch dessen KI-Analyse rückstandslos gelöscht.

5. Try-Catch Fehlerbehandlung (Zeilen 61–63)
Sollte die SQLite-Datei beispielsweise schreibgeschützt sein oder ein grober SQL-Fehler beim Tabellenaufbau vorliegen, bricht das Skript kontrolliert ab (die) und gibt eine Fehlermeldung aus. Das verhindert, dass die Anwendung mit einer unvollständigen oder defekten Datenbankverbindung weiterarbeitet, was zu unvorhersehbarem Verhalten führen würde. -->