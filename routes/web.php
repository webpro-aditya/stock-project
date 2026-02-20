<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\BSEController;
use App\Http\Controllers\Admin\NSEController;
use App\Http\Controllers\Admin\NSECommanController;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/pass', function () {
//     echo Illuminate\Support\Facades\Hash::make('Acme@54321');
// });

// Authenticated routes
Route::group(['middleware' => 'auth'], function () {

Route::get('/nse/sync/progress/{segment}', function ($segment) {
    $progress = Cache::get("nse_sync_progress_{$segment}");
            return response()->json($progress ?? [
                'current' => 0,
                'total' => 0,
                'percentage' => 0,
                'status' => 'idle'
            ]);
        })->name('nse.sync.progress');
    // Admin prefix routes
    Route::group(['middleware' => 'auth', 'prefix' => 'admin'], function () {
        // Access only for admin
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::post('/logout', [AdminController::class, 'logout'])->name('admin.logout');
        Route::get('/loadChangePass', [AdminController::class, 'changePass'])->name('admin.loadChangePass');
        Route::post('/changePassword', [AdminController::class, 'updatePass'])->name('admin.changePassword');

        // NSE
        Route::get('/files/nse', [NSEController::class, 'index'])->name('nse.index');
        Route::get('/files/nse/{segment}', [NSEController::class, 'getSegment'])->name('nse.segment');
        Route::get('/files/nse/common/{segment}/{folder}/today', [NSECommanController::class, 'getTodaySegmentFolder'])->name('nse.common.segment.folder.today');
        Route::get('/files/nse/{segment}/{folder}/today', [NSEController::class, 'getTodaySegmentFolder'])->name('nse.segment.folder.today');
        Route::get('/files/nse/{segment}/{folder}/archives', [NSEController::class, 'getArchiveSegmentFolder'])->name('nse.segment.archives');
        // Route::get('/files/nse/{id}/download', [NSEController::class, 'downloadFile'])->name('nse.segment.downloadFile');
        Route::get('/nse/download/prepare/{id}', [NseController::class, 'prepareDownload'])->name('nse.file.prepare');
        Route::get('/nse/common/download/prepare/{id}', [NSECommanController::class, 'prepareDownload'])->name('nse.common.file.prepare');
        Route::get('/nse/download/serve/{id}', [NseController::class, 'serveFile'])->name('nse.file.serve');
        Route::get('/nse/common/download/serve/{id}', [NSECommanController::class, 'serveFile'])->name('nse.common.file.serve');
        Route::post('/nse/sync/clear/{segment}/{folder}', [NseController::class, 'syncMemberSegment'])->name('nse.sync.clear');
        Route::post('/nse/member/download/bulk/prepare', [NseController::class, 'prepareBulkDownload'])->name('nse.member.download.bulk.prepare');
        Route::post('/nse/common/sync/clear/{segment}/{folder}', [NSECommanController::class, 'syncMemberSegment'])->name('nse.common.sync.clear');
        Route::post('/nse/archive/sync/clear/{segment}/{folder}', [NSECommanController::class, 'clearArchiveFolderCache'])->name('nse.archive.sync.clear');
        // Route to handle the AJAX preparation (Syncs files & Creates Zip)
        Route::post('/nse/common/download/bulk/prepare', [NSECommanController::class, 'prepareBulkDownload'])->name('nse.common.download.bulk.prepare');

        // Route to serve the generated Zip
        Route::get('/nse/download/bulk/serve/{filename}', [NSEController::class, 'serveBulkZip'])->name('nse.bulk.serve');
        Route::get('/nse/common/download/bulk/serve/{filename}', [NSECommanController::class, 'serveBulkZip'])->name('nse.common.bulk.serve');

        // BSE
        // Route::get('/files/bse', [BSEController::class, 'index'])->name('bse.index');
        // Route::get('/files/bse/{segment}', [BSEController::class, 'index'])->name('bse.segment');
        // Route::get('/files/bse/{segment}/{folder}/today', [BSEController::class, 'getTodaySegmentFolder'])->name('bse.segment.folder.today');

    });
});

Route::group(['middleware' => 'guest'], function () {
    Route::get('/', [HomeController::class, 'home'])->name('home');
    Route::get('/home', [HomeController::class, 'home'])->name('home.home');
    Route::get('/admin', [HomeController::class, 'login'])->name('login');
    Route::get('/login', [HomeController::class, 'login'])->name('home.login');
    Route::post('/login', [AdminController::class, 'login'])->name('admin.login');
    Route::get('/register', [HomeController::class, 'register'])->name('register');
    Route::post('/register', [AdminController::class, 'createUser'])->name('createUser');



    Route::get('/thanks', [HomeController::class, 'thanks'])->name('page.thanks');

    //Page Not Found
    // Route::fallback([PageController::class, 'pageNotFound'])->name('pageNotFound');
});
