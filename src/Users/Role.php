<?php
namespace Misuzu\Users;

use Misuzu\Colour;
use Misuzu\Model;

class Role extends Model
{
    protected $primaryKey = 'role_id';

    public static function createRole(
        string $name,
        ?int $hierarchy = null,
        ?Colour $colour = null,
        ?string $title = null,
        ?string $description = null,
        bool $secret = false
    ): Role {
        $hierarchy = $hierarchy ?? 1;
        $colour = $colour ?? Colour::none();

        $role = new Role;
        $role->role_hierarchy = $hierarchy;
        $role->role_name = $name;
        $role->role_title = $title;
        $role->role_description = $description;
        $role->role_secret = $secret;
        $role->role_colour = $colour->raw;
        $role->save();

        return $role;
    }

    public function addUser(User $user, bool $setDisplay = false): void
    {
        $user->addRole($this, $setDisplay);
    }

    public function removeUser(User $user): void
    {
        $user->removeRole($this);
    }

    public function hasUser(User $user): bool
    {
        return $user->hasRole($this);
    }

    public function getRoleColourAttribute(int $colour): Colour
    {
        return new Colour($colour);
    }

    public function setRoleColourAttribute(Colour $colour): void
    {
        $this->attributes['role_colour'] = $colour->getRaw();
    }

    public function getRoleDescriptionAttribute(?string $description): string
    {
        return empty($description) ? '' : $description;
    }

    public function setRoleDescriptionAttribute(string $description): void
    {
        $this->attributes['role_description'] = empty($description) ? null : $description;
    }

    public function users()
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }
}
