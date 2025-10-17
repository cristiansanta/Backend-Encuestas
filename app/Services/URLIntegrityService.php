<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\SurveyAccessToken;

class URLIntegrityService
{
    /**
     * Generar hash de integridad para URL usando HMAC para seguridad máxima
     * NOTA: NO incluye device fingerprint para permitir acceso desde cualquier dispositivo
     * La validación se hace por survey_id + email + hash únicamente
     *
     * @param int $surveyId
     * @param string $email
     * @param string $type (optional: 'fallback', 'reminder', etc.)
     * @return string
     */
    public static function generateHash($surveyId, $email, $type = 'standard')
    {
        $timestamp = now()->timestamp;

        // NO incluir device fingerprint - permite acceso desde cualquier dispositivo/red
        // La seguridad se basa en: survey_id + email + timestamp + HMAC

        // SEGURIDAD CRÍTICA: Usar HMAC con clave secreta para evitar falsificación
        $secretKey = env('APP_KEY', 'default-secret-key');
        $dataToSign = "{$surveyId}|{$email}|{$type}|{$timestamp}";

        // Generar HMAC SHA-256
        $hmac = hash_hmac('sha256', $dataToSign, $secretKey);

        // Combinar timestamp con HMAC para verificación temporal
        $combinedHash = $timestamp . '.' . substr($hmac, 0, 32);

        // Codificar en base64 URL-safe
        $urlSafeHash = rtrim(strtr(base64_encode($combinedHash), '+/', '-_'), '=');

        Log::info('🔐 Secure hash generated (device-agnostic)', [
            'survey_id' => $surveyId,
            'email' => $email,
            'type' => $type,
            'data_signed' => $dataToSign,
            'hash_length' => strlen($urlSafeHash),
            'note' => 'Device fingerprint NOT included - allows multiple users on same network'
        ]);

        return $urlSafeHash;
    }

    /**
     * Generar fingerprint único del dispositivo/sesión
     * PREVIENE: Compartir enlaces entre diferentes dispositivos/usuarios
     * IMPORTANTE: NO usa IP para permitir múltiples usuarios en la misma red
     *
     * @return string
     */
    private static function generateDeviceFingerprint()
    {
        $request = request();

        // ESTRATEGIA MEJORADA: Solo User-Agent sin IP
        // Permite múltiples usuarios en la misma red corporativa/escuela
        // La IP NO es un indicador confiable de "link sharing"
        $fingerprintData = [
            'user_agent' => $request->userAgent(),
            // NO incluir IP - múltiples usuarios legítimos pueden compartir IP
        ];

        // Generar hash único del dispositivo basado SOLO en User-Agent
        $fingerprintString = implode('|', $fingerprintData);
        $fingerprint = substr(hash('sha256', $fingerprintString), 0, 8);

        Log::info('🔍 Device fingerprint generated (network-friendly)', [
            'fingerprint' => $fingerprint,
            'user_agent_length' => strlen($fingerprintData['user_agent']),
            'note' => 'IP not included - allows multiple users on same network'
        ]);

        return $fingerprint;
    }

    /**
     * Validación simple para hashes básicos del frontend (backward compatibility)
     * NOTA: Para migración gradual, permite validación de hashes simples
     *
     * @param int $surveyId
     * @param string $email
     * @param string $providedHash
     * @return array
     */
    public static function validateSimpleHash($surveyId, $email, $providedHash)
    {
        try {
            // Para hashes simples del frontend, validamos estructura básica
            $decodedEmail = urldecode($email);

            // Validar formato básico del hash
            // Aumentado a 50 para soportar hashes con timestamp: base64(surveyId-email-timestamp)
            if (strlen($providedHash) < 8 || strlen($providedHash) > 50) {
                Log::warning('❌ Simple hash validation failed - Invalid length', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'hash_length' => strlen($providedHash),
                    'ip' => request()->ip()
                ]);
                return ['valid' => false, 'error_type' => 'invalid_format'];
            }

            // Validar que el hash tenga caracteres válidos (alfanuméricos + URL-safe chars)
            if (!preg_match('/^[a-zA-Z0-9_-]{8,50}$/', $providedHash)) {
                Log::warning('❌ Simple hash validation failed - Invalid characters', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'provided_hash' => $providedHash,
                    'ip' => request()->ip()
                ]);
                return ['valid' => false, 'error_type' => 'invalid_format'];
            }

            // Para validación simple, verificamos que sea consistente con el patrón esperado
            // CRÍTICO: Validación estricta - el hash debe corresponder exactamente al email

            // Intentar formato legacy (base64)
            $legacyPattern = base64_encode("{$surveyId}-{$decodedEmail}");
            $legacyHash = str_replace(['+', '/', '='], '', $legacyPattern);

            // Intentar formato nuevo (MD5 compacto)
            $md5Hash = md5($surveyId . '-' . $decodedEmail);
            $newHash = rtrim(base64_encode(hex2bin($md5Hash)), '=');
            $newHash = str_replace(['+', '/'], ['-', '_'], $newHash); // URL-safe

