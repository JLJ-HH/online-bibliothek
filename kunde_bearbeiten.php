<?php
// Session starten, um Zugriff auf Benutzerdaten/Rollen zu erhalten
session_start();

// Sicherheits-Check: Nur Mitarbeiter (Rolle: mitarbeiter) dürfen Kundendaten bearbeiten
if (!isset($_SESSION['nutzer_id']) || $_SESSION['rolle'] !== 'mitarbeiter') {
    header("Location: index.php");
    exit;
}

// db.php für die SQLite PDO-Verbindung einbinden
include_once 'db.php';
$meldung = '';

// Die ID des zu bearbeitenden Kunden wird per GET-Parameter aus der URL geladen
$id = $_GET['id'] ?? '';
if (empty($id)) { 
    header("Location: index.php"); 
    exit; 
}

// Aktuelle Kundendaten aus der Datenbank laden, um das Formular vorauszufüllen
try {
    $stmt = $db->prepare("SELECT * FROM nutzer WHERE id = ?");
    $stmt->execute([$id]);
    $kunde = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Falls kein Kunde mit dieser ID existiert, zur Startseite umleiten
    if (!$kunde) { 
        header("Location: index.php"); 
        exit; 
    }
} catch (PDOException $e) {
    $meldung = "Fehler: " . $e->getMessage();
}

// Wenn das Formular per POST abgesendet wurde (Änderungen speichern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $adresse   = trim($_POST['adresse'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $ist_aktiv = intval($_POST['ist_aktiv'] ?? 1); // 1 = Aktiv, 0 = Gesperrt

    // Pflichtfelder prüfen
    if (!empty($name) && !empty($adresse) && !empty($email)) {
        try {
            // Daten in Tabelle 'nutzer' aktualisieren (inkl. des Status 'ist_aktiv')
            $stmtUpdate = $db->prepare("UPDATE nutzer SET name = ?, adresse = ?, email = ?, ist_aktiv = ? WHERE id = ?");
            $stmtUpdate->execute([$name, $adresse, $email, $ist_aktiv, $id]);
            
            // Nach erfolgreichem Speichern zur Startseite weiterleiten
            header("Location: index.php?success=1");
            exit;
        } catch (PDOException $e) {
            $meldung = "Fehler beim Ändern der Daten.";
        }
    } else {
        $meldung = "Pflichtfelder ausfüllen!";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Kunde bearbeiten</title></head>
<body>
    <h2>Kundendaten bearbeiten</h2>
    <?php if (!empty($meldung)): ?><p style="color: red;"><?= htmlspecialchars($meldung) ?></p><?php endif; ?>

    <form method="post">
        <p>Name:<br><input type="text" name="name" value="<?= htmlspecialchars($kunde['name']) ?>" required></p>
        <p>Adresse:<br><input type="text" name="adresse" value="<?= htmlspecialchars($kunde['adresse']) ?>" required></p>
        <p>E-Mail:<br><input type="email" name="email" value="<?= htmlspecialchars($kunde['email']) ?>" required></p>
        <p>Status:<br>
            <select name="ist_aktiv">
                <option value="1" <?= $kunde['ist_aktiv'] == 1 ? 'selected' : '' ?>>Aktiv (Berechtigt)</option>
                <option value="0" <?= $kunde['ist_aktiv'] == 0 ? 'selected' : '' ?>>Gesperrt</option>
            </select>
        </p>
        <p><button type="submit">Änderungen speichern</button> <a href="index.php">Abbrechen</a></p>
    </form>
</body>
</html>