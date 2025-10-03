<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class URLIntegrityService
{
    /**
     * Generar hash de integridad para URL usando HMAC para seguridad máxima
     * INCLUYE: Fingerprinting del dispositivo para prevenir compartir enlaces
     *
     * @param int $surveyId
     * @param string $email
     * @param string $type (optional: 'fallback', 'reminder', etc.)
     * @return string
     */
    public static function generateHash($surveyId, $email, $type = 'standard')
    {
        $timestamp = now()->timestamp;

        // SEGURIDAD CRÍTICA: Incluir fingerprint del dispositivo para evitar compartir enlaces
        $deviceFingerprint = self::generateDeviceFingerprint();

        // SEGURIDAD CRÍTICA: Usar HMAC con clave secreta para evitar falsificación
        $secretKey = env('APP_KEY', 'default-secret-key');
        $dataToSign = "{$surveyId}|{$email}|{$type}|{$timestamp}|{$deviceFingerprint}";

        // Generar HMAC SHA-256
        $hmac = hash_hmac('sha256', $dataToSign, $secretKey);

        // Combinar timestamp con HMAC para verificación temporal
        $combinedHash = $timestamp . '.' . $deviceFingerprint . '.' . substr($hmac, 0, 24);

        // Codificar en base64 URL-safe
        $urlSafeHash = rtrim(strtr(base64_encode($combinedHash), '+/', '-_'), '=');

        Log::info('🔐 Secure hash generated with device fingerprint', [
            'survey_id' => $surveyId,
            'email' => $email,
            'type' => $type,
            'device_fingerprint' => $deviceFingerprint,
            'data_signed' => $dataToSign,
            'hash_length' => strlen($urlSafeHash)
        ]);

        return $urlSafeHash;
    }

    /**
     * Generar fingerprint único del dispositivo/sesión
     * PREVIENE: Compartir enlaces entre diferentes dispositivos/usuarios
     *
     * @return string
     */
    private static function generateDeviceFingerprint()
    {
        $request = request();

        // Recopilar información del dispositivo/sesión
        $fingerprintData = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accept_language' => $request->header('Accept-Language', 'unknown'),
            'accept_encoding' => $request->header('Accept-Encoding', 'unknown'),
            'session_id' => session()->getId(),
            // Agregar timestamp del día para renovación diaria
            'day' => date('Y-m-d')
        ];

        // Generar hash único del dispositivo
        $fingerprintString = implode('|', $fingerprintData);
        $fingerprint = substr(hash('sha256', $fingerprintString), 0, 8);

        Log::info('🔍 Device fingerprint generated', [
            'fingerprint' => $fingerprint,
            'ip' => $fingerprintData['ip'],
            'user_agent_hash' => substr(hash('sha256', $fingerprintData['user_agent']), 0, 8)
        ]);

        return $fingerprint;
    }

    /**
     * Validar integridad de URL con detalles del error
     * CRÍTICO: Verifica que el hash fue generado específicamente para este email exacto
     *
     * @param int $surveyId
     * @param string $email
     * @param string $providedHash
     * @return array
     */
    public static function validateHashWithDetails($surveyId, $email, $providedHash)
    {
        try {
            // Decodificar el email de la URL
            $decodedEmail = urldecode($email);

            // Logging de seguridad
            Log::info('🔍 HMAC URL Integrity Validation', [
                'survey_id' => $surveyId,
                'url_email' => $email,
                'decoded_email' => $decodedEmail,
                'provided_hash' => $providedHash,
                'hash_length' => strlen($providedHash),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Validaciones básicas del hash
            if (empty($providedHash)) {
                Log::warning('❌ Empty hash provided', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'ip' => request()->ip()
                ]);
                return ['valid' => false, 'error_type' => 'invalid_format'];
            }

            // COMPATIBILIDAD: Manejar tanto hashes nuevos (HMAC) como antiguos (base64 simple)
            if (strlen($providedHash) >= 40) {
                // Hash nuevo (HMAC) - usar validación HMAC
                return self::validateHMACHashWithDetails($surveyId, $decodedEmail, $providedHash);
            } else {
                // Hash antiguo (base64 simple) - usar validación antigua pero estricta
                return self::validateLegacyHashWithDetails($surveyId, $decodedEmail, $providedHash);
            }

        } catch (\Exception $e) {
            Log::error('❌ HMAC validation error', [
                'survey_id' => $surveyId,
                'email' => $email,
                'provided_hash' => $providedHash,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);
            return ['valid' => false, 'error_type' => 'validation_error'];
        }
    }

    /**
     * Validar integridad de URL usando HMAC para máxima seguridad
     * CRÍTICO: Verifica que el hash fue generado específicamente para este email exacto
     *
     * @param int $surveyId
     * @param string $email
     * @param string $providedHash
     * @return bool
     */
    public static function validateHash($surveyId, $email, $providedHash)
    {
        try {
            // Decodificar el email de la URL
            $decodedEmail = urldecode($email);

            // Logging de seguridad
            Log::info('🔍 HMAC URL Integrity Validation', [
                'survey_id' => $surveyId,
                'url_email' => $email,
                'decoded_email' => $decodedEmail,
                'provided_hash' => $providedHash,
                'hash_length' => strlen($providedHash),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Validaciones básicas del hash
            if (empty($providedHash)) {
                Log::warning('❌ Empty hash provided', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'ip' => request()->ip()
                ]);
                return false;
            }

            // COMPATIBILIDAD: Manejar tanto hashes nuevos (HMAC) como antiguos (base64 simple)
            if (strlen($providedHash) >= 40) {
                // Hash nuevo (HMAC) - usar validación HMAC
                return self::validateHMACHash($surveyId, $decodedEmail, $providedHash);
            } else {
                // Hash antiguo (base64 simple) - usar validación antigua pero estricta
                return self::validateLegacyHash($surveyId, $decodedEmail, $providedHash);
            }

        } catch (\Exception $e) {
            Log::error('❌ HMAC validation error', [
                'survey_id' => $surveyId,
                'email' => $email,
                'provided_hash' => $providedHash,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);
            return false;
        }
    }

    /**
     * Validar hash HMAC con detalles del error (nuevo sistema)
     */
    private static function validateHMACHashWithDetails($surveyId, $decodedEmail, $providedHash)
    {
        try {
            // Decodificar el hash desde base64 URL-safe
            $decodedHash = base64_decode(strtr($providedHash, '-_', '+/'));

            if (!$decodedHash || !strpos($decodedHash, '.')) {
                Log::warning('❌ HMAC hash decode failed', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'provided_hash' => $providedHash,
                    'ip' => request()->ip()
                ]);
                return ['valid' => false, 'error_type' => 'invalid_format'];
            }

            // Separar timestamp, device fingerprint y HMAC
            $hashParts = explode('.', $decodedHash);

            if (count($hashParts) === 3) {
                // NUEVO FORMATO: timestamp.fingerprint.hmac
                list($timestamp, $originalFingerprint, $providedHmac) = $hashParts;

                // SEGURIDAD CRÍTICA: Verificar device fingerprint
                $currentFingerprint = self::generateDeviceFingerprint();

                if ($originalFingerprint !== $currentFingerprint) {
                    Log::warning('❌ CRITICAL: Device fingerprint mismatch - Link sharing detected', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'original_fingerprint' => $originalFingerprint,
                        'current_fingerprint' => $currentFingerprint,
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'security_event' => 'LINK_SHARING_DETECTED'
                    ]);
                    return ['valid' => false, 'error_type' => 'device_mismatch'];
                }

                // Validar que el timestamp no sea muy antiguo (máximo 24 horas)
                $currentTime = now()->timestamp;
                if ($currentTime - $timestamp > 24 * 60 * 60) {
                    Log::warning('❌ HMAC hash expired', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_age_hours' => ($currentTime - $timestamp) / 3600,
                        'ip' => request()->ip()
                    ]);
                    return ['valid' => false, 'error_type' => 'hash_expired'];
                }

                // VALIDACIÓN CRÍTICA: Verificar HMAC con fingerprint incluido
                $secretKey = env('APP_KEY', 'default-secret-key');
                $validTypes = ['standard', 'fallback', 'reminder'];

                foreach ($validTypes as $type) {
                    $dataToSign = "{$surveyId}|{$decodedEmail}|{$type}|{$timestamp}|{$originalFingerprint}";
                    $expectedHmac = substr(hash_hmac('sha256', $dataToSign, $secretKey), 0, 24);

                    if (hash_equals($expectedHmac, $providedHmac)) {
                        Log::info('✅ HMAC validation successful with device verification', [
                            'survey_id' => $surveyId,
                            'email' => $decodedEmail,
                            'type' => $type,
                            'device_fingerprint' => $originalFingerprint,
                            'hash_age_minutes' => ($currentTime - $timestamp) / 60,
                            'ip' => request()->ip()
                        ]);
                        return ['valid' => true, 'error_type' => null];
                    }
                }

            } else if (count($hashParts) === 2) {
                // FORMATO LEGACY: timestamp.hmac (sin fingerprint)
                list($timestamp, $providedHmac) = $hashParts;

                Log::info('🔍 Processing legacy HMAC hash (no device fingerprint)', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'ip' => request()->ip()
                ]);

                // Validar que el timestamp no sea muy antiguo (máximo 24 horas)
                $currentTime = now()->timestamp;
                if ($currentTime - $timestamp > 24 * 60 * 60) {
                    Log::warning('❌ Legacy HMAC hash expired', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_age_hours' => ($currentTime - $timestamp) / 3600,
                        'ip' => request()->ip()
                    ]);
                    return ['valid' => false, 'error_type' => 'hash_expired'];
                }

                // VALIDACIÓN CRÍTICA: Verificar HMAC legacy (sin fingerprint)
                $secretKey = env('APP_KEY', 'default-secret-key');
                $validTypes = ['standard', 'fallback', 'reminder'];

                foreach ($validTypes as $type) {
                    $dataToSign = "{$surveyId}|{$decodedEmail}|{$type}|{$timestamp}";
                    $expectedHmac = substr(hash_hmac('sha256', $dataToSign, $secretKey), 0, 32);

                    if (hash_equals($expectedHmac, $providedHmac)) {
                        Log::info('✅ Legacy HMAC validation successful', [
                            'survey_id' => $surveyId,
                            'email' => $decodedEmail,
                            'type' => $type,
                            'hash_age_minutes' => ($currentTime - $timestamp) / 60,
                            'ip' => request()->ip()
                        ]);
                        return ['valid' => true, 'error_type' => null];
                    }
                }
            }

            Log::warning('❌ HMAC validation failed', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'provided_hash' => $providedHash,
                'ip' => request()->ip()
            ]);
            return ['valid' => false, 'error_type' => 'hash_tampering'];

        } catch (\Exception $e) {
            Log::error('❌ HMAC hash validation error', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);
            return ['valid' => false, 'error_type' => 'validation_error'];
        }
    }

    /**
     * Validar hash HMAC (nuevo sistema)
     */
    private static function validateHMACHash($surveyId, $decodedEmail, $providedHash)
    {
        try {
            // Decodificar el hash desde base64 URL-safe
            $decodedHash = base64_decode(strtr($providedHash, '-_', '+/'));

            if (!$decodedHash || !strpos($decodedHash, '.')) {
                Log::warning('❌ HMAC hash decode failed', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'provided_hash' => $providedHash,
                    'ip' => request()->ip()
                ]);
                return false;
            }

            // Separar timestamp, device fingerprint y HMAC
            $hashParts = explode('.', $decodedHash);

            if (count($hashParts) === 3) {
                // NUEVO FORMATO: timestamp.fingerprint.hmac
                list($timestamp, $originalFingerprint, $providedHmac) = $hashParts;

                // SEGURIDAD CRÍTICA: Verificar device fingerprint
                $currentFingerprint = self::generateDeviceFingerprint();

                if ($originalFingerprint !== $currentFingerprint) {
                    Log::warning('❌ CRITICAL: Device fingerprint mismatch - Link sharing detected', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'original_fingerprint' => $originalFingerprint,
                        'current_fingerprint' => $currentFingerprint,
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'security_event' => 'LINK_SHARING_DETECTED'
                    ]);
                    return false;
                }

                // Validar que el timestamp no sea muy antiguo (máximo 24 horas)
                $currentTime = now()->timestamp;
                if ($currentTime - $timestamp > 24 * 60 * 60) {
                    Log::warning('❌ HMAC hash expired', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_age_hours' => ($currentTime - $timestamp) / 3600,
                        'ip' => request()->ip()
                    ]);
                    return false;
                }

                // VALIDACIÓN CRÍTICA: Verificar HMAC con fingerprint incluido
                $secretKey = env('APP_KEY', 'default-secret-key');
                $validTypes = ['standard', 'fallback', 'reminder'];

                foreach ($validTypes as $type) {
                    $dataToSign = "{$surveyId}|{$decodedEmail}|{$type}|{$timestamp}|{$originalFingerprint}";
                    $expectedHmac = substr(hash_hmac('sha256', $dataToSign, $secretKey), 0, 24);

                    if (hash_equals($expectedHmac, $providedHmac)) {
                        Log::info('✅ HMAC validation successful with device verification', [
                            'survey_id' => $surveyId,
                            'email' => $decodedEmail,
                            'type' => $type,
                            'device_fingerprint' => $originalFingerprint,
                            'hash_age_minutes' => ($currentTime - $timestamp) / 60,
                            'ip' => request()->ip()
                        ]);
                        return true;
                    }
                }

            } else if (count($hashParts) === 2) {
                // FORMATO LEGACY: timestamp.hmac (sin fingerprint)
                list($timestamp, $providedHmac) = $hashParts;

                Log::info('🔍 Processing legacy HMAC hash (no device fingerprint)', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'ip' => request()->ip()
                ]);

                // Validar que el timestamp no sea muy antiguo (máximo 24 horas)
                $currentTime = now()->timestamp;
                if ($currentTime - $timestamp > 24 * 60 * 60) {
                    Log::warning('❌ Legacy HMAC hash expired', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_age_hours' => ($currentTime - $timestamp) / 3600,
                        'ip' => request()->ip()
                    ]);
                    return false;
                }

                // VALIDACIÓN CRÍTICA: Verificar HMAC legacy (sin fingerprint)
                $secretKey = env('APP_KEY', 'default-secret-key');
                $validTypes = ['standard', 'fallback', 'reminder'];

                foreach ($validTypes as $type) {
                    $dataToSign = "{$surveyId}|{$decodedEmail}|{$type}|{$timestamp}";
                    $expectedHmac = substr(hash_hmac('sha256', $dataToSign, $secretKey), 0, 32);

                    if (hash_equals($expectedHmac, $providedHmac)) {
                        Log::info('✅ Legacy HMAC validation successful', [
                            'survey_id' => $surveyId,
                            'email' => $decodedEmail,
                            'type' => $type,
                            'hash_age_minutes' => ($currentTime - $timestamp) / 60,
                            'ip' => request()->ip()
                        ]);
                        return true;
                    }
                }
            }

            Log::warning('❌ HMAC validation failed', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'provided_hash' => $providedHash,
                'ip' => request()->ip()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('❌ HMAC hash validation error', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);
            return false;
        }
    }

    /**
     * Validar hash legacy con detalles del error (sistema antiguo) con validación estricta
     */
    private static function validateLegacyHashWithDetails($surveyId, $decodedEmail, $providedHash)
    {
        try {
            Log::info('🔍 Legacy hash validation', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'hash' => $providedHash,
                'ip' => request()->ip()
            ]);

            // Decodificar el hash para ver su contenido
            $decodedHash = @base64_decode($providedHash, true);

            // VALIDACIÓN CRÍTICA: El hash debe ser válido en base64
            if ($decodedHash === false) {
                Log::warning('❌ Invalid base64 hash - tampering detected', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'provided_hash' => $providedHash,
                    'ip' => request()->ip(),
                    'security_event' => 'INVALID_BASE64_HASH'
                ]);
                return ['valid' => false, 'error_type' => 'invalid_format'];
            }

            // VALIDACIÓN CRÍTICA: El hash decodificado debe tener contenido mínimo
            if (empty($decodedHash) || strlen($decodedHash) < 5) {
                Log::warning('❌ Hash decoded content too short - tampering detected', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'provided_hash' => $providedHash,
                    'decoded_length' => strlen($decodedHash),
                    'decoded_content' => $decodedHash,
                    'ip' => request()->ip(),
                    'security_event' => 'HASH_TOO_SHORT'
                ]);
                return ['valid' => false, 'error_type' => 'hash_tampering'];
            }

            Log::info('🔍 Hash decoded content', [
                'decoded_content' => $decodedHash,
                'decoded_length' => strlen($decodedHash),
                'survey_id' => $surveyId,
                'email' => $decodedEmail
            ]);

            // FORMATO MUY ANTIGUO: surveyId-emailParcial (sin timestamp)
            if (preg_match('/^(\d+)-(.+)$/', $decodedHash, $matches)) {
                $hashSurveyId = $matches[1];
                $hashEmailPart = $matches[2];

                // VALIDACIÓN CRÍTICA: El hash email debe tener mínimo 8 caracteres para ser válido
                if (strlen($hashEmailPart) < 8) {
                    Log::warning('❌ Hash email part too short - possible tampering', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_email_part' => $hashEmailPart,
                        'hash_email_length' => strlen($hashEmailPart),
                        'provided_hash' => $providedHash,
                        'decoded_hash' => $decodedHash,
                        'ip' => request()->ip(),
                        'security_event' => 'HASH_TRUNCATION_DETECTED'
                    ]);
                    return ['valid' => false, 'error_type' => 'hash_tampering'];
                }

                // Verificar que el survey ID coincida
                if ($hashSurveyId != $surveyId) {
                    Log::warning('❌ Survey ID mismatch in hash', [
                        'expected' => $surveyId,
                        'found_in_hash' => $hashSurveyId,
                        'ip' => request()->ip()
                    ]);
                    return ['valid' => false, 'error_type' => 'hash_tampering'];
                }

                // VALIDACIÓN ESTRICTA: El email debe comenzar EXACTAMENTE con la parte del hash
                // Y la parte del hash debe ser suficientemente larga para ser única
                if (strpos($decodedEmail, $hashEmailPart) === 0) {
                    Log::info('✅ Very old legacy hash validation successful', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_email_part' => $hashEmailPart,
                        'hash_email_length' => strlen($hashEmailPart),
                        'validation_type' => 'very_old_format',
                        'ip' => request()->ip()
                    ]);
                    return ['valid' => true, 'error_type' => null];
                }

                Log::warning('❌ Email mismatch with hash content', [
                    'email' => $decodedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'expected_start' => $hashEmailPart,
                    'actual_email' => $decodedEmail,
                    'ip' => request()->ip(),
                    'security_event' => 'EMAIL_HASH_MISMATCH'
                ]);
                return ['valid' => false, 'error_type' => 'hash_tampering'];
            }

            // FORMATO NUEVO: Intentar reconstruir el hash EXACTAMENTE como se generó
            $validTypes = ['standard', 'fallback', 'reminder'];
            $timeWindow = 2 * 60 * 60; // Solo 2 horas para legacy
            $currentTime = now()->timestamp;

            for ($i = 0; $i <= $timeWindow; $i += 60) {
                $checkTime = $currentTime - $i;

                foreach ($validTypes as $type) {
                    // Reconstruir el hash exactamente como se generó en el frontend
                    $testData = "{$surveyId}-{$decodedEmail}-{$type}-{$checkTime}";
                    $testHash = base64_encode($testData);
                    $testCleanHash = preg_replace('/[+\/=]/', '', $testHash);
                    $testShortHash = substr($testCleanHash, 0, 20);

                    if ($testShortHash === $providedHash) {
                        Log::info('✅ Legacy hash validation successful', [
                            'survey_id' => $surveyId,
                            'email' => $decodedEmail,
                            'type' => $type,
                            'time_diff_minutes' => $i / 60,
                            'original_data' => $testData,
                            'ip' => request()->ip()
                        ]);
                        return ['valid' => true, 'error_type' => null];
                    }
                }
            }

            // Si llegamos aquí, el hash no tiene ningún formato conocido
            Log::warning('❌ CRITICAL: Hash format not recognized - tampering detected', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'provided_hash' => $providedHash,
                'decoded_content' => $decodedHash,
                'decoded_length' => strlen($decodedHash),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'security_event' => 'UNKNOWN_HASH_FORMAT'
            ]);

            return ['valid' => false, 'error_type' => 'hash_tampering'];

        } catch (\Exception $e) {
            Log::error('❌ Legacy hash validation error', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);
            return ['valid' => false, 'error_type' => 'validation_error'];
        }
    }

    /**
     * Validar hash legacy (sistema antiguo) con validación estricta
     */
    private static function validateLegacyHash($surveyId, $decodedEmail, $providedHash)
    {
        try {
            Log::info('🔍 Legacy hash validation', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'hash' => $providedHash,
                'ip' => request()->ip()
            ]);

            // Decodificar el hash para ver su contenido
            $decodedHash = @base64_decode($providedHash, true);

            // VALIDACIÓN CRÍTICA: El hash debe ser válido en base64
            if ($decodedHash === false) {
                Log::warning('❌ Invalid base64 hash - tampering detected', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'provided_hash' => $providedHash,
                    'ip' => request()->ip(),
                    'security_event' => 'INVALID_BASE64_HASH'
                ]);
                return false;
            }

            // VALIDACIÓN CRÍTICA: El hash decodificado debe tener contenido mínimo
            if (empty($decodedHash) || strlen($decodedHash) < 5) {
                Log::warning('❌ Hash decoded content too short - tampering detected', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'provided_hash' => $providedHash,
                    'decoded_length' => strlen($decodedHash),
                    'decoded_content' => $decodedHash,
                    'ip' => request()->ip(),
                    'security_event' => 'HASH_TOO_SHORT'
                ]);
                return false;
            }

            Log::info('🔍 Hash decoded content', [
                'decoded_content' => $decodedHash,
                'decoded_length' => strlen($decodedHash),
                'survey_id' => $surveyId,
                'email' => $decodedEmail
            ]);

            // FORMATO MUY ANTIGUO: surveyId-emailParcial (sin timestamp)
            if (preg_match('/^(\d+)-(.+)$/', $decodedHash, $matches)) {
                $hashSurveyId = $matches[1];
                $hashEmailPart = $matches[2];

                // VALIDACIÓN CRÍTICA: El hash email debe tener mínimo 8 caracteres para ser válido
                if (strlen($hashEmailPart) < 8) {
                    Log::warning('❌ Hash email part too short - possible tampering', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_email_part' => $hashEmailPart,
                        'hash_email_length' => strlen($hashEmailPart),
                        'provided_hash' => $providedHash,
                        'decoded_hash' => $decodedHash,
                        'ip' => request()->ip(),
                        'security_event' => 'HASH_TRUNCATION_DETECTED'
                    ]);
                    return false;
                }

                // Verificar que el survey ID coincida
                if ($hashSurveyId != $surveyId) {
                    Log::warning('❌ Survey ID mismatch in hash', [
                        'expected' => $surveyId,
                        'found_in_hash' => $hashSurveyId,
                        'ip' => request()->ip()
                    ]);
                    return false;
                }

                // VALIDACIÓN ESTRICTA: El email debe comenzar EXACTAMENTE con la parte del hash
                // Y la parte del hash debe ser suficientemente larga para ser única
                if (strpos($decodedEmail, $hashEmailPart) === 0) {
                    Log::info('✅ Very old legacy hash validation successful', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_email_part' => $hashEmailPart,
                        'hash_email_length' => strlen($hashEmailPart),
                        'validation_type' => 'very_old_format',
                        'ip' => request()->ip()
                    ]);
                    return true;
                }

                Log::warning('❌ Email mismatch with hash content', [
                    'email' => $decodedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'expected_start' => $hashEmailPart,
                    'actual_email' => $decodedEmail,
                    'ip' => request()->ip(),
                    'security_event' => 'EMAIL_HASH_MISMATCH'
                ]);
                return false;
            }

            // FORMATO NUEVO: Intentar reconstruir el hash EXACTAMENTE como se generó
            $validTypes = ['standard', 'fallback', 'reminder'];
            $timeWindow = 2 * 60 * 60; // Solo 2 horas para legacy
            $currentTime = now()->timestamp;

            for ($i = 0; $i <= $timeWindow; $i += 60) {
                $checkTime = $currentTime - $i;

                foreach ($validTypes as $type) {
                    // Reconstruir el hash exactamente como se generó en el frontend
                    $testData = "{$surveyId}-{$decodedEmail}-{$type}-{$checkTime}";
                    $testHash = base64_encode($testData);
                    $testCleanHash = preg_replace('/[+\/=]/', '', $testHash);
                    $testShortHash = substr($testCleanHash, 0, 20);

                    if ($testShortHash === $providedHash) {
                        Log::info('✅ Legacy hash validation successful', [
                            'survey_id' => $surveyId,
                            'email' => $decodedEmail,
                            'type' => $type,
                            'time_diff_minutes' => $i / 60,
                            'original_data' => $testData,
                            'ip' => request()->ip()
                        ]);
                        return true;
                    }
                }
            }

            // Si llegamos aquí, el hash no tiene ningún formato conocido
            Log::warning('❌ CRITICAL: Hash format not recognized - tampering detected', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'provided_hash' => $providedHash,
                'decoded_content' => $decodedHash,
                'decoded_length' => strlen($decodedHash),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'security_event' => 'UNKNOWN_HASH_FORMAT'
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('❌ Legacy hash validation error', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);
            return false;
        }
    }

    /**
     * Extraer el email original del hash decodificando la información
     *
     * @param string $providedHash
     * @return string|null
     */
    private static function extractOriginalEmailFromHash($providedHash)
    {
        try {
            // El hash son los primeros 16 caracteres de un base64
            // Necesitamos probar con diferentes completados para encontrar el email original

            $validTypes = ['standard', 'fallback', 'reminder'];
            $timeWindow = 24 * 60 * 60; // 24 horas
            $currentTime = now()->timestamp;

            // Buscar en las últimas 24 horas
            for ($i = 0; $i <= $timeWindow; $i += 60) {
                $checkTime = $currentTime - $i;

                foreach ($validTypes as $type) {
                    // Probar con diferentes emails comunes para encontrar coincidencias
                    // Esto es necesario porque el hash es truncado

                    // Generar el patrón esperado y ver si coincide
                    $testPattern = ".*-.*-{$type}-{$checkTime}";

                    // Intentar decodificar el hash con diferentes completados
                    for ($length = 20; $length <= 50; $length += 2) {
                        $testHash = $providedHash . str_repeat('A', $length - 16);
                        $decoded = @base64_decode($testHash, true);

                        if ($decoded && preg_match('/^(\d+)-([^-]+@[^-]+\.[^-]+)-(' . implode('|', $validTypes) . ')-(\d+)$/', $decoded, $matches)) {
                            if ($matches[3] === $type && abs($matches[4] - $checkTime) < 60) {
                                return $matches[2]; // Email encontrado
                            }
                        }
                    }
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('❌ Error extracting original email from hash', [
                'hash' => $providedHash,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verificar que el hash se puede regenerar con los parámetros exactos
     *
     * @param int $surveyId
     * @param string $email
     * @param string $providedHash
     * @return bool
     */
    private static function verifyHashRegeneration($surveyId, $email, $providedHash)
    {
        try {
            // ESTRATEGIA SIMPLIFICADA: Solo validar que se puede regenerar el hash con estos parámetros EXACTOS
            $validTypes = ['standard', 'fallback', 'reminder'];
            $timeWindow = 24 * 60 * 60; // 24 horas
            $currentTime = now()->timestamp;

            // Decodificar el email de la URL para comparación exacta
            $decodedEmail = urldecode($email);

            Log::info('🔍 Hash verification attempt', [
                'survey_id' => $surveyId,
                'url_email' => $email,
                'decoded_email' => $decodedEmail,
                'provided_hash' => $providedHash,
                'ip' => request()->ip()
            ]);

            for ($i = 0; $i <= $timeWindow; $i += 60) { // Verificar cada minuto en las últimas 24h
                $checkTime = $currentTime - $i;

                foreach ($validTypes as $type) {
                    // CRÍTICO: Usar el email decodificado para la verificación
                    $testDataToHash = "{$surveyId}-{$decodedEmail}-{$type}-{$checkTime}";
                    $testHash = base64_encode($testDataToHash);
                    $testCleanHash = preg_replace('/[+\/=]/', '', $testHash);
                    $testShortHash = substr($testCleanHash, 0, 16);

                    if ($testShortHash === $providedHash) {
                        Log::info('✅ Hash integrity verified - EXACT match found', [
                            'survey_id' => $surveyId,
                            'email' => $decodedEmail,
                            'type' => $type,
                            'time_diff_minutes' => $i / 60,
                            'original_data' => $testDataToHash,
                            'ip' => request()->ip()
                        ]);
                        return true;
                    }
                }
            }

            // Si llegamos aquí, NO se pudo regenerar el hash con estos parámetros
            Log::warning('❌ CRITICAL: Hash tampering detected - Cannot regenerate hash with provided parameters', [
                'survey_id' => $surveyId,
                'email' => $email,
                'decoded_email' => $decodedEmail,
                'provided_hash' => $providedHash,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('❌ Hash structure verification error', [
                'survey_id' => $surveyId,
                'email' => $email,
                'hash' => $providedHash,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);
            return false;
        }
    }

    /**
     * Validar que el email no ha sido modificado
     *
     * @param string $email
     * @return bool
     */
    public static function validateEmailFormat($email)
    {
        // Decodificar URL
        $decodedEmail = urldecode($email);

        // Validar formato estricto de email
        if (!filter_var($decodedEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('❌ Invalid email format detected', [
                'original' => $email,
                'decoded' => $decodedEmail,
                'ip' => request()->ip()
            ]);
            return false;
        }

        // Verificar que no tiene caracteres sospechosos adicionales
        if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $decodedEmail)) {
            Log::warning('❌ Email tampering detected', [
                'original' => $email,
                'decoded' => $decodedEmail,
                'ip' => request()->ip()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Generar URL completa con hash de integridad
     *
     * @param int $surveyId
     * @param string $email
     * @param string $type
     * @param string $baseUrl
     * @return string
     */
    public static function generateSecureUrl($surveyId, $email, $type = 'standard', $baseUrl = null)
    {
        if (!$baseUrl) {
            $baseUrl = env('FRONTEND_URL', 'http://149.130.180.163:5173');
        }

        $hash = self::generateHash($surveyId, $email, $type);
        $encodedEmail = urlencode($email);

        return "{$baseUrl}/encuestados/survey-view-manual/{$surveyId}?email={$encodedEmail}&hash={$hash}";
    }
}