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

use Illuminate\Database\Schema\Blueprint;
use Seat\Eveapi\Mapping\Sde\AbstractSdeMapping;
use Seat\Eveapi\Mapping\Sde\Ccp\InvTypeMapping;
use Seat\Eveapi\Database\Seeders\Sde\AbstractSdeSeeder;
use Seat\Eveapi\Models\Sde\InvType;

class InvTypesSeeder extends AbstractSdeSeeder
{

    protected const FILENAME = "types.jsonl";

    /**
     * Define seeder related SDE table structure.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $table
     * @return void
     */
    protected function getSdeTableDefinition(Blueprint $table): void
    {
        $table->integer('typeID')->primary();
        $table->integer('groupID')->nullable();
        $table->string('typeName', 150)->nullable();
        $table->text('description')->nullable();
        $table->double('mass')->nullable();
        $table->double('volume')->nullable();
        $table->double('capacity')->nullable();
        $table->integer('portionSize')->nullable();
        $table->integer('raceID')->nullable();
        $table->decimal('basePrice', 19, 4)->nullable();
        $table->boolean('published')->nullable();
        $table->integer('marketGroupID')->nullable();
        $table->integer('iconID')->nullable();
        $table->integer('soundID')->nullable();
        $table->integer('graphicID')->nullable();
        $table->integer('factionID')->nullable();
        $table->integer('metaLevel')->nullable();
        $table->integer('techLevel')->nullable();
        $table->integer('shipTreeGroupID')->nullable();
        $table->double('packagedVolume')->nullable();
        $table->boolean('isDynamicType')->nullable();
        $table->boolean('isRepackable')->nullable();

        $table->index('groupID', 'ix_invTypes_groupID');
    }

    /**
     * The mapping instance which must be used to seed table with SDE dump.
     *
     * @return \Seat\Eveapi\Mapping\Sde\AbstractSdeMapping
     */
    protected function getMappingClass(): AbstractSdeMapping
    {
        return new InvTypeMapping();
    }

    public function insert($arr) 
    {
        return InvType::insert($arr);
    }
}
