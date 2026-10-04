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
use Illuminate\Support\Facades\DB;
use Seat\Eveapi\Database\Seeders\Sde\AbstractSdeSeeder;
use Seat\Eveapi\Mapping\Sde\AbstractSdeMapping;
use Seat\Eveapi\Mapping\Sde\Ccp\InvMetaTypeMapping;

/**
 * Class InvMetaTypesSeeder.
 *
 * invMetaTypes is derived from types.jsonl, which carries metaGroupID on the
 * type itself. parentTypeID was retired and is published nowhere, so it stays
 * null like it is in the dump this table used to come from.
 *
 * @package Seat\Eveapi\Database\Seeders\Sde\Ccp
 */
class InvMetaTypesSeeder extends AbstractSdeSeeder
{
    protected const FILENAME = 'types.jsonl';

    /**
     * Define seeder related SDE table structure.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $table
     * @return void
     */
    protected function getSdeTableDefinition(Blueprint $table): void
    {
        $table->integer('typeID')->primary();
        $table->integer('parentTypeID')->nullable();
        $table->integer('metaGroupID')->nullable();

        $table->index('metaGroupID', 'ix_invMetaTypes_metaGroupID');
    }

    /**
     * The mapping instance which must be used to seed table with SDE dump.
     *
     * @return \Seat\Eveapi\Mapping\Sde\AbstractSdeMapping
     */
    protected function getMappingClass(): AbstractSdeMapping
    {
        return new InvMetaTypeMapping();
    }

    /**
     * Only types which belong to a meta group are part of this table.
     *
     * @param  array  $arr
     * @return int
     */
    public function insert($arr)
    {
        $arr = array_values(array_filter(
            $arr,
            fn ($row) => ! is_null($row['metaGroupID'])
        ));

        if (count($arr) === 0)
            return 0;

        return DB::table('invMetaTypes')->insert($arr);
    }
}
