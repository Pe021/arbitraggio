<?php
/**
 * login.php
 *
 * Pagina di login per proteggere bot.php.
 * Se l'utente è già loggato, reindirizza a bot.php.
 * Altrimenti, mostra un form di login. Al submit, verifica nickname e password
 * in users.php e, se validi, imposta la sessione.
 */

session_start();
include 'users.php'; // carica array $users con 3 utenti

// Funzione per scrivere log di accesso nel file access.log
function logAccess($message) {
    $file = __DIR__ . '/access.log';
    $date = date('Y-m-d H:i:s');
    file_put_contents($file, "[$date] $message\n", FILE_APPEND);
}

// Se l'utente è già loggato, rimandiamolo a bot.php
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: bot.php');
    exit;
}

// Se il form è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname']);
    $password = trim($_POST['password']);

    // Verifichiamo se esiste questo nickname in $users e la password corrisponde
    if (isset($users[$nickname]) && $users[$nickname] === $password) {
        // Credenziali corrette
        $_SESSION['logged_in'] = true;
        $_SESSION['nickname']  = $nickname;
        $_SESSION['start_time'] = time(); // registriamo l'istante di login

        // Scriviamo nel log
        logAccess("LOGIN: Utente '$nickname' ha effettuato l'accesso.");

        header('Location: bot.php');
        exit;
    } else {
        $error = "Credenziali non valide.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Arbitraggio</title>
    <style>
        /* Reset e impostazioni di base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 400px;
        }
        .login-container h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: #444;
        }
        form label {
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: block;
            color: #555;
        }
        form input[type="text"],
        form input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        form button {
            width: 100%;
            padding: 0.75rem;
            background-color: #667eea;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        form button:hover {
            background-color: #5a67d8;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login Bot  </h1>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="nickname">Nickname:</label>
            <input type="text" id="nickname" name="nickname" required>
            
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            
            <button type="submit">Accedi</button>
        </form>
    </div>
</body>
</html>
