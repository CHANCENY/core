document.addEventListener('DOMContentLoaded', ()=>{
    init();

    if (document.querySelectorAll('.tab')) {
        document.querySelectorAll('.tab').forEach((el)=>{
            el.addEventListener('click', ()=>{
                setTimeout(()=>{ init(); },1500)
            });
        })
    }
});

function init() {
    let active_display = document.querySelector('.tab-content.active');
    window.modal(document.querySelector('#view-modal'),document.querySelector('#add-display'),document.querySelector('#close'))

    if (active_display) {
        window.modal(document.querySelector('#add-field-modal'),active_display.querySelector('#add-field'),document.querySelector('#close-field'))

        window.modal(document.querySelector('#add-field-modal'),active_display.querySelector('#add-field-filter'),document.querySelector('#close-field'))
        window.modal(document.querySelector('#add-field-modal'),active_display.querySelector('#add-field-sort'),document.querySelector('#close-field'));

        if (document.querySelector('#view_field')) {
            document.querySelector('#view_field').addEventListener('change',(e)=>addField(e.target));
        }
    }

    if (active_display) {
        new Sortable(active_display.querySelector('#fields'), {
            animation: 150,
            ghostClass: 'blue-background-class'
        });

        new Sortable(active_display.querySelector('#filter_criteria'), {
            animation: 150,
            ghostClass: 'blue-background-class'
        });

        new Sortable(active_display.querySelector('#filter_criteria'), {
            animation: 150,
            ghostClass: 'blue-background-class'
        });
    }

    let data = {};

    function addField(e) {
        const value = e.value;
        if (value.length > 0) {
            const type = window.location.hash.substring(1);
            const list_v = value.split('|');
            const list = type.split('-');
            const form_data = new FormData(e.parentElement.parentElement);
            data = {
                field: list_v[1],
                type: list[0],
                view: form_data.get('view'),
                display: list[1],
                content_type: list_v[0],
            };
        }
    }

    const send = async (data) => {
        const requestOptions = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        };

        const response = await fetch(window.location.href, requestOptions);
        const result = await response.json();
        console.log(result);
        if (result.hasOwnProperty('result') && result.result === true) {
            window.location.reload();
        }
    }

    if (active_display) {

        if (document.querySelector('#add-field-btn')) {
            document.querySelector('#add-field-btn').addEventListener('click', (e) => {
                e.preventDefault();
                send(data);
            })
        }

        if (active_display.querySelector('#save-change')) {
            active_display.querySelector('#save-change').addEventListener('click', (e) => {
                e.preventDefault();
                const list = Array.from(active_display.querySelectorAll('.list-item > .configure')).map((item) => {
                    return item.dataset.field;
                });

                const reorder_changes = {
                    'fields': [],
                    'sort_criteria': [],
                    'filter_criteria': [],
                };

                if (list.length > 0) {

                    list.forEach((item)=>{
                        const line = item.split('|');
                        reorder_changes[line[0]].push(`${ line[1] }|${line[2]}`);
                    });

                }

                const others = {
                    more_display_settings: {
                        default_empty : active_display.querySelector('#empty_default').value,
                        limit: active_display.querySelector('#limit').value,
                        pagination: active_display.querySelector('#pagination').value,
                        custom_params: active_display.querySelector('#custom_params').value,
                        template_id: active_display.querySelector('#template_id').value,
                        display_style: active_display.querySelector('#display_style').value,
                    }
                };

                send({
                    reorder: reorder_changes,
                    display: active_display.id,
                    ...others,
                });
            })
        }

        if (active_display.querySelectorAll('.list-item > .configure')) {
            Array.from(active_display.querySelectorAll('.list-item > .configure')).forEach((item) => {
                window.modal(document.querySelector('#setting-modal'),item, document.querySelector('#setting-modal').querySelector('#close-field'));
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const modal = document.querySelector('#setting-modal');
                    modal.querySelector('div > form > .wrapper').innerHTML = '';
                    const display = JSON.parse(item.dataset.display);
                    const field_all = JSON.parse(item.dataset.field_all);
                    const view = JSON.parse(item.dataset.view);

                    const div_group = document.createElement('div');
                    div_group.classList.add('form-group');

                    if (display.type === 'fields') {

                        const title_modal = (field_all ? field_all.name : display.field);
                        modal.querySelector('div > .modal-header').textContent = 'Settings for field ' + title_modal;
                        const exclude = document.createElement('input');
                        exclude.type = 'checkbox';
                        exclude.name = 'exclude';
                        exclude.value = '1';

                        exclude.className = 'form-check-input';
                        let label = document.createElement('label');
                        label.innerText = `Exclude field from display`;

                        let clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(label);
                        clone_group.appendChild(exclude);

                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);

                        let default_field = document.createElement('input');
                        default_field.type = 'hidden';
                        default_field.name = 'target';
                        default_field.value = 'fields|'+ display.content_type+ '|' + display.field;
                        clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(default_field);
                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);

                        default_field = document.createElement('input');
                        default_field.type = 'hidden';
                        default_field.name = 'display_name';
                        default_field.value = display.display;
                        clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(default_field);
                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);
                    }

                    else if (display.type === 'filter_criteria') {

                        const title_modal = (field_all ? field_all.name : display.field);
                        modal.querySelector('div > .modal-header').textContent = 'Filter Setting ' + title_modal;

                        let select = document.createElement('select');
                        select.name = 'conjunction';
                        select.className = 'form-control';
                        let options = ['AND', 'OR'];
                        options.forEach((item)=>{
                            let option = document.createElement('option');
                            option.value = item;
                            option.innerText = item;
                            select.appendChild(option);
                        });
                        let label = document.createElement('label');
                        label.innerText = `Conjunction operator`;

                        let clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(label);
                        clone_group.appendChild(select);
                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);

                        select = document.createElement('select');
                        select.name = 'param_name';
                        select.className = 'form-control';
                        options = [];
                        view.displays.forEach((item)=> {
                            item.params.forEach((param)=>{
                                options.push(param);
                            })
                        });
                        options.forEach((item)=>{
                            let option = document.createElement('option');
                            option.value = item;
                            option.innerText = item;
                            select.appendChild(option);
                        });
                        label = document.createElement('label');
                        label.innerText = `Parameter Placeholder`;

                        clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(label);
                        clone_group.appendChild(select);
                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);

                        let default_field = document.createElement('input');
                        default_field.type = 'hidden';
                        default_field.name = 'target';
                        default_field.value = 'filter_criteria|'+ display.content_type+ '|' + display.field;
                        clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(default_field);
                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);

                        default_field = document.createElement('input');
                        default_field.type = 'hidden';
                        default_field.name = 'display_name';
                        default_field.value = display.display;
                        clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(default_field);
                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);
                    }

                    else if (display.type === 'sort_criteria') {

                        const title_modal = (field_all ? field_all.name : display.field);
                        modal.querySelector('div > .modal-header').textContent = 'Order By Setting ' + title_modal;

                        let select = document.createElement('select');
                        select.name = 'order_in';
                        select.className = 'form-control';
                        let options = ['DESC', 'ASC'];

                        options.forEach((item)=>{
                            let option = document.createElement('option');
                            option.value = item;
                            option.innerText = item;
                            select.appendChild(option);
                        });
                        let label = document.createElement('label');
                        label.innerText = `Order By`;

                        let clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(label);
                        clone_group.appendChild(select);
                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);

                        let default_field = document.createElement('input');
                        default_field.type = 'hidden';
                        default_field.name = 'target';
                        default_field.value = 'sort_criteria|'+ display.content_type+ '|' + display.field;
                        clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(default_field);
                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);

                        default_field = document.createElement('input');
                        default_field.type = 'hidden';
                        default_field.name = 'display_name';
                        default_field.value = display.display;
                        clone_group = div_group.cloneNode(true);
                        clone_group.appendChild(default_field);
                        modal.querySelector('div > form > .wrapper').appendChild(clone_group);
                    }

                })
            })
        }

        if (document.querySelector('#settings-field-btn')) {

            document.querySelector('#settings-field-btn').addEventListener('click', (e) => {
                e.preventDefault();
                const form =  document.querySelector('#settings-field-btn').parentElement.parentElement;
                const form_data = new FormData(form);
                let data = {};
                const target = form_data.get('target');
                data.target = target;
                data.display_name = form_data.get('display_name');
                const split = target.split('|');

                if (split[0] === 'filter_criteria') {

                    data.settings = {
                        conjunction: form_data.get('conjunction'),
                        param_name: form_data.get('param_name'),
                        field: split[2],
                    };
                }
                else if (split[0] === 'sort_criteria') {
                    data.settings = {
                        order_in: form_data.get('order_in'),
                        field: split[2],
                    }
                }
                else if (split[0] === 'fields') {
                    data.settings = {
                        exclude: form_data.get('exclude'),
                        default: form_data.get('default'),
                        display_template_field: form_data.get('display_template_field'),
                    }
                }

                send({setting:'settings', data:data})
            })
        }

    }

    if (active_display.querySelector('#cancel')) {

        active_display.querySelector('#cancel').addEventListener('click', (e)=>{
            e.preventDefault();
            const display_name = active_display.querySelector('#cancel').dataset.display;
            if (display_name) {
                send({delete: true, display_name: display_name});
            }
        });
    }

    if (active_display.querySelectorAll('.remove')) {
        Array.from(active_display.querySelectorAll('.remove')).forEach((item)=>{
            item.addEventListener('click', (e)=>{
                e.preventDefault();
                const field = item.parentElement.parentElement.dataset.field;
                const display =JSON.parse( item.parentElement.parentElement.dataset.display).display;
                const data = {
                    field: field,
                    display_name: display,
                }

                send({delete_field_setting: true, data});
            })
        })
    }

}

