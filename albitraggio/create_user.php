<?php
// create_user.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'db_config.php';

// Dati per l'utente da creare
$username = 'peppegil';
$password_plain = '748911gg';

// Crea un hash della password (utilizziamo password_hash per sicurezza)
$password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
if (!$stmt) {
    die("Errore nella preparazione della query: " . $mysqli->error);
}
$stmt->bind_param("ss", $username, $password_hashed);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Utente creato con successo! ID: " . $stmt->insert_id;
} else {
    echo "Errore nell'inserimento dell'utente: " . $stmt->error;
}

$stmt->close();
$mysqli->close();
?>
