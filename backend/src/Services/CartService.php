<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\CartRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\SettingRepository;

class CartService
{
    private CartRepository $cartRepo;
    private MedicineRepository $medicineRepo;
    private SettingRepository $settingRepo;

    public function __construct(
        CartRepository $cartRepo,
        MedicineRepository $medicineRepo,
        SettingRepository $settingRepo
    ) {
        $this->cartRepo = $cartRepo;
        $this->medicineRepo = $medicineRepo;
        $this->settingRepo = $settingRepo;
    }

    /**
     * Get user's cart summary including stock status, Rx requirements, GST totals and delivery fee.
     */
    public function getSummary(int $userId): array
    {
        $cart = $this->cartRepo->getOrCreateForUser($userId);
        $rawItems = $this->cartRepo->getItems((int)$cart['id']);

        $items = [];
        $subtotal = 0.0;
        $gstTotal = 0.0;
        $itemCount = 0;
        $hasRxItems = false;
        $allInStock = true;

        foreach ($rawItems as $item) {
            $qty = (int)$item['quantity'];
            $unitPrice = (float)$item['unit_price'];
            $mrp = (float)$item['mrp'];
            $availableStock = (int)$item['available_stock'];
            $gstPercent = (float)($item['gst_percent'] ?? 12.00);
            $requiresRx = !empty($item['requires_rx']);

            $lineTotal = round($unitPrice * $qty, 2);
            // Tax component included in selling price (MRP/Retail prices in India are tax-inclusive)
            $gstAmount = round($lineTotal - ($lineTotal / (1 + ($gstPercent / 100))), 2);

            $inStock = $availableStock >= $qty;
            if (!$inStock) {
                $allInStock = false;
            }

            if ($requiresRx) {
                $hasRxItems = true;
            }

            $items[] = [
                'id' => (int)$item['id'],
                'medicine_id' => (int)$item['medicine_id'],
                'name' => $item['name'],
                'slug' => $item['slug'],
                'pack_size' => $item['pack_size'],
                'schedule' => $item['schedule'],
                'requires_rx' => $requiresRx,
                'image' => $item['image'],
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'mrp' => $mrp,
                'gst_percent' => $gstPercent,
                'gst_amount' => $gstAmount,
                'line_total' => $lineTotal,
                'available_stock' => $availableStock,
                'in_stock' => $inStock,
            ];

            $subtotal += $lineTotal;
            $gstTotal += $gstAmount;
            $itemCount += $qty;
        }

        // Delivery fee from settings (0 if cart is empty or meets free delivery threshold)
        $deliveryFeeSetting = (float)($this->settingRepo->get('delivery_fee') ?? 50.00);
        $deliveryFee = ($subtotal > 0 && $subtotal < 500.00) ? $deliveryFeeSetting : 0.00;
        if ($subtotal === 0.0) {
            $deliveryFee = 0.00;
        }

        $discount = 0.00;
        $total = round($subtotal + $deliveryFee - $discount, 2);

        return [
            'cart_id' => (int)$cart['id'],
            'item_count' => $itemCount,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'gst_total' => round($gstTotal, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'discount' => round($discount, 2),
            'total' => $total,
            'has_rx_items' => $hasRxItems,
            'all_in_stock' => $allInStock,
        ];
    }

    public function addItem(int $userId, int $medicineId, int $quantity = 1): array
    {
        $medicine = $this->medicineRepo->findById($medicineId);
        if (!$medicine || empty($medicine['is_active'])) {
            throw new ValidationException(['medicine_id' => ['Medicine not found or unavailable.']]);
        }

        $cart = $this->cartRepo->getOrCreateForUser($userId);
        $this->cartRepo->addItem((int)$cart['id'], $medicineId, max(1, $quantity));

        return $this->getSummary($userId);
    }

    public function updateItem(int $userId, int $medicineId, int $quantity): array
    {
        $cart = $this->cartRepo->getOrCreateForUser($userId);
        $this->cartRepo->updateItem((int)$cart['id'], $medicineId, $quantity);

        return $this->getSummary($userId);
    }

    public function removeItem(int $userId, int $medicineId): array
    {
        $cart = $this->cartRepo->getOrCreateForUser($userId);
        $this->cartRepo->removeItem((int)$cart['id'], $medicineId);

        return $this->getSummary($userId);
    }

    public function clear(int $userId): array
    {
        $cart = $this->cartRepo->getOrCreateForUser($userId);
        $this->cartRepo->clear((int)$cart['id']);

        return $this->getSummary($userId);
    }
}

