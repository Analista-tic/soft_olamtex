<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MasterData\ParameterController;

Route::get('/parameters/areas', [ParameterController::class, 'areas']);
