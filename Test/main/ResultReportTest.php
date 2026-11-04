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

use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Base\BusinessDocumentLine;
use FacturaScripts\Core\Model\FacturaCliente;
use FacturaScripts\Core\Model\FacturaProveedor;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\Informes\Lib\Informes\ResultReport;
use FacturaScripts\Plugins\Informes\Model\Join\FacturaClienteProducto;
use FacturaScripts\Plugins\Informes\Model\Join\FacturaProveedorProducto;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

/**
 * Comprueba que el informe de resultados y el informe de productos tienen en cuenta
 * los descuentos globales de las facturas (dtopor1 y dtopor2).
 */
final class ResultReportTest extends TestCase
{
    use DefaultSettingsTrait;
    use RandomDataTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    public function testProductAverageIncludesGlobalDiscount(): void
    {
        $product = $this->getRandomProduct();
        $this->assertTrue($product->save(), 'cant-save-product');
        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'cant-save-customer');
        $supplier = $this->getRandomSupplier();
        $this->assertTrue($supplier->save(), 'cant-save-supplier');

        // venta y compra de 4 unidades a 50 con un 20 % de descuento global: 40 por unidad
        $sale = new FacturaCliente();
        $sale->setSubject($customer);
        $sale->dtopor1 = 20;
        $this->assertTrue($sale->save(), 'cant-save-sale');
        $this->addProductLine($sale, $product->referencia, 4, 50);

        $purchase = new FacturaProveedor();
        $purchase->setSubject($supplier);
        $purchase->dtopor1 = 20;
        $this->assertTrue($purchase->save(), 'cant-save-purchase');
        $this->addProductLine($purchase, $product->referencia, 4, 50);

        $sales = FacturaClienteProducto::all([Where::eq('lineasfacturascli.idproducto', $product->idproducto)]);
        $this->assertCount(1, $sales, 'bad-sales-rows');
        $this->assertEqualsWithDelta(40.0, $sales[0]->avgprecio, 0.001, 'bad-avg-price');

        $purchases = FacturaProveedorProducto::all([Where::eq('lineasfacturasprov.idproducto', $product->idproducto)]);
        $this->assertCount(1, $purchases, 'bad-purchases-rows');
        $this->assertEqualsWithDelta(40.0, $purchases[0]->avgcoste, 0.001, 'bad-avg-cost');

        // limpieza
        $this->assertTrue($sale->delete(), 'cant-delete-sale');
        $this->assertTrue($purchase->delete(), 'cant-delete-purchase');
        $this->assertTrue($product->delete(), 'cant-delete-product');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'cant-delete-contact');
        $this->assertTrue($customer->delete(), 'cant-delete-customer');
        $this->assertTrue($supplier->getDefaultAddress()->delete(), 'cant-delete-supplier-contact');
        $this->assertTrue($supplier->delete(), 'cant-delete-supplier');
    }

    public function testSalesIncludeGlobalDiscount(): void
    {
        $product = $this->getRandomProduct();
        $this->assertTrue($product->save(), 'cant-save-product');
        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'cant-save-customer');

        // factura con un 10 % y un 5 % de descuento global, todavía sin líneas
        $invoice = new FacturaCliente();
        $invoice->setSubject($customer);
        $invoice->dtopor1 = 10;
        $invoice->dtopor2 = 5;
        $this->assertTrue($invoice->save(), 'cant-save-invoice');

        // total del mes antes de añadir las líneas, para no depender de los datos de otros tests
        $before = $this->getSalesMonthTotal($invoice);

        // 2 unidades a 100: neto de 171
        $this->addProductLine($invoice, $product->referencia, 2, 100);
        $this->assertEqualsWithDelta(171.0, $invoice->neto, 0.001, 'bad-invoice-neto');

        $after = $this->getSalesMonthTotal($invoice);
        $this->assertEqualsWithDelta($invoice->neto, $after - $before, 0.001, 'global-discount-not-applied');

        // limpieza
        $this->assertTrue($invoice->delete(), 'cant-delete-invoice');
        $this->assertTrue($product->delete(), 'cant-delete-product');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'cant-delete-contact');
        $this->assertTrue($customer->delete(), 'cant-delete-customer');
    }

    public function testSalesTotalMatchesInvoicesNet(): void
    {
        $product = $this->getRandomProduct();
        $this->assertTrue($product->save(), 'cant-save-product');
        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'cant-save-customer');

        // tres facturas con un 66,67 % de descuento global, todavía sin líneas
        $invoices = [];
        for ($num = 0; $num < 3; $num++) {
            $invoice = new FacturaCliente();
            $invoice->setSubject($customer);
            $invoice->dtopor1 = 66.67;
            $this->assertTrue($invoice->save(), 'cant-save-invoice');
            $invoices[] = $invoice;
        }

        // total del mes antes de añadir las líneas, para no depender de los datos de otros tests
        $before = $this->getSalesMonthTotal($invoices[0]);

        // una línea de 10 en cada factura: 3,333 sin redondear y 3,33 de neto, así que la suma
        // sin redondear (9,999 → 10,00) no coincide con la suma de los netos (9,99)
        $netos = 0.0;
        foreach ($invoices as $invoice) {
            $this->addProductLine($invoice, $product->referencia, 1, 10);
            $this->assertEqualsWithDelta(3.33, $invoice->neto, 0.001, 'bad-invoice-neto');
            $netos += $invoice->neto;
        }

        $after = $this->getSalesMonthTotal($invoices[0]);
        $this->assertEqualsWithDelta($netos, $after - $before, 0.001, 'total-does-not-match-invoices-net');

        // limpieza
        foreach ($invoices as $invoice) {
            $this->assertTrue($invoice->delete(), 'cant-delete-invoice');
        }
        $this->assertTrue($product->delete(), 'cant-delete-product');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'cant-delete-contact');
        $this->assertTrue($customer->delete(), 'cant-delete-customer');
    }

    /**
     * Añade una línea del producto a la factura y recalcula sus totales.
     */
    private function addProductLine(BusinessDocument $invoice, string $referencia, float $cantidad, float $precio): void
    {
        /** @var BusinessDocumentLine $line */
        $line = $invoice->getNewProductLine($referencia);
        $line->cantidad = $cantidad;
        $line->pvpunitario = $precio;
        $this->assertTrue($line->save(), 'cant-save-line');

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'cant-calculate');
    }

    /**
     * Devuelve el total de ventas del informe de resultados en el mes y ejercicio de la factura.
     */
    private function getSalesMonthTotal(FacturaCliente $invoice): float
    {
        $report = new class extends ResultReport {
            public static function salesMonthTotal(string $year, string $codejercicio, int $month): float
            {
                self::$parent_codfamilia = null;
                self::salesPurchasesBuildYear($year, $codejercicio, 'load-sales');
                return (float)self::$ventas[$year]['total_mes'][$month];
            }
        };

        $time = strtotime($invoice->fecha);
        return $report::salesMonthTotal(date('Y', $time), $invoice->codejercicio, (int)date('n', $time));
    }
}
