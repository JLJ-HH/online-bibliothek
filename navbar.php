<?php
// Session starten, falls sie nicht bereits aktiv ist, um auf angemeldete Benutzerdaten zuzugreifen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- Navigationsleiste (Navbar): Wird oben in alle Hauptdateien eingebunden -->
<div style="background-color: #f4f4f4; padding: 10px; margin-bottom: 20px; border-bottom: 1px solid #ccc; font-family: sans-serif;">
    <strong>Digitale Bibliothek</strong> | 
    
    <?php if (isset($_SESSION['nutzer_id'])): ?>
        <!-- Anzeige des angemeldeten Benutzers, seines Namens und seiner Rolle -->
        <span>Angemeldet als: <strong><?= htmlspecialchars($_SESSION['nutzer_name']) ?></strong> (Rolle: <em><?= htmlspecialchars($_SESSION['rolle']) ?></em>)</span>
        | <a href="index.php">Hauptseite</a>
        
        <!-- Zusätzliche Links, die NUR für Mitarbeiter sichtbar sind -->
        <?php if ($_SESSION['rolle'] === 'mitarbeiter'): ?>
            | <a href="buch_uebersicht.php">Buchbestand bearbeiten</a>
            | <a href="buch_anlegen.php">Neues Buch / PDF hochladen</a>
            | <a href="ausleihen.php">Manuelle Ausleihe</a>
        <?php endif; ?>
        
        <!-- Logout-Button für alle angemeldeten Nutzer -->
        | <a href="logout.php" style="color: red; font-weight: bold;">[Ausloggen]</a>
    <?php else: ?>
        <!-- Login-Link, falls kein Benutzer angemeldet ist -->
        <a href="login.php">Einloggen</a>
    <?php endif; ?>
</div>
<hr>