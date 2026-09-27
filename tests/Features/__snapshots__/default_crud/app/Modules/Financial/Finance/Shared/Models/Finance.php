<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Shared\Models;

use App\Models\ModelBase;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class Finance extends ModelBase
{
    use HasFactory;

    protected $fillable = [
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [];
}
