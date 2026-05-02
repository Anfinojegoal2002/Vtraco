        const modal = document.getElementById('attendance-modal');
        const modalContent = document.getElementById('modal-content');
        const csrfToken = window.VTRACO_CSRF_TOKEN || '';
        const availableProjects = Array.isArray(window.VTRACO_AVAILABLE_PROJECTS) ? window.VTRACO_AVAILABLE_PROJECTS : [];

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[char]));
        }

        function csrfInputMarkup() {
            return csrfToken ? `<input type="hidden" name="_csrf" value="${escapeHtml(csrfToken)}">` : '';
        }

        function findProjectById(projectId) {
            const numericId = Number(projectId || 0);
            if (!numericId) {
                return null;
            }

            return availableProjects.find(project => Number(project.id || 0) === numericId) || null;
        }

        function projectIsAvailableForDate(project, dateValue) {
            const date = String(dateValue || '').trim();
            if (!date) {
                return true;
            }
            const from = String(project.project_from || '').trim().slice(0, 10);
            const to = String(project.project_to || '').trim().slice(0, 10);
            if (from && date < from) {
                return false;
            }
            if (to && date > to) {
                return false;
            }
            return true;
        }

        function availableProjectsForDate(dateValue) {
            return availableProjects.filter(project => projectIsAvailableForDate(project, dateValue));
        }

        function projectOptionsMarkup(selectedProjectId, dateValue) {
            const numericSelectedId = Number(selectedProjectId || 0);
            const dateProjects = availableProjectsForDate(dateValue);
            const placeholder = `<option value="">${dateProjects.length ? 'Select project' : 'No projects assigned for this date'}</option>`;
            const options = dateProjects.map(project => `
                <option value="${escapeHtml(project.id)}" ${Number(project.id || 0) === numericSelectedId ? 'selected' : ''}>${escapeHtml(project.project_name || '')}</option>
            `).join('');

            return placeholder + options;
        }

        function projectManualSlotName(project) {
            const projectId = Number(project && project.id ? project.id : 0);
            const projectName = String(project && project.project_name ? project.project_name : 'Project').trim() || 'Project';
            return `Project #${projectId}: ${projectName}`;
        }

        function detailValue(value, fallback = '-') {
            const text = String(value ?? '').trim();
            return text !== '' ? text : fallback;
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
                sessionMarkup = payload.sessions.map(session => {
                    const project = findProjectById(session.project_id);
                    const projectName = project && project.project_name ? project.project_name : '';
                    const sessionDuration = Number(session.session_duration || 0) > 0
                        ? `${session.session_duration} hrs`
                        : '-';
                    const geoText = session.punch_in_lat || session.punch_in_lng
                        ? `${detailValue(session.punch_in_lat)}, ${detailValue(session.punch_in_lng)}`
                        : '-';
                    const imageMarkup = session.punch_in_path
                        ? `
                            <div class="session-proof">
                                <strong>Punch Image</strong>
                                <img class="session-proof-image" src="${escapeHtml(session.punch_in_path)}" alt="Punch image for ${escapeHtml(session.slot_name || 'Session')}">
                            </div>
                        `
                        : '';

                    return `
                        <div class="list-item session-detail-card">
                            <div class="split">
                                <strong>${escapeHtml(session.slot_name || 'Session')}</strong>
                                ${projectName ? `<span class="badge">${escapeHtml(projectName)}</span>` : ''}
                            </div>
                            <div class="session-detail-grid">
                                <div class="session-detail-row"><strong>Project</strong><span>${escapeHtml(detailValue(projectName))}</span></div>
                                <div class="session-detail-row"><strong>College Name</strong><span>${escapeHtml(detailValue(session.college_name))}</span></div>
                                <div class="session-detail-row"><strong>Session Name</strong><span>${escapeHtml(detailValue(session.session_name))}</span></div>
                                <div class="session-detail-row"><strong>Session Duration</strong><span>${escapeHtml(sessionDuration)}</span></div>
                                <div class="session-detail-row"><strong>Total Students</strong><span>${escapeHtml(detailValue(session.total_students))}</span></div>
                                <div class="session-detail-row"><strong>Present Students</strong><span>${escapeHtml(detailValue(session.present_students))}</span></div>
                                <div class="session-detail-row"><strong>Topics Handled</strong><span>${escapeHtml(detailValue(session.topics_handled))}</span></div>
                                <div class="session-detail-row"><strong>Location</strong><span>${escapeHtml(detailValue(session.location))}</span></div>
                                <div class="session-detail-row"><strong>Punch In Time</strong><span>${escapeHtml(detailValue(session.punch_in_time))}</span></div>
                                <div class="session-detail-row"><strong>Geo Coordinates</strong><span>${escapeHtml(geoText)}</span></div>
                            </div>
                            ${imageMarkup}
                        </div>
                    `;
                }).join('');
            }

            let reimbursementMarkup = '';
            if (payload.reimbursement && payload.reimbursement.count > 0) {
                reimbursementMarkup = `
                    <section class="attendance-modal-section">
                        <div class="split">
                            <h3>Reimbursement Claims</h3>
                            <span class="badge">${payload.reimbursement.count} total</span>
                        </div>
                        <div class="list">
                            ${payload.reimbursement.items.map(item => `
                                <div class="list-item">
                                    <div class="split">
                                        <strong>${escapeHtml(item.category)}</strong>
                                        <span class="status-pill status-${escapeHtml(item.status.toLowerCase())}">${escapeHtml(item.status)}</span>
                                    </div>
                                    <p class="hint">${escapeHtml(item.expense_description)}</p>
                                    <div class="split" style="margin-top: 8px;">
                                        <span>Requested: Rs ${escapeHtml(item.amount_requested)}</span>
                                        ${Number(item.amount_paid) > 0 ? `<span>Paid: Rs ${escapeHtml(item.amount_paid)}</span>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </section>
                `;
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
                const dateProjects = availableProjectsForDate(payload.date);
                const hasDateProjects = dateProjects.length > 0;
                const manualInEnabled = payload.rule_manual_in || hasDateProjects;
                const manualOutEnabled = payload.rule_manual_out || hasDateProjects;
                const sessionForProject = (project, index) => {
                    const projectId = Number(project.id || 0);
                    const slot = projectManualSlotName(project);
                    return payload.sessions.find(session => Number(session.project_id || 0) === projectId)
                        || payload.sessions.find(session => (session.slot_name || '') === slot)
                        || null;
                };
                const showAddManualPunchButton = false;
                const isWeekOff = payload.status === 'Week Off';
                const isFuture = !!payload.future;
                const manualPunchPairs = dateProjects.map((project, index) => {
                    const pairNumber = index + 1;
                    const pairSession = sessionForProject(project, index);
                    const selectedProjectId = Number(project.id || 0);
                    const selectedProject = project;
                    const slot = projectManualSlotName(project);
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
                    const pairHiddenClass = '';
                    const manualInSectionDisabled = (!manualInEnabled || !hasDateProjects) ? 'disabled' : '';
                    const manualInFormDisabled = (!manualInEnabled || !hasDateProjects || pairPunchInDone) ? 'disabled' : '';
                    const manualInRequired = manualInFormDisabled ? '' : 'required';
                    const manualOutSectionDisabled = (!manualOutEnabled || !hasDateProjects || !pairPunchInDone) ? 'disabled' : '';
                    const manualOutFormDisabled = (!manualOutEnabled || !hasDateProjects || !pairPunchInDone || pairPunchOutDone) ? 'disabled' : '';
                    const manualInNote = pairPunchInDone
                        ? `Submitted at ${escapeHtml(pairPunchInTime || 'Saved')}. Bio: ${escapeHtml(pairPunchInLat || '-')}, ${escapeHtml(pairPunchInLng || '-')}`
                        : (!hasDateProjects ? 'No assigned project is active for this date.' : 'Location will be captured when this popup opens.');
                    const manualOutNote = !hasDateProjects
                        ? 'No assigned project is active for this date.'
                        : (!manualOutEnabled
                        ? 'Manual Punch Out is not enabled for this employee.'
                        : (!pairPunchInDone
                            ? `Submit Manual Punch In ${pairNumber} first.`
                            : (pairPunchOutDone
                                ? `Manual Punch Out ${pairNumber} is already submitted.`
                                : `Fill the required fields for Manual Punch Out ${pairNumber}.`)));
                    const pairStatus = pairPunchOutDone ? 'Completed' : (pairPunchInDone ? 'Punch In Submitted' : 'Pending');
                    const sessionDurationValue = pairSession && pairSession.session_duration ? pairSession.session_duration : '';
                    const collegeNameValue = pairSession && pairSession.college_name
                        ? pairSession.college_name
                        : (selectedProject && selectedProject.college_name ? selectedProject.college_name : '');
                    const sessionNameValue = pairSession && pairSession.session_name
                        ? pairSession.session_name
                        : (selectedProject && selectedProject.project_name ? selectedProject.project_name : '');
                    const locationValue = pairSession && pairSession.location
                        ? pairSession.location
                        : (selectedProject && selectedProject.location ? selectedProject.location : '');
                    const totalStudentsValue = pairSession && pairSession.total_students ? pairSession.total_students : '';
                    const presentStudentsValue = pairSession && pairSession.present_students ? pairSession.present_students : '';
                    const topicsHandledValue = pairSession && pairSession.topics_handled ? pairSession.topics_handled : '';
                    const sessionIdField = pairSession && pairSession.id ? `<input type="hidden" name="session_id" value="${escapeHtml(pairSession.id)}">` : '';

                    return `
                        <div class="manual-punch-pair ${pairHiddenClass}" data-manual-punch-pair>
                            <div class="split manual-punch-pair-head">
                                <div>
                                    <h3>Manual Punch Pair ${pairNumber}</h3>
                                    <p>${escapeHtml(selectedProject.project_name || slot)}</p>
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
                                        <input type="hidden" name="project_id" value="${escapeHtml(selectedProjectId)}">
                                        ${sessionIdField}
                                        <div class="list-item"><strong>Project</strong><br><span>${escapeHtml(selectedProject.project_name || '')}</span></div>
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
                                        <input type="hidden" name="project_id" value="${escapeHtml(selectedProjectId)}">
                                        ${sessionIdField}
                                        <label>Project<input type="text" value="${escapeHtml(selectedProject.project_name || '')}" ${manualOutFormDisabled} readonly></label>
                                        <label>College Name *<input type="text" name="college_name" data-project-college value="${escapeHtml(collegeNameValue)}" ${manualOutFormDisabled} required></label>
                                        <label>Session Name *<input type="text" name="session_name" data-project-session value="${escapeHtml(sessionNameValue)}" ${manualOutFormDisabled} required></label>
                                        <label>Session Duration in hours *<input type="number" step="0.5" min="0.5" name="session_duration" value="${escapeHtml(sessionDurationValue)}" ${manualOutFormDisabled} required></label>
                                        <label>Total Students *<input type="number" min="1" step="1" name="total_students" value="${escapeHtml(totalStudentsValue)}" ${manualOutFormDisabled} required></label>
                                        <label>Present Students *<input type="number" min="0" step="1" name="present_students" value="${escapeHtml(presentStudentsValue)}" ${manualOutFormDisabled} required></label>
                                        <label>Topics Handled *<textarea name="topics_handled" rows="3" ${manualOutFormDisabled} required>${escapeHtml(topicsHandledValue)}</textarea></label>
                                        <label>Location *<input type="text" name="location" data-project-location value="${escapeHtml(locationValue)}" ${manualOutFormDisabled} required></label>
                                        <button class="button secondary" type="submit" ${manualOutFormDisabled}>${pairPunchOutDone ? 'Submitted' : `Punch Out ${pairNumber}`}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                const manualPunchCard = hasDateProjects ? `
                        <div class="action-card manual-punch-card">
                            <button class="manual-punch-toggle" type="button" data-toggle-manual-punch aria-expanded="false">
                                <span class="manual-punch-toggle-copy">
                                    <strong>Manual Punch</strong>
                                    <span>Open all configured manual punch pairs for this assigned project date.</span>
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
                ` : `
                        <div class="action-card disabled">
                            <h3>Manual Punch</h3>
                            <p>Manual Punch In and Punch Out are shown only for dates with an active assigned project.</p>
                        </div>
                `;

                employeeCards = isFuture ? `
                    <div class="section-block">
                        <h3>Future Date Locked</h3>
                        <p>Attendance cannot be marked for future dates.</p>
                    </div>
                ` : isWeekOff ? `
                    <div class="section-block">
                        <h3>Week Off</h3>
                        <p>Attendance is not required for this date.</p>
                    </div>
                ` : `
                    <div class="cards-2">
                        ${manualPunchCard}
                        <div class="action-card ${(!payload.rule_bio_in && !payload.rule_bio_out) ? 'disabled' : ''}">
                            <h3>Biometric Options</h3>
                            <p>Use biometric actions only if they are enabled by the admin.</p>
                            <div class="inline-actions">
                                <form method="post">
                                    ${csrfInputMarkup()}
                                    <input type="hidden" name="action" value="employee_biometric">
                                    <input type="hidden" name="attend_date" value="${payload.date}">
                                    <input type="hidden" name="stamp_type" value="in">
                                    <button class="button ghost small" type="submit" disabled>Biometric In</button>
                                </form>
                                <form method="post">
                                    ${csrfInputMarkup()}
                                    <input type="hidden" name="action" value="employee_biometric">
                                    <input type="hidden" name="attend_date" value="${payload.date}">
                                    <input type="hidden" name="stamp_type" value="out">
                                    <button class="button ghost small" type="submit" disabled>Biometric Out</button>
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
            const manualGeoSession = Array.isArray(payload.sessions) ? payload.sessions.find(session => !!session.punch_in_path && (session.punch_in_lat || session.punch_in_lng)) : null;
            const isManualPunchRecord = !!payload.punch_in_path || (Array.isArray(payload.sessions) && payload.sessions.some(session => !!session.punch_in_path));
            const manualGeoLat = payload.punch_in_lat || (manualGeoSession ? manualGeoSession.punch_in_lat : '');
            const manualGeoLng = payload.punch_in_lng || (manualGeoSession ? manualGeoSession.punch_in_lng : '');
            const punchDetailsMarkup = isManualPunchRecord
                ? ((manualGeoLat || manualGeoLng) ? `Bio: ${escapeHtml(manualGeoLat || '-')}, ${escapeHtml(manualGeoLng || '-')}` : '')
                : `
                        <strong>Punch Details</strong><br>
                        Punch In: ${escapeHtml(payload.biometric_in_time || 'Not submitted')}<br>
                        Punch Out: ${escapeHtml(payload.biometric_out_time || 'Not submitted')}<br>
                        ${(payload.biometric_in_time || payload.biometric_out_time) ? 'Source: Imported biometric attendance' : 'Bio: Not available'}
                    `;
            modalContent.className = `modal-grid attendance-context-${payload.context}`;
            modalContent.innerHTML = `
                <section class="attendance-modal-hero">
                    <div>
                        <span class="eyebrow">${payload.view_mode === 'reimbursement' ? 'Reimbursement Details' : (payload.context === 'admin' ? 'Admin Attendance' : 'Employee Attendance')}</span>
                        <h2>${escapeHtml(payload.display_date)}</h2>
                        ${payload.view_mode !== 'reimbursement' ? `<p>Status: <span class="status-pill status-${escapeHtml(displayStatusClass)}">${escapeHtml(displayStatus)}</span></p>` : ''}
                    </div>
                    ${payload.view_mode !== 'reimbursement' ? `
                    <div class="attendance-modal-meta">
                        ${punchDetailsMarkup}
                    </div>
                    ` : ''}
                </section>
                ${(payload.view_mode !== 'reimbursement' && payload.context === 'admin') ? `
                <section class="attendance-modal-section">
                    <div class="split">
                        <h3>Sessions Handled</h3>
                        <span class="badge">${payload.sessions ? payload.sessions.length : 0} total</span>
                    </div>
                    <div class="list">${sessionMarkup}</div>
                </section>
                ` : ''}
                ${reimbursementMarkup}
                ${payload.view_mode !== 'reimbursement' ? `
                ${adminForm}
                ${employeeCards}
                ` : ''}
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
            const syncProjectFields = (form, force = false) => {
                if (!form) {
                    return;
                }

                const projectSelect = form.querySelector('[data-project-select]');
                if (!projectSelect) {
                    return;
                }

                const project = findProjectById(projectSelect.value);
                if (!project) {
                    return;
                }

                const setValue = (selector, value) => {
                    const field = form.querySelector(selector);
                    if (!field || value === '') {
                        return;
                    }
                    if (force || String(field.value || '').trim() === '') {
                        field.value = value;
                    }
                };

                setValue('[data-project-college]', String(project.college_name || ''));
                setValue('[data-project-session]', String(project.project_name || ''));
                setValue('[data-project-location]', String(project.location || ''));

            };

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

            document.querySelectorAll('form').forEach(form => {
                const projectSelect = form.querySelector('[data-project-select]');
                if (!projectSelect) {
                    return;
                }

                syncProjectFields(form, false);
                projectSelect.addEventListener('change', () => {
                    syncProjectFields(form, true);
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
        document.querySelectorAll('[data-employee-type-select]').forEach(select => {
            const form = select.closest('form');
            const empIdField = form ? form.querySelector('[data-contractual-emp-id-field]') : null;
            const empIdInput = empIdField ? empIdField.querySelector('input[name="emp_id"]') : null;
            const sync = () => {
                const isContractual = select.value === 'corporate';
                if (empIdField) {
                    empIdField.classList.toggle('hidden', isContractual);
                }
                if (empIdInput) {
                    empIdInput.required = !isContractual;
                    empIdInput.disabled = isContractual;
                    if (isContractual) {
                        empIdInput.value = '';
                    }
                }
            };
            select.addEventListener('change', sync);
            sync();
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
            const ruleCount = form.querySelectorAll('.rule-card input:checked, input[type="hidden"][name="manual_punch"], input[type="hidden"][name="biometric_punch"]').length;
            const employeeCount = form.matches('[data-employee-form]') ? form.querySelectorAll('input[name="employee_ids[]"]:checked').length : 1;
            const valid = form.matches('[data-project-allocation-form]') ? employeeCount > 0 : (ruleCount > 0 && employeeCount > 0);
            submits.forEach(submit => submit.classList.toggle('ghost', !valid));
        }
        document.querySelectorAll('form[data-rule-form]').forEach(form => {
            form.querySelectorAll('.rule-card input, input[name="employee_ids[]"], input[type="hidden"][name="manual_punch"], input[type="hidden"][name="biometric_punch"]').forEach(input => {
                input.addEventListener('change', () => updateRuleSubmit(form));
            });
            updateRuleSubmit(form);
        });
        document.querySelectorAll('[data-project-date-toggle]').forEach(input => {
            const sync = () => {
                const card = input.closest('.project-option-card');
                const fields = card ? card.querySelector('[data-project-date-fields]') : null;
                if (!fields) {
                    return;
                }
                fields.classList.toggle('hidden', !input.checked);
            };
            input.addEventListener('change', sync);
            sync();
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
        const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
        const sidebar = document.querySelector('[data-sidebar]');
        const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
        const closeSidebar = () => {
            document.body.classList.remove('sidebar-open');
            if (sidebarToggle) {
                sidebarToggle.setAttribute('aria-expanded', 'false');
            }
            if (sidebarBackdrop) {
                sidebarBackdrop.hidden = true;
                sidebarBackdrop.classList.remove('is-visible');
            }
        };
        const openSidebar = () => {
            document.body.classList.add('sidebar-open');
            if (sidebarToggle) {
                sidebarToggle.setAttribute('aria-expanded', 'true');
            }
            if (sidebarBackdrop) {
                sidebarBackdrop.hidden = false;
                sidebarBackdrop.classList.add('is-visible');
            }
        };
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                if (document.body.classList.contains('sidebar-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
            sidebar.querySelectorAll('a, button[type="submit"]').forEach(item => {
                item.addEventListener('click', () => {
                    if (window.innerWidth <= 760) {
                        closeSidebar();
                    }
                });
            });
        }
        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', closeSidebar);
        }
        window.addEventListener('resize', () => {
            if (window.innerWidth > 760) {
                closeSidebar();
            }
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
                closeSidebar();
            }
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
            const adminShellRoles = ['admin', 'freelancer', 'external_vendor'];
            const settingsModal = document.getElementById(adminShellRoles.includes(role) ? 'admin-profile-settings-modal' : 'employee-profile-settings-modal');
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
        
        // Notification dismiss functionality
        document.querySelectorAll('[data-dismiss-notification]').forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                const notificationId = btn.dataset.dismissNotification;
                const item = btn.closest('.sidebar-notification-item');
                const form = item ? item.querySelector('.notification-dismiss-form') : null;
                
                if (form && item) {
                    // Animate out before dismissing
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(20px)';
                    item.style.transition = 'all 0.2s ease-out';
                    item.style.pointerEvents = 'none';
                    
                    // Get form data and submit via AJAX
                    const formData = new FormData(form);
                    formData.append('ajax', '1');
                    
                    setTimeout(() => {
                        fetch(form.action || window.location.href, {
                            method: 'POST',
                            body: formData
                        }).then(() => {
                            // Remove the item from DOM after animation
                            item.remove();
                            
                            // Update unread count badge
                            const badge = document.querySelector('.sidebar-notifications .badge');
                            if (badge) {
                                const count = Math.max(0, parseInt(badge.textContent) - 1);
                                badge.textContent = count + ' unread';
                                if (count === 0) {
                                    badge.textContent = '0 unread';
                                }
                            }
                        }).catch(error => {
                            console.error('Failed to dismiss notification:', error);
                            // Revert animation on error
                            item.style.opacity = '1';
                            item.style.transform = 'translateX(0)';
                            item.style.pointerEvents = 'auto';
                        });
                    }, 150);
                }
            });
        });
