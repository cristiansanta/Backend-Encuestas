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
                abort(400, 'Nombre de archivo no válido.');
            }
            
            // Obtener el path de la imagen desde el directorio privado
            $path = storage_path('app/private/images/' . $filename);

            // Si el archivo no existe, devolvemos un 404
            if (!file_exists($path)) {
                \Log::warning('Imagen no encontrada: ' . $filename);
                abort(404, 'Imagen no encontrada.');
            }

            // Obtener el contenido del archivo
            $file = file_get_contents($path);
            
            if ($file === false) {
                \Log::error('Error al leer archivo: ' . $filename);
                abort(500, 'Error al leer el archivo.');
            }
            
            // Obtener el tipo MIME del archivo
            $type = mime_content_type($path);
            
            // Validar que es un tipo de imagen permitido
            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
            if (!in_array($type, $allowedTypes)) {
                \Log::warning('Tipo de archivo no permitido: ' . $type . ' para archivo: ' . $filename);
                abort(403, 'Tipo de archivo no permitido.');
            }

            // Devolver la imagen con headers apropiados
            return response($file, 200)
                ->header('Content-Type', $type)
                ->header('Cache-Control', 'public, max-age=86400') // Cache por 24 horas
                ->header('Content-Length', strlen($file))
                ->header('Accept-Ranges', 'bytes');
                
        } catch (\Exception $e) {
            \Log::error('Error sirviendo imagen: ' . $e->getMessage(), [
                'filename' => $filename,
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'Error interno del servidor.');
        }
    }
}