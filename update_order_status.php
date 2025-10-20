<?php
session_start();
require_once 'login.php';

if (!isset($_SESSION['id_utilisateur']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['error' => 'Accès non autorisé']));
}

$orderId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if (!$orderId || !in_array($action, ['validate', 'cancel'])) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['error' => 'Paramètres invalides']));
}

try {
    // Vérifier que la commande appartient bien à la pharmacie de l'utilisateur
    $stmt = $pdo->prepare("
        SELECT c.id_commande 
        FROM commande c
        JOIN pharmacie p ON c.id_pharmacie = p.id_pharmacie
        WHERE c.id_commande = ? AND p.id_utilisateur = ?
    ");
    $stmt->execute([$orderId, $_SESSION['id_utilisateur']]);
    $order = $stmt->fetch();

    if (!$order) {
        header('HTTP/1.1 403 Forbidden');
        die(json_encode(['error' => 'Commande non trouvée ou accès refusé']));
    }

    // Déterminer le nouveau statut
    $newStatus = $action === 'validate' ? 'validée' : 'annulée';

    // Mettre à jour le statut
    $update = $pdo->prepare("
        UPDATE commande 
        SET statut = ? 
        WHERE id_commande = ? AND statut = 'en attente'
    ");
    $update->execute([$newStatus, $orderId]);

    if ($update->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'La commande ne peut pas être modifiée']);
    }
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    die(json_encode(['error' => 'Erreur de base de données']));
}
?>