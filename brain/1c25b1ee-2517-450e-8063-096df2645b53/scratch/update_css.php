<?php
$file = 'assets/css/app.css';
$content = file_get_contents($file);

// Use regex to be more flexible with whitespace if needed, but let's try exact first.
$content = preg_replace(
    '/\.calendar-grid\s*\{\s*display:\s*grid;\s*grid-template-columns:\s*repeat\(7,\s*minmax\(0,\s*1fr\)\);\s*gap:\s*6px;\s*height:\s*280px;\s*min-height:\s*280px;\s*overflow-y:\s*auto;\s*overflow-x:\s*hidden;\s*padding-right:\s*6px;\s*overscroll-behavior:\s*contain;\s*align-content:\s*start;\s*\}/',
    '.calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; padding-right: 0; align-content: start; }',
    $content
);

$content = preg_replace(
    '/\.day-card\s*\{\s*min-height:\s*92px;[^}]+\}/',
    '.day-card { min-height: 58px; border-radius: 12px; padding: 6px; background: rgba(255,255,255,0.84); display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; gap: 4px; cursor: pointer; text-align: left; transition: transform .18s ease, box-shadow .18s ease; }',
    $content
);

$content = preg_replace(
    '/\.day-card\s+\.day-number\s*\{\s*font-size:\s*2rem;[^}]+\}/',
    '.day-card .day-number { font-size: 1.15rem; font-weight: 800; line-height: 1; color: var(--ink); }',
    $content
);

// Also handle the admin page specific one
$content = preg_replace(
    '/\.admin-attendance-page\s+\.calendar-grid\s*\{\s*height:\s*230px;\s*min-height:\s*230px;\s*\}/',
    '.admin-attendance-page .calendar-grid { height: auto; min-height: 0; }',
    $content
);

$content = preg_replace(
    '/\.day-dot\s*\{[^}]+\}/',
    '.day-dot { width: 10px; height: 10px; border-radius: 999px; }',
    $content
);

$content = preg_replace(
    '/\.panel,\s*\.section-block,\s*\.calendar-shell,\s*\.table-wrap,\s*\.metric-card\s*\{\s*padding:\s*24px;\s*\}/',
    '.panel, .section-block, .calendar-shell, .table-wrap, .metric-card { padding: 14px 18px; }',
    $content
);

$content = preg_replace(
    '/\.summary-card\s*\{\s*padding:\s*16px;\s*min-height:\s*96px;[^}]+\}/',
    '.summary-card { padding: 10px 14px; min-height: 60px; border-radius: 14px; background: rgba(19,34,56,0.04); display: flex; flex-direction: column; justify-content: center; }',
    $content
);

$content = preg_replace(
    '/\.summary-card\s+strong\s*\{\s*display:\s*block;\s*font-size:\s*1.5rem;\s*\}/',
    '.summary-card strong { display: block; font-size: 1.1rem; }',
    $content
);

$content = preg_replace(
    '/\.calendar-summary\s*\{\s*display:\s*grid;[^}]+margin-top:\s*18px;[^}]+\}/',
    '.calendar-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 10px; align-items: stretch; }',
    $content
);

$content = preg_replace(
    '/\.admin-main,\s*\.employee-main\s*\{\s*min-width:\s*0;\s*display:\s*flex;\s*flex-direction:\s*column;\s*gap:\s*18px;\s*padding:\s*18px;\s*height:\s*100vh;\s*overflow:\s*hidden;\s*\}/',
    '.admin-main, .employee-main { min-width: 0; display: flex; flex-direction: column; gap: 10px; padding: 12px; height: 100vh; overflow: hidden; }',
    $content
);

$content = preg_replace(
    '/\.attendance-header\s*\{\s*display:\s*flex;\s*justify-content:\s*space-between;\s*align-items:\s*center;\s*margin-bottom:\s*12px;\s*\}/',
    '.attendance-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }',
    $content
);

$content = preg_replace(
    '/h1\s*\{\s*font-size:\s*clamp\(1\.45rem,\s*2\.1vw,\s*2\.15rem\);\s*line-height:\s*1\.12;\s*\}/',
    'h1 { font-size: 1.25rem; line-height: 1.1; }',
    $content
);

