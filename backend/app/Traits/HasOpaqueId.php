<?php

namespace App\Traits;

use App\Support\SafeHashids;

/**
 * Opaque public IDs (specs/14): integer PKs stay internal; the API exposes
 * short hashes. Route-model binding decodes transparently; tampered values
 * resolve to null (404, no oracle). owner_id (User, internal) stays numeric.
 */
trait HasOpaqueId
{
    protected static function opaqueCodec(): SafeHashids
    {
        $salt = hash('sha256', (string) config('app.key').'|'.static::class);

        return new SafeHashids($salt, 8);
    }

    public static function encodeId(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return static::opaqueCodec()->encode($id);
    }

    public static function decodeId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        $value = (string) $value;
        // Fast reject: hashes are 8+ chars from the Hashids alphabet.
        if (! preg_match('/^[A-Za-z0-9]{8,64}$/', $value)) {
            return null;
        }
        $out = static::opaqueCodec()->decode($value);

        return $out === [] ? null : $out[0];
    }

    public function opaqueId(): string
    {
        return static::encodeId($this->getKey());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $id = static::decodeId($value);
        if ($id === null) {
            return null;
        }

        return $this->where($this->getKeyName(), $id)->first();
    }
}
