<?php

use think\facade\Route;
use Laket\Admin\Database\Controller\Database;
use Laket\Admin\Facade\Flash;

/**
 * 闪存插件路由
 */
Flash::routes(function() {
    Route::get('database/index', Database::class . '@index')->name('admin.database.index');
    Route::post('database/index', Database::class . '@index')->name('admin.database.index-post');
    Route::get('database/export', Database::class . '@export')->name('admin.database.export');
    Route::post('database/export', Database::class . '@export')->name('admin.database.export-post');
    Route::get('database/restore', Database::class . '@restore')->name('admin.database.restore');
    Route::post('database/restore', Database::class . '@restore')->name('admin.database.restore-post');
    Route::get('database/download', Database::class . '@download')->name('admin.database.download');
    Route::get('database/import', Database::class . '@import')->name('admin.database.import');
    Route::post('database/del', Database::class . '@del')->name('admin.database.del-post');
    Route::post('database/optimize', Database::class . '@optimize')->name('admin.database.optimize-post');
    Route::post('database/repair', Database::class . '@repair')->name('admin.database.repair-post');
});