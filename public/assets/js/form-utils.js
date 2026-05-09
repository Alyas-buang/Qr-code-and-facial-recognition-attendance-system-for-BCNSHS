/**
 * Enhanced Form Utilities
 * Improved form interactions with anime.js animations
 * April 2026
 */

window.FormUtils = (() => {
    /**
     * Add validation styling and animation to form fields
     * @param {HTMLFormElement} form - Form element
     * @param {object} validationRules - Validation rules
     */
    function enableFormValidation(form, validationRules = {}) {
        if (!form) return;

        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            // Real-time validation
            input.addEventListener('blur', () => {
                validateField(input, validationRules[input.name]);
            });

            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) {
                    validateField(input, validationRules[input.name]);
                }
            });

            // Focus animation
            input.addEventListener('focus', () => {
                if (AnimeUtils.isReady()) {
                    anime({
                        targets: input,
                        duration: 300,
                        borderColor: '#2563eb',
                        boxShadow: '0 0 0 3px rgba(37, 99, 235, 0.1)',
                        easing: 'easeOutCubic'
                    });
                }
            });

            input.addEventListener('blur', () => {
                if (AnimeUtils.isReady()) {
                    anime({
                        targets: input,
                        duration: 300,
                        borderColor: '#d1d5db',
                        boxShadow: 'none',
                        easing: 'easeInCubic'
                    });
                }
            });
        });
    }

    /**
     * Validate a single field
     * @param {HTMLElement} field - Form field
     * @param {object} rules - Validation rules
     * @returns {boolean} - Is valid
     */
    function validateField(field, rules = {}) {
        const value = field.value.trim();
        const errorContainer = field.parentElement.querySelector('.error-message');

        // Clear previous state
        field.classList.remove('is-invalid', 'is-valid');
        if (errorContainer) {
            errorContainer.textContent = '';
            errorContainer.style.display = 'none';
        }

        // Required validation
        if (rules.required && !value) {
            setFieldError(field, 'This field is required');
            return false;
        }

        // Email validation
        if (rules.email && value && !isValidEmail(value)) {
            setFieldError(field, 'Please enter a valid email');
            return false;
        }

        // Min length validation
        if (rules.minLength && value.length < rules.minLength) {
            setFieldError(field, `Minimum length is ${rules.minLength} characters`);
            return false;
        }

        // Max length validation
        if (rules.maxLength && value.length > rules.maxLength) {
            setFieldError(field, `Maximum length is ${rules.maxLength} characters`);
            return false;
        }

        // Pattern validation
        if (rules.pattern && value && !rules.pattern.test(value)) {
            setFieldError(field, rules.patternMessage || 'Invalid format');
            return false;
        }

        // Custom validation
        if (rules.custom && !rules.custom(value)) {
            setFieldError(field, rules.customMessage || 'Invalid value');
            return false;
        }

        // Field is valid
        setFieldSuccess(field);
        return true;
    }

    /**
     * Set field error state
     * @param {HTMLElement} field - Form field
     * @param {string} message - Error message
     */
    function setFieldError(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');

        let errorContainer = field.parentElement.querySelector('.error-message');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'error-message text-sm text-error mt-1';
            field.parentElement.appendChild(errorContainer);
        }

        errorContainer.textContent = message;
        errorContainer.style.display = 'block';

        // Animate error
        if (AnimeUtils.isReady()) {
            anime({
                targets: field,
                duration: 300,
                borderColor: '#ef4444',
                boxShadow: '0 0 0 3px rgba(239, 68, 68, 0.1)',
                easing: 'easeOutCubic'
            });

            anime({
                targets: errorContainer,
                duration: 300,
                opacity: [0, 1],
                translateY: [-10, 0],
                easing: 'easeOutCubic'
            });
        }
    }

    /**
     * Set field success state
     * @param {HTMLElement} field - Form field
     */
    function setFieldSuccess(field) {
        field.classList.add('is-valid');
        field.classList.remove('is-invalid');

        const errorContainer = field.parentElement.querySelector('.error-message');
        if (errorContainer) {
            errorContainer.style.display = 'none';
        }

        if (AnimeUtils.isReady()) {
            anime({
                targets: field,
                duration: 300,
                borderColor: '#22c55e',
                easing: 'easeOutCubic'
            });
        }
    }

    /**
     * Validate entire form
     * @param {HTMLFormElement} form - Form element
     * @param {object} validationRules - Validation rules
     * @returns {boolean} - Is form valid
     */
    function validateForm(form, validationRules = {}) {
        if (!form) return false;

        const inputs = form.querySelectorAll('input, textarea, select');
        let isValid = true;

        inputs.forEach(input => {
            if (input.name && validationRules[input.name]) {
                if (!validateField(input, validationRules[input.name])) {
                    isValid = false;
                }
            }
        });

        return isValid;
    }

    /**
     * Submit form with animation
     * @param {HTMLFormElement} form - Form element
     * @param {function} onSubmit - Submit callback
     */
    function onFormSubmit(form, onSubmit) {
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                AnimeUtils.animateButtonLoading(submitBtn, 'Submitting...');
            }

            try {
                await onSubmit();
                if (submitBtn) {
                    AnimeUtils.animateButtonSuccess(submitBtn, 'Submitted!', 1500);
                }
            } catch (error) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit';
                }
                console.error('Form submission error:', error);
            }
        });
    }

    /**
     * Check if email is valid
     * @param {string} email - Email address
     * @returns {boolean}
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Reset form with animation
     * @param {HTMLFormElement} form - Form element
     */
    function resetForm(form) {
        if (!form) return;

        form.reset();
        
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.classList.remove('is-invalid', 'is-valid');
            const errorContainer = input.parentElement.querySelector('.error-message');
            if (errorContainer) {
                errorContainer.style.display = 'none';
            }
        });

        if (AnimeUtils.isReady()) {
            anime({
                targets: form,
                duration: 300,
                opacity: [0.7, 1],
                easing: 'easeOutCubic'
            });
        }
    }

    /**
     * Add file input preview
     * @param {HTMLInputElement} fileInput - File input element
     * @param {HTMLElement} previewContainer - Container for preview
     * @param {object} options - Options (maxSize, allowedTypes, etc.)
     */
    function enableFilePreview(fileInput, previewContainer, options = {}) {
        const maxSize = options.maxSize || 5 * 1024 * 1024; // 5MB
        const allowedTypes = options.allowedTypes || ['image/jpeg', 'image/png', 'image/gif'];

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];

            if (!file) {
                previewContainer.innerHTML = '';
                return;
            }

            // Validate file size
            if (file.size > maxSize) {
                setFieldError(fileInput, `File size must be less than ${maxSize / 1024 / 1024}MB`);
                return;
            }

            // Validate file type
            if (!allowedTypes.includes(file.type)) {
                setFieldError(fileInput, 'File type not allowed');
                return;
            }

            // Create preview
            const reader = new FileReader();
            reader.onload = (event) => {
                previewContainer.innerHTML = `
                    <img src="${event.target.result}" alt="File preview" class="max-w-xs rounded-lg">
                `;

                if (AnimeUtils.isReady()) {
                    anime({
                        targets: previewContainer.querySelector('img'),
                        duration: 500,
                        opacity: [0, 1],
                        scale: [0.8, 1],
                        easing: 'easeOutCubic'
                    });
                }
            };

            reader.readAsDataURL(file);
            setFieldSuccess(fileInput);
        });
    }

    /**
     * Create form group with label
     * @param {string} name - Field name
     * @param {string} label - Field label
     * @param {string} type - Input type
     * @param {object} attributes - Additional attributes
     * @returns {HTMLElement} - Form group element
     */
    function createFormGroup(name, label, type = 'text', attributes = {}) {
        const group = document.createElement('div');
        group.className = 'form-control';

        const labelEl = document.createElement('label');
        labelEl.className = 'label';
        const span = document.createElement('span');
        span.className = 'label-text';
        span.textContent = label;
        labelEl.appendChild(span);

        const input = document.createElement('input');
        input.type = type;
        input.name = name;
        input.className = 'input input-bordered w-full';
        Object.assign(input, attributes);

        const error = document.createElement('div');
        error.className = 'error-message text-sm text-error mt-1';
        error.style.display = 'none';

        group.appendChild(labelEl);
        group.appendChild(input);
        group.appendChild(error);

        return group;
    }

    // Public API
    return {
        enableFormValidation,
        validateField,
        setFieldError,
        setFieldSuccess,
        validateForm,
        onFormSubmit,
        isValidEmail,
        resetForm,
        enableFilePreview,
        createFormGroup
    };
})();
