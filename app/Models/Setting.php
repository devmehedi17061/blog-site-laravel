<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'group', 'cast'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('key', $key)->first();

        return $row ? $row->typedValue() : $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general'): self
    {
        $cast = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'array',
            default => 'string',
        };

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
                'group' => $group,
                'cast' => $cast,
            ]
        );
    }

    public function typedValue(): mixed
    {
        return match ($this->cast) {
            'boolean' => (bool) $this->value,
            'integer' => (int) $this->value,
            'array' => json_decode((string) $this->value, true) ?? [],
            default => $this->value,
        };
    }
}
