<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue le lien d'activation du lien d'oubli.
 *
 * Les deux empruntent le meme mecanisme — jeton aleatoire, hache, a usage
 * unique — et la meme table, dont l'adresse est la cle primaire : un compte n'a
 * jamais qu'un lien vivant a la fois, et le dernier demande annule le
 * precedent. Seule leur duree de vie differe.
 *
 * Une heure convient a qui vient de cliquer « mot de passe oublie » : la
 * demande est deliberee, le lien est attendu, et une fenetre courte limite
 * d'autant la portee d'une boite compromise. C'est trop court pour un message
 * de bienvenue, qui arrive sans etre attendu — recu un vendredi soir, il serait
 * perime avant d'etre lu. D'ou 72 heures pour l'activation.
 *
 * Le type est stocke plutot que devine : sans lui, la seule facon de connaitre
 * la duree applicable serait de regarder si le compte a deja ete active, ce qui
 * changerait retroactivement la validite d'un lien deja parti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            // Les lignes anterieures sont toutes des demandes d'oubli : c'est
            // le seul usage qui existait, d'ou ce defaut.
            $table->string('type', 20)->default('reset')->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
