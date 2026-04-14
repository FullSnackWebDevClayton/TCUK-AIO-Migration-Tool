(function () {
    const root = document.querySelector('.tcuk-migrator-wrap');
    if (!root) {
        return;
    }

    const deleteButtons = document.querySelectorAll('.tcuk-confirm-delete');
    deleteButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            const proceed = window.confirm('Delete this backup file? This cannot be undone.');
            if (!proceed) {
                event.preventDefault();
            }
        });
    });

    const currentDefaults = {
        activeTheme: root.dataset.activeTheme || ''
    };

    const progressWrap = document.getElementById('tcuk-progress');
    const progressFill = document.getElementById('tcuk-progress-fill');
    const progressText = document.getElementById('tcuk-progress-text');
    const wizardCard = document.getElementById('tcuk-setup-wizard-card');
    const wizardCloseButton = document.getElementById('tcuk-wizard-close');
    const wizardRestoreWrap = document.getElementById('tcuk-wizard-restore-wrap');
    const wizardRestoreButton = document.getElementById('tcuk-wizard-restore');
    const wizardHiddenStorageKey = 'tcukMigratorWizardHidden';

    root.classList.add('tcuk-mode-advanced');
    root.classList.remove('tcuk-mode-simple');

    const fillThemeButtons = document.querySelectorAll('.tcuk-fill-active-theme');
    fillThemeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const themeFields = document.querySelectorAll('.tcuk-theme-slug-field');
            themeFields.forEach((field) => {
                field.value = currentDefaults.activeTheme;
            });
        });
    });

    document.querySelectorAll('.tcuk-theme-slug-field').forEach((field) => {
        if (!field.value.trim() && currentDefaults.activeTheme) {
            field.value = currentDefaults.activeTheme;
        }
    });

    const bindConditionalSection = ({
        scope,
        modeSelector,
        selectedValue,
        dependentSelectors
    }) => {
        const modeField = scope.querySelector(modeSelector);
        if (!modeField) {
            return;
        }

        const update = () => {
            const show = modeField.value === selectedValue;
            dependentSelectors.forEach((selector) => {
                const element = scope.querySelector(selector);
                if (!element) {
                    return;
                }
                element.classList.toggle('tcuk-hidden', !show);
            });
        };

        modeField.addEventListener('change', update);
        update();
    };

    const syncForms = document.querySelectorAll('.tcuk-action-form');
    syncForms.forEach((form) => {
        bindConditionalSection({
            scope: form,
            modeSelector: '.tcuk-plugin-mode-select',
            selectedValue: 'selected',
            dependentSelectors: ['.tcuk-plugin-select-wrap']
        });

        bindConditionalSection({
            scope: form,
            modeSelector: '.tcuk-db-mode-select',
            selectedValue: 'selected',
            dependentSelectors: ['.tcuk-db-groups-wrap', '.tcuk-custom-tables-wrap']
        });
    });

    bindConditionalSection({
        scope: root,
        modeSelector: 'select[name="backup_plugin_mode"]',
        selectedValue: 'selected',
        dependentSelectors: ['.tcuk-backup-plugin-select-wrap']
    });

    bindConditionalSection({
        scope: root,
        modeSelector: 'select[name="backup_db_mode"]',
        selectedValue: 'selected',
        dependentSelectors: ['.tcuk-backup-db-groups-wrap', '.tcuk-backup-custom-tables-wrap']
    });

    const setWizardVisibility = (hidden) => {
        if (!wizardCard || !wizardRestoreWrap) {
            return;
        }

        wizardCard.classList.toggle('tcuk-hidden', hidden);
        wizardRestoreWrap.classList.toggle('tcuk-hidden', !hidden);
    };

    if (wizardCard && wizardCloseButton && wizardRestoreWrap && wizardRestoreButton) {
        let wizardHidden = false;
        try {
            wizardHidden = window.localStorage.getItem(wizardHiddenStorageKey) === '1';
        } catch (error) {
            wizardHidden = false;
        }

        setWizardVisibility(wizardHidden);

        wizardCloseButton.addEventListener('click', () => {
            setWizardVisibility(true);
            try {
                window.localStorage.setItem(wizardHiddenStorageKey, '1');
            } catch (error) {
            }
        });

        wizardRestoreButton.addEventListener('click', () => {
            setWizardVisibility(false);
            try {
                window.localStorage.removeItem(wizardHiddenStorageKey);
            } catch (error) {
            }
        });
    }

    const forms = root.querySelectorAll('form');

    const setProgressState = ({ visible, value, text }) => {
        if (!progressWrap || !progressFill || !progressText) {
            return;
        }

        progressWrap.classList.toggle('tcuk-hidden', !visible);
        progressFill.style.width = `${Math.max(0, Math.min(100, value || 0))}%`;

        if (text) {
            progressText.textContent = text;
        }
    };

    const shouldAsyncSubmit = (form) => {
        const action = form.getAttribute('action') || '';
        const method = (form.getAttribute('method') || 'get').toLowerCase();

        if (method !== 'post') {
            return false;
        }

        if (action.indexOf('admin-post.php') === -1) {
            return false;
        }

        const actionField = form.querySelector('input[name="action"]');
        const actionName = actionField ? (actionField.value || '').trim() : '';

        const asyncActions = new Set([
            'tcuk_migrator_api_push',
            'tcuk_migrator_backup_create',
            'tcuk_migrator_backup_upload',
            'tcuk_migrator_backup_restore'
        ]);

        return asyncActions.has(actionName);
    };

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.tcukFallbackSubmitted === '1') {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitButton || submitButton.disabled) {
                return;
            }

            if (!shouldAsyncSubmit(form)) {
                submitButton.disabled = true;
                return;
            }

            event.preventDefault();

            submitButton.disabled = true;
            submitButton.dataset.originalLabel = submitButton.textContent || submitButton.value || '';

            const originalLabel = submitButton.dataset.originalLabel || 'Processing';
            if (submitButton.tagName === 'BUTTON') {
                submitButton.textContent = 'Processing...';
            } else if (submitButton.tagName === 'INPUT') {
                submitButton.value = 'Processing...';
            }

            const progressLabel = `${originalLabel.replace(/\.\.\.$/, '')} in progress...`;
            setProgressState({ visible: true, value: 8, text: progressLabel });

            let progressValue = 8;
            const progressTimer = window.setInterval(() => {
                progressValue = Math.min(92, progressValue + 3);
                setProgressState({ visible: true, value: progressValue, text: progressLabel });
            }, 700);

            const formData = new FormData(form);
            formData.set('tcuk_async', '1');

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(async (response) => {
                    const payloadText = await response.text();
                    let payloadJson = null;

                    try {
                        payloadJson = JSON.parse(payloadText);
                    } catch (error) {
                        payloadJson = null;
                    }

                    if (payloadJson && payloadJson.data && payloadJson.data.redirect) {
                        return {
                            redirect: payloadJson.data.redirect
                        };
                    }

                    if (response.redirected && response.url) {
                        return {
                            redirect: response.url
                        };
                    }

                    if (response.ok) {
                        return {
                            redirect: window.location.href
                        };
                    }

                    const textSnippet = payloadText ? payloadText.slice(0, 240) : '';
                    throw new Error(textSnippet || `HTTP ${response.status}`);
                })
                .then((result) => {
                    window.clearInterval(progressTimer);
                    setProgressState({ visible: true, value: 100, text: 'Completed. Refreshing results...' });

                    const redirect = result && result.redirect ? result.redirect : window.location.href;
                    window.setTimeout(() => {
                        window.location.href = redirect;
                    }, 150);
                })
                .catch((error) => {
                    window.clearInterval(progressTimer);

                    const messageText = (error && error.message ? String(error.message) : '').toLowerCase();
                    const shouldFallbackToNative =
                        messageText.includes('<!doctype') ||
                        messageText.includes('<html') ||
                        messageText.includes('page not found') ||
                        messageText.includes('404');

                    if (shouldFallbackToNative) {
                        setProgressState({ visible: true, value: 100, text: 'Switching to compatibility mode...' });
                        form.dataset.tcukFallbackSubmitted = '1';
                        window.setTimeout(() => {
                            form.submit();
                        }, 80);
                        return;
                    }

                    const message = error && error.message
                        ? `Request failed: ${error.message}`
                        : 'Request failed. Please retry.';
                    setProgressState({ visible: true, value: 100, text: message });

                    submitButton.disabled = false;
                    const original = submitButton.dataset.originalLabel || '';
                    if (submitButton.tagName === 'BUTTON') {
                        submitButton.textContent = original;
                    } else if (submitButton.tagName === 'INPUT') {
                        submitButton.value = original;
                    }
                });
        });
    });
})();
