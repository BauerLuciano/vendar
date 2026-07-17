<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_categoria_id_foreign');
        DB::statement('ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_marca_id_foreign');
        DB::statement('ALTER TABLE productos ALTER COLUMN categoria_id DROP NOT NULL');
        DB::statement('ALTER TABLE productos ALTER COLUMN marca_id DROP NOT NULL');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_marca_id_foreign FOREIGN KEY (marca_id) REFERENCES marcas(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE productos SET categoria_id = (SELECT MIN(id) FROM categorias) WHERE categoria_id IS NULL');
        DB::statement('UPDATE productos SET marca_id = (SELECT MIN(id) FROM marcas) WHERE marca_id IS NULL');
        DB::statement('ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_categoria_id_foreign');
        DB::statement('ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_marca_id_foreign');
        DB::statement('ALTER TABLE productos ALTER COLUMN categoria_id SET NOT NULL');
        DB::statement('ALTER TABLE productos ALTER COLUMN marca_id SET NOT NULL');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_marca_id_foreign FOREIGN KEY (marca_id) REFERENCES marcas(id) ON DELETE RESTRICT');
    }
};
