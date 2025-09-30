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
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Setting key identifier');
            $table->text('value')->nullable()->comment('Setting value (JSON for complex values)');
            $table->string('type', 20)->default('string')->comment('Data type: string, integer, boolean, json, array');
            $table->string('group', 50)->default('general')->comment('Settings group for organization');
            $table->string('label')->comment('Human readable label');
            $table->text('description')->nullable()->comment('Setting description');
            $table->json('options')->nullable()->comment('Available options for select/radio inputs');
            $table->boolean('is_public')->default(false)->comment('Whether setting can be accessed publicly');
            $table->boolean('is_editable')->default(true)->comment('Whether setting can be modified via admin panel');
            $table->string('input_type', 30)->default('text')->comment('Admin UI input type: text, textarea, select, checkbox, radio, etc.');
            $table->integer('sort_order')->default(0)->comment('Display order in admin panel');
            $table->timestamps();

            $table->index(['group', 'sort_order']);
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
