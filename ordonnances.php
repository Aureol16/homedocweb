<?php
session_start();
require_once 'login.php';

if (!isset($_SESSION['id_utilisateur']) || $_SESSION['type_utilisateur'] !== 'pharmacie') {
    header('Location: connexion.php');
    exit;
}

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

// Récupération des ordonnances
$sql = "SELECT o.id_ordonnance, o.date_ordonnance, o.description, o.statut, 
               CONCAT(p.nom, ' ', p.prenom) AS patient_nom
        FROM ordonnance o
        JOIN patient p ON o.id_patient = p.id_patient
        ORDER BY o.date_ordonnance DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$ordonnances = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordonnances Reçues | Pharmacie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <style>
        /* Reset et styles de base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        /* Layout principal */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 250px;
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .main-header h1 {
            font-size: 24px;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }

        .main-header h1 i {
            margin-right: 10px;
            color: #3498db;
        }

        /* Boutons et actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #3498db;
            color: #3498db;
        }

        .btn-outline:hover {
            background: #3498db;
            color: white;
        }

        /* Menu profil */
        .profile-btn {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .profile-btn:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .profile-icon {
            font-size: 18px;
            margin-right: 8px;
            color: #3498db;
        }

        .dropdown-menu {
            position: absolute;
            right: 20px;
            top: 60px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: 280px;
            z-index: 100;
            display: none;
            overflow: hidden;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-header {
            padding: 15px;
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .dropdown-header i {
            font-size: 30px;
            margin-right: 15px;
            color: #3498db;
        }

        .dropdown-header h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .dropdown-header p {
            font-size: 14px;
            color: #666;
        }

        .dropdown-body {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .dropdown-body p {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .dropdown-body i {
            margin-right: 10px;
            color: #7f8c8d;
            width: 20px;
            text-align: center;
        }

        .dropdown-footer {
            padding: 10px;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
            transition: background 0.3s;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        .dropdown-item i {
            margin-right: 10px;
            color: #7f8c8d;
            width: 20px;
            text-align: center;
        }

        /* Contenu principal */
        .content-section {
            margin-top: 20px;
        }

        /* Alertes */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }

        .alert i {
            margin-right: 10px;
            font-size: 20px;
        }

        .alert-info {
            background: #e3f2fd;
            color: #1976d2;
        }

        /* Cartes */
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        /* Tableau */
        .table-responsive {
            overflow-x: auto;
        }

        .ordonnances-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .ordonnances-table thead {
            background: #f8f9fa;
        }

        .ordonnances-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: #7f8c8d;
            font-size: 14px;
        }

        .ordonnances-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .ordonnances-table tr:last-child td {
            border-bottom: none;
        }

        /* Colonnes spécifiques */
        .ordonnances-table th:nth-child(1) { width: 150px; } /* Patient */
        .ordonnances-table th:nth-child(2) { width: 120px; } /* Date */
        .ordonnances-table th:nth-child(3) { width: 300px; } /* Description */
        .ordonnances-table th:nth-child(4) { width: 100px; } /* Statut */
        .ordonnances-table th:nth-child(5) { width: 150px; } /* Action */

        /* Badges de statut */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status-badge.nouvelle {
            background: #fff3e0;
            color: #e65100;
        }

        .status-badge.traitée {
            background: #e8f5e9;
            color: #2e7d32;
        }

        /* Boutons d'action */
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            background: #3498db;
            color: white;
        }

        .action-btn:hover {
            background: #2980b9;
        }

        .action-btn i {
            margin-right: 5px;
        }

        /* État vide */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #bdc3c7;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }

            .main-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .dropdown-menu {
                right: 10px;
                width: calc(100% - 20px);
            }

            .ordonnances-table {
                display: block;
                overflow-x: auto;
            }
            
            .ordonnances-table th:nth-child(1),
            .ordonnances-table th:nth-child(2),
            .ordonnances-table th:nth-child(3),
            .ordonnances-table th:nth-child(4),
            .ordonnances-table th:nth-child(5) {
                width: auto;
            }
        }

        @media (max-width: 480px) {
            .ordonnances-table {
                font-size: 13px;
            }

            .ordonnances-table th, 
            .ordonnances-table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="main-header">
                <h1><i class="fas fa-file-prescription"></i> Ordonnances Reçues</h1>
                <div class="header-actions">
                    <a href="dashboard_pharmacie.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    <div class="profile-dropdown">
                        <button class="profile-btn" id="profileBtn">
                            <i class="<?= $pharmacie_data['photo'] ?> profile-icon"></i>
                            <span><?= $pharmacie_data['nom'] ?></span>
                        </button>
                        <div class="dropdown-menu" id="profileMenu">
                            <div class="dropdown-header">
                                <i class="<?= $pharmacie_data['photo'] ?>"></i>
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
                                
                                <a href="deconnexion.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content-section">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <p>Liste des ordonnances reçues. Vous pouvez créer une commande à partir des ordonnances non traitées.</p>
                </div>

                <?php if (empty($ordonnances)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-medical"></i>
                        <h3>Aucune ordonnance reçue</h3>
                        <p>Les nouvelles ordonnances apparaîtront ici.</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="ordonnances-table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Statut</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ordonnances as $ordonnance): ?>
                                    <tr data-statut="<?= htmlspecialchars($ordonnance['statut']) ?>">
                                        <td><?= htmlspecialchars($ordonnance['patient_nom']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($ordonnance['date_ordonnance'])) ?></td>
                                        <td><?= nl2br(htmlspecialchars($ordonnance['description'])) ?></td>
                                        <td>
                                            <span class="status-badge <?= str_replace('é', 'e', strtolower($ordonnance['statut'])) ?>">
                                                <?= htmlspecialchars($ordonnance['statut']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($ordonnance['statut'] === 'nouvelle'): ?>
                                                <form method="post" action="traiter_ordonnance.php" style="display: inline;">
                                                    <input type="hidden" name="id_ordonnance" value="<?= $ordonnance['id_ordonnance'] ?>">
                                                    <button type="submit" class="action-btn">
                                                        <i class="fas fa-plus-circle"></i> Créer commande
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">Aucune action</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Gestion du menu profil
        document.getElementById('profileBtn').addEventListener('click', function() {
            document.getElementById('profileMenu').classList.toggle('show');
        });

        // Fermer le menu si on clique ailleurs
        window.addEventListener('click', function(e) {
            if (!e.target.matches('#profileBtn') && !e.target.closest('#profileBtn')) {
                const dropdown = document.getElementById('profileMenu');
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>