<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Proveedor;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportBreakdown extends Controller
{
    public $cliente;
    public $codagente;
    public $codalmacen;
    public $coddivisa;
    public $codpais;
    public $codpago;
    public $codserie;
    public $desde;
    public $estado;
    public $hasta;
    public $idempresa;
    public $proveedor;
    public $provincia;
    public $purchase_minimo;
    public $purchase_unidades;
    public $sale_minimo;
    public $sale_unidades;
    protected $where_compras;
    protected $where_compras_nf;
    protected $where_ventas;
    protected $where_ventas_nf;

    public function getPageData()
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
            case 'autocomplete-customer':
                $this->autocompleteCustomerAction();
                return;

            case 'autocomplete-supplier':
                $this->autocompleteSupplierAction();
                return;

            case 'get-provincies':
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
        $this->setWhere();
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

    protected function informeCompras()
    {
        $data = $this->getInformeComprasData();
        if (empty($data)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return;
        }

        $i18n = ToolBox::i18n();
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\""
            . str_replace(' ', '_', strtolower($i18n->trans('purchase-report'))) . ".csv\"");
        echo 'codproveedor;'
            . $i18n->trans('name') . ';'
            . $i18n->trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . $i18n->trans('total') . ';'
            . "%VAR\n";

        $proveedor = new proveedor();
        $stats = array();
        foreach ($data as $d) {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if (!isset($stats[$d['codproveedor']][$anyo])) {
                $stats[$d['codproveedor']][$anyo] = array(
                    1 => 0,
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
                    12 => 0,
                    13 => 0,
                    14 => 0
                );
            }

            $stats[$d['codproveedor']][$anyo][$mes] += floatval($d['total']);
            $stats[$d['codproveedor']][$anyo][13] += floatval($d['total']);
        }

        $totales = array();
        foreach ($stats as $i => $value) {
            // calculamos la variación
            $anterior = 0;
            foreach (array_reverse($value, TRUE) as $j => $value2) {
                if ($anterior > 0) {
                    $value[$j][14] = ($value2[13] * 100 / $anterior) - 100;
                }

                $anterior = $value2[13];

                if (isset($totales[$j])) {
                    foreach ($value2 as $k => $value3) {
                        $totales[$j][$k] += $value3;
                    }
                } else {
                    $totales[$j] = $value2;
                }
            }

            $pro = $proveedor->get($i);
            foreach ($value as $j => $value2) {
                if ($pro) {
                    echo '"' . $i . '";' . ToolBox::utils()::fixHtml($pro->nombre) . ';' . $j;
                } else {
                    echo '"' . $i . '";-;' . $j;
                }

                foreach ($value2 as $value3) {
                    echo ';' . number_format($value3, FS_NF0, '.', '');
                }

                echo "\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
        }

        foreach (array_reverse($totales, TRUE) as $i => $value) {
            echo ";" . strtoupper($i18n->trans('totals')) . ";" . $i;
            $l_total = 0;
            foreach ($value as $j => $value3) {
                if ($j < 13) {
                    echo ';' . number_format($value3, FS_NF0, '.', '');
                    $l_total += $value3;
                }
            }
            echo ";" . number_format($l_total, FS_NF0, '.', '') . ";\n";
        }
    }

    protected function informeComprasUnidades()
    {
        $data = $this->getInformeComprasUnidadesData();
        if (empty($data)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return;
        }

        $i18n = ToolBox::i18n();
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\""
            . str_replace(' ', '_', strtolower($i18n->trans('units-purchase-report'))) . ".csv\"");
        echo $i18n->trans('warehouse') . ';'
            . 'codproveedor;'
            . $i18n->trans('name') . ';'
            . $i18n->trans('reference') . ';'
            . $i18n->trans('description') . ';'
            . $i18n->trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . $i18n->trans('total') . ';'
            . "%VAR\n";

        $proveedor = new Proveedor();
        $stats = array();

        foreach ($data as $d) {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if (!isset($stats[$d['codproveedor']][$d['referencia']][$anyo])) {
                $stats[$d['codproveedor']][$d['referencia']][$anyo] = array(
                    1 => 0,
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
                    12 => 0,
                    13 => 0,
                    14 => 0,
                    15 => $d['codalmacen'],
                    16 => $d['descripcion'],
                );
            }

            $stats[$d['codproveedor']][$d['referencia']][$anyo][$mes] += floatval($d['total']);
            $stats[$d['codproveedor']][$d['referencia']][$anyo][13] += floatval($d['total']);
        }

        foreach ($stats as $i => $value) {
            $pro = $proveedor->get($i);
            foreach ($value as $j => $value2) {
                // calculamos la variación
                $anterior = 0;
                foreach (array_reverse($value2, TRUE) as $k => $value3) {
                    if ($anterior > 0) {
                        $value2[$k][14] = ($value3[13] * 100 / $anterior) - 100;
                    }
                    $anterior = $value3[13];
                }

                foreach ($value2 as $k => $value3) {
                    if ($pro) {
                        echo '"' . $value3[15] . '";' . '"' . $i . '";' . ToolBox::utils()::fixHtml($pro->nombre) . ';"' . $j . '";' . '"' . $value3[16] . '"' . ';' . $k;
                    } else {
                        echo '"' . $value3[15] . '";' . '"' . $i . '";-;"' . $j . '";' . '"' . $value3[16] . '"' . ';' . $k;
                    }

                    foreach ($value3 as $x => $value4) {
                        if ($x < 15) {
                            echo ';' . number_format($value4, FS_NF0, '.', '');
                        }
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

        $i18n = ToolBox::i18n();
        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\""
            . str_replace(' ', '_', strtolower($i18n->trans('sale-report'))) . ".csv\"");
        echo $i18n->trans('warehouse') . ';'
            . 'codcliente;'
            . $i18n->trans('name') . ';'
            . $i18n->trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . $i18n->trans('total') . ';'
            . "%VAR\n";

        $cliente = new Cliente();
        $stats = array();
        foreach ($data as $d) {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if (!isset($stats[$d['codcliente']][$anyo])) {
                $stats[$d['codcliente']][$anyo] = array(
                    1 => 0,
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
                    12 => 0,
                    13 => 0,
                    14 => 0,
                    15 => $d['codalmacen']
                );
            }

            $stats[$d['codcliente']][$anyo][$mes] += floatval($d['total']);
            $stats[$d['codcliente']][$anyo][13] += floatval($d['total']);
        }

        $totales = array();
        foreach ($stats as $i => $value) {
            // calculamos la variación y los totales
            $anterior = 0;
            foreach (array_reverse($value, TRUE) as $j => $value2) {
                if ($anterior > 0) {
                    $value[$j][14] = ($value2[13] * 100 / $anterior) - 100;
                }

                $anterior = $value2[13];

                if (isset($totales[$j])) {
                    foreach ($value2 as $k => $value3) {
                        $totales[$j][$k] += $value3;
                    }
                } else {
                    $totales[$j] = $value2;
                }
            }

            $cli = $cliente->get($i);
            foreach ($value as $j => $value2) {
                if ($cli) {
                    echo '"' . $value2[15] . '";' . '"' . $i . '";' . ToolBox::utils()::fixHtml($cli->nombre) . ';' . $j;
                } else {
                    echo '"' . $value2[15] . '";' . '"' . $i . '";-;' . $j;
                }

                foreach ($value2 as $x => $value3) {
                    if ($x < 15) {
                        echo ';' . number_format($value3, FS_NF0, '.', '');
                    }
                }
                echo "\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
        }
        foreach (array_reverse($totales, TRUE) as $i => $value) {
            echo ";;" . strtoupper($i18n->trans('totals')) . ";" . $i;
            $l_total = 0;
            foreach ($value as $j => $value3) {
                if ($j < 13) {
                    echo ';' . number_format($value3, FS_NF0, '.', '');
                }
            }
            echo ";" . number_format($l_total, FS_NF0, '.', '') . ";\n";
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
        echo $i18n->trans('warehouse') . ';'
            . 'codcliente;'
            . $i18n->trans('name') . ';'
            . $i18n->trans('reference') . ';'
            . $i18n->trans('description') . ';'
            . $i18n->trans('year') . ';'
            . $this->getMonthsTitlesRows()
            . $i18n->trans('total') . ';'
            . "%VAR\n";

        $cliente = new Cliente();
        $stats = array();
        foreach ($data as $d) {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if (!isset($stats[$d['codcliente']][$d['referencia']][$anyo])) {
                $stats[$d['codcliente']][$d['referencia']][$anyo] = array(
                    1 => 0,
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
                    12 => 0,
                    13 => 0,
                    14 => 0,
                    15 => $d['codalmacen'],
                    16 => $d['descripcion'],
                );
            }

            $stats[$d['codcliente']][$d['referencia']][$anyo][$mes] += floatval($d['total']);
            $stats[$d['codcliente']][$d['referencia']][$anyo][13] += floatval($d['total']);
        }

        foreach ($stats as $i => $value) {
            $cli = $cliente->get($i);
            foreach ($value as $j => $value2) {
                // calculamos la variación
                $anterior = 0;
                foreach (array_reverse($value2, TRUE) as $k => $value3) {
                    if ($anterior > 0) {
                        $value2[$k][14] = ($value3[13] * 100 / $anterior) - 100;
                    }

                    $anterior = $value3[13];
                }

                foreach ($value2 as $k => $value3) {
                    if ($cli) {
                        echo '"' . $value3[15] . '";' . '"' . $i . '";' . Toolbox::utils()::fixHtml($cli->nombre) . ';"' . $j . '";' . '"' . $value3[16] . '"' . ';' . $k;
                    } else {
                        echo '"' . $value3[15] . '";' . '"' . $i . '";-;"' . $j . '";' . '"' . $value3[16] . '"' . ';' . $k;
                    }

                    foreach ($value3 as $x => $value4) {
                        if ($x < 15) {
                            echo ';' . number_format($value4, FS_NF0, '.', '');
                        }
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

        $this->proveedor = FALSE;
        if ($this->request->get('codproveedor')) {
            $this->proveedor = new Proveedor();
            $this->proveedor->loadFromCode($this->request->get('codproveedor'));
        }

        $this->estado = $this->request->get('estado', false);
        $this->purchase_minimo = $this->request->get('purchase-minimo', false);
        $this->purchase_unidades = (bool)$this->request->get('purchase-unidades', '0');
        $this->sale_minimo = $this->request->get('sale-minimo', false);
        $this->sale_unidades = (bool)$this->request->get('sale-unidades', '0');
        $this->provincia = $this->request->get('provincia', false);
    }

    protected function getInformeComprasData(): array
    {
        $sql = "SELECT codproveedor,fecha,SUM(neto) as total FROM facturasprov"
            . " WHERE fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND idempresa = " . $this->dataBase->var2str($this->idempresa);

        if ($this->codserie) {
            $sql .= " AND codserie = " . $this->dataBase->var2str($this->codserie);
        }

        if ($this->codagente) {
            $sql .= " AND codagente = " . $this->dataBase->var2str($this->codagente);
        }

        if ($this->proveedor) {
            $sql .= " AND codproveedor = " . $this->dataBase->var2str($this->proveedor->codproveedor);
        }

        if ($this->purchase_minimo) {
            $sql .= " AND neto > " . $this->dataBase->var2str($this->purchase_minimo);
        }

        $sql .= " GROUP BY codproveedor,fecha ORDER BY codproveedor ASC, fecha DESC;";
        return $this->dataBase->select($sql);
    }

    protected function getInformeComprasUnidadesData(): array
    {
        $sql = "SELECT f.codalmacen,f.codproveedor,f.fecha,l.referencia,l.descripcion,SUM(l.cantidad) as total"
            . " FROM facturasprov f, lineasfacturasprov l"
            . " WHERE f.idfactura = l.idfactura AND l.referencia IS NOT NULL"
            . " AND f.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . " AND f.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND f.fecha <= " . $this->dataBase->var2str($this->hasta);

        if ($this->codserie) {
            $sql .= " AND f.codserie = " . $this->dataBase->var2str($this->codserie);
        }

        if ($this->codalmacen) {
            $sql .= " AND f.codalmacen = " . $this->dataBase->var2str($this->codalmacen);
        }

        if ($this->codagente) {
            $sql .= " AND f.codagente = " . $this->dataBase->var2str($this->codagente);
        }

        if ($this->proveedor) {
            $sql .= " AND codproveedor = " . $this->dataBase->var2str($this->proveedor->codproveedor);
        }

        if ($this->purchase_minimo) {
            $sql .= " AND l.cantidad > " . $this->dataBase->var2str($this->purchase_minimo);
        }

        $sql .= " GROUP BY f.codalmacen,f.codproveedor,f.fecha,l.referencia,l.descripcion"
            . " ORDER BY f.codproveedor ASC, l.referencia ASC, f.fecha DESC;";
        return $this->dataBase->select($sql);
    }

    protected function getInformeVentasData(): array
    {
        $sql = "SELECT codalmacen,codcliente,fecha,SUM(neto) as total FROM facturascli"
            . " WHERE fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND idempresa = " . $this->dataBase->var2str($this->idempresa);

        $sql .= $this->loadDataInformeVentasWhere($sql)
            . " GROUP BY codalmacen,codcliente,fecha ORDER BY codcliente ASC, fecha DESC;";
        return $this->dataBase->select($sql);
    }

    protected function getInformeVentasUnidadesData(): array
    {
        $sql = "SELECT f.codalmacen,f.codcliente,f.fecha,l.referencia,l.descripcion,SUM(l.cantidad) as total"
            . " FROM facturascli f, lineasfacturascli l"
            . " WHERE f.idfactura = l.idfactura AND l.referencia IS NOT NULL"
            . " AND f.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . " AND f.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND f.fecha <= " . $this->dataBase->var2str($this->hasta);

        $sql .= $this->loadDataInformeVentasWhere($sql)
            . " GROUP BY f.codalmacen,f.codcliente,f.fecha,l.referencia,l.descripcion"
            . " ORDER BY f.codcliente ASC, l.referencia ASC, f.fecha DESC;";
        return $this->dataBase->select($sql);
    }

    protected function loadDataInformeVentasWhere($sql): string
    {
        $oldSql = $sql;
        if ($this->codpais) {
            $sql .= " AND codpais = " . $this->dataBase->var2str($this->codpais);
        }

        if ($this->provincia) {
            $sql .= " AND lower(provincia) = lower(" . $this->dataBase->var2str($this->provincia) . ")";
        }

        if ($this->cliente) {
            $sql .= " AND codcliente = " . $this->dataBase->var2str($this->cliente->codcliente);
        }

        if ($this->codserie) {
            $sql .= " AND codserie = " . $this->dataBase->var2str($this->codserie);
        }

        if ($this->codalmacen) {
            $sql .= " AND codalmacen = " . $this->dataBase->var2str($this->codalmacen);
        }

        if ($this->codagente) {
            $sql .= " AND codagente = " . $this->dataBase->var2str($this->codagente);
        }

        if ($this->sale_minimo) {
            $sql .= " AND neto > " . $this->dataBase->var2str($this->sale_minimo);
        }

        return $sql === $oldSql ? '' : $sql;
    }

    /**
     * Construimos sentencias where para las consultas sql.
     */
    protected function setWhere()
    {
        $this->where_compras = " WHERE fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND fecha <= " . $this->dataBase->var2str($this->hasta);

        // nos guardamos un where sin fechas
        $this->where_compras_nf = " WHERE 1 = 1";

        if ($this->codserie) {
            $this->where_compras .= " AND codserie = " . $this->dataBase->var2str($this->codserie);
            $this->where_compras_nf .= " AND codserie = " . $this->dataBase->var2str($this->codserie);
        }

        if ($this->codagente) {
            $this->where_compras .= " AND codagente = " . $this->dataBase->var2str($this->codagente);
            $this->where_compras_nf .= " AND codagente = " . $this->dataBase->var2str($this->codagente);
        }

        if ($this->codalmacen) {
            $this->where_compras .= " AND codalmacen = " . $this->dataBase->var2str($this->codalmacen);
            $this->where_compras_nf .= " AND codalmacen = " . $this->dataBase->var2str($this->codalmacen);
        }

        if ($this->coddivisa) {
            $this->where_compras .= " AND coddivisa = " . $this->dataBase->var2str($this->coddivisa);
            $this->where_compras_nf .= " AND coddivisa = " . $this->dataBase->var2str($this->coddivisa);
        }

        if ($this->codpago) {
            $this->where_compras .= " AND codpago = " . $this->dataBase->var2str($this->codpago);
            $this->where_compras_nf .= " AND codpago = " . $this->dataBase->var2str($this->codpago);
        }

        $this->where_ventas = $this->where_compras;
        $this->where_ventas_nf = $this->where_compras_nf;

        if ($this->cliente) {
            $this->where_ventas .= " AND codcliente = " . $this->dataBase->var2str($this->cliente->codcliente);
            $this->where_ventas_nf .= " AND codcliente = " . $this->dataBase->var2str($this->cliente->codcliente);
        }

        if ($this->proveedor) {
            $this->where_compras .= " AND codproveedor = " . $this->dataBase->var2str($this->proveedor->codproveedor);
            $this->where_compras_nf .= " AND codproveedor = " . $this->dataBase->var2str($this->proveedor->codproveedor);
        }

        if ($this->estado) {
            $estado = $this->estado == 'pagada';
            $this->where_compras .= " AND pagada = " . $this->dataBase->var2str($estado);
            $this->where_ventas .= " AND pagada = " . $this->dataBase->var2str($estado);
        }
    }
}