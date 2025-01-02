<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('website_name');
            $table->string('email');
            $table->boolean('show_document_preview')->default(true);
            $table->integer('max_document_size')->default(5120); // Default 5MB in KB
            $table->timestamps();
        });

        // Insert default settings
        DB::table('settings')->insert([
            'website_name' => 'E-Signature System',
            'email' => 'admin@example.com',
            'show_document_preview' => true,
            'max_document_size' => 5120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
