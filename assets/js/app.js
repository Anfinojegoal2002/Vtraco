const modal = document.getElementById('attendance-modal');
        const modalContent = document.getElementById('modal-content');

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[char]));
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
                    <form method="post" class="stack-form">
                        <input type="hidden" name="action" value="admin_set_status">
                        <input type="hidden" name="employee_id" value="${payload.employee_id}">
                        <input type="hidden" name="attend_date" value="${payload.date}">
                        <label>Update Status
                            <select name="status">
                                ${['Present','Absent','Half Day','Leave','Week Off'].map(status => `<option value="${status}" ${status === payload.status ? 'selected' : ''}>${status}</option>`).join('')}
                            </select>
                        </label>
                        <button class="button solid" type="submit">Save Status</button>
                    </form>
                `;
            }

            let employeeCards = '';
            if (payload.context === 'employee') {
                const manualInDisabled = payload.rule_manual_in && payload.punch_in_path ? 'disabled' : (!payload.rule_manual_in ? 'disabled' : '');
                const manualOutDisabled = (!payload.rule_manual_out || !payload.punch_in_path || payload.sessions.length >= payload.manual_out_count) ? 'disabled' : '';
                const biometricDisabledIn = !payload.rule_bio_in ? 'disabled' : '';
                const biometricDisabledOut = !payload.rule_bio_out ? 'disabled' : '';
                const slotOptions = (payload.manual_out_slots || ['Manual Punch Slot 1']).map(slot => `<option value="${escapeHtml(slot)}">${escapeHtml(slot)}</option>`).join('');

                employeeCards = `
                    <div class="cards-3">
                        <div class="action-card ${!payload.rule_manual_in ? 'disabled' : ''}">
                            <h3>Manual Punch In</h3>
                            <p>Upload a geo-tagged photo to begin the day.</p>
                            <form method="post" enctype="multipart/form-data" class="stack-form">
                                <input type="hidden" name="action" value="employee_punch_in">
                                <input type="hidden" name="attend_date" value="${payload.date}">
                                <label>Geo-tagged photo<input type="file" name="punch_photo" accept="image/*" ${manualInDisabled}></label>
                                <div class="list-item hidden file-preview-box"></div>
                                <input type="hidden" name="latitude" class="geo-lat">
                                <input type="hidden" name="longitude" class="geo-lng">
                                <div class="hint geo-hint">Location will be captured when this popup opens.</div>
                                <button class="button solid" type="submit" ${manualInDisabled}>${payload.punch_in_path ? 'Submitted' : 'Punch In'}</button>
                            </form>
                        </div>
                        <div class="action-card ${manualOutDisabled ? 'disabled' : ''}">
                            <h3>Manual Punch Out</h3>
                            <p>Submit session details after punch in.</p>
                            <form method="post" class="stack-form">
                                <input type="hidden" name="action" value="employee_manual_out">
                                <input type="hidden" name="attend_date" value="${payload.date}">
                                <label>Manual punch slot<select name="slot_name" ${manualOutDisabled}>${slotOptions}</select></label>
                                <label>College Name<input type="text" name="college_name" ${manualOutDisabled} required></label>
                                <label>Session Name<input type="text" name="session_name" ${manualOutDisabled} required></label>
                                <label>Half Day / Full Day<select name="day_portion" ${manualOutDisabled}><option>Full Day</option><option>Half Day</option></select></label>
                                <label>Session Duration in hours<input type="number" step="0.5" min="0" name="session_duration" ${manualOutDisabled} required></label>
                                <label>Location<input type="text" name="location" ${manualOutDisabled} required></label>
                                <button class="button secondary" type="submit" ${manualOutDisabled}>Submit Punch Out</button>
                            </form>
                        </div>
                        <div class="action-card ${(!payload.rule_bio_in && !payload.rule_bio_out) ? 'disabled' : ''}">
                            <h3>Biometric Options</h3>
                            <p>Use biometric actions only if they are enabled by the admin.</p>
                            <div class="inline-actions">
                                <form method="post">
                                    <input type="hidden" name="action" value="employee_biometric">
                                    <input type="hidden" name="attend_date" value="${payload.date}">
                                    <input type="hidden" name="stamp_type" value="in">
                                    <button class="button ghost small" type="submit" ${biometricDisabledIn}>Biometric In</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="action" value="employee_biometric">
                                    <input type="hidden" name="attend_date" value="${payload.date}">
                                    <input type="hidden" name="stamp_type" value="out">
                                    <button class="button ghost small" type="submit" ${biometricDisabledOut}>Biometric Out</button>
                                </form>
                            </div>
                            <div class="hint">In: ${escapeHtml(payload.biometric_in_time || 'Not stamped')}<br>Out: ${escapeHtml(payload.biometric_out_time || 'Not stamped')}</div>
                        </div>
                    </div>
                    <div class="section-block">
                        <div class="split"><h3>Request for Leave</h3><button class="button outline small" type="button" data-toggle-leave>Request for Leave</button></div>
                        <form method="post" class="stack-form hidden" data-leave-form>
                            <input type="hidden" name="action" value="employee_leave">
                            <input type="hidden" name="attend_date" value="${payload.date}">
                            <label>Leave Reason<textarea name="leave_reason" required>${escapeHtml(payload.leave_reason || '')}</textarea></label>
                            <button class="button ghost" type="submit">Apply</button>
                        </form>
                    </div>
                `;
            }

            modalContent.innerHTML = `
                <div class="split">
                    <div>
                        <span class="eyebrow">${payload.context === 'admin' ? 'Admin Attendance' : 'Employee Attendance'}</span>
                        <h2>${escapeHtml(payload.display_date)}</h2>
                        <p>Status: <span class="status-pill status-${escapeHtml(payload.status.replace(/\s+/g, '-'))}">${escapeHtml(payload.status)}</span></p>
                    </div>
                    <div class="hint">
                        Punch In: ${escapeHtml(payload.punch_in_time || 'Not submitted')}<br>
                        ${payload.punch_in_path ? `Geo: ${escapeHtml(payload.punch_in_lat || '-')}, ${escapeHtml(payload.punch_in_lng || '-')}` : ''}
                    </div>
                </div>
                <div>
                    <h3>Sessions Handled</h3>
                    <div class="list">${sessionMarkup}</div>
                </div>
                ${adminForm}
                ${employeeCards}
            `;
            modal.classList.add('open');
            wireDynamicModalUi();

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
            const submit = form.querySelector('[data-rule-submit]');
            if (!submit) {
                return;
            }
            const ruleCount = form.querySelectorAll('.rule-card input:checked').length;
            const employeeCount = form.matches('[data-employee-form]') ? form.querySelectorAll('input[name="employee_ids[]"]:checked').length : 1;
            const valid = ruleCount > 0 && employeeCount > 0;
            submit.classList.toggle('ghost', !valid);
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
                document.querySelectorAll('[data-open-on-load]').forEach(element => openModalById(element.id));
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

