<?php
// Session starten, um Benutzerdaten abzufragen
session_start();

// Sicherheits-Check: Nur eingeloggte Mitarbeiter (Rolle: mitarbeiter) dürfen Bücher bearbeiten
if (!isset($_SESSION['nutzer_id']) || $_SESSION['rolle'] !== 'mitarbeiter') {
    header("Location: index.php");
    exit;
}

// db.php einbinden für die SQLite PDO Verbindung ($db)
include_once 'db.php';

// Die Buch-ID wird per GET-Parameter aus der URL geladen. Falls nicht übergeben, bricht das Skript ab.
$id = $_GET['id'] ?? exit;

// Wenn das Bearbeitungsformular abgesendet wurde (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titel = trim($_POST['titel']);
    $autor = trim($_POST['autor']);
    $isbn = trim($_POST['isbn']);
    $bestand = intval($_POST['bestand']);
    
    try {
        // 1. Alten Titel und Autor abfragen, um Änderungen festzustellen
        $stmtOld = $db->prepare("SELECT titel, autor FROM buecher WHERE id = ?");
        $stmtOld->execute([$id]);
        $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
        
        // 2. Prüfen, ob für dieses Buch bereits eine Zusammenfassung existiert
        $stmtSum = $db->prepare("SELECT zusammenfassung FROM buch_analysen WHERE buch_id = ?");
        $stmtSum->execute([$id]);
        $has_summary = $stmtSum->fetch();
        
        // Eine neue KI-Analyse ist nötig, wenn das Buch noch keine Zusammenfassung hat
        // ODER wenn sich der Titel bzw. der Autor geändert hat (damit der Inhalt aktuell bleibt)
        $needs_analysis = !$has_summary || ($old && ($old['titel'] !== $titel || $old['autor'] !== $autor));
        
        // Transaktion starten
        $db->beginTransaction();
        
        // Buchdaten in Tabelle 'buecher' aktualisieren
        $stmt = $db->prepare("UPDATE buecher SET titel=?, autor=?, isbn=?, bestand=? WHERE id=?");
        $stmt->execute([$titel, $autor, $isbn, $bestand, $id]);
        
        // Wenn eine neue KI-Analyse benötigt wird, fragen wir Ollama an
        if ($needs_analysis) {
            $ki_zusammenfassung = '';
            $ki_inhaltsverzeichnis = '';
            
            try {
                $ollama_url = "https://ollama.com/v1/chat/completions";
                $api_key = "25341745defc47f8b6af81c2a6c6e91a.4nEigoTHxY7kUpl6pdCXUc_q";
                $model_name = "gemma3:12b";
                
                $text_auszug = "Titel: " . $titel . ", Autor: " . $autor . ", ISBN: " . $isbn;
                $system_instruction = "Du bist ein Literaturexperte. Generiere für das folgende Buch eine kurze Zusammenfassung und eine Kapitelübersicht aus deinem Wissen. Reagiere AUSSCHLIESSLICH im folgenden Format:\n"
                    . "ZUSAMMENFASSUNG: [Schreibe eine kurze, dreizeilige Zusammenfassung des Inhalts]\n"
                    . "INHALTSVERZEICHNIS: [Erstelle ein kurzes Inhaltsverzeichnis oder wichtige Kapitel]";
                    
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
                // Bei API-Fehlern ignorieren, um das Speichern des Buchs nicht zu blockieren
            }
            
            // Wenn eine Zusammenfassung generiert wurde, führen wir ein Upsert durch:
            // Entweder einfügen, oder bei Konflikt (weil buch_id Primary Key ist) updaten.
            if (!empty($ki_zusammenfassung)) {
                $stmtUpsert = $db->prepare("INSERT INTO buch_analysen (buch_id, zusammenfassung, inhaltsverzeichnis) VALUES (?, ?, ?) 
                    ON CONFLICT(buch_id) DO UPDATE SET zusammenfassung=excluded.zusammenfassung, inhaltsverzeichnis=excluded.inhaltsverzeichnis, aktualisiert_am=CURRENT_TIMESTAMP");
                $stmtUpsert->execute([$id, $ki_zusammenfassung, $ki_inhaltsverzeichnis]);
            }
        }
        
        // Transaktion abschließen
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
    
    // Nach erfolgreichem Speichern zurück zur Übersicht weiterleiten
    header("Location: buch_uebersicht.php");
    exit;
}

// Aktuelle Buchdaten laden, um das Formular vorauszufüllen
$stmt = $db->prepare("SELECT * FROM buecher WHERE id=?"); 
$stmt->execute([$id]); 
$b = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><title>Buch bearbeiten</title></head><body>
    <h2>Buch bearbeiten</h2>
    <form method="post">
        <p>Titel: <input type="text" name="titel" value="<?= htmlspecialchars($b['titel']) ?>" required></p>
        <p>Autor: <input type="text" name="autor" value="<?= htmlspecialchars($b['autor']) ?>" required></p>
        <p>ISBN: <input type="text" name="isbn" value="<?= htmlspecialchars($b['isbn']) ?>" required></p>
        <p>Bestand: <input type="number" name="bestand" value="<?= htmlspecialchars($b['bestand']) ?>"></p>
        <button type="submit">Änderungen speichern</button> <a href="buch_uebersicht.php">Abbrechen</a>
    </form>
</body></html>