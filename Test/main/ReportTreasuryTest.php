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
use FacturaScripts\Plugins\Informes\Controller\ReportTreasury;
use PHPUnit\Framework\TestCase;

final class ReportTreasuryTest extends TestCase
{
    public function testCurrentCorporateTaxUsesCurrentExercisePercentage(): void
    {
        $report = new class ('ReportTreasury') extends ReportTreasury {
            public function calculateTaxes(float $currentPercentage, float $previousPercentage): array
            {
                $this->ejercicio = new Ejercicio();
                $this->ejercicio->impsociedades = $currentPercentage;
                $this->ejercicio_ant = new Ejercicio();
                $this->ejercicio_ant->impsociedades = $previousPercentage;
                $this->desde = '2026-01-01';
                $this->hasta = '2026-12-31';

                $this->cuadroImpuestos();
                return $this->da_impuestos;
            }

            protected function getVentasTotales(): float
            {
                return 1000.0;
            }

            protected function saldoCuenta(string $cuenta, string $desde, string $hasta): float
            {
                return 0.0;
            }
        };

        $taxes = $report->calculateTaxes(25.0, 10.0);
        $this->assertSame(-250.0, $taxes['sociedades']);
        $this->assertSame(-250.0, $taxes['pagofraccionado-mod202']);
    }
}
