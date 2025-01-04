<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaStock extends Migration
{
    public function up()
    {
        Schema::create('stock', function (Blueprint $table) {
            $table->id('idStock'); // Clave primaria de la tabla stock
            $table->unsignedBigInteger('idModelo'); // Relación con la tabla modelos
            $table->integer('cantidad'); // La cantidad en stock
            $table->foreign('idModelo')->references('idModelo')->on('modelos'); // Relación con idModelo
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock');
    }
}