<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::view('/admin', 'admin.panel')->name('admin.panel');
Route::view('/student', 'student.portal')->name('student.portal');
