class WikiEditor {
    constructor() {
        // Parse noscript config
        this.config = this.parseNoscript();
        this.container = document.getElementById('wiki-content');
        this.editFullBtn = document.getElementById('edit-full-btn');
        this.saveBar = null;
        this.statusSelect = null;

        // Modal elements
        this.modal = document.getElementById('editor-modal');
        this.modalFields = document.getElementById('modal-fields');
        this.modalForm = document.getElementById('modal-form');
        this.modalTitle = document.getElementById('modal-title');
        this.modalSaveBtn = document.getElementById('modal-save-btn');
        this.modalCloseBtn = document.getElementById('modal-close-btn');

        this.init();
    }

    parseNoscript() {
        const noscript = document.querySelector('noscript');
        if (!noscript) return {};
        let config = {};
        try {
            config = JSON.parse(noscript.textContent);
        } catch (e) {
            console.error('Invalid noscript JSON', e);
        }
        noscript.remove();
        return config;
    }

    init() {
        if (!this.container || !this.config) return;

        // Disable editing if not allowed
        if (!this.config.editable) return;

        // Enable double-click editing for elements with text, anchors, images, videos
        this.container.addEventListener('dblclick', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.editElement(e.target);
        });

        // Full content edit button
        if (this.editFullBtn) {
            this.editFullBtn.addEventListener('click', () => this.editFullContent());
        }

        // Initialize save bar
        this.addSaveUI();

        // Modal close
        this.modalCloseBtn.addEventListener('click', () => this.hideModal());
    }

    editElement(el) {
        let fields = {};

        if (el.tagName === 'A') {
            fields = {
                text: el.textContent,
                href: el.getAttribute('href') || '',
                title: el.getAttribute('title') || '',
                rel: el.getAttribute('rel') || ''
            };
        } else if (el.tagName === 'IMG' || el.tagName === 'VIDEO') {
            fields = {
                src: el.getAttribute('src') || '',
                width: el.getAttribute('width') || '',
                height: el.getAttribute('height') || ''
            };
        } else if (el.textContent.trim()) {
            fields = { text: el.textContent };
        } else {
            return; // Not editable
        }

        this.showModal('Edit Element', fields, (formData) => {
            if (el.tagName === 'A') {
                el.textContent = formData.text;
                el.setAttribute('href', formData.href);
                el.setAttribute('title', formData.title);
                el.setAttribute('rel', formData.rel);
            } else if (el.tagName === 'IMG' || el.tagName === 'VIDEO') {
                el.setAttribute('src', formData.src);
                el.setAttribute('width', formData.width);
                el.setAttribute('height', formData.height);
            } else {
                el.textContent = formData.text || formData.textarea || '';
            }
            this.markDirty();
        });
    }

    editFullContent() {
        const currentHTML = this.container.innerHTML;
        this.showModal('Edit Full Content', { textarea: currentHTML }, (formData) => {
            this.container.innerHTML = formData.textarea || formData.text || '';
            console.log(formData);
            this.markDirty();
        }, true);
    }

    addSaveUI() {
        this.saveBar = document.createElement('div');
        this.saveBar.className = 'wiki-save-bar';
        this.saveBar.style.display = 'none';

        // Status select
        this.statusSelect = document.createElement('select');
        ['published', 'draft', 'archived'].forEach(st => {
            const opt = document.createElement('option');
            opt.value = st.toUpperCase();
            opt.textContent = st;
            this.statusSelect.appendChild(opt);
        });

        const saveBtn = document.createElement('button');
        saveBtn.textContent = 'Save Changes';
        saveBtn.addEventListener('click', () => this.saveChanges());

        this.saveBar.appendChild(this.statusSelect);
        this.saveBar.appendChild(saveBtn);

        this.container.parentNode.insertBefore(this.saveBar, this.container.nextSibling);
    }

    markDirty() {
        if (this.saveBar) this.saveBar.style.display = 'block';
    }

    saveChanges() {
        console.log(this.config);
        const payload = {
            wiki_id: this.config.wiki_id,
            author: this.config.author,
            status: this.statusSelect.value || 'PUBLISHED',
            content: this.container.innerHTML
        };

        fetch('/wiki/revision/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                alert('Changes saved successfully!');
                this.saveBar.style.display = 'none';
            })
            .catch(err => {
                console.error(err);
                alert('Failed to save changes');
            });
    }

    showModal(title, fieldsObj, onSave, isFullContent = false) {
        this.modalTitle.textContent = title;
        this.modalFields.innerHTML = '';

        if (isFullContent) {
            const textarea = document.createElement('textarea');
            textarea.name = 'textarea';
            textarea.value = fieldsObj.textarea || '';
            textarea.style.width = '100%';
            textarea.style.height = '400px';
            textarea.classList.add('editor');
            this.modalFields.appendChild(textarea);
            window.initCkEditor(textarea)
        } else {
            for (const [k, v] of Object.entries(fieldsObj)) {
                const label = document.createElement('label');
                label.textContent = k + ': ';
                const input = document.createElement('input');
                input.type = 'text';
                input.name = k;
                input.value = v;
                input.style.width = '100%';
                label.appendChild(input);
                this.modalFields.appendChild(label);
                this.modalFields.appendChild(document.createElement('br'));
            }
        }

        this.modalForm.onsubmit = (e) => {
            e.preventDefault();
            const formData = Object.fromEntries(new FormData(this.modalForm).entries());
            onSave(formData);
            this.hideModal();
        };

        this.modal.classList.remove('hidden');
    }

    hideModal() {
        this.modal.classList.add('hidden');
        this.modalForm.onsubmit = null;
    }
}

document.addEventListener('DOMContentLoaded', () => new WikiEditor());
