(function($){

    $(document).ready(function(){

        function menuBar() {
            const toggler = document.querySelector(".menu-toggle");
            const menu = document.querySelector(".main-menu");

            function handleResize() {
                if (window.innerWidth <= 778) {
                    // Small screen: hide menu by default
                    menu.classList.remove("active");
                } else {
                    // Large screen: show menu, remove inline styles and class
                    menu.classList.remove("active");
                    menu.style.display = ""; // Ensure no leftover inline style
                }
            }

            if (toggler && menu) {
                // Initial resize check
                handleResize();

                // On window resize, adjust menu visibility
                window.addEventListener("resize", handleResize);

                // Toggle menu on button click
                toggler.addEventListener("click", () => {
                    menu.classList.toggle("active");
                });
            }
        }

        menuBar();

    })

})(jQuery);