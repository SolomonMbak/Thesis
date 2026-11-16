<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SetupGuide;

/**
 * The Setup Guide page (BUILD_SPEC 10.2): a checklist of 9 steps whose
 * status is worked out live from the school's data.
 */
final class SetupController extends Controller
{
    public function index(): void
    {
        $guide = new SetupGuide($this->app->db(), $this->school());
        $this->render('setup/index', [
            'title' => 'Setup Guide',
            'steps' => $guide->steps(),
            'done' => $guide->doneCount(),
            'complete' => $guide->isComplete(),
            'next' => $guide->next(),
            'publicLink' => absolute_url(public_url($this->school()->slug())),
            'canAct' => $this->school()->can('officer'),
        ]);
    }
}
