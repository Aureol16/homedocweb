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
    $type_utilisateur = 'medecin';

    // Insérer dans la table utilisateur
    $stmt = $pdo->prepare("INSERT INTO utilisateur (email, mot_de_passe, type_utilisateur) VALUES (?, ?, ?)");
    $stmt->execute([$email, $mot_de_passe, $type_utilisateur]);

    // Récupérer l'id_utilisateur généré
    $id_utilisateur = $pdo->lastInsertId();

    // Données médecin
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $specialite = $_POST['specialite'];
    $adresse = $_POST['adresse'];
    $telephone = $_POST['telephone'];
    $jours_dispo = implode(',', $_POST['jours_dispo'] ?? []);
    $heures_dispo = implode(',', $_POST['heures_dispo'] ?? []);
    $prix_consultation = $_POST['prix_consultation'];
    $frais_transport = $_POST['frais_transport'];

    // Gestion photo de profil
    $photoPath = null;
    if (!empty($_FILES['photo']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $photoPath = $targetDir . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath);
    }

    // Insertion dans la table médecin
    $stmt = $pdo->prepare("INSERT INTO medecin (id_utilisateur, nom, prenom, specialite, adresse, telephone, photo_profil, jours_disponibilite, heures_disponibilite, prix_consultation, frais_transport)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $id_utilisateur, $nom, $prenom, $specialite, $adresse, $telephone, $photoPath,
        $jours_dispo, $heures_dispo, $prix_consultation, $frais_transport
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
    <title>Inscription Médecin - HomeDoc</title>
    <link rel="stylesheet" href="inscription_medecin.css">
</head>
<body>
<div class="container">
    <h2>Inscription Médecin - HomeDoc</h2>
    <form action="" method="POST" enctype="multipart/form-data">
        <label for="nom">Nom :</label>
        <input type="text" name="nom" required>

        <label for="prenom">Prénom :</label>
        <input type="text" name="prenom" required>

        <label for="specialite">Spécialité :</label>
        <select name="specialite" required>
            <option value="">-- Choisir --</option>
            <option value="Cardiologie Chirurgicale">Cardiologie Chirurgicale</option>
            <option value="Orthopédie">Orthopédie</option>
            <option value="Neurochirurgie">Neurochirurgie</option>
            <option value="Cardiologie">Cardiologie</option>
            <option value="Dermatologie">Dermatologie</option>
            <option value="Généraliste">Généraliste</option>
            <option value="Gynécologie">Gynécologie</option>
            <option value="Pédiatrie">Pédiatrie</option>
            <option value="Neurologie">Neurologie</option>
            <option value="Psychiatrie">Psychiatrie</option>
            <option value="Radiologie">Radiologie</option>
        </select>

        <label for="adresse">Adresse :</label>
        <input type="text" name="adresse" required>

        <label for="telephone">Téléphone :</label>
        <input type="tel" name="telephone" required>

        <label for="photo">Photo de profil (facultatif) :</label>
        <input type="file" name="photo" accept="image/*">

        <label for="email">Adresse e-mail :</label>
        <input type="email" name="email" required>

        <label for="mot_de_passe">Mot de passe :</label>
        <div class="password-container">
            <input type="password" id="mot_de_passe" name="mot_de_passe" required>
            <button type="button" onclick="togglePassword()">👁️</button>
        </div>

        <label>Jours de disponibilité :</label>
        <div class="checkbox-group">
            <?php
            $jours = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"];
            foreach ($jours as $jour) {
                echo "<label><input type='checkbox' name='jours_dispo[]' value='$jour'> $jour</label>";
            }
            ?>
        </div>

        <label>Heures de disponibilité :</label>
        <div class="checkbox-group">
            <?php
            $heures = ["07h","08h","09h", "10h","11h", "12h", "13h","14h","15h", "16h","17h", "18h","19h", "20h","21h","22h","23h"];
            foreach ($heures as $heure) {
                echo "<label><input type='checkbox' name='heures_dispo[]' value='$heure'> $heure</label>";
            }
            ?>
        </div>

        <label for="prix_consultation">Prix consultation (FCFA) :</label>
        <input type="number" name="prix_consultation" required>

        <label for="frais_transport">Frais de transport (FCFA) :</label>
        <input type="number" name="frais_transport" required>

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