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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_1')->nullable()->index();
            $table->string('phone_2')->nullable();
            $table->string('email_1')->nullable()->index();
            $table->string('email_2')->nullable();
            $table->json('social_media_links')->nullable();
            $table->boolean('is_realtor')->default(false)->index();
            $table->json('latest_inspection')->nullable();
            $table->timestamps();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
