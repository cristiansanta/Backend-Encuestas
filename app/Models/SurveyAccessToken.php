<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyAccessToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'email',
        'hash',
        'device_fingerprint',
        'ip_address',
        'user_agent',
        'first_access_at',
        'last_access_at',
        'access_count',
        'status'
    ];

    protected $casts = [
        'first_access_at' => 'datetime',
        'last_access_at' => 'datetime'
    ];

    /**
     * Registrar el primer acceso a un enlace de encuesta
     */
    public static function registerFirstAccess($surveyId, $email, $hash, $deviceFingerprint, $ipAddress, $userAgent)
    {
        return self::create([
            'survey_id' => $surveyId,
            'email' => $email,
            'hash' => $hash,
            'device_fingerprint' => $deviceFingerprint,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'first_access_at' => now(),
            'last_access_at' => now(),
            'access_count' => 1,
            'status' => 'active'
        ]);
    }

    /**
     * Actualizar acceso existente
     */
    public function updateAccess()
    {
        $this->update([
            'last_access_at' => now(),
            'access_count' => $this->access_count + 1
        ]);
        return $this;
    }

    /**
     * Verificar si el device fingerprint coincide
     */
    public function isDeviceMatch($deviceFingerprint)
    {
        return $this->device_fingerprint === $deviceFingerprint;
    }

    /**
     * Bloquear acceso
     */
    public function blockAccess()
    {
        $this->update(['status' => 'blocked']);
        return $this;
    }
}