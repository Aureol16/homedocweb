<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Assure-toi d'avoir installé PHPMailer via Composer

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultation_id   = $_POST['consultation_id'];
    $patient_id        = $_POST['patient_id'];
    $patient_email     = $_POST['patient_email'];
    $ordonnance_date   = $_POST['ordonnance_date'];
    $ordonnance_contenu = nl2br(htmlspecialchars($_POST['ordonnance_content']));

    if (empty($patient_email)) {
        die("Adresse e-mail du patient manquante.");
    }

    // Préparation du contenu du mail
    $sujet = "Votre ordonnance - HomeDoc";
    $messageHTML = "
        <h2>Ordonnance du {$ordonnance_date}</h2>
        <p><strong>Consultation N°:</strong> {$consultation_id}</p>
        <p><strong>Contenu de l'ordonnance :</strong></p>
        <div style='border: 1px solid #ccc; padding: 10px; margin-top: 10px;'>{$ordonnance_contenu}</div>
        <p style='margin-top: 20px;'>Cordialement, <br> L'équipe HomeDoc</p>
    ";

    try {
        $mail = new PHPMailer(true);
        $mail->Host       = 'smtp.gmail.com';  // Serveur SMTP de Gmail
        $mail->SMTPAuth   = true;
        $mail->Username   = 'kingstonaureol@gmail.com';  // Ton email Gmail
        $mail->Password   = 'ton-mot-de-passe-app'; // Mot de passe spécifique à l'application Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;  // Port pour STARTTLS
        
        $mail->setFrom('ton@email.com', 'HomeDoc');
        $mail->addAddress($patient_email);

        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $messageHTML;

        $mail->send();
        echo "<script>alert('Ordonnance envoyée avec succès au patient.'); window.location.href = 'consultations_confirmees.php';</script>";
    } catch (Exception $e) {
        echo "Erreur lors de l'envoi de l'email : {$mail->ErrorInfo}";
    }
} else {
    header("Location: consultations_confirmees.php");
    exit;
}
