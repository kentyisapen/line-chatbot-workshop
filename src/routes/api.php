<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LineController;

Route::post('/webhook', [LineController::class, 'webhook']);
