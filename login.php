<?php
include_once 'db.php';
session_start();

$meldung = '';
$erfolgsmeldung = '';

if (isset($_GET['registriert']) && $_GET['registriert'] === '1') {
    $erfolgsmeldung = "Registrierung erfolgreich! Sie können sich jetzt anmelden.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot-Prüfung gegen Bots
    if (!empty($_POST['username_hp'])) {
        // Bot erkannt: stillschweigend abbrechen
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    $passwort = $_POST['passwort'] ?? '';

    if (!empty($email) && !empty($passwort)) {
        try {
            // Nutzer anhand der E-Mail in der NEUEN Tabelle 'nutzer' suchen
            $stmt = $db->prepare("SELECT * FROM nutzer WHERE email = ?");
            $stmt->execute([$email]);
            $nutzer = $stmt->fetch(PDO::FETCH_ASSOC);

            // Passwort mit dem sicheren BCRYPT-Hash abgleichen
            if ($nutzer && password_verify($passwort, $nutzer['passwort'])) {
                if (intval($nutzer['ist_aktiv']) !== 1) {
                    $meldung = "Fehler: Ihr Account wurde noch nicht verifiziert oder ist gesperrt. <a href='verifizieren.php'>Hier klicken, um die E-Mail-Adresse zu verifizieren oder einen neuen Code anzufordern.</a>";
                } else {
                    // Session-Variablen setzen
                    $_SESSION['nutzer_id'] = $nutzer['id'];
                    $_SESSION['nutzer_name'] = $nutzer['name'];
                    $_SESSION['rolle'] = $nutzer['rolle'];

                    // Weiterleitung zur Hauptseite
                    header("Location: index.php");
                    exit;
                }
            } else {
                $meldung = "Fehler: Ungültige E-Mail-Adresse oder Passwort.";
            }
        } catch (PDOException $e) {
            $meldung = "Datenbankfehler: " . $e->getMessage();
        }
    } else {
        $meldung = "Bitte alle Felder ausfüllen.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bibliothek - Login</title>
</head>
<body>
    <h2>Anmeldung zur Digitalen Bibliothek</h2>
    
    <?php if (!empty($meldung)): ?>
        <p style="color: red; font-weight: bold;"><?= htmlspecialchars($meldung) ?></p>
    <?php endif; ?>
    <?php if (!empty($erfolgsmeldung)): ?>
        <p style="color: green; font-weight: bold;"><?= htmlspecialchars($erfolgsmeldung) ?></p>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <!-- Honeypot Feld gegen Bots (für echte Nutzer unsichtbar) -->
        <div style="display: none;">
            <label for="username_hp">Bitte leer lassen:</label>
            <input type="text" id="username_hp" name="username_hp" autocomplete="off">
        </div>

        <!-- Unsichtbare Dummy-Felder zur Überlistung des Browser-Passwortmanagers -->
        <input type="text" name="username_dummy" style="position: absolute; top: -1000px; left: -1000px; width: 1px; height: 1px; opacity: 0;" tabindex="-1">
        <input type="password" name="password_dummy" style="position: absolute; top: -1000px; left: -1000px; width: 1px; height: 1px; opacity: 0;" tabindex="-1">

        <p>E-Mail-Adresse:<br><input type="email" id="email" name="email" required autocomplete="new-username"></p>
        <p>Passwort:<br><input type="password" id="passwort" name="passwort" required autocomplete="new-password"></p>
        <button type="submit">Einloggen</button>
    </form>
    
    <p><a href="registrieren.php">Noch kein Konto? Jetzt registrieren</a></p>

    <script>
        // Zusätzlicher Schutz: Leert die Felder nach dem Laden der Seite
        window.addEventListener('load', function() {
            setTimeout(function() {
                var emailField = document.getElementById('email');
                var passwortField = document.getElementById('passwort');
                if (emailField) emailField.value = '';
                if (passwortField) passwortField.value = '';
            }, 50);
        });
    </script>
</body>
</html>