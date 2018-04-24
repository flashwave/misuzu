<?php
namespace Misuzu\Users;

use Misuzu\Colour;
use Misuzu\Model;

/**
 * Class Role
 * @package Misuzu\Users
 * @property-read int $role_id
 * @property int $role_hierarchy
 * @property string $role_name
 * @property string $role_title
 * @property string $role_description
 * @property bool $role_secret
 * @property Colour $role_colour
 * @property-read array $users
 */
class Role extends Model
{
    /**
     * @var string
     */
    protected $primaryKey = 'role_id';

    /**
     * Creates a new role.
     * @param string      $name
     * @param int|null    $hierarchy
     * @param Colour|null $colour
     * @param null|string $title
     * @param null|string $description
     * @param bool        $secret
     * @return Role
     */
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
        $role->role_colour = $colour->getRaw();
        $role->save();

        return $role;
    }

    /**
     * Adds this role to a user.
     * @param User $user
     * @param bool $setDisplay
     */
    public function addUser(User $user, bool $setDisplay = false): void
    {
        $user->addRole($this, $setDisplay);
    }

    /**
     * Removes this role from a user.
     * @param User $user
     */
    public function removeUser(User $user): void
    {
        $user->removeRole($this);
    }

    /**
     * Checks if this user has this role.
     * @param User $user
     * @return bool
     */
    public function hasUser(User $user): bool
    {
        return $user->hasRole($this);
    }

    /**
     * Getter for the role_colour attribute.
     * @param int $colour
     * @return Colour
     */
    public function getRoleColourAttribute(int $colour): Colour
    {
        return new Colour($colour);
    }

    /**
     * Setter for the role_colour attribute.
     * @param Colour $colour
     */
    public function setRoleColourAttribute(Colour $colour): void
    {
        $this->attributes['role_colour'] = $colour->getRaw();
    }

    /**
     * Getter for the role_description attribute.
     * @param null|string $description
     * @return string
     */
    public function getRoleDescriptionAttribute(?string $description): string
    {
        return empty($description) ? '' : $description;
    }

    /**
     * Setter for the role_description attribute.
     * @param string $description
     */
    public function setRoleDescriptionAttribute(string $description): void
    {
        $this->attributes['role_description'] = empty($description) ? null : $description;
    }

    /**
     * Users relation.
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }
}
