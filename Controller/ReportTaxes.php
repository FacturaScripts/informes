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
use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Response;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
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

    /** @var array */
    protected $columns = [];

    /** @var array */
    protected $calculatedByInvoice = [];

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

    protected function addAmountToRows(array &$rows, string $field, float $amount): void
    {
        if (empty($rows) || abs($amount) <= 0.0001) {
            return;
        }

        $weightedIndexes = [];
        $totalWeight = 0.0;
        foreach ($rows as $index => $row) {
            $weight = abs((float)$row['neto']);
            if ($weight <= 0.0) {
                continue;
            }

            $weightedIndexes[] = $index;
            $totalWeight += $weight;
        }

        if (empty($weightedIndexes) || $totalWeight <= 0.0) {
            $firstKey = array_key_first($rows);
            $rows[$firstKey][$field] = Tools::round($rows[$firstKey][$field] + $amount);
            return;
        }

        $remaining = $amount;
        $lastIndex = end($weightedIndexes);
        foreach ($weightedIndexes as $index) {
            if ($index === $lastIndex) {
                $rows[$index][$field] = Tools::round($rows[$index][$field] + $remaining);
                break;
            }

            $share = Tools::round($amount * abs((float)$rows[$index]['neto']) / $totalWeight);
            $rows[$index][$field] = Tools::round($rows[$index][$field] + $share);
            $remaining -= $share;
        }
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
            $hide = $row['codigo'] === $lastCode && $this->format === 'PDF';

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
                Tools::trans('subaccount') => $hide ? '' : $row['codsubcuenta'] ?? '',
                Tools::trans('city') => $hide ? '' : $row['ciudad'] ?? '',
                Tools::trans('province') => $hide ? '' : $row['provincia'] ?? '',
                Tools::trans('zip-code') => $hide ? '' : $row['codpostal'] ?? '',
                Tools::trans('country') => $hide ? '' : ($row['codpais'] ? Paises::get($row['codpais'])->nombre : ''),
                Tools::trans('net') => $this->exportFieldFormat('number', $row['neto']),
                Tools::trans('pct-tax') => $this->exportFieldFormat('number', $row['iva']),
                Tools::trans('tax') => $this->exportFieldFormat('number', $row['totaliva']),
                Tools::trans('pct-surcharge') => $this->exportFieldFormat('number', $row['recargo']),
                Tools::trans('surcharge') => $this->exportFieldFormat('number', $row['totalrecargo']),
                Tools::trans('pct-irpf') => $this->exportFieldFormat('number', $row['irpf']),
                Tools::trans('irpf') => $this->exportFieldFormat('number', $row['totalirpf']),
                Tools::trans('supplied-amount') => $this->exportFieldFormat('number', $row['suplidos']),
                Tools::trans('total') => $hide ? '' : $this->exportFieldFormat('number', $row['total'])
            ];

            $lastCode = $row['codigo'];
        }

        $totalsData = $this->getTotals($data);
        if (false === $this->validateTotals($totalsData)) {
            return;
        }

        // preparamos los totales
        $totals = [];
        foreach ($totalsData as $row) {
            $total = $row['neto'] + $row['totaliva'] + $row['totalrecargo'] - $row['totalirpf'] - $row['suplidos'];
            $totals[] = [
                Tools::trans('net') => $this->exportFieldFormat('number', $row['neto']),
                Tools::trans('pct-tax') => $this->exportFieldFormat('percentage', $row['iva']),
                Tools::trans('tax') => $this->exportFieldFormat('number', $row['totaliva']),
                Tools::trans('pct-surcharge') => $this->exportFieldFormat('percentage', $row['recargo']),
                Tools::trans('surcharge') => $this->exportFieldFormat('number', $row['totalrecargo']),
                Tools::trans('pct-irpf') => $this->exportFieldFormat('percentage', $row['irpf']),
                Tools::trans('irpf') => $this->exportFieldFormat('number', $row['totalirpf']),
                Tools::trans('supplied-amount') => $this->exportFieldFormat('number', $row['suplidos']),
                Tools::trans('total') => $this->exportFieldFormat('number', $total)
            ];
        }

        $this->setTemplate(false);
        $this->processLayout($lines, $totals);
    }

    protected function exportFieldFormat(string $format, string $value): string
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
        $data = [];
        foreach ($this->getReportInvoices() as $invoiceData) {
            $document = $this->getReportDocument();
            if (false === $document->load((int)$invoiceData['idfactura'])) {
                continue;
            }

            foreach ($this->getReportDataFromDocument($invoiceData, $document) as $row) {
                $data[] = $row;
            }
        }

        return $data;
    }

    protected function getReportDataFromDocument(array $invoiceData, BusinessDocument $document): array
    {
        $subtotals = Calculator::getSubtotals($document, $document->getLines());
        $rows = [];
        foreach ($subtotals['iva'] as $taxData) {
            $rows[] = $this->newReportDataRow(
                $invoiceData,
                $document,
                (float)$taxData['neto'],
                (float)$taxData['iva'],
                (float)$taxData['totaliva'],
                (float)$taxData['recargo'],
                (float)$taxData['totalrecargo']
            );
        }

        if (empty($rows) && abs((float)$subtotals['totalsuplidos']) > 0.0001) {
            $rows[] = $this->newReportDataRow($invoiceData, $document, 0.0, 0.0, 0.0, 0.0, 0.0);
        }

        $this->addAmountToRows($rows, 'totalirpf', (float)$subtotals['totalirpf']);
        $this->addAmountToRows($rows, 'suplidos', (float)$subtotals['totalsuplidos']);

        return $rows;
    }

    protected function getReportDocument()
    {
        return $this->source === 'sales' ? new FacturaCliente() : new FacturaProveedor();
    }

    protected function getReportInvoices(): array
    {
        $db_type = Tools::config('db_type');
        $numCol = strtolower($db_type) == 'postgresql' ? 'CAST(f.numero as integer)' : 'CAST(f.numero as unsigned)';
        $columnDate = $this->typeDate === 'create' ? 'f.fecha' : 'COALESCE(f.fechadevengo, f.fecha)';
        switch ($this->source) {
            case 'purchases':
                $sql .= 'SELECT f.codserie, f.codigo, f.numproveedor, f.fecha, f.fechadevengo, f.nombre, f.cifnif, l.pvptotal,'
                    . ' l.iva, l.recargo, l.irpf, l.suplido, f.dtopor1, f.dtopor2, f.total, f.operacion, pr.codsubcuenta,'
                    . ' c.ciudad, c.provincia, c.codpostal, c.codpais'
                    . ' FROM lineasfacturasprov AS l'
                    . ' LEFT JOIN facturasprov AS f ON l.idfactura = f.idfactura '
                    . ' LEFT JOIN proveedores AS pr ON f.codproveedor = pr.codproveedor'
                    . ' LEFT JOIN contactos AS c ON pr.idcontacto = c.idcontacto'
                    . ' WHERE f.idempresa = ' . $this->dataBase->var2str($this->idempresa)
                    . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
                    . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
                    . ' AND f.coddivisa = ' . $this->dataBase->var2str($this->coddivisa)
                    . ' AND EXISTS (SELECT 1 FROM lineasfacturasprov AS l'
                    . ' WHERE l.idfactura = f.idfactura AND (l.pvptotal <> 0.00 OR l.iva <> 0.00))';
                break;

            case 'sales':
                $sql = 'SELECT f.idfactura, f.codserie, f.codigo, f.numero2, f.fecha, f.fechadevengo, f.nombrecliente AS nombre,'
                    . ' f.cifnif, f.total, f.codpais, cl.codsubcuenta, f.ciudad, f.provincia, f.codpostal'
                    . ' FROM facturascli AS f'
                    . ' LEFT JOIN clientes AS cl ON f.codcliente = cl.codcliente'
                    . ' WHERE f.idempresa = ' . $this->dataBase->var2str($this->idempresa)
                    . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
                    . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
                    . ' AND f.coddivisa = ' . $this->dataBase->var2str($this->coddivisa)
                    . ' AND EXISTS (SELECT 1 FROM lineasfacturascli AS l'
                    . ' WHERE l.idfactura = f.idfactura AND (l.pvptotal <> 0.00 OR l.iva <> 0.00))';
                if ($this->codpais) {
                    $sql .= ' AND f.codpais = ' . $this->dataBase->var2str($this->codpais);
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
        return $this->dataBase->select($sql);
    }

    protected function getTotals(array $data): array
    {
        $totals = [];
        foreach ($data as $row) {
            $code = $row['iva'] . '-' . $row['recargo'] . '-' . $row['irpf'];
            if (isset($totals[$code])) {
                $totals[$code]['neto'] += $row['neto'];
                $totals[$code]['totaliva'] += $row['totaliva'];
                $totals[$code]['totalrecargo'] += $row['totalrecargo'];
                $totals[$code]['totalirpf'] += $row['totalirpf'];
                $totals[$code]['suplidos'] += $row['suplidos'];
                continue;
            }

            $totals[$code] = [
                'neto' => $row['neto'],
                'iva' => $row['iva'],
                'totaliva' => $row['totaliva'],
                'recargo' => $row['recargo'],
                'totalrecargo' => $row['totalrecargo'],
                'irpf' => $row['irpf'],
                'totalirpf' => $row['totalirpf'],
                'suplidos' => $row['suplidos']
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

    protected function newReportDataRow(
        array $invoiceData,
        BusinessDocument $document,
        float $neto,
        float $iva,
        float $totalIva,
        float $recargo,
        float $totalRecargo
    ): array {
        return [
            'ciudad' => $invoiceData['ciudad'] ?? null,
            'provincia' => $invoiceData['provincia'] ?? null,
            'codpostal' => $invoiceData['codpostal'] ?? null,
            'codpais' => $invoiceData['codpais'] ?? null,
            'codserie' => $invoiceData['codserie'],
            'codigo' => $invoiceData['codigo'],
            'numero2' => $invoiceData['numero2'] ?? null,
            'numproveedor' => $invoiceData['numproveedor'] ?? null,
            'fecha' => $this->typeDate == 'create' ?
                $invoiceData['fecha'] :
                $invoiceData['fechadevengo'] ?? $invoiceData['fecha'],
            'nombre' => $invoiceData['nombre'],
            'codsubcuenta' => $invoiceData['codsubcuenta'] ?? null,
            'cifnif' => $invoiceData['cifnif'],
            'neto' => Tools::round($neto),
            'iva' => $iva,
            'totaliva' => Tools::round($totalIva),
            'recargo' => $recargo,
            'totalrecargo' => Tools::round($totalRecargo),
            'irpf' => (float)$document->irpf,
            'totalirpf' => 0.0,
            'suplidos' => 0.0,
            'total' => (float)$document->total,
        ];
    }

    protected function processLayout(array &$lines, array &$totals): void
    {
        $exportManager = new ExportManager();
        $exportManager->setOrientation('landscape');
        $exportManager->newDoc($this->format, Tools::trans('taxes'));
        $exportManager->setCompany($this->idempresa);

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
            ]
        );

        $options = [
            Tools::trans('net') => ['display' => 'right'],
            Tools::trans('pct-tax') => ['display' => 'right'],
            Tools::trans('tax') => ['display' => 'right'],
            Tools::trans('pct-surcharge') => ['display' => 'right'],
            Tools::trans('surcharge') => ['display' => 'right'],
            Tools::trans('pct-irpf') => ['display' => 'right'],
            Tools::trans('irpf') => ['display' => 'right'],
            Tools::trans('supplied-amount') => ['display' => 'right'],
            Tools::trans('total') => ['display' => 'right']
        ];

        // add lines table
        $this->reduceLines($lines);
        $headers = empty($lines) ? [] : array_keys(end($lines));
        $exportManager->addTablePage($headers, $lines, $options);

        // add totals table
        $headTotals = empty($totals) ? [] : array_keys(end($totals));
        $exportManager->addTablePage($headTotals, $totals, $options);

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
