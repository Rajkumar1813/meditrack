<?php

declare(strict_types=1);

namespace MediTrack\Core;

class View
{
    private string $templatePath;

    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $templatePath ?? dirname(__DIR__, 2) . '/templates';
    }

    public static function render(string $template, array $data = [], ?string $templatePath = null): string
    {
        $view = new self($templatePath);
        return $view->make($template, $data);
    }

    public function make(string $template, array $data = []): string
    {
        $cleanTemplate = str_replace(['..', "\0"], '', $template);
        $file = "{$this->templatePath}/{$cleanTemplate}.php";

        if (!file_exists($file)) {
            throw new \RuntimeException("Template [{$template}] not found at {$file}.");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}

