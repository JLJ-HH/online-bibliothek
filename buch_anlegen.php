<?php
// Session starten, um Zugriff auf die Benutzerdaten des angemeldeten Benutzers zu haben
session_start();

// Sicherheits-Check: Prüfen, ob der Nutzer eingeloggt ist UND ob er die Rolle 'mitarbeiter' besitzt.
// Wenn nicht, wird er sofort auf die Startseite (index.php) weitergeleitet und das Skript gestoppt.
if (!isset($_SESSION['nutzer_id']) || $_SESSION['rolle'] !== 'mitarbeiter') {
    header("Location: index.php");
    exit;
}

// db.php einbinden, um Zugriff auf die PDO-Datenbankverbindung ($db) zu haben
include_once 'db.php';

// Fehlermeldung und Erfolgsmeldung initialisieren
$meldung = '';
$erfolgsmeldung = '';

// Wenn das Formular per POST abgesendet wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eingabewerte bereinigen (Trimmen entfernt überflüssige Leerzeichen am Anfang/Ende)
    $titel = trim($_POST['titel'] ?? '');
    $autor = trim($_POST['autor'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $bestand = intval($_POST['bestand'] ?? 1);
    $typ = $_POST['typ'] ?? 'physisch';
    $pdf_pfad = null;
    $entries = []; // Array zur Aufnahme der zu speichernden Bücher und Nutzer

    // --- 1. VERARBEITUNG WENN EINE PDF HOCHGELADEN WURDE ---
    if ($typ === 'pdf' && isset($_FILES['pdf_datei']) && $_FILES['pdf_datei']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['pdf_datei']['tmp_name'];
        $fileName = time() . '_' . $_FILES['pdf_datei']['name']; // Eindeutigen Dateinamen per Zeitstempel erzeugen
        $uploadFileDir = __DIR__ . '/uploads/';
        $dest_path = $uploadFileDir . $fileName;

        // PDF in das uploads-Verzeichnis verschieben
        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $pdf_pfad = 'uploads/' . $fileName; // Relativen Pfad für die Datenbank speichern
            $bestand = 0; // Standardwert für PDF-Bestand (wird durch das KI-Parsing mit Lizenzen überschrieben)

            try {
                // Versuchen, Text aus der PDF extrahieren (falls Smalot/PdfParser installiert ist)
                $text_auszug = '';
                if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                    require_once __DIR__ . '/vendor/autoload.php';
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($dest_path);
                    $text_auszug = trim($pdf->getText());
                }

                if (!empty($text_auszug)) {
                    // Kürzen auf max. 2500 Zeichen für die Ollama API, um Token-Limits zu wahren
                    $text_auszug = mb_substr($text_auszug, 0, 2500);

                    // --- 2. OLLAMA KI-ANBINDUNG (DATENEXTRAKTION) ---
                    $ollama_url = "https://ollama.com/v1/chat/completions";
                    $api_key = "25341745defc47f8b6af81c2a6c6e91a.4nEigoTHxY7kUpl6pdCXUc_q";
                    $model_name = "gemma3:12b";

                    // System-Instruktion: Teilt der KI ihre Rolle und das exakte JSON-Format mit
                    $system_instruction = "Du bist ein präziser Datenextraktions-Assistent. "
                        . "Analysiere den folgenden Text aus einer hochgeladenen PDF-Datei. "
                        . "Bestimme, ob der Text Informationen über Bücher (Titel, Autor, ISBN) oder Nutzer/Kunden (Name, Adresse, E-Mail) enthält. "
                        . "Es können ein oder mehrere Bücher, und/oder ein oder mehrere Nutzer enthalten sein. "
                        . "Antworte AUSSCHLIESSLICH im folgenden JSON-Format. "
                        . "Gib KEINEN Text vor oder nach dem JSON aus. Benutze KEINE Markdown-Formatierung wie ```json.\n\n"
                        . "Format:\n"
                        . "{\n"
                        . "  \"entries\": [\n"
                        . "    {\n"
                        . "      \"type\": \"book\",\n"
                        . "      \"title\": \"Titel des Buches\",\n"
                        . "      \"author\": \"Autor des Buches\",\n"
                        . "      \"isbn\": \"ISBN-Nummer\",\n"
                        . "      \"summary\": \"Kurze, dreizeilige Zusammenfassung des Inhalts (Generiere diese aus deinem Allgemeinwissen über das Buch, falls im PDF-Text keine Inhaltsangabe enthalten ist)\",\n"
                        . "      \"table_of_contents\": \"Kurzes Inhaltsverzeichnis oder wichtige Kapitel (Generiere dieses aus deinem Allgemeinwissen über das Buch, falls im PDF-Text kein Inhaltsverzeichnis enthalten ist)\",\n"
                        . "      \"format\": \"physisch\" oder \"pdf\" (Bestimme anhand des Texts, ob es ein gedrucktes/physisches Buch [physisch] oder ein digitales E-Book [pdf] ist. Standard ist pdf, falls unklar),\n"
                        . "      \"stock\": [Zahl] (Erkannter physischer Bestand aus dem Text, falls vorhanden. Falls nicht angegeben: 1 für physische Bücher, 0 für E-Books/PDFs)\n"
                        . "    },\n"
                        . "    {\n"
                        . "      \"type\": \"user\",\n"
                        . "      \"name\": \"Name des Nutzers\",\n"
                        . "      \"address\": \"Adresse des Nutzers\",\n"
                        . "      \"email\": \"E-Mail-Adresse\"\n"
                        . "    }\n"
                        . "  ]\n"
                        . "}";

                    $data = [
                        "model" => $model_name,
                        "messages" => [
                            [
                                "role" => "system",
                                "content" => $system_instruction
                            ],
                            [
                                "role" => "user",
                                "content" => "Hier ist der extrahierte Text:\n" . $text_auszug
                            ]
                        ],
                        "stream" => false
                    ];

                    // Stream-Kontext für den cURL-freien HTTP-POST-Request konfigurieren
                    $options = [
                        'http' => [
                            'method' => 'POST',
                            'header' => "Content-Type: application/json\r\n" .
                                        "Authorization: Bearer " . $api_key . "\r\n",
                            'content' => json_encode($data),
                            'timeout' => 25,
                            'ignore_errors' => true
                        ]
                    ];
                    $context = stream_context_create($options);
                    $response = @file_get_contents($ollama_url, false, $context);

                    // HTTP-Statuscode aus den Antwort-Headern auslesen
                    $http_code = 0;
                    if (isset($http_response_header) && is_array($http_response_header)) {
                        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
                        $http_code = intval($match[1] ?? 0);
                    }

                    // Bei Erfolg (HTTP 200) die JSON-Antwort extrahieren
                    if ($http_code === 200 && $response) {
                        $json = json_decode($response, true);
                        $ki_antwort = $json['choices'][0]['message']['content'] ?? '';
                        
                        // Eventuelle Markdown-Markierungen der KI (```json) bereinigen
                        $ki_antwort = trim($ki_antwort);
                        if (strpos($ki_antwort, '```json') === 0) {
                            $ki_antwort = substr($ki_antwort, 7);
                        }
                        if (substr($ki_antwort, -3) === '```') {
                            $ki_antwort = substr($ki_antwort, 0, -3);
                        }
                        $ki_antwort = trim($ki_antwort);

                        // Bereinigtes JSON in das Array $entries parsen
                        $parsed_data = json_decode($ki_antwort, true);
                        if (isset($parsed_data['entries']) && is_array($parsed_data['entries'])) {
                            $entries = $parsed_data['entries'];
                        }
                    }
                }
            } catch (Exception $e) {
                // PDF-Fehler abfangen, falls etwas beim Parsen fehlschlägt (Fallback greift unten)
            }
        } else {
            $meldung = "Fehler beim Verschieben der hochgeladenen Datei.";
        }
    }

    // --- FALLBACK: WENN DAS FORMULAR MANUELL AUSGEFÜLLT WURDE ---
    // (Oder der PDF-Parser keine Einträge extrahieren konnte)
    if (empty($entries) && empty($meldung)) {
        if (!empty($titel) && !empty($autor) && !empty($isbn)) {
            $ki_zusammenfassung = '';
            $ki_inhaltsverzeichnis = '';
            
            try {
                $ollama_url = "https://ollama.com/v1/chat/completions";
                $api_key = "25341745defc47f8b6af81c2a6c6e91a.4nEigoTHxY7kUpl6pdCXUc_q";
                $model_name = "gemma3:12b";

                // Je nachdem, ob es ein E-Book oder physisches Buch ist, anpassen
                if ($typ === 'pdf' && !empty($pdf_pfad)) {
                    $text_auszug = "Das Buch trägt den Titel '" . $titel . "' und wurde vom Autor " . $autor . " verfasst. ";
                    $text_auszug .= "Es handelt sich um ein Fachbuch aus dem Bereich der Informatik, Literatur oder Wissenschaft mit der ISBN " . $isbn . ".";
                    
                    $system_instruction = "Analysiere folgenden Buchtext-Auszug. Reagiere AUSSCHLIESSLICH im folgenden Format:\n"
                        . "ZUSAMMENFASSUNG: [Schreibe eine kurze, dreizeilige Zusammenfassung des Inhalts]\n"
                        . "INHALTSVERZEICHNIS: [Erstelle ein kurzes, fiktives oder echtes Inhaltsverzeichnis basierend auf dem Text]";
                } else {
                    $text_auszug = "Titel: " . $titel . ", Autor: " . $autor . ", ISBN: " . $isbn;
                    
                    $system_instruction = "Du bist ein Literaturexperte. Generiere für das folgende Buch eine kurze Zusammenfassung und eine Kapitelübersicht aus deinem Wissen. Reagiere AUSSCHLIESSLICH im folgenden Format:\n"
                        . "ZUSAMMENFASSUNG: [Schreibe eine kurze, dreizeilige Zusammenfassung des Inhalts]\n"
                        . "INHALTSVERZEICHNIS: [Erstelle ein kurzes Inhaltsverzeichnis oder wichtige Kapitel]";
                }

                $data = [
                    "model" => $model_name,
                    "messages" => [
                        ["role" => "system", "content" => $system_instruction],
                        ["role" => "user", "content" => $text_auszug]
                    ],
                    "stream" => false
                ];
                $options = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $api_key . "\r\n",
                        'content' => json_encode($data),
                        'timeout' => 25,
                        'ignore_errors' => true
                    ]
                ];
                $context = stream_context_create($options);
                $response = @file_get_contents($ollama_url, false, $context);
                if ($response) {
                    $json = json_decode($response, true);
                    $ki_antwort = $json['choices'][0]['message']['content'] ?? '';
                    // Extraktion der Blöcke via Regulären Ausdrücken
                    if (preg_match('/ZUSAMMENFASSUNG:(.*)(?=INHALTSVERZEICHNIS:)/is', $ki_antwort, $matches_sub)) {
                        $ki_zusammenfassung = trim($matches_sub[1]);
                    }
                    if (preg_match('/INHALTSVERZEICHNIS:(.*)/is', $ki_antwort, $matches_toc)) {
                        $ki_inhaltsverzeichnis = trim($matches_toc[1]);
                    }
                    if (empty($ki_zusammenfassung)) {
                        $ki_zusammenfassung = $ki_antwort;
                    }
                }
            } catch (Exception $e) {
                $ki_zusammenfassung = "Fehler bei der Textanalyse: " . $e->getMessage();
            }
            
            // Einzelnen manuellen Eintrag in die Liste aufnehmen
            $entries[] = [
                'type' => 'book',
                'title' => $titel,
                'author' => $autor,
                'isbn' => $isbn,
                'summary' => $ki_zusammenfassung ?: 'Keine Zusammenfassung vorhanden.',
                'table_of_contents' => $ki_inhaltsverzeichnis ?: '',
                'typ_override' => $typ,
                'bestand_override' => $bestand
            ];
        }
    }

    // --- 3. SPEICHERN IN DER DATENBANK ---
    if (empty($meldung) && !empty($entries)) {
        $buecher_erfolgreich = 0;
        $nutzer_erfolgreich = 0;
        $fehler_details = [];

        try {
            // Datenbank-Transaktion starten, damit bei Fehlern alle Tabelleneinträge zurückgerollt werden können
            $db->beginTransaction();

            foreach ($entries as $entry) {
                if (($entry['type'] ?? '') === 'book') {
                    $entry_titel = trim($entry['title'] ?? '');
                    $entry_autor = trim($entry['author'] ?? '');
                    $entry_isbn = trim($entry['isbn'] ?? '');
                    $entry_summary = trim($entry['summary'] ?? '');
                    $entry_toc = trim($entry['table_of_contents'] ?? '');
                    
                    // Format bestimmen (Priorität hat manueller Override vor KI-Entscheidung)
                    $entry_typ = $entry['typ_override'] ?? (trim($entry['format'] ?? '') === 'physisch' ? 'physisch' : 'pdf');
                    
                    // Bestand bestimmen
                    if (isset($entry['bestand_override'])) {
                        $entry_bestand = intval($entry['bestand_override']);
                    } else {
                        if ($entry_typ === 'physisch') {
                            $entry_bestand = isset($entry['stock']) ? max(0, intval($entry['stock'])) : 1;
                        } else {
                            $entry_bestand = isset($entry['stock']) ? max(0, intval($entry['stock'])) : 0;
                        }
                    }
                    
                    // PDF-Pfad nur setzen, wenn das Buch ein E-Book ist
                    $current_pdf_pfad = ($entry_typ === 'pdf') ? $pdf_pfad : null;
                    
                    if (!empty($entry_titel) && !empty($entry_autor) && !empty($entry_isbn)) {
                        // Prüfen ob die ISBN bereits existiert (Unique Constraint manuell abfangen)
                        $stmtCheck = $db->prepare("SELECT id FROM buecher WHERE isbn = ?");
                        $stmtCheck->execute([$entry_isbn]);
                        if ($stmtCheck->fetch()) {
                            $fehler_details[] = "ISBN '$entry_isbn' existiert bereits.";
                            continue;
                        }
                        
                        // Buch in Tabelle 'buecher' eintragen
                        $stmt = $db->prepare("INSERT INTO buecher (titel, autor, isbn, bestand, typ, pdf_pfad) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$entry_titel, $entry_autor, $entry_isbn, $entry_bestand, $entry_typ, $current_pdf_pfad]);
                        
                        $buch_id = $db->lastInsertId(); // ID des gerade angelegten Buchs abfragen
                        
                        // Analyse in 1:1 Tabelle 'buch_analysen' eintragen
                        if (!empty($entry_summary)) {
                            $stmtAnalyse = $db->prepare("INSERT INTO buch_analysen (buch_id, zusammenfassung, inhaltsverzeichnis) VALUES (?, ?, ?)");
                            $stmtAnalyse->execute([$buch_id, $entry_summary, $entry_toc]);
                        }
                        $buecher_erfolgreich++;
                    } else {
                        $fehler_details[] = "Unvollständige Buchdaten.";
                    }
                } elseif (($entry['type'] ?? '') === 'user') {
                    $entry_name = trim($entry['name'] ?? '');
                    $entry_adresse = trim($entry['address'] ?? '');
                    $entry_email = trim($entry['email'] ?? '');
                    
                    if (!empty($entry_name) && !empty($entry_adresse) && !empty($entry_email)) {
                        // Prüfen ob E-Mail bereits existiert (Unique Constraint manuell abfangen)
                        $stmtCheck = $db->prepare("SELECT id FROM nutzer WHERE email = ?");
                        $stmtCheck->execute([$entry_email]);
                        if ($stmtCheck->fetch()) {
                            $fehler_details[] = "E-Mail '$entry_email' existiert bereits.";
                            continue;
                        }
                        
                        // Passwort sicher hashen (BCRYPT). Default-Passwort für importierte Nutzer: 'user123'
                        $default_pw = password_hash('user123', PASSWORD_BCRYPT);
                        
                        // Nutzer in Tabelle 'nutzer' eintragen
                        $stmt = $db->prepare("INSERT INTO nutzer (name, adresse, email, passwort, rolle, ist_aktiv) VALUES (?, ?, ?, ?, 'user', 1)");
                        $stmt->execute([$entry_name, $entry_adresse, $entry_email, $default_pw]);
                        $nutzer_erfolgreich++;
                    } else {
                        $fehler_details[] = "Unvollständige Nutzerdaten.";
                    }
                }
            }

            // Wenn bis hierhin kein SQL-Fehler aufgetreten ist, Transaktion abschließen
            $db->commit();
            
            // Zusammenfassung über den Import anzeigen
            if ($buecher_erfolgreich > 0 || $nutzer_erfolgreich > 0) {
                $msg_parts = [];
                if ($buecher_erfolgreich > 0) {
                    $msg_parts[] = "$buecher_erfolgreich Buch/Bücher";
                }
                if ($nutzer_erfolgreich > 0) {
                    $msg_parts[] = "$nutzer_erfolgreich Nutzer";
                }
                $erfolgsmeldung = "Erfolgreich erfasst: " . implode(" und ", $msg_parts) . ".";
                if (!empty($fehler_details)) {
                    $erfolgsmeldung .= " (Einige Einträge übersprungen: " . implode(", ", $fehler_details) . ")";
                }
                $titel = $autor = $isbn = ''; // Formularfelder nach erfolgreichem Speichern leeren
            } else {
                $meldung = "Es konnten keine neuen Medien oder Nutzer gespeichert werden.";
                if (!empty($fehler_details)) {
                    $meldung .= " Details: " . implode(", ", $fehler_details);
                }
            }
        } catch (PDOException $e) {
            // Bei Fehlern alle Änderungen in dieser Transaktion rückgängig machen
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $meldung = "Datenbankfehler: " . $e->getMessage();
        }
    } elseif (empty($meldung)) {
        $meldung = "Bitte füllen Sie das Formular aus oder laden Sie ein gültiges PDF hoch.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Neues Medium erfassen</title>
    <script>
        function toggleTyp() {
            var typ = document.getElementById('typ').value;
            var pdfDiv = document.getElementById('pdf_upload_section');
            var bestandDiv = document.getElementById('bestand_section');
            
            var titelInput = document.getElementById('titel');
            var autorInput = document.getElementById('autor');
            var isbnInput = document.getElementById('isbn');

            if (typ === 'pdf') {
                pdfDiv.style.display = 'block';
                bestandDiv.style.display = 'none';
                titelInput.required = false;
                autorInput.required = false;
                isbnInput.required = false;
            } else {
                pdfDiv.style.display = 'none';
                bestandDiv.style.display = 'block';
                titelInput.required = true;
                autorInput.required = true;
                isbnInput.required = true;
            }
        }
    </script>
</head>

<body>

    <?php include_once 'navbar.php'; ?>

    <h2>Neues Medium (Buch / E-Book) erfassen</h2>

    <?php if (!empty($meldung)): ?>
        <p style="color: red; font-weight: bold;"><?= htmlspecialchars($meldung) ?></p><?php endif; ?>
    <?php if (!empty($erfolgsmeldung)): ?>
        <p style="color: green; font-weight: bold;"><?= htmlspecialchars($erfolgsmeldung) ?></p><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <p>Medium-Typ:<br>
            <select name="typ" id="typ" onchange="toggleTyp()">
                <option value="physisch">Physisches Buch (Gedruckt)</option>
                <option value="pdf">Digitales Buch (PDF / E-Book)</option>
            </select>
        </p>

        <p>Titel: <br><input type="text" id="titel" name="titel" value="<?= htmlspecialchars($titel ?? '') ?>" required></p>
        <p>Autor: <br><input type="text" id="autor" name="autor" value="<?= htmlspecialchars($autor ?? '') ?>" required></p>
        <p>ISBN: <br><input type="text" id="isbn" name="isbn" value="<?= htmlspecialchars($isbn ?? '') ?>" required></p>

        <div id="bestand_section">
            <p>Physischer Bestand (Stückzahl): <br><input type="number" name="bestand" value="1" min="0"></p>
        </div>

        <div id="pdf_upload_section"
            style="display: none; background: #f9f9f9; padding: 10px; border: 1px dashed #bbb;">
            <p><strong>PDF-Datei hochladen:</strong><br>
                <input type="file" name="pdf_datei" accept=".pdf"><br>
                <small style="color: #666;">Der Text wird automatisch extrahiert und an Ollama übermittelt.</small>
            </p>
        </div>

        <br>
        <button type="submit">Medium speichern</button> or <a href="buch_uebersicht.php">Zurück zur Übersicht</a>
    </form>
</body>

</html>