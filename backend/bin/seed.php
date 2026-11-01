<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use MediTrack\Core\Database;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Repositories\CategoryRepository;
use MediTrack\Repositories\RackRepository;
use MediTrack\Repositories\ShelfRepository;
use MediTrack\Repositories\SupplierRepository;
use MediTrack\Repositories\SaltRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Enums\Role;
use MediTrack\Enums\Schedule;

$basePath = dirname(__DIR__);
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->safeLoad();
}

$dbConfig = require $basePath . '/config/database.php';
$db = new Database($dbConfig);
$pdo = $db->pdo();

echo "=== MediTrack Database Seeder ===\n";

// 1. Settings
echo "Seeding Settings... ";
$settingRepo = new SettingRepository($db);
$settings = [
    'shop_name' => 'Sharma Medical Store',
    'owner_name' => 'Ramesh Sharma',
    'low_stock_threshold' => '10',
    'delivery_fee' => '40',
    'free_delivery_above' => '499',
    'gst_no' => '07AAAAA0000A1Z5',
    'drug_license_no' => 'DL-2024-DEL-01928',
    'phone' => '+919811111111',
    'email' => 'contact@meditrack.example',
    'address' => 'Shop 4, Market Complex, Sector 15, New Delhi 110001',
];

foreach ($settings as $key => $val) {
    $settingRepo->set($key, (string)$val);
}
echo "OK\n";

// 2. Demo Users
echo "Seeding Demo Users... ";
$userRepo = new UserRepository($db);
$demoUsers = [
    [
        'name' => 'Ramesh Sharma',
        'email' => 'admin@meditrack.example',
        'phone' => '+919811111111',
        'role' => Role::ADMIN->value,
        'locale' => 'en',
        'pharmacist_reg_no' => null,
    ],
    [
        'name' => 'Priya Verma',
        'email' => 'pharmacist@meditrack.example',
        'phone' => '+919822222222',
        'role' => Role::PHARMACIST->value,
        'locale' => 'hi',
        'pharmacist_reg_no' => 'PH-DEL-2024-8891',
    ],
    [
        'name' => 'Rahul Gupta',
        'email' => 'customer@meditrack.example',
        'phone' => '+919833333333',
        'role' => Role::CUSTOMER->value,
        'locale' => 'en',
        'pharmacist_reg_no' => null,
    ],
];

foreach ($demoUsers as $u) {
    $existing = $userRepo->findByEmail($u['email']);
    if (!$existing) {
        $pdo->prepare("
            INSERT INTO users (name, email, phone, role, locale, is_blocked, phone_verified_at, email_verified_at, pharmacist_reg_no, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ")->execute([
            $u['name'],
            $u['email'],
            $u['phone'],
            $u['role'],
            $u['locale'],
            $u['pharmacist_reg_no'],
        ]);
    }
}
echo "OK\n";

// 3. Categories
echo "Seeding Categories... ";
$categoryRepo = new CategoryRepository($db);
$categories = [
    ['name' => 'Tablet', 'slug' => 'tablet', 'icon' => 'tablet', 'sort_order' => 1],
    ['name' => 'Syrup', 'slug' => 'syrup', 'icon' => 'syrup', 'sort_order' => 2],
    ['name' => 'Injection', 'slug' => 'injection', 'icon' => 'injection', 'sort_order' => 3],
    ['name' => 'Ointment', 'slug' => 'ointment', 'icon' => 'ointment', 'sort_order' => 4],
    ['name' => 'Capsule', 'slug' => 'capsule', 'icon' => 'capsule', 'sort_order' => 5],
    ['name' => 'Drops', 'slug' => 'drops', 'icon' => 'drops', 'sort_order' => 6],
    ['name' => 'Inhaler', 'slug' => 'inhaler', 'icon' => 'inhaler', 'sort_order' => 7],
    ['name' => 'Powder', 'slug' => 'powder', 'icon' => 'powder', 'sort_order' => 8],
];

