(function($){


    function reference_caller(field_id, settings, appender_element) {
        if (field_id && settings && appender_element) {
             $(`#${field_id}`).on('input',async (e) => {
                 const value = e.target.value;
                 if (value.length > 3) {
                     const result = await send({settings, value});
                     if (result.result.length > 0) {
                         appender_element.innerHTML = '';
                         result.result.forEach((item)=>{
                             const div = document.createElement('div');
                             div.classList.add('list-item');

                             if (settings.type === 'user') {
                                 div.innerHTML = item.name;
                                 div.dataset.id = item.uid;
                             }
                             else {
                                 div.innerHTML = item.title;
                                 div.dataset.id = item.nid || item.id || item.fid;
                             }

                             div.addEventListener('click', (e) => {
                                 e.preventDefault();
                                 const id = e.target.dataset.id;
                                 const input = document.querySelector(`#${field_id}`);
                                 input.value = `${id}`;
                                 input.dispatchEvent(new Event('change'));
                                 appender_element.innerHTML = '';
                             });
                             appender_element.appendChild(div);
                         });
                     }
                 }
             });
        }

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
            return result;
        }
    }

    window.reference_caller = reference_caller;

})(jQuery);