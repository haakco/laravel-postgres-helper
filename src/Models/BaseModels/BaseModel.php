<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Models\BaseModels;

use Barryvdh\LaravelIdeHelper\Eloquent;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * HaakCo\LocationManager\Models\BaseModels\BaseModel.
 *
 * @method static Builder|BaseModel newModelQuery()
 * @method static Builder|BaseModel newQuery()
 * @method static Builder|BaseModel query()
 * @mixin Eloquent
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
     */
    protected static function booted(): void
    {
        parent::booted();
    }
}
