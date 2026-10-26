<?php
namespace App\Core;

class View
{
    private string $viewsDir;

    public function __construct()
    {
        $this->viewsDir = ROOT_PATH . '/app/Views';
    }

    public function render(string $view, array $data = [], ?string $layout = 'layouts/public'): string
    {
        $content = $this->renderPartial($view, $data);

        if ($layout === null) {
            echo $content;
            return '';
        }
        echo $this->renderPartial($layout, array_merge($data, ['content' => $content]));
        return '';
    }

    public function renderPartial(string $view, array $data = []): string
    {
        $file = $this->viewsDir . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$view} ({$file})");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }
}
