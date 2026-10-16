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
use FacturaScripts\Core\Lib\InvoiceOperation;
use FacturaScripts\Core\Lib\ProductType;
use FacturaScripts\Core\Lib\RegimenIVA;
use FacturaScripts\Core\Lib\TaxExceptions;
use FacturaScripts\Core\Model\FacturaCliente;
use FacturaScripts\Core\Model\FacturaProveedor;
use FacturaScripts\Core\Response;
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

    public function testExportBuildsRealDocuments(): void
    {
        $data = [
            array_merge($this->getExportRow(21.0, 5000.0, 1050.0), ['irpf' => 15.0, 'totalirpf' => 750.0]),
            $this->getExportRow(
                21.0, 1000.0, 0.0, 'FAC-002', 1000.0,
                InvoiceOperation::REVERSE_CHARGE, 210.0, TaxExceptions::ES_TAX_EXCEPTION_84
            )
        ];

        // el motor PDF guarda la caché de fuentes en MyFiles/Cache, que el entorno de tests no crea
        Tools::folderCheckOrCreate(Tools::folder('MyFiles', 'Cache'));

        // usamos el ExportManager real para comprobar que cada formato genera su documento
        $expected = ['PDF' => '%PDF', 'CSV' => "\xEF\xBB\xBF", 'XLS' => "PK"];
        foreach ($expected as $format => $prefix) {
            $report = new class ('ReportTaxes') extends ReportTaxes {
                public array $reportData = [];

                public function exportReport(array $data, string $format): string
                {
                    $this->response = new Response();
                    $this->reportData = $data;
                    $this->format = $format;
                    $this->source = 'purchases';
                    $this->idempresa = (int)Tools::settings('default', 'idempresa');
                    $this->coddivisa = Tools::settings('default', 'coddivisa');
                    $this->typeDate = 'create';
                    $this->datefrom = date('Y-01-01');
                    $this->dateto = date('Y-12-31');
                    $this->columns = [Tools::trans('code'), Tools::trans('vat-exception'), Tools::trans('net')];
                    $this->exportAction();

                    return $this->response->getContent();
                }

                protected function getReportData(): array
                {
                    return $this->reportData;
                }

                protected function validateTotals(array $totalsData): bool
                {
                    return true;
                }
            };

            $content = $report->exportReport($data, $format);
            $this->assertStringStartsWith($prefix, $content, 'bad-document-for-' . strtolower($format));
        }
    }

    public function testExportOnlyShowsInvoiceDataOnFirstTaxRow(): void
    {
        $data = [
            $this->getExportRow(21.0, 100.0, 21.0),
            $this->getExportRow(10.0, 50.0, 5.0),
            $this->getExportRow(21.0, 200.0, 42.0, 'FAC-002', 242.0)
        ];

        $report = new class ('ReportTaxes') extends ReportTaxes {
            public array $exportedLines = [];
            public array $reportData = [];

            public function exportReport(array $data, string $format): array
            {
                $this->reportData = $data;
                $this->format = $format;
                $this->source = 'sales';
                $this->columns = [Tools::trans('code'), Tools::trans('name'), Tools::trans('total')];
                $this->exportAction();

                return $this->exportedLines;
            }

            protected function getReportData(): array
            {
                return $this->reportData;
            }

            protected function processLayout(array &$lines, array &$totals): void
            {
                $this->exportedLines = $lines;
            }

            protected function validateTotals(array $totalsData): bool
            {
                return true;
            }
        };

        foreach (['CSV', 'XLS'] as $format) {
            $lines = $report->exportReport($data, $format);
            $this->assertSame(
                ['FAC-001', '', 'FAC-002'],
                array_column($lines, Tools::trans('code')),
                'invoice-code-repeated-in-' . strtolower($format)
            );
            $this->assertSame(
                ['Test customer', '', 'Test customer'],
                array_column($lines, Tools::trans('name')),
                'invoice-name-repeated-in-' . strtolower($format)
            );
            $this->assertSame(
                ['121', '55', '242'],
                array_column($lines, Tools::trans('total')),
                'total-per-tax-row-wrong-in-' . strtolower($format)
            );
        }
    }

    public function testExportSplitsTotalsByOperation(): void
    {
        $data = [
            $this->getExportRow(21.0, 100.0, 21.0, 'FAC-001', 121.0),
            $this->getExportRow(21.0, 1000.0, 0.0, 'FAC-002', 1000.0, InvoiceOperation::REVERSE_CHARGE, 210.0),
            $this->getExportRow(21.0, 200.0, 42.0, 'FAC-003', 242.0)
        ];
        $columns = [
            Tools::trans('code'),
            Tools::trans('operation'),
            Tools::trans('tax'),
            Tools::trans('self-assessed-tax')
        ];
        [$lines, $tables] = $this->runExport($data, 'CSV', 'purchases', $columns);

        // detalle: la operación solo aparece en la factura con inversión del sujeto pasivo
        $reverseChargeName = Tools::trans(InvoiceOperation::get(InvoiceOperation::REVERSE_CHARGE));
        $this->assertSame(
            ['', $reverseChargeName, ''],
            array_column($lines, Tools::trans('operation')),
            'bad-operation-column'
        );
        $this->assertSame(
            ['0', '210', '0'],
            array_column($lines, Tools::trans('self-assessed-tax')),
            'bad-self-assessed-tax-column'
        );

        // totales (tabla plana): el 21% normal y el 21% con inversión del sujeto pasivo van en filas separadas
        $this->assertCount(1, $tables, 'csv-should-have-one-totals-table');
        $totals = $tables[0]['rows'];
        $this->assertCount(2, $totals, 'totals-not-split-by-operation');
        $this->assertSame(
            ['', $reverseChargeName],
            array_column($totals, Tools::trans('operation')),
            'bad-operation-in-totals'
        );
        $this->assertSame(['300', '1000'], array_column($totals, Tools::trans('net')), 'bad-net-in-totals');
        $this->assertSame(['63', '0'], array_column($totals, Tools::trans('tax')), 'bad-tax-in-totals');
        $this->assertSame(
            ['0', '210'],
            array_column($totals, Tools::trans('self-assessed-tax')),
            'bad-self-assessed-tax-in-totals'
        );
    }

    public function testExportSplitsTotalsByTaxException(): void
    {
        $data = [
            $this->getExportRow(0.0, 100.0, 0.0, 'FAC-001', 100.0, null, 0.0, TaxExceptions::ES_TAX_EXCEPTION_20),
            $this->getExportRow(0.0, 50.0, 0.0, 'FAC-001', 50.0, null, 0.0, TaxExceptions::ES_TAX_EXCEPTION_7),
            $this->getExportRow(0.0, 30.0, 0.0, 'FAC-002', 30.0, null, 0.0, TaxExceptions::ES_TAX_EXCEPTION_20)
        ];
        $columns = [Tools::trans('code'), Tools::trans('vat-exception'), Tools::trans('net')];
        [$lines, $tables] = $this->runExport($data, 'CSV', 'sales', $columns);

        // detalle: la excepción es de la línea, así que se repite aunque la factura sea la misma
        $exempt = Tools::trans(TaxExceptions::get(TaxExceptions::ES_TAX_EXCEPTION_20));
        $notSubject = Tools::trans(TaxExceptions::get(TaxExceptions::ES_TAX_EXCEPTION_7));
        $this->assertSame(['FAC-001', '', 'FAC-002'], array_column($lines, Tools::trans('code')), 'bad-code-column');
        $this->assertSame(
            [$exempt, $notSubject, $exempt],
            array_column($lines, Tools::trans('vat-exception')),
            'bad-vat-exception-column'
        );

        // totales: las ventas exentas y las no sujetas al 0% van en filas separadas
        $totals = $tables[0]['rows'];
        $this->assertSame(
            [$exempt, $notSubject],
            array_column($totals, Tools::trans('vat-exception')),
            'totals-not-split-by-vat-exception'
        );
        $this->assertSame(['130', '50'], array_column($totals, Tools::trans('net')), 'bad-net-in-totals');
    }

    public function testExportTotalsWithoutOperationsKeepColumns(): void
    {
        $data = [
            $this->getExportRow(21.0, 100.0, 21.0),
            $this->getExportRow(21.0, 200.0, 42.0, 'FAC-002', 242.0)
        ];
        [, $tables] = $this->runExport($data, 'CSV', 'sales', [Tools::trans('code')]);

        // sin operaciones especiales los totales no añaden columnas vacías
        $totals = $tables[0]['rows'];
        $this->assertCount(1, $totals, 'totals-should-group-same-rate');
        $this->assertArrayNotHasKey(Tools::trans('operation'), $totals[0]);
        $this->assertArrayNotHasKey(Tools::trans('self-assessed-tax'), $totals[0]);
        $this->assertArrayNotHasKey(Tools::trans('vat-exception'), $totals[0]);
    }

    public function testPdfTotalsSkipEmptyTaxTables(): void
    {
        $data = [
            $this->getExportRow(21.0, 100.0, 21.0),
            array_merge($this->getExportRow(0.0, 0.0, 0.0, 'FAC-002', 50.0), ['suplidos' => 50.0])
        ];
        [, $tables] = $this->runExport($data, 'PDF', 'sales', [Tools::trans('code')]);

        // sin recargo, IRPF ni excepciones solo quedan la tabla de IVA y el resumen
        $this->assertSame(
            [Tools::trans('vat'), Tools::trans('summary')],
            array_column($tables, 'title'),
            'bad-pdf-totals-tables'
        );

        // la fila de solo suplidos no aparece en el IVA y la tabla no lleva columnas vacías
        $vatRows = $tables[0]['rows'];
        $this->assertCount(1, $vatRows, 'supplied-row-should-not-be-in-vat-table');
        $this->assertSame(
            [Tools::trans('tax-base'), Tools::trans('pct-tax'), Tools::trans('tax')],
            array_keys($vatRows[0]),
            'bad-vat-table-columns'
        );

        // el resumen solo añade la columna de suplidos porque es la única opcional con importe
        $this->assertSame(
            [
                Tools::trans('net') => Tools::number(100.0),
                Tools::trans('tax') => Tools::number(21.0),
                Tools::trans('supplied-amount') => Tools::number(50.0),
                Tools::trans('total') => Tools::number(71.0)
            ],
            $tables[1]['rows'][0],
            'bad-pdf-summary'
        );
    }

    public function testPdfTotalsSplitByTax(): void
    {
        $data = [
            array_merge($this->getExportRow(21.0, 5000.0, 1050.0), ['irpf' => 15.0, 'totalirpf' => 750.0]),
            $this->getExportRow(
                21.0, 1000.0, 0.0, 'FAC-002', 1000.0,
                InvoiceOperation::REVERSE_CHARGE, 210.0, TaxExceptions::ES_TAX_EXCEPTION_84
            ),
            $this->getExportRow(0.0, 100.0, 0.0, 'FAC-003', 100.0, null, 0.0, TaxExceptions::ES_TAX_EXCEPTION_20)
        ];
        $columns = [Tools::trans('code'), Tools::trans('vat-exception')];
        [$lines, $tables] = $this->runExport($data, 'PDF', 'purchases', $columns);

        // en el detalle del PDF la excepción sale con su código corto
        $this->assertSame(
            ['', TaxExceptions::ES_TAX_EXCEPTION_84, TaxExceptions::ES_TAX_EXCEPTION_20],
            array_column($lines, Tools::trans('vat-exception')),
            'pdf-detail-should-use-exception-code'
        );

        // una tabla por impuesto con importes (sin recargo), el resumen y la leyenda
        $this->assertSame(
            [Tools::trans('vat'), Tools::trans('irpf'), Tools::trans('summary'), Tools::trans('tax-exceptions')],
            array_column($tables, 'title'),
            'bad-pdf-totals-tables'
        );
        [$vatRows, $irpfRows, $summaryRows, $legendRows] = array_column($tables, 'rows');

        // IVA: una fila por tipo, operación y excepción, con la cuota autorepercutida
        $reverseChargeName = Tools::trans(InvoiceOperation::get(InvoiceOperation::REVERSE_CHARGE));
        $this->assertSame(
            ['', $reverseChargeName, ''],
            array_column($vatRows, Tools::trans('operation')),
            'bad-operation-in-vat-table'
        );
        $this->assertSame(
            ['', TaxExceptions::ES_TAX_EXCEPTION_84, TaxExceptions::ES_TAX_EXCEPTION_20],
            array_column($vatRows, Tools::trans('vat-exception')),
            'bad-exception-in-vat-table'
        );
        $this->assertSame(
            [Tools::number(5000.0), Tools::number(1000.0), Tools::number(100.0)],
            array_column($vatRows, Tools::trans('tax-base')),
            'bad-base-in-vat-table'
        );
        $this->assertSame(
            [Tools::number(0.0), Tools::number(210.0), Tools::number(0.0)],
            array_column($vatRows, Tools::trans('self-assessed-tax')),
            'bad-self-assessed-tax-in-vat-table'
        );
        $this->assertArrayNotHasKey(Tools::trans('irpf'), $vatRows[0], 'vat-table-should-not-have-irpf');

        // IRPF: solo la base que lleva retención
        $this->assertSame(
            [[
                Tools::trans('tax-base') => Tools::number(5000.0),
                Tools::trans('pct-irpf') => Tools::number(15.0) . ' %',
                Tools::trans('retention') => Tools::number(750.0)
            ]],
            $irpfRows,
            'bad-irpf-table'
        );

        // resumen: 6100 de base + 1050 de IVA - 750 de IRPF; la cuota autorepercutida no suma al total
        $this->assertSame(
            [
                Tools::trans('net') => Tools::number(6100.0),
                Tools::trans('tax') => Tools::number(1050.0),
                Tools::trans('self-assessed-tax') => Tools::number(210.0),
                Tools::trans('irpf') => Tools::number(750.0),
                Tools::trans('total') => Tools::number(6400.0)
            ],
            $summaryRows[0],
            'bad-pdf-summary'
        );

        // leyenda: solo las excepciones usadas, ordenadas por código y con su descripción
        $this->assertSame(
            [
                [
                    Tools::trans('code') => TaxExceptions::ES_TAX_EXCEPTION_20,
                    Tools::trans('description') => Tools::trans(TaxExceptions::get(TaxExceptions::ES_TAX_EXCEPTION_20))
                ],
                [
                    Tools::trans('code') => TaxExceptions::ES_TAX_EXCEPTION_84,
                    Tools::trans('description') => Tools::trans(TaxExceptions::get(TaxExceptions::ES_TAX_EXCEPTION_84))
                ]
            ],
            $legendRows,
            'bad-tax-exceptions-legend'
        );
    }

    public function testPurchasesIntraCommunityServicesVat(): void
    {
        $this->assertPurchaseReverseCharge(InvoiceOperation::INTRA_COMMUNITY_SERVICES);
    }

    public function testPurchasesIntraCommunityVat(): void
    {
        $this->assertPurchaseReverseCharge(InvoiceOperation::INTRA_COMMUNITY);
    }

    public function testPurchasesReverseChargeVat(): void
    {
        $this->assertPurchaseReverseCharge(InvoiceOperation::REVERSE_CHARGE);
    }

    public function testSalesSplitsLinesByTaxException(): void
    {
        $tax = Impuestos::get('IVA0');
        if (false === $tax->exists()) {
            $this->markTestSkipped('IVA0-not-found');
        }

        // producto al 0% sin control de stock
        $product = $this->getRandomProduct();
        $product->codimpuesto = $tax->codimpuesto;
        $product->nostock = true;
        $this->assertTrue($product->save(), 'cant-save-product');

        // serie propia para aislar la factura en el informe
        $serie = $this->getRandomSerie();
        $this->assertTrue($serie->save(), 'cant-save-serie');

        // cliente + factura
        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'cant-save-customer');
        $invoice = new FacturaCliente();
        $invoice->setSubject($customer);
        $invoice->codserie = $serie->codserie;
        $this->assertTrue($invoice->save(), 'cant-save-invoice');

        // dos líneas al 0%: una exenta (art. 20) y otra no sujeta (art. 7)
        $lines = [];
        foreach ([[100, TaxExceptions::ES_TAX_EXCEPTION_20], [50, TaxExceptions::ES_TAX_EXCEPTION_7]] as [$price, $exception]) {
            $line = $invoice->getNewProductLine($product->referencia);
            $line->cantidad = 1;
            $line->pvpunitario = $price;
            $line->excepcioniva = $exception;
            $this->assertTrue($line->save(), 'cant-save-line');
            $lines[] = $line;
        }
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'cant-calculate');

        $data = $this->fetchSalesReport($invoice->idempresa, $invoice->coddivisa, $serie->codserie);

        // cada excepción va en su propia fila, aunque compartan factura y tipo de IVA
        $this->assertCount(2, $data, 'lines-not-split-by-vat-exception');
        $netByException = array_column($data, 'neto', 'excepcioniva');
        $this->assertEqualsWithDelta(100.0, $netByException[TaxExceptions::ES_TAX_EXCEPTION_20] ?? 0.0, 0.001, 'bad-exempt-net');
        $this->assertEqualsWithDelta(50.0, $netByException[TaxExceptions::ES_TAX_EXCEPTION_7] ?? 0.0, 0.001, 'bad-not-subject-net');

        // limpieza
        $this->assertTrue($invoice->delete(), 'cant-delete-invoice');
        $this->assertTrue($serie->delete(), 'cant-delete-serie');
        $this->assertTrue($product->delete(), 'cant-delete-product');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'cant-delete-contact');
        $this->assertTrue($customer->delete(), 'cant-delete-customer');
    }

    public function testSalesTravelAgencyMarginVat(): void
    {
        $tax = Impuestos::get('IVA21');
        if (false === $tax->exists()) {
            $this->markTestSkipped('IVA21-not-found');
        }

        // producto normal (en agencia de viajes el margen aplica a todas las líneas)
        $product = $this->getRandomProduct();
        $product->codimpuesto = $tax->codimpuesto;
        $product->nostock = true;
        $this->assertTrue($product->save(), 'cant-save-product');

        // empresa en régimen de agencia de viajes
        $invoice = new FacturaCliente();
        $company = $invoice->getCompany();
        $originalRegimen = $company->regimeniva;
        $company->regimeniva = RegimenIVA::TAX_SYSTEM_TRAVEL;
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

        // línea: pvp 200, coste 120, margen 80 -> IVA 21% de 80 = 16.8
        $line = $invoice->getNewProductLine($product->referencia);
        $line->cantidad = 1;
        $line->pvpunitario = 200;
        $line->coste = 120;
        $this->assertTrue($line->save(), 'cant-save-line');
        $lines = [$line];
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'cant-calculate');

        // sanity: la cabecera guarda el IVA sobre el margen
        $this->assertEqualsWithDelta(200.0, $invoice->neto, 0.001, 'bad-invoice-neto');
        $this->assertEqualsWithDelta(16.8, $invoice->totaliva, 0.001, 'bad-invoice-totaliva');

        $data = $this->fetchSalesReport($company->idempresa, $invoice->coddivisa, $serie->codserie);
        $this->assertNotEmpty($data, 'report-data-empty');

        // agregados: IVA sobre el margen (16.8), no sobre el total (42)
        $reportNeto = $reportIva = 0.0;
        foreach ($data as $row) {
            $reportNeto += $row['neto'];
            $reportIva += $row['totaliva'];
        }
        $this->assertEqualsWithDelta(200.0, $reportNeto, 0.001, 'bad-report-neto');
        $this->assertEqualsWithDelta(16.8, $reportIva, 0.001, 'report-iva-should-be-over-margin');

        // desglose fiel: el coste va al 0% y el margen al tipo de IVA
        $this->assertTrue($this->hasRate($data, 0.0, ['neto' => 120.0]), 'missing-cost-at-zero-rate');
        $this->assertTrue($this->hasRate($data, 21.0, ['neto' => 80.0, 'totaliva' => 16.8]), 'missing-margin-at-tax-rate');

        // limpieza
        $company->regimeniva = $originalRegimen;
        $this->assertTrue($company->save(), 'cant-restore-company');
        $this->assertTrue($invoice->delete(), 'cant-delete-invoice');
        $this->assertTrue($serie->delete(), 'cant-delete-serie');
        $this->assertTrue($product->delete(), 'cant-delete-product');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'cant-delete-contact');
        $this->assertTrue($customer->delete(), 'cant-delete-customer');
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
        $data = $this->fetchSalesReport($company->idempresa, $invoice->coddivisa, $serie->codserie);
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
     * Comprueba que una compra con autorepercusión (adquisición intracomunitaria de bienes,
     * servicios intracomunitarios o inversión del sujeto pasivo) aparece en el informe con su
     * tipo de IVA pero sin cuota, igual que la cabecera de la factura.
     */
    private function assertPurchaseReverseCharge(string $operacion): void
    {
        $tax = Impuestos::get('IVA21');
        if (false === $tax->exists()) {
            $this->markTestSkipped('IVA21-not-found');
        }

        // el cálculo especial de estas operaciones solo aplica a empresas españolas
        $invoice = new FacturaProveedor();
        $company = $invoice->getCompany();
        if ($company->codpais !== 'ESP') {
            $this->markTestSkipped('company-is-not-spanish');
        }

        // producto sin control de stock para no alterar el inventario
        $product = $this->getRandomProduct();
        $product->codimpuesto = $tax->codimpuesto;
        $product->nostock = true;
        $this->assertTrue($product->save(), 'cant-save-product');

        // serie propia para aislar la factura en el informe
        $serie = $this->getRandomSerie();
        $this->assertTrue($serie->save(), 'cant-save-serie');

        // proveedor + factura con la operación a comprobar
        $supplier = $this->getRandomSupplier();
        $this->assertTrue($supplier->save(), 'cant-save-supplier');
        $invoice->setSubject($supplier);
        $invoice->codserie = $serie->codserie;
        $invoice->operacion = $operacion;
        $this->assertTrue($invoice->save(), 'cant-save-invoice');

        // línea: 1 x 1000 al 21% -> la línea conserva el IVA, la cabecera no
        $line = $invoice->getNewProductLine($product->referencia);
        $line->cantidad = 1;
        $line->pvpunitario = 1000;
        $this->assertTrue($line->save(), 'cant-save-line');
        $lines = [$line];
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'cant-calculate');

        // sanity: la línea mantiene el 21% pero la cabecera no tiene cuota de IVA
        $this->assertEqualsWithDelta(21.0, (float)$line->iva, 0.001, 'bad-line-iva');
        $this->assertEqualsWithDelta(1000.0, $invoice->neto, 0.001, 'bad-invoice-neto');
        $this->assertEqualsWithDelta(0.0, $invoice->totaliva, 0.001, 'bad-invoice-totaliva');

        $data = $this->fetchPurchasesReport($company->idempresa, $invoice->coddivisa, $serie->codserie);
        $this->assertNotEmpty($data, 'report-data-empty');

        // el informe muestra el neto y el tipo de IVA, pero la cuota y el recargo a cero
        $this->assertTrue(
            $this->hasRate($data, 21.0, ['neto' => 1000.0, 'totaliva' => 0.0, 'totalrecargo' => 0.0]),
            'report-tax-should-be-zero-for-' . $operacion
        );

        // la fila lleva la operación y la cuota autorepercutida informativa (21% de 1000)
        $this->assertTrue(
            $this->hasRate($data, 21.0, ['neto' => 1000.0, 'totalivaauto' => 210.0]),
            'report-self-assessed-tax-wrong-for-' . $operacion
        );
        $this->assertSame([$operacion], array_values(array_unique(array_column($data, 'operacion'))));
        $this->assertSame(
            [TaxExceptions::ES_TAX_EXCEPTION_84],
            array_values(array_unique(array_column($data, 'excepcioniva'))),
            'report-tax-exception-wrong-for-' . $operacion
        );

        // limpieza
        $this->assertTrue($invoice->delete(), 'cant-delete-invoice');
        $this->assertTrue($serie->delete(), 'cant-delete-serie');
        $this->assertTrue($product->delete(), 'cant-delete-product');
        $this->assertTrue($supplier->getDefaultAddress()->delete(), 'cant-delete-contact');
        $this->assertTrue($supplier->delete(), 'cant-delete-supplier');
    }

    /**
     * Ejecuta getReportData() del controlador de impuestos para compras del año en curso,
     * filtrando por la serie dada para aislar la factura de prueba.
     */
    private function fetchPurchasesReport(int $idempresa, string $coddivisa, string $codserie): array
    {
        $report = new class ('ReportTaxes') extends ReportTaxes {
            public function fetchReportData(): array
            {
                return $this->getReportData();
            }
        };
        $report->source = 'purchases';
        $report->idempresa = $idempresa;
        $report->coddivisa = $coddivisa;
        $report->codserie = $codserie;
        $report->codpais = '';
        $report->typeDate = 'create';
        $report->datefrom = date('Y-01-01');
        $report->dateto = date('Y-12-31');

        return $report->fetchReportData();
    }

    /**
     * Ejecuta getReportData() del controlador de impuestos para ventas del año en curso,
     * filtrando por la serie dada para aislar la factura de prueba.
     */
    private function fetchSalesReport(int $idempresa, string $coddivisa, string $codserie): array
    {
        $report = new class ('ReportTaxes') extends ReportTaxes {
            public function fetchReportData(): array
            {
                return $this->getReportData();
            }
        };
        $report->source = 'sales';
        $report->idempresa = $idempresa;
        $report->coddivisa = $coddivisa;
        $report->codserie = $codserie;
        $report->codpais = '';
        $report->typeDate = 'create';
        $report->datefrom = date('Y-01-01');
        $report->dateto = date('Y-12-31');

        return $report->fetchReportData();
    }

    private function getExportRow(
        float $iva,
        float $neto,
        float $totalIva,
        string $codigo = 'FAC-001',
        float $total = 176.0,
        ?string $operacion = null,
        float $totalIvaAuto = 0.0,
        ?string $excepcionIva = null
    ): array {
        return [
            'codserie' => 'A',
            'codigo' => $codigo,
            'numero2' => '',
            'numproveedor' => '',
            'fecha' => date('Y-m-d'),
            'nombre' => 'Test customer',
            'cifnif' => '12345678Z',
            'codpago' => null,
            'codsubcuenta' => null,
            'ciudad' => null,
            'provincia' => null,
            'codpostal' => null,
            'codpais' => null,
            'operacion' => $operacion,
            'excepcioniva' => $excepcionIva,
            'neto' => $neto,
            'iva' => $iva,
            'totaliva' => $totalIva,
            'recargo' => 0.0,
            'totalrecargo' => 0.0,
            'irpf' => 0.0,
            'totalirpf' => 0.0,
            'suplidos' => 0.0,
            'totalivaauto' => $totalIvaAuto,
            'total' => $total
        ];
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

    /**
     * Ejecuta exportAction() con los datos dados, sin consultar la base de datos ni generar el documento.
     * Devuelve las líneas del detalle (reducidas a las columnas pedidas) y las tablas de totales.
     */
    private function runExport(array $data, string $format, string $source, array $columns): array
    {
        $report = new class ('ReportTaxes') extends ReportTaxes {
            public array $exportedLines = [];
            public array $exportedTotals = [];
            public array $reportData = [];

            public function exportReport(array $data, string $format, string $source, array $columns): void
            {
                $this->reportData = $data;
                $this->format = $format;
                $this->source = $source;
                $this->columns = $columns;
                $this->exportAction();
            }

            protected function getReportData(): array
            {
                return $this->reportData;
            }

            protected function processLayout(array &$lines, array &$totals): void
            {
                $this->reduceLines($lines);
                $this->exportedLines = $lines;
                $this->exportedTotals = $totals;
            }

            protected function validateTotals(array $totalsData): bool
            {
                return true;
            }
        };
        $report->exportReport($data, $format, $source, $columns);

        return [$report->exportedLines, $report->exportedTotals];
    }

    protected function setUp(): void
    {
        // los regímenes y operaciones que se comprueban aquí son específicos de España
        if (Tools::config('codpais') !== 'ESP') {
            $this->markTestSkipped('country-is-not-spain');
        }
    }
}
