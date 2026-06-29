<?php
session_start();

// Sicherheits-Check: Nur eingeloggte Nutzer dürfen ausleihen
if (!isset($_SESSION['nutzer_id'])) {
    header("Location: index.php");
    exit;
}

include_once 'db.php';

// Wir holen uns die Buch-ID direkt aus der URL via GET
$buch_id = intval($_GET['buch_id'] ?? 0);
$nutzer_id = $_SESSION['nutzer_id'];

if ($buch_id > 0) {
    try {
        $db->beginTransaction();

        // 1. Checken, ob der Nutzer existiert und aktiv ist
        $stmtNutzer = $db->prepare("SELECT ist_aktiv FROM nutzer WHERE id = ?");
        $stmtNutzer->execute([$nutzer_id]);
        $nutzer = $stmtNutzer->fetch(PDO::FETCH_ASSOC);

        if (!$nutzer || intval($nutzer['ist_aktiv']) !== 1) {
            $db->rollBack();
            header("Location: index.php?leih_fehler=gesperrt");
            exit;
        }

        // 2. Checken, um was für ein Medium es sich handelt
        $stmtBuch = $db->prepare("SELECT typ, bestand FROM buecher WHERE id = ?");
        $stmtBuch->execute([$buch_id]);
        $buch = $stmtBuch->fetch(PDO::FETCH_ASSOC);

        if ($buch) {
            $kann_ausleihen = false;

            if ($buch['bestand'] > 0) {
                // Sowohl physische Bücher als auch E-Books/PDFs können geliehen werden, wenn Bestand vorhanden ist
                $kann_ausleihen = true;
                
                // Bestand um 1 verringern
                $stmtUpdate = $db->prepare("UPDATE buecher SET bestand = bestand - 1 WHERE id = ?");
                $stmtUpdate->execute([$buch_id]);
            }

            if ($kann_ausleihen) {
                // 3. In die Ausleihtabelle eintragen (Nutzer-ID und Buch-ID)
                $stmtAusleihe = $db->prepare("INSERT INTO ausleihen (nutzer_id, buch_id, ausgeliehen_am) VALUES (?, ?, datetime('now', 'localtime'))");
                $stmtAusleihe->execute([$nutzer_id, $buch_id]);

                $db->commit();
                header("Location: index.php?leih_erfolg=1");
                exit;
            } else {
                $db->rollBack();
                header("Location: index.php?leih_fehler=bestand");
                exit;
            }
        } else {
            $db->rollBack();
            header("Location: index.php?leih_fehler=notfound");
            exit;
        }

    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        die("Datenbankfehler beim Ausleihen: " . $e->getMessage());
    }
} else {
    // Ohne Buch-ID schicken wir den User zurück
    header("Location: index.php");
    exit;
}