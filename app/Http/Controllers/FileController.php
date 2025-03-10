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
        // Obtener el path de la imagen desde el directorio privado
        $path = storage_path('app/private/images/' . $filename);

        // Si el archivo no existe, devolvemos un 404
        if (!file_exists($path)) {
            abort(404, 'Imagen no encontrada.');
        }

        // Obtener el contenido del archivo
        $file = file_get_contents($path);
        
        // Obtener el tipo MIME del archivo
        $type = mime_content_type($path);

        // Devolver la imagen con el tipo MIME correcto
        return response($file, 200)->header('Content-Type', $type);
    }
}