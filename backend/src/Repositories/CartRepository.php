<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class CartRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getOrCreateForUser(int $userId): array
    {
        $cart = $this->db->fetchOne('SELECT * FROM carts WHERE user_id = ?', [$userId]);
        if (!$cart) {
            $this->db->execute('INSERT INTO carts (user_id, updated_at) VALUES (?, CURRENT_TIMESTAMP)', [$userId]);
            $cart = $this->db->fetchOne('SELECT * FROM carts WHERE user_id = ?', [$userId]);
        }
        return $cart;
    }

    public function getItems(int $cartId): array
    {
        $today = date('Y-m-d');
        $sql = "SELECT ci.id, ci.cart_id, ci.medicine_id, ci.quantity,
                       m.name, m.slug, m.pack_size, m.schedule, m.requires_rx, m.gst_percent, m.image,
                       COALESCE(
                           (
                               SELECT b_prc.selling_price FROM batches b_prc
                               WHERE b_prc.medicine_id = m.id
                                 AND b_prc.exp_date > '{$today}'
                                 AND (b_prc.quantity - b_prc.reserved_quantity) > 0
                                 AND b_prc.disposal_status = 'none'
                               ORDER BY b_prc.exp_date ASC, b_prc.id ASC
                               LIMIT 1
                           ),
                           0
                       ) as unit_price,
                       COALESCE(
                           (
                               SELECT b_prc.mrp FROM batches b_prc
                               WHERE b_prc.medicine_id = m.id
                                 AND b_prc.exp_date > '{$today}'
                                 AND (b_prc.quantity - b_prc.reserved_quantity) > 0
                                 AND b_prc.disposal_status = 'none'
                               ORDER BY b_prc.exp_date ASC, b_prc.id ASC
                               LIMIT 1
                           ),
                           0
                       ) as mrp,
                       COALESCE(
                           (
                               SELECT SUM(b_stk.quantity - b_stk.reserved_quantity) FROM batches b_stk
                               WHERE b_stk.medicine_id = m.id
                                 AND b_stk.exp_date > '{$today}'
                                 AND b_stk.disposal_status = 'none'
                           ),
                           0
                       ) as available_stock
                FROM cart_items ci
                JOIN medicines m ON ci.medicine_id = m.id
                WHERE ci.cart_id = ? AND m.deleted_at IS NULL AND m.is_active = 1
                ORDER BY ci.id ASC";

        return $this->db->fetchAll($sql, [$cartId]);
    }

    public function addItem(int $cartId, int $medicineId, int $quantity = 1): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id, quantity FROM cart_items WHERE cart_id = ? AND medicine_id = ?',
            [$cartId, $medicineId]
        );

        if ($existing) {
            $this->db->execute(
                'UPDATE cart_items SET quantity = quantity + ? WHERE id = ?',
                [max(1, $quantity), $existing['id']]
            );
        } else {
            $this->db->execute(
                'INSERT INTO cart_items (cart_id, medicine_id, quantity) VALUES (?, ?, ?)',
                [$cartId, $medicineId, max(1, $quantity)]
            );
        }

        $this->touchCart($cartId);
    }

    public function updateItem(int $cartId, int $medicineId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($cartId, $medicineId);
            return;
        }

        $this->db->execute(
            'UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND medicine_id = ?',
            [$quantity, $cartId, $medicineId]
        );
        $this->touchCart($cartId);
    }

    public function removeItem(int $cartId, int $medicineId): void
    {
        $this->db->execute('DELETE FROM cart_items WHERE cart_id = ? AND medicine_id = ?', [$cartId, $medicineId]);
        $this->touchCart($cartId);
    }

    public function clear(int $cartId): void
    {
        $this->db->execute('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
        $this->touchCart($cartId);
    }

    private function touchCart(int $cartId): void
    {
        $this->db->execute('UPDATE carts SET updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$cartId]);
    }
}
