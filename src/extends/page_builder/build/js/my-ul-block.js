// Register as a global plugin
grapesjs.plugins.add('ul-block', (editor, opts = {}) => {
    const bm = editor.BlockManager;
    const domc = editor.DomComponents;

    bm.add('custom-ul', {
        label: 'List (UL)',
        category: 'Basic',
        attributes: { class: 'fa fa-list' },
        content: {
            type: 'custom-ul',
            components: [
                { type: 'custom-li', content: 'List Item 1' },
                { type: 'custom-li', content: 'List Item 2' },
                { type: 'custom-li', content: 'List Item 3' }
            ]
        }
    });

    domc.addType('custom-ul', {
        model: {
            defaults: {
                tagName: 'ul',
                draggable: true,
                droppable: true,
                traits: [
                    {
                        type: 'select',
                        name: 'list-style-type',
                        label: 'Bullet Style',
                        options: [
                            { value: 'disc', name: 'Disc' },
                            { value: 'circle', name: 'Circle' },
                            { value: 'square', name: 'Square' },
                            { value: 'none', name: 'None' }
                        ]
                    }
                ],
                style: { 'padding-left': '20px' }
            }
        }
    });

    domc.addType('custom-li', {
        model: {
            defaults: {
                tagName: 'li',
                draggable: 'ul,ol',
                droppable: false,
                // Don't use 'editable' here, it conflicts with drops
                content: 'List Item',
                traits: []
            }
        },
        view: {
            events: {
                dblclick: 'enableEditing'
            },
            enableEditing() {
                this.el.setAttribute('contenteditable', true);
                this.el.focus();

                const stopEditing = () => {
                    this.el.removeAttribute('contenteditable');
                    this.model.set('content', this.el.innerHTML);
                    this.el.removeEventListener('blur', stopEditing);
                };

                this.el.addEventListener('blur', stopEditing);
            }
        }
    });



});
