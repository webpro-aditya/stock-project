<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nse_contents', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('segment', 20);          
            $blueprint->string('parent_folder', 150);   
            $blueprint->string('name', 191); 
            $blueprint->string('type', 10); 
            $blueprint->string('path', 191)->unique(); 
            $blueprint->bigInteger('size')->nullable();
            $blueprint->timestamp('nse_modified_at')->nullable();
            $blueprint->timestamps();
            $blueprint->index(['segment', 'parent_folder']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nse_contents');
    }
};
