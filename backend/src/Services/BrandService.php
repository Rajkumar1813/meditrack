<?php

declare(strict_types=1);

namespace MediTrack\Services;

class BrandService
{
    private array $brandConfig;

    public function __construct(?array $brandConfig = null)
    {
        if ($brandConfig !== null) {
            $this->brandConfig = $brandConfig;
        } else {
            $path = dirname(__DIR__, 2) . '/config/brand.php';
            $this->brandConfig = file_exists($path) ? require $path : [];
        }
    }

    public function getBrandData(): array
    {
        return $this->brandConfig;
    }

    public function getEtag(): string
    {
        $version = $this->brandConfig['version'] ?? '1.0.0';
        $json = json_encode($this->brandConfig);
        return '"' . md5("{$version}:{$json}") . '"';
    }
}

