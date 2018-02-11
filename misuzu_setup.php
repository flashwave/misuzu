<?php
/**
 * Setup script
 * @todo Move this into a CLI commands system.
 */

namespace Misuzu;

use Misuzu\Users\Role;
use Misuzu\Users\User;

require_once __DIR__ . '/misuzu.php';

$role = Role::find(1);

if ($role === null) {
    $role = Role::createRole('Member');
}

foreach (User::all() as $user) {
    if (!$user->hasRole($role)) {
        $user->addRole($role);
    }
}
