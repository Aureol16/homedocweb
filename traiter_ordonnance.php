<?php
require_once 'login.php';
session_start();

if (!isset($_SESSION['userId']) || $_SESSION['userType'] !== 'pharmacie') {
    header('Location: ../connexion.php');
    exit;
}

if (isset($_POST['id_ordonnance'])) {
    $idOrdonnance = $_POST['id_ordonnance'];

    // Met à jour le statut de l’ordonnance à "en traitement"
    $stmt = $pdo->prepare("UPDATE ordonnance SET statut = 'en traitement' WHERE id_ordonnance = ?");
    $stmt->execute([$idOrdonnance]);

    // Redirige vers une page pour créer la commande
    header("Location: creer_commande.php?id_ordonnance=" . $idOrdonnance);
    exit;
} else {
    echo "Erreur : Ordonnance introuvable.";
}
