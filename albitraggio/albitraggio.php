<?php
/**
 * albitraggio.php
 *
 * Pagina protetta: se l'utente non è loggato, reindirizza a login.php.
 * Se è loggato, mostra il contenuto "bot di albitraggio" (placeholder).
 */

session_start();

// Se non loggato, reindirizza al login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Funzione per log di permanenza
function logAccess($message) {
    $file = __DIR__ . '/access.log';
    $date = date('Y-m-d H:i:s');
    file_put_contents($file, "[$date] $message\n", FILE_APPEND);
}

// Se l'utente fa logout (o esce dalla pagina)
if (isset($_GET['logout'])) {
    // calcoliamo la permanenza
    if (isset($_SESSION['start_time'])) {
        $start = $_SESSION['start_time'];
        $end   = time();
        $duration = $end - $start; // in secondi
        $nickname = $_SESSION['nickname'];

        // Convertiamo in minuti o manteniamo in secondi
        $minutes = round($duration / 60, 2);

        // Logghiamo la permanenza
        logAccess("LOGOUT - Utente '$nickname' ha lasciato la pagina. Permanenza: $minutes minuti");
    }

    // Distruggiamo la sessione
    session_destroy();
    header('Location: login.php');
    exit;
}

// Se l'utente è loggato, mostriamo il contenuto
$nickname = $_SESSION['nickname'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Albitraggio - Bot</title>
</head>
<body>
    <h1>Benvenuto, <?php echo htmlspecialchars($nickname); ?>!</h1>
    <p>Questa è la pagina protetta del bot di albitraggio (placeholder).<br>
       Qui va inserito il codice del tuo BOT (logiche, funzioni, ecc.).</p>
    <p><a href="?logout=1">Logout</a></p>
</body>
</html>
