<?php
// Session starten, um Zugriff auf die Benutzerrolle und ID des angemeldeten Benutzers zu haben
session_start();

// Cache-Control-Header setzen, um zu verhindern, dass der Browser die Seite im Cache speichert.
// Dies ist wichtig nach dem Logout: Der Benutzer kann nicht über die "Zurück"-Schaltfläche
// des Browsers auf vertrauliche Bibliotheksdaten zugreifen.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// db.php einbinden, um die SQLite PDO Verbindung ($db) zu laden
include_once 'db.php';

// Sicherheits-Check: Falls der Nutzer gar nicht eingeloggt ist (keine Session-ID existiert),
// wird er direkt zur Anmeldeseite (login.php) weitergeleitet und das Skript gestoppt.
if (!isset($_SESSION['nutzer_id'])) {
    header("Location: login.php");
    exit;
}

$meldung = '';// wird verwendet um dem user eine nachricht zu übermitteln z.b fehler beim ausleihen oder nach dem successful ausleihen
$rolle = $_SESSION['rolle'];// wird verwendet um dem user die entsprechende sicht zu zeigen z.b mitarbeiter oder nutzer
$aktueller_nutzer_id = $_SESSION['nutzer_id'];// wird verwendet um dem user die entsprechenden daten zu zeigen z.b nutzer daten

// Fehlermeldungen für Ausleihfehler über GET-Parameter auswerten und lesbar machen
if (isset($_GET['leih_fehler'])) {
    if ($_GET['leih_fehler'] === 'bestand') {// das bedeutet dasMedium was sich der nutzer ausleihen wollte ist vergriffen
        $meldung = "Fehler: Dieses Medium ist momentan vergriffen.";
    } elseif ($_GET['leih_fehler'] === 'notfound') {// das bedeutet dasMedium was sich der nutzer ausleihen wollte ist nicht in der datenbank vorhanden
        $meldung = "Fehler: Das gewünschte Medium wurde nicht gefunden.";
    } elseif ($_GET['leih_fehler'] === 'gesperrt') {// das bedeutet dasNutzer konto ist gesperrt
        $meldung = "Fehler: Ihr Benutzerkonto ist gesperrt.";
    } elseif ($_GET['leih_fehler'] === 'rechte') {// das bedeutet dasNutzer hat keine rechte für diese aktion
        $meldung = "Fehler: Sie haben keine Berechtigung für diese Aktion.";
    } else {// ansonsten gibt es eine allgemeine fehlermeldung
        $meldung = "Fehler: Der     Ausleihvorgang konnte nicht durchgeführt werden.";
    }
}

