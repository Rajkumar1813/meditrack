<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\AddressRepository;
use MediTrack\Validators\AddressValidator;

class AddressController
{
    private AddressRepository $addressRepo;

    public function __construct(AddressRepository $addressRepo)
    {
        $this->addressRepo = $addressRepo;
    }

    public function index(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $addresses = $this->addressRepo->findByUser($userId);

        return ApiResponse::success($addresses);
    }

    public function show(Request $request, array $vars): Response
    {
        $userId = (int)$request->attribute('user_id');
        $id = (int)$vars['id'];

        $address = $this->addressRepo->findByIdAndUser($id, $userId);
        if (!$address) {
            return ApiResponse::notFound('Address not found.');
        }

        return ApiResponse::success($address);
    }

    public function store(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $body = $request->json();

        $validator = AddressValidator::validate($body, false);
        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $id = $this->addressRepo->create($userId, $body);
        $address = $this->addressRepo->findByIdAndUser($id, $userId);

        return ApiResponse::success($address, ['message' => 'Address saved successfully.'], 201);
    }

    public function update(Request $request, array $vars): Response
    {
        $userId = (int)$request->attribute('user_id');
        $id = (int)$vars['id'];
        $body = $request->json();

        $existing = $this->addressRepo->findByIdAndUser($id, $userId);
        if (!$existing) {
            return ApiResponse::notFound('Address not found.');
        }

        $validator = AddressValidator::validate($body, true);
        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $this->addressRepo->update($id, $userId, $body);
        $address = $this->addressRepo->findByIdAndUser($id, $userId);

        return ApiResponse::success($address, ['message' => 'Address updated successfully.']);
    }

    public function destroy(Request $request, array $vars): Response
    {
        $userId = (int)$request->attribute('user_id');
        $id = (int)$vars['id'];

        $existing = $this->addressRepo->findByIdAndUser($id, $userId);
        if (!$existing) {
            return ApiResponse::notFound('Address not found.');
        }

        $this->addressRepo->delete($id, $userId);

        return ApiResponse::success(['id' => $id], ['message' => 'Address deleted successfully.']);
    }

    public function setDefault(Request $request, array $vars): Response
    {
        $userId = (int)$request->attribute('user_id');
        $id = (int)$vars['id'];

        $existing = $this->addressRepo->findByIdAndUser($id, $userId);
        if (!$existing) {
            return ApiResponse::notFound('Address not found.');
        }

        $this->addressRepo->setDefault($id, $userId);
        $address = $this->addressRepo->findByIdAndUser($id, $userId);

        return ApiResponse::success($address, ['message' => 'Default address updated.']);
    }
}

