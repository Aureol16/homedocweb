<?php
// Récupérer les infos du médecin pour la sidebar
$photo_profil = $_SESSION['photo_profil'] ?? 'images/profil_defaut.jpg';
$nom_complet = "Dr. " . ($_SESSION['prenom'] ?? '') . " " . ($_SESSION['nom'] ?? '');
$specialite = $_SESSION['specialite'] ?? '';
?>

<aside class="sidebar">
    <div class="profile-card">
        <img src="<?= $photo_profil ?>" alt="Photo profil" class="profile-img">
        <h3><?= htmlspecialchars($nom_complet) ?></h3>
        <p><?= htmlspecialchars($specialite) ?></p>
    </div>

    <nav>
        <ul>
            <li><a href="dashboard_medecin.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
            <li><a href="consultations_confirmees.php"><i class="fas fa-check-circle"></i> Consultations</a></li>
            <li class="active"><a href="emploi_du_temps.php"><i class="fas fa-calendar-alt"></i> Emploi du temps</a></li>
            <li><a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </nav>
</aside>