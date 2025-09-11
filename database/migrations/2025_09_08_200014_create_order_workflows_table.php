<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('triggered_by')->default('system'); // system, user
            $table->foreignId('triggered_by_id')->nullable()->constrained('users');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->datetime('processed_at');
            $table->timestamps();

            $table->index(['order_id', 'processed_at']);
            $table->index(['to_status', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_workflows');
    }
};