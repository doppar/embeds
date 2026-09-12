<?php

use Phaseolies\Support\Facades\Schema;
use Phaseolies\Database\Migration\Migration;
use Phaseolies\Database\Migration\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations
     *
     * This table backs the "brute_force" driver only. It is skipped when a
     * different driver is configured, so choosing "pgvector" or "redis"
     * does not leave an unused table behind.
     *
     * @return void
     */
    public function up(): void
    {
        if (config('embeds.driver', 'brute_force') !== 'brute_force') {
            return;
        }

        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('embeddable_type')->index();
            $table->string('embeddable_id')->index();
            $table->string('attribute')->index();
            $table->longText('vector');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down(): void
    {
        if (config('embeds.driver', 'brute_force') !== 'brute_force') {
            return;
        }

        Schema::dropIfExists('embeddings');
    }
};
