<?php
include_once 'db.php';
session_start();

$email = $_SESSION['registrierte_email'] ?? '';
$meldung = '';
$erfolgsmeldung = '';

// 1. Verarbeitung Code-Verifizierung (POST von code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = trim($_POST['code'] ?? '');

    if (!empty($code) && !empty($email)) {
        try {
            $stmt = $db->prepare("SELECT bestaetigungstoken, ist_aktiv FROM nutzer WHERE email = ?");
            $stmt->execute([$email]);
            $nutzer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($nutzer) {
                if ($nutzer['ist_aktiv'] == 1) {
                    $meldung = "Dieses Konto ist bereits aktiviert. Bitte melde dich an.";
                } elseif ($nutzer['bestaetigungstoken'] === $code) {
                    // Token stimmt überein! E-Mail als verifiziert markieren und weiterleiten
                    $_SESSION['verifizierte_email'] = $email;
                    
                    // Temporäre Registrierungsdaten aus der Session aufräumen
                    unset($_SESSION['registrierte_email']);

                    header("Location: passwort_erstellen.php");
                    exit;
                } else {
                    $meldung = "Fehler: Der eingegebene Bestätigungscode ist ungültig.";
                }
            } else {
                $meldung = "Fehler: E-Mail-Adresse wurde nicht gefunden.";
            }
        } catch (PDOException $e) {
            $meldung = "Datenbankfehler: " . $e->getMessage();
        }
    } else {
        $meldung = "Bitte geben Sie den Bestätigungscode ein.";
    }
}

// 2. Verarbeitung Code-Erneuerung (POST von resend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $resend_email = trim($_POST['resend_email'] ?? '');

    if (!empty($resend_email)) {
        if (!filter_var($resend_email, FILTER_VALIDATE_EMAIL)) {
            $meldung = "Fehler: Ungültige E-Mail-Adresse!";
        } else {
            try {
                // Prüfen, ob der Nutzer existiert und inaktiv ist
                $stmtFind = $db->prepare("SELECT name, ist_aktiv FROM nutzer WHERE email = ?");
                $stmtFind->execute([$resend_email]);
                $nutzer = $stmtFind->fetch(PDO::FETCH_ASSOC);

                if ($nutzer) {
                    if ($nutzer['ist_aktiv'] == 1) {
                        $meldung = "Diese E-Mail-Adresse ist bereits verifiziert und aktiv. Bitte logge dich ein.";
                    } else {
                        // Generiere neuen 6-stelligen Bestätigungscode
                        $token = sprintf("%06d", mt_rand(100000, 999999));
                        
                        // Token in DB aktualisieren
                        $stmtUpdate = $db->prepare("UPDATE nutzer SET bestaetigungstoken = ? WHERE email = ?");
                        $stmtUpdate->execute([$token, $resend_email]);

                        // E-Mail senden
                        $betreff = "Verifizierungscode für deine Registrierung (erneut gesendet)";
                        $nachricht = "Hallo " . $nutzer['name'] . ",\r\n\r\n";
                        $nachricht .= "Dein neuer Bestätigungscode für die Registrierung lautet: " . $token . "\r\n\r\n";
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

                        mail($resend_email, $betreff, $nachricht, $headers);

                        // E-Mail in der Session und Variable für das obere Formular aktualisieren
                        $_SESSION['registrierte_email'] = $resend_email;
                        $email = $resend_email;

                        $erfolgsmeldung = "Ein neuer Bestätigungscode wurde erfolgreich gesendet!";
                    }
                } else {
                    $meldung = "Fehler: Zu dieser E-Mail-Adresse wurde keine ausstehende Registrierung gefunden.";
                }
            } catch (PDOException $e) {
                $meldung = "Datenbankfehler: " . $e->getMessage();
            }
        }
    } else {
        $meldung = "Bitte gib eine E-Mail-Adresse an.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bibliothek - E-Mail verifizieren</title>
</head>
<body style="font-family: sans-serif; margin: 20px;">
    <h2>E-Mail-Adresse verifizieren</h2>
    
    <?php if (!empty($meldung)): ?>
        <p style="color: red; font-weight: bold;"><?= $meldung ?></p>
    <?php endif; ?>
    <?php if (!empty($erfolgsmeldung)): ?>
        <p style="color: green; font-weight: bold;"><?= htmlspecialchars($erfolgsmeldung) ?></p>
    <?php endif; ?>

    <?php if (!empty($email)): ?>
        <p>Wir haben einen Bestätigungscode an deine E-Mail-Adresse (<strong><?= htmlspecialchars($email) ?></strong>) gesendet. Bitte gib den Code hier ein, um dein Konto zu aktivieren.</p>

        <form method="post">
            <p>Bestätigungscode (6-stellig):<br>
                <input type="text" name="code" required maxlength="6" pattern="[0-9]{6}" placeholder="123456" autofocus style="font-size: 1.1em; padding: 5px; width: 150px; text-align: center;">
            </p>
            <button type="submit" style="padding: 6px 12px;">Code verifizieren</button>
        </form>
        <hr style="margin: 30px 0;">
    <?php endif; ?>

    <h3>Neuen Bestätigungscode anfordern</h3>
    <p>Falls du deinen Code nicht erhalten hast oder er abgelaufen ist, kannst du hier einen neuen anfordern.</p>
    <form method="post">
        <p>E-Mail-Adresse:<br>
            <input type="email" name="resend_email" required value="<?= htmlspecialchars($email) ?>" style="padding: 5px; width: 250px;">
        </p>
        <button type="submit" name="resend" style="padding: 6px 12px;">Neuen Code senden</button>
    </form>
    
    <p style="margin-top: 35px;"><a href="registrieren.php">Zurück zur Registrierung</a> | <a href="login.php">Zurück zum Login</a></p>
</body>
</html>
