<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Auth;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Validator;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Exceptions\UnauthorizedException;
use MediTrack\Services\OtpService;
use MediTrack\Services\AuthService;

class AuthController
{
    private OtpService $otpService;
    private AuthService $authService;

    public function __construct(OtpService $otpService, AuthService $authService)
    {
        $this->otpService = $otpService;
        $this->authService = $authService;
    }

    /**
     * POST /api/v1/auth/otp/request
     */
    public function requestOtp(Request $request): Response
    {
        $input = $request->getBody();
        $validator = Validator::make($input, [
            'identifier' => 'required|string|min:4|max:190',
            'type' => 'required|in:phone,email',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }

        $type = (string)$input['type'];
        $identifier = (string)$input['identifier'];

        // Extra format validation
        if ($type === 'email' && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['identifier' => ['Please enter a valid email address.']]);
        }
        if ($type === 'phone') {
            $digits = preg_replace('/[^\d]/', '', $identifier);
            if (strlen($digits) < 10) {
                throw new ValidationException(['identifier' => ['Please enter a valid 10-digit mobile number.']]);
            }
        }

        $result = $this->otpService->requestOtp($identifier, $type, $request->ip());

        return ApiResponse::success(
            $result,
            200,
            [],
            'Verification code sent successfully.'
        );
    }

    /**
     * POST /api/v1/auth/otp/verify
     */
    public function verifyOtp(Request $request): Response
    {
        $input = $request->getBody();
        $validator = Validator::make($input, [
            'identifier' => 'required|string|min:4|max:190',
            'code' => 'required|string|min:6|max:6',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }

        $identifier = (string)$input['identifier'];
        $code = (string)$input['code'];
        $client = (string)($input['client'] ?? 'web');
        $deviceName = isset($input['device_name']) ? (string)$input['device_name'] : $request->header('user-agent');

        $user = $this->otpService->verifyOtp($identifier, $code, $request->ip());
        $tokenData = $this->authService->issueTokens($user, $request->ip(), $deviceName);

        return $this->formatAuthResponse($tokenData, $client, $request);
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(Request $request): Response
    {
        $input = $request->getBody();
        $client = (string)($input['client'] ?? 'web');

        // Check HttpOnly cookie first for web, fallback to request body
        $refreshToken = $request->cookie('refresh_token') ?? ($input['refresh_token'] ?? null);

        if (empty($refreshToken)) {
            throw new UnauthorizedException('Refresh token is required.', 'INVALID_TOKEN');
        }

        $deviceName = isset($input['device_name']) ? (string)$input['device_name'] : $request->header('user-agent');
        $tokenData = $this->authService->rotateRefreshToken((string)$refreshToken, $request->ip(), $deviceName);

        return $this->formatAuthResponse($tokenData, $client, $request);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): Response
    {
        $input = $request->getBody();
        $client = (string)($input['client'] ?? 'web');
        $refreshToken = $request->cookie('refresh_token') ?? ($input['refresh_token'] ?? null);

        if (!empty($refreshToken)) {
            $this->authService->revokeToken((string)$refreshToken);
        }

        $response = ApiResponse::success(null, 200, [], 'Logged out successfully.');

        // If web client, clear the refresh cookie
        if ($client === 'web') {
            $isSecure = $this->isHttps($request);
            $response->cookie(
                'refresh_token',
                '',
                time() - 3600,
                '/api/v1/auth',
                '',
                $isSecure,
                true,
                'Strict'
            );
        }

        return $response;
    }

    /**
     * GET /api/v1/me
     */
    public function me(Request $request): Response
    {
        $user = $request->getAttribute('user');
        if (!$user) {
            throw new UnauthorizedException('Unauthenticated.', 'UNAUTHORIZED');
        }

        return ApiResponse::success($this->authService->sanitizeUser($user));
    }

    private function formatAuthResponse(array $tokenData, string $client, Request $request): Response
    {
        $isSecure = $this->isHttps($request);
        $payload = [
            'user' => $tokenData['user'],
        ];

        if ($client === 'mobile') {
            // Mobile app receives refresh token directly in JSON body
            $payload['tokens'] = [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'token_type' => 'Bearer',
                'expires_in' => $tokenData['expires_in'],
            ];

            return ApiResponse::success($payload, 200, [], 'Authentication successful.');
        }

        // Web client receives refresh token strictly as an HttpOnly, SameSite=Strict cookie
        $payload['tokens'] = [
            'access_token' => $tokenData['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokenData['expires_in'],
        ];

        $response = ApiResponse::success($payload, 200, [], 'Authentication successful.');
        $response->cookie(
            'refresh_token',
            $tokenData['refresh_token'],
            time() + (30 * 86400), // 30 days
            '/api/v1/auth',
            '',
            $isSecure,
            true, // HttpOnly
            'Strict' // SameSite=Strict
        );

        return $response;
    }

    private function isHttps(Request $request): bool
    {
        $proto = $request->header('x-forwarded-proto');
        if ($proto === 'https') {
            return true;
        }

        $appUrl = $_ENV['APP_URL'] ?? '';
        return str_starts_with($appUrl, 'https://');
    }
}

