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
        Schema::create('replacements', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('worker_id')->constrained()->onDelete('cascade');
            $table->bigInteger('state_workers_id')->constrained()->onDelete('restrict');
            $table->boolean('is_pay')->default(false);
            $table->decimal('pay_mount', 9, 2)->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replacements');
    }
};
