<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\WeightRepository;

/**
 * Rules and weights: what each hard and soft rule means and how many
 * penalty points one break of it adds (BUILD_SPEC section 6), per school.
 */
final class RulesController extends Controller
{
    public function index(array $errors = [], array $old = []): void
    {
        $this->render('rules/index', [
            'title' => 'Rules and weights',
            'weights' => (new WeightRepository($this->app->db(), $this->school()))->all(),
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    public function save(): void
    {
        $input = (array) $this->app->request->input('weight', []);
        $weights = new WeightRepository($this->app->db(), $this->school());
        $rows = $weights->all();
        $errors = [];
        $changes = [];
        foreach ($rows as $row) {
            $value = trim((string) ($input[$row['code']] ?? ''));
            $min = $row['code'] === 'H' ? 1 : 0;
            if (!preg_match('/^\d{1,7}$/', $value) || (int) $value < $min) {
                $errors['weight_' . $row['code']] = "The weight for {$row['code']} must be a whole number from {$min} to 9999999.";
                continue;
            }
            if ((int) $value !== (int) $row['weight']) {
                $changes[$row['code']] = ['from' => (int) $row['weight'], 'to' => (int) $value];
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->index($errors, $input);
            return;
        }
        foreach ($changes as $code => $change) {
            $weights->set($code, $change['to']);
        }
        $this->app->log('settings.weights', null, null, $changes);
        $this->app->flash->success($changes ? 'Weights saved. New runs use them; earlier runs keep the weights they were run with.' : 'No weights were changed.');
        $this->redirect('rules');
    }
}
