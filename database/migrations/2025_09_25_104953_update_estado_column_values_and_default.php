<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Actualizar todos los valores NULL a 'pendiente'
        DB::statement('UPDATE "Produc".notificationsurvays SET estado = \'pendiente\' WHERE estado IS NULL');

        // 2. Eliminar el constraint anterior
        DB::statement('ALTER TABLE "Produc".notificationsurvays DROP CONSTRAINT IF EXISTS estado_check');

        // 3. Agregar nuevo CHECK constraint con los valores correctos
        DB::statement('ALTER TABLE "Produc".notificationsurvays ADD CONSTRAINT estado_check CHECK (estado IN (\'pendiente\', \'en_proceso\', \'enviado\', \'fallo\', \'cancelado\'))');

        // 4. Establecer 'pendiente' como valor por defecto
        DB::statement('ALTER TABLE "Produc".notificationsurvays ALTER COLUMN estado SET DEFAULT \'pendiente\'');

        // 5. No permitir valores NULL
        DB::statement('ALTER TABLE "Produc".notificationsurvays ALTER COLUMN estado SET NOT NULL');
    }

    public function down(): void
    {
        // Revertir cambios
        DB::statement('ALTER TABLE "Produc".notificationsurvays DROP CONSTRAINT IF EXISTS estado_check');
        DB::statement('ALTER TABLE "Produc".notificationsurvays ALTER COLUMN estado DROP DEFAULT');
        DB::statement('ALTER TABLE "Produc".notificationsurvays ALTER COLUMN estado DROP NOT NULL');

        // Restaurar constraint anterior (si quieres)
        DB::statement('ALTER TABLE "Produc".notificationsurvays ADD CONSTRAINT estado_check CHECK (estado IN (\'pendiente\', \'enviado\', \'entregado\', \'fallido\', \'rebotado\'))');
    }
};
