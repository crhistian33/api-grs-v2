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
        Schema::create('inassist_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inassist_id')->constrained()->onDelete('cascade');
            $table->date('inassist_date');
            $table->text('comment')->nullable();
            $table->foreignId('replacement_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inassist_details');
    }
};
