<?php
$ollama_url = "https://ollama.com/v1/chat/completions";
$api_key = "25341745defc47f8b6af81c2a6c6e91a.4nEigoTHxY7kUpl6pdCXUc_q";

$data = [
    "model" => "gemma3:12b",
    "messages" => [
        [
            "role" => "user",
            "content" => "Hi"
        ]
    ],
    "stream" => false
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n" .
                    "Authorization: Bearer " . $api_key . "\r\n",
        'content' => json_encode($data),
        'timeout' => 10,
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);

echo "<h2>Test der Ollama Cloud-API Verbindung via stream_context / file_get_contents</h2>";

$response = @file_get_contents($ollama_url, false, $context);

if ($response === false) {
    $error = error_get_last();
    echo "<p style='color:red; font-weight:bold;'>Verbindung fehlgeschlagen!</p>";
    if ($error) {
        echo "<p>Fehlermeldung: " . htmlspecialchars($error['message']) . "</p>";
    }
    echo "<p>Mögliche Ursache: Der Hoster (bplaced) blockiert ausgehende Verbindungen oder es liegt ein Timeout vor.</p>";
} else {
    $http_code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
        $http_code = intval($match[1] ?? 0);
    }
    
    echo "<p>HTTP-Statuscode: <strong>" . $http_code . "</strong></p>";
    
    if ($http_code === 200) {
        echo "<p style='color:green; font-weight:bold;'>Verbindung zur Ollama Cloud erfolgreich!</p>";
        $json = json_decode($response, true);
        echo "<pre>" . htmlspecialchars(print_r($json, true)) . "</pre>";
    } else {
        echo "<p style='color:orange; font-weight:bold;'>Verbindung aufgebaut, aber API meldet Fehler (Status $http_code).</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}
?>
