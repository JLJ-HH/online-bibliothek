<?php
include_once 'db.php';
session_start();

// Wenn die E-Mail nicht verifiziert wurde, zurück zur Registrierung
if (empty($_SESSION['verifizierte_email'])) {
    header("Location: registrieren.php");
    exit;
}

$email = $_SESSION['verifizierte_email'];
$meldung = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passwort = $_POST['passwort'] ?? '';
    $passwort_wdh = $_POST['passwort_wdh'] ?? '';

    if (!empty($passwort) && !empty($passwort_wdh)) {
        if ($passwort !== $passwort_wdh) {
            $meldung = "Fehler: Die Passwörter stimmen nicht überein!";
        } elseif (strlen($passwort) < 6) {
            $meldung = "Fehler: Das Passwort muss mindestens 6 Zeichen lang sein.";
        } else {
            try {
                // Passwort sicher mit BCRYPT hashen
                $passwort_hash = password_hash($passwort, PASSWORD_BCRYPT);

                // Prepared Statement zur sicheren Aktualisierung des Nutzers
                $stmt = $db->prepare("UPDATE nutzer SET passwort = ?, ist_aktiv = 1, bestaetigungstoken = NULL WHERE email = ?");
                $stmt->execute([$passwort_hash, $email]);

                // Verifizierte E-Mail aus der Session löschen
                unset($_SESSION['verifizierte_email']);

                // Erfolgreich registriert, weiter zum Login mit Erfolgsmeldung
                header("Location: login.php?registriert=1");
                exit;
            } catch (PDOException $e) {
                $meldung = "Datenbankfehler: " . $e->getMessage();
            }
        }
    } else {
        $meldung = "Bitte füllen Sie alle Felder aus.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bibliothek - Passwort erstellen</title>
</head>
<body style="font-family: sans-serif; margin: 20px;">
    <h2>Neues Passwort festlegen</h2>
    <p>Erstellen Sie ein Passwort für Ihr Konto (E-Mail: <strong><?= htmlspecialchars($email) ?></strong>):</p>
    
    <?php if (!empty($meldung)): ?>
        <p style="color: red; font-weight: bold;"><?= htmlspecialchars($meldung) ?></p>
    <?php endif; ?>

    <form method="post">
        <p>Neues Passwort:<br>
            <input type="password" name="passwort" required minlength="6" autofocus style="padding: 5px; width: 250px;">
        </p>
        <p>Passwort wiederholen:<br>
            <input type="password" name="passwort_wdh" required minlength="6" style="padding: 5px; width: 250px;">
        </p>
        <button type="submit" style="padding: 6px 12px;">Passwort speichern & Account aktivieren</button>
    </form>
</body>
</html>
