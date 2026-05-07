<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'user';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://github.com/codeigniter4/shield/blob/develop/docs/quickstart.md#change-available-groups for more info
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Super Admin',
            'description' => 'Complete control of the site.',
        ],
        'admin' => [
            'title'       => 'Admin',
            'description' => 'Day to day administrators of the site.',
        ],
        'developer' => [
            'title'       => 'Developer',
            'description' => 'Site programmers.',
        ],
        'user' => [
            'title'       => 'User',
            'description' => 'General users of the site. Often customers.',
        ],
        'beta' => [
            'title'       => 'Beta User',
            'description' => 'Has access to beta-level features.',
        ],
        'categories' => [
            'title'       => 'Categories',
            'description' => 'Can be assigned category management responsibilities.',
        ],
        'batches' => [
            'title'       => 'Production Batches',
            'description' => 'Can work with production batch records.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        'admin.access'        => 'Can access the sites admin area',
        'admin.settings'      => 'Can access the main site settings',
        'users.manage-admins' => 'Can manage other admins',
        'users.create'        => 'Can create new non-admin users',
        'users.edit'          => 'Can edit existing non-admin users',
        'users.delete'        => 'Can delete existing non-admin users',
        'beta.access'         => 'Can access beta-level features',
        'reports.catalog.view' => 'Can view the report catalog',
        'reports.dashboard.view' => 'Can view the reporting dashboard',
        'reports.sales.view' => 'Can view sales reports',
        'reports.inventory.view' => 'Can view inventory and stock reports',
        'reports.production.view' => 'Can view production reports',
        'reports.expenses.view' => 'Can view expense reports',
        'reports.customers.view' => 'Can view customer reports',
        'reports.suppliers.view' => 'Can view supplier reports',
        'reports.staff.view' => 'Can view staff reports',
        'reports.finance.view' => 'Can view financial reports',
        'reports.audit.view' => 'Can view audit and risk reports',
        'reports.forecasting.view' => 'Can view forecasting reports',
        'reports.alerts.view' => 'Can view report alerts and insights',
        'reports.export' => 'Can export reports',
        'reports.print' => 'Can print reports',
        'reports.custom.create' => 'Can create custom reports',
        'reports.custom.edit' => 'Can edit custom reports',
        'reports.custom.delete' => 'Can delete custom reports',
        'reports.custom.share' => 'Can share custom reports',
        'reports.custom.run' => 'Can run custom reports',
        'categories.manage' => 'Can manage product and production categories',
        'production.batches.manage' => 'Can manage production batches',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [
        'superadmin' => [
            'admin.*',
            'users.*',
            'beta.*',
            'reports.*',
            'categories.*',
            'production.*',
        ],
        'admin' => [
            'admin.access',
            'users.create',
            'users.edit',
            'users.delete',
            'beta.access',
            'reports.*',
            'categories.*',
            'production.*',
        ],
        'developer' => [
            'admin.access',
            'admin.settings',
            'users.create',
            'users.edit',
            'beta.access',
        ],
        'user' => [],
        'beta' => [
            'beta.access',
        ],
        'categories' => [
            'categories.manage',
        ],
        'batches' => [
            'production.batches.manage',
        ],
    ];
}
