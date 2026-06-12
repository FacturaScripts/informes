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

use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Calculator;
use FacturaScripts\Dinamic\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Lib\ProductType;
use FacturaScripts\Dinamic\Lib\RegimenIVA;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Plugins\Informes\Controller\ReportTaxes;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

final class ReportTaxesTest extends TestCase
{
    use LogErrorsTrait;
    use RandomDataTrait;

    public function testIntraCommunityPurchaseKeepsVatRateButNeutralizesAmount(): void
    {
        if (Tools::config('codpais') !== 'ESP') {
            $this->markTestSkipped('country-is-not-spain');
        }

        $supplier = $this->getRandomSupplier();
        $this->assertTrue($supplier->save(), 'can-not-save-supplier');

        $invoice = new FacturaProveedor();
        $invoice->operacion = InvoiceOperation::INTRA_COMMUNITY;
        $this->assertTrue($invoice->setSubject($supplier), 'can-not-assign-supplier');
        $this->assertTrue($invoice->save(), 'can-not-save-invoice');

        $line = $invoice->getNewLine();
        $line->cantidad = 1;
        $line->pvpunitario = 100;
        $line->iva = 21;
        $this->assertTrue($line->save(), 'can-not-save-line');

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'can-not-calculate-invoice');
        $this->assertTrue($invoice->load($invoice->idfactura), 'can-not-reload-invoice');

        $rows = ReportTaxesTestAccess::getReportRowsFromPurchaseInvoice($invoice);
        $this->assertCount(1, $rows, 'bad-rows-count');
        $this->assertEquals(100.0, $rows[0]['neto'], 'bad-neto');
        $this->assertEquals(21.0, $rows[0]['iva'], 'bad-iva');
        $this->assertEquals(0.0, $rows[0]['totaliva'], 'bad-totaliva');
        $this->assertEquals($invoice->totaliva, array_sum(array_column($rows, 'totaliva')), 'bad-report-totaliva');

