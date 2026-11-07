<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The store-wide settings admins change in the panel. There is one row.
 *
 * @property int $id
 * @property Money $signup_credit Credited to each new account. Zero turns the bonus off.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['signup_credit'])]
class StoreSettings extends Model
{
    public const int DEFAULT_SIGNUP_CREDIT_CENTS = 10000;

    public const int MAX_SIGNUP_CREDIT_CENTS = 50000;

    protected $table = 'store_settings';

    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['signup_credit' => self::DEFAULT_SIGNUP_CREDIT_CENTS]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signup_credit' => MoneyCast::class,
        ];
    }
}
