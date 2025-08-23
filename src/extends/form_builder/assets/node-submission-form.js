document.addEventListener('DOMContentLoaded', function () {
    const forms = $('[data-submission]').find('form');

    if (forms.length > 0) {
        forms.each(function () {
            $(this).on('submit', function (e) {
                e.preventDefault();

                const form = this;
                const formData = new FormData(form);

                fetch(form.action || window.location.href, {
                    method: form.method || 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(response => {
                        alert(response.message);
                        window.location.reload();
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('Error submitting form');
                    });
            });
        });
    }
});
