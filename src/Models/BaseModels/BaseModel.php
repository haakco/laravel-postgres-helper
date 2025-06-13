<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Models\BaseModels;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * HaakCo\PostgresHelper\Models\BaseModels\BaseModel.
 *
 * @method static Builder|BaseModel newModelQuery()
 * @method static Builder|BaseModel newQuery()
 * @method static Builder|BaseModel query()
 * @method static BaseModel[]|$this[] get()
 * @method static BaseModel|$this findOrFail(int $id)
 * @method static BaseModel|$this|null find(int $id)
 * @method static void truncate()
 */
class BaseModel extends Model
{
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = \DateTimeInterface::ATOM;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The "booted" method of the model.
     */
    #[\Override]
    protected static function booted(): void
    {
        parent::booted();
    }
}
