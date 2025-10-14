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
        Schema::create('contact_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_type');
            $table->string('call_outcome')->nullable();
            $table->string('call_direction')->nullable();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->softDeletes();

            $table->index(['customer_id', 'occurred_at']);
            $table->index(['contact_type', 'occurred_at']);
        });

        Schema::table('customers', function(Blueprint $table) {
            $table->dateTime('last_contact_at')->nullable()->after('updated_at');
            $table->string('last_contact_type')->nullable()->after('last_contact_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['last_contact_at', 'last_contact_type']);
        });
        Schema::dropIfExists('contact_records');
    }
};
