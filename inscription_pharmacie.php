<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Données utilisateur
    $email = $_POST['email'];
    $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
    $type_utilisateur = 'pharmacie';

    // Insérer dans la table utilisateur
    $stmt = $pdo->prepare("INSERT INTO utilisateur (email, mot_de_passe, type_utilisateur) VALUES (?, ?, ?)");
    $stmt->execute([$email, $mot_de_passe, $type_utilisateur]);

    // Récupérer l'id_utilisateur généré
    $id_utilisateur = $pdo->lastInsertId();

    // Données pharmacie
    $nom = $_POST['nom'];
    $adresse = $_POST['adresse'];
    $telephone = $_POST['telephone'];
    $heure_ouverture = $_POST['heure_ouverture'];
    $heure_fermeture = $_POST['heure_fermeture'];

    // Insertion dans la table pharmacie
    $stmt = $pdo->prepare("INSERT INTO pharmacie (id_utilisateur, nom, adresse, telephone, email, heure_ouverture, heure_fermeture)
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $id_utilisateur, $nom, $adresse, $telephone, $email, $heure_ouverture, $heure_fermeture
    ]);

    // Redirection vers la page de connexion
    header("Location: connexion.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription Pharmacie - HomeDoc</title>
    <link rel="stylesheet" href="inscription_pharmacie.css">
</head>
<body>
<div class="container">
    <h2>Inscription d'une Pharmacie</h2>
    <form action="" method="POST">
        <label for="nom">Nom de la pharmacie :</label>
        <input type="text" name="nom" required>

        <label for="adresse">Adresse :</label>
        <input type="text" name="adresse" required>

        <label for="telephone">Téléphone :</label>
        <input type="tel" name="telephone" required>

        <label for="email">E-mail :</label>
        <input type="email" name="email" required>

        <label for="mot_de_passe">Mot de passe :</label>
        <div class="password-container">
            <input type="password" id="mot_de_passe" name="mot_de_passe" required>
            <button type="button" onclick="togglePassword()">👁️</button>
        </div>

        <label for="heure_ouverture">Heure d'ouverture :</label>
        <input type="time" name="heure_ouverture" required>

        <label for="heure_fermeture">Heure de fermeture :</label>
        <input type="time" name="heure_fermeture" required>

        <button type="submit">S'inscrire</button>
    </form>
</div>

<script>
    function togglePassword() {
        const input = document.getElementById("mot_de_passe");
        input.type = input.type === "password" ? "text" : "password";
    }
</script>
</body>
</html>