<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    public static function get(string $key, $default = null)
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, $value, ?string $description = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'description' => $description]);
    }
}
