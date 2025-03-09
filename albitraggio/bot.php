<?php
/**
 * bot.php
 *
 * Esempio completo con:
 * - Saldi reali (8 decimali)
 * - Buy/Sell su Binance, Coinbase (Exchange), Kraken
 * - Spread & Dashboard
 * - Log delle risposte Binance e Coinbase in /logs/...
 * - Auto-refresh 180s
 */

// ======================
// 0) GESTIONE SESSIONE
// ======================
ini_set('session.cookie_lifetime', 2147483647);
session_set_cookie_params(2147483647);
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Carichiamo configurazione
$config = require 'db_config.php';

// ======================
// 1) FLAG BOT E AJAX
// ======================
if (!isset($_SESSION['botActive'])) {
    $_SESSION['botActive'] = false;
}
$botActive = $_SESSION['botActive'];

// Gestione salvataggio impostazioni via AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if (isset($_POST['selected_coins']) && is_array($_POST['selected_coins'])) {
        $_SESSION['selected_coins'] = $_POST['selected_coins'];
    }
    if (isset($_POST['threshold'])) {
        $_SESSION['threshold_percent'] = floatval($_POST['threshold']);
    }
    if (isset($_POST['custom_investment'])) {
        $_SESSION['custom_investment'] = floatval($_POST['custom_investment']);
    }
    echo json_encode(['status' => 'OK']);
    exit;
}

// Bot ON/OFF
if (isset($_GET['action']) && $_GET['action'] === 'toggleBot') {
    $state = $_POST['state'] ?? 'off';
    $_SESSION['botActive'] = ($state === 'on');
    echo json_encode(['status'=>'OK','botActive'=>$_SESSION['botActive']]);
    exit;
}

// Test API Keys
if (isset($_GET['action']) && $_GET['action'] === 'testApiKeys') {
    $testResults = testBrokerApiKeys($config);
    echo json_encode(['status'=>'OK','results'=>$testResults]);
    exit;
}

// Azioni Trading
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'sell':
            $broker = $_POST['broker'] ?? '';
            $coin   = $_POST['coin'] ?? '';
            $amt    = floatval($_POST['amount'] ?? 0);
            $res    = sellCrypto($config, $broker, $coin, $amt);
            echo json_encode($res);
            exit;
        case 'buy':
            $broker = $_POST['broker'] ?? '';
            $coin   = $_POST['coin'] ?? '';
            $amt    = floatval($_POST['amount'] ?? 0);
            $res    = buyCrypto($config, $broker, $coin, $amt);
            echo json_encode($res);
            exit;
        case 'transfer':
            $from   = $_POST['from'] ?? '';
            $to     = $_POST['to'] ?? '';
            $coin   = $_POST['coin'] ?? '';
            $amt    = floatval($_POST['amount'] ?? 0);
            $res    = transferCrypto($config, $from, $to, $coin, $amt);
            echo json_encode($res);
            exit;
    }
}

// ======================
// 2) PARAMETRI & DEFAULT
// ======================
$allCoins = [
  "BTC","ETH","BNB","SOL","XRP","DOGE","AVAX","MATIC","DOT","LTC",
  "UNI","SHIB","BCH","ADA","LINK","XLM","TRX","EOS","XMR","NEO",
  "ETC","DASH","ZEC","VET","ICP","FIL","THETA","HBAR","MKR","KSM",
  "CHZ","FTM","OP","CRV","SNX","GLMR","APE2","RUNE","IMX","1INCH",
  "GALA","ENS","KAVA","ROSE","QTUM","CSPR","WAVES","BAL","JASMY","DASH2",
  "XTZ","ZIL","MINA","RAY","SRM","CELO","EGLD","TUSD","HOT","RSR","FLR"
];
$defaultSelected = array_slice($allCoins, 0, 15);

if (!isset($_SESSION['selected_coins'])) {
    $_SESSION['selected_coins'] = $defaultSelected;
}
if (!isset($_SESSION['threshold_percent'])) {
    $_SESSION['threshold_percent'] = 0.10;
}
if (!isset($_SESSION['custom_investment'])) {
    $_SESSION['custom_investment'] = 1000;
}

$selectedCoins    = $_SESSION['selected_coins'];
$thresholdPercent = $_SESSION['threshold_percent'];
$customInvestment = $_SESSION['custom_investment'];

// ======================
// 3) FUNZIONI UTILI
// ======================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function logToFile($msg) {
    $file = __DIR__.'/bot_log.txt';
    $date = date('Y-m-d H:i:s');
    file_put_contents($file, "[$date] $msg\n", FILE_APPEND);
}
function httpGet($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_TIMEOUT,10);
    $resp = curl_exec($ch);
    if(curl_errno($ch)){
        error_log("cURL error: ".curl_error($ch));
    }
    curl_close($ch);
    return $resp;
}
function autoRefreshPage($seconds=180){
    echo '<meta http-equiv="refresh" content="'.$seconds.'">';
}
function detectTrend($pc){
    if(!is_numeric($pc)) return "N/A";
    if($pc>3) return "Bullish";
    if($pc<-3) return "Bearish";
    return "Stable";
}

