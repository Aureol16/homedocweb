<?php
session_start();

if (!isset($_SESSION['id_utilisateur']) || !isset($_GET['id'])) {
    echo json_encode(['error' => 'Accès non autorisé']);
    exit;
}

$host = 'localhost';
$dbname = 'dbhomedoc';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $id_commande = intval($_GET['id']);

    // Vérifier si la commande appartient bien à la pharmacie
    $stmt = $pdo->prepare("
        SELECT c.id_commande, c.date_commande, c.statut, 
               p.nom AS patient_nom, p.prenom AS patient_prenom
        FROM commande c
        JOIN patient p ON c.id_patient = p.id_patient
        WHERE c.id_commande = ? AND c.id_pharmacie IN (
            SELECT id_pharmacie FROM pharmacie WHERE id_utilisateur = ?
        )
    ");
    $stmt->execute([$id_commande, $_SESSION['id_utilisateur']]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        echo json_encode(['error' => 'Commande non trouvée ou accès refusé.']);
        exit;
    }

    // Récupérer les médicaments de la commande
    $stmt = $pdo->prepare("
        SELECT m.nom_medicament, cm.quantite
        FROM commande_medicament cm
        JOIN medicament m ON cm.id_medicament = m.id_medicament
        WHERE cm.id_commande = ?
    ");
    $stmt->execute([$id_commande]);
    $medicaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Construire les détails des médicaments
    $details = "";
    foreach ($medicaments as $med) {
        $details .= "<li>" . htmlspecialchars($med['nom_medicament']) . " - Quantité: " . intval($med['quantite']) . "</li>";
    }

    echo json_encode([
        'patient_name' => htmlspecialchars($commande['patient_prenom'] . ' ' . $commande['patient_nom']),
        'date_commande' => date('d/m/Y H:i', strtotime($commande['date_commande'])),
        'statut' => htmlspecialchars(ucfirst($commande['statut'])),
        'details' => "<ul>$details</ul>"
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données']);
}
