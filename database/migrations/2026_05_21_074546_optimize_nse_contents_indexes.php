<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE INDEX nse_contents_optim_idx 
            ON nse_contents (segment, parent_folder(100), type, nse_modified_at)
        ");

        DB::statement("
            CREATE INDEX nse_comman_contents_optim_idx 
            ON nse_comman_contents (segment, parent_folder(100), type, nse_modified_at)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX nse_contents_optim_idx ON nse_contents");
        DB::statement("DROP INDEX nse_comman_contents_optim_idx ON nse_comman_contents");
    }
};