// ======================
// 4) MAPPATURE E FIRMA
// ======================
$krakenPairs = [
    "BTC"=>"XBTUSD","ETH"=>"ETHUSD","LTC"=>"LTCUSD","XRP"=>"XXRPZUSD",
    "BCH"=>"BCHUSD","ADA"=>"ADAUSD","LINK"=>"LINKUSD","XLM"=>"XXLMZUSD",
    "ETC"=>"XETCZUSD"
];

/**
 * Logga la risposta integrale in /logs/ per Binance o Coinbase
 */
function logResponse($broker, $resp, $coinSymbol=''){
    $dir = __DIR__.'/logs';
    if(!is_dir($dir)){
        mkdir($dir, 0777, true);
    }
    $filename = $dir.'/'.$broker.'_balance.log';
    $ts = date('Y-m-d H:i:s');
    file_put_contents($filename, "[$ts] Coin: $coinSymbol\n$resp\n\n", FILE_APPEND);
}

// ======================
// 5) GET BALANCE BINANCE, COINBASE, KRAKEN
// ======================
function getBalanceBinance($apiKey, $apiSecret, $apiBase, $coinSymbol){
    if(empty($apiKey) || empty($apiSecret)) {
        return 0;
    }
    $timestamp = round(microtime(true)*1000);
    $recvWindow= 5000;
    $query     = "timestamp=$timestamp&recvWindow=$recvWindow";
    $signature = hash_hmac('sha256',$query,$apiSecret);
    $url       = $apiBase."/api/v3/account?".$query."&signature=".$signature;

    $headers   = ["X-MBX-APIKEY: $apiKey"];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if(curl_errno($ch)){
        curl_close($ch);
        return 0;
    }
    curl_close($ch);

    // Log la risposta integrale
    logResponse('binance',$resp,$coinSymbol);

    if($code>=200 && $code<300){
        $data = json_decode($resp,true);
        if(isset($data['balances']) && is_array($data['balances'])){
            foreach($data['balances'] as $b){
                if(strtoupper($b['asset'])==strtoupper($coinSymbol)){
                    return (float)$b['free'];
                }
            }
        }
    }
    return 0;
}

// Coinbase Exchange
function getBalanceCoinbase($apiKey, $apiSecret, $passphrase, $apiBase, $coinSymbol){
    if(empty($apiKey)||empty($apiSecret)||empty($passphrase)){
        return 0;
    }
    $timestamp = time();
    $method    = 'GET';
    $requestPath = '/accounts';
    $body      = '';
    $toSign    = $timestamp.$method.$requestPath.$body;

    $decodedSecret = base64_decode($apiSecret);
    if(!$decodedSecret) return 0;

    $signature = hash_hmac('sha256',$toSign,$decodedSecret,true);
    $signature = base64_encode($signature);

    $url = $apiBase.$requestPath;
    $headers = [
        "CB-ACCESS-KEY: $apiKey",
        "CB-ACCESS-SIGN: $signature",
        "CB-ACCESS-TIMESTAMP: $timestamp",
        "CB-ACCESS-PASSPHRASE: $passphrase"
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if(curl_errno($ch)){
        curl_close($ch);
        return 0;
    }
    curl_close($ch);

    // Log la risposta integrale
    logResponse('coinbase',$resp,$coinSymbol);

    if($code>=200 && $code<300){
        $data = json_decode($resp,true);
        if(is_array($data)){
            if(isset($data[0]) && is_array($data[0])){
                // Caso array
                foreach($data as $acc){
                    if(isset($acc['currency']) && strtoupper($acc['currency'])==strtoupper($coinSymbol)){
                        return (float)$acc['balance'];
                    }
                }
            } elseif(isset($data['data']) && is_array($data['data'])){
                // Caso "data" => array
                foreach($data['data'] as $acc){
                    if(isset($acc['currency']) && strtoupper($acc['currency'])==strtoupper($coinSymbol)){
                        return (float)$acc['balance'];
                    }
                }
            }
        }
    }
    return 0;
}

// Kraken
function krakenSign($path,$data,$secret){
    if(empty($secret)) return '';
    $nonce = $data['nonce']??'';
    $postData = http_build_query($data,'','&');
    $sha256=hash('sha256',$nonce.$postData,true);
    $message=$path.$sha256;
    $secretDecoded=base64_decode($secret);
    if(!$secretDecoded) return '';
    $signature=hash_hmac('sha512',$message,$secretDecoded,true);
    return base64_encode($signature);
}
function getBalanceKraken($apiKey,$apiSecret,$apiBase,$coinSymbol){
    if(empty($apiKey) || empty($apiSecret)){
        return 0;
    }
    $path='/0/private/Balance';
    $url=$apiBase.$path;
    $nonce=microtime(true)*1000000;
    $pData=['nonce'=>$nonce];
    $sign=krakenSign($path,$pData,$apiSecret);

    $headers=[
        "API-Key: $apiKey",
        "API-Sign: $sign"
    ];
    $pf=http_build_query($pData);
    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$pf);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
    $resp=curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if(curl_errno($ch)){
        curl_close($ch);
        return 0;
    }
    curl_close($ch);

    // Se vuoi, potresti loggare anche la risposta kraken con logResponse('kraken',$resp,$coinSymbol);

    if($code>=200 && $code<300){
        $data=json_decode($resp,true);
        if(!empty($data['error'])) return 0;
        $map=[
            'BTC'=>'XXBT','ETH'=>'XETH','USDT'=>'USDT','XRP'=>'XXRP',
            'LTC'=>'XLTC','BCH'=>'BCHE','ADA'=>'ADA'
        ];
        $kCoin = $map[strtoupper($coinSymbol)] ?? strtoupper($coinSymbol);
        if(isset($data['result'][$kCoin])){
            return (float)$data['result'][$kCoin];
        }
    }
    return 0;
}

