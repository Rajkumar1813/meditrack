<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escape HTML special characters for safe output.
     */
    function e(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('brand')) {
    /**
     * Retrieve a configuration value from brand.php using dot notation.
     * Example: brand('app.name'), brand('colors.primary.600')
     */
    function brand(?string $key = null, mixed $default = null): mixed
    {
        static $brandConfig = null;

        if ($brandConfig === null) {
            $brandFile = dirname(__DIR__) . '/config/brand.php';
            if (file_exists($brandFile)) {
                $brandConfig = require $brandFile;
            } else {
                $brandConfig = [];
            }
        }

        if ($key === null) {
            return $brandConfig;
        }

        $segments = explode('.', $key);
        $current = $brandConfig;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}

if (!function_exists('t')) {
    /**
     * Translate a given key using the Lang core service.
     */
    function t(string $key, array $replace = [], ?string $locale = null): string
    {
        static $fallbackLang = null;

        if (\MediTrack\Core\Container::getInstance()->has(\MediTrack\Core\Lang::class)) {
            /** @var \MediTrack\Core\Lang $lang */
            $lang = \MediTrack\Core\Container::getInstance()->get(\MediTrack\Core\Lang::class);
            return $lang->get($key, $replace, $locale);
        }

        if ($fallbackLang === null) {
            $langPath = dirname(__DIR__) . '/lang';
            if (is_dir($langPath)) {
                $fallbackLang = new \MediTrack\Core\Lang($langPath, 'en');
            }
        }

        if ($fallbackLang !== null) {
            return $fallbackLang->get($key, $replace, $locale);
        }

        return $key;
    }
}

