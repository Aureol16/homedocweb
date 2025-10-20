document.addEventListener('DOMContentLoaded', function () {
    // Gestion du menu profil
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');

    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            profileMenu.classList.toggle('show');
        });

        document.addEventListener('click', function () {
            profileMenu.classList.remove('show');
        });
    }

    // Gestion des formulaires de changement de statut
    document.querySelectorAll('form[method="POST"]').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const row = this.closest('tr');

            // Envoi de la requête AJAX
            fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.json();
            })
            .then(data => {
                console.log('Réponse du serveur:', data); // Pour déboguer

                if (data.success) {
                    // Mise à jour du statut dans l'interface
                    const statusBadge = row.querySelector('.status-badge');
                    statusBadge.textContent = data.newStatus;
                    statusBadge.className = 'status-badge ' + data.newStatus.toLowerCase().replace('é', 'e');

                    // Désactiver les boutons
                    row.querySelectorAll('button').forEach(btn => {
                        btn.disabled = true;
                    });

                    // Afficher le message de succès
                    showAlert(data.message);

                    // Supprimer la ligne après 2 secondes
                    setTimeout(() => {
                        row.remove();

                        // Recharger la page s’il n’y a plus de lignes
                        if (document.querySelectorAll('tbody tr').length === 0) {
                            location.reload();
                        }
                    }, 2000);
                } else if (data.error) {
                    showAlert(`<div class="alert alert-danger">${data.error}</div>`);
                }
            })
            .catch(error => {
                console.error('Erreur lors de la requête:', error);
                showAlert('<div class="alert alert-danger">Erreur lors de la communication avec le serveur</div>');
            });
        });
    });

    // Fonction pour afficher un message temporaire
    function showAlert(message) {
        // Supprimer les anciens messages
        document.querySelectorAll('.alert').forEach(alert => alert.remove());

        // Créer et afficher le nouveau message
        const alertDiv = document.createElement('div');
        alertDiv.innerHTML = message;
        document.querySelector('.main-content').prepend(alertDiv);

        // Supprimer le message après 5 secondes
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
});
