<?php
/**
 * access_log.php
 *
 * Mostra i log degli accessi (login/logout) e la permanenza registrata in access.log.
 * Proteggiamo anche questa pagina, così solo utenti loggati possono visualizzarla.
 */

session_start();

// Se vuoi che solo utenti loggati (admin) vedano i log, controlla sessione
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Leggiamo il file di log
$logfile = __DIR__ . '/access.log';
if (!file_exists($logfile)) {
    $logContent = "Nessun log disponibile.";
} else {
    $logContent = file_get_contents($logfile);
    if (empty($logContent)) {
        $logContent = "Nessun contenuto nel file di log.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Log Accessi Arbitraggio</title>
</head>
<body>
    <h1>Log degli Accessi</h1>
    <pre><?php echo htmlspecialchars($logContent); ?></pre>
    <p><a href="bot.php">Torna al BOT</a></p>
</body>
</html>
