<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Test\Core\Model;

use FacturaScripts\Plugins\Informes\Model\BalanceCode;
use PHPUnit\Framework\TestCase;

final class BalanceCodeTest extends TestCase
{
    public function testCreate()
    {
        // creamos un balance
        $balance = new BalanceCode();
        $balance->codbalance = 'test';
        $balance->description1 = 'test';
        $balance->nature = 'A';
        $this->assertTrue($balance->save(), 'cant-save-balance');

        // eliminamos
        $this->assertTrue($balance->delete(), 'cant-delete-balance');
    }

    public function testCantCreateEmpty()
    {
        $balance = new BalanceCode();
        $this->assertFalse($balance->save(), 'cant-save-balance');
    }

    public function testHtmlOnFields()
    {
        $balance = new BalanceCode();
        $balance->codbalance = '<test>';
        $balance->description1 = '<test>';
        $balance->description2 = '<test>';
        $balance->description3 = '<test>';
        $balance->description4 = '<test>';
        $balance->nature = '<test>';
        $this->assertFalse($balance->save(), 'cant-save-balance-with-html');

        // cambiamos el código a un código válido
        $balance->codbalance = 'test';
        $this->assertTrue($balance->save(), 'cant-save-balance-2');

        // comprobamos que el html se ha escapado
        $this->assertEquals('&lt;test&gt;', $balance->description1);
        $this->assertEquals('&lt;test&gt;', $balance->description2);
        $this->assertEquals('&lt;test&gt;', $balance->description3);
        $this->assertEquals('&lt;test&gt;', $balance->description4);
        $this->assertEquals('&lt;test&gt;', $balance->nature);

        // eliminamos
        $this->assertTrue($balance->delete(), 'cant-delete-balance');
    }
}
