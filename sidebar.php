<?php
if (!isset($pharmacie_data)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['id_utilisateur'])) {
        header('Location: connexion.php');
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=dbhomedoc;charset=utf8", 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("
            SELECT p.id_pharmacie, p.nom, p.telephone, p.adresse, u.email
            FROM pharmacie p
            JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
            WHERE p.id_utilisateur = ?
        ");
        $stmt->execute([$_SESSION['id_utilisateur']]);
        $pharmacie = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pharmacie) die("Accès non autorisé");

        $pharmacie_data = [
            'id' => $pharmacie['id_pharmacie'],
            'nom' => htmlspecialchars($pharmacie['nom']),
            'email' => htmlspecialchars($pharmacie['email']),
            'telephone' => htmlspecialchars($pharmacie['telephone']),
            'adresse' => htmlspecialchars($pharmacie['adresse']),
            'photo' => 'fas fa-prescription-bottle-alt'
        ];
    } catch (PDOException $e) {
        die("Erreur de base de données : " . $e->getMessage());
    }
}
?>

<aside class="sidebar">
    <div class="profile-summary">
        <i class="<?= $pharmacie_data['photo'] ?> profile-icon"></i>
        <h3><?= $pharmacie_data['nom'] ?></h3>
    </div>

    <nav class="main-nav">
        <ul>
            <li class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard_pharmacie.php' ? 'active' : '' ?>">
                <a href="dashboard_pharmacie.php"><i class="fas fa-home"></i> Tableau de bord</a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) === 'gestion_stock.php' ? 'active' : '' ?>">
                <a href="gestion_stock.php"><i class="fas fa-pills"></i> Gestion du stock</a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) === 'commandes_recues.php' ? 'active' : '' ?>">
                <a href="commandes_recues.php"><i class="fas fa-box"></i> Commandes reçues</a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) === 'historique.php' ? 'active' : '' ?>">
                <a href="historique.php"><i class="fas fa-history"></i> Historique</a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) === 'ordonnances.php' ? 'active' : '' ?>">
                <a href="ordonnances.php"><i class="fas fa-file-medical"></i> Ordonnances</a>
            </li>
        </ul>
    </nav>
</aside>