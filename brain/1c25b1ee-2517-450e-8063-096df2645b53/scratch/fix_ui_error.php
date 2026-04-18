<?php
$file = 'assets/css/app.css';
$content = file_get_contents($file);

// REVERT GLOBAL STYLES
$content = preg_replace(
    '/\.panel,\s*\.section-block,\s*\.calendar-shell,\s*\.table-wrap,\s*\.metric-card\s*\{\s*padding:\s*14px\s*18px;\s*\}/',
    '.panel, .section-block, .calendar-shell, .table-wrap, .metric-card { padding: 24px; }',
    $content
);

$content = preg_replace(
    '/h1\s*\{\s*font-size:\s*1\.25rem;\s*line-height:\s*1\.1;\s*\}/',
    'h1 { font-size: clamp(1.45rem, 2.1vw, 2.15rem); line-height: 1.12; }',
    $content
);

$content = preg_replace(
    '/\.button\s*\{\s*display:\s*inline-flex;[^}]+padding:\s*6px\s*14px;[^}]+\}/',
    '.button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 24px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all .2s ease; font-size: 0.94rem; border: 2px solid transparent; min-height: 48px; }',
    $content
);

$content = preg_replace(
    '/\.scroll-panel\s*\{\s*max-height:\s*none;\s*overflow:\s*visible;\s*\}/',
    '.scroll-panel { max-height: 620px; overflow: auto; }',
    $content
);

$content = preg_replace(
    '/\.admin-main,\s*\.employee-main\s*\{\s*min-width:\s*0;\s*display:\s*flex;\s*flex-direction:\s*column;\s*gap:\s*10px;\s*padding:\s*12px;\s*height:\s*100vh;\s*overflow:\s*hidden;\s*\}/',
    '.admin-main, .employee-main { min-width: 0; display: flex; flex-direction: column; gap: 18px; padding: 18px; height: 100vh; overflow: hidden; }',
    $content
);

$content = preg_replace(
    '/\.attendance-header\s*\{\s*display:\s*flex;\s*justify-content:\s*space-between;\s*align-items:\s*center;\s*margin-bottom:\s*6px;\s*\}/',
    '.attendance-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }',
    $content
);

// APPLY SPECIFIC CSS
$specific_css = "
            /* Attendance Page Specific Shrinkage */
            .admin-attendance-page .panel, 
            .admin-attendance-page .section-block, 
            .admin-attendance-page .calendar-shell { padding: 10px 14px !important; }
            .admin-attendance-page h1 { font-size: 1.1rem !important; }
            .admin-attendance-page .button { padding: 4px 10px !important; min-height: 32px !important; font-size: 0.8rem !important; }
            .admin-attendance-page .summary-card { padding: 6px 10px !important; min-height: 46px !important; }
            .admin-attendance-page .summary-card strong { font-size: 0.95rem !important; }
            .admin-attendance-page .calendar-grid { gap: 2px !important; }
            .admin-attendance-page .day-card { min-height: 48px !important; padding: 3px !important; }
            .admin-attendance-page .day-number { font-size: 0.9rem !important; }
            .admin-attendance-page .day-copy { font-size: 0.58rem !important; }
            .admin-attendance-page .attendance-header { margin-bottom: 4px !important; }
            .admin-attendance-page .toolbar { margin-bottom: 6px !important; }
";

if (strpos($content, '/* Attendance Page Specific Shrinkage') === false) {
    $content .= $specific_css;
} else {
    // Update existing
    $content = preg_replace('/\/\* Attendance Page Specific Shrinkage \*\/.*?\n\s*\}\n/s', $specific_css, $content);
}

file_put_contents($file, $content);
echo "Full UI fix applied.\n";
