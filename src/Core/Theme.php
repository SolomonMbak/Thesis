<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Dark and light themes (BUILD_SPEC 10.5).
 *
 * To avoid a flash of the wrong theme, a tiny script in <head> sets
 * data-theme on <html> before the stylesheet paints. It is the only inline
 * script in the app; the Content-Security-Policy allows exactly this text by
 * its SHA-256 hash, so any other inline script is still blocked.
 *
 * Choice order: the signed-in user's saved choice (data-theme-pref, also
 * copied to localStorage), else localStorage, else the device setting.
 */
final class Theme
{
    public const CHOICES = ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'];

    public const SCRIPT = "(function(){var d=document.documentElement,p=d.getAttribute('data-theme-pref'),t;"
        . "try{if(p){localStorage.setItem('tt-theme',p);}t=p||localStorage.getItem('tt-theme');"
        . "if(localStorage.getItem('tt-rail')==='1'){d.classList.add('rail');}}catch(e){t=p;}"
        . "if(t!=='light'&&t!=='dark'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}"
        . "d.setAttribute('data-theme',t);})();";

    /** The CSP source that allows SCRIPT, e.g. 'sha256-abc...'. */
    public static function cspSource(): string
    {
        return "'sha256-" . base64_encode(hash('sha256', self::SCRIPT, true)) . "'";
    }

    /** The <script> tag for the page head. */
    public static function headScript(): string
    {
        return '<script>' . self::SCRIPT . '</script>';
    }
}
