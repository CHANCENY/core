(function ($){

    $(document).ready(function(){

        function addOption() {
            let optionsWrapper = $('.option-wrapper');
            if (optionsWrapper) {
                optionsWrapper.find('.add-option').off('click').on('click', function(e){
                    const collections = optionsWrapper.find('.option-collection');
                    const clone = collections.find('.form-group').first().clone();
                    console.log(clone);
                    clone.find('input').val('');
                    collections.append(clone);
                    clone.find('a').removeClass('d-none')
                    clone.find('a').off('click').on('click', function(e){
                        e.preventDefault();
                        $(this).parent().remove();
                    })

                })
            }
        }
        addOption();
        setInterval(addOption, 1000);

    })

})(jQuery)