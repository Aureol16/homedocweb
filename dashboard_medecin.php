<?php
session_start();

// Vérification de la connexion
if (!isset($_SESSION['id_utilisateur'])) {
    header('Location: connexion.php');
    exit;
}

$host = 'localhost';
$dbname = 'dbhomedoc';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupération des infos du médecin
    $stmt = $pdo->prepare("
        SELECT m.id_medecin, m.nom, m.prenom, m.specialite, m.telephone, m.photo_profil, u.email 
        FROM medecin m
        JOIN utilisateur u ON m.id_utilisateur = u.id_utilisateur
        WHERE m.id_utilisateur = ?
    ");
    $stmt->execute([$_SESSION['id_utilisateur']]);
    $medecin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$medecin) die("Accès non autorisé");

    // Formatage des données
    $medecin_data = [
        'id' => $medecin['id_medecin'],
        'nom_complet' => "Dr. " . htmlspecialchars($medecin['prenom']) . " " . htmlspecialchars($medecin['nom']),
        'specialite' => htmlspecialchars($medecin['specialite']),
        'telephone' => htmlspecialchars($medecin['telephone']),
        'email' => htmlspecialchars($medecin['email']),
        // On utilise directement une icône au lieu de l'image
        'photo' => "fas fa-user-md" // Icône Font Awesome pour médecin
    ];

    // Gestion des actions sur les consultations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'], $_POST['id_consultation'])) {
            $action = $_POST['action'];
            $id_consultation = $_POST['id_consultation'];
            
            if (in_array($action, ['confirmer', 'annuler'])) {
                $nouveau_statut = $action === 'confirmer' ? 'confirmé' : 'annulé';
                
                $stmt = $pdo->prepare("
                    UPDATE consultation 
                    SET statut = ? 
                    WHERE id_consultation = ? AND id_medecin = ?
                ");
                $stmt->execute([$nouveau_statut, $id_consultation, $medecin_data['id']]);
                
                header("Location: dashboard_medecin.php");
                exit;
            }
        }
    }

    // Récupération des consultations avec l'adresse de la consultation
    $stmt = $pdo->prepare("
        SELECT 
    c.id_consultation, 
    DATE_FORMAT(c.date_consultation, '%d/%m/%Y') AS date_formatee,
    DATE_FORMAT(c.heure_consultation, '%H:%i') AS heure_formatee,
    c.statut,
    c.urgent,
    c.adresse AS adresse_consultation,
    p.id_patient,
    p.nom AS nom_patient,
    p.prenom AS prenom_patient,
    p.telephone AS telephone_patient,
    p.adresse AS adresse_patient,
    p.date_naissance,
    p.sexe
FROM 
    consultation c
JOIN 
    patient p ON c.id_patient = p.id_patient
WHERE 
    c.id_medecin = ?
ORDER BY 
    c.date_consultation DESC, 
    c.heure_consultation DESC
LIMIT 15;

    ");
    $stmt->execute([$medecin_data['id']]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord | HomeDoc</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard_medecin.css">
    <style>
        /* Ajout du style pour la modale */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 700px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .patient-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        
        .info-group {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
        }
        
        .info-value {
            margin-top: 5px;
        }
        
        .profile-icon {
            
            margin-right: 10px;
            color:rgb(250, 250, 250);
        }
    </style>
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
            <li class="active">
                <a href="dashboard_medecin.php">
                    <i class="fas fa-home"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="consultations_confirmees.php">
                    <i class="fas fa-check-circle"></i> Consultations
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
            <header class="main-header">
                <h1>Tableau de bord</h1>
                
                <div class="header-actions">
                    <div class="profile-dropdown">
                        <button class="profile-btn" id="profileBtn">
                            <i class="<?= $medecin_data['photo'] ?> profile-icon"></i>
                            <span><?= explode(' ', $medecin_data['nom_complet'])[1] ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        
                        <div class="dropdown-menu" id="profileDropdown">
                            <div class="dropdown-header">
                                <i class="<?= $medecin_data['photo'] ?> profile-icon"></i>
                                <div>
                                    <h4><?= $medecin_data['nom_complet'] ?></h4>
                                    <p><?= $medecin_data['specialite'] ?></p>
                                </div>
                            </div>
                            <div class="dropdown-body">
                                <p><i class="fas fa-envelope"></i> <?= $medecin_data['email'] ?></p>
                                <p><i class="fas fa-phone"></i> <?= $medecin_data['telephone'] ?></p>
                            </div>
                            <div class="dropdown-footer">
                                <a href="deconnexion.php" class="logout-btn">
                                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <section class="content-section">
                <h2>Dernières consultations</h2>
                
                <?php if (empty($consultations)): ?>
                    <div class="empty-state">
                        <img src="assets/images/no-data.svg" alt="Aucune donnée">
                        <h3>Aucune consultation récente</h3>
                    </div>
                <?php else: ?>
                    <div class="consultations-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Heure</th>
                                    <th>Patient</th>
                                    <th>Adresse</th>
                                    <th>Contact</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultations as $consultation): ?>
                                    <tr class="<?= $consultation['urgent'] === 'oui' ? 'urgent' : '' ?>">
                                        <td><?= $consultation['date_formatee'] ?></td>
                                        <td><?= $consultation['heure_formatee'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($consultation['prenom_patient'] . ' ' . $consultation['nom_patient']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($consultation['adresse_consultation']) ?></td>
                                        <td>
                                            <a href="tel:<?= htmlspecialchars($consultation['telephone_patient']) ?>">
                                                <i class="fas fa-phone"></i> <?= htmlspecialchars($consultation['telephone_patient']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $consultation['statut'] ?>">
                                                <?= ucfirst($consultation['statut']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($consultation['statut'] === 'en attente'): ?>
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="id_consultation" value="<?= $consultation['id_consultation'] ?>">
                                                    <button type="submit" name="action" value="confirmer" class="btn btn-success">
                                                        <i class="fas fa-check"></i> Confirmer
                                                    </button>
                                                    <button type="submit" name="action" value="annuler" class="btn btn-danger">
                                                        <i class="fas fa-times"></i> Annuler
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-outline view-patient-btn" 
                                                        data-patient-id="<?= $consultation['id_patient'] ?>"
                                                        data-consultation-id="<?= $consultation['id_consultation'] ?>">
                                                    <i class="fas fa-eye"></i> Voir
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modale pour afficher les informations du patient -->
    <div id="patientModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalPatientName"></h2>
            <div class="patient-info-grid">
                <div class="info-group">
                    <div class="info-label">Date de naissance:</div>
                    <div class="info-value" id="modalPatientBirthdate"></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Sexe:</div>
                    <div class="info-value" id="modalPatientSex"></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Groupe sanguin:</div>
                    <div class="info-value" id="modalPatientBloodType"></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Téléphone:</div>
                    <div class="info-value" id="modalPatientPhone"></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Adresse du patient:</div>
                    <div class="info-value" id="modalPatientAddress"></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Adresse de consultation:</div>
                    <div class="info-value" id="modalConsultationAddress"></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Date de consultation:</div>
                    <div class="info-value" id="modalConsultationDate"></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Heure de consultation:</div>
                    <div class="info-value" id="modalConsultationTime"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gestion du dropdown du profil corrigée
        document.addEventListener('DOMContentLoaded', function() {
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            
            // Toggle dropdown
            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });
            
            // Fermer le dropdown si on clique ailleurs
            document.addEventListener('click', function() {
                if (profileDropdown.classList.contains('show')) {
                    profileDropdown.classList.remove('show');
                }
            });
            
            // Empêcher la fermeture quand on clique dans le dropdown
            profileDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Gestion de la modale
            const modal = document.getElementById('patientModal');
            const span = document.getElementsByClassName('close')[0];
            const viewButtons = document.getElementsByClassName('view-patient-btn');
            
            // Stocker les données des patients pour la modale
            const consultationsData = <?= json_encode($consultations) ?>;
            
            // Quand on clique sur un bouton Voir
            Array.from(viewButtons).forEach(button => {
                button.addEventListener('click', function() {
                    const patientId = this.getAttribute('data-patient-id');
                    const consultationId = this.getAttribute('data-consultation-id');
                    
                    // Trouver la consultation correspondante
                    const consultation = consultationsData.find(c => 
                        c.id_patient == patientId && c.id_consultation == consultationId
                    );
                    
                    if (consultation) {
                        // Remplir la modale avec les données
                        document.getElementById('modalPatientName').textContent = 
                            `${consultation.prenom_patient} ${consultation.nom_patient}`;
                        document.getElementById('modalPatientBirthdate').textContent = 
                            consultation.date_naissance;
                        document.getElementById('modalPatientSex').textContent = 
                            consultation.sexe;
                        document.getElementById('modalPatientBloodType').textContent = 
                            consultation.groupe_sanguin || 'Non spécifié';
                        document.getElementById('modalPatientPhone').textContent = 
                            consultation.telephone_patient;
                        document.getElementById('modalPatientAddress').textContent = 
                            consultation.adresse_patient;
                        document.getElementById('modalConsultationAddress').textContent = 
                            consultation.adresse_consultation;
                        document.getElementById('modalConsultationDate').textContent = 
                            consultation.date_formatee;
                        document.getElementById('modalConsultationTime').textContent = 
                            consultation.heure_formatee;
                        
                        // Afficher la modale
                        modal.style.display = 'block';
                    }
                });
            });
            
            // Quand on clique sur la croix pour fermer
            span.onclick = function() {
                modal.style.display = 'none';
            }
            
            // Quand on clique en dehors de la modale
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>