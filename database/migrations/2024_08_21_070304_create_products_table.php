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
        // Schema::create('products', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('N17_KODAS_PS')->unique();
        //     $table->string('N17_PAV');
        //     $table->string('N17_PAVU')->nullable();
        //     $table->string('N17_KODAS_KS')->nullable();
        //     $table->string('N17_KOD_T')->nullable();
        //     $table->string('N17_KODAS_LS_1')->nullable();
        //     $table->string('N17_KODAS_LS_2')->nullable();
        //     $table->string('N17_KODAS_LS_3')->nullable();
        //     $table->string('N17_KODAS_LS_4')->nullable();
        //     $table->timestamps();
        // });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique()->index();
            $table->string('shopify_product_id')->nullable()->index();
            $table->string('shopify_collection_id')->nullable()->index();
            $table->text('title');
            $table->text('body_html');
            $table->string('vendor');
            $table->string('product_type');
            $table->json('variants');
            $table->text('image')->nullable();
            $table->text('collection_title')->nullable();
            $table->text('collection_desc')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
