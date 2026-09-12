<?php
namespace App\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
class Settings
{
    public function all(): array
    {
        return Cache::remember('platform.settings', 300, fn () => DB::table('settings')->where('key', 'not like', 'secret.%')->pluck('value', 'key')
            ->map(fn ($v) => json_decode($v, true, 512, JSON_THROW_ON_ERROR))->all());
    }
    public function get(string $key, mixed $default = null): mixed { return $this->all()[$key] ?? $default; }
    public function update(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) DB::table('settings')->updateOrInsert(['key' => $key],
                ['value' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => now()]);
            app(Audit::class)->record('settings.updated', null, ['keys' => array_keys($values)]);
        });
        Cache::forget('platform.settings');
    }
    public function hasSecret(string $key): bool
    {
        return DB::table('settings')->where('key', 'secret.'.$key)->exists();
    }
    public function updateSecrets(array $values): void
    {
        $values = array_filter($values, fn ($value) => is_string($value) && $value !== '');
        if (!$values) return;
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                DB::table('settings')->updateOrInsert(['key' => 'secret.'.$key], [
                    'value' => json_encode(Crypt::encryptString($value), JSON_THROW_ON_ERROR),
                    'updated_at' => now(), 'created_at' => now(),
                ]);
            }
            app(Audit::class)->record('settings.secrets_updated', null, ['keys' => array_keys($values)]);
        });
        Cache::forget('platform.settings');
    }
    public function secret(string $key): ?string
    {
        $value = DB::table('settings')->where('key', 'secret.'.$key)->value('value');
        if (!is_string($value)) return null;
        try { return Crypt::decryptString(json_decode($value, true, 512, JSON_THROW_ON_ERROR)); }
        catch (\Throwable) { return null; }
    }
}
