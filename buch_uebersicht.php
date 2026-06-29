<?php
// Session starten, um Zugriff auf die Benutzerrolle zu haben
session_start();

// Sicherheits-Check: Nur eingeloggte Mitarbeiter (Rolle: mitarbeiter) dürfen die Systemverwaltung sehen
if (!isset($_SESSION['nutzer_id']) || $_SESSION['rolle'] !== 'mitarbeiter') {
    header("Location: index.php");
    exit;
}

// db.php einbinden für die SQLite PDO Verbindung ($db)
include_once 'db.php';
$meldung = '';

// Wenn der Mitarbeiter ein Medium löschen möchte (ID wird per GET-Parameter übergeben)
if (isset($_GET['delete_id'])) {
    try {
        // Prepare Statement zum sicheren Löschen des Mediums aus der Tabelle 'buecher'
        // Durch "ON DELETE CASCADE" in db.php werden verknüpfte Analysen/Ausleihen automatisch gelöscht
        $stmt = $db->prepare("DELETE FROM buecher WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        
        // Nach dem Löschen die Seite neu laden, um GET-Parameter zu bereinigen und Erfolgsmeldung anzuzeigen
        header("Location: buch_uebersicht.php?success=1");
        exit;
    } catch (PDOException $e) {
        $meldung = "Fehler beim Löschen.";
    }
}

try {
    // SQL-Abfrage: Alle Buchdaten abrufen und verknüpfen mit der 1:1 Tabelle 'buch_analysen' (LEFT JOIN).
    // Sortiert nach Autor (case-insensitive) und anschließend nach Titel (case-insensitive), um eine saubere alphabetische Sortierung zu erhalten.
    $sql = "SELECT buecher.*, buch_analysen.zusammenfassung, buch_analysen.inhaltsverzeichnis 
            FROM buecher 
            LEFT JOIN buch_analysen ON buecher.id = buch_analysen.buch_id 
            ORDER BY buecher.autor COLLATE NOCASE ASC, buecher.titel COLLATE NOCASE ASC";
    $buecher = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $buecher = [];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bücherübersicht - Verwaltung</title>
</head>
<body>
    
    <?php include_once 'navbar.php'; ?>

    <h2>Systemverwaltung: Aktueller Medienbestand</h2>
    
    <?php if (isset($_GET['success'])): ?>
        <p style="color: green; font-weight: bold;">Medium erfolgreich aus dem System gelöscht!</p>
    <?php endif; ?>

    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Autor</th>
                <th>ISBN</th>
                <th>Typ</th>
                <th>Bestand</th>
                <th>KI-Zusammenfassung (Ollama)</th>
                <th>System-Zeitstempel</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($buecher as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['id']) ?></td>
                    <td><strong><?= htmlspecialchars($b['titel']) ?></strong></td>
                    <td><?= htmlspecialchars($b['autor']) ?></td>
                    <td><?= htmlspecialchars($b['isbn']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($b['typ'])) ?></td>
                    <td>
                        <?= htmlspecialchars($b['bestand']) ?> Stück
                    </td>
                    <td>
                        <div style="max-width: 300px; font-size: 0.9em;">
                            <?php if (!empty($b['zusammenfassung'])): ?>
                                <strong>Inhalt:</strong> <?= htmlspecialchars($b['zusammenfassung']) ?>
                            <?php else: ?>
                                <span style="color: #999;">Keine KI-Analyse (Physisches Buch)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <small>Erstellt: <?= htmlspecialchars($b['angelegt_am']) ?><br>
                        Geändert: <?= htmlspecialchars($b['aktualisiert_am']) ?></small>
                    </td>
                    <td>
                        <a href="buch_bearbeiten.php?id=<?= $b['id'] ?>">Bearbeiten</a> | 
                        <a href="buch_uebersicht.php?delete_id=<?= $b['id'] ?>" onclick="return confirm('Medium wirklich löschen?');">Löschen</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>