// --- LOGIK FÜR DEN KI-BIBLIOTHEKAR (OLLAMA RAG-SYSTEM) ---
$ki_antwort = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ki_frage'])) {// wenn der nutzer eine frage an den ki-bibliothekar stellt
    $user_query = trim($_POST['ki_frage']);
    if (!empty($user_query)) {
        try {
            // 1. Kontext-Wissen laden (RAG - Retrieval-Augmented Generation):
            // Wir laden den gesamten aktuellen Buchbestand samt deren KI-Zusammenfassungen.
            $ctx_query = $db->query("
                SELECT b.id, b.titel, b.autor, b.typ, b.bestand, a.zusammenfassung, a.inhaltsverzeichnis 
                FROM buecher b
                LEFT JOIN buch_analysen a ON b.id = a.buch_id
            ");
            $ctx_books = $ctx_query->fetchAll(PDO::FETCH_ASSOC); // hier werden alle bücher aus der datenbank geladen
            $books_context = ""; // hier wird der kontext für den ki-bibliothekar gespeichert
            foreach ($ctx_books as $bk) {// hier werden alle bücher durchlaufen
                $books_context .= "- ID: {$bk['id']}, Titel: '{$bk['titel']}', Autor: '{$bk['autor']}', Format: '{$bk['typ']}', Bestand: {$bk['bestand']}\n";
                if (!empty($bk['zusammenfassung'])) {// hier wird die zusammenfassung des buches gespeichert
                    $books_context .= "  Zusammenfassung: " . $bk['zusammenfassung'] . "\n";
                }
                if (!empty($bk['inhaltsverzeichnis'])) {// hier wird das inhaltsverzeichnis des buches gespeichert
                    $books_context .= "  Inhaltsverzeichnis: " . $bk['inhaltsverzeichnis'] . "\n";
                }
            }

            // 2. Ollama API Verbindung konfigurieren
            $ollama_url = "https://ollama.com/v1/chat/completions";
            $api_key = "25341745defc47f8b6af81c2a6c6e91a.4nEigoTHxY7kUpl6pdCXUc_q";

            // Der System-Prompt teilt der KI ihren Namen, ihre Rolle und das RAG-Buchwissen mit.
            $system_prompt = "Du bist ein hilfsbereiter, kompetenter und freundlicher KI-Bibliothekar namens Ollama. Beantworte die Fragen des Nutzers und gib detaillierte, ausführliche und qualitativ hochwertige Buchempfehlungen basierend auf dem folgenden Buchbestand sowie den Inhalten (Zusammenfassungen und Inhaltsverzeichnisse):\n"
                . $books_context
                . "\nRegeln für deine Antwort:\n"
                . "1. Beziehe dich primär auf die Bücher aus dieser Liste. Nutze die hinterlegten Zusammenfassungen und Inhaltsverzeichnisse intensiv, um dem Nutzer fundierte und maßgeschneiderte Empfehlungen auszusprechen.\n"
                . "2. Antworte auf Deutsch. Nimm dir ausreichend Raum, um die Bücher verständlich, flüssig und strukturiert vorzustellen (z. B. durch Absätze, Stichpunkte oder kurze Auszüge aus den Zusammenfassungen).\n"
                . "3. Wenn kein Buch aus der Liste zu der Frage passt oder das gewünschte Thema nicht abgedeckt ist, weise freundlich darauf hin und schlage passende Alternativen aus dem Bestand vor.";

            $data = [
                "model" => "gemma3:12b",// hier wird das KI-Modell angegeben
                "messages" => [
                    [
                        "role" => "system",
                        "content" => $system_prompt// hier werden die buchempfehlungen generiert
                    ],
                    [
                        "role" => "user",
                        "content" => $user_query // hier wird die frage des nutzers gespeichert
                    ]
                ],
                "stream" => false
            ];

            // HTTP POST an Ollama absenden
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n" .
                        "Authorization: Bearer " . $api_key . "\r\n", // hier wird die api-key angegeben
                    'content' => json_encode($data), // hier werden die buchempfehlungen generiert
                    'timeout' => 20,
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($ollama_url, false, $context);

            $http_code = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
                $http_code = intval($match[1] ?? 0);
            }

            if ($http_code === 200 && $response) {
                $json = json_decode($response, true);
                $ki_antwort = $json['choices'][0]['message']['content'] ?? 'Keine Antwort erhalten.';
            } else {
                $ki_antwort = "Der KI-Bibliothekar ist momentan nicht erreichbar (Ollama offline).";
            }
        } catch (Exception $e) {
            $ki_antwort = "Fehler bei der KI-Anfrage: " . $e->getMessage();
        }
    }
}

