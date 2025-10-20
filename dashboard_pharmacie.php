<?php
session_start();
require_once 'login.php';

if (!isset($_SESSION['id_utilisateur']) || $_SESSION['type_utilisateur'] !== 'pharmacie') {
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

    // Récupération des infos pharmacie
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

    // Récupération des commandes
    $stmt = $pdo->prepare("
    SELECT 
        c.id_commande,
        c.date_commande,
        c.statut,
        c.montant_total,
        CONCAT(p.nom, ' ', p.prenom) AS patient_nom,
        GROUP_CONCAT(CONCAT(m.nom, ' (', cm.quantite, ' x ', cm.prix_unitaire, ' FCFA)') SEPARATOR ', ') AS medicaments
    FROM commande c
    JOIN patient p ON c.id_patient = p.id_patient
    JOIN commande_medicament cm ON c.id_commande = cm.id_commande
    JOIN medicament m ON cm.id_medicament = m.id_medicament
    WHERE c.id_pharmacie = ? 
    GROUP BY c.id_commande
    ORDER BY c.date_commande DESC
    LIMIT 15
");
$stmt->execute([$pharmacie_data['id']]);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Gestion de la requête AJAX pour les détails de commande
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && isset($_GET['id'])) {
    $orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if ($orderId) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    c.*,
                    CONCAT(p.nom, ' ', p.prenom) AS patient_nom,
                    p.telephone AS patient_telephone,
                    p.adresse AS patient_adresse,
                    GROUP_CONCAT(CONCAT(m.nom, ' (', cm.quantite, ' x ', cm.prix_unitaire, ' FCFA)') SEPARATOR '; ') AS medicaments
                FROM commande c
                JOIN patient p ON c.id_patient = p.id_patient
                JOIN commande_medicament cm ON c.id_commande = cm.id_commande
                JOIN medicament m ON cm.id_medicament = m.id_medicament
                WHERE c.id_commande = ? AND c.id_pharmacie = ?
                GROUP BY c.id_commande
            ");
            $stmt->execute([$orderId, $pharmacie_data['id']]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($commande) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'patient' => $commande['patient_nom'],
                        'telephone' => $commande['patient_telephone'],
                        'adresse' => $commande['patient_adresse'],
                        'date' => date('d/m/Y H:i', strtotime($commande['date_commande'])),
                        'statut' => ucfirst($commande['statut']),
                        'montant' => number_format($commande['montant_total'], 0, ',', ' ') . ' FCFA',
                        'medicaments' => str_replace('; ', '<br>', $commande['medicaments'])
                    ]
                ]);
                exit;
            }
        } catch (PDOException $e) {
            // Erreur SQL
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Commande introuvable']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord | Pharmacie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard_pharmacie.css">
    <link rel="stylesheet" href="sidebar.css">
    <style>
        /* Styles pour la popup */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .popup-overlay.active {
            display: flex;
        }
        
        .popup-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
        }
        
        .popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .close-popup {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .close-popup:hover {
            color: #333;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        
        .order-details p {
            margin-bottom: 10px;
        }
        
        .order-details strong {
            display: inline-block;
            width: 150px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <header class="main-header">
                <h1>Tableau de bord</h1>
                <div class="header-actions">
                    <div class="profile-dropdown">
                        <button class="profile-btn" id="profileBtn">
                            <i class="<?= $pharmacie_data['photo'] ?> profile-icon"></i>
                            <span><?= $pharmacie_data['nom'] ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu" id="profileDropdown">
                            <div class="dropdown-header">
                                <i class="<?= $pharmacie_data['photo'] ?> profile-icon"></i>
                                <div>
                                    <h4><?= $pharmacie_data['nom'] ?></h4>
                                    <p><?= $pharmacie_data['email'] ?></p>
                                </div>
                            </div>
                            <div class="dropdown-body">
                                <p><i class="fas fa-phone"></i> <?= $pharmacie_data['telephone'] ?></p>
                                <p><i class="fas fa-map-marker-alt"></i> <?= $pharmacie_data['adresse'] ?></p>
                            </div>
                            <div class="dropdown-footer">
                                <a href="deconnexion.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="info-message">
                <i class="fas fa-info-circle"></i>
                <p>Bienvenue sur votre tableau de bord. Vous pouvez suivre ici l'état de vos commandes récentes.</p>
            </div>

            <section class="content-section">
                <h2>Commandes récentes</h2>
                <?php if (empty($commandes)): ?>
                    <div class="empty-state">
                        <img src="assets/images/no-data.svg" alt="Aucune commande">
                        <h3>Aucune commande récente</h3>
                        <p>Vous n'avez aucune commande pour le moment. Les nouvelles commandes apparaîtront ici.</p>
                    </div>
                <?php else: ?>
                    <table class="commandes-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Statut</th>
                                
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $cmd): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($cmd['date_commande'])) ?></td>
                                    <td><?= htmlspecialchars($cmd['patient_nom']) ?></td>
                                    <td>
                                        <span class="status-badge <?= str_replace('é', 'e', strtolower($cmd['statut'])) ?>">
                                            <?= ucfirst($cmd['statut']) ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <button class="btn btn-outline view-order-btn" data-id="<?= $cmd['id_commande'] ?>">
                                            <i class="fas fa-eye"></i> Voir
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Popup pour les détails de commande -->
    <div class="popup-overlay" id="orderPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h3>Détails de la commande <span id="orderId"></span></h3>
                <button class="close-popup" id="closePopup">&times;</button>
            </div>
            <div id="orderDetails">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i> Chargement des détails...
                </div>
            </div>
        </div>
    </div>

    <script>
        // Menu profil
        document.getElementById('profileBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('profileDropdown').classList.toggle('show');
        });

        document.addEventListener('click', function() {
            document.getElementById('profileDropdown').classList.remove('show');
        });

        // Gestion de la popup
        const popup = document.getElementById('orderPopup');
        const closeBtn = document.getElementById('closePopup');
        const orderDetails = document.getElementById('orderDetails');
        
        // Ouvrir la popup quand on clique sur "Voir"
        document.querySelectorAll('.view-order-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.getAttribute('data-id');
                document.getElementById('orderId').textContent = '#' + orderId;
                
                // Afficher le loader
                orderDetails.innerHTML = `
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Chargement des détails...
                    </div>
                `;
                
                // Ouvrir la popup
                popup.classList.add('active');
                
                // Charger les détails via AJAX
                fetch(`?ajax=1&id=${orderId}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Erreur réseau');
                        return response.json();
                    })
                    .then(data => {
                        if (data.error) {
                            orderDetails.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    ${data.error}
                                </div>
                            `;
                        } else if (data.success) {
                            const cmd = data.data;
                            orderDetails.innerHTML = `
                                <div class="order-details">
                                    <p><strong>Patient:</strong> ${cmd.patient}</p>
                                    <p><strong>Téléphone:</strong> ${cmd.telephone}</p>
                                    <p><strong>Adresse:</strong> ${cmd.adresse}</p>
                                    <p><strong>Date:</strong> ${cmd.date}</p>
                                    <p><strong>Statut:</strong> ${cmd.statut}</p>
                                    <p><strong>Total:</strong> ${cmd.montant}</p>
                                    <p><strong>Médicaments:</strong></p>
                                    <div style="margin-left: 20px;">${cmd.medicaments}</div>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        orderDetails.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                Une erreur est survenue lors du chargement des détails.
                            </div>
                        `;
                    });
            });
        });
        
        // Fermer la popup
        closeBtn.addEventListener('click', function() {
            popup.classList.remove('active');
        });
        
        // Fermer quand on clique en dehors
        popup.addEventListener('click', function(e) {
            if (e.target === popup) {
                popup.classList.remove('active');
            }
        });
    </script>
</body>
</html>