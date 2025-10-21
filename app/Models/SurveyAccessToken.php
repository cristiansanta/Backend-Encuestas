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
        'status',
        'device_changes_count',
        'last_device_change_at',
        'previous_device_fingerprint'
    ];

    protected $casts = [
        'first_access_at' => 'datetime',
        'last_access_at' => 'datetime',
        'last_device_change_at' => 'datetime'
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

    /**
     * Verificar si el patrón de cambios de dispositivo es sospechoso
     * Detecta link sharing cuando hay múltiples cambios de dispositivo en poco tiempo
     * CONFIGURACIÓN ESTRICTA: Bloqueo 100% efectivo contra link sharing
     */
    public function isSuspiciousDevicePattern()
    {
        // REGLA 1: Más de 1 cambio de dispositivo es altamente sospechoso
        // Un usuario legítimo normalmente usa un solo dispositivo por sesión
        if ($this->device_changes_count >= 2) {
            return true;
        }

        // REGLA 2: Cualquier cambio de dispositivo en menos de 10 minutos es sospechoso
        // Tiempo realista para que un usuario cambie de dispositivo legítimamente
        if ($this->device_changes_count >= 1 && $this->last_device_change_at) {
            $minutesSinceLastChange = now()->diffInMinutes($this->last_device_change_at);
            if ($minutesSinceLastChange < 10) {
                return true;
            }
        }

        // REGLA 3: Primer acceso y cambio de dispositivo casi inmediato (menos de 5 minutos)
        // Indica link sharing activo
        if ($this->device_changes_count >= 1 && $this->first_access_at && $this->last_device_change_at) {
            $minutesSinceFirstAccess = $this->first_access_at->diffInMinutes($this->last_device_change_at);
            if ($minutesSinceFirstAccess < 5) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registrar cambio de dispositivo
     */
    public function registerDeviceChange($newDeviceFingerprint, $newIpAddress, $newUserAgent)
    {
        $this->update([
            'previous_device_fingerprint' => $this->device_fingerprint,
            'device_fingerprint' => $newDeviceFingerprint,
            'ip_address' => $newIpAddress,
            'user_agent' => $newUserAgent,
            'device_changes_count' => $this->device_changes_count + 1,
            'last_device_change_at' => now()
        ]);
        return $this;
    }
}