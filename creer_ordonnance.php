<?php
session_start();

// Vérifier la connexion du médecin
if (!isset($_SESSION['id_utilisateur'])) {
    header('Location: connexion.php');
    exit;
}

// Récupérer les paramètres
$id_consultation = $_GET['id_consultation'] ?? null;
$id_patient = $_GET['id_patient'] ?? null;

if (!$id_consultation || !$id_patient) {
    die("Paramètres manquants");
}

$host = 'localhost';
$dbname = 'dbhomedoc';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les infos du patient
    $stmt_patient = $pdo->prepare("
        SELECT p.*, u.email, u.telephone, u.date_naissance 
        FROM patient p
        JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
        WHERE p.id_patient = ?
    ");
    $stmt_patient->execute([$id_patient]);
    $patient = $stmt_patient->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        die("Patient non trouvé");
    }

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Formulaire de création d'ordonnance
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer une ordonnance</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .patient-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .patient-info p {
            margin: 5px 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        textarea {
            min-height: 200px;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="ordonnance-container">
        <h1>Nouvelle ordonnance</h1>
        <form action="generer_ordonnance.php" method="post">
            <input type="hidden" name="id_consultation" value="<?= htmlspecialchars($id_consultation) ?>">
            <input type="hidden" name="id_patient" value="<?= htmlspecialchars($id_patient) ?>">
            
            <div class="patient-info">
                <h3>Informations du patient</h3>
                <p><strong>Nom complet:</strong> <?= htmlspecialchars($patient['prenom']) . ' ' . htmlspecialchars($patient['nom']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($patient['email']) ?></p>
                <p><strong>Téléphone:</strong> <?= htmlspecialchars($patient['telephone']) ?></p>
                <p><strong>Date de naissance:</strong> <?= htmlspecialchars($patient['date_naissance']) ?></p>
                <p><strong>Sexe:</strong> <?= htmlspecialchars($patient['sexe']) ?></p>
            </div>
            
            <div class="form-group">
                <label for="email_patient">Email du patient:</label>
                <input type="email" id="email_patient" name="email_patient" value="<?= htmlspecialchars($patient['email']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="contenu">Contenu de l'ordonnance:</label>
                <textarea id="contenu" name="contenu" rows="10" required placeholder="Rédigez ici l'ordonnance..."></textarea>
            </div>
            
            <button type="submit" class="btn">Générer et envoyer l'ordonnance</button>
        </form>
    </div>
</body>
</html>