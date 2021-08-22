<?php

namespace App\Models\BaseModels;

use DateTimeInterface;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\BaseModels\BaseModel
 *
 * @method static Builder|BaseModel newModelQuery()
 * @method static Builder|BaseModel newQuery()
 * @method static Builder|BaseModel query()
 * @mixin Eloquent
 * @mixin IdeHelperBaseModel
 */
class BaseModel extends Model
{
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = DateTimeInterface::ATOM;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        parent::booted();
    }
}
