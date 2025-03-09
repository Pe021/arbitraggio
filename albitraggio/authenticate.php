<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'db_config.php';

// Verifica che i dati siano stati inviati
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    die("Dati non inviati correttamente!");
}

$username = trim($_POST['username']);
$password_input = $_POST['password'];

// Prepara la query per selezionare l'id e la password hashata
$stmt = $mysqli->prepare("SELECT id, password FROM users WHERE username = ?");
if (!$stmt) {
    die("Errore nella preparazione della query: " . $mysqli->error);
}
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($user_id, $password_hashed);
    $stmt->fetch();
    // Verifica la password
    if (password_verify($password_input, $password_hashed)) {
        // Credenziali corrette: imposta la sessione
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit;
    } else {
        $error = "Password errata.";
    }
} else {
    $error = "Utente non trovato.";
}

$stmt->close();
$mysqli->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Errore Login</title>
</head>
<body>
    <h2>Errore Login</h2>
    <p><?php echo htmlspecialchars($error); ?></p>
    <p><a href="login.php">Torna al login</a></p>
</body>
</html>