$content = preg_replace(
    '/\.weekday\s*\{\s*text-align:\s*center;\s*color:\s*var\(--muted\);\s*font-weight:\s*700;\s*padding-bottom:\s*4px;\s*\}/',
    '.weekday { text-align: center; color: var(--muted); font-weight: 700; font-size: 0.75rem; padding-bottom: 2px; }',
    $content
);

$content = preg_replace(
    '/\.day-card\s+\.day-copy\s*\{\s*min-height:\s*0\.95rem;\s*font-size:\s*0\.78rem;[^}]+\}/',
    '.day-card .day-copy { min-height: 0; font-size: 0.65rem; font-weight: 700; color: var(--muted); line-height: 1; }',
    $content
);

$content = preg_replace(
    '/\.eyebrow\s*\{\s*display:\s*inline-block;\s*padding:\s*8px\s*14px;[^}]+\}/',
    '.eyebrow { display: inline-block; padding: 4px 10px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; font-size: 0.7rem; }',
    $content
);

$content = preg_replace(
    '/\.brand-copy\s+strong\s*\{\s*display:\s*block;\s*font-family:\s*"Inter",\s*"Segoe\s*UI",\s*sans-serif;\s*font-size:\s*1\.04rem;\s*\}/',
    '.brand-copy strong { display: block; font-family: "Inter", "Segoe UI", sans-serif; font-size: 0.9rem; }',
    $content
);

$content = preg_replace(
    '/\.sidebar-profile\s*\{\s*display:\s*flex;\s*align-items:\s*center;\s*gap:\s*14px;\s*padding:\s*16px;\s*border-radius:\s*22px;\s*background:\s*rgba\(255,255,255,0\.08\);\s*\}/',
    '.sidebar-profile { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 16px; background: rgba(255,255,255,0.08); }',
    $content
);

$content = preg_replace(
    '/\.sidebar-link\s*\{\s*display:\s*flex;\s*align-items:\s*center;\s*gap:\s*12px;\s*padding:\s*14px\s*16px;\s*border-radius:\s*18px;[^}]+\}/',
    '.sidebar-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 14px; color: rgba(248,250,252,0.84); transition: background .2s ease, transform .2s ease, color .2s ease; }',
    $content
);

$content = preg_replace(
    '/\.employee-tabs-section\s*\{\s*padding:\s*0\s*24px;\s*margin-bottom:\s*12px;\s*\}/',
    '.employee-tabs-section { padding: 0 12px; margin-top: -12px; margin-bottom: 8px; }',
    $content
);

$content = preg_replace(
    '/\.tab-link\s*\{\s*display:\s*inline-flex;[^}]+padding:\s*12px\s*18px;[^}]+\}/',
    '.tab-link { display: inline-flex; align-items: center; padding: 6px 14px; font-weight: 600; color: var(--muted); border: none; background: transparent; cursor: pointer; border-bottom: 3px solid transparent; transition: color .2s ease, border-color .2s ease; font-size: 0.88rem; margin-bottom: -2px; }',
    $content
);

$content = preg_replace(
    '/\.scroll-panel\s*\{\s*max-height:\s*620px;\s*overflow:\s*auto;\s*\}/',
    '.scroll-panel { max-height: none; overflow: visible; }',
    $content
);

$content = preg_replace(
    '/\.button\s*\{\s*display:\s*inline-flex;[^}]+padding:\s*12px\s*24px;[^}]+\}/',
    '.button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 6px 14px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all .2s ease; font-size: 0.85rem; border: 2px solid transparent; min-height: 36px; }',
    $content
);

$content = preg_replace(
    '/\.button\.small\s*\{\s*padding:\s*8px\s*14px;\s*font-size:\s*0\.82rem;\s*min-height:\s*34px;\s*\}/',
    '.button.small { padding: 4px 10px; font-size: 0.75rem; min-height: 28px; }',
    $content
);

file_put_contents($file, $content);
echo "Replacement completed successfully.\n";
