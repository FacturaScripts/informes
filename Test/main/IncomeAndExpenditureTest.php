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

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Plugins\Informes\Lib\Accounting\IncomeAndExpenditure;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use PHPUnit\Framework\TestCase;

final class IncomeAndExpenditureTest extends TestCase
{
    use DefaultSettingsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    public function testCreateBalance()
    {
        // creamos el asiento
        $asiento = new Asiento();
        $asiento->concepto = 'Test';
        $this->assertTrue($asiento->save(), 'asiento-cant-save-1');
        $this->assertNotNull($asiento->primaryColumnValue(), 'asiento-not-stored');
        $this->assertTrue($asiento->exists(), 'asiento-cant-persist');

        // añadimos una línea
        $firstLine = $asiento->getNewLine();
        $firstLine->codsubcuenta = '1000000000';
        $firstLine->concepto = 'Test linea 1';
        $firstLine->debe = 100;
        $this->assertTrue($firstLine->save(), 'linea-cant-save-1');

        // añadimos otra línea
        $secondLine = $asiento->getNewLine();
        $secondLine->codsubcuenta = '5700000000';
        $secondLine->concepto = 'Test linea 2';
        $secondLine->haber = 100;
        $this->assertTrue($secondLine->save(), 'linea-cant-save-2');

        // obtenemos el balance de ingresos y gastos
        $balance = new IncomeAndExpenditure();
        $exercise = $asiento->getExercise();
        $pages = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin);
        $this->assertNotEmpty($pages, 'balance-cant-generate');

        // eliminamos el asiento
        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
    }
}