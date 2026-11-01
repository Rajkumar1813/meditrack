<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Store;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Services\BrandService;

class BrandController
{
    private BrandService $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    public function show(Request $request): Response
    {
        $etag = $this->brandService->getEtag();
        $ifNoneMatch = $request->header('if-none-match');

        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            return new Response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=3600, must-revalidate',
            ]);
        }

        $brandData = $this->brandService->getBrandData();

        return ApiResponse::success(
            $brandData,
            200,
            [
                'etag' => $etag,
                'version' => $brandData['version'] ?? '1.0.0',
            ],
            null,
            [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=3600, must-revalidate',
            ]
        );
    }
}

