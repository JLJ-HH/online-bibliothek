<?php
// setup.php - Datenbank zurücksetzen und Testdaten laden

// 1. Verbindung trennen, falls vorhanden, und DB-Datei löschen
$db_file = __DIR__ . '/bibliothek_02.sqlite';
if (file_exists($db_file)) {
    // Falls PDO-Verbindungen offen sind, kann unlink fehlschlagen, daher stellen wir sicher, dass wir sauber löschen.
    try {
        unlink($db_file);
    } catch (Exception $e) {
        // Fallback: Ignorieren oder Fehlermeldung ausgeben
    }
}

// 2. Uploads-Verzeichnis leeren
$uploads_dir = __DIR__ . '/uploads';
if (is_dir($uploads_dir)) {
    $files = glob($uploads_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
} else {
    mkdir($uploads_dir, 0777, true);
}

// Erstelle ein Dummy-PDF für Testzwecke, damit der PDF-Link nicht ins Leere läuft
file_put_contents($uploads_dir . '/php_handbuch.pdf', '%PDF-1.4 [Dummy PDF Content for Testing]');

// 3. db.php einbinden, um Datenbank und Tabellen frisch zu erstellen
include_once 'db.php';

try {
    // Fremdschlüssel-Unterstützung aktivieren
    $db->exec("PRAGMA foreign_keys = ON;");

    // --- TEST-NUTZER ANLEGEN ---
    // Passwörter werden sicher mit BCRYPT gehasht
    $pwMitarbeiter = password_hash('admin123', PASSWORD_BCRYPT);
    $pwUser1 = password_hash('user123', PASSWORD_BCRYPT);
    $pwUser2 = password_hash('geblockt123', PASSWORD_BCRYPT);

    $stmtNutzer = $db->prepare("INSERT INTO nutzer (name, adresse, email, passwort, rolle, ist_aktiv) VALUES (?, ?, ?, ?, ?, ?)");
    
    // 1. Ein Mitarbeiter (Admin) - MUSS aktiv sein (ist_aktiv = 1)
    $stmtNutzer->execute(['Bibliothekar Chef', 'Hauptstraße 1, Hamburg', 'admin@bib.de', $pwMitarbeiter, 'mitarbeiter', 1]);
    
    // 2. Ein normaler, aktiver User (Kunde)
    $stmtNutzer->execute(['Jan Vanderfalk', 'Hocheneichen 8, Hamburg', 'jan@va.de', $pwUser1, 'user', 1]);
    
    // 3. Ein gesperrter User (Kunde)
    $stmtNutzer->execute(['Gesperrter Tester', 'Schuldenweg 1, Nirgendwo', 'gesperrt@test.de', $pwUser2, 'user', 0]);


    // --- TEST-BÜCHER & PDFS ANLEGEN ---
    $stmtBuch = $db->prepare("INSERT INTO buecher (titel, autor, isbn, bestand, typ, pdf_pfad) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Physische Medien
    $stmtBuch->execute(['Der Herr der Ringe', 'J.R.R. Tolkien', '978-3-608-93828-9', 3, 'physisch', null]);
    $stmtBuch->execute(['Clean Code', 'Robert C. Martin', '978-3-826-65548-7', 2, 'physisch', null]);
    
    // Digitales PDF-Medium (mit 5 Lizenzen als Standardwert)
    $stmtBuch->execute(['PHP-Handbuch Digitale Edition', 'Michael Kofler', '978-3-836-27485-2', 5, 'pdf', 'uploads/php_handbuch.pdf']);
    $buch_id_pdf = $db->lastInsertId();

    // --- TEST-ANALYSE FÜR DAS PDF ANLEGEN ---
    $stmtAnalyse = $db->prepare("INSERT INTO buch_analysen (buch_id, zusammenfassung, inhaltsverzeichnis) VALUES (?, ?, ?)");
    $stmtAnalyse->execute([
        $buch_id_pdf,
        'Eine umfassende Einführung in die Programmiersprache PHP, Datenbankanbindung mit PDO, Sicherheit (SQL-Injections, XSS) und objektorientierte Programmierung.',
        'Kapitel 1: Einführung in PHP; Kapitel 2: Kontrollstrukturen; Kapitel 3: PDO-Datenbankverbindungen; Kapitel 4: Sicherheitsmechanismen.'
    ]);

    echo "<h3>Setup erfolgreich!</h3>";
    echo "<p>Die SQLite-Datenbank wurde vollständig gelöscht und frisch aufgesetzt.</p>";
    echo "<p><strong>Zugangsdaten zum Testen:</strong><br>";
    echo "- <strong>Mitarbeiter (Admin):</strong> admin@bib.de (Passwort: admin123)<br>";
    echo "- <strong>Normaler Kunde:</strong> jan@va.de (Passwort: user123)</p>";
    echo "<p><a href='login.php'>Hier geht es zum Login</a></p>";

} catch (PDOException $e) {
    echo "<h3>Fehler beim Ausführen des Setups:</h3>";
    echo "<p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>