<?php
namespace Misuzu;

use Illuminate\Database\Eloquent\Model as BaseModel;

/**
 * Class Model
 * @package Misuzu
 * @property-read \Carbon\Carbon|null $created_at
 * @property-read \Carbon\Carbon|null $updated_at
 */
abstract class Model extends BaseModel
{
}
