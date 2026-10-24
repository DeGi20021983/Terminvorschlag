<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Administrative Einstellungen als Schlüssel-Wert-Paare.
 *
 * @property string $key
 * @property string|null $value
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
