document.addEventListener('DOMContentLoaded', function() {
    // Add any JavaScript functionality needed for the admin interface here
    const form = document.querySelector('.qq-cpc-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = 'Checking...';
            }
        });
    }
});
