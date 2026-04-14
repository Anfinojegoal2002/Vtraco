const modal = document.getElementById('attendance-modal');
        const modalContent = document.getElementById('modal-content');
        const csrfToken = window.VTRACO_CSRF_TOKEN || '';

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[char]));
        }

        function csrfInputMarkup() {
            return csrfToken ? `<input type="hidden" name="_csrf" value="${escapeHtml(csrfToken)}">` : '';
        }

        function openModalById(id) {
            const target = document.getElementById(id);
            if (target) {
                target.classList.add('open');
            }
        }

        function closeModal(element) {
            if (element) {
                element.classList.remove('open');
            }
        }

        function openAttendanceModal(payload) {
            if (payload.context === 'employee' && payload.status === 'Week Off') {
                return;
            }

            const displayStatus = payload.status || 'Not Marked';
            const displayStatusClass = payload.status ? payload.status.replace(/\s+/g, '-') : 'Unmarked';

            let sessionMarkup = '<div class="list-item muted">No sessions recorded for this date.</div>';
            if (payload.sessions && payload.sessions.length) {
                sessionMarkup = payload.sessions.map(session => `
                    <div class="list-item">
                        <strong>${escapeHtml(session.slot_name || 'Session')}</strong><br>
                        ${escapeHtml(session.college_name || '-')} | ${escapeHtml(session.session_name || '-')}<br>
                        ${escapeHtml(session.day_portion || '-')} | ${escapeHtml(session.session_duration || '-')} hrs<br>
                        ${escapeHtml(session.location || '-')}
                    </div>
                `).join('');
            }

            let adminForm = '';
            if (payload.context === 'admin') {
                adminForm = `
                    <section class="attendance-modal-section">
                        <h3>Update Attendance</h3>
                        <form method="post" class="stack-form">
                            ${csrfInputMarkup()}
                            <input type="hidden" name="action" value="admin_set_status">
                            <input type="hidden" name="employee_id" value="${payload.employee_id}">
                            <input type="hidden" name="attend_date" value="${payload.date}">
                            <label>Update Status
                                <select name="status">
                                    <option value="" disabled ${payload.status ? '' : 'selected'}>Select status</option>
                                    ${['Present','Absent','Half Day','Leave','Week Off'].map(status => `<option value="${status}" ${status === payload.status ? 'selected' : ''}>${status}</option>`).join('')}
                                </select>
                            </label>
                            <button class="button solid" type="submit">Save Status</button>
                        </form>
                    </section>
                `;
            }

            let employeeCards = '';
            if (payload.context === 'employee') {
                const manualPairSlotCount = Math.max(payload.manual_out_count || 0, payload.rule_manual_in ? 1 : 0, 1);
                const manualPairSlots = Array.from({ length: manualPairSlotCount }, (_, index) => (
                    payload.manual_out_slots && payload.manual_out_slots[index]
                        ? payload.manual_out_slots[index]
                        : `Manual Punch Slot ${index + 1}`
                ));
                const sessionForSlot = (slot, index) => payload.sessions.find(session => (session.slot_name || '') === slot) || payload.sessions[index] || null;
                const showAddManualPunchButton = false;
                const isWeekOff = payload.status === 'Week Off';
                const manualPunchPairs = manualPairSlots.map((slot, index) => {
                    const pairNumber = index + 1;
                    const pairSession = sessionForSlot(slot, index);
                    const pairPunchInPath = pairSession && pairSession.punch_in_path
                        ? pairSession.punch_in_path
                        : (pairNumber === 1 ? (payload.punch_in_path || '') : '');
                    const pairPunchInTime = pairSession && pairSession.punch_in_time
                        ? pairSession.punch_in_time
                        : (pairNumber === 1 ? (payload.punch_in_time || '') : '');
                    const pairPunchInLat = pairSession && pairSession.punch_in_lat
                        ? pairSession.punch_in_lat
                        : (pairNumber === 1 ? (payload.punch_in_lat || '') : '');
                    const pairPunchInLng = pairSession && pairSession.punch_in_lng
                        ? pairSession.punch_in_lng
                        : (pairNumber === 1 ? (payload.punch_in_lng || '') : '');
                    const pairPunchInDone = !!pairPunchInPath;
                    const pairPunchOutDone = !!(pairSession && (pairSession.college_name || pairSession.session_name || pairSession.location || Number(pairSession.session_duration || 0) > 0));
                    const pairHiddenClass = ''; // Show every configured manual punch pair for every date
                    const manualInSectionDisabled = !payload.rule_manual_in ? 'disabled' : '';
                    const manualInFormDisabled = (!payload.rule_manual_in || pairPunchInDone) ? 'disabled' : '';
                    const manualInRequired = manualInFormDisabled ? '' : 'required';
                    const manualOutSectionDisabled = (!payload.rule_manual_out || !pairPunchInDone) ? 'disabled' : '';
                    const manualOutFormDisabled = (!payload.rule_manual_out || !pairPunchInDone || pairPunchOutDone) ? 'disabled' : '';
                    const manualInNote = pairPunchInDone
                        ? `Submitted at ${escapeHtml(pairPunchInTime || 'Saved')}. Geo: ${escapeHtml(pairPunchInLat || '-')}, ${escapeHtml(pairPunchInLng || '-')}`
                        : 'Location will be captured when this popup opens.';
                    const manualOutNote = !payload.rule_manual_out
                        ? 'Manual Punch Out is not enabled for this employee.'
                        : (!pairPunchInDone
                            ? `Submit Manual Punch In ${pairNumber} first.`
                            : (pairPunchOutDone
                                ? `Manual Punch Out ${pairNumber} is already submitted.`
                                : `Fill the required fields for Manual Punch Out ${pairNumber}.`));
                    const pairStatus = pairPunchOutDone ? 'Completed' : (pairPunchInDone ? 'Punch In Submitted' : 'Pending');
                    const selectedDayPortion = pairSession && pairSession.day_portion ? pairSession.day_portion : 'Full Day';
                    const sessionDurationValue = pairSession && pairSession.session_duration ? pairSession.session_duration : '';
                    const sessionIdField = pairSession && pairSession.id ? `<input type="hidden" name="session_id" value="${escapeHtml(pairSession.id)}">` : '';

                    return `
                        <div class="manual-punch-pair ${pairHiddenClass}" data-manual-punch-pair>
                            <div class="split manual-punch-pair-head">
                                <div>
                                    <h3>Manual Punch Pair ${pairNumber}</h3>
                                    <p>${escapeHtml(slot)}</p>
                                </div>
                                <span class="badge">${escapeHtml(pairStatus)}</span>
                            </div>
                            <div class="manual-punch-grid">
                                <div class="manual-punch-section ${manualInSectionDisabled}">
                                    <h3>Manual Punch In ${pairNumber}</h3>
                                    <p>Upload the photo for Manual Punch In ${pairNumber}.</p>
                                    <form method="post" enctype="multipart/form-data" class="stack-form">
                                        ${csrfInputMarkup()}
                                        <input type="hidden" name="action" value="employee_manual_in">
                                        <input type="hidden" name="attend_date" value="${payload.date}">
                                        <input type="hidden" name="slot_index" value="${pairNumber}">
                                        <input type="hidden" name="slot_name" value="${escapeHtml(slot)}">
                                        ${sessionIdField}
                                        <label>Punch In Photo *<input type="file" name="punch_photo" accept="image/*" ${manualInFormDisabled} ${manualInRequired}></label>
                                        <div class="list-item hidden file-preview-box"></div>
                                        <input type="hidden" name="latitude" class="geo-lat">
                                        <input type="hidden" name="longitude" class="geo-lng">
                                        <div class="hint geo-hint">${manualInNote}</div>
                                        <button class="button solid" type="submit" ${manualInFormDisabled}>${pairPunchInDone ? 'Submitted' : `Punch In ${pairNumber}`}</button>
                                    </form>
                                </div>
                                <div class="manual-punch-section ${manualOutSectionDisabled}">
                                    <h3>Manual Punch Out ${pairNumber}</h3>
                                    <p>${escapeHtml(manualOutNote)}</p>
                                    <form method="post" class="stack-form">
                                        ${csrfInputMarkup()}
                                        <input type="hidden" name="action" value="employee_manual_out">
                                        <input type="hidden" name="attend_date" value="${payload.date}">
                                        <input type="hidden" name="slot_index" value="${pairNumber}">
                                        <input type="hidden" name="slot_name" value="${escapeHtml(slot)}">
                                        ${sessionIdField}
                                        <label>College Name *<input type="text" name="college_name" value="${escapeHtml(pairSession && pairSession.college_name ? pairSession.college_name : '')}" ${manualOutFormDisabled} required></label>
                                        <label>Session Name *<input type="text" name="session_name" value="${escapeHtml(pairSession && pairSession.session_name ? pairSession.session_name : '')}" ${manualOutFormDisabled} required></label>
                                        <label>Half Day / Full Day
                                            <select name="day_portion" ${manualOutFormDisabled}>
                                                ${['Full Day', 'Half Day'].map(option => `<option value="${option}" ${option === selectedDayPortion ? 'selected' : ''}>${option}</option>`).join('')}
                                            </select>
                                        </label>
                                        <label>Session Duration in hours *<input type="number" step="0.5" min="0.5" name="session_duration" value="${escapeHtml(sessionDurationValue)}" ${manualOutFormDisabled} required></label>
                                        <label>Location *<input type="text" name="location" value="${escapeHtml(pairSession && pairSession.location ? pairSession.location : '')}" ${manualOutFormDisabled} required></label>
                                        <button class="button secondary" type="submit" ${manualOutFormDisabled}>${pairPunchOutDone ? 'Submitted' : `Punch Out ${pairNumber}`}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                employeeCards = isWeekOff ? `
                    <div class="section-block">
                        <h3>Week Off</h3>
                        <p>Attendance is not required for this date.</p>
                    </div>
                ` : `
                    <div class="cards-2">
                        <div class="action-card manual-punch-card">
                            <button class="manual-punch-toggle" type="button" data-toggle-manual-punch aria-expanded="false">
                                <span class="manual-punch-toggle-copy">
                                    <strong>Manual Punch</strong>
                                    <span>Open all configured manual punch pairs for this date, including slots 2 and 3.</span>
                                </span>
                                <span class="manual-punch-toggle-state">Open</span>
                            </button>
                            <div class="manual-punch-panel hidden" data-manual-punch-panel>
                                <div class="manual-punch-popup">
                                    <div class="split manual-punch-popup-head">
                                        <div>
                                            <h3>Manual Punch</h3>
                                            <p>Each manual punch pair has its own Manual Punch In and Manual Punch Out section. All configured slots are shown for this date.</p>
                                        </div>
                                        <div class="inline-actions">
                                            ${showAddManualPunchButton ? '<button class="button outline small" type="button" data-add-manual-punch-entry>Add More Manual Punch</button>' : ''}
                                            <button class="manual-punch-popup-close" type="button" data-close-manual-punch-popup>&times;</button>
                                        </div>
                                    </div>
                                    <div class="manual-punch-pairs">
                                        ${manualPunchPairs}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="action-card ${(!payload.rule_bio_in && !payload.rule_bio_out) ? 'disabled' : ''}">
                            <h3>Biometric Options</h3>
                            <p>Use biometric actions only if they are enabled by the admin.</p>
                            <div class="inline-actions">
                                <form method="post">
                                    ${csrfInputMarkup()}
                                    <input type="hidden" name="action" value="employee_biometric">
                                    <input type="hidden" name="attend_date" value="${payload.date}">
                                    <input type="hidden" name="stamp_type" value="in">
                                    <button class="button ghost small" type="submit" ${!payload.rule_bio_in ? 'disabled' : ''}>Biometric In</button>
                                </form>
                                <form method="post">
                                    ${csrfInputMarkup()}
                                    <input type="hidden" name="action" value="employee_biometric">
                                    <input type="hidden" name="attend_date" value="${payload.date}">
                                    <input type="hidden" name="stamp_type" value="out">
                                    <button class="button ghost small" type="submit" ${!payload.rule_bio_out ? 'disabled' : ''}>Biometric Out</button>
                                </form>
                            </div>
                            <div class="hint">In: ${escapeHtml(payload.biometric_in_time || 'Not stamped')}<br>Out: ${escapeHtml(payload.biometric_out_time || 'Not stamped')}</div>
                        </div>
                    </div>
                    <div class="section-block">
                        <div class="split"><h3>Request for Leave</h3><button class="button outline small" type="button" data-toggle-leave>Request for Leave</button></div>
                        <form method="post" class="stack-form hidden" data-leave-form>
                            ${csrfInputMarkup()}
                            <input type="hidden" name="action" value="employee_leave">
                            <input type="hidden" name="attend_date" value="${payload.date}">
                            <label>Leave Reason<textarea name="leave_reason" required>${escapeHtml(payload.leave_reason || '')}</textarea></label>
                            <button class="button ghost" type="submit">Apply</button>
                        </form>
                    </div>
                `;
            }
            modalContent.className = `modal-grid attendance-context-${payload.context}`;
            modalContent.innerHTML = `
                <section class="attendance-modal-hero">
                    <div>
                        <span class="eyebrow">${payload.context === 'admin' ? 'Admin Attendance' : 'Employee Attendance'}</span>
                        <h2>${escapeHtml(payload.display_date)}</h2>
                        <p>Status: <span class="status-pill status-${escapeHtml(displayStatusClass)}">${escapeHtml(displayStatus)}</span></p>
                    </div>
                    <div class="attendance-modal-meta">
                        <strong>Punch Details</strong><br>
                        Punch In: ${escapeHtml(payload.punch_in_time || payload.biometric_in_time || 'Not submitted')}<br>
                        Punch Out: ${escapeHtml(payload.biometric_out_time || 'Not submitted')}<br>
                        ${payload.punch_in_path ? `Geo: ${escapeHtml(payload.punch_in_lat || '-')}, ${escapeHtml(payload.punch_in_lng || '-')}` : ((payload.biometric_in_time || payload.biometric_out_time) ? 'Source: Imported biometric attendance' : 'Geo: Not available')}
                    </div>
                </section>
                <section class="attendance-modal-section">
                    <div class="split">
                        <h3>Sessions Handled</h3>
                        <span class="badge">${payload.sessions ? payload.sessions.length : 0} total</span>
                    </div>
                    <div class="list">${sessionMarkup}</div>
                </section>
                ${adminForm}
                ${employeeCards}
            `;
            modal.classList.add('open');
            wireDynamicModalUi();
        wireProfilePhoto();

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    document.querySelectorAll('.geo-lat').forEach(input => input.value = position.coords.latitude);
                    document.querySelectorAll('.geo-lng').forEach(input => input.value = position.coords.longitude);
                    document.querySelectorAll('.geo-hint').forEach(el => el.textContent = `Location captured: ${position.coords.latitude.toFixed(5)}, ${position.coords.longitude.toFixed(5)}`);
                }, () => {
                    document.querySelectorAll('.geo-hint').forEach(el => el.textContent = 'Location permission blocked. You can still submit, but geo coordinates will be empty.');
                });
            }
        }

        function wireDynamicModalUi() {
            const closeManualPunchPopup = panel => {
                if (!panel) {
                    return;
                }
                const card = panel.closest('.manual-punch-card');
                const button = card ? card.querySelector('[data-toggle-manual-punch]') : null;
                panel.classList.add('hidden');
                if (card) {
                    card.classList.remove('open');
                }
                if (button) {
                    button.setAttribute('aria-expanded', 'false');
                    const state = button.querySelector('.manual-punch-toggle-state');
                    if (state) {
                        state.textContent = 'Open';
                    }
                }
            };

            document.querySelectorAll('[data-toggle-manual-punch]').forEach(button => {
                button.addEventListener('click', () => {
                    const card = button.closest('.manual-punch-card');
                    const panel = card ? card.querySelector('[data-manual-punch-panel]') : null;
                    if (!card || !panel) {
                        return;
                    }
                    panel.classList.remove('hidden');
                    card.classList.add('open');
                    button.setAttribute('aria-expanded', 'true');
                    const state = button.querySelector('.manual-punch-toggle-state');
                    if (state) {
                        state.textContent = 'Opened';
                    }
                });
            });

            document.querySelectorAll('[data-close-manual-punch-popup]').forEach(button => {
                button.addEventListener('click', () => {
                    closeManualPunchPopup(button.closest('[data-manual-punch-panel]'));
                });
            });

            document.querySelectorAll('[data-manual-punch-panel]').forEach(panel => {
                panel.addEventListener('click', event => {
                    if (event.target === panel) {
                        closeManualPunchPopup(panel);
                    }
                });
            });

            document.querySelectorAll('[data-add-manual-punch-entry]').forEach(button => {
                button.addEventListener('click', () => {
                    const panel = button.closest('[data-manual-punch-panel]');
                    const nextEntry = panel ? panel.querySelector('[data-manual-punch-pair].hidden') : null;
                    if (!nextEntry) {
                        button.setAttribute('disabled', 'disabled');
                        return;
                    }
                    nextEntry.classList.remove('hidden');
                    if (!panel.querySelector('[data-manual-punch-pair].hidden')) {
                        button.setAttribute('disabled', 'disabled');
                    }
                });
            });
            document.querySelectorAll('[data-toggle-leave]').forEach(button => {
                button.addEventListener('click', () => {
                    const form = button.closest('.section-block').querySelector('[data-leave-form]');
                    if (form) {
                        form.classList.toggle('hidden');
                    }
                });
            });

            document.querySelectorAll('input[type="file"][name="punch_photo"]').forEach(input => {
                input.addEventListener('change', event => {
                    const file = event.target.files && event.target.files[0];
                    const box = event.target.closest('form').querySelector('.file-preview-box');
                    if (!box || !file) {
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = loadEvent => {
                        box.classList.remove('hidden');
                        box.innerHTML = `<strong>Preview</strong><div class="spacer"></div><img src="${loadEvent.target.result}" alt="Preview" style="max-width:100%;border-radius:16px;">`;
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        document.querySelectorAll('[data-attendance]').forEach(button => {
            button.addEventListener('click', () => openAttendanceModal(JSON.parse(button.dataset.attendance)));
        });
        document.querySelectorAll('[data-close-modal]').forEach(button => button.addEventListener('click', () => closeModal(button.closest('.modal'))));
        if (modal) {
            modal.addEventListener('click', event => {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        }
        document.querySelectorAll('[data-modal-target]').forEach(button => {
            button.addEventListener('click', () => openModalById(button.dataset.modalTarget));
        });
        document.querySelectorAll('[data-switch-modal-target]').forEach(button => {
            button.addEventListener('click', () => {
                closeModal(button.closest('.modal'));
                openModalById(button.dataset.switchModalTarget);
            });
        });
        document.querySelectorAll('.modal').forEach(dialog => {
            dialog.addEventListener('click', event => {
                if (event.target === dialog) {
                    closeModal(dialog);
                }
            });
        });
        document.querySelectorAll('[data-password-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                if (!input) {
                    return;
                }
                input.type = input.type === 'password' ? 'text' : 'password';
                button.textContent = input.type === 'password' ? 'Show' : 'Hide';
            });
        });
        document.querySelectorAll('[data-add-manual-slot]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.querySelector(button.dataset.target);
                input.value = String(Math.max(1, Number(input.value || '1')) + 1);
            });
        });
        document.querySelectorAll('form[data-validate]').forEach(form => {
            form.querySelectorAll('input[required], textarea[required], select[required]').forEach(input => {
                const validate = () => {
                    const field = input.closest('.field') || input.closest('label');
                    if (!field) {
                        return;
                    }
                    field.classList.toggle('invalid', !input.checkValidity());
                };
                input.addEventListener('blur', validate);
                input.addEventListener('input', validate);
            });
        });
        document.querySelectorAll('form[data-watch-required]').forEach(form => {
            const submit = form.querySelector('[data-required-submit]');
            const update = () => {
                const valid = [...form.querySelectorAll('input[required], textarea[required], select[required]')].every(input => input.value.trim() !== '' && input.checkValidity());
                if (submit) {
                    submit.classList.toggle('ghost', !valid);
                }
            };
            form.querySelectorAll('input[required], textarea[required], select[required]').forEach(input => input.addEventListener('input', update));
            update();
        });
        document.querySelectorAll('.rule-card input').forEach(input => {
            const sync = () => input.closest('.rule-card').classList.toggle('active', input.checked);
            input.addEventListener('change', sync);
            sync();
        });
        document.querySelectorAll('[data-employee-filter]').forEach(filterInput => {
            filterInput.addEventListener('input', () => {
                const list = document.getElementById(filterInput.dataset.employeeFilter);
                if (!list) {
                    return;
                }
                const term = filterInput.value.toLowerCase();
                list.querySelectorAll('.employee-option').forEach(option => {
                    option.style.display = option.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        });
        document.querySelectorAll('[data-table-filter]').forEach(filterInput => {
            const tableBody = document.getElementById(filterInput.dataset.tableFilter);
            const emptyState = filterInput.dataset.emptyTarget ? document.getElementById(filterInput.dataset.emptyTarget) : null;
            const countTarget = filterInput.dataset.countTarget ? document.getElementById(filterInput.dataset.countTarget) : null;
            if (!tableBody) {
                return;
            }
            const rows = [...tableBody.querySelectorAll('[data-filter-row]')];
            const totalCount = rows.length;
            const update = () => {
                const term = filterInput.value.trim().toLowerCase();
                let visibleCount = 0;
                rows.forEach(row => {
                    const haystack = (row.dataset.filterText || row.textContent || '').toLowerCase();
                    const matches = term === '' || haystack.includes(term);
                    row.style.display = matches ? '' : 'none';
                    if (matches) {
                        visibleCount += 1;
                    }
                });
                if (emptyState) {
                    emptyState.classList.toggle('hidden', visibleCount !== 0);
                }
                if (countTarget) {
                    countTarget.textContent = term === '' ? totalCount + ' total' : visibleCount + ' of ' + totalCount;
                }
            };
            filterInput.addEventListener('input', update);
            update();
        });
        document.querySelectorAll('[data-tag-source]').forEach(container => {
            const tagTarget = document.getElementById(container.dataset.tagSource);
            const updateTags = () => {
                if (!tagTarget) {
                    return;
                }
                const checked = [...container.querySelectorAll('input[type="checkbox"]:checked')];
                tagTarget.innerHTML = checked.map(item => `<span class="tag">${escapeHtml(item.dataset.label || item.value)}</span>`).join('');
                const ruleForm = container.closest('form[data-rule-form]');
                if (ruleForm) {
                    updateRuleSubmit(ruleForm);
                }
            };
            container.querySelectorAll('input[type="checkbox"]').forEach(input => input.addEventListener('change', updateTags));
            updateTags();
        });
        function updateRuleSubmit(form) {
            const submits = form.querySelectorAll('[data-rule-submit]');
            if (!submits.length) {
                return;
            }
            const ruleCount = form.querySelectorAll('.rule-card input:checked').length;
            const employeeCount = form.matches('[data-employee-form]') ? form.querySelectorAll('input[name="employee_ids[]"]:checked').length : 1;
            const valid = ruleCount > 0 && employeeCount > 0;
            submits.forEach(submit => submit.classList.toggle('ghost', !valid));
        }
        document.querySelectorAll('form[data-rule-form]').forEach(form => {
            form.querySelectorAll('.rule-card input, input[name="employee_ids[]"]').forEach(input => {
                input.addEventListener('change', () => updateRuleSubmit(form));
            });
            updateRuleSubmit(form);
        });
        document.querySelectorAll('[data-confirm-delete]').forEach(button => {
            button.addEventListener('click', () => {
                const modal = document.getElementById('delete-employee-modal');
                if (!modal) {
                    return;
                }
                modal.querySelector('input[name="user_id"]').value = button.dataset.userId;
                modal.querySelector('[data-delete-name]').textContent = button.dataset.userName;
                openModalById('delete-employee-modal');
            });
        });
        document.querySelectorAll('.flash').forEach((toast, index) => {
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-6px)';
                setTimeout(() => toast.remove(), 220);
            }, 3400 + (index * 200));
        });
        const landingTopbar = document.querySelector('.landing-topbar');
        if (landingTopbar) {
            const syncTopbar = () => {
                landingTopbar.classList.toggle('scrolled', window.scrollY > 24);
            };
            syncTopbar();
            window.addEventListener('scroll', syncTopbar, { passive: true });
        }
        const revealItems = document.querySelectorAll('.reveal');
        if (revealItems.length) {
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.18 });
            revealItems.forEach(item => observer.observe(item));
        }
        document.querySelectorAll('[data-counter]').forEach(counter => {
            const target = Number(counter.dataset.counter || '0');
            const duration = 1200;
            const start = performance.now();
            const tick = now => {
                const progress = Math.min((now - start) / duration, 1);
                const value = target % 1 === 0 ? Math.floor(target * progress) : (target * progress).toFixed(1);
                counter.textContent = progress === 1 ? String(target) : String(value);
                if (progress < 1) {
                    requestAnimationFrame(tick);
                }
            };
            requestAnimationFrame(tick);
        });
        function wireProfilePhoto() {
            const card = document.querySelector('[data-profile-card]');
            if (!card || !window.localStorage) {
                return;
            }

            const role = card.dataset.profileRole || 'employee';
            const settingsModal = document.getElementById(role === 'admin' ? 'admin-profile-settings-modal' : 'employee-profile-settings-modal');
            if (!settingsModal) {
                return;
            }

            const photo = card.querySelector('[data-profile-photo]');
            const fallback = card.querySelector('[data-profile-fallback]');
            const input = settingsModal.querySelector('[data-profile-photo-input]');
            const status = settingsModal.querySelector('[data-profile-photo-status]');
            const storageKey = 'vtraco:' + role + '-profile-photo:' + (card.dataset.profileId || 'default');
            const applyPhoto = value => {
                if (!photo || !fallback) {
                    return;
                }
                if (value) {
                    photo.src = value;
                    photo.classList.remove('hidden');
                    fallback.classList.add('hidden');
                } else {
                    photo.removeAttribute('src');
                    photo.classList.add('hidden');
                    fallback.classList.remove('hidden');
                }
            };

            try {
                applyPhoto(localStorage.getItem(storageKey) || '');
            } catch (error) {
                if (status) {
                    status.textContent = 'Browser storage is unavailable on this device.';
                }
                return;
            }

            if (!input) {
                return;
            }

            input.addEventListener('change', event => {
                const file = event.target.files && event.target.files[0];
                if (!file) {
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    if (status) {
                        status.textContent = 'Please choose an image under 2 MB.';
                    }
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = loadEvent => {
                    const result = typeof loadEvent.target.result === 'string' ? loadEvent.target.result : '';
                    if (!result) {
                        return;
                    }
                    try {
                        localStorage.setItem(storageKey, result);
                        applyPhoto(result);
                        if (status) {
                            status.textContent = 'Profile photo saved in this browser.';
                        }
                    } catch (error) {
                        if (status) {
                            status.textContent = 'This image is too large to store locally.';
                        }
                    }
                };
                reader.readAsDataURL(file);
            });
        }
        document.querySelectorAll('[data-open-on-load]').forEach(element => openModalById(element.id));
        document.querySelectorAll('form[method="post"]').forEach(form => {
            if (!csrfToken || form.querySelector('input[name="_csrf"]')) {
                return;
            }
            form.insertAdjacentHTML('afterbegin', csrfInputMarkup());
        });
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => {
                const submit = form.querySelector('button[type="submit"]');
                if (submit) {
                    submit.dataset.originalText = submit.textContent;
                    submit.textContent = 'Processing...';
                }
            });
        });
        wireDynamicModalUi();
        wireProfilePhoto();





