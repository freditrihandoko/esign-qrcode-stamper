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
        // Tambahkan kolom peran ke tabel users
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'superadmin',
                'admin',
                'pimpinan',
                'approver',
                'user'
            ])->default('user')->after('email');

            $table->string('nip')->nullable()->unique()->after('role');
            $table->string('position')->nullable()->after('nip');
            $table->unsignedBigInteger('department_id')->nullable()->after('position');
            $table->string('signature_path')->nullable()->after('department_id');
            $table->softDeletes();
        });

        // Tabel Departemen
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Foreign key untuk department
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments');
        });

        // Tabel Jenis Dokumen
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();
        });

        // Tabel Dokumen
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('document_number')->unique()->nullable();
            $table->date('document_date')->nullable();
            $table->text('description')->nullable();

            $table->unsignedBigInteger('document_type_id');
            $table->unsignedBigInteger('created_by');

            $table->string('file_path')->nullable();
            $table->enum('status', [
                'draft',
                'waiting_approval',
                'waiting_first_approval',
                'waiting_final_approval',
                'approved',
                'rejected',
                'signed',
                'archived'
            ])->default('draft');

            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->unsignedBigInteger('first_approver_id')->nullable();
            $table->unsignedBigInteger('final_approver_id')->nullable();
            $table->text('approval_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->string('signed_file_path')->nullable()->after('file_path');
            $table->timestamp('pdf_generated_at')->nullable();

            // Foreign key constraints
            $table->foreign('document_type_id')->references('id')->on('document_types');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('first_approver_id')->references('id')->on('users');
            $table->foreign('final_approver_id')->references('id')->on('users');
        });

        // Tabel Alur Persetujuan Dokumen
        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('approver_id');
            $table->enum('approval_status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('document_id')->references('id')->on('documents');
            $table->foreign('approver_id')->references('id')->on('users');
        });

        // Tabel Generate QR Code
        Schema::create('qr_code_generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('qr_generation_code')->unique();
            $table->json('generation_details')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Tabel QR Codes
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('qr_generation_id')->nullable();
            $table->string('qr_code_path');
            $table->string('unique_hash')->unique();
            $table->string('verification_url')->nullable();
            $table->json('additional_metadata')->nullable();
            $table->dateTime('generated_at');
            $table->boolean('is_verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('document_id')->references('id')->on('documents');
            $table->foreign('qr_generation_id')->references('id')->on('qr_code_generations');
        });

        // Tabel Log Verifikasi
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qr_code_id');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('ip_address');
            $table->string('device_info')->nullable();
            $table->string('browser_info')->nullable();
            $table->boolean('is_successful')->default(true);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('qr_code_id')->references('id')->on('qr_codes');
            $table->foreign('verified_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Urutan drop yang aman
        Schema::dropIfExists('verification_logs');
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('qr_code_generations');
        Schema::dropIfExists('document_approvals');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('departments');

        // Rollback perubahan di tabel users
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn([
                'role',
                'nip',
                'position',
                'department_id',
                'signature_path'
            ]);
            $table->dropSoftDeletes();
        });
    }
};
