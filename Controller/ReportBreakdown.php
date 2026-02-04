<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\AlbaranProveedor;
use FacturaScripts\Dinamic\Model\Almacen;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportBreakdown extends Controller
{
    /** @var Contacto */
    public $billingAddress;

    /** @var Cliente */
    public $cliente;

    /** @var string */
    public $codagente;

    /** @var string */
    public $codalmacen;

    /** @var string */
    public $coddivisa;

    /** @var string */
    public $codpais;

    /** @var string */
    public $codpago;

    /** @var string */
    public $codserie;

    /** @var array */
    public $data;

    /** @var string */
    public $desde;

    /** @var string */
    public $format = 'screen';

    /** @var string */
    public $generar = 'informe_ventas';

    /** @var string */
    public $hasta;

    /** @var int */
    public $idempresa;

    /** @var Proveedor */
    public $proveedor;

    /** @var string */
    public $provincia;

    /** @var float */
    public $purchase_minimo;

    /** @var float */
    public $sale_minimo;

    /** @var Contacto */
    public $shippingAddress;

    /** @var string */
    public $type = 'invoice';

    /** @var Variante */
    public $variant;

    public function getAddress(Contacto $contacto): string
    {
        if (empty($contacto->id())) {
            return '';
        }

        $description = empty($contacto->descripcion) ? '(' . Tools::trans('empty') . ') ' : '(' . $contacto->descripcion . ') ';
        $description .= empty($contacto->direccion) ? '' : $contacto->direccion;
        return $description;
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["menu"] = "reports";
        $data["title"] = "report-breakdown";
        $data["icon"] = "fa-solid fa-braille";
        return $data;
    }

    public function getSelectValues($table, $code, $description, $empty = false): array
    {
        $values = $empty ? ['' => '------'] : [];
        foreach (CodeModel::all($table, $code, $description, $empty) as $row) {
            $values[$row->code] = $row->description;
        }
        return $values;
    }

    public function getUrlCustomer(string $customerName): string
    {
        $customer = new Cliente();
        $customerNameArr = explode(' | ', $customerName);
        if (!empty($customerNameArr[0]) && $customer->load($customerNameArr[0])) {
            return '<a href="' . $customer->url() . '" target="_blank">' . $customerName . '</a>';
        }

        return $customerName;
    }

    public function getUrlDocument(string $codigo): string
    {
        $isVentas = $this->generar === 'informe_ventas';
        if ($isVentas && $this->type === 'invoices') {
            $document = new FacturaCliente();
        } elseif ($isVentas && $this->type === 'delivery-notes') {
            $document = new AlbaranCliente();
        } elseif (!$isVentas && $this->type === 'invoices') {
            $document = new FacturaProveedor();
        } elseif (!$isVentas && $this->type === 'delivery-notes') {
            $document = new AlbaranProveedor();
        } else {
            return $codigo;
        }

        $where = [Where::eq('codigo', $codigo)];
        if ($document->loadWhere($where)) {
            return '<a href="' . $document->url() . '" target="_blank">' . $codigo . '</a>';
        }

        return $codigo;
    }

    public function getUrlProduct(string $referencia): string
    {
        $variant = new Variante();
        $where = [Where::eq('referencia', $referencia)];
        if ($variant->loadWhere($where)) {
            return '<a href="' . $variant->url() . '" target="_blank">' . $referencia . '</a>';
        }

        return $referencia;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->input('action', '');
        switch ($action) {
            case 'autocomplete-shipping-address':
            case 'autocomplete-billing-address':
                $this->autocompleteCustomerAddressAction();
                break;

            case 'autocomplete-customer':
                $this->autocompleteCustomerAction();
                return;

            case 'autocomplete-supplier':
                $this->autocompleteSupplierAction();
                return;

            case 'autocomplete-variant':
                $this->autocompleteVariantAction();
                return;

            case 'get-payment-methods':
                $this->getPaymentMethods();
                return;

            case 'get-provinces':
                $this->getProvincias();
                return;

            case 'get-warehouses':
                $this->getWarehouses();
                return;

            default:
                $this->iniFilters();
                $this->generarInforme();
        }
    }

    protected function autocompleteCustomerAction(): void
    {
        $this->setTemplate(false);

        $list = [];
        $cliente = new Cliente();
        $query = $this->request->input('query');
        foreach ($cliente->codeModelSearch($query, 'codcliente') as $value) {
            $list[] = [
                'key' => Tools::fixHtml($value->code),
                'value' => Tools::fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

    protected function autocompleteCustomerAddressAction(): void
    {
        $this->setTemplate(false);

        $list = [];
        $where = [
            Where::eq('codcliente', $this->request->input('customer')),
            Where::like('direccion', $this->request->input('query'))
        ];
        $orderBy = ['apellidos' => 'ASC', 'nombre' => 'ASC'];
        foreach (Contacto::all($where, $orderBy) as $contacto) {
            $list[] = [
                'key' => Tools::fixHtml($contacto->idcontacto),
                'value' => Tools::fixHtml($this->getAddress($contacto))
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

    protected function autocompleteSupplierAction(): void
    {
        $this->setTemplate(false);

        $list = [];
        $proveedor = new Proveedor();
        $query = $this->request->input('query');
        foreach ($proveedor->codeModelSearch($query, 'codproveedor') as $value) {
            $list[] = [
                'key' => Tools::fixHtml($value->code),
                'value' => Tools::fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

    protected function autocompleteVariantAction(): void
    {
        $this->setTemplate(false);

        $list = [];
        $variant = new Variante();
        $query = $this->request->input('query');
        foreach ($variant->codeModelSearch($query, 'referencia') as $value) {
            $list[] = [
                'key' => Tools::fixHtml($value->code),
                'value' => Tools::fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

    protected function buildDocumentExportRows(
        string $codeHeader,
        string $customerHeader,
        string $dateHeader,
        string $totalHeader
    ): array {
        $rows = [];
        $documents = $this->data[$this->type] ?? [];
        foreach ($documents as $document) {
            $customerName = $document['nombrecliente'] ?? $document['nombre'] ?? '';
            $dateValue = empty($document['fecha']) ? '' : Tools::date($document['fecha']);
            $rows[] = [
                $codeHeader => $document['codigo'] ?? '',
                $customerHeader => $customerName,
                $dateHeader => $dateValue,
                $totalHeader => Tools::number($document['total'] ?? 0)
            ];
        }

        return $rows;
    }

    protected function buildFilterExportRows(ExportManager $exportManager): void
    {
        // Construimos los datos de los filtros aplicados
        $filterData = [];
        $isVentas = $this->generar === 'informe_ventas';

        // Información general
        $filterData[Tools::trans('report')] = Tools::trans($this->getPageData()['title']);
        $filterData[Tools::trans('creation-date')] = Tools::dateTime();
        $filterData[Tools::trans('from-date')] = Tools::date($this->desde);
        $filterData[Tools::trans('until-date')] = Tools::date($this->hasta);
        $filterData[Tools::trans('company')] = Empresas::get($this->idempresa)->nombrecorto;
        $filterData[Tools::trans('document')] = Tools::trans($this->type);
        $filterData[Tools::trans('type')] = $isVentas ? Tools::trans('sales') : Tools::trans('purchases');

        // Filtros comunes
        if (!empty($this->codalmacen)) {
            $filterData[Tools::trans('warehouse')] = $this->codalmacen;
        }

        if (!empty($this->coddivisa)) {
            $filterData[Tools::trans('currency')] = $this->coddivisa;
        }

        if (!empty($this->codpago)) {
            $filterData[Tools::trans('method-payment')] = $this->codpago;
        }

        if (!empty($this->codserie)) {
            $filterData[Tools::trans('serie')] = $this->codserie;
        }

        // Filtros específicos de ventas
        if ($isVentas) {
            if (!empty($this->cliente->id())) {
                $filterData[Tools::trans('customer')] = $this->cliente->nombre;
            }

            if (!empty($this->billingAddress->id())) {
                $filterData[Tools::trans('billing-address')] = $this->getAddress($this->billingAddress);
            }

            if (!empty($this->shippingAddress->id())) {
                $filterData[Tools::trans('shipping-address')] = $this->getAddress($this->shippingAddress);
            }

            if (!empty($this->codagente)) {
                $filterData[Tools::trans('agent')] = $this->codagente;
            }

            if (!empty($this->codpais)) {
                $filterData[Tools::trans('country')] = $this->codpais;
            }

            if (!empty($this->provincia)) {
                $filterData[Tools::trans('province')] = $this->provincia;
            }

            if (!empty($this->sale_minimo)) {
                $filterData[Tools::trans('minimum-amount')] = Tools::number($this->sale_minimo);
            }
        } else {
            // Filtros específicos de compras
            if (!empty($this->proveedor->id())) {
                $filterData[Tools::trans('supplier')] = $this->proveedor->nombre;
            }

            if (!empty($this->purchase_minimo)) {
                $filterData[Tools::trans('minimum-amount')] = Tools::number($this->purchase_minimo);
            }
        }

        // Filtro de variante
        if (!empty($this->variant->referencia)) {
            $filterData[Tools::trans('product') . '/' . Tools::trans('variant')] = $this->variant->referencia;
        }

        // Convertimos el array asociativo a filas para la tabla
        $filterHeader = Tools::trans('filter');
        $valueHeader = Tools::trans('value');
        $rows = [];
        foreach ($filterData as $key => $value) {
            $rows[] = [
                $filterHeader => $key,
                $valueHeader => $value
            ];
        }

        // Añadimos la tabla de filtros
        $exportManager->addTablePage(
            [$filterHeader, $valueHeader],
            $rows,
            [],
            Tools::trans('filters')
        );
    }

    protected function buildMonthOptions(array $monthHeaderMap): array
    {
        $options = [];
        foreach ($monthHeaderMap as $monthHeader) {
            $options[$monthHeader] = ['display' => 'right'];
        }

        return $options;
    }

    protected function buildNetExportRows(array $monthHeaderMap, string $customerHeader, string $yearHeader): array
    {
        $rows = [];
        $previousCustomer = '';
        $netData = $this->data['net'] ?? [];
        foreach ($netData as $customer => $years) {
            foreach ($years as $year => $months) {
                $row = [
                    $customerHeader => $customer !== $previousCustomer ? $customer : '',
                    $yearHeader => $year
                ];

                foreach ($monthHeaderMap as $monthKey => $monthHeader) {
                    $row[$monthHeader] = Tools::number($months[$monthKey] ?? 0);
                }

                $rows[] = $row;
                $previousCustomer = $customer;
            }
        }

        return $rows;
    }

    protected function buildUnitsExportRows(
        array $monthHeaderMap,
        string $customerHeader,
        string $productHeader,
        string $yearHeader
    ): array {
        $rows = [];
        $previousCustomer = '';
        $previousProduct = '';
        $unitsData = $this->data['units'] ?? [];
        foreach ($unitsData as $customer => $products) {
            foreach ($products as $product => $years) {
                foreach ($years as $year => $months) {
                    // Igual que en Twig: no repetimos cliente o producto consecutivo.
                    $row = [
                        $customerHeader => $customer !== $previousCustomer ? $customer : '',
                        $productHeader => ($customer !== $previousCustomer || $product !== $previousProduct) ? $product : '',
                        $yearHeader => $year
                    ];

                    foreach ($monthHeaderMap as $monthKey => $monthHeader) {
                        $row[$monthHeader] = Tools::number($months[$monthKey] ?? 0);
                    }

                    $rows[] = $row;
                    $previousProduct = $product;
                }

                $previousCustomer = $customer;
            }
        }

        return $rows;
    }

    protected function generarInforme(): void
    {
        switch ($this->generar) {
            case 'informe_compras':
                $this->data['units'] = $this->getInformeComprasUnidadesData();
                $this->data['net'] = $this->getInformeComprasNetoData();
                $this->data[$this->type] = $this->getInformeComprasDocumentData();
                break;

            case 'informe_ventas':
                $this->data['units'] = $this->getInformeVentasUnidadesData();
                $this->data['net'] = $this->getInformeVentasNetoData();
                $this->data[$this->type] = $this->getInformeVentasDocumentData();
                break;
        }

        switch ($this->format) {
            case 'XLS':
            case 'PDF':
                $this->generarInformeExport();
                return;
        }
    }

    protected function generarInformeExport(): void
    {
        $this->setTemplate(false);
        $exportManager = new ExportManager();
        $exportManager->setOrientation('landscape');
        $exportManager->newDoc($this->format, $this->getTitleExport());
        $exportManager->setCompany($this->idempresa);

        $monthHeaderMap = $this->getMonthHeaderMap();
        $monthHeaders = array_values($monthHeaderMap);
        $monthOptions = $this->buildMonthOptions($monthHeaderMap);

        $customerHeader = Tools::trans('customer');
        $productHeader = Tools::trans('product');
        $yearHeader = Tools::trans('year');

        // creamos la página de resumen con los filtros aplicados
        $this->buildFilterExportRows($exportManager);

        // creamos la página de unidades
        $unitsHeaders = array_merge([$customerHeader, $productHeader, $yearHeader], $monthHeaders);
        $unitsRows = $this->buildUnitsExportRows($monthHeaderMap, $customerHeader, $productHeader, $yearHeader);
        $exportManager->addTablePage($unitsHeaders, $unitsRows, $monthOptions, Tools::trans('units'));

        // creamos la pagina de neto
        $netHeaders = array_merge([$customerHeader, $yearHeader], $monthHeaders);
        $netRows = $this->buildNetExportRows($monthHeaderMap, $customerHeader, $yearHeader);
        $exportManager->addTablePage($netHeaders, $netRows, $monthOptions, Tools::trans('net'));

        // creamos la página de documentos
        $codeHeader = Tools::trans('code');
        $dateHeader = Tools::trans('date');
        $totalHeader = Tools::trans('total');
        $documentHeaders = [$codeHeader, $customerHeader, $dateHeader, $totalHeader];
        $documentRows = $this->buildDocumentExportRows($codeHeader, $customerHeader, $dateHeader, $totalHeader);
        $documentOptions = [
            $dateHeader => ['display' => 'right'],
            $totalHeader => ['display' => 'right']
        ];
        $exportManager->addTablePage($documentHeaders, $documentRows, $documentOptions, Tools::trans($this->type));

        $exportManager->show($this->response);
    }

    protected function getDatosAgrupados(array $data, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $agrupados = [];
        foreach ($data as $row) {
            $keyValue = '';
            foreach ($keys as $key) {
                if (empty($keyValue)) {
                    $keyValue = $row[$key];
                    continue;
                }

                $keyValue .= ' | ' . $row[$key];
            }

            $year = date('Y', strtotime($row['fecha']));
            $mes = strtolower(date('F', strtotime($row['fecha'])));
            if (!isset($agrupados[$keyValue][$year])) {
                $agrupados[$keyValue][$year] = [
                    'january' => 0, // enero
                    'february' => 0, // febrero
                    'march' => 0,
                    'april' => 0,
                    'may' => 0,
                    'june' => 0,
                    'july' => 0,
                    'august' => 0,
                    'september' => 0,
                    'october' => 0,
                    'november' => 0,
                    'december' => 0, // diciembre
                    'total' => 0 // total anual
                ];
            }

            // acumulamos mes a mes
            $agrupados[$keyValue][$year][$mes] += floatval($row['total']);

            // acumulamos el año
            $agrupados[$keyValue][$year]['total'] += floatval($row['total']);
        }

        return $agrupados;
    }

    protected function getDatosAgrupadosRef(array $data, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $agrupados = [];
        foreach ($data as $row) {
            $keyVal = '';
            foreach ($keys as $key) {
                if (empty($keyVal)) {
                    $keyVal = $row[$key];
                    continue;
                }

                $keyVal .= ' | ' . $row[$key];
            }

            $ref = $row['referencia'];
            $year = date('Y', strtotime($row['fecha']));
            $mes = strtolower(date('F', strtotime($row['fecha'])));
            if (!isset($agrupados[$keyVal][$ref][$year])) {
                $agrupados[$keyVal][$ref][$year] = [
                    'january' => 0, // enero
                    'february' => 0, // febrero
                    'march' => 0,
                    'april' => 0,
                    'may' => 0,
                    'june' => 0,
                    'july' => 0,
                    'august' => 0,
                    'september' => 0,
                    'october' => 0,
                    'november' => 0,
                    'december' => 0, // diciembre
                    'total' => 0 // total anual
                ];
            }

            // acumulamos mes a mes
            $agrupados[$keyVal][$ref][$year][$mes] += floatval($row['total']);

            // acumulamos el año
            $agrupados[$keyVal][$ref][$year]['total'] += floatval($row['total']);
        }

        return $agrupados;
    }

    protected function getInformeComprasDataWhere(): string
    {
        $sql = '';

        if ($this->codalmacen) {
            $sql .= " AND d.codalmacen = " . $this->dataBase->var2str($this->codalmacen);
        }

        if ($this->coddivisa) {
            $sql .= " AND d.coddivisa = " . $this->dataBase->var2str($this->coddivisa);
        }

        if ($this->codpago) {
            $sql .= " AND d.codpago = " . $this->dataBase->var2str($this->codpago);
        }

        if ($this->codserie) {
            $sql .= " AND d.codserie = " . $this->dataBase->var2str($this->codserie);
        }

        if (!empty($this->proveedor->id())) {
            $sql .= " AND d.codproveedor = " . $this->dataBase->var2str($this->proveedor->id());
        }

        if ($this->purchase_minimo) {
            $sql .= " AND d.neto > " . $this->dataBase->var2str($this->purchase_minimo);
        }

        if (!empty($this->variant->id())) {
            $sql .= " AND l.referencia = " . $this->dataBase->var2str($this->variant->referencia);
        }

        return $sql;
    }

    protected function getInformeComprasDocumentData(): array
    {
        $table = $this->type === 'invoices' ? 'facturasprov' : 'albaranesprov';
        $code = $this->type === 'invoices' ? 'idfactura' : 'idalbaran';

        $sql = "SELECT d." . $code . " as id, d.codigo, d.codproveedor, d.nombre, d.fecha, d.total"
            . " FROM " . $table . " d"
            . " WHERE d.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND d.fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND d.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . $this->getInformeComprasDataWhere()
            . " ORDER BY d.fecha ASC, d.hora ASC;";

        return $this->dataBase->select($sql);
    }

    protected function getInformeComprasNetoData(): array
    {
        $table = $this->type === 'invoices' ? 'facturasprov' : 'albaranesprov';
        $line = $this->type === 'invoices' ? 'lineasfacturasprov' : 'lineasalbaranesprov';
        $code = $this->type === 'invoices' ? 'idfactura' : 'idalbaran';

        $sql = "SELECT d.codproveedor, d.nombre, d.fecha, SUM(d.neto) as total"
            . " FROM " . $table . " d"
            . " LEFT JOIN " . $line . " l ON d." . $code . " = l." . $code
            . " WHERE d.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND d.fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND d.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . $this->getInformeComprasDataWhere()
            . " GROUP BY d.codproveedor, d.nombre, d.fecha"
            . " ORDER BY d.codproveedor ASC, d.fecha DESC;";

        $data = $this->dataBase->select($sql);
        if (empty($data)) {
            return [];
        }

        // agrupamos los datos por proveedor, año y mes
        return $this->getDatosAgrupados($data, ['codproveedor', 'nombre']);
    }

    protected function getInformeComprasUnidadesData(): array
    {
        $table = $this->type === 'invoices' ? 'facturasprov' : 'albaranesprov';
        $line = $this->type === 'invoices' ? 'lineasfacturasprov' : 'lineasalbaranesprov';
        $code = $this->type === 'invoices' ? 'idfactura' : 'idalbaran';

        $sql = "SELECT d.codproveedor, d.nombre, d.fecha, l.referencia, SUM(l.cantidad) as total"
            . " FROM " . $table . " d, " . $line . " l"
            . " WHERE l." . $code . " = d." . $code
            . " AND l.referencia IS NOT NULL"
            . " AND d.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . " AND d.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND d.fecha <= " . $this->dataBase->var2str($this->hasta)
            . $this->getInformeComprasDataWhere()
            . " GROUP BY d.codproveedor, d.nombre, d.fecha, l.referencia, l.descripcion"
            . " ORDER BY d.codproveedor ASC, l.referencia ASC, d.fecha DESC;";

        $data = $this->dataBase->select($sql);
        if (empty($data)) {
            return [];
        }

        // agrupamos los datos por proveedor, referencia, año y mes
        return $this->getDatosAgrupadosRef($data, ['codproveedor', 'nombre']);
    }

    protected function getInformeVentasDataWhere(): string
    {
        $sql = '';
        if (!empty($this->cliente->id())) {
            $sql .= " AND d.codcliente = " . $this->dataBase->var2str($this->cliente->id());
        }

        if (!empty($this->billingAddress->id())) {
            $sql .= " AND d.idcontactofact = " . $this->dataBase->var2str($this->billingAddress->id());
        }

        if (!empty($this->shippingAddress->id())) {
            $sql .= " AND d.idcontactoenv = " . $this->dataBase->var2str($this->shippingAddress->id());
        }

        if ($this->codagente) {
            $sql .= " AND d.codagente = " . $this->dataBase->var2str($this->codagente);
        }

        if ($this->codalmacen) {
            $sql .= " AND d.codalmacen = " . $this->dataBase->var2str($this->codalmacen);
        }

        if ($this->coddivisa) {
            $sql .= " AND d.coddivisa = " . $this->dataBase->var2str($this->coddivisa);
        }

        if ($this->codpago) {
            $sql .= " AND d.codpago = " . $this->dataBase->var2str($this->codpago);
        }

        if ($this->codpais) {
            $sql .= " AND d.codpais = " . $this->dataBase->var2str($this->codpais);
        }

        if ($this->codserie) {
            $sql .= " AND d.codserie = " . $this->dataBase->var2str($this->codserie);
        }

        if ($this->provincia) {
            $sql .= " AND lower(d.provincia) = lower(" . $this->dataBase->var2str($this->provincia) . ")";
        }

        if ($this->sale_minimo) {
            $sql .= " AND d.neto > " . $this->dataBase->var2str($this->sale_minimo);
        }

        if (!empty($this->variant->id())) {
            $sql .= "AND l.referencia = " . $this->dataBase->var2str($this->variant->referencia);
        }

        return $sql;
    }

    protected function getInformeVentasNetoData(): array
    {
        $table = $this->type === 'invoices' ? 'facturascli' : 'albaranescli';
        $line = $this->type === 'invoices' ? 'lineasfacturascli' : 'lineasalbaranescli';
        $code = $this->type === 'invoices' ? 'idfactura' : 'idalbaran';

        $sql = "SELECT d.codcliente, d.nombrecliente, d.fecha, SUM(d.neto) as total"
            . " FROM " . $table . " d"
            . " LEFT JOIN " . $line . " l ON d." . $code . " = l." . $code
            . " WHERE d.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND d.fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND d.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . $this->getInformeVentasDataWhere()
            . " GROUP BY d.codalmacen, d.codcliente, d.nombrecliente, d.fecha"
            . " ORDER BY d.codcliente ASC, d.fecha DESC;";

        $data = $this->dataBase->select($sql);
        if (empty($data)) {
            return [];
        }

        // agrupamos los datos por cliente, año y mes
        return $this->getDatosAgrupados($data, ['codcliente', 'nombrecliente']);
    }

    protected function getInformeVentasDocumentData(): array
    {
        $table = $this->type === 'invoices' ? 'facturascli' : 'albaranescli';
        $line = $this->type === 'invoices' ? 'lineasfacturascli' : 'lineasalbaranescli';
        $code = $this->type === 'invoices' ? 'idfactura' : 'idalbaran';

        $sql = "SELECT d." . $code . " as id, d.codigo, d.codcliente, d.nombrecliente, d.fecha, d.total"
            . " FROM " . $table . " d"
            . " LEFT JOIN " . $line . " l ON d." . $code . " = l." . $code
            . " WHERE d.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND d.fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND d.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . $this->getInformeVentasDataWhere()
            . " ORDER BY d.fecha ASC, d.hora ASC;";

        return $this->dataBase->select($sql);
    }

    protected function getInformeVentasUnidadesData(): array
    {
        $table = $this->type === 'invoices' ? 'facturascli' : 'albaranescli';
        $line = $this->type === 'invoices' ? 'lineasfacturascli' : 'lineasalbaranescli';
        $code = $this->type === 'invoices' ? 'idfactura' : 'idalbaran';

        $sql = "SELECT d.codcliente, d.nombrecliente, d.fecha, l.referencia, SUM(l.cantidad) as total"
            . " FROM " . $table . " d, " . $line . " l"
            . " WHERE l." . $code . " = d." . $code
            . " AND l.referencia IS NOT NULL"
            . " AND d.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . " AND d.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND d.fecha <= " . $this->dataBase->var2str($this->hasta)
            . $this->getInformeVentasDataWhere()
            . " GROUP BY d.codcliente, d.nombrecliente, d.fecha, l.referencia, l.descripcion"
            . " ORDER BY d.codcliente ASC, l.referencia ASC, d.fecha DESC;";

        $data = $this->dataBase->select($sql);
        if (empty($data)) {
            return [];
        }

        // agrupamos los datos por cliente, referencia, año y mes
        return $this->getDatosAgrupadosRef($data, ['codcliente', 'nombrecliente']);
    }

    protected function getMonthHeaderMap(): array
    {
        return [
            'january' => Tools::trans('january'),
            'february' => Tools::trans('february'),
            'march' => Tools::trans('march'),
            'april' => Tools::trans('april'),
            'may' => Tools::trans('may'),
            'june' => Tools::trans('june'),
            'july' => Tools::trans('july'),
            'august' => Tools::trans('august'),
            'september' => Tools::trans('september'),
            'october' => Tools::trans('october'),
            'november' => Tools::trans('november'),
            'december' => Tools::trans('december'),
            'total' => Tools::trans('total')
        ];
    }

    protected function getPaymentMethods(): void
    {
        $this->setTemplate(false);

        $html = '<option value="">------</option>';
        $this->idempresa = $this->request->input('idempresa');
        $where = [Where::eq('idempresa', $this->idempresa)];
        foreach (FormaPago::all($where) as $paymentMethod) {
            $html .= '<option value="' . $paymentMethod->codpago . '">' . Tools::fixHtml($paymentMethod->descripcion) . '</option>';
        }

        $this->response->setContent(json_encode($html));
    }

    protected function getProvincias(): void
    {
        $this->setTemplate(false);

        $html = '<option value="">------</option>';
        $this->provincia = $this->request->input('provincia');
        $this->codpais = $this->request->input('codpais');
        if (!empty($this->codpais)) {
            $sql = "select distinct provincia from facturascli as fc where fc.codpais = '" . $this->codpais . "'";
            $lineas = $this->dataBase->select($sql);
            foreach ($lineas as $dl) {
                if (!is_null($dl['provincia'])) {
                    $check = $this->provincia == $dl['provincia'] ? 'selected' : '';
                    $html .= '<option value="' . $dl['provincia'] . '" ' . $check . '>' . $dl['provincia'] . '</option>';
                }
            }
        }

        $this->response->setContent(json_encode($html));
    }

    protected function getTitleExport(): string
    {
        $title = Tools::trans('report-breakdown');

        $title .= $this->generar === 'informe_ventas'
            ? '_' . Tools::trans('sales')
            : '_' . Tools::trans('purchases');

        $title .= $this->type === 'invoices'
            ? '_' . Tools::trans('invoices')
            : '_' . Tools::trans('delivery-notes');

        $title .= '_' . Tools::dateTime();

        return strtolower(str_replace(' ', '_', $title));
    }

    protected function getWarehouses(): void
    {
        $this->setTemplate(false);

        $html = '<option value="">------</option>';
        $this->idempresa = $this->request->input('idempresa');
        $where = [Where::eq('idempresa', $this->idempresa)];
        foreach (Almacen::all($where) as $warehouse) {
            $html .= '<option value="' . $warehouse->codalmacen . '">' . Tools::fixHtml($warehouse->nombre) . '</option>';
        }

        $this->response->setContent(json_encode($html));
    }

    /**
     * Obtenemos los valores de los filtros del formulario.
     */
    protected function iniFilters(): void
    {
        // filtros generales
        $this->desde = $this->request->input('desde', date('Y') . '-01-01');
        $this->hasta = $this->request->input('hasta', date('Y') . '-12-31');
        $this->idempresa = $this->request->input('idempresa', Tools::settings('default', 'idempresa'));
        $this->codalmacen = $this->request->input('codalmacen');
        $this->codserie = $this->request->input('codserie');
        $this->codpago = $this->request->input('codpago');
        $this->coddivisa = $this->request->input('coddivisa', Tools::settings('default', 'coddivisa'));
        $this->codagente = $this->request->input('codagente');
        $this->type = $this->request->input('type', 'invoice');
        $this->format = $this->request->input('format', 'screen');
        $this->generar = $this->request->input('generar', 'informe_ventas');

        $this->variant = new Variante();
        $whereVariant = [Where::eq('referencia', $this->request->input('refvariant'))];
        $this->variant->loadWhere($whereVariant);

        // filtros de ventas
        $this->codpais = $this->request->input('codpais');
        $this->provincia = $this->request->input('provincia', false);
        $this->sale_minimo = (float)$this->request->input('sale-minimo', false);

        $this->cliente = new Cliente();
        $this->cliente->load($this->request->input('codcliente'));

        $this->billingAddress = new Contacto();
        $this->billingAddress->load($this->request->input('idcontactofact'));

        $this->shippingAddress = new Contacto();
        $this->shippingAddress->load($this->request->input('idcontactoenv'));

        // filtros de compras
        $this->proveedor = new Proveedor();
        $this->proveedor->load($this->request->input('codproveedor'));

        $this->purchase_minimo = (float)$this->request->input('purchase-minimo', false);
    }


    protected function getMonthsTitlesRows(): string
    {
        return substr(Tools::trans('january'), 0, 3) . ';'
            . substr(Tools::trans('february'), 0, 3) . ';'
            . substr(Tools::trans('march'), 0, 3) . ';'
            . substr(Tools::trans('april'), 0, 3) . ';'
            . substr(Tools::trans('may'), 0, 3) . ';'
            . substr(Tools::trans('june'), 0, 3) . ';'
            . substr(Tools::trans('july'), 0, 3) . ';'
            . substr(Tools::trans('august'), 0, 3) . ';'
            . substr(Tools::trans('september'), 0, 3) . ';'
            . substr(Tools::trans('october'), 0, 3) . ';'
            . substr(Tools::trans('november'), 0, 3) . ';'
            . substr(Tools::trans('december'), 0, 3) . ';';
    }

    protected function getNombreCliente(string $codcliente): string
    {
        $cliente = new Cliente();
        if ($codcliente && $cliente->load($codcliente)) {
            return Tools::fixHtml($cliente->nombre);
        }

        return '-';
    }

    protected function getNombreProveedor(string $codproveedor): string
    {
        $proveedor = new Proveedor();
        if ($codproveedor && $proveedor->load($codproveedor)) {
            return Tools::fixHtml($proveedor->nombre);
        }

        return '-';
    }

    protected function getTotalesAgrupados(array $agrupados): array
    {
        $totales = [];
        foreach ($agrupados as $years) {
            foreach ($years as $year => $meses) {
                if (!isset($totales[$year])) {
                    $totales[$year] = [
                        1 => 0, // enero
                        2 => 0,
                        3 => 0,
                        4 => 0,
                        5 => 0,
                        6 => 0,
                        7 => 0,
                        8 => 0,
                        9 => 0,
                        10 => 0,
                        11 => 0,
                        12 => 0, // diciembre
                        13 => 0 // total anual
                    ];
                }

                foreach ($meses as $mes => $total) {
                    $totales[$year][$mes] += $total;
                }
            }
        }

        return $totales;
    }

    protected function informeCompras(): void
    {
        // imprimimos las cabeceras
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header('Content-Disposition: attachment; filename="'
            . str_replace(' ', '_', strtolower(Tools::trans('purchase-report'))) . '.csv"');
        echo 'codproveedor;'
            . Tools::trans('name') . ';'
            . Tools::trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . Tools::trans('total') . "\n";

        // agrupamos los datos por proveedor, año y mes
        //$agrupados = $this->getDatosAgrupados($data, 'codproveedor');

        // imprimimos las líneas
        foreach ($agrupados as $codproveedor => $years) {
            foreach ($years as $year => $meses) {
                // ahora para cada año imprimimos una línea
                echo '"' . $codproveedor . '";' . $this->getNombreProveedor($codproveedor) . ';' . $year;
                foreach ($meses as $mes) {
                    echo ';' . number_format($mes, FS_NF0, ',', '');
                }
                echo "\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
        }

        // imprimimos los totales por año
        foreach ($this->getTotalesAgrupados($agrupados) as $year => $meses) {
            echo ";" . strtoupper(Tools::trans('totals')) . ";" . $year;
            foreach ($meses as $mes) {
                echo ';' . number_format($mes, FS_NF0, ',', '');
            }
            echo ";\n";
        }
    }

    protected function informeComprasUnidades(): void
    {
        // imprimimos las cabeceras
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\""
            . str_replace(' ', '_', strtolower(Tools::trans('units-purchase-report'))) . ".csv\"");
        echo 'codproveedor;'
            . Tools::trans('name') . ';'
            . Tools::trans('reference') . ';'
            . Tools::trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . Tools::trans('total') . "\n";

        // agrupamos los datos por proveedor, referencia, año y mes
        //$agrupados = $this->getDatosAgrupadosRef($data, 'codproveedor');

        // imprimimos las líneas
        foreach ($agrupados as $codproveedor => $referencias) {
            foreach ($referencias as $referencia => $years) {
                foreach ($years as $year => $meses) {
                    echo '"' . $codproveedor . '";' . $this->getNombreProveedor($codproveedor) . ';"' . $referencia . '";' . $year;
                    foreach ($meses as $mes) {
                        echo ';' . number_format($mes, FS_NF0, ',', '');
                    }
                    echo "\n";
                }
                echo ";;;;;;;;;;;;;;;\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
        }
    }

    protected function informeVentas(): void
    {
        // imprimimos las cabeceras
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\""
            . str_replace(' ', '_', strtolower(Tools::trans('sale-report'))) . ".csv\"");
        echo 'codcliente;'
            . Tools::trans('name') . ';'
            . Tools::trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . Tools::trans('total') . "\n";

        // agrupamos los datos por cliente, año y mes
        //$agrupados = $this->getDatosAgrupados($data, 'codcliente');

        // imprimimos las líneas
        foreach ($agrupados as $codcliente => $years) {
            foreach ($years as $year => $meses) {
                echo '"' . $codcliente . '";' . $this->getNombreCliente($codcliente) . ';' . $year;
                foreach ($meses as $mes) {
                    echo ';' . number_format($mes, FS_NF0, ',', '');
                }
                echo "\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
        }

        // imprimimos los totales por año
        foreach ($this->getTotalesAgrupados($agrupados) as $year => $meses) {
            echo ";" . strtoupper(Tools::trans('totals')) . ";" . $year;
            foreach ($meses as $mes) {
                echo ';' . number_format($mes, FS_NF0, ',', '');
            }
            echo ";\n";
        }
    }

    protected function informeVentasUnidades(): void
    {
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\""
            . str_replace(' ', '_', strtolower(Tools::trans('units-sale-report'))) . ".csv\"");
        echo 'codcliente;'
            . Tools::trans('name') . ';'
            . Tools::trans('reference') . ';'
            . Tools::trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . Tools::trans('total') . "\n";

        // agrupamos los datos por cliente, referencia, añi y mes
        //$agrupados = $this->getDatosAgrupadosRef($data, 'codcliente');

        // imprimimos las líneas
        foreach ($agrupados as $codcliente => $referencias) {
            foreach ($referencias as $referencia => $years) {
                foreach ($years as $year => $meses) {
                    echo '"' . $codcliente . '";' . $this->getNombreCliente($codcliente) . ';"' . $referencia . '";' . $year;
                    foreach ($meses as $mes) {
                        echo ';' . number_format($mes, FS_NF0, ',', '');
                    }
                    echo "\n";
                }
                echo ";;;;;;;;;;;;;;;;\n";
            }
            echo ";;;;;;;;;;;;;;;;\n";
        }
    }
}
