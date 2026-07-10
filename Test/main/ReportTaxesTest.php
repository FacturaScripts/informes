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

use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Lib\ProductType;
use FacturaScripts\Core\Lib\RegimenIVA;
use FacturaScripts\Core\Model\FacturaCliente;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Informes\Controller\ReportTaxes;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

final class ReportTaxesTest extends TestCase
{
    use DefaultSettingsTrait;
    use RandomDataTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    protected function setUp(): void
    {
        // el régimen de bienes usados (REBU) es específico de España
        if (Tools::config('codpais') !== 'ESP') {
            $this->markTestSkipped('country-is-not-spain');
        }
    }

    public function testSalesUsedGoodsMarginVat(): void
    {
        $tax = Impuestos::get('IVA21');
        if (false === $tax->exists()) {
            $this->markTestSkipped('IVA21-not-found');
        }

        // producto de segunda mano (sin control de stock para poder guardar la venta en el test)
        $product = $this->getRandomProduct();
        $product->codimpuesto = $tax->codimpuesto;
        $product->tipo = ProductType::SECOND_HAND;
        $product->nostock = true;
        $this->assertTrue($product->save(), 'cant-save-product');

        // empresa en régimen de bienes usados
        $invoice = new FacturaCliente();
        $company = $invoice->getCompany();
        $originalRegimen = $company->regimeniva;
        $company->regimeniva = RegimenIVA::TAX_SYSTEM_USED_GOODS;
        $this->assertTrue($company->save(), 'cant-save-company');

        // serie propia para aislar la factura en el informe
        $serie = $this->getRandomSerie();
        $this->assertTrue($serie->save(), 'cant-save-serie');

        // cliente + factura
        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'cant-save-customer');
        $invoice->setSubject($customer);
        $invoice->codserie = $serie->codserie;
        $this->assertTrue($invoice->save(), 'cant-save-invoice');

        // línea: pvp 200, coste 150, margen 50 -> IVA 21% de 50 = 10.5
        $line = $invoice->getNewProductLine($product->referencia);
        $line->cantidad = 1;
        $line->pvpunitario = 200;
        $line->coste = 150;
        $this->assertTrue($line->save(), 'cant-save-line');
        $lines = [$line];
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'cant-calculate');

        // sanity: la cabecera guarda el IVA sobre el margen, no sobre el total
        $this->assertEqualsWithDelta(200.0, $invoice->neto, 0.001, 'bad-invoice-neto');
        $this->assertEqualsWithDelta(10.5, $invoice->totaliva, 0.001, 'bad-invoice-totaliva');

        // ejecutamos el informe de impuestos (ventas) filtrando por nuestra serie
        $report = new class('ReportTaxes') extends ReportTaxes {
            public function fetchReportData(): array
            {
                return $this->getReportData();
            }
        };
        $report->source = 'sales';
        $report->idempresa = $company->idempresa;
        $report->coddivisa = $invoice->coddivisa;
        $report->codserie = $serie->codserie;
        $report->codpais = '';
        $report->typeDate = 'create';
        $report->datefrom = date('Y-01-01');
        $report->dateto = date('Y-12-31');

        $data = $report->fetchReportData();
        $this->assertNotEmpty($data, 'report-data-empty');

        // agregados: el IVA del informe debe ser sobre el margen (10.5), no sobre el total (42)
        $reportNeto = $reportIva = 0.0;
        foreach ($data as $row) {
            $reportNeto += $row['neto'];
            $reportIva += $row['totaliva'];
        }
        $this->assertEqualsWithDelta(200.0, $reportNeto, 0.001, 'bad-report-neto');
        $this->assertEqualsWithDelta(10.5, $reportIva, 0.001, 'report-iva-should-be-over-margin');

        // desglose fiel: el coste va al 0% y el margen al tipo de IVA
        $this->assertTrue(
            $this->hasRate($data, 0.0, ['neto' => 150.0]),
            'missing-cost-at-zero-rate'
        );
        $this->assertTrue(
            $this->hasRate($data, 21.0, ['neto' => 50.0, 'totaliva' => 10.5]),
            'missing-margin-at-tax-rate'
        );

        // limpieza
        $company->regimeniva = $originalRegimen;
        $this->assertTrue($company->save(), 'cant-restore-company');
        $this->assertTrue($invoice->delete(), 'cant-delete-invoice');
        $this->assertTrue($serie->delete(), 'cant-delete-serie');
        $this->assertTrue($product->delete(), 'cant-delete-product');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'cant-delete-contact');
        $this->assertTrue($customer->delete(), 'cant-delete-customer');
    }

    /**
     * Comprueba que existe una fila con el tipo de IVA dado cuyos campos coinciden (con tolerancia).
     */
    private function hasRate(array $data, float $iva, array $expected): bool
    {
        foreach ($data as $row) {
            if (abs((float)$row['iva'] - $iva) > 0.001) {
                continue;
            }

            foreach ($expected as $field => $value) {
                if (abs((float)$row[$field] - $value) > 0.001) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }
}
