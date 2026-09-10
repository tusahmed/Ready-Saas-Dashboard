<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('plan', 30)->default('starter')->index();
            $table->string('status', 20)->default('active')->index();
            $table->date('trial_ends_at')->nullable();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->json('permissions');
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_super_admin')->default(false);
            $table->string('phone', 40)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->text('bio')->nullable();
            $table->string('locale', 5)->default('ar');
            $table->string('theme', 10)->default('light');
            $table->string('status', 20)->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->index(['tenant_id', 'status']);
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('notifications');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['is_super_admin', 'phone', 'job_title', 'bio', 'locale', 'theme', 'status', 'last_login_at']);
        });
        Schema::dropIfExists('roles');
        Schema::dropIfExists('tenants');
    }
};
