// Form Builder JavaScript

class FormBuilder {
    constructor() {
        this.formConfig = {
            name: '',
            attributes: {
                enctype: 'multipart/form-data',
                method: 'POST',
                action: '',
                target: '_self'
            },
            description: '',
            machine_name: '',
            created: new Date().toISOString().slice(0, 19).replace('T', ' ') + ' PM',
            fields: {},
            display_setting: {},
            permission: {},
            storage: {}
        };
        
        this.selectedField = null;
        this.fieldCounter = 0;
        this.storageCounter = 0;
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.showFormSettings();
    }
    
    setupEventListeners() {
        // Drag and drop for field types
        this.setupDragAndDrop();
        
        // Form settings
        this.setupFormSettings();
        
        // Field settings
        this.setupFieldSettings();
        
        // Canvas click
        document.getElementById('form-canvas').addEventListener('click', (e) => {
            if (e.target.id === 'form-canvas' || e.target.classList.contains('canvas-placeholder')) {
                this.selectForm();
            }
        });
        
        // Canvas header click
        document.querySelector('.canvas-header').addEventListener('click', () => {
            this.selectForm();
        });
        
        // Save form button
        document.getElementById('save-form-btn').addEventListener('click', () => {
            this.saveForm();
        });
        
        // Delete field button
        document.getElementById('delete-field-btn').addEventListener('click', () => {
            this.deleteSelectedField();
        });
    }
    
