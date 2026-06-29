<?php
// Session starten, um Zugriff auf die Benutzerrolle zu haben
session_start();

// Sicherheits-Check: Nur Mitarbeiter (Rolle: mitarbeiter) dürfen neue Kunden im System registrieren
if (!isset($_SESSION['nutzer_id']) || $_SESSION['rolle'] !== 'mitarbeiter') {
    header("Location: index.php");
    exit;
}

// db.php für die SQLite PDO-Verbindung einbinden
include_once 'db.php';
$meldung = '';

// Wenn das Formular per POST abgesendet wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eingabewerte bereinigen
    $name    = trim($_POST['name'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $email   = trim($_POST['email'] ?? '');

    // Prüfen, ob alle benötigten Felder ausgefüllt sind
    if (!empty($name) && !empty($adresse) && !empty($email)) {
        // E-Mail-Format serverseitig validieren
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $meldung = "Fehler: Ungültige E-Mail-Adresse!";
        } else {
            try {
                // Ein Standardpasswort generieren und sicher mit BCRYPT hashen (Default für neue Kunden: 'user123')
                $default_pw = password_hash('user123', PASSWORD_BCRYPT);
                
                // Kunde in die Tabelle 'nutzer' einfügen (Rolle ist standardmäßig 'user', Status ist aktiv [ist_aktiv = 1])
                $stmt = $db->prepare("INSERT INTO nutzer (name, adresse, email, passwort, rolle, ist_aktiv) VALUES (?, ?, ?, ?, 'user', 1)");
                $stmt->execute([$name, $adresse, $email, $default_pw]);
                
                // Nach erfolgreichem Speichern zur Startseite weiterleiten
                header("Location: index.php?success=1");
                exit;
            } catch (PDOException $e) {
                // Fehlercode 23000 fängt einen Unique-Constraint-Fehler ab (wenn E-Mail bereits vergeben ist)
                $meldung = ($e->getCode() == 23000) ? "Fehler: E-Mail existiert bereits!" : "Datenbankfehler: " . $e->getMessage();
            }
        }
    } else {
        $meldung = "Bitte alle Felder ausfüllen!";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Kunde anlegen</title></head>
<body>
    <h2>Neuen Kunden registrieren</h2>
    <?php if (!empty($meldung)): ?><p style="color: red;"><?= htmlspecialchars($meldung) ?></p><?php endif; ?>
    
    <form method="post">
        <p>Name:<br><input type="text" name="name" required></p>
        <p>Adresse:<br><input type="text" name="adresse" required></p>
        <p>E-Mail:<br><input type="email" name="email" required></p>
        <p><button type="submit">Speichern</button> <a href="index.php">Abbrechen</a></p>
    </form>
</body>
</html>