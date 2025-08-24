(function($){


    async function reference_caller(value, settings) {

        const send = async (data) => {
            const requestOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            };
            const response = await fetch('/reference/field/filter', requestOptions);
            const result = await response.json();
            return result.result;
        }

        if (value && settings) {
            const result = await send({settings, value});
            return result;
        }
    }

    window.reference_caller = reference_caller;

})(jQuery);