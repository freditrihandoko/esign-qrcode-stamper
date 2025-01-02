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
        Schema::table('document_types', function (Blueprint $table) {
            $table->unsignedBigInteger('default_first_approver_id')->nullable()->after('requires_approval');
            $table->unsignedBigInteger('default_final_approver_id')->nullable()->after('default_first_approver_id');

            // Tambahkan foreign key constraints
            $table->foreign('default_first_approver_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('default_final_approver_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropForeign(['default_first_approver_id']);
            $table->dropForeign(['default_final_approver_id']);
            $table->dropColumn(['default_first_approver_id', 'default_final_approver_id']);
        });
    }
};
