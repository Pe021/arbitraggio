<?php
// broker_lib.php

require_once 'vendor/autoload.php'; // ad es. se usi composer

function getBalanceBinance($config) {
    // $config è l'array con apiKey e apiSecret
    $client = new \Binance\Spot([
        'key'    => $config['binance']['apiKey'],
        'secret' => $config['binance']['apiSecret']
    ]);
    // Chiamiamo l'endpoint per ottenere i saldi
    // https://binance-docs.github.io/apidocs/spot/en/#account-information-user_data
    $res = $client->account();
    // $res conterrà i balances
    return $res['balances'] ?? [];
}

function buyBinance($config, $coin, $amountUSD) {
    // Esempio: per comprare BTC su Binance con $100
    // Devi convertire $100 in quanti BTC vuoi comprare, in base al lastPrice di BTCUSDT
    // Poi chiamare l'endpoint /api/v3/order
    // Qui esempio semplificato “MARKET BUY”
    // Attenzione: i parametri reali richiedono "symbol", "side", "type", "quoteOrderQty", etc.

    // 1) Otteniamo prezzo
    $client = new \Binance\Spot([
        'key'    => $config['binance']['apiKey'],
        'secret' => $config['binance']['apiSecret']
    ]);

    // Esempio: “quoteOrderQty” = $amountUSD
    $symbol = strtoupper($coin).'USDT'; // Esempio: BTCUSDT
    try {
        $order = $client->newOrder($symbol, 'BUY', 'MARKET', [
            'quoteOrderQty' => $amountUSD
        ]);
        return $order;
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function sellBinance($config, $coin, $amountCoin) {
    // MARKET SELL di $amountCoin
    $client = new \Binance\Spot([
        'key'    => $config['binance']['apiKey'],
        'secret' => $config['binance']['apiSecret']
    ]);
    $symbol = strtoupper($coin).'USDT';
    try {
        $order = $client->newOrder($symbol, 'SELL', 'MARKET', [
            'quantity' => $amountCoin
        ]);
        return $order;
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function getBalanceCoinbase($config) {
    // Usa la libreria adatta per Coinbase (Advanced Trade).
    // Esempio generico, pseudocodice
    // ...
    return [];
}

function buyCoinbase($config, $coin, $amountUSD) {
    // Esempio di acquisto su Coinbase
    // ...
    return [];
}

function sellCoinbase($config, $coin, $amountCoin) {
    // ...
    return [];
}

function getBalanceKraken($config) {
    // Usa libreria kraken
    // ...
    return [];
}

function buyKraken($config, $coin, $amountUSD) {
    // ...
    return [];
}

function sellKraken($config, $coin, $amountCoin) {
    // ...
    return [];
}

/**
 * Per “trasferire” da un broker all’altro, in pratica devi:
 * - Eseguire un withdrawal (prelievo) su broker A (ad es. Binance) 
 * - Fornendo l’indirizzo deposit di broker B (Kraken).
 * - Attendere la conferma on-chain
 * - Non esiste un semplice “transfer” interno multi-broker
 * 
 * Semplifichiamo come "withdrawBrokerA + depositBrokerB"
 */
function transferBetweenBrokers($config, $fromBroker, $toBroker, $coin, $amount) {
    // ESEMPIO PSEUDOCODICE
    // 1) get deposit address from $toBroker
    // 2) call withdraw on $fromBroker
    // 3) attendi conferma
    // 4) se vuoi, verifichi arrivo su $toBroker
    return ['status' => 'Transfer initiated', 'amount' => $amount, 'coin' => $coin];
}
