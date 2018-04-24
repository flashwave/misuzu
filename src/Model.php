<?php
namespace Misuzu;

use Closure;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Support\Collection;

/**
 * Class Model
 * @package Misuzu
 * @property-read \Carbon\Carbon|null $created_at
 * @property-read \Carbon\Carbon|null $updated_at
 */
abstract class Model extends BaseModel
{
}
