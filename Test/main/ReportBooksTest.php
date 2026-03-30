<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Plugins\Informes\Controller\ReportBooks;
use PHPUnit\Framework\TestCase;

final class ReportBooksTest extends TestCase
{
    public function testExpenseWithoutInvoiceStaysInTaxBase(): void
    {
        $amounts = ReportBooksTestAccess::getLineAmounts([
            'line_baseimponible' => 0,
            'line_iva' => 0,
            'line_recargo' => 0,
            'debe' => 1540.25,
            'invoice_total' => 0,
            'invoice_totaliva' => 0,
            'invoice_totalrecargo' => 0,
        ], 1540.25);

        $this->assertEquals(1540.25, $amounts['baseimponible']);
        $this->assertEquals(0.0, $amounts['iva']);
        $this->assertEquals(0.0, $amounts['recargo']);
        $this->assertEquals(0.0, $amounts['gasto']);
    }

    public function testInvoiceAmountsAreProratedByExpenseBase(): void
    {
        $amounts = ReportBooksTestAccess::getLineAmounts([
            'line_baseimponible' => 0,
            'line_iva' => 0,
            'line_recargo' => 0,
            'debe' => 60,
            'invoice_total' => 121,
            'invoice_totaliva' => 21,
            'invoice_totalrecargo' => 0,
        ], 100);

        $this->assertEquals(60.0, $amounts['baseimponible']);
        $this->assertEquals(12.6, $amounts['iva']);
        $this->assertEquals(0.0, $amounts['recargo']);
        $this->assertEquals(72.6, $amounts['gasto']);
    }

    public function testManualVatLineKeepsComputedTotal(): void
    {
        $amounts = ReportBooksTestAccess::getLineAmounts([
            'line_baseimponible' => 100,
            'line_iva' => 21,
            'line_recargo' => 5.2,
            'debe' => 100,
            'invoice_total' => 0,
            'invoice_totaliva' => 0,
            'invoice_totalrecargo' => 0,
        ], 100);

        $this->assertEquals(100.0, $amounts['baseimponible']);
        $this->assertEquals(21.0, $amounts['iva']);
        $this->assertEquals(5.2, $amounts['recargo']);
        $this->assertEquals(126.2, $amounts['gasto']);
    }
}

class ReportBooksTestAccess extends ReportBooks
{
    public static function getLineAmounts(array $row, float $entryBaseTotal): array
    {
        return parent::getExpenseBookLineAmounts($row, $entryBaseTotal);
    }
}
