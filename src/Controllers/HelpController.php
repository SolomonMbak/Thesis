<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CsvImporter;

/**
 * Help Centre at /help (BUILD_SPEC 10.3 item 6): getting started, preparing
 * data, understanding results, publishing, inviting the team, FAQ and the
 * glossary, with a search box that filters on the page. Open to everyone,
 * so people can read it before they sign up.
 */
final class HelpController extends Controller
{
    public function index(): void
    {
        $data = ['title' => 'Help Centre', 'templates' => CsvImporter::TEMPLATES, 'glossary' => GLOSSARY];
        if ($this->app->auth()->check() && $this->app->school() !== null) {
            $this->render('help/centre', $data);
            return;
        }
        $this->renderPlain('help/centre', $data + ['standalone' => true]);
    }

}