        $this->assertTrue($invoice->delete(), 'can-not-delete-invoice');
        $this->assertTrue($supplier->getDefaultAddress()->delete(), 'contacto-cant-delete');
        $this->assertTrue($supplier->delete(), 'supplier-cant-delete');
    }

    public function testIntraCommunitySaleUsesSameTaxBreakdownAsInvoice(): void
    {
        if (Tools::config('codpais') !== 'ESP') {
            $this->markTestSkipped('country-is-not-spain');
        }

        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'can-not-save-customer');

        $invoice = new FacturaCliente();
        $invoice->operacion = InvoiceOperation::INTRA_COMMUNITY;
        $this->assertTrue($invoice->setSubject($customer), 'can-not-assign-customer');
        $this->assertTrue($invoice->save(), 'can-not-save-invoice');

        $line = $invoice->getNewLine();
        $line->cantidad = 1;
        $line->pvpunitario = 100;
        $line->iva = 21;
        $this->assertTrue($line->save(), 'can-not-save-line');

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'can-not-calculate-invoice');
        $this->assertTrue($invoice->load($invoice->idfactura), 'can-not-reload-invoice');

        $rows = ReportTaxesTestAccess::getReportRowsFromSalesInvoice($invoice);
        $this->assertCount(1, $rows, 'bad-rows-count');
        $this->assertEquals(100.0, $rows[0]['neto'], 'bad-neto');
        $this->assertEquals(0.0, $rows[0]['iva'], 'bad-iva');
        $this->assertEquals(0.0, $rows[0]['totaliva'], 'bad-totaliva');
        $this->assertEquals($invoice->totaliva, array_sum(array_column($rows, 'totaliva')), 'bad-report-totaliva');

        $this->assertTrue($invoice->delete(), 'can-not-delete-invoice');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'contacto-cant-delete');
        $this->assertTrue($customer->delete(), 'customer-cant-delete');
    }

    public function testPurchaseReportQueryReturnsInvoiceOnceWithMatchingVat(): void
    {
        if (Tools::config('codpais') !== 'ESP') {
            $this->markTestSkipped('country-is-not-spain');
        }

        $supplier = $this->getRandomSupplier();
        $this->assertTrue($supplier->save(), 'can-not-save-supplier');

        $invoice = new FacturaProveedor();
        $this->assertTrue($invoice->setSubject($supplier), 'can-not-assign-supplier');
        $this->assertTrue($invoice->save(), 'can-not-save-invoice');

        // dos líneas para comprobar que la factura no se duplica en el informe
        $line1 = $invoice->getNewLine();
        $line1->cantidad = 1;
        $line1->pvpunitario = 100;
        $line1->iva = 21;
        $this->assertTrue($line1->save(), 'can-not-save-line-1');

        $line2 = $invoice->getNewLine();
        $line2->cantidad = 1;
        $line2->pvpunitario = 50;
        $line2->iva = 21;
        $this->assertTrue($line2->save(), 'can-not-save-line-2');

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'can-not-calculate-invoice');
        $this->assertTrue($invoice->load($invoice->idfactura), 'can-not-reload-invoice');

        $rows = ReportTaxesTestAccess::getReportRowsFromPurchaseQuery($invoice);

        // la factura aparece una sola vez (todas las filas son del mismo código)
        $codes = array_unique(array_column($rows, 'codigo'));
        $this->assertCount(1, $codes, 'invoice-should-appear-once');
        $this->assertEquals($invoice->codigo, $codes[0], 'bad-invoice-code');

        // el IVA del informe coincide con el de la factura
        $reportVat = array_sum(array_column($rows, 'totaliva'));
        $this->assertEquals($invoice->totaliva, $reportVat, 'bad-report-totaliva');

        // el neto del informe coincide con el de la factura
        $reportNet = array_sum(array_column($rows, 'neto'));
        $this->assertEquals($invoice->neto, $reportNet, 'bad-report-neto');

        $this->assertTrue($invoice->delete(), 'can-not-delete-invoice');
        $this->assertTrue($supplier->getDefaultAddress()->delete(), 'contacto-cant-delete');
        $this->assertTrue($supplier->delete(), 'supplier-cant-delete');
    }

    public function testSecondHandInvoiceUsesDocumentTaxBreakdown(): void
    {
        if (Tools::config('codpais') !== 'ESP') {
            $this->markTestSkipped('country-is-not-spain');
        }

        $tax = Impuestos::get('IVA21');
        if (false === $tax->exists()) {
            $this->markTestSkipped('IVA21-not-found');
        }

        $product = $this->getRandomProduct();
        $product->codimpuesto = $tax->codimpuesto;
        $product->nostock = true;
        $product->tipo = ProductType::SECOND_HAND;
        $this->assertTrue($product->save(), 'can-not-save-product');

        $invoice = new FacturaCliente();
        $company = $invoice->getCompany();
        $originalRegimen = $company->regimeniva;
        $company->regimeniva = RegimenIVA::TAX_SYSTEM_USED_GOODS;
        $this->assertTrue($company->save(), 'can-not-save-company');

        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'can-not-save-customer');
        $this->assertTrue($invoice->setSubject($customer), 'can-not-assign-customer');
        $this->assertTrue($invoice->save(), 'can-not-save-invoice');

        $line = $invoice->getNewProductLine($product->referencia);
        $line->cantidad = 1;
        $line->pvpunitario = 200;
        $line->coste = 150;
        $this->assertTrue($line->save(), 'can-not-save-line');

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'can-not-calculate-invoice');
        $this->assertTrue($invoice->load($invoice->idfactura), 'can-not-reload-invoice');

        $rows = ReportTaxesTestAccess::getReportRowsFromSalesInvoice($invoice);
        $this->assertCount(2, $rows, 'bad-rows-count');

        $rowsByIva = [];
        foreach ($rows as $row) {
            $rowsByIva[(string)$row['iva']] = $row;
        }

        $this->assertArrayHasKey('0', $rowsByIva, 'missing-zero-tax-row');
        $this->assertArrayHasKey('21', $rowsByIva, 'missing-margin-tax-row');
        $this->assertEquals(150.0, $rowsByIva['0']['neto'], 'bad-cost-net');
        $this->assertEquals(0.0, $rowsByIva['0']['totaliva'], 'bad-cost-tax');
        $this->assertEquals(50.0, $rowsByIva['21']['neto'], 'bad-margin-net');
        $this->assertEquals(10.5, $rowsByIva['21']['totaliva'], 'bad-margin-tax');

        $reportVat = array_sum(array_column($rows, 'totaliva'));
        $this->assertEquals($invoice->totaliva, $reportVat, 'bad-report-totaliva');

        $company->regimeniva = $originalRegimen;
        $this->assertTrue($company->save(), 'can-not-restore-company');

        $this->assertTrue($invoice->delete(), 'can-not-delete-invoice');
        $this->assertTrue($product->delete(), 'can-not-delete-product');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'contacto-cant-delete');
        $this->assertTrue($customer->delete(), 'customer-cant-delete');
    }

    public function testTravelAgencyInvoiceUsesMarginTaxBreakdown(): void
    {
        if (Tools::config('codpais') !== 'ESP') {
            $this->markTestSkipped('country-is-not-spain');
        }

        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'can-not-save-customer');

        $invoice = new FacturaCliente();
        $company = $invoice->getCompany();
        $originalRegimen = $company->regimeniva;
        $company->regimeniva = RegimenIVA::TAX_SYSTEM_TRAVEL;
        $this->assertTrue($company->save(), 'can-not-save-company');

        $this->assertTrue($invoice->setSubject($customer), 'can-not-assign-customer');
        $this->assertTrue($invoice->save(), 'can-not-save-invoice');

        $line = $invoice->getNewLine();
        $line->cantidad = 1;
        $line->pvpunitario = 200;
        $line->coste = 120;
        $line->iva = 21;
        $this->assertTrue($line->save(), 'can-not-save-line');

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'can-not-calculate-invoice');
        $this->assertTrue($invoice->load($invoice->idfactura), 'can-not-reload-invoice');

        $rows = ReportTaxesTestAccess::getReportRowsFromSalesInvoice($invoice);
        $this->assertCount(2, $rows, 'bad-rows-count');

        $rowsByIva = [];
        foreach ($rows as $row) {
            $rowsByIva[(string)$row['iva']] = $row;
        }

        $this->assertArrayHasKey('0', $rowsByIva, 'missing-zero-tax-row');
        $this->assertArrayHasKey('21', $rowsByIva, 'missing-margin-tax-row');
        $this->assertEquals(120.0, $rowsByIva['0']['neto'], 'bad-cost-net');
        $this->assertEquals(0.0, $rowsByIva['0']['totaliva'], 'bad-cost-tax');
        $this->assertEquals(80.0, $rowsByIva['21']['neto'], 'bad-margin-net');
        $this->assertEquals(16.8, $rowsByIva['21']['totaliva'], 'bad-margin-tax');
        $this->assertEquals($invoice->totaliva, array_sum(array_column($rows, 'totaliva')), 'bad-report-totaliva');

        $company->regimeniva = $originalRegimen;
        $this->assertTrue($company->save(), 'can-not-restore-company');

        $this->assertTrue($invoice->delete(), 'can-not-delete-invoice');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'contacto-cant-delete');
        $this->assertTrue($customer->delete(), 'customer-cant-delete');
    }

    public function testTravelAgencyNegativeMarginProducesZeroVatInReport(): void
    {
        if (Tools::config('codpais') !== 'ESP') {
            $this->markTestSkipped('country-is-not-spain');
        }

        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'can-not-save-customer');

        $invoice = new FacturaCliente();
        $company = $invoice->getCompany();
        $originalRegimen = $company->regimeniva;
        $company->regimeniva = RegimenIVA::TAX_SYSTEM_TRAVEL;
        $this->assertTrue($company->save(), 'can-not-save-company');

        $this->assertTrue($invoice->setSubject($customer), 'can-not-assign-customer');
        $this->assertTrue($invoice->save(), 'can-not-save-invoice');

        $line = $invoice->getNewLine();
        $line->cantidad = 1;
        $line->pvpunitario = 80;
        $line->coste = 100;
        $line->iva = 21;
        $this->assertTrue($line->save(), 'can-not-save-line');

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'can-not-calculate-invoice');
        $this->assertTrue($invoice->load($invoice->idfactura), 'can-not-reload-invoice');

        $rows = ReportTaxesTestAccess::getReportRowsFromSalesInvoice($invoice);
        $this->assertCount(2, $rows, 'bad-rows-count');

        $rowsByIva = [];
        foreach ($rows as $row) {
            $rowsByIva[(string)$row['iva']] = $row;
        }

        $this->assertArrayHasKey('0', $rowsByIva, 'missing-zero-tax-row');
        $this->assertArrayHasKey('21', $rowsByIva, 'missing-margin-tax-row');
        $this->assertEquals(100.0, $rowsByIva['0']['neto'], 'bad-cost-net');
        $this->assertEquals(-20.0, $rowsByIva['21']['neto'], 'bad-margin-net');
        $this->assertEquals(0.0, $rowsByIva['21']['totaliva'], 'bad-margin-tax');
        $this->assertEquals(0.0, array_sum(array_column($rows, 'totaliva')), 'bad-report-totaliva');
        $this->assertEquals($invoice->totaliva, array_sum(array_column($rows, 'totaliva')), 'bad-document-totaliva');

        $company->regimeniva = $originalRegimen;
        $this->assertTrue($company->save(), 'can-not-restore-company');

        $this->assertTrue($invoice->delete(), 'can-not-delete-invoice');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'contacto-cant-delete');
        $this->assertTrue($customer->delete(), 'customer-cant-delete');
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}

