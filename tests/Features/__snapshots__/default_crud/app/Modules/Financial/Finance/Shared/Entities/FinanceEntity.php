<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Shared\Entities;

use App\Shared\Entities\BaseEntity;
use App\Shared\Entities\IsDateString;
use App\Shared\Entities\IsDateStringFormat;

final class FinanceEntity extends BaseEntity
{
    public function __construct(
        public ?int $id,
        public ?int $created_by_user_id,
        public ?int $updated_by_user_id,
        #[IsDateString(IsDateStringFormat::ISO8601)]
        public ?string $created_at,
        #[IsDateString(IsDateStringFormat::ISO8601)]
        public ?string $updated_at,
    ) {}
}
