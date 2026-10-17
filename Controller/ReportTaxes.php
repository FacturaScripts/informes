<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2021-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\DataSrc\Divisas;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Response;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Lib\ProductType;
use FacturaScripts\Core\Lib\RegimenIVA;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Lib\TaxExceptions;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Dinamic\Model\User;

/**
 * Description of ReportTaxes
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ReportTaxes extends Controller
{
    const MAX_TOTAL_DIFF = 0.05;

    /** @var string */
    public $coddivisa;

    /** @var string */
    public $codpais;

    /** @var string */
    public $codserie;

    /** @var string */
    public $datefrom;

    /** @var string */
    public $dateto;

    /** @var Divisa */
    public $divisa;

    /** @var string */
    public $format;

    /** @var int */
    public $idempresa;

    /** @var Pais */
    public $pais;

    /** @var Serie */
    public $serie;

    /** @var string */
    public $source;

    /** @var string */
    public $typeDate;

    /** @var array */
    protected $calculatedByInvoice = [];

    /** @var array */
    protected $columns = [];

    /** @var Empresa|null */
    protected $reportCompany;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'taxes';
        $data['menu'] = 'reports';
        $data['icon'] = 'fa-solid fa-wallet';
        return $data;
    }

    /**
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->divisa = new Divisa();
        $this->pais = new Pais();
        $this->serie = new Serie();
        $this->initFilters();
        $this->initColumns();

        if ('export' === $this->request->input('action')) {
            $this->exportAction();
        }
    }

    /**
     * Acumula un conjunto de importes en $data bajo la clave $code, creando la fila si no existe.
     */
    protected function addAmounts(array &$data, string $code, array $row, array $amounts): void
    {
        if (isset($data[$code])) {
            $data[$code]['neto'] += $amounts['neto'];
            $data[$code]['totaliva'] += $amounts['totaliva'];
            $data[$code]['totalrecargo'] += $amounts['totalrecargo'];
            $data[$code]['totalirpf'] += $amounts['totalirpf'];
            $data[$code]['suplidos'] += $amounts['suplidos'];
            $data[$code]['totalivaauto'] += $amounts['totalivaauto'];
            return;
        }

        $data[$code] = [
            'ciudad' => $row['ciudad'] ?? null,
            'provincia' => $row['provincia'] ?? null,
            'codpostal' => $row['codpostal'] ?? null,
            'codpais' => $row['codpais'] ?? null,
            'codserie' => $row['codserie'],
            'codigo' => $row['codigo'],
            'numero2' => $row['numero2'] ?? null,
            'numproveedor' => $row['numproveedor'] ?? null,
            'fecha' => $this->typeDate == 'create' ?
                $row['fecha'] :
                $row['fechadevengo'] ?? $row['fecha'],
            'nombre' => $row['nombre'],
            'codsubcuenta' => $row['codsubcuenta'],
            'cifnif' => $row['cifnif'],
            'codpago' => $row['codpago'] ?? null,
            'operacion' => $row['operacion'] ?? null,
            'excepcioniva' => $row['excepcioniva'] ?? null,
            'neto' => $amounts['neto'],
            'iva' => $amounts['iva'],
            'totaliva' => $amounts['totaliva'],
            'recargo' => $amounts['recargo'],
            'totalrecargo' => $amounts['totalrecargo'],
            'irpf' => $amounts['irpf'],
            'totalirpf' => $amounts['totalirpf'],
            'suplidos' => $amounts['suplidos'],
            'totalivaauto' => $amounts['totalivaauto']
        ];
    }

    /**
     * Desdobla una línea de venta en régimen de margen (bienes usados o agencia de viajes)
     * en dos tramos: el coste al 0% y el margen al tipo de IVA de la línea, replicando el
     * cálculo de la cabecera (CalculatorModSpain::applyUsedGoods / applyTravel).
     */
    protected function addMarginRegimeAmounts(array &$data, array $row, float $pvpTotal): void
    {
        $totalCoste = (float)$row['cantidad'] * (float)$row['coste'];
        $margin = $pvpTotal - $totalCoste;

        // solo se repercute IVA si hay margen positivo o es una factura rectificativa
        $applyIva = $margin > 0 || ($row['serie_tipo'] ?? '') === 'R';

        // tramo del coste al 0%
        if (abs($totalCoste) > 0.0) {
            $codeCost = $row['codigo'] . '-0-0-0-' . $row['suplido'] . '-' . $row['excepcioniva'];
            $this->addAmounts($data, $codeCost, $row, [
                'iva' => 0.0,
                'recargo' => 0.0,
                'irpf' => 0.0,
                'neto' => $totalCoste,
                'totaliva' => 0.0,
                'totalrecargo' => 0.0,
                'totalirpf' => 0.0,
                'suplidos' => 0.0,
                'totalivaauto' => 0.0
            ]);
        }

        // tramo del margen al tipo de IVA de la línea (sin recargo, igual que el calculador)
        $codeMargin = $row['codigo'] . '-' . $row['iva'] . '-' . $row['recargo'] . '-' . $row['irpf'] . '-' . $row['suplido']
            . '-' . $row['excepcioniva'];
        $this->addAmounts($data, $codeMargin, $row, [
            'iva' => (float)$row['iva'],
            'recargo' => (float)$row['recargo'],
            'irpf' => (float)$row['irpf'],
            'neto' => $margin,
            'totaliva' => $applyIva ? (float)$row['iva'] * $margin / 100 : 0.0,
            'totalrecargo' => 0.0,
            'totalirpf' => (float)$row['irpf'] * $margin / 100,
            'suplidos' => 0.0,
            'totalivaauto' => 0.0
        ]);
    }

    /**
     * Construye un mapa de datos calculados agregados por factura desde los datos del reporte
     *
     * @param array $reportData Datos del reporte obtenidos de getReportData()
     * @return void
     */
    protected function buildCalculatedByInvoiceMap(array $reportData): void
    {
        $this->calculatedByInvoice = [];
        foreach ($reportData as $row) {
            $codigo = $row['codigo'];
            if (!isset($this->calculatedByInvoice[$codigo])) {
                $this->calculatedByInvoice[$codigo] = [
                    'neto' => 0.0,
                    'totaliva' => 0.0,
                    'totalrecargo' => 0.0
                ];
            }
            $this->calculatedByInvoice[$codigo]['neto'] += $row['neto'];
            $this->calculatedByInvoice[$codigo]['totaliva'] += $row['totaliva'];
            $this->calculatedByInvoice[$codigo]['totalrecargo'] += $row['totalrecargo'];
        }
    }

    protected function exportAction(): void
    {
        // si no hay columnas, terminamos
        if (empty($this->columns)) {
            return;
        }

        // si no hay datos, terminamos
        $data = $this->getReportData();
        if (empty($data)) {
            Tools::log()->warning('no-data');
            return;
        }

        // Construir el mapa de datos calculados por factura una sola vez
        $this->buildCalculatedByInvoiceMap($data);

        // preparamos las líneas
        $lastCode = '';
        $lines = [];
        foreach ($data as $row) {
            $hide = $row['codigo'] === $lastCode;

            // en ventas usamos la columna numero2, en compras numproveedor
            if ($this->source === 'sales') {
                $number2title = Tools::trans('number2');
                $number2value = $hide ? '' : $row['numero2'];
            } else {
                $number2title = Tools::trans('numsupplier');
                $number2value = $hide ? '' : $row['numproveedor'];
            }

            $lines[] = [
                Tools::trans('serie') => $hide ? '' : $row['codserie'],
                Tools::trans('code') => $hide ? '' : $row['codigo'],
                $number2title => $number2value,
                Tools::trans('date') => $hide ? '' : Tools::date($row['fecha']),
                Tools::trans('name') => $hide ? '' : Tools::fixHtml($row['nombre']),
                Tools::trans('cifnif') => $hide ? '' : $row['cifnif'],
                Tools::trans('payment-method') => $hide ? '' : $row['codpago'] ?? '',
                Tools::trans('operation') => $hide ? '' : $this->getOperationName($row['operacion']),
                Tools::trans('subaccount') => $hide ? '' : $row['codsubcuenta'] ?? '',
                Tools::trans('city') => $hide ? '' : $row['ciudad'] ?? '',
                Tools::trans('province') => $hide ? '' : $row['provincia'] ?? '',
                Tools::trans('zip-code') => $hide ? '' : $row['codpostal'] ?? '',
                Tools::trans('country') => $hide ? '' : ($row['codpais'] ? Paises::get($row['codpais'])->nombre : ''),
                Tools::trans('net') => $this->exportFieldFormat('number', $row['neto']),
                Tools::trans('vat-exception') => $this->getTaxExceptionLabel($row['excepcioniva']),
                Tools::trans('pct-tax') => $this->exportFieldFormat('number', $row['iva']),
                Tools::trans('tax') => $this->exportFieldFormat('number', $row['totaliva']),
                Tools::trans('self-assessed-tax') => $this->exportFieldFormat('number', $row['totalivaauto']),
                Tools::trans('pct-surcharge') => $this->exportFieldFormat('number', $row['recargo']),
                Tools::trans('surcharge') => $this->exportFieldFormat('number', $row['totalrecargo']),
                Tools::trans('pct-irpf') => $this->exportFieldFormat('number', $row['irpf']),
                Tools::trans('irpf') => $this->exportFieldFormat('number', $row['totalirpf']),
                Tools::trans('supplied-amount') => $this->exportFieldFormat('number', $row['suplidos']),
                Tools::trans('total') => $this->exportFieldFormat('number', $this->getRowTotal($row))
            ];

            $lastCode = $row['codigo'];
        }

        $totalsData = $this->getTotals($data);
        if (false === $this->validateTotals($totalsData)) {
            return;
        }

        // en PDF separamos los totales en una tabla por impuesto; en CSV y Excel mantenemos la tabla plana
        $totals = $this->format === 'PDF' ?
            $this->getPdfTotalTables($totalsData) :
            [['title' => Tools::trans('totals'), 'rows' => $this->getFlatTotalRows($totalsData)]];

        $this->setTemplate(false);
        $this->processLayout($lines, $totals);
    }

    protected function exportFieldFormat(string $format, $value): string
    {
        return match ($format) {
            'number' => $this->format === 'PDF' ? Tools::number($value) : $value,
            'percentage' => $this->format === 'PDF' ? Tools::number($value) . ' %' : $value,
            default => $value,
        };
    }

    /**
     * Encuentra las facturas con posibles diferencias y genera los mensajes de error
     * Hace una sola consulta SQL independientemente de cuántos campos tengan diferencias
     *
     * @param array $fieldsWithDifferences Array con los campos que tienen diferencias: ['neto', 'totaliva', 'totalrecargo']
     * @param array $differences Array con los valores: ['neto' => ['calculated' => X, 'database' => Y], ...]
     * @return bool Siempre retorna false ya que hay diferencias
     */
    protected function findProblematicInvoices(array $fieldsWithDifferences, array $differences): bool
    {
        $tableName = $this->source === 'sales' ? 'facturascli' : 'facturasprov';
        $columnDate = $this->typeDate === 'create' ? 'fecha' : 'COALESCE(fechadevengo, fecha)';

        // Construir la consulta una sola vez para obtener todos los campos necesarios
        $sql = 'SELECT codigo, neto, totaliva, totalrecargo FROM ' . $tableName
            . ' WHERE idempresa = ' . $this->dataBase->var2str($this->idempresa)
            . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
            . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
            . ' AND coddivisa = ' . $this->dataBase->var2str($this->coddivisa);

        if ($this->codserie) {
            $sql .= ' AND codserie = ' . $this->dataBase->var2str($this->codserie);
        }

        if ($this->codpais && $this->source === 'sales') {
            $sql .= ' AND codpais = ' . $this->dataBase->var2str($this->codpais);
        }

        $sql .= ' ORDER BY codigo ASC;';

        // Agrupar facturas problemáticas por campo
        $invoicesByField = [];
        foreach ($fieldsWithDifferences as $field) {
            $invoicesByField[$field] = [];
        }

        // Comparar datos de base de datos con datos calculados ya preconstruidos
        foreach ($this->dataBase->select($sql) as $row) {
            $codigo = $row['codigo'];
            foreach ($fieldsWithDifferences as $field) {
                $dbValue = (float)$row[$field];
                $calculatedValue = $this->calculatedByInvoice[$codigo][$field] ?? 0.0;

                if (abs($calculatedValue - $dbValue) > self::MAX_TOTAL_DIFF) {
                    $invoicesByField[$field][] = $codigo;
                }
            }
        }

        $printError = false;

        // Generar los mensajes de error para cada campo con diferencias
        foreach ($fieldsWithDifferences as $field) {
            $problematicInvoices = array_unique($invoicesByField[$field]);
            $invoiceList = implode(', ', $problematicInvoices);

            // Mapeo de campos a mensajes de error
            $errorMessages = [
                'neto' => [
                    'key' => 'calculated-net-diff',
                    'calculated' => '%net%',
                    'database' => '%net2%'
                ],
                'totaliva' => [
                    'key' => 'calculated-tax-diff',
                    'calculated' => '%tax%',
                    'database' => '%tax2%'
                ],
                'totalrecargo' => [
                    'key' => 'calculated-surcharge-diff',
                    'calculated' => '%surcharge%',
                    'database' => '%surcharge2%'
                ]
            ];

            if (isset($errorMessages[$field])) {
                $printError = true;
                $errorMsg = $errorMessages[$field];
                Tools::log()->warning($errorMsg['key'], [
                    $errorMsg['calculated'] => $differences[$field]['calculated'],
                    $errorMsg['database'] => $differences[$field]['database'],
                    '%invoices%' => $invoiceList
                ]);
            }
        }

        if ($printError) {
            Tools::log()->info('report-taxes-solutions');
        }

        return false;
    }

    /**
     * Empresa del informe, cargada una sola vez.
     */
    protected function getCompany(): Empresa
    {
        if (null === $this->reportCompany) {
            $this->reportCompany = new Empresa();
            $this->reportCompany->load($this->idempresa);
        }

        return $this->reportCompany;
    }

    /**
     * Régimen de IVA de la empresa del informe (cadena vacía si no aplica o no es de ventas).
     */
    protected function getCompanyRegimen(): string
    {
        if ($this->source !== 'sales') {
            return '';
        }

        return (string)$this->getCompany()->regimeniva;
    }

    /**
     * Totales en una sola tabla, agrupados por tipos, operación y excepción de IVA (CSV y Excel).
     */
    protected function getFlatTotalRows(array $totalsData): array
    {
        // las columnas de operación, excepción y cuota autorepercutida solo se añaden a los totales si hay datos
        $hasOperations = $hasExceptions = $hasSelfAssessed = false;
        foreach ($totalsData as $row) {
            $hasOperations = $hasOperations || !empty($row['operacion']);
            $hasExceptions = $hasExceptions || !empty($row['excepcioniva']);
            $hasSelfAssessed = $hasSelfAssessed || abs($row['totalivaauto']) > 0.0;
        }

        $totals = [];
        foreach ($totalsData as $row) {
            $item = [];
            if ($hasOperations) {
                $item[Tools::trans('operation')] = $this->getOperationName($row['operacion']);
            }

            if ($hasExceptions) {
                $item[Tools::trans('vat-exception')] = $this->getTaxExceptionLabel($row['excepcioniva']);
            }

            $item[Tools::trans('net')] = $this->exportFieldFormat('number', $row['neto']);
            $item[Tools::trans('pct-tax')] = $this->exportFieldFormat('percentage', $row['iva']);
            $item[Tools::trans('tax')] = $this->exportFieldFormat('number', $row['totaliva']);
            if ($hasSelfAssessed) {
                $item[Tools::trans('self-assessed-tax')] = $this->exportFieldFormat('number', $row['totalivaauto']);
            }

            $item[Tools::trans('pct-surcharge')] = $this->exportFieldFormat('percentage', $row['recargo']);
            $item[Tools::trans('surcharge')] = $this->exportFieldFormat('number', $row['totalrecargo']);
            $item[Tools::trans('pct-irpf')] = $this->exportFieldFormat('percentage', $row['irpf']);
            $item[Tools::trans('irpf')] = $this->exportFieldFormat('number', $row['totalirpf']);
            $item[Tools::trans('supplied-amount')] = $this->exportFieldFormat('number', $row['suplidos']);
            $item[Tools::trans('total')] = $this->exportFieldFormat('number', $this->getRowTotal($row));
            $totals[] = $item;
        }

        return $totals;
    }

    /**
     * Nombre traducido de la operación de la factura (cadena vacía si es una operación normal).
     */
    protected function getOperationName(?string $operacion): string
    {
        if (empty($operacion)) {
            return '';
        }

        return Tools::trans(InvoiceOperation::get($operacion) ?? $operacion);
    }

    /**
     * Filas de un impuesto con un único porcentaje (recargo o IRPF), agrupadas por ese porcentaje.
     */
    protected function getPdfRateRows(
        array $totalsData,
        string $pctField,
        string $amountField,
        string $pctTitle,
        string $amountTitle
    ): array {
        $groups = [];
        foreach ($totalsData as $row) {
            if (0.0 == $row[$pctField] && 0.0 == $row[$amountField]) {
                continue;
            }

            $code = (string)$row[$pctField];
            if (false === isset($groups[$code])) {
                $groups[$code] = ['neto' => 0.0, 'pct' => $row[$pctField], 'amount' => 0.0];
            }
            $groups[$code]['neto'] += $row['neto'];
            $groups[$code]['amount'] += $row[$amountField];
        }

        $rows = [];
        foreach ($groups as $group) {
            $rows[] = [
                Tools::trans('tax-base') => $this->exportFieldFormat('number', $group['neto']),
                Tools::trans($pctTitle) => $this->exportFieldFormat('percentage', $group['pct']),
                Tools::trans($amountTitle) => $this->exportFieldFormat('number', $group['amount'])
            ];
        }

        return $rows;
    }

    /**
     * Fila única con los importes totales del informe.
     */
    protected function getPdfSummaryRows(array $totalsData): array
    {
        $sum = ['neto' => 0.0, 'totaliva' => 0.0, 'totalivaauto' => 0.0, 'totalrecargo' => 0.0,
            'totalirpf' => 0.0, 'suplidos' => 0.0, 'total' => 0.0];
        foreach ($totalsData as $row) {
            foreach (array_keys($sum) as $field) {
                $sum[$field] += $field === 'total' ? $this->getRowTotal($row) : $row[$field];
            }
        }

        // las columnas opcionales solo se muestran si tienen importes
        $item = [
            Tools::trans('net') => $this->exportFieldFormat('number', $sum['neto']),
            Tools::trans('tax') => $this->exportFieldFormat('number', $sum['totaliva'])
        ];
        $optional = [
            'totalivaauto' => 'self-assessed-tax',
            'totalrecargo' => 'surcharge',
            'totalirpf' => 'irpf',
            'suplidos' => 'supplied-amount'
        ];
        foreach ($optional as $field => $title) {
            if (abs($sum[$field]) > 0.0) {
                $item[Tools::trans($title)] = $this->exportFieldFormat('number', $sum[$field]);
            }
        }
        $item[Tools::trans('total')] = $this->exportFieldFormat('number', $sum['total']);

        return [$item];
    }

    /**
     * Leyenda con el código y la descripción de las excepciones de IVA que aparecen en el informe.
     */
    protected function getPdfTaxExceptionLegendRows(array $totalsData): array
    {
        $exceptions = array_unique(array_filter(array_column($totalsData, 'excepcioniva')));
        sort($exceptions);

        $rows = [];
        foreach ($exceptions as $exception) {
            $rows[] = [
                Tools::trans('code') => $exception,
                Tools::trans('description') => $this->getTaxExceptionName($exception)
            ];
        }

        return $rows;
    }

    /**
     * Totales para PDF: una tabla por impuesto (IVA, recargo e IRPF), cada una solo si tiene importes,
     * un resumen general y la leyenda de las excepciones de IVA usadas.
     *
     * @return array lista de tablas con 'title' y 'rows'
     */
    protected function getPdfTotalTables(array $totalsData): array
    {
        $tables = [];

        $vatRows = $this->getPdfVatRows($totalsData);
        if (!empty($vatRows)) {
            $tables[] = ['title' => Tools::trans('vat'), 'rows' => $vatRows];
        }

        $surchargeRows = $this->getPdfRateRows($totalsData, 'recargo', 'totalrecargo', 'pct-surcharge', 'surcharge');
        if (!empty($surchargeRows)) {
            $tables[] = ['title' => Tools::trans('surcharge'), 'rows' => $surchargeRows];
        }

        $irpfRows = $this->getPdfRateRows($totalsData, 'irpf', 'totalirpf', 'pct-irpf', 'retention');
        if (!empty($irpfRows)) {
            $tables[] = ['title' => Tools::trans('irpf'), 'rows' => $irpfRows];
        }

        $tables[] = ['title' => Tools::trans('summary'), 'rows' => $this->getPdfSummaryRows($totalsData)];

        $legendRows = $this->getPdfTaxExceptionLegendRows($totalsData);
        if (!empty($legendRows)) {
            $tables[] = ['title' => Tools::trans('tax-exceptions'), 'rows' => $legendRows];
        }

        return $tables;
    }

    /**
     * Filas de IVA agrupadas por tipo, operación y excepción. Se omiten las filas sin base ni cuotas
     * (por ejemplo, las de solo suplidos).
     */
    protected function getPdfVatRows(array $totalsData): array
    {
        $groups = [];
        $hasOperations = $hasExceptions = $hasSelfAssessed = false;
        foreach ($totalsData as $row) {
            if (0.0 == $row['neto'] && 0.0 == $row['totaliva'] && 0.0 == $row['totalivaauto']) {
                continue;
            }

            $code = $row['iva'] . '-' . $row['operacion'] . '-' . $row['excepcioniva'];
            if (false === isset($groups[$code])) {
                $groups[$code] = [
                    'operacion' => $row['operacion'],
                    'excepcioniva' => $row['excepcioniva'],
                    'iva' => $row['iva'],
                    'neto' => 0.0,
                    'totaliva' => 0.0,
                    'totalivaauto' => 0.0
                ];
            }
            $groups[$code]['neto'] += $row['neto'];
            $groups[$code]['totaliva'] += $row['totaliva'];
            $groups[$code]['totalivaauto'] += $row['totalivaauto'];

            $hasOperations = $hasOperations || !empty($row['operacion']);
            $hasExceptions = $hasExceptions || !empty($row['excepcioniva']);
            $hasSelfAssessed = $hasSelfAssessed || abs($row['totalivaauto']) > 0.0;
        }

        $rows = [];
        foreach ($groups as $group) {
            $item = [];
            if ($hasOperations) {
                $item[Tools::trans('operation')] = $this->getOperationName($group['operacion']);
            }

            if ($hasExceptions) {
                $item[Tools::trans('vat-exception')] = $this->getTaxExceptionLabel($group['excepcioniva']);
            }

            $item[Tools::trans('tax-base')] = $this->exportFieldFormat('number', $group['neto']);
            $item[Tools::trans('pct-tax')] = $this->exportFieldFormat('percentage', $group['iva']);
            $item[Tools::trans('tax')] = $this->exportFieldFormat('number', $group['totaliva']);
            if ($hasSelfAssessed) {
                $item[Tools::trans('self-assessed-tax')] = $this->exportFieldFormat('number', $group['totalivaauto']);
            }
            $rows[] = $item;
        }

        return $rows;
    }

    protected function getQuarterDate(bool $start): string
    {
        $month = (int)date('m');

        // si la fecha actual es de enero, seleccionamos el trimestre anterior
        if ($month === 1) {
            return $start ?
                date('Y-10-01', strtotime('-1 year')) :
                date('Y-12-31', strtotime('-1 year'));
        }

        // comprobamos si la fecha actual está en el primer trimestre o justo en el siguiente mes
        if ($month >= 1 && $month <= 4) {
            return $start ? date('Y-01-01') : date('Y-03-31');
        }

        // comprobamos si la fecha actual está en el segundo trimestre o justo en el siguiente mes
        if ($month >= 4 && $month <= 7) {
            return $start ? date('Y-04-01') : date('Y-06-30');
        }

        // comprobamos si la fecha actual está en el tercer trimestre o justo en el siguiente mes
        if ($month >= 7 && $month <= 10) {
            return $start ? date('Y-07-01') : date('Y-09-30');
        }

        // la fecha actual está en el cuarto trimestre
        return $start ? date('Y-10-01') : date('Y-12-31');
    }

    protected function getReportData(): array
    {
        $sql = '';
        $db_type = Tools::config('db_type');
        $numCol = strtolower($db_type) == 'postgresql' ? 'CAST(f.numero as integer)' : 'CAST(f.numero as unsigned)';
        $columnDate = $this->typeDate === 'create' ? 'f.fecha' : 'COALESCE(f.fechadevengo, f.fecha)';
        switch ($this->source) {
            case 'purchases':
                $sql .= 'SELECT f.codserie, f.codigo, f.numproveedor, f.fecha, f.fechadevengo, f.nombre, f.cifnif, l.pvptotal,'
                    . ' l.iva, l.recargo, l.irpf, l.suplido, l.excepcioniva, f.dtopor1, f.dtopor2, f.total, f.operacion, f.codpago, pr.codsubcuenta,'
                    . ' c.ciudad, c.provincia, c.codpostal, c.codpais'
                    . ' FROM lineasfacturasprov AS l'
                    . ' LEFT JOIN facturasprov AS f ON l.idfactura = f.idfactura '
                    . ' LEFT JOIN proveedores AS pr ON f.codproveedor = pr.codproveedor'
                    . ' LEFT JOIN contactos AS c ON pr.idcontacto = c.idcontacto'
                    . ' WHERE f.idempresa = ' . $this->dataBase->var2str($this->idempresa)
                    . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
                    . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
                    . ' AND (l.pvptotal <> 0.00 OR l.iva <> 0.00)'
                    . ' AND f.coddivisa = ' . $this->dataBase->var2str($this->coddivisa);
                break;

            case 'sales':
                $sql .= 'SELECT f.codserie, f.codigo, f.numero2, f.fecha, f.fechadevengo, f.nombrecliente AS nombre, f.cifnif, l.pvptotal,'
                    . ' l.iva, l.recargo, l.irpf, l.suplido, l.excepcioniva, l.cantidad, l.coste, f.dtopor1, f.dtopor2, f.total, f.operacion, f.codpago, f.codpais, cl.codsubcuenta,'
                    . ' f.ciudad, f.provincia, f.codpostal, p.tipo AS producto_tipo, se.tipo AS serie_tipo'
                    . ' FROM lineasfacturascli AS l'
                    . ' LEFT JOIN facturascli AS f ON l.idfactura = f.idfactura '
                    . ' LEFT JOIN clientes AS cl ON f.codcliente = cl.codcliente'
                    . ' LEFT JOIN productos AS p ON l.referencia = p.referencia'
                    . ' LEFT JOIN series AS se ON f.codserie = se.codserie'
                    . ' WHERE f.idempresa = ' . $this->dataBase->var2str($this->idempresa)
                    . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
                    . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
                    . ' AND (l.pvptotal <> 0.00 OR l.iva <> 0.00)'
                    . ' AND f.coddivisa = ' . $this->dataBase->var2str($this->coddivisa);
                if ($this->codpais) {
                    $sql .= ' AND codpais = ' . $this->dataBase->var2str($this->codpais);
                }
                break;

            default:
                Tools::log()->warning('wrong-source');
                return [];
        }
        if ($this->codserie) {
            $sql .= ' AND f.codserie = ' . $this->dataBase->var2str($this->codserie);
        }
        $sql .= ' ORDER BY ' . $columnDate . ', ' . $numCol . ' ASC;';

        $companyRegimen = $this->getCompanyRegimen();

        $data = [];
        foreach ($this->dataBase->select($sql) as $row) {
            $pvpTotal = floatval($row['pvptotal']) * (100 - floatval($row['dtopor1'])) * (100 - floatval($row['dtopor2'])) / 10000;
            $row['suplido'] = filter_var($row['suplido'], FILTER_VALIDATE_BOOLEAN);

            // regímenes de margen (bienes usados REBU y agencias de viajes): el IVA va sobre el margen
            if ($this->isMarginRegimeSale($row, $companyRegimen)) {
                $this->addMarginRegimeAmounts($data, $row, $pvpTotal);
                continue;
            }

            // en las compras con autorepercusión la cuota de IVA y el recargo van a cero,
            // pero guardamos la cuota autorepercutida (devengada y deducible) como dato informativo
            $reverseCharge = $this->isReverseChargePurchase($row);
            $amounts = [
                'iva' => $row['suplido'] ? 0.0 : (float)$row['iva'],
                'recargo' => $row['suplido'] ? 0.0 : (float)$row['recargo'],
                'irpf' => $row['suplido'] ? 0.0 : (float)$row['irpf'],
                'neto' => $row['suplido'] ? 0.0 : $pvpTotal,
                'totaliva' => $row['suplido'] || $reverseCharge ? 0.0 : (float)$row['iva'] * $pvpTotal / 100,
                'totalrecargo' => $row['suplido'] || $reverseCharge ? 0.0 : (float)$row['recargo'] * $pvpTotal / 100,
                'totalirpf' => $row['suplido'] ? 0.0 : (float)$row['irpf'] * $pvpTotal / 100,
                'suplidos' => $row['suplido'] ? $pvpTotal : 0.0,
                'totalivaauto' => !$row['suplido'] && $reverseCharge ? (float)$row['iva'] * $pvpTotal / 100 : 0.0
            ];
            $code = $row['codigo'] . '-' . $row['iva'] . '-' . $row['recargo'] . '-' . $row['irpf'] . '-' . $row['suplido']
                . '-' . $row['excepcioniva'];
            $this->addAmounts($data, $code, $row, $amounts);
        }

        // round
        $nf0 = Tools::settings('default', 'decimals', 2);
        foreach ($data as $key => $value) {
            $data[$key]['neto'] = round($value['neto'], $nf0);
            $data[$key]['totaliva'] = round($value['totaliva'], $nf0);
            $data[$key]['totalrecargo'] = round($value['totalrecargo'], $nf0);
            $data[$key]['totalirpf'] = round($value['totalirpf'], $nf0);
            $data[$key]['suplidos'] = round($value['suplidos'], $nf0);
            $data[$key]['totalivaauto'] = round($value['totalivaauto'], $nf0);
        }

        return $data;
    }

    /**
     * Calcula el total de una fila del informe (grupo de impuesto de una factura),
     * a partir de sus propios importes, sin depender del total de cabecera de la factura.
     */
    protected function getRowTotal(array $row): float
    {
        return $row['neto'] + $row['totaliva'] + $row['totalrecargo'] - $row['totalirpf'] - $row['suplidos'];
    }

    /**
     * Texto de la excepción de IVA para el export: en PDF el código corto (la descripción va en la
     * leyenda), en el resto de formatos el nombre traducido.
     */
    protected function getTaxExceptionLabel(?string $excepcion): string
    {
        if ($this->format === 'PDF') {
            return (string)$excepcion;
        }

        return $this->getTaxExceptionName($excepcion);
    }

    /**
     * Nombre traducido de la excepción de IVA de la línea (cadena vacía si no tiene).
     */
    protected function getTaxExceptionName(?string $excepcion): string
    {
        if (empty($excepcion)) {
            return '';
        }

        return Tools::trans(TaxExceptions::get($excepcion) ?? $excepcion);
    }

    protected function getTotals(array $data): array
    {
        $totals = [];
        foreach ($data as $row) {
            // separamos por operación y excepción de IVA para no mezclar, por ejemplo, compras normales con
            // inversión del sujeto pasivo, o ventas exentas con ventas no sujetas
            $code = $row['iva'] . '-' . $row['recargo'] . '-' . $row['irpf'] . '-' . $row['operacion']
                . '-' . $row['excepcioniva'];
            if (isset($totals[$code])) {
                $totals[$code]['neto'] += $row['neto'];
                $totals[$code]['totaliva'] += $row['totaliva'];
                $totals[$code]['totalrecargo'] += $row['totalrecargo'];
                $totals[$code]['totalirpf'] += $row['totalirpf'];
                $totals[$code]['suplidos'] += $row['suplidos'];
                $totals[$code]['totalivaauto'] += $row['totalivaauto'];
                continue;
            }

            $totals[$code] = [
                'operacion' => $row['operacion'],
                'excepcioniva' => $row['excepcioniva'],
                'neto' => $row['neto'],
                'iva' => $row['iva'],
                'totaliva' => $row['totaliva'],
                'recargo' => $row['recargo'],
                'totalrecargo' => $row['totalrecargo'],
                'irpf' => $row['irpf'],
                'totalirpf' => $row['totalirpf'],
                'suplidos' => $row['suplidos'],
                'totalivaauto' => $row['totalivaauto']
            ];
        }

        return $totals;
    }

    protected function initColumns(): void
    {
        // recorremos los datos recibidos
        foreach ($this->request->request->all() as $key => $value) {
            // obtenemos la key sin el column_
            $column = substr($key, 7);

            // reemplazamos _ por -
            $column = str_replace('_', '-', $column);

            // traducimos la columna
            $column = Tools::trans($column);

            // si la key empieza por column_ y no está en el array de columnas, la añadimos
            if (str_starts_with($key, 'column_') && !in_array($column, $this->columns)) {
                $this->columns[] = $column;
            }
        }
    }

    protected function initFilters(): void
    {
        $this->coddivisa = $this->request->input(
            'coddivisa',
            Tools::settings('default', 'coddivisa')
        );

        $this->codpais = $this->request->input('codpais', '');
        $this->codserie = $this->request->input('codserie', '');
        $this->datefrom = $this->request->input('datefrom', $this->getQuarterDate(true));
        $this->dateto = $this->request->input('dateto', $this->getQuarterDate(false));

        $this->idempresa = (int)$this->request->input(
            'idempresa',
            Tools::settings('default', 'idempresa')
        );

        $this->format = $this->request->input('format');
        $this->source = $this->request->input('source');
        $this->typeDate = $this->request->input('type-date');
    }

    protected function isMarginRegimeSale(array $row, string $companyRegimen): bool
    {
        if ($this->source !== 'sales' || $row['suplido']) {
            return false;
        }

        // agencias de viajes: el IVA sobre el margen aplica a todas las líneas
        if ($companyRegimen === RegimenIVA::TAX_SYSTEM_TRAVEL) {
            return true;
        }

        // bienes usados (REBU): solo a los productos de segunda mano
        return $companyRegimen === RegimenIVA::TAX_SYSTEM_USED_GOODS
            && ($row['producto_tipo'] ?? '') === ProductType::SECOND_HAND;
    }

    /**
     * Indica si la fila corresponde a una compra con autorepercusión: adquisición intracomunitaria
     * de bienes, servicios intracomunitarios o inversión del sujeto pasivo.
     *
     * En estos casos CalculatorModSpain::updateSubtotals() deja la cuota de IVA y el recargo de la
     * cabecera a cero, aunque las líneas conserven su tipo impositivo, porque el IVA devengado y el
     * deducible se compensan. Replicamos ese criterio para que el informe cuadre con la factura.
     */
    protected function isReverseChargePurchase(array $row): bool
    {
        // el core solo anula la cuota de la cabecera en empresas españolas
        if ($this->source !== 'purchases' || $this->getCompany()->codpais !== 'ESP') {
            return false;
        }

        return in_array($row['operacion'], [
            InvoiceOperation::INTRA_COMMUNITY,
            InvoiceOperation::INTRA_COMMUNITY_SERVICES,
            InvoiceOperation::REVERSE_CHARGE
        ], true);
    }

    protected function processLayout(array &$lines, array &$totals): void
    {
        $exportManager = new ExportManager();
        $exportManager->setOrientation('landscape');
        $exportManager->newDoc($this->format, Tools::trans('taxes'));
        $exportManager->setCompany($this->idempresa);

        // en Excel el título da nombre a la hoja; en PDF se imprimiría como encabezado, así que no lo usamos
        $isExcel = $this->format === 'XLS';

        // add information table
        $exportManager->addTablePage(
            [
                Tools::trans('report'),
                Tools::trans('currency'),
                Tools::trans('date'),
                Tools::trans('from-date'),
                Tools::trans('until-date')
            ],
            [
                [
                    Tools::trans('report') => Tools::trans('taxes') . ' ' . Tools::trans($this->source),
                    Tools::trans('currency') => Divisas::get($this->coddivisa)->descripcion,
                    Tools::trans('date') => Tools::trans($this->typeDate === 'create' ? 'creation-date' : 'accrual-date'),
                    Tools::trans('from-date') => Tools::date($this->datefrom),
                    Tools::trans('until-date') => Tools::date($this->dateto)
                ]
            ],
            [],
            $isExcel ? Tools::trans('report') : ''
        );

        $options = [
            Tools::trans('net') => ['display' => 'right'],
            Tools::trans('tax-base') => ['display' => 'right'],
            Tools::trans('pct-tax') => ['display' => 'right'],
            Tools::trans('tax') => ['display' => 'right'],
            Tools::trans('self-assessed-tax') => ['display' => 'right'],
            Tools::trans('pct-surcharge') => ['display' => 'right'],
            Tools::trans('surcharge') => ['display' => 'right'],
            Tools::trans('pct-irpf') => ['display' => 'right'],
            Tools::trans('irpf') => ['display' => 'right'],
            Tools::trans('retention') => ['display' => 'right'],
            Tools::trans('supplied-amount') => ['display' => 'right'],
            Tools::trans('total') => ['display' => 'right']
        ];

        // add lines table
        $this->reduceLines($lines);
        $headers = empty($lines) ? [] : array_keys(end($lines));
        $exportManager->addTablePage($headers, $lines, $options, $isExcel ? Tools::trans('lines') : '');

        // add totals tables
        foreach ($totals as $table) {
            $headTotals = empty($table['rows']) ? [] : array_keys(end($table['rows']));
            $exportManager->addTablePage($headTotals, $table['rows'], $options, $table['title']);
        }

        // clear output buffer to avoid errors
        if (ob_get_length()) {
            ob_end_clean();
        }

        $exportManager->show($this->response);
    }

    protected function reduceLines(array &$lines): void
    {
        // recorremos las líneas y buscamos la key de cada línea en el array de columnas, si no existe, eliminamos la columna
        foreach ($lines as $key => $line) {
            foreach ($line as $column => $value) {
                if (!in_array($column, $this->columns)) {
                    unset($lines[$key][$column]);
                }
            }
        }
    }

    protected function validateTotals(array $totalsData): bool
    {
        // sum totals from the given data
        $neto = $totalIva = $totalRecargo = 0.0;
        foreach ($totalsData as $row) {
            $neto += $row['neto'];
            $totalIva += $row['totaliva'];
            $totalRecargo += $row['totalrecargo'];
        }

        // gets totals from the database
        $neto2 = $totalIva2 = $totalRecargo2 = 0.0;
        $tableName = $this->source === 'sales' ? 'facturascli' : 'facturasprov';
        $columnDate = $this->typeDate === 'create' ? 'fecha' : 'COALESCE(fechadevengo, fecha)';
        $sql = 'SELECT SUM(neto) as neto, SUM(totaliva) as t1, SUM(totalrecargo) as t2'
            . ' FROM ' . $tableName
            . ' WHERE idempresa = ' . $this->dataBase->var2str($this->idempresa)
            . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
            . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
            . ' AND coddivisa = ' . $this->dataBase->var2str($this->coddivisa);
        if ($this->codserie) {
            $sql .= ' AND codserie = ' . $this->dataBase->var2str($this->codserie);
        }
        if ($this->codpais && $this->source === 'sales') {
            $sql .= ' AND codpais = ' . $this->dataBase->var2str($this->codpais);
        }
        foreach ($this->dataBase->selectLimit($sql) as $row) {
            $neto2 += (float)$row['neto'];
            $totalIva2 += (float)$row['t1'];
            $totalRecargo2 += (float)$row['t2'];
        }

        // Determinar qué campos tienen diferencias
        $fieldsWithDifferences = [];
        $differences = [];

        if (abs($neto - $neto2) > self::MAX_TOTAL_DIFF) {
            $fieldsWithDifferences[] = 'neto';
            $differences['neto'] = ['calculated' => $neto, 'database' => $neto2];
        }

        if (abs($totalIva - $totalIva2) > self::MAX_TOTAL_DIFF) {
            $fieldsWithDifferences[] = 'totaliva';
            $differences['totaliva'] = ['calculated' => $totalIva, 'database' => $totalIva2];
        }

        if (abs($totalRecargo - $totalRecargo2) > self::MAX_TOTAL_DIFF) {
            $fieldsWithDifferences[] = 'totalrecargo';
            $differences['totalrecargo'] = ['calculated' => $totalRecargo, 'database' => $totalRecargo2];
        }

        // Si hay diferencias, llamar a findProblematicInvoices una sola vez
        if (!empty($fieldsWithDifferences)) {
            return $this->findProblematicInvoices($fieldsWithDifferences, $differences);
        }

        return true;
    }
}
