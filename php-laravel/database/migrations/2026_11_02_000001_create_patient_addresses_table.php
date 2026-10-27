<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_addresses', function (Blueprint $table) {
            $table->id();
            // 1:1 with the patient. Soft-deleting the patient keeps the address; a hard delete removes it.
            $table->foreignId('patient_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('cep', 8);
            // Encrypted at rest (`encrypted` cast): ciphertext is much longer than the plain value, hence TEXT.
            $table->text('street');
            $table->text('number');
            $table->text('complement')->nullable();
            $table->text('neighborhood');
            // Plain: city and state are not identifying on their own and may be filtered/grouped later.
            $table->string('city', 100);
            $table->char('state', 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_addresses');
    }
};