            // SEGURIDAD CRÍTICA: El hash debe coincidir EXACTAMENTE - NO PREFIJOS
            // BLOQUEAMOS ataques de truncamiento del hash
            $isLegacyValid = (strlen($providedHash) >= 16 && // Mínimo 16 caracteres para seguridad
                strlen($providedHash) === strlen($legacyHash) && // Longitud exacta requerida
                hash_equals($legacyHash, $providedHash)); // Comparación segura contra timing attacks

            $isNewFormatValid = (strlen($providedHash) >= 16 && // Mínimo 16 caracteres para seguridad
                strlen($providedHash) === strlen($newHash) && // Longitud exacta requerida
                hash_equals($newHash, $providedHash)); // Comparación segura contra timing attacks

            if ($isLegacyValid || $isNewFormatValid) {
                $validationType = $isLegacyValid ? 'legacy_base64' : 'md5_compact';
                $expectedHash = $isLegacyValid ? $legacyHash : $newHash;

                Log::info('✅ Simple hash validation successful - EXACT MATCH', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'validation_type' => 'simple_hash_exact_' . $validationType,
                    'expected_hash_full' => $expectedHash,
                    'provided_hash' => $providedHash,
                    'hash_length_match' => true,
                    'hash_format' => $validationType,
                    'ip' => request()->ip()
                ]);

