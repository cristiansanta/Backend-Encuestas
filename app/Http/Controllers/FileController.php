<?php

namespace App\Http\Controllers;



use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

class FileController extends Controller
{
    // public function getImage($filename): Response
    // {
    //     // Construir la ruta completa
    //     $path = 'images/' . $filename;

    //     // Verificar si el archivo existe
    //     if (!Storage::disk('private')->exists($path)) {
    //         return response()->json(['error' => 'Image not found'], 404);
    //     }

    //     // Obtener el archivo y su tipo MIME
    //     $file = Storage::disk('private')->get($path);
    //     $mimeType = Storage::disk('private')->mimeType($path);

    //     // Retornar la respuesta con la imagen
    //     return response($file, 200)
    //         ->header('Content-Type', $mimeType)
    //         ->header('Cache-Control', 'public, max-age=86400');
    // }

    public function show($filename)
    {
        try {
            // Validar el nombre del archivo para seguridad
            if (!preg_match('/^[a-zA-Z0-9_-]+\.(png|jpg|jpeg|svg)$/i', $filename)) {
                \Log::error('Nombre de archivo no válido: ' . $filename);
                abort(400, 'Nombre de archivo no válido.');
            }
            
            // Obtener el path de la imagen desde el directorio privado
            $path = storage_path('app/private/images/' . $filename);
            \Log::info('Attempting to serve image: ' . $filename . ' from path: ' . $path);

            // Si el archivo no existe, devolvemos un 404
            if (!file_exists($path)) {
                \Log::warning('Imagen no encontrada: ' . $filename . ' en path: ' . $path);
                abort(404, 'Imagen no encontrada.');
            }

            // Verificar permisos del archivo
            $permissions = substr(sprintf('%o', fileperms($path)), -4);
            \Log::info('File permissions for ' . $filename . ': ' . $permissions);
            
            // Verificar si el archivo es legible
            if (!is_readable($path)) {
                \Log::error('File not readable: ' . $filename . ' permissions: ' . $permissions);
                
                // Intentar cambiar permisos si es posible
                try {
                    chmod($path, 0644);
                    \Log::info('Permissions corrected for: ' . $filename);
                } catch (\Exception $permError) {
                    \Log::error('Cannot fix permissions for: ' . $filename . ' - ' . $permError->getMessage());
                }
            }

            // Usar Storage::disk para leer el archivo si file_get_contents falla
            try {
                $file = file_get_contents($path);
                if ($file === false) {
                    \Log::warning('file_get_contents failed, trying Storage::disk method for: ' . $filename);
                    $file = Storage::disk('private')->get('images/' . $filename);
                }
            } catch (\Exception $readError) {
                \Log::error('Both file_get_contents and Storage::disk failed for: ' . $filename . ' - ' . $readError->getMessage());
                abort(500, 'Error al leer el archivo.');
            }
            
            if ($file === false || empty($file)) {
                \Log::error('File content is empty or false for: ' . $filename);
                abort(500, 'Error al leer el archivo.');
            }
            
            // Obtener el tipo MIME del archivo
            $type = mime_content_type($path);
            if (!$type) {
                // Fallback basado en extensión
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'svg' => 'image/svg+xml'
                ];
                $type = $mimeTypes[$extension] ?? 'application/octet-stream';
                \Log::info('Using fallback MIME type for ' . $filename . ': ' . $type);
            }
            
            // Validar que es un tipo de imagen permitido
            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
            if (!in_array($type, $allowedTypes)) {
                \Log::warning('Tipo de archivo no permitido: ' . $type . ' para archivo: ' . $filename);
                abort(403, 'Tipo de archivo no permitido.');
            }

            \Log::info('Successfully serving image: ' . $filename . ' size: ' . strlen($file) . ' bytes, type: ' . $type);

            // Devolver la imagen con headers apropiados
            return response($file, 200)
                ->header('Content-Type', $type)
                ->header('Cache-Control', 'public, max-age=86400') // Cache por 24 horas
                ->header('Content-Length', strlen($file))
                ->header('Accept-Ranges', 'bytes');
                
        } catch (\Exception $e) {
            \Log::error('Error sirviendo imagen: ' . $e->getMessage(), [
                'filename' => $filename,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'Error interno del servidor.');
        }
    }
}