$catMap = [];
foreach ($categories as $cat) {
    $existing = $categoryRepo->findBySlug($cat['slug']);
    if ($existing) {
        $catMap[$cat['name']] = (int)$existing['id'];
    } else {
        $catMap[$cat['name']] = $categoryRepo->create($cat);
    }
}
echo "OK (" . count($catMap) . " categories)\n";

// 4. Manufacturers
echo "Seeding Manufacturers... ";
$manufacturers = [
    'Sun Pharma', 'Cipla', "Dr Reddy's", 'Mankind', 'Win-Medicare',
    'Biocon', 'FDC', 'USV', 'Aristo', 'Alembic', 'Alkem'
];
$mfgMap = [];
foreach ($manufacturers as $mfgName) {
    $row = $db->fetchOne('SELECT id FROM manufacturers WHERE name = ?', [$mfgName]);
    if ($row) {
        $mfgMap[$mfgName] = (int)$row['id'];
    } else {
        $db->execute('INSERT INTO manufacturers (name) VALUES (?)', [$mfgName]);
        $mfgMap[$mfgName] = (int)$db->lastInsertId();
    }
}
echo "OK (" . count($mfgMap) . " manufacturers)\n";

// 5. Racks & Shelves
echo "Seeding Racks & Shelves... ";
$rackRepo = new RackRepository($db);
$shelfRepo = new ShelfRepository($db);
$rackMap = [];
$shelfMap = [];

$racks = ['Rack A', 'Rack B', 'Rack C', 'Rack D'];
foreach ($racks as $rackName) {
    $rack = $rackRepo->findByName($rackName);
    if ($rack) {
        $rackId = (int)$rack['id'];
    } else {
        $rackId = $rackRepo->create($rackName);
    }
    $rackMap[$rackName] = $rackId;

    for ($s = 1; $s <= 3; $s++) {
        $shelfName = "Shelf {$s}";
        $shelf = $shelfRepo->findByRackAndName($rackId, $shelfName);
        if ($shelf) {
            $shelfMap["{$rackName}/{$shelfName}"] = (int)$shelf['id'];
        } else {
            $shelfMap["{$rackName}/{$shelfName}"] = $shelfRepo->create($rackId, $shelfName);
        }
    }
}
echo "OK\n";

// 6. Suppliers
echo "Seeding Suppliers... ";
$supplierRepo = new SupplierRepository($db);
$suppliers = [
    ['name' => 'MediSupply Co.', 'phone' => '+919810011111', 'email' => 'orders@medisupply.example', 'gstin' => '07AABCM1111A1Z1'],
    ['name' => 'HealthDist', 'phone' => '+919810022222', 'email' => 'sales@healthdist.example', 'gstin' => '07AABCH2222B1Z2'],
    ['name' => 'PharmaLink', 'phone' => '+919810033333', 'email' => 'supply@pharmalink.example', 'gstin' => '07AABCP3333C1Z3'],
    ['name' => 'Surgical House', 'phone' => '+919810044444', 'email' => 'info@surgicalhouse.example', 'gstin' => '07AABCS4444D1Z4'],
    ['name' => 'ColdChain Dist', 'phone' => '+919810055555', 'email' => 'cold@coldchain.example', 'gstin' => '07AABCC5555E1Z5'],
    ['name' => 'DiabetiCare', 'phone' => '+919810066666', 'email' => 'care@diabeticare.example', 'gstin' => '07AABCD6666F1Z6'],
];

$supplierMap = [];
foreach ($suppliers as $sup) {
    $row = $db->fetchOne('SELECT id FROM suppliers WHERE name = ?', [$sup['name']]);
    if ($row) {
        $supplierMap[$sup['name']] = (int)$row['id'];
    } else {
        $supplierMap[$sup['name']] = $supplierRepo->create($sup);
    }
}
echo "OK (" . count($supplierMap) . " suppliers)\n";

