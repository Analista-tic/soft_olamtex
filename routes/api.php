<?php

use App\Http\Controllers\MasterData\ParameterController;
use Illuminate\Support\Facades\Route;

Route::get('/parameters/areas', [ParameterController::class, 'areas']);
