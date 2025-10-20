<?php
session_start();
require_once 'login.php'; // Inclusion du fichier de connexion

// Vérification de la session
if (!isset($_SESSION['id_utilisateur']) || $_SESSION['type_utilisateur'] !== 'pharmacie') {
    header('Location: connexion.php');
    exit;
}

$idPharmacie = $_SESSION['id_utilisateur']; // Identifiant de la pharmacie

// Traitement des actions
$message = '';
if (isset($_POST['ajouter'])) {
    $idMedicament = $_POST['id_medicament'];
    $quantite = $_POST['quantite_en_stock'];

    $verif = $pdo->prepare("SELECT * FROM stockpharmacie WHERE id_pharmacie = ? AND id_medicament = ?");
    $verif->execute([$idPharmacie, $idMedicament]);

    if ($verif->rowCount() > 0) {
        $update = $pdo->prepare("UPDATE stockpharmacie SET quantite_en_stock = quantite_en_stock + ? WHERE id_pharmacie = ? AND id_medicament = ?");
        $update->execute([$quantite, $idPharmacie, $idMedicament]);
        $message = '<div class="success">Stock mis à jour avec succès</div>';
    } else {
        $insert = $pdo->prepare("INSERT INTO stockpharmacie (id_pharmacie, id_medicament, quantite_en_stock) VALUES (?, ?, ?)");
        $insert->execute([$idPharmacie, $idMedicament, $quantite]);
        $message = '<div class="success">Médicament ajouté au stock</div>';
    }
}

if (isset($_GET['supprimer'])) {
    $idStock = $_GET['supprimer'];
    $delete = $pdo->prepare("DELETE FROM stockpharmacie WHERE id_stock = ? AND id_pharmacie = ?");
    $delete->execute([$idStock, $idPharmacie]);
    $message = '<div class="success">Médicament supprimé du stock</div>';
}

if (isset($_POST['maj_quantite'])) {
    $idStock = $_POST['id_stock'];
    $quantite = $_POST['quantite_en_stock'];
    $update = $pdo->prepare("UPDATE stockpharmacie SET quantite_en_stock = ? WHERE id_stock = ? AND id_pharmacie = ?");
    $update->execute([$quantite, $idStock, $idPharmacie]);
    $message = '<div class="success">Quantité mise à jour</div>';
}

// Récupération des données
$stocks = $pdo->prepare("
    SELECT s.id_stock, m.id_medicament, m.nom, m.description, m.prix, m.image, s.quantite_en_stock
    FROM stockpharmacie s
    JOIN medicament m ON s.id_medicament = m.id_medicament
    WHERE s.id_pharmacie = ?
    ORDER BY m.nom ASC
");
$stocks->execute([$idPharmacie]);
$medicaments_stock = $stocks->fetchAll();

$tous_medicaments = $pdo->query("SELECT * FROM medicament ORDER BY nom ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Stock | Pharmacie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 250px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .header h1 {
            font-size: 24px;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }

        .header h1 i {
            margin-right: 10px;
            color: #3498db;
        }

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
        }

        .back-btn:hover {
            background: #2980b9;
        }

        .back-btn i {
            margin-right: 5px;
        }

        /* Messages */
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .success i {
            margin-right: 10px;
        }

        /* Sections */
        .add-section, .stock-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .add-section h2, .stock-section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }

        .add-section h2 i, .stock-section h2 i {
            margin-right: 10px;
            color: #3498db;
        }

        /* Formulaires */
        .add-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: #7f8c8d;
        }

        .form-group select, 
        .form-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn i {
            margin-right: 5px;
        }

        /* Tableau */
        .table-responsive {
            overflow-x: auto;
        }

        .stock-table {
            width: 100%;
            border-collapse: collapse;
        }

        .stock-table thead {
            background: #f8f9fa;
        }

        .stock-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: #7f8c8d;
            font-size: 14px;
        }

        .stock-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            vertical-align: middle;
        }

        .stock-table tr:last-child td {
            border-bottom: none;
        }

        .medic-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        /* Formulaires dans le tableau */
        .quantity-form {
            display: flex;
            align-items: center;
        }

        .quantity-input {
            width: 70px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-update {
            background: #2ecc71;
            color: white;
        }

        .btn-update:hover {
            background: #27ae60;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
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

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }

            .add-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="sidebar.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <h1><i class="fas fa-pills"></i> Gestion du Stock</h1>
                <a href="dashboard_pharmacie.php" class="back-btn"><i class="fas fa-arrow-left"></i> Retour</a>
            </header>

            <?php if ($message) echo $message; ?>

            <section class="add-section">
                <h2><i class="fas fa-plus-circle"></i> Ajouter un médicament</h2>
                <form method="POST" class="add-form">
                    <div class="form-group">
                        <label for="id_medicament">Médicament</label>
                        <select name="id_medicament" id="id_medicament" required>
                            <option value="">Sélectionnez un médicament</option>
                            <?php foreach ($tous_medicaments as $med) : ?>
                                <option value="<?= $med['id_medicament'] ?>">
                                    <?= htmlspecialchars($med['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="quantite_en_stock">Quantité</label>
                        <input type="number" name="quantite_en_stock" id="quantite_en_stock" min="1" required>
                    </div>

                    <button type="submit" name="ajouter" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </form>
            </section>

            <section class="stock-section">
                <h2><i class="fas fa-boxes"></i> Stock actuel</h2>
                
                <?php if (empty($medicaments_stock)) : ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>Aucun médicament en stock pour le moment</p>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="stock-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Nom</th>
                                    <th>Description</th>
                                    <th>Prix (FCFA)</th>
                                    <th>Quantité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicaments_stock as $med) : ?>
                                    <tr>
                                        <td>
                                            <img src="<?= htmlspecialchars($med['image']) ?>" 
                                                 alt="<?= htmlspecialchars($med['nom']) ?>" 
                                                 class="medic-image">
                                        </td>
                                        <td><?= htmlspecialchars($med['nom']) ?></td>
                                        <td><?= htmlspecialchars($med['description']) ?></td>
                                        <td><?= number_format($med['prix'], 0, ',', ' ') ?></td>
                                        <td>
                                            <form method="POST" class="quantity-form">
                                                <input type="hidden" name="id_stock" value="<?= $med['id_stock'] ?>">
                                                <input type="number" name="quantite_en_stock" 
                                                       value="<?= $med['quantite_en_stock'] ?>" 
                                                       min="0" class="quantity-input">
                                                <button type="submit" name="maj_quantite" class="btn btn-sm btn-update">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <a href="?supprimer=<?= $med['id_stock'] ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Voulez-vous vraiment supprimer ce médicament du stock ?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

    <script>
        // Confirmation avant suppression
        document.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Voulez-vous vraiment supprimer ce médicament du stock ?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>