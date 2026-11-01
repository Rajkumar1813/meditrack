<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Order;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Repositories\AddressRepository;
use MediTrack\Validators\AddressValidator;

class AddressApiTest extends TestCase
{
    private Database $db;
    private AddressRepository $addressRepo;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $pdo = $this->db->pdo();
        $pdo->exec("
            CREATE TABLE addresses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                label TEXT NULL,
                recipient_name TEXT NOT NULL,
                phone TEXT NOT NULL,
                line1 TEXT NOT NULL,
                line2 TEXT NULL,
                landmark TEXT NULL,
                city TEXT NOT NULL,
                state TEXT NOT NULL,
                pincode TEXT NOT NULL,
                lat NUMERIC NULL,
                lng NUMERIC NULL,
                is_default INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
        ");

        $this->addressRepo = new AddressRepository($this->db);
    }

    public function testFirstAddressAutomaticallyBecomesDefault(): void
    {
        $id1 = $this->addressRepo->create(1, [
            'label' => 'Home',
            'recipient_name' => 'Rahul Sharma',
            'phone' => '9876543210',
            'line1' => 'Flat 101, Palm Heights',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'pincode' => '302001',
            'is_default' => 0, // Even if 0, first address becomes default
        ]);

        $addr1 = $this->addressRepo->findById($id1);
        $this->assertSame(1, (int)$addr1['is_default']);

        // Second address without is_default remains 0
        $id2 = $this->addressRepo->create(1, [
            'label' => 'Office',
            'recipient_name' => 'Rahul Sharma',
            'phone' => '9876543210',
            'line1' => 'Plot 55, Tech Zone',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'pincode' => '302020',
            'is_default' => 0,
        ]);

        $addr2 = $this->addressRepo->findById($id2);
        $this->assertSame(0, (int)$addr2['is_default']);

        // Setting second address as default unsets the first
        $this->addressRepo->setDefault($id2, 1);
        $this->assertSame(0, (int)$this->addressRepo->findById($id1)['is_default']);
        $this->assertSame(1, (int)$this->addressRepo->findById($id2)['is_default']);
    }

    public function testAddressValidationRules(): void
    {
        // Missing required fields
        $v = AddressValidator::validate([]);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('recipient_name', $v->errors());
        $this->assertArrayHasKey('phone', $v->errors());
        $this->assertArrayHasKey('line1', $v->errors());
        $this->assertArrayHasKey('city', $v->errors());
        $this->assertArrayHasKey('state', $v->errors());
        $this->assertArrayHasKey('pincode', $v->errors());

        // Invalid pincode (not 6 digits or starts with 0)
        $v2 = AddressValidator::validate([
            'recipient_name' => 'Amit',
            'phone' => '9876543210',
            'line1' => 'Street 1',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'pincode' => '011234', // invalid leading 0
        ]);
        $this->assertTrue($v2->fails());
        $this->assertArrayHasKey('pincode', $v2->errors());

        // Valid data
        $v3 = AddressValidator::validate([
            'recipient_name' => 'Amit Kumar',
            'phone' => '9876543210',
            'line1' => 'House 12, Block B',
            'city' => 'New Delhi',
            'state' => 'Delhi',
            'pincode' => '110001',
        ]);
        $this->assertFalse($v3->fails());
    }

    public function testUserIsolation(): void
    {
        $id1 = $this->addressRepo->create(1, [
            'recipient_name' => 'User 1',
            'phone' => '9876543210',
            'line1' => 'Address 1',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
        ]);

        // User 2 cannot access or delete User 1's address
        $this->assertNull($this->addressRepo->findByIdAndUser($id1, 2));
        $this->assertFalse($this->addressRepo->delete($id1, 2));

        // Address still exists for User 1
        $this->assertNotNull($this->addressRepo->findByIdAndUser($id1, 1));
    }
}

