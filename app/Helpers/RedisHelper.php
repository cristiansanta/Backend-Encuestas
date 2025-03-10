<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisHelper
{
    public static function get($key)
    {
        try {
            return Redis::get($key);
        } catch (\Exception $e) {
            Log::error('Error al obtener clave de Redis: ' . $e->getMessage());
            return null;
        }
    }

    public static function set($key, $value, $ttl = 3600)
    {
        try {
            Redis::setex($key, $ttl, $value); // Establece valor con TTL
        } catch (\Exception $e) {
            Log::error('Error al establecer clave en Redis: ' . $e->getMessage());
        }
    }

    public static function exists($key)
    {
        try {
            return Redis::exists($key);
        } catch (\Exception $e) {
            Log::error('Error al verificar existencia de clave en Redis: ' . $e->getMessage());
            return false;
        }
    }

    public static function delete($key)
    {
        try {
            Redis::del($key);
        } catch (\Exception $e) {
            Log::error('Error al eliminar clave de Redis: ' . $e->getMessage());
        }
    }
}
