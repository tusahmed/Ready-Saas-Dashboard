<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 80)->unique();
            $table->foreignId('tenant_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name', 150);
            $table->string('business_email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('country', 120)->nullable();
            $table->string('tax_number', 120)->nullable();
            $table->string('registration_number', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_settings');
    }
};