class ReportTaxesTestAccess extends ReportTaxes
{
    public static function getReportRowsFromPurchaseInvoice(FacturaProveedor $invoice): array
    {
        $controller = new self('ReportTaxes');
        $controller->source = 'purchases';
        $controller->typeDate = 'create';

        return $controller->getReportDataFromDocument([
            'codserie' => $invoice->codserie,
            'codigo' => $invoice->codigo,
            'numproveedor' => $invoice->numproveedor,
            'fecha' => $invoice->fecha,
            'fechadevengo' => $invoice->fechadevengo,
            'nombre' => $invoice->nombre,
            'cifnif' => $invoice->cifnif,
            'total' => $invoice->total,
        ], $invoice);
    }

    public static function getReportRowsFromPurchaseQuery(FacturaProveedor $invoice): array
    {
        $controller = new self('ReportTaxes');
        $controller->source = 'purchases';
        $controller->typeDate = 'create';
        $controller->idempresa = $invoice->idempresa;
        $controller->coddivisa = $invoice->coddivisa;
        $controller->datefrom = $invoice->fecha;
        $controller->dateto = $invoice->fecha;
        $controller->codserie = '';
        $controller->codpais = '';

        return $controller->getReportData();
    }

    public static function getReportRowsFromSalesInvoice(FacturaCliente $invoice): array
    {
        $controller = new self('ReportTaxes');
        $controller->source = 'sales';
        $controller->typeDate = 'create';

        return $controller->getReportDataFromDocument([
            'codserie' => $invoice->codserie,
            'codigo' => $invoice->codigo,
            'numero2' => $invoice->numero2,
            'fecha' => $invoice->fecha,
            'fechadevengo' => $invoice->fechadevengo,
            'nombre' => $invoice->nombrecliente,
            'cifnif' => $invoice->cifnif,
            'codpais' => $invoice->codpais,
            'ciudad' => $invoice->ciudad,
            'provincia' => $invoice->provincia,
            'codpostal' => $invoice->codpostal,
            'total' => $invoice->total,
        ], $invoice);
    }
}