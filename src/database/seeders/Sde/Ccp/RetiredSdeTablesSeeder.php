<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to present Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */


namespace Seat\Eveapi\Database\Seeders\Sde\Ccp;

use Illuminate\Database\Seeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class RetiredSdeTablesSeeder.
 *
 * CCP stopped publishing these tables. They are still part of the schema every
 * reader of the SDE expects, so they are created and left empty. invNames and
 * invUniqueNames are filled from the map dump, which is where their content
 * came from.
 *
 * @package Seat\Eveapi\Database\Seeders\Sde\Ccp
 */
class RetiredSdeTablesSeeder extends Seeder
{
    /**
     * Create the tables CCP no longer publishes.
     *
     * @return void
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::dropIfExists('invFlags');
            Schema::create('invFlags', function (Blueprint $table) {
                $table->integer('flagID');
                $table->string('flagName', 200)->nullable();
                $table->string('flagText', 100)->nullable();
                $table->integer('orderID')->nullable();
            });

            Schema::dropIfExists('invItems');
            Schema::create('invItems', function (Blueprint $table) {
                $table->integer('itemID');
                $table->integer('typeID')->nullable();
                $table->integer('ownerID')->nullable();
                $table->integer('locationID')->nullable();
                $table->integer('flagID')->nullable();
                $table->integer('quantity')->nullable();
            });

            Schema::dropIfExists('invPositions');
            Schema::create('invPositions', function (Blueprint $table) {
                $table->integer('itemID');
                $table->float('x');
                $table->float('y');
                $table->float('z');
                $table->float('yaw')->nullable();
                $table->float('pitch')->nullable();
                $table->float('roll')->nullable();
            });

            Schema::dropIfExists('invTypeReactions');
            Schema::create('invTypeReactions', function (Blueprint $table) {
                $table->integer('reactionTypeID');
                $table->boolean('input');
                $table->integer('typeID');
                $table->integer('quantity')->nullable();
            });

            Schema::dropIfExists('invControlTowerResourcePurposes');
            Schema::create('invControlTowerResourcePurposes', function (Blueprint $table) {
                $table->integer('purpose');
                $table->string('purposeText', 100)->nullable();
            });

            $this->seedNames();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Fill the name tables from the map dump.
     *
     * @return void
     */
    protected function seedNames(): void
    {
        Schema::dropIfExists('invNames');
        Schema::create('invNames', function (Blueprint $table) {
            $table->integer('itemID');
            $table->string('itemName', 200);
        });

        Schema::dropIfExists('invUniqueNames');
        Schema::create('invUniqueNames', function (Blueprint $table) {
            $table->integer('itemID');
            $table->string('itemName', 200);
            $table->integer('groupID')->nullable();
        });

        if (! Schema::hasTable('mapDenormalize'))
            return;

        DB::table('invNames')->insertUsing(
            ['itemID', 'itemName'],
            DB::table('mapDenormalize')->select(['itemID', 'itemName'])
        );

        DB::table('invUniqueNames')->insertUsing(
            ['itemID', 'itemName', 'groupID'],
            DB::table('mapDenormalize')->select(['itemID', 'itemName', 'groupID'])
        );
    }
}
