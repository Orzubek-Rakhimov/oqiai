<?php

namespace App\Models;

use App\Enums\Avatar;
use Database\Factories\ChildFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Child: a reading profile inside a Parent's account. It never logs in.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $grade
 * @property Avatar $avatar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $parent
 */
#[Fillable(['name', 'grade', 'avatar'])]
class Child extends Model
{
    /** @use HasFactory<ChildFactory> */
    use HasFactory;

    /**
     * Get the Parent who owns this Child.
     *
     * @return BelongsTo<User, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'avatar' => Avatar::class,
        ];
    }
}
