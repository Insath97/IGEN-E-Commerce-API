<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            /* Access Management */
            ['name' => 'Permission Index',  'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Create', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Update', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Permission Delete', 'group_name' => 'Access Management Permissions'],

            ['name' => 'Role Index',  'group_name' => 'Access Management Permissions'],
            ['name' => 'Role List',   'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Create', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Update', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Role Delete', 'group_name' => 'Access Management Permissions'],

            ['name' => 'Admin User Index',  'group_name' => 'Access Management Permissions'],
            ['name' => 'Admin User Create', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Admin User Update', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Admin User Delete', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Admin User Activate', 'group_name' => 'Access Management Permissions'],
            ['name' => 'Admin User Deactivate', 'group_name' => 'Access Management Permissions'],

            /* Customer Management */
            ['name' => 'Customer Index',  'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Create', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Update', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Delete', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Activate', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Deactivate', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Verify', 'group_name' => 'Customer Management Permissions'],

            /* Brand Management */
            ['name' => 'Brand Index',  'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Create', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Update', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Delete', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Activate', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Deactivate', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Featured', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Restore', 'group_name' => 'Brand Management Permissions'],
            ['name' => 'Brand Force Delete', 'group_name' => 'Brand Management Permissions'],

            /* Category Management */
            ['name' => 'Category Index',  'group_name' => 'Category Management Permissions'],
            ['name' => 'Category List',   'group_name' => 'Category Management Permissions'],
            ['name' => 'Category Create', 'group_name' => 'Category Management Permissions'],
            ['name' => 'Category Update', 'group_name' => 'Category Management Permissions'],
            ['name' => 'Category Delete', 'group_name' => 'Category Management Permissions'],
            ['name' => 'Category Restore', 'group_name' => 'Category Management Permissions'],
            ['name' => 'Category Force Delete', 'group_name' => 'Category Management Permissions'],
            ['name' => 'Category Activate', 'group_name' => 'Category Management Permissions'],
            ['name' => 'Category Deactivate', 'group_name' => 'Category Management Permissions'],
            ['name' => 'Category Featured', 'group_name' => 'Category Management Permissions'],

            /* Product Management */
            ['name' => 'Product Index',  'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Show',   'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Create', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Update', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Delete', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Restore', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Force Delete', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Activate', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Deactivate', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Featured', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Trending', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Publish', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Archive', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Variant Activate', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Variant Deactivate', 'group_name' => 'Product Management Permissions'],

            /* Product Variant Management */
            ['name' => 'Product Variant Index',  'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Create', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Update', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Delete', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Restore', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Force Delete', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Activate', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Deactivate', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Toggle', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Featured', 'group_name' => 'Product Management Permissions'],
            ['name' => 'Product Variant Trending', 'group_name' => 'Product Management Permissions'],

            /* Coupon Management */
            ['name' => 'Coupon Index',  'group_name' => 'Coupon Management Permissions'],
            ['name' => 'Coupon Create', 'group_name' => 'Coupon Management Permissions'],
            ['name' => 'Coupon Update', 'group_name' => 'Coupon Management Permissions'],
            ['name' => 'Coupon Delete', 'group_name' => 'Coupon Management Permissions'],
            ['name' => 'Coupon Activate', 'group_name' => 'Coupon Management Permissions'],
            ['name' => 'Coupon Deactivate', 'group_name' => 'Coupon Management Permissions'],
            ['name' => 'Coupon Toggle', 'group_name' => 'Coupon Management Permissions'],
            ['name' => 'Coupon Usage', 'group_name' => 'Coupon Management Permissions'],

            /* Order Management */
            ['name' => 'Order Index',  'group_name' => 'Order Management Permissions'],
            ['name' => 'Order Statistics', 'group_name' => 'Order Management Permissions'],
            ['name' => 'Order Verify', 'group_name' => 'Order Management Permissions'],
            ['name' => 'Order Status Update', 'group_name' => 'Order Management Permissions'],
            ['name' => 'Order Payment Update', 'group_name' => 'Order Management Permissions'],

            /* Review Management */
            ['name' => 'Review Index',  'group_name' => 'Review Management Permissions'],
            ['name' => 'Review Status Update', 'group_name' => 'Review Management Permissions'],
            ['name' => 'Review Delete', 'group_name' => 'Review Management Permissions'],

            /* Contact Management */
            ['name' => 'Contact Index',  'group_name' => 'Contact Management Permissions'],
            ['name' => 'Contact Show',   'group_name' => 'Contact Management Permissions'],
            ['name' => 'Contact Reply',  'group_name' => 'Contact Management Permissions'],
            ['name' => 'Contact Delete', 'group_name' => 'Contact Management Permissions'],

            /* Setting Management */
            ['name' => 'Setting Index',  'group_name' => 'Setting Management Permissions'],
            ['name' => 'Setting Update', 'group_name' => 'Setting Management Permissions'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission['name'],
                'group_name' => $permission['group_name'],
                'guard_name' => 'api',
            ]);
        }
    }
}