// --- LOGIK FÜR MITARBEITER: NUTZER / KUNDEN LÖSCHEN ---
if ($rolle === 'mitarbeiter' && isset($_GET['delete_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM nutzer WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        header("Location: index.php?success=1");
        exit;
    } catch (PDOException $e) {
        $meldung = "Fehler beim Löschen: " . htmlspecialchars($e->getMessage());
    }
}

// --- DATEN LADEN JE NACH BENUTZERROLLE ---
try {
    if ($rolle === 'mitarbeiter') {
        // Mitarbeiter-Ansicht: Lädt alle registrierten Kunden und deren geliehene Bücher
        $sql = "SELECT nutzer.*, ausleihen.id AS ausleihe_id, buecher.titel AS buch_titel 
                FROM nutzer 
                LEFT JOIN ausleihen ON nutzer.id = ausleihen.nutzer_id
                LEFT JOIN buecher ON ausleihen.buch_id = buecher.id
                ORDER BY nutzer.id DESC";
        $query = $db->query($sql);
        $daten = $query->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Kunden-Ansicht: Lädt nur die eigenen aktiven Ausleihen des Kunden
        $stmt = $db->prepare("SELECT ausleihen.id AS ausleihe_id, buecher.titel AS buch_titel, buecher.typ, buecher.pdf_pfad, ausleihen.ausgeliehen_am 
                FROM ausleihen 
                JOIN buecher ON ausleihen.buch_id = buecher.id
                WHERE ausleihen.nutzer_id = ?");
        $stmt->execute([$aktueller_nutzer_id]);
        $meine_ausleihen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Lädt den ausleihbaren Medienkatalog für Kunden (sortiert alphabetisch nach Autor, dann Titel)
        $buecher_query = $db->query("SELECT buecher.*, buch_analysen.zusammenfassung 
                                     FROM buecher 
                                     LEFT JOIN buch_analysen ON buecher.id = buch_analysen.buch_id
                                     ORDER BY buecher.autor COLLATE NOCASE ASC, buecher.titel COLLATE NOCASE ASC");
        $verfuegbare_buecher = $buecher_query->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $daten = [];
    $meine_ausleihen = [];
    $meldung = "Fehler beim Laden: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Digitale Bibliothek - Startseite</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #ffffff;
            color: #000000;
            margin: 20px;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .alert-success {
            color: green;
            border: 1px solid green;
            background-color: #e6ffe6;
        }

        .alert-danger {
            color: red;
            border: 1px solid red;
            background-color: #ffe6e6;
        }

        .ai-box {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
            max-width: 800px;
        }

        .ai-title {
            margin-top: 0;
            font-weight: bold;
        }

        .search-form-container {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
            max-width: 800px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 30px;
        }

        th,
        td {
            text-align: left;
            padding: 8px;
        }

        summary {
            cursor: pointer;
            font-weight: bold;
            color: blue;
        }
    </style>
</head>

<body>

    <?php include_once 'navbar.php'; ?>

    <div class="container">

        <?php if (isset($_GET['success']) || isset($_GET['leih_erfolg'])): ?>
            <div class="alert alert-success">
                <?= isset($_GET['leih_erfolg']) ? "Medium erfolgreich ausgeliehen!" : "Aktion erfolgreich ausgeführt!" ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($meldung)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($meldung) ?>
            </div>
        <?php endif; ?>

        <?php if ($rolle === 'mitarbeiter'): ?>
            <h2>Kunden- und Systemverwaltung</h2>
            <p>
                <a href="kunde_anlegen.php">Neuen Kunden registrieren</a> |
                <a href="buch_uebersicht.php">Zur Bücher-Übersicht</a> |
                <a href="ausleihen.php">Manuelle Ausleihe eintragen</a>
            </p>

            <table border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Adresse</th>
                        <th>E-Mail</th>
                        <th>Rolle</th>
                        <th>Status</th>
                        <th>Ausgeliehenes Buch</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daten)): ?>
                        <tr>
                            <td colspan="8">Keine Nutzer im System.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daten as $n): ?>
                            <tr>
                                <td><?= htmlspecialchars($n['id']) ?></td>
                                <td><?= htmlspecialchars($n['name']) ?></td>
                                <td><?= htmlspecialchars($n['adresse']) ?></td>
                                <td><?= htmlspecialchars($n['email']) ?></td>
                                <td><strong><?= htmlspecialchars($n['rolle']) ?></strong></td>
                                <td>
                                    <?= ($n['ist_aktiv'] == 1) ? 'Berechtigt' : 'Gesperrt' ?>
                                </td>
                                <td>
                                    <?php if (!empty($n['buch_titel'])): ?>
                                        <?= htmlspecialchars($n['buch_titel']) ?><br>
                                        <a href="rueckgabe.php?id=<?= $n['ausleihe_id'] ?>"
                                            onclick="return confirm('Buch zurückgeben?');">[Zurückgeben]</a>
                                    <?php else: ?>
                                        <em>Keine Ausleihen</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="kunde_bearbeiten.php?id=<?= $n['id'] ?>">Bearbeiten</a>
                                    <?php if ($n['id'] != $aktueller_nutzer_id): ?>
                                        | <a href="index.php?delete_id=<?= $n['id'] ?>"
                                            onclick="return confirm('Wirklich löschen?');">Löschen</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else: ?>
            <h2>Meine ausgeliehenen Medien</h2>
            <table border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>Medium</th>
                        <th>Typ</th>
                        <th>Ausgeliehen am</th>
                        <th>Aktion / Lesen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($meine_ausleihen)): ?>
                        <tr>
                            <td colspan="4">Du hast aktuell keine Medien ausgeliehen.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($meine_ausleihen as $ma): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ma['buch_titel']) ?></strong></td>
                                <td><?= htmlspecialchars(ucfirst($ma['typ'])) ?></td>
                                <td><?= htmlspecialchars($ma['ausgeliehen_am']) ?></td>
                                <td>
                                    <?php if ($ma['typ'] === 'pdf' && !empty($ma['pdf_pfad'])): ?>
                                        <a href="<?= htmlspecialchars($ma['pdf_pfad']) ?>" target="_blank">[PDF öffnen & lesen]</a>
                                    <?php else: ?>
                                        <span style="color: #666;">Physisches Buch (Bitte in Filiale abgeben)</span>
                                    <?php endif; ?>
                                    | <a href="rueckgabe.php?id=<?= $ma['ausleihe_id'] ?>"
                                        onclick="return confirm('Möchtest du dieses Medium zurückgeben?');">Zurückgeben</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- OLLAMA KI-BIBLIOTHEKAR -->
            <div class="search-form-container">
                <h3>Frage deinen KI-Bibliothekar (Ollama)</h3>
                <p>
                    Frag mich nach Themen, Autoren oder Buchempfehlungen aus unserem Bestand.
                </p>
                <form method="post">
                    <input type="text" name="ki_frage" style="width: 70%; padding: 5px;"
                        placeholder="z. B. Welche Bücher habt ihr über Webentwicklung?" required
                        value="<?= htmlspecialchars($_POST['ki_frage'] ?? '') ?>">
                    <button type="submit" style="padding: 5px 10px;">Absenden</button>
                </form>
            </div>

            <?php if (!empty($ki_antwort)): ?>
                <div class="ai-box">
                    <div class="ai-title">Antwort des KI-Bibliothekars:</div>
                    <p><?= nl2br(htmlspecialchars($ki_antwort)) ?></p>
                </div>
            <?php endif; ?>

            <h2>Verfügbare Bücher & PDFs leihen</h2>
            <table border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>Buch-ID</th>
                        <th>Titel</th>
                        <th>Autor</th>
                        <th>ISBN</th>
                        <th>Format</th>
                        <th>KI-Inhaltsangabe (Ollama)</th>
                        <th>Verfügbarkeit / Bestand</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verfuegbare_buecher as $vb): ?>
                        <tr>
                            <td><?= htmlspecialchars($vb['id']) ?></td>
                            <td><strong><?= htmlspecialchars($vb['titel']) ?></strong></td>
                            <td><?= htmlspecialchars($vb['autor']) ?></td>
                            <td><?= htmlspecialchars($vb['isbn']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($vb['typ'])) ?></td>
                            <td>
                                <?php if (!empty($vb['zusammenfassung'])): ?>
                                    <details>
                                        <summary>Anzeigen</summary>
                                        <div style="margin-top: 5px; font-size: 0.9em; color: #555;">
                                            <?= htmlspecialchars($vb['zusammenfassung']) ?>
                                        </div>
                                    </details>
                                <?php else: ?>
                                    <em>Keine KI-Analyse vorhanden.</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vb['typ'] === 'pdf'): ?>
                                    Digital (<?= intval($vb['bestand']) ?> Lizenzen)
                                <?php else: ?>
                                    <?= intval($vb['bestand']) ?> Stück
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (intval($vb['bestand']) > 0): ?>
                                    <a href="ausleihen.php?buch_id=<?= $vb['id'] ?>">Jetzt ausleihen</a>
                                <?php else: ?>
                                    <?php if ($vb['typ'] === 'pdf'): ?>
                                        <span style="color: red; font-weight: bold;">Lizenzen vergriffen</span>
                                    <?php else: ?>
                                        <span style="color: red; font-weight: bold;">Aktuell vergriffen</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

</body>

</html>