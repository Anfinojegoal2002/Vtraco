<?php

declare(strict_types=1);

function render_landing(): void
{
    $landingAdminCount = admin_count();
    $landingEmployeeCount = employee_count();
    $landingAttendanceCount = (int) db()->query('SELECT COUNT(*) FROM attendance_records')->fetchColumn();
    render_header('Welcome', 'landing');
    ?>
    <section class="landing-page">
        <a class="landing-scroll-button" href="#how-it-works">How It Works?</a>
        <section class="landing-hero">
            <div class="landing-grid">
                <div class="landing-copy">
                    <span class="eyebrow">Employee Attendance and Payroll Management System</span>
                    <h1><span>Smart</span><span>Attendance.</span><span class="nowrap">Effortless Payroll.</span></h1>
                    <p>Manage your workforce, track attendance in real time, and automate salary calculations, all in one place built for growing teams that value control, accuracy, and simplicity.</p>
                    <div class="landing-cta">
                                                <button class="button solid landing-button" type="button" data-modal-target="admin-login-modal">
                            <strong>Admin Login</strong>
                        </button>
                                                <button class="button secondary landing-button" type="button" data-modal-target="employee-login-modal">
                            <strong>Employee Login</strong>
                        </button>
                    </div>
                    <div class="trust-line">Trusted by growing teams to manage attendance and payroll seamlessly.</div>
                </div>
                <div class="device-mockup reveal">
                    <div class="mockup-screen">
                        <div class="mock-top">
                            <span class="mock-dot"></span>
                            <span class="mock-dot"></span>
                            <span class="mock-dot"></span>
                        </div>
                        <div class="mock-grid">
                            <div class="mock-card">
                                <span class="eyebrow">Attendance Overview</span>
                                <h3>April Calendar</h3>
                                <div class="mock-calendar">
                                    <div class="mock-cell active"></div>
                                    <div class="mock-cell"></div>
                                    <div class="mock-cell"></div>
                                    <div class="mock-cell active"></div>
                                    <div class="mock-cell"></div>
                                    <div class="mock-cell active"></div>
                                    <div class="mock-cell"></div>
                                    <div class="mock-cell"></div>
                                </div>
                            </div>
                            <div class="mock-card">
                                <span class="eyebrow">Team Snapshot</span>
                                <h3>128 Employees</h3>
                                <p>Live attendance sync, salary progress, and employee rules in one dashboard.</p>
                            </div>
                        </div>
                    </div>
                    <div class="hero-glow"></div>
                </div>
            </div>
        </section>

        <section class="marketing-section">
            <div class="marketing-wrap">
                <div class="section-heading reveal">
                    <span class="eyebrow">Features</span>
                    <h2>Everything You Need to Manage Your Team</h2>
                    <p>From attendance capture to payroll visibility, V Traco keeps the whole workflow in one secure place.</p>
                </div>
                <div class="feature-grid">
                    <article class="feature-card reveal">
                        <div class="feature-icon">RT</div>
                        <h3>Real-Time Attendance Tracking</h3>
                        <p>Monitor presence, leave, half days, and week offs through a clear interactive calendar.</p>
                    </article>
                    <article class="feature-card reveal">
                        <div class="feature-icon">SP</div>
                        <h3>Smart Payroll Calculation</h3>
                        <p>Automatically calculate salaries based on working days and employee salary structures.</p>
                    </article>
                    <article class="feature-card reveal">
                        <div class="feature-icon">RB</div>
                        <h3>Role-Based Access</h3>
                        <p>Give admins and employees dedicated experiences with the right controls for each role.</p>
                    </article>
                    <article class="feature-card reveal">
                        <div class="feature-icon">EM</div>
                        <h3>Instant Email Notifications</h3>
                        <p>Deliver employee credentials and rule updates the moment onboarding or changes happen.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="marketing-section" id="how-it-works" style="background:rgba(255,255,255,0.5);">
            <div class="marketing-wrap">
                <div class="section-heading reveal">
                    <span class="eyebrow">How It Works</span>
                    <h2>Get Started in 3 Simple Steps</h2>
                </div>
                <div class="steps-row">
                    <article class="step-card reveal">
                        <div class="step-number">1</div>
                        <h3>Admin Adds Employees</h3>
                        <p>Admins onboard employees manually or by CSV import, and the employee email and password are sent through email.</p>
                    </article>
                    <article class="step-card reveal">
                        <div class="step-number">2</div>
                        <h3>Assign Attendance Rules</h3>
                        <p>Set manual or biometric punch permissions and control the exact attendance flow.</p>
                    </article>
                    <article class="step-card reveal">
                        <div class="step-number">3</div>
                        <h3>Track and Calculate Payroll</h3>
                        <p>Review attendance trends and let salary totals update automatically from working days.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="stats-band">
            <div class="stats-grid">
                <div class="stat-item reveal">
                    <strong data-counter="<?= (int) $landingAdminCount ?>"><?= (int) $landingAdminCount ?></strong>
                    <span>Admins</span>
                </div>
                <div class="stat-item reveal">
                    <strong data-counter="<?= (int) $landingEmployeeCount ?>"><?= (int) $landingEmployeeCount ?></strong>
                    <span>Employees Managed</span>
                </div>
                <div class="stat-item reveal">
                    <strong data-counter="<?= (int) $landingAttendanceCount ?>"><?= (int) $landingAttendanceCount ?></strong>
                    <span>Attendance Records</span>
                </div>
            </div>
        </section>

        <footer class="landing-footer">
            <div class="landing-footer-wrap">
                <div>
                    <a class="brand" href="<?= h(BASE_URL) ?>">
                        <span class="brand-mark">VT</span>
                        <span class="brand-copy"><strong style="color:#fff;">V Traco</strong><small style="color:rgba(219,234,254,0.72);">Attendance & Payroll</small></span>
                    </a>
                    <p>Designed to simplify attendance capture, payroll accuracy, and workforce accountability.</p>
                </div>
                <div>
                    <h3>Connect</h3>
                    <div class="social-links">
                        <a href="#">LinkedIn</a>
                        <a href="#">X</a>
                        <a href="#">Instagram</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">&copy; <?= date('Y') ?> V Traco. All rights reserved.</div>
        </footer>
    </section>
    <?php
    render_footer();
}
