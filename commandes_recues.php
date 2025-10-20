<?php
session_start();
require_once 'login.php';

// Fonction pour vérifier si la requête est AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Vérification de l'authentification
if (!isset($_SESSION['id_utilisateur']) || $_SESSION['type_utilisateur'] !== 'pharmacie') {
    if (isAjaxRequest()) {
        http_response_code(401);
        echo json_encode(['error' => 'Non autorisé']);
        exit;
    } else {
        header('Location: connexion.php');
        exit;
    }
}

$idUtilisateur = $_SESSION['id_utilisateur'];
$message = '';

// Récupération de l'ID pharmacie
try {
    $stmt = $pdo->prepare("SELECT id_pharmacie FROM pharmacie WHERE id_utilisateur = ?");
    $stmt->execute([$idUtilisateur]);
    $pharmacie = $stmt->fetch();
    
    if (!$pharmacie) {
        die("Accès non autorisé - Pharmacie non trouvée");
    }
    
    $idPharmacie = $pharmacie['id_pharmacie'];
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Traitement du changement de statut
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['valider_commande'])) {
        $idCommande = filter_input(INPUT_POST, 'id_commande', FILTER_VALIDATE_INT);
        $nouveauStatut = 'validée';
    } elseif (isset($_POST['annuler_commande'])) {
        $idCommande = filter_input(INPUT_POST, 'id_commande', FILTER_VALIDATE_INT);
        $nouveauStatut = 'annulée';
    }

    if ($idCommande && in_array($nouveauStatut, ['validée', 'annulée'])) {
        try {
            $update = $pdo->prepare("UPDATE commande SET statut = ? 
                                   WHERE id_commande = ? 
                                   AND id_pharmacie = ? 
                                   AND statut = 'en attente'");
            $update->execute([$nouveauStatut, $idCommande, $idPharmacie]);
            
            if ($update->rowCount() > 0) {
                $message = 'Statut de la commande mis à jour.';
                
                if (isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => '<div class="alert alert-success">'.$message.'</div>',
                        'newStatus' => $nouveauStatut,
                        'idCommande' => $idCommande
                    ]);
                    exit;
                }
            } else {
                $message = 'Commande introuvable ou déjà traitée.';
                if (isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'error' => '<div class="alert alert-warning">'.$message.'</div>'
                    ]);
                    exit;
                }
            }
        } catch (PDOException $e) {
            $message = 'Erreur lors de la mise à jour: ' . $e->getMessage();
            if (isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => '<div class="alert alert-danger">'.$message.'</div>'
                ]);
                exit;
            }
        }
    }
}

