(function () {
    console.log('tcuk-admin: script loaded');
    const root = document.querySelector('.tcuk-migrator-wrap');
    if (!root) {
        console.log('tcuk-admin: root element .tcuk-migrator-wrap not found; aborting');
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

    // Delegate clicks on submit buttons that target external forms (via `form` attr)
    document.addEventListener('click', (ev) => {
        try {
            const btn = ev.target.closest && ev.target.closest('button[type="submit"].tcuk-submit');
            if (!btn) return;
            const formId = btn.getAttribute('form');
            if (!formId) return;
            const targetForm = document.getElementById(formId);
            if (!targetForm) return;

            // Prevent default and programmatically submit so our handlers run
            ev.preventDefault();
            console.debug('tcuk-admin: delegated submit button clicked for form', formId);
            if (typeof targetForm.requestSubmit === 'function') {
                try {
                    // Pass the clicked button as the submitter so the submit event has event.submitter
                    targetForm.requestSubmit(btn);
                } catch (e) {
                    try {
                        // Older browsers: inject a temporary hidden submit button and click it
                        const temp = document.createElement('button');
                        temp.type = 'submit';
                        temp.style.display = 'none';
                        targetForm.appendChild(temp);
                        temp.click();
                        temp.remove();
                    } catch (ee) {
                        targetForm.submit();
                    }
                }
            } else {
                targetForm.submit();
            }
        } catch (e) {
            console.warn('tcuk-admin: delegated click handler error', e);
        }
    }, false);

    const currentDefaults = {
        activeTheme: root.dataset.activeTheme || ''
    };

    // ensure a global toast helper is available for all admin actions
    const TCUK = window.TCUK = window.TCUK || {};
    if (!TCUK.toast) {
        // msg: string, type: 'info'|'success'|'error', duration: ms (optional)
        TCUK.toast = (msg, type = 'info', duration = 8000) => {
            try {
                const toast = document.createElement('div');
                toast.className = 'tcuk-toast tcuk-toast-' + type;

                // Allow multi-line messages and small manual close
                const close = document.createElement('button');
                close.type = 'button';
                close.className = 'tcuk-toast-close';
                close.textContent = '×';
                close.addEventListener('click', () => {
                    toast.classList.remove('visible');
                    setTimeout(() => toast.remove(), 300);
                });

                const content = document.createElement('div');
                content.className = 'tcuk-toast-content';
                content.textContent = msg;

                toast.appendChild(close);
                toast.appendChild(content);

                document.body.appendChild(toast);
                // show
                setTimeout(() => toast.classList.add('visible'), 10);

                // auto-hide
                setTimeout(() => {
                    try { toast.classList.remove('visible'); } catch (e) {}
                    setTimeout(() => { try { toast.remove(); } catch (e) {} }, 300);
                }, Math.max(2000, Number(duration) || 8000));
            } catch (e) {
                console.log(type.toUpperCase() + ': ' + msg);
            }
        };
    }

    
    (function bindDirBrowser() {
        console.log('tcuk-admin: bindDirBrowser init');

        const doBind = () => {
            console.log('tcuk-admin: binding browse controls');

            const browseBtn = document.getElementById('tcuk-browse-btn');
            const wpcontentPathField = document.getElementById('tcuk-wpcontent-path');
            const dirBrowser = document.getElementById('tcuk-dir-browser');

            const TCUK = window.TCUK = window.TCUK || {};
            if (!TCUK.toast) {
                TCUK.toast = (msg, type = 'info', duration = 8000) => {
                    const toast = document.createElement('div');
                    toast.className = 'tcuk-toast tcuk-toast-' + type;

                    const close = document.createElement('button');
                    close.type = 'button';
                    close.className = 'tcuk-toast-close';
                    close.textContent = '×';
                    close.addEventListener('click', () => {
                        toast.classList.remove('visible');
                        setTimeout(() => toast.remove(), 300);
                    });

                    const content = document.createElement('div');
                    content.className = 'tcuk-toast-content';
                    content.textContent = msg;

                    toast.appendChild(close);
                    toast.appendChild(content);
                    document.body.appendChild(toast);
                    setTimeout(() => toast.classList.add('visible'), 10);
                    setTimeout(() => { toast.classList.remove('visible'); setTimeout(() => toast.remove(), 300); }, Math.max(2000, Number(duration) || 8000));
                };
            }

            // keep `#tcuk-dir-browser` in the DOM where the template placed it

            TCUK.renderDirectory = (data) => {
                if (!dirBrowser) return;
                dirBrowser.innerHTML = '';
                dirBrowser.classList.add('tcuk-dir-panel-open');
                const list = document.createElement('ul');
                list.className = 'tcuk-dir-list';

                const header = document.createElement('li');
                header.className = 'tcuk-dir-item tcuk-dir-header';
                header.innerHTML = '<label><input type="checkbox" class="tcuk-select-all"> Select All</label><span class="tcuk-dir-name">Name</span><span class="tcuk-dir-type">Type</span>';
                list.appendChild(header);

                (data.items || []).forEach((it) => {
                    const li = document.createElement('li');
                    li.className = 'tcuk-dir-item';
                    li.innerHTML = `<label><input type="checkbox" class="tcuk-item-checkbox" data-name="${it.name}"></label><span class="tcuk-dir-name">${it.name}</span><span class="tcuk-dir-type">${it.type}</span>`;
                    list.appendChild(li);
                });

                dirBrowser.appendChild(list);
                // mark anchor open for styling
                const anchor = wpcontentPathField.parentElement;
                if (anchor) {
                    anchor.classList.add('tcuk-dir-open');
                }

                const selectAll = dirBrowser.querySelector('.tcuk-select-all');
                if (selectAll) {
                    selectAll.addEventListener('change', (e) => {
                        const checked = !!e.target.checked;
                        dirBrowser.querySelectorAll('.tcuk-item-checkbox').forEach(cb => { cb.checked = checked; });
                    });
                }
                // close behavior: clicking outside should close
            
                setTimeout(() => {
                    document.addEventListener('click', function _outsideClick(ev) {
                        if (!dirBrowser) return document.removeEventListener('click', _outsideClick);
                        if (!dirBrowser.contains(ev.target) && ev.target !== browseBtn && ev.target !== wpcontentPathField) {
                            dirBrowser.innerHTML = '';
                            const anchor = wpcontentPathField.parentElement;
                            if (anchor) anchor.classList.remove('tcuk-dir-open');
                            document.removeEventListener('click', _outsideClick);
                        }
                    });
                }, 20);
            };

            if (!browseBtn || !wpcontentPathField) {
                console.log('tcuk-admin: browseBtn or path field not found', { browseBtn: !!browseBtn, pathField: !!wpcontentPathField });
                // temporary visible fallback to help debugging for missing elements
                if (!browseBtn) {
                    console.warn('tcuk-admin: browse button missing - showing alert fallback');
                }
                return;
            }
            // change the Browse button to a Check button for wp-content detection
            try {
                browseBtn.textContent = 'Check';
            } catch (e) {}

            const analyzeItems = (items) => {
                const names = new Set((items || []).map(i => String(i.name || '').toLowerCase()));
                const found = {
                    themes: names.has('themes'),
                    plugins: names.has('plugins'),
                    uploads: names.has('uploads'),
                    muplugins: names.has('mu-plugins') || names.has('mup-plugins') || names.has('mu_plugins')
                };
                return found;
            };

            const onBrowseClick = (e) => {
                console.log('tcuk-admin: check clicked');
                e.preventDefault();
                const path = (wpcontentPathField.value || '').trim();
                if (!path) {
                    TCUK.toast('Please enter a remote path to check', 'error');
                    return;
                }

                TCUK.toast('Checking remote path...');

                const ajaxUrl = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.ajax_url) ? tcukMigratorAjax.ajax_url : window.location.origin + '/wp-admin/admin-ajax.php';
                const ajaxNonce = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.nonce) ? tcukMigratorAjax.nonce : '';

                const formData = new FormData();
                formData.append('action', 'tcuk_list_directory');
                formData.append('nonce', ajaxNonce);
                formData.append('path', path);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(r => r.json()).then((payload) => {
                    console.debug('tcuk-admin: list_directory response', payload);
                    if (payload && payload.success && payload.data) {
                        const found = analyzeItems(payload.data.items || []);
                        const required = ['themes','plugins','uploads'];
                        const missing = required.filter(k => !found[k]);
                        if (missing.length === 0) {
                            const parts = [];
                            if (found.muplugins) parts.push('mu-plugins');
                            TCUK.toast('Looks like wp-content (' + (parts.length ? parts.join(', ') : 'core dirs found') + ')', 'success');
                        } else {
                            TCUK.toast('Not wp-content — missing: ' + missing.join(', '), 'error');
                        }
                    } else {
                        TCUK.toast((payload && payload.data && payload.data.message) ? payload.data.message : 'Unable to inspect path', 'error');
                    }
                }).catch((err) => {
                    console.error('tcuk-admin: list_directory fetch error', err);
                    TCUK.toast('Request failed', 'error');
                });
            };

            // attach handler
            if (browseBtn.addEventListener) {
                browseBtn.addEventListener('click', onBrowseClick);
            } else if (browseBtn.attachEvent) {
                browseBtn.attachEvent('onclick', onBrowseClick);
            } else {
                browseBtn.onclick = onBrowseClick;
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', doBind);
        } else {
            // DOM already ready
            doBind();
        }
    })();

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

    const scrollTables = root.querySelectorAll('.tcuk-table-scroll');
    const updateScrollCue = (element) => {
        const hasOverflow = (element.scrollWidth - element.clientWidth) > 8;
        const canScrollRight = element.scrollLeft < (element.scrollWidth - element.clientWidth - 8);

        element.classList.toggle('tcuk-scroll-cue', hasOverflow && canScrollRight);
    };

    scrollTables.forEach((element) => {
        updateScrollCue(element);
        element.addEventListener('scroll', () => updateScrollCue(element), { passive: true });
    });

    window.addEventListener('resize', () => {
        scrollTables.forEach((element) => updateScrollCue(element));
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

    const connectionForm = root.querySelector('.tcuk-connection-form');
    // Target forms that should inherit connection settings when submitted
    const syncSettingsForms = root.querySelectorAll('.tcuk-sync-settings-form, .tcuk-action-form');

    const appendSyncedField = (targetForm, name, value) => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = value;
        hidden.dataset.tcukSynced = '1';
        targetForm.appendChild(hidden);
    };

    syncSettingsForms.forEach((targetForm) => {
        targetForm.addEventListener('submit', () => {
            if (!connectionForm) {
                return;
            }

            targetForm.querySelectorAll('[data-tcuk-synced="1"]').forEach((field) => field.remove());

            const sourceFields = connectionForm.querySelectorAll('input, select, textarea');
            sourceFields.forEach((field) => {
                const name = field.name || '';
                if (!name || field.disabled) {
                    return;
                }

                if (['action', 'settings_scope', '_wpnonce', '_wp_http_referer', 'tcuk_async'].includes(name)) {
                    return;
                }

                const type = (field.type || '').toLowerCase();
                if (['submit', 'button', 'file', 'image', 'reset'].includes(type)) {
                    return;
                }

                if (field.tagName === 'SELECT' && field.multiple) {
                    Array.from(field.selectedOptions || []).forEach((option) => {
                        appendSyncedField(targetForm, name, option.value || '');
                    });
                    return;
                }

                if (type === 'checkbox' || type === 'radio') {
                    if (!field.checked) {
                        return;
                    }

                    appendSyncedField(targetForm, name, field.value || '1');
                    return;
                }

                appendSyncedField(targetForm, name, field.value || '');
            });
        });
    });

    const forms = root.querySelectorAll('form');

    // Replace SSH Pull filename input with a populated <select> so users can choose remote backups
    (function replaceSshPullFileInput() {
        try {
            const actionInput = root.querySelector('input[name="action"][value="tcuk_migrator_ssh_pull"]');
            if (!actionInput) return;
            const pullForm = actionInput.closest('form');
            if (!pullForm) return;

            const fileInput = pullForm.querySelector('input[name="ssh_remote_backup_file"]');
            if (!fileInput) return;

            const select = document.createElement('select');
            select.name = 'ssh_remote_backup_file';
            select.className = fileInput.className || 'widefat';
            select.id = 'tcuk-ssh-remote-backup-select';

            fileInput.parentElement.replaceChild(select, fileInput);

            // Add a small refresh control
            const refreshBtn = document.createElement('button');
            refreshBtn.type = 'button';
            refreshBtn.className = 'button';
            refreshBtn.style.marginLeft = '8px';
            refreshBtn.textContent = 'Refresh list';

            select.parentElement.appendChild(refreshBtn);

            const getRemoteDir = () => {
                const hub = document.getElementById('tcuk-wpcontent-path');
                return hub ? (hub.value || '') : '';
            };

            refreshBtn.addEventListener('click', () => populateRemoteBackupSelect(select, getRemoteDir(), pullForm));

            // Populate initially
            populateRemoteBackupSelect(select, getRemoteDir(), pullForm);
        } catch (e) {
            console.warn('tcuk-admin: replaceSshPullFileInput error', e);
        }
    })();

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
        // Treat explicit action forms as AJAX-first
        if (form.classList && form.classList.contains('tcuk-action-form')) {
            return true;
        }

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
            'tcuk_migrator_save_settings',
            'tcuk_migrator_api_push',
            'tcuk_migrator_ssh_push',
            'tcuk_migrator_ssh_pull',
            'tcuk_migrator_backup_create',
            'tcuk_migrator_backup_upload',
            'tcuk_migrator_backup_restore',
            'tcuk_migrator_backup_delete',
            'tcuk_migrator_github_pull',
            'tcuk_migrator_setup_wizard',
            'tcuk_migrator_remote_api_test',
            'tcuk_migrator_ssh_test',
            'tcuk_migrator_github_test',
            'tcuk_migrator_repair_fse'
        ]);

        // Special-case: license settings are saved via `tcuk_migrator_save_settings` with settings_scope='license'
        if (actionName === 'tcuk_migrator_save_settings') {
            const scopeField = form.querySelector('input[name="settings_scope"]');
            const scope = scopeField ? (scopeField.value || '').trim() : '';
            // Only treat license-scope saves as async by default; full settings saves may still redirect
            if (scope === 'license') {
                return true;
            }
        }

        return asyncActions.has(actionName);
    };

    // Populate SSH Pull backup select list from remote via AJAX
    function populateRemoteBackupSelect(selectEl, remoteDir, formEl) {
        if (!selectEl) return;

        const ajaxUrl = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.ajax_url) ? tcukMigratorAjax.ajax_url : window.location.origin + '/wp-admin/admin-ajax.php';
        const ajaxNonce = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.nonce) ? tcukMigratorAjax.nonce : '';

        const attemptPaths = [];
        const base = (remoteDir || '').replace(/\/+$/,'');
        attemptPaths.push(base || '');
        if (base) {
            attemptPaths.push(base + '/uploads/tcuk-migrator-backups');
            attemptPaths.push(base + '/tcuk-migrator-backups');
            attemptPaths.push(base + '/uploads/tcuk-migrator-backups');
            attemptPaths.push(base + '/uploads');
        }
        // Also try common absolute locations
        attemptPaths.push('/wp-content/uploads/tcuk-migrator-backups');
        attemptPaths.push('/uploads/tcuk-migrator-backups');
        attemptPaths.push('/tcuk-migrator-backups');

        selectEl.disabled = true;
        selectEl.innerHTML = '<option>Loading...</option>';

        const tryNext = (index) => {
            if (index >= attemptPaths.length) {
                selectEl.disabled = false;
                selectEl.innerHTML = '<option value="">No backups found</option>';
                return;
            }

            const path = attemptPaths[index] || '';
            const fd = new FormData();
            fd.append('action', 'tcuk_list_directory');
            fd.append('nonce', ajaxNonce);
            fd.append('path', path);

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then((payload) => {
                    if (!payload || !payload.success || !payload.data) {
                        // try next
                        return tryNext(index + 1);
                    }

                    const items = (payload.data.items || []).filter(it => {
                        const name = (it.name || '').toString();
                        return /tcuk-backup-.*\.zip$/i.test(name) && (it.type || 'file') === 'file';
                    });

                    if (!items || items.length === 0) {
                        return tryNext(index + 1);
                    }

                    items.sort((a, b) => (b.mtime || 0) - (a.mtime || 0));

                    const opts = [];
                    opts.push(`<option value="">Latest available (auto)</option>`);
                    items.forEach(it => {
                        const name = it.name || '';
                        const ts = (it.display_mtime && it.display_mtime.length) ? it.display_mtime : (it.iso_mtime ? new Date(it.iso_mtime).toLocaleString() : (it.mtime ? new Date((it.mtime|0) * 1000).toLocaleString() : ''));
                        opts.push(`<option value="${name}">${name}${ts ? ' — ' + ts : ''}</option>`);
                    });

                    selectEl.disabled = false;
                    selectEl.innerHTML = opts.join('');

                    // remember which remote path produced these results and inform the server
                    try {
                        selectEl.dataset.remotePath = path || '';
                        if (formEl) {
                            let hidden = formEl.querySelector('input[name="ssh_remote_backup_dir_override"]');
                            if (!hidden) {
                                hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = 'ssh_remote_backup_dir_override';
                                formEl.appendChild(hidden);
                            }
                            hidden.value = path || '';
                        }
                    } catch (e) {
                        // ignore
                    }
                })
                .catch((err) => {
                    console.error('tcuk-admin: list_directory fetch error', err);
                    // try next path on network error
                    tryNext(index + 1);
                });
        };

        tryNext(0);
    };

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            try {
                const af = form.querySelector('input[name="action"]');
                const an = af ? (af.value || '').trim() : ''; 
                const shouldAsync = shouldAsyncSubmit(form);
                const nonceField = form.querySelector('input[name="_wpnonce"]');
                console.debug('tcuk-admin: submit event', { action: an, shouldAsync: shouldAsync, hasNonce: !!nonceField });
            } catch (e) {
                console.warn('tcuk-admin: submit debug error', e);
            }

            if (form.dataset.tcukFallbackSubmitted === '1') {
                return;
            }

            const submitButton = (event && event.submitter) ? event.submitter : form.querySelector('button[type="submit"], input[type="submit"]');
            const hasSubmitButton = !!submitButton;

            if (hasSubmitButton && submitButton.disabled) {
                return;
            }

            if (!shouldAsyncSubmit(form)) {
                if (hasSubmitButton) {
                    submitButton.disabled = true;
                }
                return;
            }

            event.preventDefault();

            if (hasSubmitButton) {
                submitButton.disabled = true;
                submitButton.dataset.originalLabel = submitButton.textContent || submitButton.value || '';
            }

            const originalLabel = hasSubmitButton ? (submitButton.dataset.originalLabel || 'Processing') : 'Processing';
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

            // Choose endpoint: prefer admin-ajax for async actions to hit our AJAX delegates
            const actionFieldForFetch = form.querySelector('input[name="action"]');
            const actionNameForFetch = actionFieldForFetch ? (actionFieldForFetch.value || '').trim() : '';
            const ajaxUrl = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.ajax_url) ? tcukMigratorAjax.ajax_url : window.location.origin + '/wp-admin/admin-ajax.php';
            const targetUrl = ajaxUrl;

            fetch(targetUrl, {
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
                    console.debug('tcuk-admin: fetch response status', response.status, 'textSnippet', payloadText.slice(0,240));
                    let payloadJson = null;

                    try {
                        payloadJson = JSON.parse(payloadText);
                    } catch (error) {
                        payloadJson = null;
                    }

                    // If server returned structured JSON, handle success or error without redirect
                    if (payloadJson && typeof payloadJson.success !== 'undefined') {
                        const msg = payloadJson.data && payloadJson.data.message ? payloadJson.data.message : (payloadJson.success ? 'Completed successfully' : 'Request failed');
                        TCUK.toast(msg, payloadJson.success ? 'success' : 'error');
                        return { noRedirect: true, json: payloadJson, error: !payloadJson.success };
                    }

                    // If HTTP response is not OK and we didn't get structured JSON, surface useful error
                    if (!response.ok) {
                        let serverMsg = payloadText && payloadText.trim() ? payloadText.trim().slice(0, 1000) : `HTTP ${response.status}`;
                        // Try to extract simple JSON message if possible
                        try {
                            const maybe = payloadJson || JSON.parse(payloadText || '{}');
                            if (maybe && maybe.data && maybe.data.message) {
                                serverMsg = maybe.data.message;
                            } else if (maybe && maybe.message) {
                                serverMsg = maybe.message;
                            }
                        } catch (e) {
                            // ignore
                        }

                        const errText = `Server error: ${serverMsg}`;
                        console.warn('tcuk-admin: server error response', response.status, serverMsg);
                        TCUK.toast(errText, 'error');
                        throw new Error(errText);
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

                    if (result && result.noRedirect) {
                        // Completed via AJAX — hide progress and re-enable controls
                        setProgressState({ visible: false, value: 0, text: '' });
                        if (hasSubmitButton) {
                            submitButton.disabled = false;
                            const original = submitButton.dataset.originalLabel || '';
                            if (submitButton.tagName === 'BUTTON') {
                                submitButton.textContent = original;
                            } else if (submitButton.tagName === 'INPUT') {
                                submitButton.value = original;
                            }
                        }

                        // If this was a license activation, request refreshed admin markup
                        try {
                            const actionField = form.querySelector('input[name="action"]');
                            const scopeField = form.querySelector('input[name="settings_scope"]');
                            const actionName = actionField ? (actionField.value || '').trim() : '';
                            const scopeName = scopeField ? (scopeField.value || '').trim() : '';

                            if (actionName === 'tcuk_migrator_save_settings' && scopeName === 'license') {
                                const ajaxUrl = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.ajax_url) ? tcukMigratorAjax.ajax_url : window.location.origin + '/wp-admin/admin-ajax.php';
                                const ajaxNonce = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.nonce) ? tcukMigratorAjax.nonce : '';

                                const fd = new FormData();
                                fd.append('action', 'tcuk_refresh_admin_markup');
                                fd.append('nonce', ajaxNonce);

                                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                                    .then(r => r.json())
                                    .then((payload) => {
                                        if (payload && payload.success && payload.data && payload.data.html) {
                                            try {
                                                const parser = new DOMParser();
                                                const doc = parser.parseFromString(payload.data.html, 'text/html');
                                                const newWrap = doc.querySelector('.tcuk-migrator-wrap');
                                                const oldWrap = document.querySelector('.tcuk-migrator-wrap');
                                                if (newWrap && oldWrap) {
                                                    oldWrap.outerHTML = newWrap.outerHTML;

                                                    // Re-execute admin script to rebind interactions: find current admin.js script and re-add it
                                                    const existing = document.querySelector('script[src*="assets/js/admin.js"]');
                                                    if (existing && existing.src) {
                                                        const s = document.createElement('script');
                                                        s.src = existing.src + '?r=' + Date.now();
                                                        document.body.appendChild(s);
                                                    }
                                                }
                                            } catch (e) {
                                                console.error('tcuk-admin: refresh markup parse error', e);
                                            }
                                        } else if (payload && payload.data && payload.data.message) {
                                            TCUK.toast(payload.data.message, 'error');
                                        }
                                    }).catch((err) => {
                                        console.error('tcuk-admin: refresh_markup fetch error', err);
                                    });
                            }
                            // If setup wizard completed, refresh admin markup so timestamps, counts and state update
                            else if (actionName === 'tcuk_migrator_setup_wizard') {
                                const ajaxUrl = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.ajax_url) ? tcukMigratorAjax.ajax_url : window.location.origin + '/wp-admin/admin-ajax.php';
                                const ajaxNonce = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.nonce) ? tcukMigratorAjax.nonce : '';

                                const fd = new FormData();
                                fd.append('action', 'tcuk_refresh_admin_markup');
                                fd.append('nonce', ajaxNonce);

                                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                                    .then(r => r.json())
                                    .then((payload) => {
                                        if (payload && payload.success && payload.data && payload.data.html) {
                                            try {
                                                const parser = new DOMParser();
                                                const doc = parser.parseFromString(payload.data.html, 'text/html');
                                                const newWrap = doc.querySelector('.tcuk-migrator-wrap');
                                                const oldWrap = document.querySelector('.tcuk-migrator-wrap');
                                                if (newWrap && oldWrap) {
                                                    oldWrap.outerHTML = newWrap.outerHTML;
                                                    const existing = document.querySelector('script[src*="assets/js/admin.js"]');
                                                    if (existing && existing.src) {
                                                        const s = document.createElement('script');
                                                        s.src = existing.src + '?r=' + Date.now();
                                                        document.body.appendChild(s);
                                                    }
                                                }
                                            } catch (e) {
                                                console.error('tcuk-admin: refresh markup parse error', e);
                                            }
                                        } else if (payload && payload.data && payload.data.message) {
                                            TCUK.toast(payload.data.message, 'error');
                                        }
                                    }).catch((err) => {
                                        console.error('tcuk-admin: refresh_markup fetch error', err);
                                    });
                            }
                            // Refresh backups list after backup actions so table updates live
                            else if (['tcuk_migrator_backup_create', 'tcuk_migrator_backup_delete', 'tcuk_migrator_backup_upload', 'tcuk_migrator_backup_restore'].indexOf(actionName) !== -1) {
                                const ajaxUrl = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.ajax_url) ? tcukMigratorAjax.ajax_url : window.location.origin + '/wp-admin/admin-ajax.php';
                                const ajaxNonce = (typeof tcukMigratorAjax !== 'undefined' && tcukMigratorAjax.nonce) ? tcukMigratorAjax.nonce : '';

                                const fd = new FormData();
                                fd.append('action', 'tcuk_refresh_admin_markup');
                                fd.append('nonce', ajaxNonce);

                                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                                    .then(r => r.json())
                                    .then((payload) => {
                                        if (payload && payload.success && payload.data && payload.data.html) {
                                            try {
                                                const parser = new DOMParser();
                                                const doc = parser.parseFromString(payload.data.html, 'text/html');
                                                const newWrap = doc.querySelector('.tcuk-migrator-wrap');
                                                const oldWrap = document.querySelector('.tcuk-migrator-wrap');
                                                if (newWrap && oldWrap) {
                                                    // Replace the entire wrap to ensure table and counts refresh
                                                    oldWrap.outerHTML = newWrap.outerHTML;

                                                    const existing = document.querySelector('script[src*="assets/js/admin.js"]');
                                                    if (existing && existing.src) {
                                                        const s = document.createElement('script');
                                                        s.src = existing.src + '?r=' + Date.now();
                                                        document.body.appendChild(s);
                                                    }
                                                }
                                            } catch (e) {
                                                console.error('tcuk-admin: refresh markup parse error', e);
                                            }
                                        } else if (payload && payload.data && payload.data.message) {
                                            TCUK.toast(payload.data.message, 'error');
                                        }
                                    }).catch((err) => {
                                        console.error('tcuk-admin: refresh_markup fetch error', err);
                                    });
                            }
                        } catch (e) {
                            // ignore
                        }

                        return;
                    }

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

                    // Show an unobtrusive toast for errors and hide the progress UI
                    try {
                        TCUK.toast(message, 'error');
                    } catch (e) {
                        console.error('tcuk-admin: toast error', e);
                    }

                    setProgressState({ visible: false, value: 0, text: '' });

                    if (hasSubmitButton) {
                        submitButton.disabled = false;
                        const original = submitButton.dataset.originalLabel || '';
                        if (submitButton.tagName === 'BUTTON') {
                            submitButton.textContent = original;
                        } else if (submitButton.tagName === 'INPUT') {
                            submitButton.value = original;
                        }
                    }
                });
        });
    });
})();
