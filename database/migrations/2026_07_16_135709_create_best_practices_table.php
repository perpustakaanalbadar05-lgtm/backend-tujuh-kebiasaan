<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('best_practices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('documentation')->nullable(); // path file / image
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('best_practices');
    }
};
