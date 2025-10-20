<?php
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    header('Location: connexion.php');
    exit;
}

$host = 'localhost';
$dbname = 'dbhomedoc';
$user = 'root';
$pass = '';

$jours_fr = [
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi',
    'Saturday' => 'Samedi',
    'Sunday' => 'Dimanche'
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer l'ID du médecin
    $stmt = $pdo->prepare("SELECT id_medecin FROM medecin WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['id_utilisateur']]);
    $medecin = $stmt->fetch();
    $id_medecin = $medecin['id_medecin'];

    // Récupérer les informations du médecin
    $stmt_medecin_data = $pdo->prepare("
        SELECT m.nom, m.prenom, m.specialite
        FROM medecin m
        WHERE m.id_medecin = ?
    ");
    $stmt_medecin_data->execute([$id_medecin]);
    $medecin_data = $stmt_medecin_data->fetch(PDO::FETCH_ASSOC);

    if (!$medecin_data) {
        die("Accès non autorisé");
    }

    // Formatage des données
    $medecin_data = [
        'nom_complet' => "Dr. " . htmlspecialchars($medecin_data['prenom']) . " " . htmlspecialchars($medecin_data['nom']),
        'specialite' => htmlspecialchars($medecin_data['specialite']),
        'photo' => "fas fa-user-md" // Icône Font Awesome pour médecin
    ];

    // Date sélectionnée (par défaut aujourd'hui)
    $date_selectionnee = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $jour_selectionne_en = date('l', strtotime($date_selectionnee));
    $jour_selectionne_fr = $jours_fr[$jour_selectionne_en];

    // Requête pour récupérer les consultations confirmées
    $sql = "
        SELECT c.*, p.nom AS nom_patient, p.prenom AS prenom_patient,
               c.adresse, p.telephone, p.date_naissance, u.email,
               DATE_FORMAT(c.date_consultation, '%d/%m/%Y') AS date_formatee,
               DATE_FORMAT(c.heure_consultation, '%H:%i') AS heure_formatee
        FROM consultation c
        JOIN patient p ON c.id_patient = p.id_patient
        JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
        WHERE c.id_medecin = ?
        AND c.date_consultation = ?
        AND c.statut = 'confirmé'
        ORDER BY c.heure_consultation ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_medecin, $date_selectionnee]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Générer le calendrier
    $mois_actuel = date('m', strtotime($date_selectionnee));
    $annee_actuelle = date('Y', strtotime($date_selectionnee));

    $premier_jour = date('N', strtotime("$annee_actuelle-$mois_actuel-01"));
    $jours_dans_mois = date('t', strtotime("$annee_actuelle-$mois_actuel-01"));

    // Récupérer les jours avec consultations
    $sql_jours_occupes = "
        SELECT DISTINCT DATE_FORMAT(date_consultation, '%Y-%m-%d') AS jour
        FROM consultation
        WHERE id_medecin = ?
        AND MONTH(date_consultation) = ?
        AND YEAR(date_consultation) = ?
        AND statut = 'confirmé'
    ";
    $stmt = $pdo->prepare($sql_jours_occupes);
    $stmt->execute([$id_medecin, $mois_actuel, $annee_actuelle]);
    $jours_occupes = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Emploi du temps | HomeDoc</title>

    <link rel="stylesheet" href="emploi_du_temps.css"> <!-- CSS spécifique -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="profile-summary">
                <div class="profile-icon-container">
                    <i class="<?= $medecin_data['photo'] ?> profile-icon"></i>
                </div>
                <h3><?= $medecin_data['nom_complet'] ?></h3>
                <p><?= $medecin_data['specialite'] ?></p>
            </div>

            <nav class="main-nav">
                <ul>
                    <li>
                        <a href="dashboard_medecin.php">
                            <i class="fas fa-home"></i> Tableau de bord
                        </a>
                    </li>
                    <li>
                        <a href="consultations_confirmees.php">
                            <i class="fas fa-check-circle"></i> Consultations
                        </a>
                    </li>
                    <li class="active">
                        <a href="emploi_du_temps.php">
                            <i class="fas fa-calendar-alt"></i>  Emploi du temps
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-calendar-alt"></i> Emploi du temps</h1>
                <div class="header-actions">
                    <div class="calendar-mini">
                        <div class="calendar-header">
                            <button onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                            <span><?= date('F Y', strtotime($date_selectionnee)) ?></span>
                            <button onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="calendar-weekdays">
                            <?php foreach(['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $day): ?>
                                <span><?= $day ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="calendar-days">
                            <?php
                            // Jours vides du mois précédent
                            for ($i = 1; $i < $premier_jour; $i++) {
                                echo '<span class="other-month"></span>';
                            }

                            // Jours du mois actuel
                            for ($i = 1; $i <= $jours_dans_mois; $i++) {
                                $current_date = "$annee_actuelle-$mois_actuel-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                                $is_active = $current_date == $date_selectionnee;
                                $has_consultation = in_array($current_date, $jours_occupes);

                                echo '<span class="day ' . ($is_active ? 'active' : '') .
                                     ($has_consultation ? ' has-consultation' : '') . '"
                                     onclick="selectDate(\'' . $current_date . '\')">' . $i . '</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-section">
                <div class="section-header">
                    <h2>Consultations du <?= $jour_selectionne_fr ?> <?= date('d/m/Y', strtotime($date_selectionnee)) ?></h2>
                </div>

                <?php if (empty($consultations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>Aucune consultation confirmée ce jour</p>
                    </div>
                <?php else: ?>
                    <div class="consultations-grid">
                        <?php foreach ($consultations as $consultation): ?>
                            <div class="consultation-card <?= $consultation['urgent'] === 'oui' ? 'urgent' : '' ?>">
                                <div class="consultation-time">
                                    <span class="heure"><?= $consultation['heure_formatee'] ?></span>
                                    <span class="duree">30 min</span>
                                </div>

                                <div class="patient-info">
                                    <div class="patient-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="patient-details">
                                        <h3><?= htmlspecialchars($consultation['prenom_patient'] . ' ' . $consultation['nom_patient']) ?></h3>
                                        <div class="patient-meta">
                                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($consultation['telephone']) ?></span>
                                            <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($consultation['email']) ?></span>
                                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($consultation['adresse']) ?></span>
                                            <span><i class="fas fa-birthday-cake"></i> <?= date('d/m/Y', strtotime($consultation['date_naissance'])) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($consultation['urgent'] === 'oui'): ?>
                                    <div class="urgence-badge">
                                        <i class="fas fa-exclamation-triangle"></i> Urgent
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function selectDate(date) {
            window.location.href = `?date=${date}`;
        }

        function changeMonth(offset) {
            const currentDate = new Date('<?= $date_selectionnee ?>');
            currentDate.setMonth(currentDate.getMonth() + offset);

            const year = currentDate.getFullYear();
            const month = String(currentDate.getMonth() + 1).padStart(2, '0');
            const day = '01';

            window.location.href = `?date=${year}-${month}-${day}`;
        }
    </script>
</body>
</html>
