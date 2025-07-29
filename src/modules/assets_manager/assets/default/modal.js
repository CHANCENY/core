(function () {

    function modal(modal, openModal, closeModal) {

        if (modal && openModal && closeModal) {
            openModal.onclick = function() { modal.style.display = "block"; }
            closeModal.onclick = function() { modal.style.display = "none"; }
            window.onclick = function(event) { if (event.target === modal) modal.style.display = "none"; }
        }
    }
    window.modal = modal;
})()