// Funzione generica per saldo
function getWalletBalance($config,$broker,$coin){
    switch(strtolower($broker)){
        case 'binance':
            return getBalanceBinance(
                $config['binance']['apiKey']    ??'',
                $config['binance']['apiSecret'] ??'',
                $config['binance']['apiBase']   ??'https://api.binance.com',
                $coin
            );
        case 'coinbase':
            return getBalanceCoinbase(
                $config['coinbase']['apiKey']     ??'',
                $config['coinbase']['apiSecret']  ??'',
                $config['coinbase']['passphrase'] ??'',
                $config['coinbase']['apiBase']    ??'https://api.exchange.coinbase.com',
                $coin
            );
        case 'kraken':
            return getBalanceKraken(
                $config['kraken']['apiKey']    ??'',
                $config['kraken']['apiSecret'] ??'',
                $config['kraken']['apiBase']   ??'https://api.kraken.com',
                $coin
            );
        default:
            return 0;
    }
}

// ======================
// 6) BUY / SELL / TRANSFER / GETLASTPRICE
// ======================
function buyCrypto($config,$broker,$coin,$usd){
    logToFile("BUY request: $broker - $coin - $usd USD");
    $p = getLastPrice($broker,$coin);
    if($p<=0){
        return ['status'=>'error','message'=>"Prezzo $coin su $broker non trovato"];
    }
    $qty = round($usd/$p,6);
    switch(strtolower($broker)){
        case 'binance':
            $symbol = strtoupper($coin).'USDT';
            return buyCryptoBinance(
                $config['binance']['apiKey']    ??'',
                $config['binance']['apiSecret'] ??'',
                $config['binance']['apiBase']   ??'https://api.binance.com',
                $symbol,
                $qty
            );
        case 'coinbase':
            $productId = strtoupper($coin).'-USD';
            return buyCryptoCoinbase(
                $config['coinbase']['apiKey']    ??'',
                $config['coinbase']['apiSecret'] ??'',
                $config['coinbase']['passphrase']??'',
                $config['coinbase']['apiBase']   ??'https://api.exchange.coinbase.com',
                $productId,
                $qty
            );
        case 'kraken':
            // ...
            // Esempio
            $c=strtoupper($coin);
            if($c=='BTC') $c='XBT';
            $pair=$c.'USD';
            return buyCryptoKraken(
                $config['kraken']['apiKey']    ??'',
                $config['kraken']['apiSecret'] ??'',
                $config['kraken']['apiBase']   ??'https://api.kraken.com',
                $pair,
                $qty
            );
        default:
            return ['status'=>'error','message'=>"Broker $broker non riconosciuto"];
    }
}
function sellCrypto($config,$broker,$coin,$usd){
    logToFile("SELL request: $broker - $coin - $usd USD");
    $p = getLastPrice($broker,$coin);
    if($p<=0){
        return ['status'=>'error','message'=>"Prezzo $coin su $broker non trovato (SELL)"];
    }
    $qty = round($usd/$p,6);
    switch(strtolower($broker)){
        case 'binance':
            $symbol=strtoupper($coin).'USDT';
            return sellCryptoBinance(
                $config['binance']['apiKey']    ??'',
                $config['binance']['apiSecret'] ??'',
                $config['binance']['apiBase']   ??'https://api.binance.com',
                $symbol,
                $qty
            );
        case 'coinbase':
            $productId=strtoupper($coin).'-USD';
            return sellCryptoCoinbase(
                $config['coinbase']['apiKey']    ??'',
                $config['coinbase']['apiSecret'] ??'',
                $config['coinbase']['passphrase']??'',
                $config['coinbase']['apiBase']   ??'https://api.exchange.coinbase.com',
                $productId,
                $qty
            );
        case 'kraken':
            // ...
            $c=strtoupper($coin);
            if($c=='BTC') $c='XBT';
            $pair=$c.'USD';
            return sellCryptoKraken(
                $config['kraken']['apiKey']    ??'',
                $config['kraken']['apiSecret'] ??'',
                $config['kraken']['apiBase']   ??'https://api.kraken.com',
                $pair,
                $qty
            );
        default:
            return ['status'=>'error','message'=>"Broker $broker non riconosciuto (SELL)"];
    }
}
function transferCrypto($config,$from,$to,$coin,$amount){
    logToFile("TRANSFER request: from $from to $to - $coin - $amount USD");
    // Non implementato
    return ['status'=>'error','message'=>'Transfer non implementato'];
}
function getLastPrice($broker,$coin){
    if(strtolower($broker)=='binance'){
        $sym=strtoupper($coin).'USDT';
        $url="https://api.binance.com/api/v3/ticker/price?symbol=".$sym;
        $r=httpGet($url);
        $d=json_decode($r,true);
        return isset($d['price'])?(float)$d['price']:0;
    }
    if(strtolower($broker)=='coinbase'){
        $url="https://api.coinbase.com/v2/prices/".strtoupper($coin)."-USD/spot";
        $r=httpGet($url);
        $d=json_decode($r,true);
        return isset($d['data']['amount'])?(float)$d['data']['amount']:0;
    }
    if(strtolower($broker)=='kraken'){
        $c=strtoupper($coin);
        if($c=='BTC') $c='XBT';
        $pair=$c.'USD';
        $url="https://api.kraken.com/0/public/Ticker?pair=".$pair;
        $r=httpGet($url);
        $jd=json_decode($r,true);
        if(isset($jd['result']) && is_array($jd['result'])){
            $val=reset($jd['result']);
            if(isset($val['c'][0])) return (float)$val['c'][0];
        }
        return 0;
    }
    return 0;
}

