function attachAutoFilterListener(input_field_id, name) {
        if (input_field_id.length > 1) {
            const field = document.querySelector(`#${input_field_id}`);
            if(field) {
                field.addEventListener('input',(e)=>{
                    if (e.target.value.length > 4) {
                        setTimeout(()=>{
                            filter(e.target.value, name, field);
                        },2000);
                    }
                });
            }
        }
}

const filter = async (value, name, field) =>{
    const response = await fetch(`/user/search/${value}/${name}/auto`);
    const data = await response.json();
    const id = field.id;
    const result_element_wrap = document.getElementById(`filters--${id}`);
    if (data) {

        data.forEach((item)=>{
            const div = document.createElement('div');
            div.textContent = item;
            div.addEventListener('click',(e)=>{
                console.log(item);
            });
            result_element_wrap.appendChild(div);
        });
    }
}
