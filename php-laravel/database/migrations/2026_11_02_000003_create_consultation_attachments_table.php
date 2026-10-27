<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_attachments', function (Blueprint $table) {
            $table->id();
            // The files on disk are removed by model events (App\Models\Consultation / Patient), since a database
            // cascade fires none; the cascade only keeps the rows consistent.
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            // Path on the private disk: random name, encrypted contents.
            $table->string('path')->unique();
            // Encrypted at rest: file names and descriptions may contain the patient's name or clinical details.
            $table->text('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['consultation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_attachments');
    }
};