// ======================
// 7) BUY/SELL BINANCE, COINBASE, KRAKEN
// ======================
/** Esempio acquisto su Binance */
function buyCryptoBinance($apiKey,$apiSecret,$apiBase,$symbol,$quantity){
    // ...
    // QUI la tua logica di firma POST /api/v3/order
    // Ometto i dettagli se li hai già
    return ['status'=>'error','message'=>'buyCryptoBinance non implementato'];
}
function sellCryptoBinance($apiKey,$apiSecret,$apiBase,$symbol,$quantity){
    // ...
    return ['status'=>'error','message'=>'sellCryptoBinance non implementato'];
}

/** Esempio acquisto su Coinbase Exchange (Advanced) */
function buyCryptoCoinbase($apiKey,$apiSecret,$passphrase,$apiBase,$productId,$size){
    // POST /orders
    $timestamp=time();
    $method='POST';
    $requestPath='/orders';
    $bodyArr=[
        'type'=>'market',
        'side'=>'buy',
        'product_id'=>$productId,
        'size'=>$size
    ];
    $body=json_encode($bodyArr);

    $decodedSecret=base64_decode($apiSecret);
    if(!$decodedSecret){
        return ['status'=>'error','message'=>'Coinbase Secret Key invalid'];
    }
    $what=$timestamp.$method.$requestPath.$body;
    $signature=hash_hmac('sha256',$what,$decodedSecret,true);
    $signature=base64_encode($signature);

    $url=$apiBase.$requestPath;
    $headers=[
        "CB-ACCESS-KEY: $apiKey",
        "CB-ACCESS-SIGN: $signature",
        "CB-ACCESS-TIMESTAMP: $timestamp",
        "CB-ACCESS-PASSPHRASE: $passphrase",
        "Content-Type: application/json"
    ];
    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
    $resp=curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if(curl_errno($ch)){
        $err=curl_error($ch);
        curl_close($ch);
        return ['status'=>'error','message'=>$err];
    }
    curl_close($ch);
    $data=json_decode($resp,true);
    if($code>=200 && $code<300){
        return ['status'=>'success','data'=>$data];
    }
    return ['status'=>'error','code'=>$code,'data'=>$data];
}
function sellCryptoCoinbase($apiKey,$apiSecret,$passphrase,$apiBase,$productId,$size){
    // POST /orders, side= 'sell'
    $timestamp=time();
    $method='POST';
    $requestPath='/orders';
    $bodyArr=[
        'type'=>'market',
        'side'=>'sell',
        'product_id'=>$productId,
        'size'=>$size
    ];
    $body=json_encode($bodyArr);

    $decodedSecret=base64_decode($apiSecret);
    if(!$decodedSecret){
        return ['status'=>'error','message'=>'Coinbase Secret Key invalid'];
    }
    $what=$timestamp.$method.$requestPath.$body;
    $signature=hash_hmac('sha256',$what,$decodedSecret,true);
    $signature=base64_encode($signature);

    $url=$apiBase.$requestPath;
    $headers=[
        "CB-ACCESS-KEY: $apiKey",
        "CB-ACCESS-SIGN: $signature",
        "CB-ACCESS-TIMESTAMP: $timestamp",
        "CB-ACCESS-PASSPHRASE: $passphrase",
        "Content-Type: application/json"
    ];
    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
    $resp=curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if(curl_errno($ch)){
        $err=curl_error($ch);
        curl_close($ch);
        return ['status'=>'error','message'=>$err];
    }
    curl_close($ch);
    $data=json_decode($resp,true);
    if($code>=200 && $code<300){
        return ['status'=>'success','data'=>$data];
    }
    return ['status'=>'error','code'=>$code,'data'=>$data];
}

/** Esempio acquisto su Kraken */
function buyCryptoKraken($apiKey,$apiSecret,$apiBase,$pair,$volume){
    // ...
    return ['status'=>'error','message'=>'buyCryptoKraken non implementato'];
}
function sellCryptoKraken($apiKey,$apiSecret,$apiBase,$pair,$volume){
    // ...
    return ['status'=>'error','message'=>'sellCryptoKraken non implementato'];
}

// ======================
// 8) TEST BROKER API KEYS
// ======================
function testBrokerApiKeys($config){
    $res=[];
    // Binance ping
    $bApi=$config['binance']['apiBase']??'https://api.binance.com';
    $respB=httpGet($bApi."/api/v3/ping");
    $res['binance'] = (!empty($respB))? "OK - Binance ping" : "Errore/ping Binance";

    // Coinbase
    $cApi=$config['coinbase']['apiBase']??'https://api.exchange.coinbase.com';
    $respC=httpGet($cApi."/time");
    $res['coinbase'] = (!empty($respC))? "OK - Coinbase endpoint" : "Errore/ping Coinbase";

    // Kraken
    $kApi=$config['kraken']['apiBase']??'https://api.kraken.com';
    $respK=httpGet($kApi."/0/public/SystemStatus");
    $res['kraken'] = (!empty($respK))? "OK - Kraken system status" : "Errore/ping Kraken";

    return $res;
}

