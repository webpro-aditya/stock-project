<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

function cleanDuplicates($table) {
    echo "Cleaning $table...\n";
    $dupes = DB::select("
        SELECT segment, parent_folder, name, MIN(id) as keep_id
        FROM $table
        GROUP BY segment, parent_folder, name
        HAVING COUNT(*) > 1
    ");

    $count = 0;
    foreach ($dupes as $dupe) {
        $deleted = DB::table($table)
            ->where('segment', $dupe->segment)
            ->where('parent_folder', $dupe->parent_folder)
            ->where('name', $dupe->name)
            ->where('id', '>', $dupe->keep_id)
            ->delete();
        $count += $deleted;
    }
    echo "Deleted $count duplicates from $table.\n";
}

cleanDuplicates('nse_contents');
cleanDuplicates('nse_comman_contents');
