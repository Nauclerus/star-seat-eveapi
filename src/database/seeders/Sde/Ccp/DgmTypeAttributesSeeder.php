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
use Seat\Eveapi\Mapping\Sde\Ccp\DgmTypeAttributeMapping;
use Seat\Eveapi\Database\Seeders\Sde\AbstractSdeSeeder;
use Seat\Eveapi\Models\Sde\DgmTypeAttribute;

class DgmTypeAttributesSeeder extends AbstractSdeSeeder
{

    protected const FILENAME = "typeDogma.jsonl";

    protected const IS_MULTI_SEEDER = true;

    
    /**
     * Define seeder related SDE table structure.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $table
     * @return void
     */
    protected function getSdeTableDefinition(Blueprint $table): void
    {
        $table->integer('typeID');
        $table->integer('attributeID');
        $table->integer('valueInt')->nullable();
        $table->double('valueFloat')->nullable();

        $table->primary(['typeID', 'attributeID']);
        $table->index('attributeID', 'ix_dgmTypeAttributes_attributeID');
    }

    /**
     * The mapping instance which must be used to seed table with SDE dump.
     *
     * @return \Seat\Eveapi\Mapping\Sde\AbstractSdeMapping
     */
    protected function getMappingClass(): AbstractSdeMapping
    {
        return new DgmTypeAttributeMapping();
    }

    /**
     * CCP publishes a single value per attribute. Store it in valueFloat and
     * mirror it into valueInt when it is a whole number, so readers which only
     * look at one of the two columns still find the value.
     *
     * @param  array  $arr
     * @return int
     */
    public function insert($arr)
    {
        foreach ($arr as &$row) {
            $value = $row['valueFloat'] ?? null;

            // Every row must carry the same columns for a batch insert.
            $row['valueInt'] = null;
            $row['valueFloat'] = is_null($value) ? null : (float) $value;

            if (is_null($row['valueFloat']))
                continue;

            if (abs($row['valueFloat'] - round($row['valueFloat'])) < 0.000001 &&
                abs($row['valueFloat']) <= 2147483647)
                $row['valueInt'] = (int) round($row['valueFloat']);
        }
        unset($row);

        return DgmTypeAttribute::insert($arr);
    }
}
