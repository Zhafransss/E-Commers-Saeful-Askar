<?php

/*
| Temporary sample data for the static checkout page, in the API Contract shapes:
| `addresses` = ADDR-01 data, `regions` = REG-01 … REG-04 answers nested as a tree.
| Shipping rates are generated in resources/js/components/checkoutPage.js in the
| SHIP-01 shape. Delete this file once the page reads from /api/v1.
*/

$region = fn (string $id, string $name, array $children = [], ?string $postCode = null) => array_filter(
    ['id' => $id, 'name' => $name, 'post_code' => $postCode, 'children' => $children],
    fn ($value) => $value !== [] && $value !== null,
);

$regions = [
    $region('32', 'JAWA BARAT', [
        $region('3216', 'KAB. BEKASI', [
            $region('321605', 'TAMBUN UTARA', [
                $region('3216052001', 'Satriajaya', [], '17565'),
                $region('3216052006', 'Srijaya', [], '17510'),
                $region('3216052003', 'Karangsatria', [], '17510'),
            ]),
            $region('321604', 'TAMBUN SELATAN', [
                $region('3216042001', 'Mekarsari', [], '17510'),
                $region('3216042008', 'Tambun', [], '17510'),
            ]),
            $region('321608', 'CIKARANG UTARA', [
                $region('3216082002', 'Karangasih', [], '17530'),
            ]),
        ]),
        $region('3275', 'KOTA BEKASI', [
            $region('327501', 'BEKASI TIMUR', [
                $region('3275011001', 'Margahayu', [], '17113'),
                $region('3275011002', 'Bekasi Jaya', [], '17112'),
            ]),
        ]),
        $region('3273', 'KOTA BANDUNG', [
            $region('327315', 'COBLONG', [
                $region('3273151001', 'Dago', [], '40135'),
            ]),
        ]),
    ]),
    $region('31', 'DKI JAKARTA', [
        $region('3172', 'KOTA ADM. JAKARTA TIMUR', [
            $region('317203', 'DUREN SAWIT', [
                $region('3172031001', 'Pondok Bambu', [], '13430'),
                $region('3172031005', 'Klender', [], '13470'),
            ]),
        ]),
    ]),
    $region('35', 'JAWA TIMUR', [
        $region('3578', 'KOTA SURABAYA', [
            $region('357808', 'GUBENG', [
                $region('3578081001', 'Airlangga', [], '60286'),
            ]),
        ]),
    ]),
];

$addresses = [
    [
        'id' => 7,
        'label' => 'Rumah',
        'recipient_name' => 'Budi Santoso',
        'phone' => '081234567890',
        'address_line' => 'Jl. Melati No. 12, RT 03/RW 05',
        'province' => ['id' => '32', 'name' => 'JAWA BARAT'],
        'city' => ['id' => '3216', 'name' => 'KAB. BEKASI'],
        'district' => ['id' => '321605', 'name' => 'TAMBUN UTARA'],
        'subdistrict' => ['id' => '3216052001', 'name' => 'Satriajaya'],
        'post_code' => '17565',
        'is_default' => true,
    ],
    [
        'id' => 8,
        'label' => 'Kantor',
        'recipient_name' => 'Budi Santoso',
        'phone' => '081234567890',
        'address_line' => 'Jl. Pahlawan Revolusi No. 45, Gedung B Lt. 2',
        'province' => ['id' => '31', 'name' => 'DKI JAKARTA'],
        'city' => ['id' => '3172', 'name' => 'KOTA ADM. JAKARTA TIMUR'],
        'district' => ['id' => '317203', 'name' => 'DUREN SAWIT'],
        'subdistrict' => ['id' => '3172031001', 'name' => 'Pondok Bambu'],
        'post_code' => '13430',
        'is_default' => false,
    ],
];

return compact('regions', 'addresses');
