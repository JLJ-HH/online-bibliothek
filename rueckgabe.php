<?php
session_start();

// Sicherheits-Check: Nur eingeloggte Nutzer dürfen Rückgaben durchführen
if (!isset($_SESSION['nutzer_id'])) {
    header("Location: index.php");
    exit;
}

include_once 'db.php';

// Prüfen, ob die ID der Ausleihe übergeben wurde
$ausleihe_id = $_GET['id'] ?? '';

if (!empty($ausleihe_id)) {
    try {
        // 1. Zuerst die buch_id und nutzer_id aus der Ausleihe holen, um Berechtigung und Bestand zu prüfen
        $stmtFind = $db->prepare("SELECT buch_id, nutzer_id FROM ausleihen WHERE id = ?");
        $stmtFind->execute([$ausleihe_id]);
        $ausleihe = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($ausleihe) {
            // Berechtigungsprüfung: Nur Mitarbeiter oder der ausleihende Nutzer selbst dürfen zurückgeben
            if ($_SESSION['rolle'] !== 'mitarbeiter' && intval($ausleihe['nutzer_id']) !== intval($_SESSION['nutzer_id'])) {
                header("Location: index.php?leih_fehler=rechte");
                exit;
            }

            $buch_id = $ausleihe['buch_id'];

            // Transaktion starten, damit beide SQLs sicher durchlaufen
            $db->beginTransaction();

            // A. Buchbestand um 1 erhöhen (sowohl für physische Medien als auch E-Books/PDFs)
            $stmtUpdateBuch = $db->prepare("UPDATE buecher SET bestand = bestand + 1 WHERE id = ?");
            $stmtUpdateBuch->execute([$buch_id]);

            // B. Den Ausleihdatensatz löschen
            $stmtDelete = $db->prepare("DELETE FROM ausleihen WHERE id = ?");
            $stmtDelete->execute([$ausleihe_id]);

            $db->commit();
        }
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // Bei einem Fehler stoppen und anzeigen
        exit("Datenbankfehler bei der Rückgabe: " . htmlspecialchars($e->getMessage()));
    }
}

// Nach erfolgreicher Rückgabe direkt zurück zur Startseite
header("Location: index.php?success=1");
exit;
?>