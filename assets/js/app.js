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

        function projectDayPortionLabel(project) {
            const sessionType = String(project && project.session_type ? project.session_type : 'FULL_DAY').trim().toUpperCase();
            return ['FIRST_HALF', 'SECOND_HALF'].includes(sessionType) ? 'Half Day' : 'Full Day';
        }

        function detailValue(value, fallback = '-') {
            const text = String(value ?? '').trim();
            return text !== '' ? text : fallback;
        }

        function punchDetailRow(label, value) {
            return `
                <div class="session-detail-row">
                    <strong>${escapeHtml(label)}</strong>
                    <span>${escapeHtml(detailValue(value, 'Not recorded'))}</span>
                </div>
            `;
        }

        function punchPhotoMarkup(src, label = 'Punch Photo') {
            return src ? `
                <div class="session-proof">
                    <strong>${escapeHtml(label)}</strong>
                    <img class="session-proof-image" src="${escapeHtml(src)}" alt="${escapeHtml(label)}">
                </div>
            ` : '';
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

        function bindModalCloseHandlers(root) {
            if (!root) {
                return;
            }
            root.querySelectorAll('[data-close-modal]').forEach(button => {
                button.addEventListener('click', () => closeModal(button.closest('.modal')));
            });
        }

        function openAttendanceModal(payload) {
            if (payload.context === 'employee' && payload.status === 'Week Off') {
                return;
            }

            const displayStatus = payload.status || 'Not Marked';
            const displayStatusClass = payload.status ? payload.status.replace(/\s+/g, '-') : 'Unmarked';

            const dateProjects = availableProjectsForDate(payload.date);
            const existingProjectRecords = Array.isArray(payload.sessions) ? payload.sessions : [];
            const existingProjectIds = new Set(existingProjectRecords.map(record => Number(record.project_id || 0)).filter(Boolean));
            const browserAssignedProjectRecords = dateProjects.map(project => ({
                id: '',
                project_id: project.id || '',
                college_name: project.college_name || '',
                session_name: project.project_name || '',
                topics_handled: '',
                total_students: '',
                present_students: '',
                punch_in_path: '',
                punch_in_time: '',
                punch_out_time: '',
                punch_in_lat: '',
                punch_in_lng: '',
                location: project.location || '',
                day_portion: projectDayPortionLabel(project),
            }));
            const assignedProjectRecords = browserAssignedProjectRecords.filter(record => !existingProjectIds.has(Number(record.project_id || 0)));
            const employeeProjectRecords = [...existingProjectRecords, ...assignedProjectRecords].slice(0, 3);
            const projectRecords = payload.context === 'employee' ? employeeProjectRecords : existingProjectRecords;
            const hasProjectRecords = projectRecords.length > 0;
            const projectRecordMarkup = projectRecords.map((record, index) => {
                    const totalStudents = Number(record.total_students || 0);
                    const presentStudents = Number(record.present_students || 0);
                    const absentStudents = totalStudents > 0
                        ? Math.max(totalStudents - presentStudents, 0)
                        : '';
                    const imageMarkup = record.punch_in_path
                        ? `
                            <div class="session-proof">
                                <strong>GPS Photo</strong>
                                <img class="session-proof-image" src="${escapeHtml(record.punch_in_path)}" alt="GPS photo for ${escapeHtml(record.session_name || 'project record')}">
                            </div>
                        `
                        : '';
                    const projectRecordForm = payload.context === 'employee'
                        ? `
                            <form method="post" enctype="multipart/form-data" class="stack-form project-record-form" data-validate>
                                ${csrfInputMarkup()}
                                <input type="hidden" name="action" value="employee_project_record">
                                <input type="hidden" name="attend_date" value="${escapeHtml(payload.date)}">
                                <div class="field"><label>Project<div class="field-row"><select name="project_id" data-project-select required>${projectOptionsMarkup(record.project_id, payload.date)}</select></div></label><small class="field-error"><span>!</span>Project is required.</small></div>
                                <input type="hidden" name="latitude" class="geo-lat">
                                <input type="hidden" name="longitude" class="geo-lng">
                                <div class="field"><label>College Name<div class="field-row"><input type="text" name="college_name" data-project-college value="${escapeHtml(record.college_name)}" required></div></label><small class="field-error"><span>!</span>College name is required.</small></div>
                                <div class="field"><label>Subject<div class="field-row"><input type="text" name="session_name" data-project-session value="${escapeHtml(record.session_name)}" required></div></label><small class="field-error"><span>!</span>Subject is required.</small></div>
                                <div class="field"><label>Day Type<div class="field-row"><select name="day_portion" data-project-day-portion required><option value="Full Day" ${String(record.day_portion || 'Full Day') === 'Full Day' ? 'selected' : ''}>Full Day</option><option value="Half Day" ${String(record.day_portion || '') === 'Half Day' ? 'selected' : ''}>Half Day</option></select></div></label><small class="field-error"><span>!</span>Day type is required.</small></div>
                                <div class="field"><label>Topics Handled<div class="field-row"><textarea name="topics_handled" rows="3" required>${escapeHtml(record.topics_handled)}</textarea></div></label><small class="field-error"><span>!</span>Topics handled is required.</small></div>
                                <div class="field"><label>Total No of Students<div class="field-row"><input type="number" name="total_students" min="1" step="1" value="${escapeHtml(record.total_students)}" required></div></label><small class="field-error"><span>!</span>Total students is required.</small></div>
                                <div class="field"><label>Present<div class="field-row"><input type="number" name="present_students" min="0" step="1" value="${escapeHtml(record.present_students)}" required></div></label><small class="field-error"><span>!</span>Present count is required.</small></div>
                                <div class="field"><label>GPS Photo<div class="field-row"><input type="file" name="gps_photo" accept="image/*" ${record.id ? '' : 'required'}></div></label><small class="field-error"><span>!</span>${record.id ? 'Upload only if you want to replace the existing GPS photo.' : 'GPS photo is required.'}</small></div>
                                <input type="hidden" name="location" data-project-location value="${escapeHtml(record.location || '')}">
                                <p class="hint geo-hint">Location will be captured when this popup opens.</p>
                                <div class="project-record-actions">
                                    <button class="button ghost" type="button" data-next-project>Next Project</button>
                                    <button class="button solid" type="submit">Submit</button>
                                </div>
                            </form>
                        `
                        : '';

                    return `
                        <div class="list-item session-detail-card">
                            <div class="split">
                                <strong>Project Record ${index + 1}</strong>
                            </div>
                            ${payload.context !== 'employee' ? `<div class="session-detail-grid">
                                <div class="session-detail-row"><strong>College Name</strong><span>${escapeHtml(detailValue(record.college_name))}</span></div>
                                <div class="session-detail-row"><strong>Subject</strong><span>${escapeHtml(detailValue(record.session_name))}</span></div>
                                <div class="session-detail-row"><strong>Day Type</strong><span>${escapeHtml(detailValue(record.day_portion))}</span></div>
                                <div class="session-detail-row"><strong>Topics Handled</strong><span>${escapeHtml(detailValue(record.topics_handled))}</span></div>
                                <div class="session-detail-row"><strong>Total No of Students</strong><span>${escapeHtml(detailValue(record.total_students))}</span></div>
                                <div class="session-detail-row"><strong>Present</strong><span>${escapeHtml(detailValue(record.present_students))}</span></div>
                                <div class="session-detail-row"><strong>Absent</strong><span>${escapeHtml(detailValue(absentStudents))}</span></div>
                            </div>` : projectRecordForm}
                            ${imageMarkup}
                        </div>
                    `;
                }).join('');

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
            let employeeReimbursementForm = '';
            const reimbursementAvailable = !payload.reimbursement || payload.reimbursement.available !== false;
            if (reimbursementAvailable && payload.context === 'employee' && payload.view_mode !== 'reimbursement') {
                const reimbursementLocked = !!(payload.reimbursement && payload.reimbursement.locked);
                const reimbursementFuture = !!(payload.reimbursement && payload.reimbursement.future);
                const reimbursementCurrentMonth = !!(payload.reimbursement && payload.reimbursement.current_month);
                const hasApprovedReimbursement = !!(payload.reimbursement && Array.isArray(payload.reimbursement.items) && payload.reimbursement.items.some(item => ['APPROVED', 'PARTIALLY PAID', 'PAID'].includes(String(item.status || '').toUpperCase())));
                const reimbursementDisabled = reimbursementLocked || reimbursementFuture || !reimbursementCurrentMonth || hasApprovedReimbursement;
                const disabledAttr = reimbursementDisabled ? 'disabled' : '';
                const reimbursementHint = reimbursementLocked
                    ? 'You can submit up to 3 reimbursement requests for this date.'
                    : (reimbursementFuture
                        ? 'Future dates are not allowed for reimbursement claims.'
                        : (!reimbursementCurrentMonth
                            ? 'Reimbursements can only be submitted for dates in the current month.'
                            : (hasApprovedReimbursement
                                ? 'Reimbursement data cannot be updated once it is approved.'
                                : 'Submit food, travel, or accommodation expenses for this date.')));

                employeeReimbursementForm = `
                    <section class="attendance-modal-section">
                        <div class="split">
                            <h3>Employee Reimbursement</h3>
                            <span class="badge">${escapeHtml(payload.display_date)}</span>
                        </div>
                        <p class="hint">${escapeHtml(reimbursementHint)}</p>
                        <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                            ${csrfInputMarkup()}
                            <input type="hidden" name="action" value="employee_submit_reimbursement">
                            <input type="hidden" name="expense_date" value="${escapeHtml(payload.date)}">
                            <input type="hidden" name="return_page" value="employee_log">
                            <input type="hidden" name="return_month" value="${escapeHtml(String(payload.date || '').slice(0, 7))}">
                            <div class="field">
                                <label>Category</label>
                                <div class="reimbursement-radio-group">
                                    ${['FOOD', 'TRAVEL', 'ACCOMMODATION'].map(category => `
                                        <label class="reimbursement-radio">
                                            <input type="radio" name="category" value="${category}" required ${disabledAttr}>
                                            <span>${category}</span>
                                        </label>
                                    `).join('')}
                                </div>
                                <small class="field-error"><span>!</span>Please choose a category.</small>
                            </div>
                            <div class="field">
                                <label>Expense Description</label>
                                <div class="field-row"><textarea name="expense_description" rows="4" placeholder="Explain the expense briefly." required ${disabledAttr}></textarea></div>
                                <small class="field-error"><span>!</span>Description is required.</small>
                            </div>
                            <div class="field">
                                <label>Amount</label>
                                <div class="field-row"><input type="number" name="amount_requested" min="0.01" step="0.01" placeholder="Enter amount" required ${disabledAttr}></div>
                                <small class="field-error"><span>!</span>Enter a valid amount.</small>
                            </div>
                            <div class="field">
                                <label>Upload File (JPG/PDF, max 5MB)</label>
                                <div class="field-row"><input type="file" name="attachment" accept=".jpg,.jpeg,.pdf,image/jpeg,application/pdf" required ${disabledAttr}></div>
                                <small class="field-error"><span>!</span>Upload a JPG or PDF file up to 5MB.</small>
                            </div>
                            <button class="button solid" type="submit" ${disabledAttr}>Submit Reimbursement</button>
                        </form>
                    </section>
                `;
            }

            let adminForm = '';
            let adminPunchSection = '';
            const currentShellRole = document.querySelector('[data-profile-card]')?.dataset.profileRole || '';
            const isVendorShell = currentShellRole === 'external_vendor';
            if (payload.context === 'admin') {
                const manualSessionRows = projectRecords
                    .filter(record => record.punch_in_time || record.punch_out_time || record.punch_in_path || record.punch_in_lat || record.punch_in_lng || record.location)
                    .map(record => {
                        const title = detailValue(record.slot_name || record.session_name || record.college_name, 'Manual Punch');
                        return `
                            <div class="list-item session-detail-card">
                                <div class="split"><strong>${escapeHtml(title)}</strong></div>
                                <div class="session-detail-grid">
                                    ${punchDetailRow('Manual In', record.punch_in_time)}
                                    ${punchDetailRow('Manual Out', record.punch_out_time)}
                                    ${record.location ? punchDetailRow('Location', record.location) : ''}
                                    ${record.punch_in_lat || record.punch_in_lng ? punchDetailRow('Coordinates', `${detailValue(record.punch_in_lat)} / ${detailValue(record.punch_in_lng)}`) : ''}
                                </div>
                                ${punchPhotoMarkup(record.punch_in_path, 'Manual Punch Photo')}
                            </div>
                        `;
                    }).join('');
                const hasTopLevelManual = !!(payload.punch_in_time || payload.punch_out_time || payload.punch_in_path || payload.punch_in_lat || payload.punch_in_lng);
                const topLevelManualMarkup = hasTopLevelManual ? `
                    <div class="list-item session-detail-card">
                        <div class="split"><strong>Manual Punch</strong></div>
                        <div class="session-detail-grid">
                            ${punchDetailRow('Manual In', payload.punch_in_time)}
                            ${punchDetailRow('Manual Out', payload.punch_out_time)}
                            ${payload.punch_in_lat || payload.punch_in_lng ? punchDetailRow('Coordinates', `${detailValue(payload.punch_in_lat)} / ${detailValue(payload.punch_in_lng)}`) : ''}
                        </div>
                        ${punchPhotoMarkup(payload.punch_in_path, 'Manual Punch Photo')}
                    </div>
                ` : '';

                adminPunchSection = `
                    <section class="attendance-modal-section">
                        <div class="split">
                            <h3>Punch Details</h3>
                            <span class="badge">${escapeHtml(payload.display_date)}</span>
                        </div>
                        <div class="list">
                            <div class="list-item session-detail-card">
                                <div class="split"><strong>Biometric Punch</strong></div>
                                <div class="session-detail-grid">
                                    ${punchDetailRow('Biometric In', payload.biometric_in_time)}
                                    ${punchDetailRow('Biometric Out', payload.biometric_out_time)}
                                </div>
                            </div>
                            ${topLevelManualMarkup}
                            ${manualSessionRows}
                            ${(!topLevelManualMarkup && !manualSessionRows && !payload.biometric_in_time && !payload.biometric_out_time) ? '<p class="hint">No punch details recorded for this date.</p>' : ''}
                        </div>
                    </section>
                `;
                if (!isVendorShell) {
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
            }

            let employeeCards = '';
            if (payload.context === 'employee') {
                const isWeekOff = payload.status === 'Week Off';
                const isFuture = !!payload.future;

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
            const projectRecordSection = (payload.view_mode !== 'reimbursement' && projectRecords.length) ? `
                <section class="attendance-modal-section">
                    <div class="split">
                        <h3>Project Record :-</h3>
                    </div>
                    <div class="list">${projectRecordMarkup}</div>
                </section>
            ` : '';
            const reimbursementSection = `${reimbursementMarkup}${employeeReimbursementForm}`;
            const employeeChooser = (reimbursementAvailable && payload.context === 'employee' && payload.view_mode !== 'reimbursement') ? `
                <section class="attendance-modal-section">
                    <div class="cards-2">
                        ${hasProjectRecords ? `<button class="action-card" type="button" data-attendance-detail-button="project">
                            <h3>Project Record</h3>
                            <p>Open project record details and mark attendance.</p>
                        </button>` : ''}
                        <button class="action-card" type="button" data-attendance-detail-button="reimbursement">
                            <h3>Employee Reimbursement</h3>
                            <p>Open reimbursement requests and submit a claim.</p>
                        </button>
                    </div>
                </section>
                ${hasProjectRecords ? `<div class="attendance-detail-popup hidden" data-attendance-detail-section="project">
                    <div class="attendance-detail-popup-card">
                        <button class="modal-close attendance-detail-close" type="button" data-attendance-detail-close>&times;</button>
                        ${projectRecordSection}
                    </div>
                </div>` : ''}
                <div class="attendance-detail-popup hidden" data-attendance-detail-section="reimbursement">
                    <div class="attendance-detail-popup-card">
                        <button class="modal-close attendance-detail-close" type="button" data-attendance-detail-close>&times;</button>
                        ${reimbursementSection}
                    </div>
                </div>
            ` : '';
            modalContent.className = `modal-grid attendance-context-${payload.context}`;
            modalContent.innerHTML = `
                <section class="attendance-modal-hero">
                    <div>
                        <span class="eyebrow">${payload.view_mode === 'reimbursement' ? 'Reimbursement Details' : (payload.context === 'admin' ? 'Admin Attendance' : 'Employee Attendance')}</span>
                        <h2>${escapeHtml(payload.display_date)}</h2>
                        ${payload.view_mode !== 'reimbursement' ? `<p>Status: <span class="status-pill status-${escapeHtml(displayStatusClass)}">${escapeHtml(displayStatus)}</span></p>` : ''}
                    </div>
                </section>
                ${payload.context === 'employee' ? employeeChooser : projectRecordSection}
                ${adminPunchSection}
                ${payload.context === 'employee' ? '' : reimbursementMarkup}
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
            document.querySelectorAll('[data-attendance-detail-button]').forEach(button => {
                button.addEventListener('click', () => {
                    const target = button.dataset.attendanceDetailButton || '';
                    document.querySelectorAll('[data-attendance-detail-section]').forEach(section => {
                        section.classList.toggle('hidden', section.dataset.attendanceDetailSection !== target);
                    });
                    document.querySelectorAll('[data-attendance-detail-button]').forEach(item => {
                        item.classList.toggle('active', item === button);
                    });
                });
            });
            document.querySelectorAll('[data-attendance-detail-close]').forEach(button => {
                button.addEventListener('click', () => {
                    const popup = button.closest('[data-attendance-detail-section]');
                    if (popup) {
                        popup.classList.add('hidden');
                    }
                    document.querySelectorAll('[data-attendance-detail-button]').forEach(item => item.classList.remove('active'));
                });
            });

            document.querySelectorAll('[data-next-project]').forEach(button => {
                const card = button.closest('.session-detail-card');
                const nextCard = card ? card.nextElementSibling : null;
                if (!nextCard || !nextCard.classList.contains('session-detail-card')) {
                    button.disabled = true;
                    button.textContent = 'Next Project';
                }

                button.addEventListener('click', () => {
                    const currentCard = button.closest('.session-detail-card');
                    const targetCard = currentCard ? currentCard.nextElementSibling : null;
                    if (!targetCard || !targetCard.classList.contains('session-detail-card')) {
                        return;
                    }

                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    const firstField = targetCard.querySelector('input:not([type="hidden"]), textarea, select');
                    if (firstField) {
                        firstField.focus({ preventScroll: true });
                    }
                });
            });

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
                setValue('[data-project-day-portion]', projectDayPortionLabel(project));

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
        bindModalCloseHandlers(document);
        if (modal) {
            modal.addEventListener('click', event => {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        }
        document.querySelectorAll('[data-lazy-employee-details]').forEach(button => {
            button.addEventListener('click', async () => {
                const employeeId = button.dataset.lazyEmployeeDetails || '';
                const modalId = button.dataset.modalId || `employee-rules-modal-${employeeId}`;
                if (document.getElementById(modalId)) {
                    openModalById(modalId);
                    return;
                }

                const originalText = button.textContent;
                button.disabled = true;
                button.textContent = 'Loading...';
                try {
                    const response = await fetch(`?action=admin_employee_details_modal&employee_id=${encodeURIComponent(employeeId)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload.success || !payload.html) {
                        throw new Error(payload.message || 'Unable to load employee details.');
                    }

                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = payload.html;
                    const loadedModal = wrapper.firstElementChild;
                    if (!loadedModal) {
                        throw new Error('Employee details response was empty.');
                    }
                    document.body.appendChild(loadedModal);
                    bindModalCloseHandlers(loadedModal);
                    loadedModal.addEventListener('click', event => {
                        if (event.target === loadedModal) {
                            closeModal(loadedModal);
                        }
                    });
                    openModalById(modalId);
                } catch (error) {
                    alert(error.message || 'Unable to load employee details.');
                } finally {
                    button.disabled = false;
                    button.textContent = originalText;
                }
            });
        });
        document.querySelectorAll('[data-modal-target]').forEach(button => {
            button.addEventListener('click', () => openModalById(button.dataset.modalTarget));
        });
        document.querySelectorAll('[data-switch-modal-target]').forEach(button => {
            button.addEventListener('click', () => {
                closeModal(button.closest('.modal'));
                openModalById(button.dataset.switchModalTarget);
            });
        });
        document.querySelectorAll('[data-project-confirmation-form]').forEach(form => {
            form.querySelectorAll('[data-signature-upload]').forEach(input => {
                input.addEventListener('change', () => {
                    const file = input.files && input.files[0] ? input.files[0] : null;
                    if (!file || !file.type.startsWith('image/')) {
                        return;
                    }
                    const key = input.dataset.signatureUpload || '';
                    const signatureType = input.dataset.signatureType || 'authorized';
                    const editor = Array.from(form.querySelectorAll('[data-confirmation-editor]'))
                        .find(item => (item.dataset.confirmationEditor || '') === key);
                    if (!editor) {
                        return;
                    }
                    const reader = new FileReader();
                    reader.addEventListener('load', () => {
                        const slot = signatureType === 'trainer'
                            ? editor.querySelector('[data-trainer-signature-slot]')
                            : editor.querySelector('[data-signature-slot]');
                        if (!slot) {
                            return;
                        }
                        const image = document.createElement('img');
                        image.className = 'karyoun-signature-image';
                        image.src = String(reader.result || '');
                        image.alt = signatureType === 'trainer' ? 'Trainer signature' : 'Authorized signature';
                        slot.innerHTML = '';
                        slot.append('Signature: ');
                        slot.appendChild(image);
                    });
                    reader.readAsDataURL(file);
                });
            });
            form.addEventListener('submit', () => {
                form.querySelectorAll('[data-confirmation-editor]').forEach(editor => {
                    const key = editor.dataset.confirmationEditor || '';
                    const target = Array.from(form.querySelectorAll('[data-confirmation-html]'))
                        .find(input => (input.dataset.confirmationHtml || '') === key);
                    if (target) {
                        target.value = editor.innerHTML;
                    }
                });
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
            const contractualHiddenFields = form ? [...form.querySelectorAll('[data-contractual-hidden-field]')] : [];
            const sync = () => {
                const isContractual = select.value === 'corporate';
                const isVendorEmployee = select.value === 'vendor';
                const hideManagedFields = isContractual || isVendorEmployee;
                contractualHiddenFields.forEach(field => {
                    field.classList.toggle('hidden', hideManagedFields);
                    field.querySelectorAll('input, select, textarea').forEach(input => {
                        input.disabled = hideManagedFields;
                        if (hideManagedFields) {
                            input.dataset.wasRequired = input.required ? '1' : '';
                            input.required = false;
                            if (input.name === 'emp_id') {
                                input.value = '';
                            }
                        } else if (input.dataset.wasRequired === '1') {
                            input.required = true;
                        }
                    });
                });
                if (empIdInput && !hideManagedFields) {
                    empIdInput.required = true;
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
            const linkedFilters = [...document.querySelectorAll(`[data-table-filter="${filterInput.dataset.tableFilter}"]`)];
            const rows = [...tableBody.querySelectorAll('[data-filter-row]')];
            const totalCount = rows.length;
            const update = () => {
                let visibleCount = 0;
                rows.forEach(row => {
                    const matches = linkedFilters.every(input => {
                        const term = input.value.trim().toLowerCase();
                        if (term === '') {
                            return true;
                        }
                        const key = input.dataset.filterKey || 'filterText';
                        const haystack = (row.dataset[key] || row.dataset.filterText || row.textContent || '').toLowerCase();
                        return haystack.includes(term);
                    });
                    row.style.display = matches ? '' : 'none';
                    if (matches) {
                        visibleCount += 1;
                    }
                });
                if (emptyState) {
                    emptyState.classList.toggle('hidden', visibleCount !== 0);
                }
                if (countTarget) {
                    const hasTerm = linkedFilters.some(input => input.value.trim() !== '');
                    countTarget.textContent = hasTerm ? visibleCount + ' of ' + totalCount : totalCount + ' total';
                }
            };
            filterInput.addEventListener('input', update);
            filterInput.addEventListener('change', update);
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
            const valid = form.matches('[data-project-allocation-form], [data-time-allocation-form], [data-power-access-form]') ? employeeCount > 0 : (ruleCount > 0 && employeeCount > 0);
            submits.forEach(submit => submit.classList.toggle('ghost', !valid));
        }
        document.querySelectorAll('form[data-rule-form]').forEach(form => {
            form.querySelectorAll('.rule-card input, input[name="employee_ids[]"], .power-access-rule input, input[type="hidden"][name="manual_punch"], input[type="hidden"][name="biometric_punch"], select[name="shift"], input[name="shift_from"], input[name="shift_to"], input[name="employee_from"], input[name="employee_to"]').forEach(input => {
                input.addEventListener('change', () => updateRuleSubmit(form));
                input.addEventListener('input', () => updateRuleSubmit(form));
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
        document.querySelectorAll('[data-confirm-vendor-delete]').forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.dataset.vendorDeleteModal || '';
                const modal = document.getElementById(modalId);
                if (!modal) {
                    return;
                }
                const vendorIdInput = modal.querySelector('input[name="vendor_id"]');
                const vendorName = modal.querySelector('[data-vendor-delete-name]');
                if (vendorIdInput) {
                    vendorIdInput.value = button.dataset.vendorId || '';
                }
                if (vendorName) {
                    vendorName.textContent = button.dataset.vendorName || 'this vendor';
                }
                openModalById(modalId);
            });
        });
        document.querySelectorAll('.flash').forEach((toast, index) => {
            const closeFlash = () => {
                toast.style.opacity = '0';
                toast.style.transform = 'scale(0.96)';
                setTimeout(() => {
                    const stack = toast.closest('.flash-stack');
                    toast.remove();
                    if (stack && !stack.querySelector('.flash')) {
                        stack.remove();
                    }
                }, 220);
            };
            const okButton = toast.querySelector('.flash-ok');
            if (okButton) {
                okButton.addEventListener('click', closeFlash);
            }
            setTimeout(() => {
                closeFlash();
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
            if (!card) {
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
                        applyPhoto(result);
                        if (status) {
                            status.textContent = 'Profile photo selected. Save profile to store it.';
                        }
                    } catch (error) {
                        if (status) {
                            status.textContent = 'Unable to preview this image.';
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