// 7. Salts
echo "Seeding Salts... ";
$saltRepo = new SaltRepository($db);
$salts = [
    'Paracetamol', 'Azithromycin', 'Cetirizine', 'Amoxicillin', 'Povidone Iodine',
    'Insulin Glargine', 'Salbutamol', 'Oral Rehydration Salts', 'Ofloxacin',
    'Metformin', 'Pantoprazole', 'Dextromethorphan', 'Diclofenac',
    'Cholecalciferol', 'Clavulanic Acid'
];
$saltMap = [];
foreach ($salts as $saltName) {
    $saltMap[$saltName] = $saltRepo->findOrCreate($saltName);
}
echo "OK (" . count($saltMap) . " salts)\n";

// 8. Medicines & Batches (Reference Meditrack.html data)
echo "Seeding Medicines & Batches... ";
$medicineRepo = new MedicineRepository($db);
$batchRepo = new BatchRepository($db);

$medicinesData = [
    [
        'name' => 'Paracetamol 500',
        'slug' => 'paracetamol-500',
        'generic_name' => 'Paracetamol',
        'company' => 'Sun Pharma',
        'category' => 'Tablet',
        'schedule' => Schedule::OTC->value,
        'pack_size' => '10 tablets',
        'gst_percent' => 12.00,
        'description' => 'Fast acting antipyretic and analgesic for fever and pain relief.',
        'notes' => 'Fast moving, keep front',
        'salts' => [['salt' => 'Paracetamol', 'strength' => '500 mg']],
        'batches' => [
            [
                'batch_number' => 'PAR22A101',
                'quantity' => 250,
                'reserved_quantity' => 0,
                'purchase_price' => 12.00,
                'mrp' => 18.00,
                'selling_price' => 18.00,
                'mfg_offset_days' => -300,
                'exp_offset_days' => 180, // healthy future
                'rack' => 'Rack A',
                'shelf' => 'Shelf 1',
                'supplier' => 'MediSupply Co.',
                'notes' => 'Fast moving, keep front',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Azithromycin 500',
        'slug' => 'azithromycin-500',
        'generic_name' => 'Azithromycin',
        'company' => 'Cipla',
        'category' => 'Tablet',
        'schedule' => Schedule::H->value,
        'pack_size' => '3 tablets',
        'gst_percent' => 12.00,
        'description' => 'Macrolide antibiotic for bacterial infections.',
        'notes' => 'Antibiotic - prescription',
        'salts' => [['salt' => 'Azithromycin', 'strength' => '500 mg']],
        'batches' => [
            [
                'batch_number' => 'AZI23B45',
                'quantity' => 8, // LOW STOCK (< 10)
                'reserved_quantity' => 0,
                'purchase_price' => 45.00,
                'mrp' => 68.00,
                'selling_price' => 68.00,
                'mfg_offset_days' => -200,
                'exp_offset_days' => 15, // EXPIRING IN 15 DAYS
                'rack' => 'Rack A',
                'shelf' => 'Shelf 2',
                'supplier' => 'HealthDist',
                'notes' => 'Antibiotic - prescription',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Cetirizine Syrup',
        'slug' => 'cetirizine-syrup',
        'generic_name' => 'Cetirizine',
        'company' => "Dr Reddy's",
        'category' => 'Syrup',
        'schedule' => Schedule::OTC->value,
        'pack_size' => '60 ml',
        'gst_percent' => 12.00,
        'description' => 'Second generation antihistamine for allergic rhinitis and urticaria.',
        'notes' => 'For allergy',
        'salts' => [['salt' => 'Cetirizine', 'strength' => '5 mg/5ml']],
        'batches' => [
            [
                'batch_number' => 'CET23C09',
                'quantity' => 32,
                'reserved_quantity' => 0,
                'purchase_price' => 28.00,
                'mrp' => 42.00,
                'selling_price' => 42.00,
                'mfg_offset_days' => -180,
                'exp_offset_days' => 5, // EXPIRING IN 5 DAYS
                'rack' => 'Rack B',
                'shelf' => 'Shelf 1',
                'supplier' => 'MediSupply Co.',
                'notes' => 'For allergy',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Amoxicillin 250',
        'slug' => 'amoxicillin-250',
        'generic_name' => 'Amoxicillin',
        'company' => 'Mankind',
        'category' => 'Capsule',
        'schedule' => Schedule::H->value,
        'pack_size' => '10 capsules',
        'gst_percent' => 12.00,
        'description' => 'Broad-spectrum penicillin antibiotic.',
        'notes' => 'Antibiotic capsule',
        'salts' => [['salt' => 'Amoxicillin', 'strength' => '250 mg']],
        'batches' => [
            [
                'batch_number' => 'AMX22Z88',
                'quantity' => 120,
                'reserved_quantity' => 0,
                'purchase_price' => 22.00,
                'mrp' => 35.00,
                'selling_price' => 35.00,
                'mfg_offset_days' => -400,
                'exp_offset_days' => -10, // EXPIRED 10 DAYS AGO
                'rack' => 'Rack A',
                'shelf' => 'Shelf 3',
                'supplier' => 'PharmaLink',
                'notes' => 'EXPIRED - to be returned',
                'disposal_status' => 'none',
            ],
            [
                'batch_number' => 'AMX23A01',
                'quantity' => 5, // LOW STOCK
                'reserved_quantity' => 0,
                'purchase_price' => 23.00,
                'mrp' => 35.00,
                'selling_price' => 35.00,
                'mfg_offset_days' => -30,
                'exp_offset_days' => 365,
                'rack' => 'Rack A',
                'shelf' => 'Shelf 3',
                'supplier' => 'PharmaLink',
                'notes' => 'Fresh stock',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Betadine Ointment',
        'slug' => 'betadine-ointment',
        'generic_name' => 'Povidone Iodine',
        'company' => 'Win-Medicare',
        'category' => 'Ointment',
        'schedule' => Schedule::OTC->value,
        'pack_size' => '20 g',
        'gst_percent' => 12.00,
        'description' => 'Topical antiseptic for minor cuts, wounds, and burns.',
        'notes' => 'Topical use only',
        'salts' => [['salt' => 'Povidone Iodine', 'strength' => '5% w/w']],
        'batches' => [
            [
                'batch_number' => 'BET21D33',
                'quantity' => 18,
                'reserved_quantity' => 0,
                'purchase_price' => 35.00,
                'mrp' => 55.00,
                'selling_price' => 55.00,
                'mfg_offset_days' => -500,
                'exp_offset_days' => -35, // EXPIRED 35 DAYS AGO
                'rack' => 'Rack C',
                'shelf' => 'Shelf 2',
                'supplier' => 'Surgical House',
                'notes' => 'Expired last month',
                'disposal_status' => 'none',
            ],
            [
                'batch_number' => 'BET24B02',
                'quantity' => 45,
                'reserved_quantity' => 0,
                'purchase_price' => 36.00,
                'mrp' => 55.00,
                'selling_price' => 55.00,
                'mfg_offset_days' => -60,
                'exp_offset_days' => 300,
                'rack' => 'Rack C',
                'shelf' => 'Shelf 2',
                'supplier' => 'Surgical House',
                'notes' => 'Active inventory',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Insulin Glargine',
        'slug' => 'insulin-glargine',
        'generic_name' => 'Insulin Glargine',
        'company' => 'Biocon',
        'category' => 'Injection',
        'schedule' => Schedule::H->value,
        'pack_size' => '3 ml cartridge',
        'gst_percent' => 12.00,
        'description' => 'Long-acting basal insulin analogue for diabetes mellitus management.',
        'notes' => 'Refrigerate 2-8C',
        'salts' => [['salt' => 'Insulin Glargine', 'strength' => '100 IU/ml']],
        'batches' => [
            [
                'batch_number' => 'INS23G12',
                'quantity' => 6, // LOW STOCK (< 10)
                'reserved_quantity' => 0,
                'purchase_price' => 320.00,
                'mrp' => 450.00,
                'selling_price' => 450.00,
                'mfg_offset_days' => -90,
                'exp_offset_days' => 25, // EXPIRING IN 25 DAYS
                'rack' => 'Rack D',
                'shelf' => 'Shelf 1',
                'supplier' => 'ColdChain Dist',
                'notes' => 'Refrigerate 2-8C',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Salbutamol Inhaler',
        'slug' => 'salbutamol-inhaler',
        'generic_name' => 'Salbutamol',
        'company' => 'Cipla',
        'category' => 'Inhaler',
        'schedule' => Schedule::H->value,
        'pack_size' => '200 doses',
        'gst_percent' => 12.00,
        'description' => 'Fast-acting bronchodilator for asthma and COPD symptom relief.',
        'notes' => 'Asthalin',
        'salts' => [['salt' => 'Salbutamol', 'strength' => '100 mcg']],
        'batches' => [
            [
                'batch_number' => 'SAL23H07',
                'quantity' => 14,
                'reserved_quantity' => 0,
                'purchase_price' => 110.00,
                'mrp' => 165.00,
                'selling_price' => 165.00,
                'mfg_offset_days' => -120,
                'exp_offset_days' => 45,
                'rack' => 'Rack D',
                'shelf' => 'Shelf 2',
                'supplier' => 'HealthDist',
                'notes' => 'Asthalin',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'ORS Powder',
        'slug' => 'ors-powder',
        'generic_name' => 'Oral Rehydration Salts',
        'company' => 'FDC',
        'category' => 'Powder',
        'schedule' => Schedule::OTC->value,
        'pack_size' => '21.8 g sachet',
        'gst_percent' => 12.00,
        'description' => 'WHO-formula electrolyte replacement therapy for dehydration.',
        'notes' => 'Bulk stock',
        'salts' => [['salt' => 'Oral Rehydration Salts', 'strength' => '21.8 g']],
        'batches' => [
            [
                'batch_number' => 'ORS23P21',
                'quantity' => 200,
                'reserved_quantity' => 0,
                'purchase_price' => 8.00,
                'mrp' => 12.00,
                'selling_price' => 12.00,
                'mfg_offset_days' => -100,
                'exp_offset_days' => 400,
                'rack' => 'Rack B',
                'shelf' => 'Shelf 3',
                'supplier' => 'MediSupply Co.',
                'notes' => 'Bulk stock',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Ofloxacin Drops',
        'slug' => 'ofloxacin-drops',
        'generic_name' => 'Ofloxacin',
        'company' => 'Sun Pharma',
        'category' => 'Drops',
        'schedule' => Schedule::H->value,
        'pack_size' => '5 ml bottle',
        'gst_percent' => 12.00,
        'description' => 'Fluoroquinolone antibacterial eye/ear drops.',
        'notes' => 'Ear drops',
        'salts' => [['salt' => 'Ofloxacin', 'strength' => '0.3% w/v']],
        'batches' => [
            [
                'batch_number' => 'OFL23D14',
                'quantity' => 9, // LOW STOCK (< 10)
                'reserved_quantity' => 0,
                'purchase_price' => 18.00,
                'mrp' => 28.00,
                'selling_price' => 28.00,
                'mfg_offset_days' => -210,
                'exp_offset_days' => 5, // EXPIRING IN 5 DAYS
                'rack' => 'Rack C',
                'shelf' => 'Shelf 1',
                'supplier' => 'MediSupply Co.',
                'notes' => 'Ear drops',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Pantoprazole 40',
        'slug' => 'pantoprazole-40',
        'generic_name' => 'Pantoprazole',
        'company' => 'Aristo',
        'category' => 'Tablet',
        'schedule' => Schedule::H->value,
        'pack_size' => '10 tablets',
        'gst_percent' => 12.00,
        'description' => 'Proton pump inhibitor (PPI) for GERD and acid peptic disorders.',
        'notes' => 'Acidity',
        'salts' => [['salt' => 'Pantoprazole', 'strength' => '40 mg']],
        'batches' => [
            [
                'batch_number' => 'PAN23Q18',
                'quantity' => 45,
                'reserved_quantity' => 0,
                'purchase_price' => 30.00,
                'mrp' => 48.00,
                'selling_price' => 48.00,
                'mfg_offset_days' => -150,
                'exp_offset_days' => 85,
                'rack' => 'Rack B',
                'shelf' => 'Shelf 2',
                'supplier' => 'PharmaLink',
                'notes' => 'Acidity',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Cough Syrup DX',
        'slug' => 'cough-syrup-dx',
        'generic_name' => 'Dextromethorphan',
        'company' => 'Alembic',
        'category' => 'Syrup',
        'schedule' => Schedule::H->value,
        'pack_size' => '100 ml',
        'gst_percent' => 12.00,
        'description' => 'Antitussive cough syrup for dry irritating cough relief.',
        'notes' => 'Keep away from children',
        'salts' => [['salt' => 'Dextromethorphan', 'strength' => '10 mg/5ml']],
        'batches' => [
            [
                'batch_number' => 'COU23S99',
                'quantity' => 4, // LOW STOCK + EXPIRED
                'reserved_quantity' => 0,
                'purchase_price' => 52.00,
                'mrp' => 78.00,
                'selling_price' => 78.00,
                'mfg_offset_days' => -300,
                'exp_offset_days' => -5, // EXPIRED 5 DAYS AGO
                'rack' => 'Rack B',
                'shelf' => 'Shelf 1',
                'supplier' => 'HealthDist',
                'notes' => 'Expired batch',
                'disposal_status' => 'none',
            ],
            [
                'batch_number' => 'COU24A01',
                'quantity' => 25,
                'reserved_quantity' => 0,
                'purchase_price' => 53.00,
                'mrp' => 78.00,
                'selling_price' => 78.00,
                'mfg_offset_days' => -30,
                'exp_offset_days' => 200,
                'rack' => 'Rack B',
                'shelf' => 'Shelf 1',
                'supplier' => 'HealthDist',
                'notes' => 'Fresh stock',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Diclofenac Gel',
        'slug' => 'diclofenac-gel',
        'generic_name' => 'Diclofenac',
        'company' => 'Sun Pharma',
        'category' => 'Ointment',
        'schedule' => Schedule::OTC->value,
        'pack_size' => '30 g tube',
        'gst_percent' => 12.00,
        'description' => 'Topical NSAID gel for fast relief of muscular and joint pain.',
        'notes' => 'Pain relief',
        'salts' => [['salt' => 'Diclofenac', 'strength' => '1% w/w']],
        'batches' => [
            [
                'batch_number' => 'DIC23G44',
                'quantity' => 27,
                'reserved_quantity' => 0,
                'purchase_price' => 40.00,
                'mrp' => 62.00,
                'selling_price' => 62.00,
                'mfg_offset_days' => -110,
                'exp_offset_days' => 320,
                'rack' => 'Rack C',
                'shelf' => 'Shelf 2',
                'supplier' => 'MediSupply Co.',
                'notes' => 'Pain relief',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Vitamin D3 60k',
        'slug' => 'vitamin-d3-60k',
        'generic_name' => 'Cholecalciferol',
        'company' => 'Mankind',
        'category' => 'Capsule',
        'schedule' => Schedule::OTC->value,
        'pack_size' => '4 softgels',
        'gst_percent' => 12.00,
        'description' => 'High-potency Vitamin D3 supplement for bone health and deficiency.',
        'notes' => 'Low stock - reorder',
        'salts' => [['salt' => 'Cholecalciferol', 'strength' => '60,000 IU']],
        'batches' => [
            [
                'batch_number' => 'VIT23D60',
                'quantity' => 5, // LOW STOCK (< 10)
                'reserved_quantity' => 0,
                'purchase_price' => 18.00,
                'mrp' => 28.00,
                'selling_price' => 28.00,
                'mfg_offset_days' => -70,
                'exp_offset_days' => 12, // EXPIRING IN 12 DAYS
                'rack' => 'Rack A',
                'shelf' => 'Shelf 2',
                'supplier' => 'HealthDist',
                'notes' => 'Low stock - reorder',
                'disposal_status' => 'none',
            ],
        ],
    ],
    [
        'name' => 'Amoxyclav 625',
        'slug' => 'amoxyclav-625',
        'generic_name' => 'Amoxicillin+Clavulanate',
        'company' => 'Alkem',
        'category' => 'Tablet',
        'schedule' => Schedule::H1->value,
        'pack_size' => '10 tablets',
        'gst_percent' => 12.00,
        'description' => 'Co-amoxiclav broad spectrum antibiotic against beta-lactamase producing strains.',
        'notes' => 'Antibiotic combo',
        'salts' => [
            ['salt' => 'Amoxicillin', 'strength' => '500 mg'],
            ['salt' => 'Clavulanic Acid', 'strength' => '125 mg'],
        ],
        'batches' => [
            [
                'batch_number' => 'ACL23A62',
                'quantity' => 60,
                'reserved_quantity' => 0,
                'purchase_price' => 85.00,
                'mrp' => 125.00,
                'selling_price' => 125.00,
                'mfg_offset_days' => -95,
                'exp_offset_days' => 95,
                'rack' => 'Rack C',
                'shelf' => 'Shelf 3',
                'supplier' => 'PharmaLink',
                'notes' => 'Antibiotic combo',
                'disposal_status' => 'none',
            ],
        ],
    ],
];

foreach ($medicinesData as $medData) {
    $existing = $medicineRepo->findBySlug($medData['slug']);
    if ($existing) {
        $medicineId = (int)$existing['id'];
    } else {
        $medicineId = $medicineRepo->create([
            'name' => $medData['name'],
            'slug' => $medData['slug'],
            'generic_name' => $medData['generic_name'],
            'manufacturer_id' => $mfgMap[$medData['company']] ?? null,
            'category_id' => $catMap[$medData['category']],
            'schedule' => $medData['schedule'],
            'pack_size' => $medData['pack_size'],
            'gst_percent' => $medData['gst_percent'],
            'description' => $medData['description'],
            'notes' => $medData['notes'],
            'is_active' => 1,
            'is_featured' => 0,
        ]);
    }

    // Attach salts
    foreach ($medData['salts'] as $saltInfo) {
        $saltId = $saltMap[$saltInfo['salt']] ?? null;
        if ($saltId) {
            $saltRepo->attachToMedicine($medicineId, $saltId, $saltInfo['strength']);
        }
    }

    // Insert batches
    foreach ($medData['batches'] as $b) {
        $existingBatch = $batchRepo->findByMedicineAndBatchNumber($medicineId, $b['batch_number']);
        if (!$existingBatch) {
            $mfgDate = date('Y-m-d', strtotime("{$b['mfg_offset_days']} days"));
            $expDate = date('Y-m-d', strtotime("{$b['exp_offset_days']} days"));

            $rackId = $rackMap[$b['rack']] ?? null;
            $shelfKey = "{$b['rack']}/{$b['shelf']}";
            $shelfId = $shelfMap[$shelfKey] ?? null;
            $supplierId = $supplierMap[$b['supplier']] ?? null;

            $batchRepo->create([
                'medicine_id' => $medicineId,
                'batch_number' => $b['batch_number'],
                'quantity' => $b['quantity'],
                'reserved_quantity' => $b['reserved_quantity'],
                'purchase_price' => $b['purchase_price'],
                'mrp' => $b['mrp'],
                'selling_price' => $b['selling_price'],
                'mfg_date' => $mfgDate,
                'exp_date' => $expDate,
                'supplier_id' => $supplierId,
                'rack_id' => $rackId,
                'shelf_id' => $shelfId,
                'disposal_status' => $b['disposal_status'],
                'notes' => $b['notes'],
            ]);
        }
    }
}
echo "OK (" . count($medicinesData) . " medicines seeded with batches)\n";
echo "=== Seeding Completed Successfully! ===\n";