// Récupération des commandes en attente
$commandes_recue = [];
try {
    $query = $pdo->prepare("SELECT 
                                c.id_commande, 
                                CONCAT(p.nom, ' ', p.prenom) AS patient_nom,
                                c.statut, 
                                c.date_commande, 
                                c.montant_total,
                                GROUP_CONCAT(CONCAT(m.nom, ' (', cm.quantite, ' x ', ROUND(cm.prix_unitaire), ' FCFA)') SEPARATOR '<br>') AS medicaments_detail
                            FROM commande c
                            JOIN patient p ON c.id_patient = p.id_patient
                            JOIN commande_medicament cm ON c.id_commande = cm.id_commande
                            JOIN medicament m ON cm.id_medicament = m.id_medicament
                            WHERE c.id_pharmacie = ? AND c.statut = 'en attente'
                            GROUP BY c.id_commande
                            ORDER BY c.date_commande DESC");
    
    $query->execute([$idPharmacie]);
    $commandes_recue = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Erreur lors de la récupération des commandes: ' . $e->getMessage() . '</div>';
}

// Récupération des infos pharmacie pour l'affichage
try {
    $stmt = $pdo->prepare("SELECT p.id_pharmacie, p.nom, p.telephone, p.adresse, u.email
                          FROM pharmacie p
                          JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
                          WHERE p.id_utilisateur = ?");
    $stmt->execute([$idUtilisateur]);
    $pharmacie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pharmacie) {
        die("Accès non autorisé");
    }
    
    $pharmacie_data = [
        'id' => $pharmacie['id_pharmacie'],
        'nom' => htmlspecialchars($pharmacie['nom']),
        'email' => htmlspecialchars($pharmacie['email']),
        'telephone' => htmlspecialchars($pharmacie['telephone']),
        'adresse' => htmlspecialchars($pharmacie['adresse']),
        'photo' => 'fas fa-prescription-bottle-alt'
    ];
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

if (isAjaxRequest()) {
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes Reçues | Pharmacie</title>
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
        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background: #3498db;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
            margin-right: 15px;
        }

        .back-btn:hover {
            background: #2980b9;
        }

        .back-btn i {
            margin-right: 5px;
        }

        .header-actions {
            display: flex;
            align-items: center;
        }

        /* Menu profil */
        .profile-btn {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .profile-btn:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .profile-icon {
            font-size: 20px;
            margin-right: 8px;
            color: #3498db;
        }

        .profile-name {
            margin-right: 8px;
            font-weight: 500;
        }

        /* Cartes et tableaux */
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .consultations-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .consultations-table thead {
            background:rgb(96, 156, 216);
        }

        .consultations-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color:rgb(241, 241, 241);
            font-size: 14px;
        }

        .consultations-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .consultations-table tr:last-child td {
            border-bottom: none;
        }


.consultations-table td.actions {
    display: flex;
    align-items: center;
    height: 100%;
    padding: 0 15px;
}

.consultations-table td.actions form {
    display: flex;
    align-items: center;
    height: 100%;
    margin: 0;
    padding: 5px 0;
}

.consultations-table td.actions .btn {
    height: calc(100% - 10px);
    margin: 5px 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* Pour les écrans mobiles où les boutons sont en colonne */
@media (max-width: 768px) {
    .consultations-table td.actions {
        flex-direction: column;
        justify-content: center;
    }
    
    .consultations-table td.actions form {
        width: 100%;
    }
    
    .consultations-table td.actions .btn {
        width: 100%;
        height: auto;
        padding: 8px 12px;
    }
}

        /* Colonnes spécifiques */
        .consultations-table th:nth-child(1) { width: 80px; } /* ID */
        .consultations-table th:nth-child(2) { width: 150px; } /* Patient */
        .consultations-table th:nth-child(3) { width: 250px; } /* Médicaments */
        .consultations-table th:nth-child(4) { width: 100px; } /* Total */
        .consultations-table th:nth-child(5) { width: 120px; } /* Date */
        .consultations-table th:nth-child(6) { width: 100px; } /* Statut */
        .consultations-table th:nth-child(7) { width: 180px; } /* Actions */

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

        .status-badge.en-attente {
            background: #fff3e0;
            color: #e65100;
        }

        .status-badge.validee {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.annulee {
            background: #ffebee;
            color: #c62828;
        }

        /* Boutons */
        .btn {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: 12px;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn i {
            margin-right: 5px;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        /* Alertes et messages */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 10px;
            font-size: 20px;
        }

        .alert-info {
            background: #e3f2fd;
            color: #1976d2;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .alert-warning {
            background: #fff3e0;
            color: #e65100;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .consultations-table {
                display: block;
                overflow-x: auto;
            }
            
            .consultations-table th:nth-child(1),
            .consultations-table th:nth-child(2),
            .consultations-table th:nth-child(3),
            .consultations-table th:nth-child(4),
            .consultations-table th:nth-child(5),
            .consultations-table th:nth-child(6),
            .consultations-table th:nth-child(7) {
                width: auto;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }

            .actions {
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            .main-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .back-btn {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="main-header">
                <h1><i class="fas fa-clipboard-list"></i> Commandes Reçues</h1>
                <div class="header-actions">
                    <a href="dashboard_pharmacie.php" class="back-btn"><i class="fas fa-arrow-left"></i> Retour</a>
                    <div class="profile-dropdown">
                        <button class="profile-btn" id="profileBtn">
                            <i class="<?= $pharmacie_data['photo'] ?> profile-icon"></i>
                            <span class="profile-name"><?= htmlspecialchars($pharmacie_data['nom']) ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($message) echo $message; ?>
            
            <?php if (!empty($commandes_recue)) : ?>
                <div class="card">
                    <div class="table-responsive">
                        <table class="consultations-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Médicaments</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commandes_recue as $commande) : ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($commande['id_commande']) ?></td>
                                        <td><?= htmlspecialchars($commande['patient_nom']) ?></td>
                                        <td><?= $commande['medicaments_detail'] ?></td>
                                        <td><?= number_format($commande['montant_total'], 0, ',', ' ') ?> FCFA</td>
                                        <td><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></td>
                                        <td>
                                            <span class="status-badge <?= str_replace('é', 'e', strtolower($commande['statut'])) ?>">
                                                <?= htmlspecialchars($commande['statut']) ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="id_commande" value="<?= $commande['id_commande'] ?>">
                                                <button type="submit" name="valider_commande" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Valider
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="id_commande" value="<?= $commande['id_commande'] ?>">
                                                <button type="submit" name="annuler_commande" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> Annuler
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else : ?>
                <div class="card">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucune commande en attente pour le moment.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>