// ======================
// 9) PREZZI 24H BINANCE, COINBASE, KRAKEN
// ======================
function getBinance24hr($coin){
    $symbol=strtoupper($coin).'USDT';
    $url="https://api.binance.com/api/v3/ticker/24hr?symbol=".$symbol;
    $r=httpGet($url);
    $d=json_decode($r,true);
    if(isset($d['lastPrice'])){
        return [
            "lastPrice"          => (float)$d['lastPrice'],
            "priceChangePercent" => (float)$d['priceChangePercent'],
            "highPrice"          => (float)$d['highPrice'],
            "lowPrice"           => (float)$d['lowPrice'],
            "volume"             => (float)$d['volume']
        ];
    }
    return false;
}
function getCoinbase24hr($coin){
    $url="https://api.coinbase.com/v2/prices/".strtoupper($coin)."-USD/spot";
    $r=httpGet($url);
    $d=json_decode($r,true);
    if(isset($d['data']['amount'])){
        return [
            "lastPrice" => (float)$d['data']['amount'],
            "priceChangePercent"=>"N/D",
            "highPrice"=>"N/D",
            "lowPrice"=>"N/D",
            "volume"=>"N/D"
        ];
    }
    return false;
}
function getKraken24hr($coin){
    global $krakenPairs;
    $c=strtoupper($coin);
    if(!isset($krakenPairs[$c])) {
        return false;
    }
    $pair=$krakenPairs[$c];
    $url="https://api.kraken.com/0/public/Ticker?pair=".$pair;
    $r=httpGet($url);
    $j=json_decode($r,true);
    if(isset($j['result']) && is_array($j['result'])){
        $val=reset($j['result']);
        if(is_array($val) && isset($val['c']) && is_array($val['c'])){
            $lp=(float)$val['c'][0];
            $hi=(isset($val['h'][1]))?(float)$val['h'][1]:'N/D';
            $lo=(isset($val['l'][1]))?(float)$val['l'][1]:'N/D';
            $vol=(isset($val['v'][1]))?(float)$val['v'][1]:'N/D';
            return [
                "lastPrice"=>$lp,
                "priceChangePercent"=>"N/D",
                "highPrice"=>$hi,
                "lowPrice"=>$lo,
                "volume"=>$vol
            ];
        }
    }
    return false;
}

// ======================
// 10) LOG AVVIO
// ======================
logToFile("Access Bot: threshold=$thresholdPercent, invest=$customInvestment, coins=".implode(',',$selectedCoins).", botActive=".($botActive?'ON':'OFF'));

// ======================
// 11) DASHBOARD & CALCOLI
// ======================
$dashboardData=[];
$automaticOpsLog=[];
foreach($selectedCoins as $coin){
    $bData=getBinance24hr($coin);
    $cData=getCoinbase24hr($coin);
    $kData=getKraken24hr($coin);

    $dashboardData[$coin]=[
        "Binance"  => $bData ?: ["lastPrice"=>"N/D","priceChangePercent"=>"N/D","highPrice"=>"N/D","lowPrice"=>"N/D","volume"=>"N/D"],
        "Coinbase" => $cData ?: ["lastPrice"=>"N/D","priceChangePercent"=>"N/D","highPrice"=>"N/D","lowPrice"=>"N/D","volume"=>"N/D"],
        "Kraken"   => $kData ?: ["lastPrice"=>"N/D","priceChangePercent"=>"N/D","highPrice"=>"N/D","lowPrice"=>"N/D","volume"=>"N/D"]
    ];

    $pricesNumeric = [];
    foreach(["Binance","Coinbase","Kraken"] as $ex){
        $lp=$dashboardData[$coin][$ex]["lastPrice"];
        if(is_numeric($lp)){
            $pricesNumeric[$ex]=(float)$lp;
        }
    }
    if(!empty($pricesNumeric)){
        $minPrice=min($pricesNumeric);
        $maxPrice=max($pricesNumeric);
        $minExch=array_search($minPrice,$pricesNumeric);
        $maxExch=array_search($maxPrice,$pricesNumeric);
        $spreadPercent=0;
        if($minPrice>0){
            $spreadPercent=(($maxPrice-$minPrice)/$minPrice)*100;
        }
        if($botActive && $spreadPercent>=$thresholdPercent){
            $b=buyCrypto($config,$minExch,$coin,$customInvestment);
            $s=sellCrypto($config,$maxExch,$coin,$customInvestment);
            $t=transferCrypto($config,$minExch,'Kraken',$coin,$customInvestment);

            $automaticOpsLog[$coin]=[
                'buy'=>$b,'sell'=>$s,'transfer'=>$t,'spread'=>$spreadPercent
            ];
        }
    }
}

// ======================
// 12) AUTO REFRESH
// ======================
autoRefreshPage(180);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Bot Crypto Volatili - Trading Automatico</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f7f7f7;
    margin: 0;
    padding: 20px;
}
.container {
    max-width:1200px;
    margin:auto;
    background:#fff;
    padding:20px;
    border-radius:6px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}
