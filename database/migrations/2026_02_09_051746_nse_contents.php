<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nse_contents', function (Blueprint $table) {

            $table->id();

            $table->string('segment', 20);
            $table->string('parent_folder', 512)->default('root');
            $table->string('name', 255);
            $table->string('type', 10)->nullable();
            $table->string('path', 2048);

            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('nse_modified_at')->nullable();
            $table->timestamps();

            $table->index('nse_modified_at');
        });

        DB::statement("
    CREATE INDEX nse_segment_parent_idx
    ON nse_contents (segment, parent_folder(100))
");

        DB::statement("
    CREATE UNIQUE INDEX nse_unique_filesystem
    ON nse_contents (
        segment,
        parent_folder(100),
        name(100)
    )
");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nse_contents');
    }
};
