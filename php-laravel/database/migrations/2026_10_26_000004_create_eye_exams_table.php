<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ophthalmic record, one per consultation.
 *
 * One typed column per eye and measure (od_* / oe_*) instead of a JSON document per eye: the set of measures is
 * small and fixed, the database enforces the numeric types, values can be queried and indexed later (e.g. IOP
 * screening reports) without JSON functions that differ between MySQL and SQLite, and the free-text clinical
 * fields stay the only encrypted ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eye_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->unique()->constrained()->cascadeOnDelete();

            foreach (['od', 'oe'] as $eye) {
                // Snellen notation "20/N".
                $table->string("{$eye}_visual_acuity_sc", 7)->nullable();
                $table->string("{$eye}_visual_acuity_cc", 7)->nullable();
                // Diopters, steps of 0.25.
                $table->decimal("{$eye}_spherical", 5, 2)->nullable();
                $table->decimal("{$eye}_cylindrical", 5, 2)->nullable();
                $table->unsignedTinyInteger("{$eye}_axis")->nullable();
                $table->decimal("{$eye}_addition", 4, 2)->nullable();
                // Intraocular pressure, mmHg.
                $table->unsignedTinyInteger("{$eye}_iop")->nullable();
            }

            // Encrypted at rest (`encrypted` cast).
            $table->text('diagnosis')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eye_exams');
    }
};
