<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Renders PHP templates from views/. A page template is rendered first,
 * then wrapped in a layout that receives it as $content.
 * Templates must escape every value with e().
 */
final class View
{
    private array $shared = [];

    public function __construct(private string $dir)
    {
    }

    /** Values every template can see, e.g. the signed-in user. */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function render(string $template, array $data = [], ?string $layout = 'layout/app'): string
    {
        $data = $data + $this->shared;
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->renderFile($layout, ['content' => $content] + $data);
    }

    /** Render a small reusable piece, e.g. $this->partial('partials/field', [...]) inside a template. */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data + $this->shared);
    }

    private function renderFile(string $template, array $data): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!preg_match('#^[a-z0-9_/-]+$#', $template) || !is_file($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $ex) {
            ob_end_clean();
            throw $ex;
        }
        return (string) ob_get_clean();
    }
}
