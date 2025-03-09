<?php
require 'vendor/autoload.php';
use \Firebase\JWT\JWT;

echo "<h2>Test Advanced Trade API - Generazione JWT e richiesta autenticata</h2>";

// ------------------------------------------------------------------------
// 1) CONFIGURAZIONE DELLE CHIAVI API
// ------------------------------------------------------------------------
// Sostituisci i seguenti valori con le tue credenziali (ottenibili dal Coinbase Developer Platform)
$apiKey = 'organizations/fd955874-b68c-4db9-b5c3-ef76137b6b72/apiKeys/d41d4e8a-8ef9-43e9-9fea-f41ab7f4d6af';  // ad esempio: organizations/72a3d5ff-10f2-4b67-92cd-02c7e6dcb31c/apiKeys/29c15f8e-77f1-4bb3-a1a6-9e3d8e527b76
$apiSecret = "-----BEGIN EC PRIVATE KEY-----\nMHcCAQEEIAVSE5EZgHY1YUUKoW6WhPelke0NKZ2Clz91w7COKs0LoAoGCCqGSM49\nAwEHoUQDQgAEWbEfmTxoaM68v7TrBzwkMvbJ9pnuQer7Y9Jq9Y896lJR8Xl0ur0R\nZkdZHzeOob64v0SXukH52c22t0nWMXCbdw==\n-----END EC PRIVATE KEY-----\n";

// ------------------------------------------------------------------------
// 2) CONFIGURAZIONE DELLA RICHIESTA
// ------------------------------------------------------------------------
// Scegli il metodo e il percorso dell'endpoint che desideri testare.
// Ad esempio, per ottenere la lista degli account:
$requestMethod = 'GET';
$requestPath = '/api/v3/brokerage/accounts'; 
// Nota: se vuoi testare altri endpoint, sostituisci il percorso con quello desiderato.

$apiBase = 'https://api.coinbase.com';
$url = $apiBase . $requestPath;

// ------------------------------------------------------------------------
// 3) GENERAZIONE DEL JWT
// ------------------------------------------------------------------------
// Il JWT scade dopo 2 minuti; per ogni richiesta unica occorre generare un nuovo token.
$iat = time();
$exp = $iat + 120;  // scadenza: 2 minuti

$payload = [
    'iss'    => $apiKey,      // "issuer": la tua API key
    'iat'    => $iat,         // issued at
    'exp'    => $exp,         // expiration time
    'method' => $requestMethod, // metodo HTTP della richiesta
    'path'   => $requestPath    // percorso della richiesta
];

// Genera il JWT usando l'algoritmo ES256
$jwt = JWT::encode($payload, $apiSecret, 'ES256');

echo "<p><strong>JWT generato:</strong></p>";
echo "<pre>$jwt</pre>";

// ------------------------------------------------------------------------
// 4) ESECUZIONE DELLA RICHIESTA AUTENTICATA
// ------------------------------------------------------------------------
// Utilizza il JWT come Authorization Bearer header.
$headers = [
    "Authorization: Bearer $jwt",
    "User-Agent: MyCoinbaseClient/1.0"
];

echo "<p><strong>Effettuo richiesta all'endpoint:</strong> $url</p>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "<p style='color:red;'>Errore cURL: " . curl_error($ch) . "</p>";
    exit;
}
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";

$responseData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<p style='color:red;'>Errore nel decodificare la risposta JSON: " . json_last_error_msg() . "</p>";
    echo "<p><strong>Risposta Grezza:</strong></p><pre>" . htmlspecialchars($response) . "</pre>";
    exit;
}

echo "<p><strong>Risposta:</strong></p>";
echo "<pre>" . print_r($responseData, true) . "</pre>";
?>
