<?php
declare(strict_types=1);

/**
 * Definitions for the simple "one table, one form" records, used by
 * includes/crud.php. Table and column names here are trusted constants.
 *
 * Field types: text, email, int, money, select (needs 'fk'), photo (file name from public/assets/img/products/).
 */
return [
    'owner' => [
        'table'  => 'owner',
        'pk'     => 'owner_id',
        'label'  => 'Owner',
        'plural' => 'Owners',
        'order'  => 'owner_name',
        'list'   => ['owner_name', 'position', 'email'],
        'search' => ['owner_name', 'position', 'email'],
        'fields' => [
            'owner_name' => ['label' => 'Owner name', 'type' => 'text',  'required' => true, 'max' => 100],
            'position'   => ['label' => 'Position',   'type' => 'text',  'required' => true, 'max' => 50],
            'email'      => ['label' => 'Email',      'type' => 'email', 'required' => true, 'max' => 100, 'unique' => true],
        ],
    ],

    'staff' => [
        'table'  => 'staff',
        'pk'     => 'staff_id',
        'label'  => 'Staff member',
        'plural' => 'Staff',
        'order'  => 'staff_name',
        'list'   => ['staff_name', 'owner_id'],
        'search' => ['staff_name'],
        'fields' => [
            'staff_name' => ['label' => 'Staff name', 'type' => 'text', 'required' => true, 'max' => 100],
            'owner_id'   => [
                'label' => 'Employed by', 'type' => 'select', 'required' => true,
                'fk'    => ['table' => 'owner', 'key' => 'owner_id', 'label' => 'owner_name'],
            ],
        ],
    ],

    'product' => [
        'table'  => 'product',
        'pk'     => 'product_id',
        'label'  => 'Product',
        'plural' => 'Products',
        'order'  => 'product_name',
        'list'   => ['image', 'product_name', 'product_type', 'price', 'owner_id'],
        'search' => ['product_name', 'product_type'],
        'fields' => [
            'product_name' => ['label' => 'Product name', 'type' => 'text',  'required' => true, 'max' => 100],
            'product_type' => ['label' => 'Type (Main, Drink, Add-on...)', 'type' => 'text', 'required' => true, 'max' => 50],
            'price'        => ['label' => 'Price (PHP)', 'type' => 'money', 'required' => true],
            'image'        => ['label' => 'Photo', 'type' => 'photo', 'required' => false],
            'owner_id'     => [
                'label' => 'Managed by', 'type' => 'select', 'required' => true,
                'fk'    => ['table' => 'owner', 'key' => 'owner_id', 'label' => 'owner_name'],
            ],
        ],
    ],

    'customer' => [
        'table'  => 'customer',
        'pk'     => 'customer_id',
        'label'  => 'Customer',
        'plural' => 'Customers',
        'order'  => 'customer_name',
        'list'   => ['customer_name', 'contact_number', 'customer_group', 'address'],
        'search' => ['customer_name', 'contact_number', 'customer_group', 'address'],
        'fields' => [
            'customer_name'  => ['label' => 'Customer name', 'type' => 'text', 'required' => true, 'max' => 100],
            'contact_number' => [
                'label' => 'Contact number', 'type' => 'text', 'required' => false, 'max' => 20,
                'pattern' => '/^[0-9+\-\s()]{7,20}$/',
                'pattern_message' => 'Contact number may only contain digits, spaces, +, - and parentheses (7 to 20 characters).',
            ],
            'customer_group' => ['label' => 'Group (Regular, Corporate...)', 'type' => 'text', 'required' => false, 'max' => 50],
            'address'        => ['label' => 'Address', 'type' => 'text', 'required' => false, 'max' => 255],
        ],
    ],

    'stock_item' => [
        'table'  => 'stock_item',
        'pk'     => 'stock_item_id',
        'label'  => 'Stock item',
        'plural' => 'Stock items',
        'order'  => 'item_name',
        'list'   => ['item_name', 'minimum_level'],
        'search' => ['item_name'],
        'fields' => [
            'item_name'     => ['label' => 'Item name (include the unit, e.g. "Rice (kg)")', 'type' => 'text', 'required' => true, 'max' => 100, 'unique' => true],
            'minimum_level' => ['label' => 'Minimum level (reorder when below)', 'type' => 'int', 'required' => true, 'max' => 1000000],
        ],
    ],
];
