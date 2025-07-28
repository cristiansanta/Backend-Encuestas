<?php

namespace App\Services;

use App\Models\QuestionModel;
use App\Models\TypeQuestionModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class QuestionIntegrityService
{
    /**
     * Registrar cambio de integridad en pregunta
     */
    public static function auditQuestionIntegrityChange($operation, $questionBefore, $questionAfter, $userId = null)
    {
        $auditEntry = [
            'timestamp' => now()->toISOString(),
            'operation' => $operation,
            'question_id' => $questionAfter->id ?? $questionBefore->id ?? null,
            'user_id' => $userId,
            'changes' => []
        ];

        // Detectar cambios en tipo de pregunta
        if ($questionBefore && $questionAfter) {
            $typeBefore = $questionBefore->type_questions_id;
            $typeAfter = $questionAfter->type_questions_id;
            
            if ($typeBefore !== $typeAfter) {
                $typeNameBefore = TypeQuestionModel::find($typeBefore)->title ?? 'Unknown';
                $typeNameAfter = TypeQuestionModel::find($typeAfter)->title ?? 'Unknown';
                
                $auditEntry['changes']['question_type'] = [
                    'from' => $typeBefore,
                    'to' => $typeAfter,
                    'from_name' => $typeNameBefore,
                    'to_name' => $typeNameAfter
                ];
            }
            
            // Detectar cambios en estado obligatorio
            $mandatoryBefore = self::normalizeMandatoryStatus($questionBefore);
            $mandatoryAfter = self::normalizeMandatoryStatus($questionAfter);
            
            if ($mandatoryBefore !== $mandatoryAfter) {
                $auditEntry['changes']['mandatory'] = [
                    'from' => $mandatoryBefore,
                    'to' => $mandatoryAfter
                ];
            }
        }

        // Solo registrar si hay cambios
        if (!empty($auditEntry['changes'])) {
            Log::info('QUESTION_INTEGRITY_AUDIT', $auditEntry);
            
            // Guardar en tabla de auditoría si existe
            try {
                DB::table('question_integrity_audit')->insert([
                    'timestamp' => now(),
                    'operation' => $operation,
                    'question_id' => $auditEntry['question_id'],
                    'user_id' => $userId,
                    'changes' => json_encode($auditEntry['changes']),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $e) {
                // Si la tabla no existe, solo registrar en log
                Log::warning('Could not save to audit table (table may not exist): ' . $e->getMessage());
            }
        }
    }

    /**
     * Normalizar el estado obligatorio de una pregunta
     */
    public static function normalizeMandatoryStatus($question)
    {
        if (!$question) {
            return false;
        }

        // Prioridad 1: Campo validate con valor específico
        if (isset($question->validate)) {
            $validateValue = strtolower(trim($question->validate));
            return in_array($validateValue, ['requerido', 'required', 'true', '1']);
        }

        // Prioridad 2: Campo mandatory boolean
        if (isset($question->mandatory)) {
            return (bool) $question->mandatory;
        }

        // Prioridad 3: Campo required boolean
        if (isset($question->required)) {
            return (bool) $question->required;
        }

        // Por defecto, no es obligatorio
        return false;
    }

    /**
     * Validar integridad de tipo de pregunta
     */
    public static function validateQuestionType($typeId)
    {
        if (!$typeId || !is_numeric($typeId)) {
            throw new \InvalidArgumentException("Tipo de pregunta inválido: {$typeId}");
        }

        $typeId = (int) $typeId;
        
        if ($typeId < 1 || $typeId > 6) {
            throw new \InvalidArgumentException("Tipo de pregunta fuera de rango: {$typeId}");
        }

        // Verificar que el tipo existe en la base de datos
        $typeExists = TypeQuestionModel::where('id', $typeId)->exists();
        if (!$typeExists) {
            throw new \InvalidArgumentException("Tipo de pregunta no existe en la base de datos: {$typeId}");
        }

        return $typeId;
    }

    /**
     * Validar integridad completa de una pregunta
     */
    public static function validateQuestionIntegrity($question)
    {
        $errors = [];
        $warnings = [];

        // Validar estructura básica
        if (!$question || !is_object($question)) {
            $errors[] = 'La pregunta debe ser un objeto válido';
            return ['is_valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // Validar título
        if (empty($question->title)) {
            $errors[] = 'La pregunta debe tener un título válido';
        }

        // Validar tipo
        try {
            self::validateQuestionType($question->type_questions_id);
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        // Validar estado obligatorio
        $mandatory = self::normalizeMandatoryStatus($question);
        if (!is_bool($mandatory)) {
            $warnings[] = 'Estado obligatorio no pudo ser determinado correctamente';
        }

        // Validar opciones para tipos que las requieren (más flexible para borradores)
        if (in_array($question->type_questions_id, [3, 4])) { // Opción única y múltiple
            $optionsCount = DB::table('questionsoptions')
                ->where('questions_id', $question->id)
                ->count();
            
            if ($optionsCount === 0) {
                $warnings[] = 'Las preguntas de opción única/múltiple deberían tener opciones definidas';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Normalizar una pregunta completa manteniendo integridad
     */
    public static function normalizeQuestion($question)
    {
        if (!$question || !is_object($question)) {
            throw new \InvalidArgumentException('Pregunta inválida para normalización');
        }

        // Crear copia para normalizar
        $normalized = clone $question;
        
        // Normalizar tipo
        $normalized->type_questions_id = self::validateQuestionType($question->type_questions_id);
        
        // Normalizar estado obligatorio
        $mandatory = self::normalizeMandatoryStatus($question);
        $normalized->validate = $mandatory ? 'Requerido' : 'Opcional';

        // Validar la pregunta normalizada
        $validation = self::validateQuestionIntegrity($normalized);
        if (!$validation['is_valid']) {
            throw new \InvalidArgumentException('Pregunta inválida después de normalización: ' . implode(', ', $validation['errors']));
        }

        return $normalized;
    }
}