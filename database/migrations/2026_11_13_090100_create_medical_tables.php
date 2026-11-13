<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every table carries its calendar so a household's records are scoped with a
     * single where clause. A dosar is a person's folder and does not need a login:
     * only the household's membership decides who may open it.
     */
    public function up(): void
    {
        Schema::create('medical_dosars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('date_of_birth')->nullable();
            $table->text('cnp')->nullable();
            $table->string('color', 7)->default('#2563eb');
            $table->text('notes')->nullable();
            $table->string('search_text')->default('');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['calendar_id', 'archived_at']);
        });

        Schema::create('medical_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_dosar_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->default('other');
            $table->json('tags')->nullable();
            $table->date('document_date')->nullable();

            $table->string('original_name');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('thumbnail_path')->nullable();

            $table->string('status')->default('pending');
            $table->text('ocr_error')->nullable();
            $table->string('ocr_model')->nullable();
            $table->unsignedInteger('ocr_pages')->nullable();
            $table->timestamp('ocr_edited_at')->nullable();
            $table->string('metadata_state')->default('none');
            $table->json('metadata')->nullable();

            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('medical_documents')->nullOnDelete();
            $table->string('search_text')->default('');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['calendar_id', 'sha256']);
            $table->index(['calendar_id', 'deleted_at']);
        });

        Schema::create('medical_document_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->longText('markdown');
            $table->json('blocks')->nullable();
            $table->longText('search_text')->nullable();
            $table->timestamps();

            $table->unique(['medical_document_id', 'page_number']);
        });

        Schema::create('medical_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['calendar_id', 'created_at']);
            $table->index('medical_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_activity');
        Schema::dropIfExists('medical_document_pages');
        Schema::dropIfExists('medical_documents');
        Schema::dropIfExists('medical_dosars');
    }
};
