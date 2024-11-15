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
        Schema::create('rich_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rich_menu_id')->nullable(); // LINEから返されるリッチメニューID
            $table->timestamps();
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rich_menus');
    }
};
