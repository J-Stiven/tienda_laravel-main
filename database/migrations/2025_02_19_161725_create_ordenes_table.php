<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
       public function up(): void
       {
           Schema::create('ordenes', function (Blueprint $table) {
               $table->id();
               $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
               $table->decimal('total', 10, 2);
               $table->enum('estado', ['pendiente', 'procesando', 'enviado', 'entregado', 'cancelado'])->default('pendiente');
               $table->string('metodo_pago')->nullable();
               $table->timestamps();
           });
       }

       public function down(): void
       {
           Schema::dropIfExists('ordenes');
       }
   };