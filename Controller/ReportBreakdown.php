<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Proveedor;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportBreakdown extends Controller
{
    public $billingAddress;
    public $cliente;
    public $codagente;
    public $codalmacen;
    public $coddivisa;
    public $codpais;
    public $codpago;
    public $codserie;
    public $desde;
    public $hasta;
    public $idempresa;
    public $proveedor;
    public $provincia;
    public $purchase_minimo;
    public $purchase_unidades;
    public $sale_minimo;
    public $sale_unidades;
    public $shippingAddress;

    public function getAddress($contacto): string
    {
        if (false === $contacto) {
            return '';
        }
        $description = empty($contacto->descripcion) ? '(' . $this->toolBox()->i18n()->trans('empty') . ') ' : '(' . $contacto->descripcion . ') ';
        $description .= empty($contacto->direccion) ? '' : $contacto->direccion;
        return $description;
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["menu"] = "reports";
        $data["title"] = "report-breakdown";
        $data["icon"] = "fas fa-braille";
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

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->get('action', '');
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

            case 'get-provinces':
                $this->getProvincias();
                return;

            default:
                $this->iniFilters();
                $this->generarInforme();
        }
    }

    protected function autocompleteCustomerAction()
    {
        $this->setTemplate(false);

        $list = [];
        $cliente = new Cliente();
        $query = $this->request->get('query');
        foreach ($cliente->codeModelSearch($query, 'codcliente') as $value) {
            $list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($value->code),
                'value' => $this->toolBox()->utils()->fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

    protected function autocompleteCustomerAddressAction()
    {
        $this->setTemplate(false);

        $list = [];
        $contactoModel = new Contacto();
        $where = [
            new DataBaseWhere('codcliente', $this->request->get('customer')),
            new DataBaseWhere('direccion', $this->request->get('query'), 'LIKE')
        ];
        foreach ($contactoModel->all($where, ['apellidos' => 'ASC', 'nombre' => 'ASC'], 0, 0) as $contacto) {
            $list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($contacto->idcontacto),
                'value' => $this->toolBox()->utils()->fixHtml($this->getAddress($contacto))
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

    protected function autocompleteSupplierAction()
    {
        $this->setTemplate(false);

        $list = [];
        $proveedor = new Proveedor();
        $query = $this->request->get('query');
        foreach ($proveedor->codeModelSearch($query, 'codproveedor') as $value) {
            $list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($value->code),
                'value' => $this->toolBox()->utils()->fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

    protected function generarInforme()
    {
        switch ($this->request->get('generar')) {
            case 'informe_compras':
                if ($this->purchase_unidades) {
                    $this->informeComprasUnidades();
                    break;
                }
                $this->informeCompras();
                break;

            case 'informe_ventas':
                if ($this->sale_unidades) {
                    $this->informeVentasUnidades();
                    break;
                }
                $this->informeVentas();
                break;
        }
    }

    protected function getDatosAgrupados(array $data, string $key): array
    {
        $agrupados = [];
        foreach ($data as $row) {
            $keyValue = $row[$key];
            $year = date('Y', strtotime($row['fecha']));
            $mes = date('n', strtotime($row['fecha']));
            if (!isset($agrupados[$keyValue][$year])) {
                $agrupados[$keyValue][$year] = [
                    1 => 0, // enero
                    2 => 0, // febrero
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

            // acumulamos mes a mes
            $agrupados[$keyValue][$year][$mes] += floatval($row['total']);

            // acumulamos el año
            $agrupados[$keyValue][$year][13] += floatval($row['total']);
        }

        return $agrupados;
    }

    protected function getDatosAgrupadosRef(array $data, string $key): array
    {
        $agrupados = [];
        foreach ($data as $row) {
            $keyVal = $row[$key];
            $ref = $row['referencia'];
            $year = date('Y', strtotime($row['fecha']));
            $mes = date('n', strtotime($row['fecha']));
            if (!isset($agrupados[$keyVal][$ref][$year])) {
                $agrupados[$keyVal][$ref][$year] = [
                    1 => 0, // enero
                    2 => 0, // febrero
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

            // acumulamos mes a mes
            $agrupados[$keyVal][$ref][$year][$mes] += floatval($row['total']);

            // acumulamos el año
            $agrupados[$keyVal][$ref][$year][13] += floatval($row['total']);
        }

        return $agrupados;
    }

    protected function getInformeComprasData(): array
    {
        $sql = "SELECT codproveedor,fecha,SUM(neto) as total FROM facturasprov"
            . " WHERE fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND idempresa = " . $this->dataBase->var2str($this->idempresa)
            . $this->getInformeComprasDataWhere()
            . " GROUP BY codproveedor,fecha"
            . " ORDER BY codproveedor ASC, fecha DESC;";
        return $this->dataBase->select($sql);
    }

    protected function getInformeComprasDataWhere(): string
    {
        $sql = '';
        if ($this->codalmacen) {
            $sql .= " AND codalmacen = " . $this->dataBase->var2str($this->codalmacen);
        }

        if ($this->coddivisa) {
            $sql .= " AND coddivisa = " . $this->dataBase->var2str($this->coddivisa);
        }

        if ($this->codpago) {
            $sql .= " AND codpago = " . $this->dataBase->var2str($this->codpago);
        }

        if ($this->codserie) {
            $sql .= " AND codserie = " . $this->dataBase->var2str($this->codserie);
        }

        if ($this->proveedor) {
            $sql .= " AND codproveedor = " . $this->dataBase->var2str($this->proveedor->codproveedor);
        }

        if ($this->purchase_minimo) {
            $sql .= " AND neto > " . $this->dataBase->var2str($this->purchase_minimo);
        }

        return $sql;
    }

    protected function getInformeComprasUnidadesData(): array
    {
        $sql = "SELECT f.codproveedor,f.fecha,l.referencia,SUM(l.cantidad) as total"
            . " FROM facturasprov f, lineasfacturasprov l"
            . " WHERE l.idfactura = f.idfactura"
            . " AND l.referencia IS NOT NULL"
            . " AND f.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . " AND f.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND f.fecha <= " . $this->dataBase->var2str($this->hasta)
            . $this->getInformeComprasDataWhere()
            . " GROUP BY f.codproveedor,f.fecha,l.referencia,l.descripcion"
            . " ORDER BY f.codproveedor ASC, l.referencia ASC, f.fecha DESC;";
        return $this->dataBase->select($sql);
    }

    protected function getInformeVentasData(): array
    {
        $sql = "SELECT codcliente,fecha,SUM(neto) as total FROM facturascli"
            . " WHERE fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND idempresa = " . $this->dataBase->var2str($this->idempresa)
            . $this->getInformeVentasDataWhere()
            . " GROUP BY codalmacen,codcliente,fecha"
            . " ORDER BY codcliente ASC, fecha DESC;";
        return $this->dataBase->select($sql);
    }

    protected function getInformeVentasDataWhere(): string
    {
        $sql = '';
        if ($this->cliente) {
            $sql .= " AND codcliente = " . $this->dataBase->var2str($this->cliente->codcliente);
        }

        if ($this->billingAddress) {
            $sql .= " AND idcontactofact = " . $this->dataBase->var2str($this->billingAddress->idcontacto);
        }

        if ($this->shippingAddress) {
            $sql .= " AND idcontactoenv = " . $this->dataBase->var2str($this->shippingAddress->idcontacto);
        }

        if ($this->codagente) {
            $sql .= " AND codagente = " . $this->dataBase->var2str($this->codagente);
        }

        if ($this->codalmacen) {
            $sql .= " AND codalmacen = " . $this->dataBase->var2str($this->codalmacen);
        }

        if ($this->coddivisa) {
            $sql .= " AND coddivisa = " . $this->dataBase->var2str($this->coddivisa);
        }

        if ($this->codpago) {
            $sql .= " AND codpago = " . $this->dataBase->var2str($this->codpago);
        }

        if ($this->codpais) {
            $sql .= " AND codpais = " . $this->dataBase->var2str($this->codpais);
        }

        if ($this->codserie) {
            $sql .= " AND codserie = " . $this->dataBase->var2str($this->codserie);
        }

        if ($this->provincia) {
            $sql .= " AND lower(provincia) = lower(" . $this->dataBase->var2str($this->provincia) . ")";
        }

        if ($this->sale_minimo) {
            $sql .= " AND neto > " . $this->dataBase->var2str($this->sale_minimo);
        }

        return $sql;
    }

    protected function getInformeVentasUnidadesData(): array
    {
        $sql = "SELECT f.codcliente,f.fecha,l.referencia,SUM(l.cantidad) as total"
            . " FROM facturascli f, lineasfacturascli l"
            . " WHERE l.idfactura = f.idfactura"
            . " AND l.referencia IS NOT NULL"
            . " AND f.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . " AND f.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND f.fecha <= " . $this->dataBase->var2str($this->hasta)
            . $this->getInformeVentasDataWhere()
            . " GROUP BY f.codcliente,f.fecha,l.referencia,l.descripcion"
            . " ORDER BY f.codcliente ASC, l.referencia ASC, f.fecha DESC;";
        return $this->dataBase->select($sql);
    }

    protected function getMonthsTitlesRows(): string
    {
        $i18n = ToolBox::i18n();
        return substr($i18n->trans('january'), 0, 3) . ';'
            . substr($i18n->trans('february'), 0, 3) . ';'
            . substr($i18n->trans('march'), 0, 3) . ';'
            . substr($i18n->trans('april'), 0, 3) . ';'
            . substr($i18n->trans('may'), 0, 3) . ';'
            . substr($i18n->trans('june'), 0, 3) . ';'
            . substr($i18n->trans('july'), 0, 3) . ';'
            . substr($i18n->trans('august'), 0, 3) . ';'
            . substr($i18n->trans('september'), 0, 3) . ';'
            . substr($i18n->trans('october'), 0, 3) . ';'
            . substr($i18n->trans('november'), 0, 3) . ';'
            . substr($i18n->trans('december'), 0, 3) . ';';
    }

    protected function getNombreCliente(string $codcliente): string
    {
        $cliente = new Cliente();
        if ($codcliente && $cliente->loadFromCode($codcliente)) {
            return self::toolBox()::utils()::fixHtml($cliente->nombre);
        }

        return '-';
    }

    protected function getNombreProveedor(string $codproveedor): string
    {
        $proveedor = new Proveedor();
        if ($codproveedor && $proveedor->loadFromCode($codproveedor)) {
            return self::toolBox()::utils()::fixHtml($proveedor->nombre);
        }

        return '-';
    }

    protected function getProvincias()
    {
        $this->setTemplate(false);

        $html = '<option value="">------</option>';
        $this->provincia = $this->request->get('provincia');
        $this->codpais = $this->request->get('codpais');
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

    protected function informeCompras()
    {
        $data = $this->getInformeComprasData();
        if (empty($data)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return;
        }

        // imprimimos las cabeceras
        $i18n = ToolBox::i18n();
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header('Content-Disposition: attachment; filename="'
            . str_replace(' ', '_', strtolower($i18n->trans('purchase-report'))) . '.csv"');
        echo 'codproveedor;'
            . $i18n->trans('name') . ';'
            . $i18n->trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . $i18n->trans('total') . "\n";

        // agrupamos los datos por proveedor, año y mes
        $agrupados = $this->getDatosAgrupados($data, 'codproveedor');

        // imprimimos las líneas
        foreach ($agrupados as $codproveedor => $years) {
            foreach ($years as $year => $meses) {
                // ahora para cada año imprimimos una línea
                echo '"' . $codproveedor . '";' . $this->getNombreProveedor($codproveedor) . ';' . $year;
                foreach ($meses as $mes) {
                    echo ';' . number_format($mes, FS_NF0, '.', '');
                }
                echo "\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
        }

        // imprimimos los totales por año
        foreach ($this->getTotalesAgrupados($agrupados) as $year => $meses) {
            echo ";" . strtoupper(ToolBox::i18n()->trans('totals')) . ";" . $year;
            foreach ($meses as $mes) {
                echo ';' . number_format($mes, FS_NF0, '.', '');
            }
            echo ";\n";
        }
    }

    protected function informeComprasUnidades()
    {
        $data = $this->getInformeComprasUnidadesData();
        if (empty($data)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return;
        }

        // imprimimos las cabeceras
        $i18n = ToolBox::i18n();
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\""
            . str_replace(' ', '_', strtolower($i18n->trans('units-purchase-report'))) . ".csv\"");
        echo 'codproveedor;'
            . $i18n->trans('name') . ';'
            . $i18n->trans('reference') . ';'
            . $i18n->trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . $i18n->trans('total') . "\n";

        // agrupamos los datos por proveedor, referencia, año y mes
        $agrupados = $this->getDatosAgrupadosRef($data, 'codproveedor');

        // imprimimos las líneas
        foreach ($agrupados as $codproveedor => $referencias) {
            foreach ($referencias as $referencia => $years) {
                foreach ($years as $year => $meses) {
                    echo '"' . $codproveedor . '";' . $this->getNombreProveedor($codproveedor) . ';"' . $referencia . '";' . $year;
                    foreach ($meses as $mes) {
                        echo ';' . number_format($mes, FS_NF0, '.', '');
                    }
                    echo "\n";
                }
                echo ";;;;;;;;;;;;;;;\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
        }
    }

    protected function informeVentas()
    {
        $data = $this->getInformeVentasData();
        if (empty($data)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return;
        }

        // imprimimos las cabeceras
        $i18n = ToolBox::i18n();
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\""
            . str_replace(' ', '_', strtolower($i18n->trans('sale-report'))) . ".csv\"");
        echo 'codcliente;'
            . $i18n->trans('name') . ';'
            . $i18n->trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . $i18n->trans('total') . "\n";

        // agrupamos los datos por cliente, año y mes
        $agrupados = $this->getDatosAgrupados($data, 'codcliente');

        // imprimimos las líneas
        foreach ($agrupados as $codcliente => $years) {
            foreach ($years as $year => $meses) {
                echo '"' . $codcliente . '";' . $this->getNombreCliente($codcliente) . ';' . $year;
                foreach ($meses as $mes) {
                    echo ';' . number_format($mes, FS_NF0, '.', '');
                }
                echo "\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
        }

        // imprimimos los totales por año
        foreach ($this->getTotalesAgrupados($agrupados) as $year => $meses) {
            echo ";" . strtoupper(ToolBox::i18n()->trans('totals')) . ";" . $year;
            foreach ($meses as $mes) {
                echo ';' . number_format($mes, FS_NF0, '.', '');
            }
            echo ";\n";
        }
    }

    protected function informeVentasUnidades()
    {
        $data = $this->getInformeVentasUnidadesData();
        if (empty($data)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return;
        }

        $i18n = ToolBox::i18n();
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\""
            . str_replace(' ', '_', strtolower($i18n->trans('units-sale-report'))) . ".csv\"");
        echo 'codcliente;'
            . $i18n->trans('name') . ';'
            . $i18n->trans('reference') . ';'
            . $i18n->trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . $i18n->trans('total') . "\n";

        // agrupamos los datos por cliente, referencia, añi y mes
        $agrupados = $this->getDatosAgrupadosRef($data, 'codcliente');

        // imprimimos las líneas
        foreach ($agrupados as $codcliente => $referencias) {
            foreach ($referencias as $referencia => $years) {
                foreach ($years as $year => $meses) {
                    echo '"' . $codcliente . '";' . $this->getNombreCliente($codcliente) . ';"' . $referencia . '";' . $year;
                    foreach ($meses as $mes) {
                        echo ';' . number_format($mes, FS_NF0, '.', '');
                    }
                    echo "\n";
                }
                echo ";;;;;;;;;;;;;;;;\n";
            }
            echo ";;;;;;;;;;;;;;;;\n";
        }
    }

    /**
     * Obtenemos los valores de los filtros del formulario.
     */
    protected function iniFilters()
    {
        $this->desde = $this->request->get('desde', Date('Y') . '-01-01');
        $this->hasta = $this->request->get('hasta', Date('Y') . '-12-31');
        $this->idempresa = $this->request->get('idempresa', false);
        $this->codpais = $this->request->get('codpais', false);
        $this->codserie = $this->request->get('codserie', false);
        $this->codpago = $this->request->get('codpago', false);
        $this->codagente = $this->request->get('codagente', false);
        $this->codalmacen = $this->request->get('codalmacen', false);
        $this->coddivisa = $this->request->get('coddivisa', AppSettings::get('default', 'coddivisa'));

        $this->cliente = FALSE;
        if ($this->request->get('codcliente')) {
            $this->cliente = new Cliente();
            $this->cliente->loadFromCode($this->request->get('codcliente'));
        }

        $this->shippingAddress = FALSE;
        if ($this->request->get('idcontactoenv')) {
            $this->shippingAddress = new Contacto();
            $this->shippingAddress->loadFromCode($this->request->get('idcontactoenv'));
        }

        $this->billingAddress = FALSE;
        if ($this->request->get('idcontactofact')) {
            $this->billingAddress = new Contacto();
            $this->billingAddress->loadFromCode($this->request->get('idcontactofact'));
        }

        $this->proveedor = FALSE;
        if ($this->request->get('codproveedor')) {
            $this->proveedor = new Proveedor();
            $this->proveedor->loadFromCode($this->request->get('codproveedor'));
        }

        $this->purchase_minimo = $this->request->get('purchase-minimo', false);
        $this->purchase_unidades = (bool)$this->request->get('purchase-unidades', '0');
        $this->sale_minimo = $this->request->get('sale-minimo', false);
        $this->sale_unidades = (bool)$this->request->get('sale-unidades', '0');
        $this->provincia = $this->request->get('provincia', false);
    }
}