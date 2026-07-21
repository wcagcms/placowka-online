(() => {
    'use strict';

    const root = document.querySelector('[data-facility-create]');

    if (!root) {
        return;
    }

    const form = root.querySelector('[data-facility-form]');
    const codeInput = root.querySelector('[data-facility-code]');
    const nameInput = root.querySelector('[data-facility-name]');
    const deviceInput = root.querySelector('[data-device-name]');
    const emailInput = root.querySelector('[data-contact-email]');
    const previewCode = root.querySelector('[data-preview-code]');
    const previewName = root.querySelector('[data-preview-name]');
    const previewDevice = root.querySelector('[data-preview-device]');
    const previewEmail = root.querySelector('[data-preview-email]');
    const submitButton = root.querySelector('[data-submit-button]');
    const submitLabel = root.querySelector('[data-submit-label]');

    const normalize = (value) => value.trim();

    const updatePreview = () => {
        if (previewCode && codeInput) {
            previewCode.textContent = normalize(codeInput.value).toUpperCase() || 'KOD';
        }

        if (previewName && nameInput) {
            previewName.textContent = normalize(nameInput.value) || 'Nazwa placówki';
        }

        if (previewDevice && deviceInput) {
            previewDevice.textContent = normalize(deviceInput.value) || 'Pierwsze urządzenie';
        }

        if (previewEmail && emailInput) {
            previewEmail.textContent = normalize(emailInput.value) || 'Nie podano';
        }
    };

    if (codeInput) {
        codeInput.addEventListener('input', () => {
            const selectionStart = codeInput.selectionStart;
            const selectionEnd = codeInput.selectionEnd;
            codeInput.value = codeInput.value.toUpperCase();

            if (selectionStart !== null && selectionEnd !== null) {
                codeInput.setSelectionRange(selectionStart, selectionEnd);
            }

            updatePreview();
        });
    }

    [nameInput, deviceInput, emailInput].forEach((input) => {
        input?.addEventListener('input', updatePreview);
    });

    form?.addEventListener('submit', () => {
        if (!form.checkValidity()) {
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
        }

        if (submitLabel) {
            submitLabel.textContent = 'Tworzenie placówki i paczki…';
        }
    });

    updatePreview();
})();