h1,h2 { text-align:center; color:#333; }
.user-info { text-align:center; margin-bottom:10px; }
.user-info span { margin:0 10px; font-weight:bold; }
.controls, .controls-2 { text-align:center; margin-bottom:20px; }
.balance-table {
    margin:0 auto 25px auto;
    border:1px solid #aaa; border-collapse:collapse;
    max-width:700px;
}
.balance-table th, .balance-table td {
    border:1px solid #aaa;
    padding:6px;
    text-align:center;
}
.coin-section {
    margin-bottom:35px;
    border-bottom:1px solid #ccc;
    padding-bottom:25px;
}
table { width:100%; border-collapse:collapse; margin-top:10px; }
table, th, td { border:1px solid #ccc; }
th, td { padding:8px; text-align:center; }
th { background:#eee; }
.highlight-min { background:#cdeecd; }
.highlight-max { background:#ffe8a5; }
.spread-info {
    margin-top:10px; padding:10px; text-align:center; border-radius:4px;
}
.opportunity {
    background:#dff0d8!important; color:#3c763d; font-weight:bold;
}
.no-opportunity {
    background:#fcf8e3!important; color:#8a6d3b;
}
.opportunity-text {
    animation:pulsate 1s infinite; font-weight:bold;
}
@keyframes pulsate{
    0%{opacity:1;} 50%{opacity:0.4;} 100%{opacity:1;}
}
#countdown {
    font-weight:bold; color:#555; margin-left:20px;
}
.auto-ops {
    background:#eef; padding:10px; margin:20px auto;
    border:1px solid #99c; border-radius:4px; max-width:800px;
}
.toggle-btn {
    margin:10px; padding:10px 20px; font-size:16px;
}
.profit-simulation { margin-top:10px; }
.profit-simulation table {
    margin:auto; border-collapse:collapse;
}
.profit-simulation th, .profit-simulation td {
    border:1px solid #aaa; padding:5px;
}
</style>
<script>
function updateClock(){
    const now=new Date();
    const d=String(now.getDate()).padStart(2,'0');
    const m=String(now.getMonth()+1).padStart(2,'0');
    const y=now.getFullYear();
    const hh=String(now.getHours()).padStart(2,'0');
    const mm=String(now.getMinutes()).padStart(2,'0');
    const ss=String(now.getSeconds()).padStart(2,'0');
    document.getElementById('clock').innerHTML=`${d}/${m}/${y} ${hh}:${mm}:${ss}`;
}
setInterval(updateClock,1000);

let remainingSec=180;
function updateCountdown(){
    if(remainingSec>=0){
        let min=Math.floor(remainingSec/60);
        let sec=remainingSec%60;
        if(sec<10) sec='0'+sec;
        document.getElementById('countdown').innerHTML=`Prossimo aggiornamento: ${min}:${sec}`;
        remainingSec--;
    }
}
setInterval(updateCountdown,1000);

function saveSettings(){
    let cbs=document.querySelectorAll('.chk-coin');
    let selected=[];
    cbs.forEach(ch=>{
        if(ch.checked) selected.push(ch.value);
    });
    let thr=document.getElementById('thresholdField').value;
    let cap=document.getElementById('capitalField').value;
    const fd=new FormData();
    selected.forEach(v=>fd.append('selected_coins[]',v));
    fd.append('threshold',thr);
    fd.append('custom_investment',cap);
    fetch('bot.php?ajax=1',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(resp=>{alert('Impostazioni salvate. Verranno applicate al prossimo refresh.');})
      .catch(err=>alert('Errore salvataggio.'));
}
function selectAllCoins(){ document.querySelectorAll('.chk-coin').forEach(ch=>ch.checked=true); }
function unselectAllCoins(){ document.querySelectorAll('.chk-coin').forEach(ch=>ch.checked=false); }
function selectDefault15(){
    unselectAllCoins();
    let cbs=document.querySelectorAll('.chk-coin');
    for(let i=0;i<15 && i<cbs.length;i++){
        cbs[i].checked=true;
    }
}
function resetThreshold010(){
    document.getElementById('thresholdField').value=0.10;
}
function filterCoins(){
    const s=document.getElementById('searchCoin').value.toLowerCase();
    const secs=document.getElementsByClassName('coin-section');
    Array.from(secs).forEach(sec=>{
        let coinName=sec.getAttribute('data-coin').toLowerCase();
        sec.style.display=(coinName.indexOf(s)!==-1)?'':'none';
    });
}
function toggleBot(curState){
    let newState=(curState==='on')?'off':'on';
    const fd=new FormData();
    fd.append('state',newState);
    fetch('bot.php?action=toggleBot',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(resp=>{
        let btn=document.getElementById('toggleBotBtn');
        if(resp.botActive){
            btn.innerText='Ferma Bot';
            btn.setAttribute('onclick',"toggleBot('on')");
            alert('Bot avviato!');
        } else {
            btn.innerText='Avvia Bot';
            btn.setAttribute('onclick',"toggleBot('off')");
            alert('Bot fermato.');
        }
      })
      .catch(err=>alert('Errore cambio stato bot.'));
}
function testApiKeys(){
    fetch('bot.php?action=testApiKeys')
      .then(r=>r.json())
      .then(resp=>{
        if(resp.results){
            let msg='Test API Keys:\n\n';
            for(let b in resp.results){
                msg += b +': '+ resp.results[b] + '\n';
            }
            alert(msg);
        } else {
            alert('Nessun risultato test API keys');
        }
      })
      .catch(err=>alert('Errore test API keys.'));
}

window.addEventListener('DOMContentLoaded',()=>{
    updateClock();
    updateCountdown();
});
</script>
</head>
<body>
<div class="container">
    <h1>Bot Crypto Volatili - Trading Automatico</h1>
    <div class="user-info">
        <?php
            $nickname=$_SESSION['nickname']??'Ospite';
            echo "<span>Ciao ".htmlspecialchars($nickname)."!</span>";
        ?>
        <span id="clock"></span>
        <a href="logout.php" style="margin-left:20px;color:red;">Logout</a>
        <span id="countdown"></span>
    </div>
    <div style="text-align:center; margin-bottom:20px;">
        <button id="toggleBotBtn" class="toggle-btn" onclick="toggleBot('<?php echo $botActive?'on':'off';?>')">
            <?php echo $botActive?'Ferma Bot':'Avvia Bot';?>
        </button>
        <button class="toggle-btn" onclick="testApiKeys()">Test API Keys</button>
    </div>

    <!-- SEZIONE SALDI (8 decimali) -->
    <h2>Saldi Reali Attuali</h2>
    <?php
    $brokers = ['Binance','Coinbase','Kraken'];
    ?>
    <table class="balance-table">
      <thead>
        <tr>
          <th>Coin</th>
          <?php foreach($brokers as $b): ?>
            <th><?php echo htmlspecialchars($b);?></th>
          <?php endforeach;?>
        </tr>
      </thead>
      <tbody>
      <?php
      foreach($selectedCoins as $c):
        echo "<tr><td><b>".htmlspecialchars($c)."</b></td>";
        foreach($brokers as $br){
          $bal = getWalletBalance($config,$br,$c);
          if(is_null($bal) || $bal===false){
            echo "<td>N/D</td>";
          } else {
            // 8 cifre decimali
            echo "<td>".number_format($bal,8)."</td>";
          }
        }
        echo "</tr>";
      endforeach;
      ?>
      </tbody>
    </table>

    <div class="controls">
        <input type="text" id="searchCoin" placeholder="Cerca criptovaluta..." onkeyup="filterCoins()">
    </div>
    <div class="controls-2" style="border:1px solid #ccc;padding:10px;border-radius:4px;background:#fafafa;max-width:900px;margin:auto;">
        <p><b>Impostazioni Operative:</b></p>
        <button type="button" onclick="selectAllCoins()">Seleziona tutte</button>
        <button type="button" onclick="unselectAllCoins()">Deseleziona tutte</button>
        <button type="button" onclick="selectDefault15()">Mostra default 15</button>
        <br><br>
        <?php
        foreach($allCoins as $coinOpt){
            $chk = in_array($coinOpt,$selectedCoins)?'checked':'';
            echo '<label style="margin:0 5px;"><input type="checkbox" class="chk-coin" value="'.htmlspecialchars($coinOpt).'" '.$chk.'> '.$coinOpt.'</label> ';
        }
        ?>
        <br><br>
        <label><b>Soglia spread (%):</b></label>
        <input type="number" step="0.01" id="thresholdField" value="<?php echo htmlspecialchars($thresholdPercent);?>" style="width:70px;">
        <button onclick="resetThreshold010()">Reset 0.10%</button>
        <br><br>
        <label><b>Capitale (USD):</b></label>
        <input type="number" step="0.01" id="capitalField" value="<?php echo htmlspecialchars($customInvestment);?>" style="width:100px;">
        <br><br>
        <button type="button" onclick="saveSettings()">Salva impostazioni</button>
        <br><br>
        <button type="button" onclick="alert('Il bot opera automaticamente al refresh, se attivo e se si supera la soglia di spread.')">
            Info Operazioni Automatiche
        </button>
    </div>

    <!-- DASHBOARD PREZZI & OP -->
    <?php
    $simLevels=[50,100,200,500,1000,3000,10000];
    foreach($dashboardData as $coin=>$exData):
        $pricesArr=[];
        foreach(["Binance","Coinbase","Kraken"] as $exn){
            $lp=$exData[$exn]["lastPrice"];
            if(is_numeric($lp)){
                $pricesArr[$exn]=(float)$lp;
            }
        }
        $minPrice=0; $maxPrice=0; $spread=0; $minExch=''; $maxExch='';
        if(!empty($pricesArr)){
            $minPrice=min($pricesArr);
            $maxPrice=max($pricesArr);
            $minExch=array_search($minPrice,$pricesArr);
            $maxExch=array_search($maxPrice,$pricesArr);
            if($minPrice>0){
                $spread=(($maxPrice-$minPrice)/$minPrice)*100;
            }
        }
        $hasOpportunity=($spread>=$thresholdPercent);
        $desc= match(strtoupper($coin)){
          "BTC"=>"Bitcoin (BTC) - Principale Crypto",
          "ETH"=>"Ethereum (ETH) - Smart contracts",
          "XRP"=>"Ripple (XRP) - Pagamenti Globali",
          default=>strtoupper($coin)." (Crypto Volatile)"
        };
    ?>
    <div class="coin-section" data-coin="<?php echo htmlspecialchars($coin); ?>">
        <h2><?php echo htmlspecialchars($desc);?></h2>
        <table>
            <thead>
              <tr>
                <th>Exchange</th>
                <th>Prezzo (USD)</th>
                <th>Variaz. (%)</th>
                <th>High 24h</th>
                <th>Low 24h</th>
                <th>Volume 24h</th>
                <th>Trend</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach(["Binance","Coinbase","Kraken"] as $exN):
                $row=$exData[$exN];
                $trClass='';
                if(is_numeric($row["lastPrice"]??null)){
                    if($exN==$minExch) $trClass='highlight-min';
                    if($exN==$maxExch) $trClass='highlight-max';
                }
                $trend=detectTrend($row["priceChangePercent"]??null);
            ?>
              <tr class="<?php echo $trClass; ?>">
                <td><?php echo $exN;?></td>
                <td><?php
                    $lp=$row["lastPrice"];
                    echo is_numeric($lp)? number_format($lp,4): $lp;
                ?></td>
                <td><?php
                    $pc=$row["priceChangePercent"];
                    echo is_numeric($pc)? number_format($pc,2).'%' : $pc;
                ?></td>
                <td><?php
                    $hp=$row["highPrice"];
                    echo is_numeric($hp)? number_format($hp,4): $hp;
                ?></td>
                <td><?php
                    $lop=$row["lowPrice"];
                    echo is_numeric($lop)? number_format($lop,4): $lop;
                ?></td>
                <td><?php
                    $vv=$row["volume"];
                    echo is_numeric($vv)? number_format($vv,2):$vv;
                ?></td>
                <td><?php echo htmlspecialchars($trend);?></td>
              </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        <?php if(!empty($pricesArr)):?>
          <?php
            $classOp = $hasOpportunity?'opportunity':'no-opportunity';
          ?>
          <div class="spread-info <?php echo $classOp;?>">
              <p><strong>Prezzo Minimo:</strong> 
                <?php
                  echo ($minPrice>0)? number_format($minPrice,4)." ($minExch)": "N/D";
                ?>
              </p>
              <p><strong>Prezzo Massimo:</strong> 
                <?php
                  echo ($maxPrice>0)? number_format($maxPrice,4)." ($maxExch)": "N/D";
                ?>
              </p>
              <p><strong>Spread:</strong> <?php echo number_format($spread,2)."%";?></p>
                         <?php if($hasOpportunity): ?>
                <p class="opportunity-text">
                  Opportunità: spread <?php echo number_format($spread,2);?>% (>= <?php echo $thresholdPercent;?>%).
                </p>
                <!-- Tabella di simulazione profitti -->
                <div class="profit-simulation">
                  <table>
                    <thead><tr><th>Invest (USD)</th><th>Profit (USD)</th></tr></thead>
                    <tbody>
                    <?php
                    foreach($simLevels as $lvl){
                        $profit = ($minPrice>0)? $lvl*((($maxPrice/$minPrice)-1)) : 0;
                        echo "<tr><td>$lvl</td><td>".number_format($profit,2)."</td></tr>";
                    }
                    ?>
                    </tbody>
                  </table>
                </div>
                <?php 
                  if($botActive && isset($automaticOpsLog[$coin])): 
                      $ops=$automaticOpsLog[$coin];
                ?>
                <div class="auto-ops">
                  <p><strong>Operazioni Automatiche su <?php echo htmlspecialchars($coin);?>:</strong></p>
                  <p>Acquisto (<?php echo $minExch;?>): <?php echo $ops['buy']['status']??'n/a';?></p>
                  <p>Vendita (<?php echo $maxExch;?>): <?php echo $ops['sell']['status']??'n/a';?></p>
                  <p>Trasferimento (<?php echo $minExch;?> -> Kraken): <?php echo $ops['transfer']['status']??'n/a';?></p>
                </div>
                <?php else: ?>
                  <p>Nessuna opportunità (spread < <?php echo $thresholdPercent;?>%).</p>
                <?php endif; // chiudo if interno ?>
              <?php endif; // chiudo if($hasOpportunity) ?>

          </div>
        <?php else:?>
          <div class="spread-info no-opportunity">
            <p>Dati insufficienti per calcolare Spread.</p>
          </div>
        <?php endif;?>
    </div>
    <?php endforeach;?>
    <h2 style="text-align:center; margin-top:40px;">Spiegazione delle Colonne</h2>
    <table style="width:80%; margin:20px auto; border-collapse:collapse; border:1px solid #ccc;">
      <thead>
        <tr style="background:#ddd;"><th>Colonna</th><th>Descrizione</th></tr>
      </thead>
      <tbody>
        <tr><td>Exchange</td><td>Piattaforma di trading (Binance, Coinbase, Kraken)</td></tr>
        <tr><td>Prezzo (USD)</td><td>Ultimo prezzo in dollari</td></tr>
        <tr><td>Variaz. (%)</td><td>Percentuale di variazione 24h</td></tr>
        <tr><td>High 24h</td><td>Prezzo massimo 24h</td></tr>
        <tr><td>Low 24h</td><td>Prezzo minimo 24h</td></tr>
        <tr><td>Volume 24h</td><td>Quantità scambiata in 24h</td></tr>
        <tr><td>Trend</td><td>Bullish (>3%), Bearish (<-3%), Stable (±3%)</td></tr>
      </tbody>
    </table>
</div>
</body>
</html>
