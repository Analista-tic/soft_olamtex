<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ParameterController;

Route::get('/parameters/areas', [ParameterController::class, 'areas']);
