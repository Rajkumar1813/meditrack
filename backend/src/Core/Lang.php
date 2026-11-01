<?php

declare(strict_types=1);

namespace MediTrack\Core;

class Lang
{
    private string $locale = 'en';
    private string $fallbackLocale = 'en';
    private array $loaded = [];
    private string $langPath;

    public function __construct(?string $langPath = null, string $defaultLocale = 'en')
    {
        $this->langPath = $langPath ?? dirname(__DIR__, 2) . '/lang';
        $this->locale = $defaultLocale;
        $this->fallbackLocale = $defaultLocale;
    }

    public function setLocale(string $locale): void
    {
        if (in_array($locale, ['en', 'hi'], true)) {
            $this->locale = $locale;
        }
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $activeLocale = $locale ?? $this->locale;
        $translation = $this->lookup($key, $activeLocale);

        if ($translation === null && $activeLocale !== $this->fallbackLocale) {
            $translation = $this->lookup($key, $this->fallbackLocale);
        }

        $translation = $translation ?? $key;

        foreach ($replace as $placeholder => $value) {
            $translation = str_replace(
                [':' . $placeholder, '{' . $placeholder . '}'],
                (string)$value,
                $translation
            );
        }

        return $translation;
    }

    private function lookup(string $key, string $locale): ?string
    {
        $parts = explode('.', $key);
        $group = array_shift($parts);

        $this->loadGroup($locale, $group);

        $current = $this->loaded[$locale][$group] ?? null;
        if (!is_array($current)) {
            return null;
        }

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return is_string($current) ? $current : null;
    }

    private function loadGroup(string $locale, string $group): void
    {
        if (isset($this->loaded[$locale][$group])) {
            return;
        }

        $filePath = "{$this->langPath}/{$locale}/{$group}.php";
        if (file_exists($filePath)) {
            $this->loaded[$locale][$group] = require $filePath;
        } else {
            $this->loaded[$locale][$group] = [];
        }
    }
}