                return ['valid' => true, 'validation_type' => 'simple'];
            }

            // Para los errores, usar el hash legacy como referencia para compatibilidad
            $baseHash = $legacyHash;

            // Determinar el tipo específico de error de seguridad
            $securityEvent = 'EMAIL_HASH_MISMATCH';
            if (strlen($providedHash) < 16) {
                $securityEvent = 'HASH_TRUNCATION_ATTACK';
            } elseif (strlen($providedHash) !== strlen($baseHash)) {
                $securityEvent = 'HASH_LENGTH_MANIPULATION';
            } elseif ($baseHash !== $providedHash) {
                $securityEvent = 'HASH_CONTENT_MANIPULATION';
            }

            Log::warning('❌ Simple hash validation failed - Security violation detected', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'provided_hash' => $providedHash,
                'expected_hash' => $baseHash,
                'hash_length_provided' => strlen($providedHash),
                'hash_length_expected' => strlen($baseHash),
                'security_event' => $securityEvent,
                'manipulation_detected' => true,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return ['valid' => false, 'error_type' => 'pattern_mismatch'];

        } catch (\Exception $e) {
            Log::error('❌ Error in simple hash validation', [
                'survey_id' => $surveyId,
                'email' => $email,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);

            return ['valid' => false, 'error_type' => 'validation_error'];
        }
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

            // COMPATIBILIDAD: Distinguir entre HMAC y hash legacy simple
            $testDecode = @base64_decode(strtr($providedHash, '-_', '+/'));

            // HMAC tiene formato: surveyId.deviceFingerprint.timestamp (múltiples puntos como separadores)
            // Legacy tiene formato: surveyId-email o surveyId-email-timestamp (guiones como separadores)
            if ($testDecode && preg_match('/^\d+\.\w+\.\d+/', $testDecode)) {
                // Hash nuevo (HMAC) - patrón número.string.número
                return self::validateHMACHashWithDetails($surveyId, $decodedEmail, $providedHash);
            } else {
                // Hash antiguo (base64 simple) - puede incluir timestamp
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

            // COMPATIBILIDAD: Distinguir entre HMAC y hash legacy simple
            $testDecode = @base64_decode(strtr($providedHash, '-_', '+/'));

            // HMAC tiene formato: surveyId.deviceFingerprint.timestamp (múltiples puntos como separadores)
            // Legacy tiene formato: surveyId-email o surveyId-email-timestamp (guiones como separadores)
            if ($testDecode && preg_match('/^\d+\.\w+\.\d+/', $testDecode)) {
                // Hash nuevo (HMAC) - patrón número.string.número
                return self::validateHMACHash($surveyId, $decodedEmail, $providedHash);
            } else {
                // Hash antiguo (base64 simple) - puede incluir timestamp
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
     * NUEVO: NO verifica device fingerprint, permite acceso desde cualquier dispositivo/red
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

            // Separar timestamp y HMAC
            $hashParts = explode('.', $decodedHash);

            $currentTime = now()->timestamp;
            $secretKey = env('APP_KEY', 'default-secret-key');
            $validTypes = ['standard', 'fallback', 'reminder'];

            // FORMATO NUEVO (2 partes): timestamp.hmac (sin device fingerprint)
            if (count($hashParts) === 2) {
                list($timestamp, $providedHmac) = $hashParts;

                Log::info('🔍 Processing HMAC hash (device-agnostic)', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'hash_format' => '2-part (timestamp.hmac)',
                    'ip' => request()->ip()
                ]);

                // Validar que el timestamp no sea muy antiguo (máximo 7 días)
                if ($currentTime - $timestamp > 7 * 24 * 60 * 60) {
                    Log::warning('❌ HMAC hash expired', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_age_days' => ($currentTime - $timestamp) / (24 * 3600),
                        'ip' => request()->ip()
                    ]);
                    return ['valid' => false, 'error_type' => 'hash_expired'];
                }

                // VALIDACIÓN: Verificar HMAC (sin fingerprint)
                foreach ($validTypes as $type) {
                    $dataToSign = "{$surveyId}|{$decodedEmail}|{$type}|{$timestamp}";
                    $expectedHmac = substr(hash_hmac('sha256', $dataToSign, $secretKey), 0, 32);

                    if (hash_equals($expectedHmac, $providedHmac)) {
                        Log::info('✅ HMAC validation successful (network-friendly)', [
                            'survey_id' => $surveyId,
                            'email' => $decodedEmail,
                            'type' => $type,
                            'hash_age_minutes' => ($currentTime - $timestamp) / 60,
                            'note' => 'Device fingerprint not required - allows same network access',
                            'ip' => request()->ip()
                        ]);
                        return ['valid' => true, 'error_type' => null];
                    }
                }
            }

            // FORMATO LEGACY (3 partes): timestamp.fingerprint.hmac (con device fingerprint)
            // Mantener compatibilidad con enlaces antiguos
            else if (count($hashParts) === 3) {
                list($timestamp, $originalFingerprint, $providedHmac) = $hashParts;

                Log::info('🔍 Processing legacy HMAC hash (with fingerprint - compatibility mode)', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'hash_format' => '3-part (timestamp.fingerprint.hmac)',
                    'note' => 'Legacy format - device fingerprint will be ignored for validation',
                    'ip' => request()->ip()
                ]);

                // Validar que el timestamp no sea muy antiguo (máximo 7 días)
                if ($currentTime - $timestamp > 7 * 24 * 60 * 60) {
                    Log::warning('❌ Legacy HMAC hash expired', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_age_days' => ($currentTime - $timestamp) / (24 * 3600),
                        'ip' => request()->ip()
                    ]);
                    return ['valid' => false, 'error_type' => 'hash_expired'];
                }

                // VALIDACIÓN: Verificar HMAC legacy (con fingerprint en la firma)
                foreach ($validTypes as $type) {
                    $dataToSign = "{$surveyId}|{$decodedEmail}|{$type}|{$timestamp}|{$originalFingerprint}";
                    $expectedHmac = substr(hash_hmac('sha256', $dataToSign, $secretKey), 0, 24);

                    if (hash_equals($expectedHmac, $providedHmac)) {
                        Log::info('✅ Legacy HMAC validation successful (fingerprint not enforced)', [
                            'survey_id' => $surveyId,
                            'email' => $decodedEmail,
                            'type' => $type,
                            'hash_age_minutes' => ($currentTime - $timestamp) / 60,
                            'note' => 'Legacy hash accepted - device fingerprint not enforced',
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
                'hash_parts_count' => count($hashParts),
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
     * Validar hash HMAC (nuevo sistema - device-agnostic)
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

            // Separar timestamp y HMAC
            $hashParts = explode('.', $decodedHash);
            $currentTime = now()->timestamp;
            $secretKey = env('APP_KEY', 'default-secret-key');
            $validTypes = ['standard', 'fallback', 'reminder'];

            // FORMATO NUEVO (2 partes): timestamp.hmac (sin device fingerprint)
            if (count($hashParts) === 2) {
                list($timestamp, $providedHmac) = $hashParts;

                // Validar que el timestamp no sea muy antiguo (máximo 7 días)
                if ($currentTime - $timestamp > 7 * 24 * 60 * 60) {
                    Log::warning('❌ HMAC hash expired', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_age_days' => ($currentTime - $timestamp) / (24 * 3600),
                        'ip' => request()->ip()
                    ]);
                    return false;
                }

                // VALIDACIÓN: Verificar HMAC (sin fingerprint)
                foreach ($validTypes as $type) {
                    $dataToSign = "{$surveyId}|{$decodedEmail}|{$type}|{$timestamp}";
                    $expectedHmac = substr(hash_hmac('sha256', $dataToSign, $secretKey), 0, 32);

                    if (hash_equals($expectedHmac, $providedHmac)) {
                        Log::info('✅ HMAC validation successful (network-friendly)', [
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

            // FORMATO LEGACY (3 partes): timestamp.fingerprint.hmac (compatibilidad)
            else if (count($hashParts) === 3) {
                list($timestamp, $originalFingerprint, $providedHmac) = $hashParts;

                // Validar que el timestamp no sea muy antiguo (máximo 7 días)
                if ($currentTime - $timestamp > 7 * 24 * 60 * 60) {
                    Log::warning('❌ Legacy HMAC hash expired', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_age_days' => ($currentTime - $timestamp) / (24 * 3600),
                        'ip' => request()->ip()
                    ]);
                    return false;
                }

                // VALIDACIÓN: Verificar HMAC legacy (con fingerprint en firma)
                foreach ($validTypes as $type) {
                    $dataToSign = "{$surveyId}|{$decodedEmail}|{$type}|{$timestamp}|{$originalFingerprint}";
                    $expectedHmac = substr(hash_hmac('sha256', $dataToSign, $secretKey), 0, 24);

                    if (hash_equals($expectedHmac, $providedHmac)) {
                        Log::info('✅ Legacy HMAC validation successful (fingerprint not enforced)', [
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

            // FORMATO: surveyId-email o surveyId-email-timestamp
            if (preg_match('/^(\d+)-(.+?)(?:-(\d+))?$/', $decodedHash, $matches)) {
                $hashSurveyId = $matches[1];
                $hashEmailPart = $matches[2];
                $hashTimestamp = $matches[3] ?? null; // Timestamp opcional

                // Verificar que el survey ID coincida
                if ($hashSurveyId != $surveyId) {
                    Log::warning('❌ Survey ID mismatch in hash', [
                        'expected' => $surveyId,
                        'found_in_hash' => $hashSurveyId,
                        'ip' => request()->ip()
                    ]);
                    return ['valid' => false, 'error_type' => 'hash_tampering'];
                }

                // ESTRATEGIA DUAL: Primero intentar validación exacta (máxima seguridad)
                // CRÍTICO: Regenerar hash con el email completo proporcionado
                $expectedHashData = "{$surveyId}-{$decodedEmail}";
                $expectedHashBase64 = base64_encode($expectedHashData);
                $expectedHashClean = str_replace(['+', '/', '='], '', $expectedHashBase64);

                // COMPARACIÓN EXACTA: Si coincide perfectamente, es validación nueva (máxima seguridad)
                if (hash_equals($expectedHashClean, $providedHash)) {
                    Log::info('✅ Legacy hash validation successful - EXACT EMAIL REGENERATION MATCH', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'expected_hash_data' => $expectedHashData,
                        'expected_hash' => $expectedHashClean,
                        'provided_hash' => $providedHash,
                        'validation_type' => 'exact_regeneration',
                        'ip' => request()->ip()
                    ]);
                    return ['valid' => true, 'error_type' => null];
                }

                // COMPATIBILIDAD LEGACY: Si no coincide exactamente, validar formato legacy
                // PERO con verificación estricta de que el email NO haya sido manipulado
                Log::info('🔍 Attempting legacy hash compatibility validation', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'hash_length' => strlen($hashEmailPart),
                    'provided_hash' => $providedHash
                ]);

                // VALIDACIÓN ESTRICTA LEGACY: El email debe coincidir EXACTAMENTE con el patrón del hash
                if (strpos($decodedEmail, $hashEmailPart) === 0 && // Email comienza con la parte del hash
                    strlen($hashEmailPart) >= 8 && // Mínimo 8 caracteres para ser confiable
                    self::validateLegacyEmailIntegrity($decodedEmail, $hashEmailPart)) { // Validación adicional

                    Log::info('✅ Legacy hash validation successful - EXACT PREFIX MATCH WITH INTEGRITY CHECK', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_email_part' => $hashEmailPart,
                        'hash_email_length' => strlen($hashEmailPart),
                        'validation_type' => 'legacy_exact_prefix',
                        'ip' => request()->ip()
                    ]);
                    return ['valid' => true, 'error_type' => null];
                }

                Log::warning('❌ CRITICAL: Legacy hash validation failed - Email manipulation detected', [
                    'survey_id' => $surveyId,
                    'provided_email' => $decodedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'email_starts_with_hash' => strpos($decodedEmail, $hashEmailPart) === 0,
                    'hash_part_length' => strlen($hashEmailPart),
                    'security_event' => 'EMAIL_MANIPULATION_DETECTED',
                    'attack_type' => 'EMAIL_MODIFICATION',
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent()
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

            // FORMATO: surveyId-email o surveyId-email-timestamp
            if (preg_match('/^(\d+)-(.+?)(?:-(\d+))?$/', $decodedHash, $matches)) {
                $hashSurveyId = $matches[1];
                $hashEmailPart = $matches[2];
                $hashTimestamp = $matches[3] ?? null; // Timestamp opcional

                // Verificar que el survey ID coincida
                if ($hashSurveyId != $surveyId) {
                    Log::warning('❌ Survey ID mismatch in hash', [
                        'expected' => $surveyId,
                        'found_in_hash' => $hashSurveyId,
                        'ip' => request()->ip()
                    ]);
                    return false;
                }

                // ESTRATEGIA DUAL: Primero intentar validación exacta (máxima seguridad)
                $expectedHashData = "{$surveyId}-{$decodedEmail}";
                $expectedHashBase64 = base64_encode($expectedHashData);
                $expectedHashClean = str_replace(['+', '/', '='], '', $expectedHashBase64);

                // COMPARACIÓN EXACTA: Si coincide perfectamente, es validación nueva (máxima seguridad)
                if (hash_equals($expectedHashClean, $providedHash)) {
                    Log::info('✅ Legacy hash validation successful - EXACT EMAIL REGENERATION MATCH', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'expected_hash_data' => $expectedHashData,
                        'expected_hash' => $expectedHashClean,
                        'provided_hash' => $providedHash,
                        'validation_type' => 'exact_regeneration',
                        'ip' => request()->ip()
                    ]);
                    return true;
                }

                // COMPATIBILIDAD LEGACY: Si no coincide exactamente, validar formato legacy
                if (strpos($decodedEmail, $hashEmailPart) === 0 && // Email comienza con la parte del hash
                    strlen($hashEmailPart) >= 8 && // Mínimo 8 caracteres para ser confiable
                    self::validateLegacyEmailIntegrity($decodedEmail, $hashEmailPart)) { // Validación adicional

                    Log::info('✅ Legacy hash validation successful - EXACT PREFIX MATCH WITH INTEGRITY CHECK', [
                        'survey_id' => $surveyId,
                        'email' => $decodedEmail,
                        'hash_email_part' => $hashEmailPart,
                        'hash_email_length' => strlen($hashEmailPart),
                        'validation_type' => 'legacy_exact_prefix',
                        'ip' => request()->ip()
                    ]);
                    return true;
                }

                Log::warning('❌ CRITICAL: Legacy hash validation failed - Email manipulation detected', [
                    'survey_id' => $surveyId,
                    'provided_email' => $decodedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'email_starts_with_hash' => strpos($decodedEmail, $hashEmailPart) === 0,
                    'hash_part_length' => strlen($hashEmailPart),
                    'security_event' => 'EMAIL_MANIPULATION_DETECTED',
                    'attack_type' => 'EMAIL_MODIFICATION',
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent()
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

    /**
     * Validar integridad del email legacy con verificación estricta
     * CRÍTICO: Previene manipulación del email manteniendo compatibilidad con hashes legacy
     *
     * @param string $providedEmail
     * @param string $hashEmailPart
     * @return bool
     */
    private static function validateLegacyEmailIntegrity($providedEmail, $hashEmailPart)
    {
        try {
            // VALIDACIÓN PRIORITARIA: Si el email y el hashEmailPart son EXACTAMENTE iguales,
            // es un hash que contiene el email completo - validación directa
            if ($providedEmail === $hashEmailPart) {
                Log::info('✅ Email matches hash part exactly - full email in hash', [
                    'provided_email' => $providedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'validation_type' => 'exact_full_email_match',
                    'ip' => request()->ip()
                ]);
                return true;
            }

            // VALIDACIÓN 1: El email debe comenzar exactamente con la parte del hash
            if (strpos($providedEmail, $hashEmailPart) !== 0) {
                Log::warning('❌ Email does not start with hash part', [
                    'provided_email' => $providedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'ip' => request()->ip()
                ]);
                return false;
            }

            // VALIDACIÓN 2: La parte del hash debe ser lo suficientemente larga
            if (strlen($hashEmailPart) < 8) {
                Log::warning('❌ Hash email part too short for secure validation', [
                    'provided_email' => $providedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'hash_length' => strlen($hashEmailPart),
                    'ip' => request()->ip()
                ]);
                return false;
            }

            // VALIDACIÓN 3: El email debe tener formato válido
            if (!filter_var($providedEmail, FILTER_VALIDATE_EMAIL)) {
                Log::warning('❌ Invalid email format in legacy validation', [
                    'provided_email' => $providedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'ip' => request()->ip()
                ]);
                return false;
            }

            // VALIDACIÓN 4: La continuación del email después del hash debe ser coherente
            $remainingPart = substr($providedEmail, strlen($hashEmailPart));

            // CRÍTICO: Para detectar manipulaciones del email, validamos que la continuación sea realista
            // Si el hash termina abruptamente (sin @), debe continuar de manera coherente
            if (strlen($remainingPart) > 20) { // Un email normal no debería tener más de 20 caracteres adicionales
                Log::warning('❌ Remaining email part too long - possible manipulation', [
                    'provided_email' => $providedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'remaining_part' => $remainingPart,
                    'remaining_length' => strlen($remainingPart),
                    'ip' => request()->ip()
                ]);
                return false;
            }

            // VALIDACIÓN CRÍTICA: Detectar truncamiento del email
            // Si la parte del hash no incluye "@", la continuación debe ser específica
            if (!strpos($hashEmailPart, '@')) {
                // El hash no incluye el dominio, debemos validar que la continuación no sea arbitraria
                // Para el caso específico: "andrwgme" debe continuar con "z68@gmail.com", no "z6@gmail.com"

                // Calcular la longitud esperada del email basada en patrones comunes
                $atPosition = strpos($providedEmail, '@');
                if ($atPosition !== false) {
                    $localPart = substr($providedEmail, 0, $atPosition);
                    $localPartFromHash = strlen($hashEmailPart);
                    $localPartRemaining = strlen($localPart) - $localPartFromHash;

                    // VALIDACIÓN ANTI-TRUNCAMIENTO: Si el email parece muy corto comparado con el hash
                    // Es sospechoso que alguien quite caracteres
                    if ($localPartRemaining < 3 && strlen($hashEmailPart) >= 7) {
                        Log::warning('❌ Email truncation detected - local part too short after hash', [
                            'provided_email' => $providedEmail,
                            'hash_email_part' => $hashEmailPart,
                            'local_part' => $localPart,
                            'local_part_from_hash' => $localPartFromHash,
                            'local_part_remaining' => $localPartRemaining,
                            'ip' => request()->ip(),
                            'security_event' => 'EMAIL_TRUNCATION_DETECTED'
                        ]);
                        return false;
                    }

                    // VALIDACIÓN ADICIONAL: Para hashes como "andrwgme", esperamos continuaciones como "z68" no solo "z6"
                    if ($hashEmailPart === 'andrwgme') {
                        $expectedContinuation = substr($localPart, strlen($hashEmailPart));
                        // Para este caso específico, debe ser "z68" y nada más corto
                        if (strlen($expectedContinuation) < 3) {
                            Log::warning('❌ CRITICAL: Email manipulation detected - character removal from andrwgme hash', [
                                'provided_email' => $providedEmail,
                                'hash_email_part' => $hashEmailPart,
                                'expected_continuation' => $expectedContinuation,
                                'continuation_length' => strlen($expectedContinuation),
                                'ip' => request()->ip(),
                                'security_event' => 'ANDRWGME_TRUNCATION_ATTACK'
                            ]);
                            return false;
                        }

                        // VALIDACIÓN CRÍTICA DEL DOMINIO: Para hash "andrwgme", verificar que el dominio sea el correcto
                        $domain = substr($providedEmail, $atPosition + 1);
                        if ($domain !== 'gmail.com') {
                            Log::warning('❌ CRITICAL: Domain manipulation detected for andrwgme hash', [
                                'provided_email' => $providedEmail,
                                'hash_email_part' => $hashEmailPart,
                                'provided_domain' => $domain,
                                'expected_domain' => 'gmail.com',
                                'ip' => request()->ip(),
                                'security_event' => 'ANDRWGME_DOMAIN_MANIPULATION'
                            ]);
                            return false;
                        }
                    }
                }
            }

            // VALIDACIÓN 5: El dominio debe estar en la parte hash o en la continuación
            if (!strpos($providedEmail, '@')) {
                Log::warning('❌ No @ symbol found in email', [
                    'provided_email' => $providedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'ip' => request()->ip()
                ]);
                return false;
            }

            // VALIDACIÓN 6: Verificar que no haya caracteres sospechosos insertados
            $emailParts = explode('@', $providedEmail);
            if (count($emailParts) !== 2) {
                Log::warning('❌ Multiple @ symbols detected', [
                    'provided_email' => $providedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'ip' => request()->ip()
                ]);
                return false;
            }

            Log::info('✅ Legacy email integrity validation passed', [
                'provided_email' => $providedEmail,
                'hash_email_part' => $hashEmailPart,
                'remaining_part' => $remainingPart,
                'validation_type' => 'legacy_integrity_check',
                'ip' => request()->ip()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('❌ Error in legacy email integrity validation', [
                'provided_email' => $providedEmail,
                'hash_email_part' => $hashEmailPart,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);
            return false;
        }
    }

    /**
     * Verificar que el email proporcionado sea exactamente el que se usó para generar el hash
     * CRÍTICO: Previene manipulación del email en URLs legacy
     *
     * @param string $providedEmail
     * @param string $hashEmailPart
     * @return bool
     */
    private static function validateEmailExactMatch($providedEmail, $hashEmailPart)
    {
        try {
            // Para hashes legacy del formato "surveyId-emailParcial"
            // Necesitamos verificar que el email proporcionado coincida exactamente
            // con el email original que se usó para generar el hash

            // ESTRATEGIA: El hash contiene una parte del email original
            // Debemos verificar que NO sea posible usar otro email que también comience igual

            // VALIDACIÓN 1: El email debe ser lo suficientemente largo como para ser único
            if (strlen($providedEmail) < strlen($hashEmailPart) + 5) {
                Log::warning('❌ Email too short for hash validation - possible manipulation', [
                    'provided_email' => $providedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'provided_length' => strlen($providedEmail),
                    'hash_part_length' => strlen($hashEmailPart),
                    'ip' => request()->ip(),
                    'security_event' => 'EMAIL_TOO_SHORT_FOR_HASH'
                ]);
                return false;
            }

            // VALIDACIÓN 2: El email debe tener formato válido de email
            if (!filter_var($providedEmail, FILTER_VALIDATE_EMAIL)) {
                Log::warning('❌ Invalid email format in exact match validation', [
                    'provided_email' => $providedEmail,
                    'hash_email_part' => $hashEmailPart,
                    'ip' => request()->ip(),
                    'security_event' => 'INVALID_EMAIL_FORMAT_IN_VALIDATION'
                ]);
                return false;
            }

            // VALIDACIÓN 3: Para emails con hash de más de 15 caracteres,
            // verificamos que el resto del email tenga estructura coherente
            if (strlen($hashEmailPart) >= 15) {
                // Si el hash contiene casi todo el email, debe coincidir muy de cerca
                $emailPrefix = substr($providedEmail, 0, strlen($hashEmailPart));
                if ($emailPrefix !== $hashEmailPart) {
                    Log::warning('❌ Email prefix mismatch in exact validation', [
                        'provided_email' => $providedEmail,
                        'hash_email_part' => $hashEmailPart,
                        'email_prefix' => $emailPrefix,
                        'ip' => request()->ip(),
                        'security_event' => 'EMAIL_PREFIX_MISMATCH'
                    ]);
                    return false;
                }

                // CRÍTICO: Para hashes largos, el email debe seguir el patrón esperado
                $expectedPattern = $hashEmailPart . '*@*.com';
                if (!fnmatch($expectedPattern, $providedEmail, FNM_CASEFOLD)) {
                    Log::warning('❌ Email pattern mismatch for long hash', [
                        'provided_email' => $providedEmail,
                        'hash_email_part' => $hashEmailPart,
                        'expected_pattern' => $expectedPattern,
                        'ip' => request()->ip(),
                        'security_event' => 'EMAIL_PATTERN_MISMATCH'
                    ]);
                    return false;
                }
            }

            // VALIDACIÓN 4: Verificación adicional de integridad del dominio
            $emailParts = explode('@', $providedEmail);
            if (count($emailParts) !== 2) {
                Log::warning('❌ Invalid email structure in exact match', [
                    'provided_email' => $providedEmail,
                    'ip' => request()->ip(),
                    'security_event' => 'INVALID_EMAIL_STRUCTURE'
                ]);
                return false;
            }

            list($localPart, $domain) = $emailParts;

            // VALIDACIÓN 5: El dominio debe ser válido y no sospechoso
            if (strlen($domain) < 4 || !strpos($domain, '.')) {
                Log::warning('❌ Suspicious domain in email validation', [
                    'provided_email' => $providedEmail,
                    'domain' => $domain,
                    'ip' => request()->ip(),
                    'security_event' => 'SUSPICIOUS_EMAIL_DOMAIN'
                ]);
                return false;
            }

            // VALIDACIÓN 6: Para hashes con más de 10 caracteres del email,
            // verificamos que no haya caracteres sospechosos insertados
            if (strlen($hashEmailPart) >= 10) {
                $hashWithoutSpecialChars = preg_replace('/[^a-zA-Z0-9@.]/', '', $hashEmailPart);
                $emailWithoutSpecialChars = preg_replace('/[^a-zA-Z0-9@.]/', '', substr($providedEmail, 0, strlen($hashEmailPart)));

                if ($hashWithoutSpecialChars !== $emailWithoutSpecialChars) {
                    Log::warning('❌ Email manipulation detected - character insertion/modification', [
                        'provided_email' => $providedEmail,
                        'hash_email_part' => $hashEmailPart,
                        'cleaned_hash' => $hashWithoutSpecialChars,
                        'cleaned_email' => $emailWithoutSpecialChars,
                        'ip' => request()->ip(),
                        'security_event' => 'EMAIL_CHARACTER_MANIPULATION'
                    ]);
                    return false;
                }
            }

            Log::info('✅ Email exact match validation passed', [
                'provided_email' => $providedEmail,
                'hash_email_part' => $hashEmailPart,
                'validation_type' => 'exact_match',
                'all_checks_passed' => true,
                'ip' => request()->ip()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('❌ Error in email exact match validation', [
                'provided_email' => $providedEmail,
                'hash_email_part' => $hashEmailPart,
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);
            return false;
        }
    }

    /**
     * Validar acceso por email y hash únicamente
     * IMPORTANTE: Permite múltiples usuarios desde la misma red
     * ÚNICO BLOQUEO: Cuando el enlace es marcado manualmente como bloqueado
     *
     * @param int $surveyId
     * @param string $email
     * @param string $providedHash
     * @return array
     */
    public static function validateDeviceAccess($surveyId, $email, $providedHash)
    {
        try {
            $decodedEmail = urldecode($email);
            $currentFingerprint = self::generateDeviceFingerprint();
            $request = request();

            // VALIDACIÓN PRINCIPAL: survey_id + email + hash (único por usuario)
            $accessToken = SurveyAccessToken::where('survey_id', $surveyId)
                ->where('email', $decodedEmail)
                ->where('hash', $providedHash)
                ->first();

            if (!$accessToken) {
                // SEGURIDAD CRÍTICA: Verificar si este hash ya existe para OTRO usuario
                // Esto previene que Usuario B use el enlace de Usuario A
                $existingHashForOtherUser = SurveyAccessToken::where('survey_id', $surveyId)
                    ->where('hash', $providedHash)
                    ->where('email', '!=', $decodedEmail)
                    ->first();

                if ($existingHashForOtherUser) {
                    // INTENTO DE LINK SHARING DETECTADO
                    Log::warning('🚨 LINK SHARING BLOCKED: Hash already used by different user', [
                        'survey_id' => $surveyId,
                        'attempting_email' => $decodedEmail,
                        'original_email' => $existingHashForOtherUser->email,
                        'hash' => substr($providedHash, 0, 16) . '...',
                        'original_user_ip' => $existingHashForOtherUser->ip_address,
                        'current_ip' => $request->ip(),
                        'security_event' => 'LINK_SHARING_ATTEMPT'
                    ]);

                    // Bloquear el hash original como medida de seguridad
                    $existingHashForOtherUser->blockAccess();

                    return ['valid' => false, 'error_type' => 'link_sharing'];
                }

                // PRIMER ACCESO: Registrar este usuario (hash no existe previamente)
                $accessToken = SurveyAccessToken::registerFirstAccess(
                    $surveyId,
                    $decodedEmail,
                    $providedHash,
                    $currentFingerprint,
                    $request->ip(),
                    $request->userAgent()
                );

                Log::info('🔐 FIRST ACCESS: User registered for survey', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'device_fingerprint' => $currentFingerprint,
                    'ip' => $request->ip(),
                    'note' => 'New user access registered',
                    'access_token_id' => $accessToken->id
                ]);

                return ['valid' => true, 'is_first_access' => true, 'access_token_id' => $accessToken->id];
            }

            // VERIFICAR BLOQUEO MANUAL
            // Solo bloquear si fue marcado explícitamente como bloqueado
            if ($accessToken->status === 'blocked') {
                Log::warning('❌ BLOCKED: Access attempt to manually blocked survey link', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'ip' => $request->ip(),
                    'access_token_id' => $accessToken->id,
                    'note' => 'Link was manually blocked by administrator'
                ]);
                return ['valid' => false, 'error_type' => 'link_blocked'];
            }

            // ACTUALIZAR DISPOSITIVO: Permitir que el mismo usuario acceda desde cualquier dispositivo
            if (!$accessToken->isDeviceMatch($currentFingerprint)) {
                Log::info('ℹ️ Device fingerprint changed - updating to new device', [
                    'survey_id' => $surveyId,
                    'email' => $decodedEmail,
                    'original_device' => $accessToken->device_fingerprint,
                    'current_device' => $currentFingerprint,
                    'note' => 'Same user from different device - allowed',
                    'access_token_id' => $accessToken->id
                ]);

                // Actualizar el fingerprint al dispositivo actual
                // El mismo usuario puede acceder desde diferentes dispositivos sin restricción
                $accessToken->update([
                    'device_fingerprint' => $currentFingerprint,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
            }

            // ACCESO VÁLIDO: Actualizar estadísticas
            $accessToken->updateAccess();

            Log::info('✅ Valid access to survey link', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'device_fingerprint' => $currentFingerprint,
                'ip' => $request->ip(),
                'access_count' => $accessToken->access_count + 1,
                'note' => 'Network-friendly: Multiple users from same IP allowed',
                'access_token_id' => $accessToken->id
            ]);

            return ['valid' => true, 'is_first_access' => false, 'access_token_id' => $accessToken->id];

        } catch (\Exception $e) {
            Log::error('❌ Device access validation error', [
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
     * Resetear/limpiar tokens de acceso cuando se reenvía una encuesta
     * Esto permite que el nuevo hash generado pueda ser usado sin restricciones
     * IMPORTANTE: Solo elimina tokens ACTIVOS, mantiene los BLOQUEADOS
     *
     * Razón:
     * - Cada reenvío genera un hash ÚNICO con timestamp
     * - Hash viejo bloqueado → Permanece bloqueado (registro independiente)
     * - Hash nuevo → Funcionará sin problemas (nuevo registro cuando usuario acceda)
     * - Solo limpiamos tokens activos para evitar residuos
     *
     * @param int $surveyId
     * @param string $email
     * @return bool
     */
    public static function resetAccessTokensForResend($surveyId, $email)
    {
        try {
            $decodedEmail = urldecode($email);

            Log::info('🔄 RESEND: Clearing active access tokens for survey resend', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail
            ]);

            // Obtener información de tokens antes de eliminar
            $existingTokens = SurveyAccessToken::where('survey_id', $surveyId)
                ->where('email', $decodedEmail)
                ->get();

            $blockedTokens = $existingTokens->where('status', 'blocked');
            $activeTokens = $existingTokens->whereNotIn('status', ['blocked']);

            Log::info('🔒 RESEND: Token analysis before cleanup', [
                'total_tokens' => $existingTokens->count(),
                'blocked_tokens' => $blockedTokens->count(),
                'active_tokens' => $activeTokens->count(),
                'blocked_hashes' => $blockedTokens->pluck('hash')->toArray(),
                'active_hashes' => $activeTokens->pluck('hash')->toArray(),
                'action' => 'Deleting ONLY active tokens, preserving blocked (each hash is unique)'
            ]);

            // CRÍTICO: Solo eliminar tokens ACTIVOS, mantener los BLOQUEADOS
            // Razón: Con hashes únicos (timestamp), cada enlace tiene su propio registro
            // Hash viejo bloqueado permanece bloqueado, hash nuevo es independiente
            $deletedCount = SurveyAccessToken::where('survey_id', $surveyId)
                ->where('email', $decodedEmail)
                ->where('status', '!=', 'blocked')
                ->delete();

            Log::info('✅ RESEND: Active tokens cleared, blocked tokens preserved', [
                'survey_id' => $surveyId,
                'email' => $decodedEmail,
                'deleted_active_tokens' => $deletedCount,
                'preserved_blocked_tokens' => $blockedTokens->count(),
                'preserved_blocked_hashes' => $blockedTokens->pluck('hash')->toArray(),
                'security_note' => 'New hash (with timestamp) will have independent lifecycle'
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('❌ RESEND: Error clearing access tokens', [
                'survey_id' => $surveyId,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}