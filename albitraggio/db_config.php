<?php
// db_config.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Configurazione generale
 * Aggiorna i valori con le tue credenziali e gli endpoint corretti.
 */
$config = [
    // Parametri di connessione al DB MySQL
    'database' => [
        'host'     => 'localhost',
        'user'     => 'u655576349_arbitraggio_db',  
        'password' => '=8oay:sJffsfsgw44bbd344',
        'name'     => 'u655576349_arbitraggio',
    ],
    
    // Config Binance
    'binance' => [
        'apiKey'    => 'uTdsYcKMBNDqzBVVY2uEaZ706mxl9dCt113LPtGs7UMZdUhrFF4abY6OGUlroRCF',  
        'apiSecret' => 'AhtHwyOw0b3AHC3rcCw2DyFGzhr3QdcLPNcBLdJsv6lORtBM75fwRlfKBBv9GTrF',
        'apiBase'   => 'https://api.binance.com'
    ],

    // Config Kraken
    'kraken' => [
        'apiKey'    => 'nSWf3bn3HAR3lB9kViC7MnA4I/v9dLtLhMNKTCW5iU/zureuOK+YDZq7',
        'apiSecret' => '2u5o5lD5ROJLyPixC/rPnKHZf0Yb3EA2+ij4haNtjyQKB5Oi5kfQThL3qrV+N/HXpOGbpbPgbvtttDNWrYOwMw==',
        'apiBase'   => 'https://api.kraken.com'
    ],

    // Config Coinbase
    'coinbase' => [
        'apiKey'     => 'organizations/fd955874-b68c-4db9-b5c3-ef76137b6b72/apiKeys/d41d4e8a-8ef9-43e9-9fea-f41ab7f4d6af',
        'apiSecret'  => '-----BEGIN EC PRIVATE KEY-----\nMHcCAQEEIAVSE5EZgHY1YUUKoW6WhPelke0NKZ2Clz91w7COKs0LoAoGCCqGSM49\nAwEHoUQDQgAEWbEfmTxoaM68v7TrBzwkMvbJ9pnuQer7Y9Jq9Y896lJR8Xl0ur0R\nZkdZHzeOob64v0SXukH52c22t0nWMXCbdw==\n-----END EC PRIVATE KEY-----\n',
        'apiBase'    => 'https://api.coinbase.com/api/v3/brokerage/accounts'
    ],
];

// Connessione al database MySQL
$mysqli = new mysqli(
    $config['database']['host'],
    $config['database']['user'],
    $config['database']['password'],
    $config['database']['name']
);

// Gestione errori di connessione
if ($mysqli->connect_error) {
    die("Connessione al database fallita: " . $mysqli->connect_error);
}

return $config;
?>
