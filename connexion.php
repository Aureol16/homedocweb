<?php
session_start();

// Connexion à la base de données
$host = 'localhost';
$dbname = 'dbhomedoc';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$message = "";

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $mot_de_passe = $_POST['mot_de_passe'];

    // Récupération de l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE email = ?");
    $stmt->execute([$email]);
    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérification du mot de passe
    if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
        $_SESSION['id_utilisateur'] = $utilisateur['id_utilisateur'];
        $_SESSION['type_utilisateur'] = $utilisateur['type_utilisateur'];

        // Redirection selon le type d'utilisateur
        if ($utilisateur['type_utilisateur'] === 'medecin') {
            header("Location: dashboard_medecin.php");
            exit;
        } elseif ($utilisateur['type_utilisateur'] === 'pharmacie') {
            $_SESSION['id_pharmacie'] = $utilisateur['id_utilisateur']; // Ajout important
            header("Location: dashboard_pharmacie.php");
            exit;
        } else {
            $message = "Type d'utilisateur non reconnu.";
        }
    } else {
        $message = "Identifiants incorrects.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion HomeDoc - HomeDoc</title>
    <link rel="stylesheet" href="connexion.css">
</head>
<body>
<div class="container">
    <h2>Connexion HomeDoc</h2>

    <?php if ($message): ?>
        <p class="error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="email">Adresse e-mail :</label>
        <input type="email" name="email" required>

        <label for="mot_de_passe">Mot de passe :</label>
        <div class="password-container">
            <input type="password" id="mot_de_passe" name="mot_de_passe" required>
            <button type="button" onclick="togglePassword()">👁️</button>
        </div>

        <button type="submit">Se connecter</button>
    </form>

    <div class="links">
        <p>Vous êtes un médecin ? <a href="inscription_medecin.php">Inscrivez-vous ici</a></p>
        <p>Vous êtes une pharmacie ? <a href="inscription_pharmacie.php">Inscrivez-vous ici</a></p>
    </div>
</div>

<script>
    function togglePassword() {
        const input = document.getElementById("mot_de_passe");
        input.type = input.type === "password" ? "text" : "password";
    }
</script>
</body>
</html>
