<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2026 Carlos García Gómez <carlos@facturascripts.com>
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

use FacturaScripts\Dinamic\Model\Ejercicio;
use PHPUnit\Framework\TestCase;

final class EjercicioTest extends TestCase
{
    public function testCorporateTaxDefault(): void
    {
        $exercise = new Ejercicio();
        $this->assertSame(0.0, $exercise->impsociedades);
    }

    public function testCorporateTaxPercentage(): void
    {
        $exercise = new Ejercicio();
        $exercise->codejercicio = 'test';
        $exercise->nombre = 'test';

        foreach ([0, 25, 100, '25.5'] as $value) {
            $exercise->impsociedades = $value;
            $this->assertTrue($exercise->test());
            $this->assertIsFloat($exercise->impsociedades);
        }

        foreach ([-0.01, 100.01, 'invalid'] as $value) {
            $exercise->impsociedades = $value;
            $this->assertFalse($exercise->test());
        }
    }
}