    setupDragAndDrop() {
        const fieldTypes = document.querySelectorAll('.field-type');
        const canvas = document.getElementById('form-canvas');
        
        fieldTypes.forEach(fieldType => {
            fieldType.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', fieldType.dataset.type);
                fieldType.style.opacity = '0.5';
            });
            
            fieldType.addEventListener('dragend', (e) => {
                fieldType.style.opacity = '1';
            });
        });
        
        canvas.addEventListener('dragover', (e) => {
            e.preventDefault();
            canvas.classList.add('drag-over');
        });
        
        canvas.addEventListener('dragleave', (e) => {
            if (!canvas.contains(e.relatedTarget)) {
                canvas.classList.remove('drag-over');
            }
        });
        
        canvas.addEventListener('drop', (e) => {
            e.preventDefault();
            canvas.classList.remove('drag-over');
            
            const fieldType = e.dataTransfer.getData('text/plain');
            this.addField(fieldType);
        });
    }
    
    setupFormSettings() {
        const formName = document.getElementById('form-name');
        const formDesc = document.getElementById('form-desc');
        const formMachineName = document.getElementById('form-machine-name');
        const formAction = document.getElementById('form-action');
        const formMethod = document.getElementById('form-method');
        const formEnctype = document.getElementById('form-enctype');
        const formTarget = document.getElementById('form-target');
        
        formName.addEventListener('input', (e) => {
            this.formConfig.name = e.target.value;
            document.getElementById('form-title').textContent = e.target.value || 'Untitled Form';
            
            // Auto-generate machine name
            if (e.target.value && !formMachineName.value) {
                const machineName = 'content_' + e.target.value.toLowerCase()
                    .replace(/[^a-z0-9]/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '');
                formMachineName.value = machineName;
                this.formConfig.machine_name = machineName;
            }
        });
        
        formDesc.addEventListener('input', (e) => {
            this.formConfig.description = e.target.value;
            document.getElementById('form-description').textContent = e.target.value || 'Click on the form to edit settings';
        });
        
        formMachineName.addEventListener('input', (e) => {
            this.formConfig.machine_name = e.target.value;
        });

        formAction.addEventListener('input', (e) => {
            this.formConfig.attributes.action = e.target.value;
        })

        formMethod.addEventListener('change', (e) => {
            this.formConfig.attributes.method = e.target.value;
        })
        formEnctype.addEventListener('input', (e) => {
            this.formConfig.attributes.enctype = e.target.value;
        })

        formTarget.addEventListener('input', (e) => {
            this.formConfig.attributes.target = e.target.value;
        })
    }
    
    setupFieldSettings() {
        const inputs = [
            'field-label', 'field-name', 'field-id', 'field-class', 'field-default',
            'field-required', 'field-limit', 'allowed-file-types', 'allowed-file-size',
            'textarea-rows', 'textarea-cols', 'select-options', 'checkbox-options',
            'reference-type', 'reference-entity'
        ];
        
        inputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('input', () => {
                    this.updateSelectedField();
                });
                
                if (input.type === 'checkbox') {
                    input.addEventListener('change', () => {
                        this.updateSelectedField();
                    });
                }
            }
        });
    }
    
    addField(type) {
        this.fieldCounter++;
        const fieldId = `field_${this.fieldCounter}`;
        const fieldName = `${this.formConfig.machine_name || 'form'}_field_${type}_${this.fieldCounter}`;
        
        // Remove placeholder if it exists
        const placeholder = document.querySelector('.canvas-placeholder');
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        
        // Create field configuration
        const fieldConfig = this.createFieldConfig(type, fieldName);
        this.formConfig.fields[fieldName] = fieldConfig;
        
        // Create display setting
        this.formConfig.display_setting[fieldName] = {
            display_label: "1",
            display_as: this.getDisplayAs(type),
            display_enabled: "1"
        };
        
        // Create storage entry
        this.formConfig.storage[this.storageCounter] = `node__${fieldName}`;
        this.storageCounter++;
        
        // Create DOM element
        const fieldElement = this.createFieldElement(fieldId, fieldConfig, type);
        document.getElementById('form-canvas').appendChild(fieldElement);
        
        // Select the new field
        this.selectField(fieldElement, fieldName);
        
        // Add animation class
        fieldElement.classList.add('new');
        setTimeout(() => fieldElement.classList.remove('new'), 300);
    }
    
    createFieldConfig(type, fieldName) {
        const baseConfig = {
            label: this.getDefaultLabel(type),
            type: type,
            name: fieldName,
            id: "",
            class: [""],
            default_value: "",
            required: false,
            handler: this.getHandler(type),
            limit: 1
        };
        
        // Add type-specific configurations
        switch (type) {
            case 'file':
                baseConfig.settings = {
                    allowed_file_types: ["image/*"],
                    allowed_file_size: "6000000"
                };
                break;
            case 'textarea':
                baseConfig.options = {
                    rows: "10",
                    cols: "9"
                };
                break;
            case 'select':
                baseConfig.option_values = ["Option 1", "Option 2", "Option 3"];
                break;
            case 'checkbox':
                baseConfig.checkboxes = ["Yes", "No"];
                break;
            case 'reference':
                baseConfig.reference = {
                    type: "node",
                    reference_entity: "content_type"
                };
                break;
        }
        
        return baseConfig;
    }
    
    createFieldElement(fieldId, fieldConfig, type) {
        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'form-field';
        fieldDiv.dataset.fieldId = fieldId;
        fieldDiv.dataset.fieldName = fieldConfig.name;
        
        const label = document.createElement('div');
        label.className = 'field-label';
        label.innerHTML = `
            ${fieldConfig.label}
            ${fieldConfig.required ? '<span class="required-indicator">*</span>' : ''}
        `;
        
        const input = this.createFieldInput(type, fieldConfig);
        
        const badge = document.createElement('div');
        badge.className = 'field-type-badge';
        badge.textContent = type.charAt(0).toUpperCase() + type.slice(1);
        
        fieldDiv.appendChild(label);
        fieldDiv.appendChild(input);
        fieldDiv.appendChild(badge);
        
        fieldDiv.addEventListener('click', (e) => {
            e.stopPropagation();
            this.selectField(fieldDiv, fieldConfig.name);
        });
        
        return fieldDiv;
    }
    
    createFieldInput(type, fieldConfig) {
        const input = document.createElement('input');
        input.className = 'field-input';
        input.placeholder = `Enter ${fieldConfig.label.toLowerCase()}`;
        
        switch (type) {
            case 'text':
                input.type = 'text';
                break;
            case 'number':
                input.type = 'number';
                break;
            case 'date':
                input.type = 'date';
                break;
            case 'file':
                input.type = 'file';
                break;
            case 'textarea':
                const textarea = document.createElement('textarea');
                textarea.className = 'field-input';
                textarea.rows = fieldConfig.options?.rows || 3;
                textarea.placeholder = `Enter ${fieldConfig.label.toLowerCase()}`;
                return textarea;
            case 'select':
                const select = document.createElement('select');
                select.className = 'field-input';
                const defaultOption = document.createElement('option');
                defaultOption.textContent = `Select ${fieldConfig.label.toLowerCase()}`;
                select.appendChild(defaultOption);
                
                if (fieldConfig.option_values) {
                    fieldConfig.option_values.forEach(option => {
                        const optionElement = document.createElement('option');
                        optionElement.value = option;
                        optionElement.textContent = option;
                        select.appendChild(optionElement);
                    });
                }
                return select;
            case 'checkbox':
                const checkboxContainer = document.createElement('div');
                checkboxContainer.className = 'field-input';
                
                if (fieldConfig.checkboxes) {
                    fieldConfig.checkboxes.forEach(option => {
                        const label = document.createElement('label');
                        label.style.display = 'flex';
                        label.style.alignItems = 'center';
                        label.style.gap = '0.5rem';
                        label.style.marginBottom = '0.5rem';
                        
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.value = option;
                        
                        const span = document.createElement('span');
                        span.textContent = option;
                        
                        label.appendChild(checkbox);
                        label.appendChild(span);
                        checkboxContainer.appendChild(label);
                    });
                }
                return checkboxContainer;
            case 'reference':
                input.type = 'text';
                input.placeholder = `Select ${fieldConfig.label.toLowerCase()}`;
                break;
            default:
                input.type = 'text';
        }
        
        return input;
    }
    
    selectField(fieldElement, fieldName) {
        // Remove previous selection
        document.querySelectorAll('.form-field.selected').forEach(field => {
            field.classList.remove('selected');
        });
        
        // Select current field
        fieldElement.classList.add('selected');
        this.selectedField = fieldName;
        
        // Show field settings
        this.showFieldSettings(fieldName);
    }
    
    selectForm() {
        // Remove field selection
        document.querySelectorAll('.form-field.selected').forEach(field => {
            field.classList.remove('selected');
        });
        
        this.selectedField = null;
        this.showFormSettings();
    }
    
    showFormSettings() {
        document.getElementById('settings-title').textContent = 'Form Settings';
        document.getElementById('form-settings').classList.add('active');
        document.getElementById('field-settings').classList.remove('active');
        
        // Populate form settings
        document.getElementById('form-name').value = this.formConfig.name;
        document.getElementById('form-desc').value = this.formConfig.description;
        document.getElementById('form-machine-name').value = this.formConfig.machine_name;
        document.getElementById('form-action').value = this.formConfig.attributes.action;
        document.getElementById('form-method').value = this.formConfig.attributes.method;
        document.getElementById('form-enctype').value = this.formConfig.attributes.enctype;
        document.getElementById('form-target').value = this.formConfig.attributes.target;

    }
    
    showFieldSettings(fieldName) {
        const fieldConfig = this.formConfig.fields[fieldName];
        if (!fieldConfig) return;
        
        document.getElementById('settings-title').textContent = 'Field Settings';
        document.getElementById('form-settings').classList.remove('active');
        document.getElementById('field-settings').classList.add('active');
        
        // Hide all type-specific settings
        document.querySelectorAll('.type-settings').forEach(setting => {
            setting.classList.remove('active');
        });
        
        // Show relevant type-specific settings
        const typeSettings = document.getElementById(`${fieldConfig.type}-settings`);
        if (typeSettings) {
            typeSettings.classList.add('active');
        }
        
        // Populate field settings
        document.getElementById('field-label').value = fieldConfig.label || '';
        document.getElementById('field-name').value = fieldConfig.name || '';
        document.getElementById('field-id').value = fieldConfig.id || '';
        document.getElementById('field-class').value = (fieldConfig.class || []).join(' ');
        document.getElementById('field-default').value = fieldConfig.default_value || '';
        document.getElementById('field-required').checked = fieldConfig.required || false;
        document.getElementById('field-limit').value = fieldConfig.limit || 1;
        
        // Populate type-specific settings
        this.populateTypeSpecificSettings(fieldConfig);
    }
    
    populateTypeSpecificSettings(fieldConfig) {
        switch (fieldConfig.type) {
            case 'file':
                if (fieldConfig.settings) {
                    document.getElementById('allowed-file-types').value = 
                        (fieldConfig.settings.allowed_file_types || []).join(', ');
                    document.getElementById('allowed-file-size').value = 
                        fieldConfig.settings.allowed_file_size || '';
                }
                break;
            case 'textarea':
                if (fieldConfig.options) {
                    document.getElementById('textarea-rows').value = fieldConfig.options.rows || 10;
                    document.getElementById('textarea-cols').value = fieldConfig.options.cols || 9;
                }
                break;
            case 'select':
                document.getElementById('select-options').value = 
                    (fieldConfig.option_values || []).join('\\n');
                break;
            case 'checkbox':
                document.getElementById('checkbox-options').value = 
                    (fieldConfig.checkboxes || []).join('\\n');
                break;
            case 'reference':
                if (fieldConfig.reference) {
                    document.getElementById('reference-type').value = fieldConfig.reference.type || 'node';
                    document.getElementById('reference-entity').value = fieldConfig.reference.reference_entity || '';
                }
                break;
        }
    }
    
    updateSelectedField() {
        if (!this.selectedField) return;
        
        const fieldConfig = this.formConfig.fields[this.selectedField];
        if (!fieldConfig) return;
        
        // Update basic settings
        fieldConfig.label = document.getElementById('field-label').value;
        fieldConfig.name = document.getElementById('field-name').value;
        fieldConfig.id = document.getElementById('field-id').value;
        fieldConfig.class = document.getElementById('field-class').value.split(' ').filter(c => c.trim());
        fieldConfig.default_value = document.getElementById('field-default').value;
        fieldConfig.required = document.getElementById('field-required').checked;
        fieldConfig.limit = parseInt(document.getElementById('field-limit').value) || 1;
        
        // Update type-specific settings
        this.updateTypeSpecificSettings(fieldConfig);
        
        // Update DOM element
        this.updateFieldElement(fieldConfig);
        
        // Update storage if name changed
        if (fieldConfig.name !== this.selectedField) {
            // Update storage entries
            Object.keys(this.formConfig.storage).forEach(key => {
                if (this.formConfig.storage[key] === `node__${this.selectedField}`) {
                    this.formConfig.storage[key] = `node__${fieldConfig.name}`;
                }
            });
            
            // Update display settings
            if (this.formConfig.display_setting[this.selectedField]) {
                this.formConfig.display_setting[fieldConfig.name] = this.formConfig.display_setting[this.selectedField];
                delete this.formConfig.display_setting[this.selectedField];
            }
            
            // Update fields object
            this.formConfig.fields[fieldConfig.name] = fieldConfig;
            delete this.formConfig.fields[this.selectedField];
            
            // Update selected field reference
            this.selectedField = fieldConfig.name;
            
            // Update DOM element dataset
            const fieldElement = document.querySelector(`[data-field-name="${this.selectedField}"]`);
            if (fieldElement) {
                fieldElement.dataset.fieldName = fieldConfig.name;
            }
        }
    }
    
    updateTypeSpecificSettings(fieldConfig) {
        switch (fieldConfig.type) {
            case 'file':
                if (!fieldConfig.settings) fieldConfig.settings = {};
                const fileTypes = document.getElementById('allowed-file-types').value;
                fieldConfig.settings.allowed_file_types = fileTypes ? 
                    fileTypes.split(',').map(t => t.trim()) : [];
                fieldConfig.settings.allowed_file_size = document.getElementById('allowed-file-size').value;
                break;
            case 'textarea':
                if (!fieldConfig.options) fieldConfig.options = {};
                fieldConfig.options.rows = document.getElementById('textarea-rows').value;
                fieldConfig.options.cols = document.getElementById('textarea-cols').value;
                break;
            case 'select':
                const selectOptions = document.getElementById('select-options').value;
                fieldConfig.option_values = selectOptions ? 
                    selectOptions.split('\\n').map(o => o.trim()).filter(o => o) : [];
                break;
            case 'checkbox':
                const checkboxOptions = document.getElementById('checkbox-options').value;
                fieldConfig.checkboxes = checkboxOptions ? 
                    checkboxOptions.split('\\n').map(o => o.trim()).filter(o => o) : [];
                break;
            case 'reference':
                if (!fieldConfig.reference) fieldConfig.reference = {};
                fieldConfig.reference.type = document.getElementById('reference-type').value;
                fieldConfig.reference.reference_entity = document.getElementById('reference-entity').value;
                break;
        }
    }
    
    updateFieldElement(fieldConfig) {
        const fieldElement = document.querySelector(`[data-field-name="${this.selectedField}"]`);
        if (!fieldElement) return;
        
        // Update label
        const labelElement = fieldElement.querySelector('.field-label');
        labelElement.innerHTML = `
            ${fieldConfig.label}
            ${fieldConfig.required ? '<span class="required-indicator">*</span>' : ''}
        `;
        
        // Update input if needed (for select and checkbox types)
        if (fieldConfig.type === 'select' || fieldConfig.type === 'checkbox') {
            const oldInput = fieldElement.querySelector('.field-input');
            const newInput = this.createFieldInput(fieldConfig.type, fieldConfig);
            fieldElement.replaceChild(newInput, oldInput);
        }
    }
    
    deleteSelectedField() {
        if (!this.selectedField) return;
        
        // Remove from configuration
        delete this.formConfig.fields[this.selectedField];
        delete this.formConfig.display_setting[this.selectedField];
        
        // Remove from storage
        Object.keys(this.formConfig.storage).forEach(key => {
            if (this.formConfig.storage[key] === `node__${this.selectedField}`) {
                delete this.formConfig.storage[key];
            }
        });
        
        // Remove DOM element
        const fieldElement = document.querySelector(`[data-field-name="${this.selectedField}"]`);
        if (fieldElement) {
            fieldElement.remove();
        }
        
        // Show placeholder if no fields left
        const remainingFields = document.querySelectorAll('.form-field');
        if (remainingFields.length === 0) {
            document.querySelector('.canvas-placeholder').style.display = 'flex';
        }
        
        // Reset selection
        this.selectedField = null;
        this.showFormSettings();
    }
    
    async saveForm() {
        // Show loading overlay
        document.getElementById('loading-overlay').classList.add('active');
        
        try {
            const response = await fetch('/admin/form-builder/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(this.formConfig)
            });
            
            if (response.ok) {
                // Success feedback
                this.showNotification('Form saved successfully!', 'success');
            } else {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
        } catch (error) {
            console.error('Error saving form:', error);
            this.showNotification('Error saving form. Please try again.', 'error');
        } finally {
            // Hide loading overlay
            document.getElementById('loading-overlay').classList.remove('active');
        }
    }
    
    showNotification(message, type) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#48bb78' : '#e53e3e'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 1001;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            animation: slideInRight 0.3s ease;
        `;
        
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.remove();
            style.remove();
        }, 3000);
    }
    
    // Helper methods
    getDefaultLabel(type) {
        const labels = {
            text: 'Text Field',
            number: 'Number Field',
            date: 'Date Field',
            file: 'File Upload',
            textarea: 'Text Area',
            select: 'Select Field',
            checkbox: 'Checkbox Field',
            reference: 'Reference Field'
        };
        return labels[type] || 'Field';
    }
    
    getHandler(type) {
        const handlers = {
            text: 'Simp\\\\Default\\\\BasicField',
            number: 'Simp\\\\Default\\\\BasicField',
            date: 'Simp\\\\Default\\\\BasicField',
            file: 'Simp\\\\Default\\\\FileField',
            textarea: 'Simp\\\\Default\\\\TextAreaField',
            select: 'Simp\\\\Default\\\\SelectField',
            checkbox: 'Simp\\\\Default\\\\CheckboxField',
            reference: 'Simp\\\\Core\\\\components\\\\reference_field\\\\ReferenceField'
        };
        return handlers[type] || 'Simp\\\\Default\\\\BasicField';
    }
    
    getDisplayAs(type) {
        const displayAs = {
            text: 'p',
            number: 'p',
            date: 'p',
            file: 'link',
            textarea: 'p',
            select: 'p',
            checkbox: 'p',
            reference: 'reference'
        };
        return displayAs[type] || 'p';
    }
}

// Initialize the form builder when the page loads
document.addEventListener('DOMContentLoaded', () => {
    new FormBuilder();
});

