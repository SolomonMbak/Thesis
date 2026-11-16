<?php
/**
 * Inline SVG icons (BUILD_SPEC 10.6: no icon font CDN). Simple line icons
 * on a 24 x 24 grid drawn with currentColor, so they follow the theme.
 */
declare(strict_types=1);

/** <svg> markup for an icon name; unknown names give an empty square. */
function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'start' => '<path d="M5 3v18"/><path d="M5 4h11l-2 4 2 4H5"/>',
        'guide' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'home' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'school' => '<path d="M3 9l9-5 9 5-9 5z"/><path d="M7 11.5V16c0 1.5 2.5 3 5 3s5-1.5 5-3v-4.5"/>',
        'room' => '<path d="M4 21V4h11v17"/><path d="M15 6h5v15"/><circle cx="12" cy="13" r="0.8"/><path d="M2 21h20"/>',
        'level' => '<circle cx="8" cy="8" r="3"/><circle cx="16" cy="8" r="3"/><path d="M2 20c0-3 3-5 6-5s6 2 6 5"/><path d="M14 15.3c.6-.2 1.3-.3 2-.3 3 0 6 2 6 5"/>',
        'lecturer' => '<circle cx="12" cy="7" r="4"/><path d="M4 21c0-4 3.5-7 8-7s8 3 8 7"/>',
        'course' => '<path d="M4 5c3-1 5-1 8 1 3-2 5-2 8-1v14c-3-1-5-1-8 1-3-2-5-2-8-1z"/><path d="M12 6v14"/>',
        'availability' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M9 15l2 2 4-4"/>',
        'import' => '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 17v3h16v-3"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'check' => '<path d="M12 3l8 4v5c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V7z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'generate' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'runs' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'publish' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 10.5l6.8-4M8.6 13.5l6.8 4"/>',
        'manage' => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'members' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-6 7-6s7 2.5 7 6"/><path d="M16 4.5a3.5 3.5 0 010 7M18 14c2.5.6 4 2.8 4 6"/>',
        'rules' => '<path d="M12 3v18M5 7h14"/><path d="M5 7l-3 7c0 1.7 1.3 3 3 3s3-1.3 3-3zM19 7l-3 7c0 1.7 1.3 3 3 3s3-1.3 3-3z"/>',
        'semester' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M8 14h3v3H8z"/>',
        'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>',
        'platform' => '<rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M7 7h.01M7 17h.01"/>',
        'health' => '<path d="M20.8 5.6a5.5 5.5 0 00-7.8 0L12 6.7l-1-1.1a5.5 5.5 0 00-7.8 7.8L12 22l8.8-8.6a5.5 5.5 0 000-7.8z"/><path d="M7 12h2l1.5-2.5 2 5L14 12h3"/>',
        'backup' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 015 .5c0 1.7-2.5 2-2.5 3.5"/><path d="M12 17h.01"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>',
        'system' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-7 8-7s8 3 8 7"/>',
        'chevron' => '<path d="M9 6l6 6-6 6"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'collapse' => '<path d="M15 6l-6 6 6 6"/><path d="M20 4v16"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'print' => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
        'copy' => '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 00-2-2H5a2 2 0 00-2 2v9a2 2 0 002 2h3"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
    ];
    $body = $paths[$name] ?? '<rect x="4" y="4" width="16" height="16" rx="2"/>';
    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 24 24" width="20" height="20" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $body . '</svg>';
}
