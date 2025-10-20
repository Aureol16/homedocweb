<?php
echo "<h2>Installation de PHPMailer et TCPDF</h2>";

// Étape 1 : Vérifier que Composer est installé
$composerExists = shell_exec("composer --version");

if (!$composerExists) {
    echo "<p style='color:red;'>❌ Composer n'est pas installé sur ce serveur. Veuillez l'installer depuis : <a href='https://getcomposer.org/download/'>getcomposer.org</a></p>";
    exit;
}

echo "<p>✅ Composer est installé.</p>";

// Étape 2 : Créer composer.json si absent
if (!file_exists('composer.json')) {
    file_put_contents('composer.json', json_encode([
        "require" => new stdClass()
    ], JSON_PRETTY_PRINT));
    echo "<p>📄 Fichier <code>composer.json</code> créé.</p>";
} else {
    echo "<p>📄 Fichier <code>composer.json</code> déjà présent.</p>";
}

// Étape 3 : Installer PHPMailer et TCPDF via Composer
echo "<p>⚙️ Installation de <strong>phpmailer/phpmailer</strong> et <strong>tecnickcom/tcpdf</strong>...</p>";

exec("composer require phpmailer/phpmailer tecnickcom/tcpdf", $output, $returnCode);

if ($returnCode === 0) {
    echo "<p style='color:green;'>✅ PHPMailer et TCPDF ont été installés avec succès.</p>";
} else {
    echo "<p style='color:red;'>❌ Une erreur est survenue lors de l'installation.<br><pre>" . implode("\n", $output) . "</pre></p>";
}

// Étape 4 : Vérifier que le dossier vendor a été créé
if (file_exists('vendor/autoload.php')) {
    echo "<p>✅ Le fichier <code>vendor/autoload.php</code> est prêt. Vous pouvez maintenant inclure les bibliothèques avec <code>require 'vendor/autoload.php';</code></p>";
} else {
    echo "<p style='color:red;'>❌ Le fichier <code>vendor/autoload.php</code> est manquant. Vérifiez les permissions ou réessayez manuellement en ligne de commande.</p>";
}
?>
