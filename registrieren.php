<?php
include_once 'db.php';
session_start();// hier werden die session-variblen für die verifizierung gespeichert

$meldung = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot-Prüfung gegen Bots
    if (!empty($_POST['username_hp'])) {
        // Bot erkannt: stillschweigend abbrechen
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!empty($name) && !empty($adresse) && !empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {// hier werden die email-adressen auf ihre gültigkeit geprüft
            $meldung = "Fehler: Ungültige E-Mail-Adresse!";
        } else {
            try {
                // Generiere 6-stelligen Bestätigungscode
                $token = sprintf("%06d", mt_rand(100000, 999999));

                // Prepared Statement zur Sicherung gegen SQL-Injections
                $stmt = $db->prepare("INSERT INTO nutzer (name, adresse, email, passwort, rolle, ist_aktiv, bestaetigungstoken) VALUES (?, ?, ?, ?, ?, ?, ?)");

                // name, adresse, email, passwort, rolle, ist_aktiv, bestaetigungstoken
                $stmt->execute([
                    $name,
                    $adresse,
                    $email,
                    '', // Vorläufig leer, wird in passwort_erstellen.php befüllt
                    'user',
                    0,  // Inaktiv bis zur Verifizierung
                    $token
                ]);
                // --- E-MAIL-VERSAND ÜBER SERVER ---
                $betreff = "Verifizierungscode für deine Registrierung";
                $nachricht = "Hallo " . $name . ",\r\n\r\n";
                $nachricht .= "Dein Bestätigungscode für die Registrierung lautet: " . $token . "\r\n\r\n";
                $nachricht .= "Bitte gib diesen Code auf der Verifizierungsseite ein, um dein Passwort festzulegen.\r\n\r\n";
                $nachricht .= "Dein Bibliotheksteam";

                $host = $_SERVER['HTTP_HOST'];
                $host = preg_replace('/:\d+$/', '', $host); // Port entfernen, falls vorhanden
                $absender = "no-reply@" . $host;
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $headers .= "From: " . $absender . "\r\n";
                $headers .= "Reply-To: " . $absender . "\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();

                // E-Mail senden
                mail($email, $betreff, $nachricht, $headers);

                // E-Mail in der Session für die Verifizierungsseite speichern
                $_SESSION['registrierte_email'] = $email;

                // Weiterleitung zur Verifizierung
                header("Location: verifizieren.php");
                exit;
            } catch (PDOException $e) {
                // Code 23000 = Unique Constraint Verletzung (E-Mail existiert bereits)
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'UNIQUE') !== false) {
                    $meldung = "Fehler: Diese E-Mail-Adresse wird bereits verwendet!";
                } else {
                    $meldung = "Datenbankfehler: " . $e->getMessage();
                }
            }
        }
    } else {
        $meldung = "Bitte alle Felder ausfüllen!";
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Bibliothek - Registrieren</title>
</head>

<body style="font-family: sans-serif; margin: 20px;">
    <h2>Konto registrieren</h2>

    <?php if (!empty($meldung)): ?>
        <p style="color: red; font-weight: bold;"><?= htmlspecialchars($meldung) ?></p>
    <?php endif; ?>

    <form method="post">
        <!-- Honeypot Feld gegen Bots (für echte Nutzer unsichtbar) -->
        <div style="display: none;">
            <label for="username_hp">Bitte leer lassen:</label>
            <input type="text" id="username_hp" name="username_hp" autocomplete="off">
        </div>

        <p>Name:<br><input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></p>
        <p>Adresse (Straße, Hausnr., PLZ & Ort):<br><input type="text" name="adresse" required
                value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>"></p>
        <p>E-Mail-Adresse:<br><input type="email" name="email" required
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></p>
        <button type="submit">Registrieren & Code anfordern</button>
    </form>

    <p><a href="login.php">Zurück zum Login</a></p>
</body>

</html>