<?php
session_start();

// Vérifier la connexion du médecin
if (!isset($_SESSION['id_utilisateur'])) {
    header('Location: connexion.php');
    exit;
}

$host   = 'localhost';
$dbname = 'dbhomedoc';
$user   = 'root';
$pass   = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pour avoir les jours de la semaine en français
    $pdo->exec("SET lc_time_names = 'fr_FR'");

    // Récupérer l'ID du médecin
    $stmt_medecin = $pdo->prepare("SELECT id_medecin FROM medecin WHERE id_utilisateur = ?");
    $stmt_medecin->execute([$_SESSION['id_utilisateur']]);
    $medecin = $stmt_medecin->fetch(PDO::FETCH_ASSOC);

    if (!$medecin) {
        die("Accès non autorisé");
    }

    // Récupérer les informations du médecin
    $stmt_medecin_data = $pdo->prepare("
        SELECT nom, prenom, specialite
        FROM medecin
        WHERE id_medecin = ?
    ");
    $stmt_medecin_data->execute([$medecin['id_medecin']]);
    $medecin_info = $stmt_medecin_data->fetch(PDO::FETCH_ASSOC);

    if (!$medecin_info) {
        die("Accès non autorisé");
    }

    // Formatage des données
    $medecin_data = [
        'photo' => "fas fa-user-circle",
        'nom_complet' => "Dr. " . htmlspecialchars($medecin_info['prenom']) . " " . htmlspecialchars($medecin_info['nom']),
        'specialite' => htmlspecialchars($medecin_info['specialite']),
        'nom' => htmlspecialchars($medecin_info['nom']),
        'prenom' => htmlspecialchars($medecin_info['prenom'])
    ];

    // Récupérer les consultations confirmées
    $sql = "
    SELECT
        c.id_consultation,
        DATE_FORMAT(c.date_consultation, '%W %d/%m/%y') AS date_formatee,
        DATE_FORMAT(c.heure_consultation, '%H:%i') AS heure_formatee,
        c.statut,
        c.urgent,
        c.adresse AS adresse_consultation,
        p.id_patient,
        p.nom AS nom_patient,
        p.prenom AS prenom_patient,
        p.telephone,
        p.date_naissance,
        p.sexe,
        u.email AS email_patient
    FROM consultation c
    JOIN patient p ON c.id_patient = p.id_patient
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    WHERE c.id_medecin = ? AND c.statut = 'confirmé'
    ORDER BY c.date_consultation ASC, c.heure_consultation ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$medecin['id_medecin']]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultations confirmées | HomeDoc</title>
    <link rel="stylesheet" href="consultations_confirmees.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Styles pour la popup */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .popup-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .close-popup {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .popup-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .popup-header h2 {
            color: #3a7bd5;
            margin: 0;
        }
        
        .ordonnance-form {
            display: grid;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        .popup-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            border: none;
        }
        
        .btn-primary {
            background-color: #3a7bd5;
            color: white;
        }
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .patient-info-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .patient-info-item {
            margin-bottom: 5px;
        }
        
        .patient-info-label {
            font-weight: 600;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
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
                    <li class="active">
                        <a href="consultations_confirmees.php">
                            <i class="fas fa-check-circle"></i> Consultations confirmées
                        </a>
                    </li>
                    <li>
                        <a href="emploi_du_temps.php">
                            <i class="fas fa-calendar-alt"></i> Emploi du temps
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header>
                <h1><i class="fas fa-check-circle"></i> Consultations confirmées</h1>
                <div class="header-actions">
                    <div class="search-box">
                        <input type="text" placeholder="Rechercher...">
                        <button><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </header>

            <section class="consultations-section">
                <?php if (empty($consultations)): ?>
                    <div class="empty-state">
                        <img src="images/no-data.svg" alt="Aucune donnée">
                        <h2>Aucune consultation confirmée</h2>
                        <p>Vous n'avez pas encore de consultations confirmées.</p>
                    </div>
                <?php else: ?>
                    <div class="consultations-grid">
                        <?php foreach ($consultations as $consultation): ?>
                            <div class="consultation-card" 
                                 data-consultation-id="<?= $consultation['id_consultation'] ?>" 
                                 data-patient-id="<?= $consultation['id_patient'] ?>"
                                 data-patient-name="<?= htmlspecialchars($consultation['prenom_patient']) . ' ' . htmlspecialchars($consultation['nom_patient']) ?>"
                                 data-patient-email="<?= htmlspecialchars($consultation['email_patient']) ?>"
                                 data-patient-phone="<?= htmlspecialchars($consultation['telephone']) ?>"
                                 data-patient-birthdate="<?= htmlspecialchars($consultation['date_naissance']) ?>"
                                 data-patient-gender="<?= htmlspecialchars($consultation['sexe']) ?>">
                                <div class="consultation-header">
                                    <div>
                                        <h3><?= htmlspecialchars($consultation['prenom_patient']) . ' ' . htmlspecialchars($consultation['nom_patient']) ?></h3>
                                        <p class="consultation-date">
                                            <i class="far fa-calendar-alt"></i> <?= $consultation['date_formatee'] ?>
                                            <i class="far fa-clock"></i> <?= $consultation['heure_formatee'] ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="consultation-details">
                                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($consultation['telephone']) ?></p>
                                    <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($consultation['adresse_consultation']) ?></p>
                                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($consultation['email_patient']) ?></p>
                                    
                                    <?php if ($consultation['urgent'] === 'oui'): ?>
                                        <div class="urgence-tag">
                                            <i class="fas fa-exclamation-triangle"></i> Urgent
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="consultation-actions">
                                    <button class="btn btn-primary btn-create-ordonnance">
                                        <i class="fas fa-notes-medical"></i> Créer une ordonnance
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Popup pour créer une ordonnance -->
    <div id="ordonnancePopup" class="popup-overlay">
        <div class="popup-content">
            <span class="close-popup">&times;</span>
            <div class="popup-header">
                <h2>Créer une ordonnance</h2>
            </div>
            <form id="ordonnanceForm" class="ordonnance-form" action="" method="post">
                <input type="hidden" id="popup_consultation_id" name="consultation_id">
                <input type="hidden" id="popup_patient_id" name="patient_id">
                
                <div class="patient-info-section">
                    <h3>Informations du patient</h3>
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <span class="patient-info-label">Nom complet:</span>
                            <span id="popup_patient_name_display"></span>
                        </div>
                        <div class="patient-info-item">
                            <span class="patient-info-label">Date de naissance:</span>
                            <span id="popup_patient_birthdate"></span>
                        </div>
                        <div class="patient-info-item">
                            <span class="patient-info-label">Sexe:</span>
                            <span id="popup_patient_gender"></span>
                        </div>
                        <div class="patient-info-item">
                            <span class="patient-info-label">Téléphone:</span>
                            <span id="popup_patient_phone"></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="popup_patient_email">Email du patient(e)</label>
                    <input type="email" id="popup_patient_email" name="patient_email" required>
                </div>
                
                <div class="form-group">
                    <label for="ordonnance_date">Date de l'ordonnance</label>
                    <input type="text" id="ordonnance_date" name="ordonnance_date" value="<?= date('d/m/Y') ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label for="ordonnance_content">Contenu de l'ordonnance</label>
                    <textarea id="ordonnance_content" name="ordonnance_content" required placeholder="Rédigez ici le contenu de l'ordonnance..."></textarea>
                </div>
                
                <div class="popup-actions">
                    <button type="button" class="btn btn-secondary close-popup-btn">Annuler</button>
                    <button type="submit" class="btn btn-primary">Générer et envoyer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Gestion de la popup
        const popup = document.getElementById('ordonnancePopup');
        const closePopup = document.querySelector('.close-popup');
        const closePopupBtn = document.querySelector('.close-popup-btn');
        const createOrdonnanceBtns = document.querySelectorAll('.btn-create-ordonnance');
        const ordonnanceForm = document.getElementById('ordonnanceForm');
        
        // Ouvrir la popup
        createOrdonnanceBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const card = this.closest('.consultation-card');
                
                // Remplir les champs cachés
                document.getElementById('popup_consultation_id').value = card.dataset.consultationId;
                document.getElementById('popup_patient_id').value = card.dataset.patientId;
                
                // Remplir les informations du patient
                document.getElementById('popup_patient_name_display').textContent = card.dataset.patientName;
                document.getElementById('popup_patient_email').value = card.dataset.patientEmail;
                document.getElementById('popup_patient_phone').textContent = card.dataset.patientPhone;
                document.getElementById('popup_patient_birthdate').textContent = card.dataset.patientBirthdate;
                document.getElementById('popup_patient_gender').textContent = card.dataset.patientGender;
                
                popup.style.display = 'flex';
            });
        });
        
        // Fermer la popup
        closePopup.addEventListener('click', () => popup.style.display = 'none');
        closePopupBtn.addEventListener('click', () => popup.style.display = 'none');
        
        // Fermer quand on clique en dehors
        popup.addEventListener('click', (e) => {
            if (e.target === popup) {
                popup.style.display = 'none';
            }
        });
        
        // Soumission du formulaire
        ordonnanceForm.addEventListener('submit', function(e) {
            // Validation supplémentaire si nécessaire
            if (!this.ordonnance_content.value.trim()) {
                e.preventDefault();
                alert('Veuillez saisir le contenu de l\'ordonnance');
                return;
            }
        });
    </script>
</body>
</html>

