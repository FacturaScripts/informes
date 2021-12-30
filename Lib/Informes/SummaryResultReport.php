<?php
/**
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Asiento;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SummaryResultReport
{
    protected static $charts;
    protected static $codejercicio;
    protected static $codejercicio_ant;
    protected static $lastyear;
    protected static $year;
    protected static $ventas;
    protected static $gastos;
    protected static $resultado;

    public static function render(array $formData)
    {
        self::apply($formData);

        $number = '<span style="color:#ccc;">' . ToolBox::coins()::format(0) . '</span>';

        $html = ''
            . '<div class="table-responsive">'
            . '<table class="table mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th class="card-title h4"><b>' . ToolBox::i18n()->trans('summary') . '</b></th>'
            . '<th class="porc table-info text-right">' . ToolBox::i18n()->trans('monthly-average') . '</th>'
            . '<th class="total table-info text-right">' . ToolBox::i18n()->trans('total') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('january') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('february') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('march') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('april') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('may') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('june') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('july') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('august') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('september') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('october') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('november') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('december') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>'
            . '<tr>'
            . '<td class="title align-middle"><b>' . ToolBox::i18n()->trans('sales') . '</b></td>'
            . '<td class="porc table-info text-right">';

        $money = self::$ventas[self::$year]['total_mes']['media'];
        $lastmoney = self::$ventas[self::$lastyear]['total_mes']['media'];
        $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : $number;
        $html .= '<div class="small">';
        $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : $number;

        $html .= ''
            . '</div>'
            . '</td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'total table-info' : 'month';
            $money = self::$ventas[self::$year]['total_mes'][$x];
            $lastmoney = self::$ventas[self::$lastyear]['total_mes'][$x];
            $html .= '<td class="' . $css . ' text-right">';
            $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : $number;
            $html .= '<div class="small">';
            $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : $number;
            $html .= ''
                . '</div>'
                . '</td>';
        }

        $html .= '</tr>'
            . '<tr>'
            . '<td class="title align-middle"><b>' . ToolBox::i18n()->trans('purchases') . '</b></td>'
            . '<td class="porc table-info text-right">';

        $money = self::$gastos[self::$year]['total_mes']['media'];
        $lastmoney = self::$gastos[self::$lastyear]['total_mes']['media'];
        $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : $number;
        $html .= '<div class="small">';
        $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : $number;

        $html .= ''
            . '</div>'
            . '</td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'total table-info' : 'month';
            $money = self::$gastos[self::$year]['total_mes'][$x];
            $lastmoney = self::$gastos[self::$lastyear]['total_mes'][$x];
            $html .= '<td class="' . $css . ' text-right">';
            $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : $number;
            $html .= '<div class="small">';
            $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : $number;
            $html .= ''
                . '</div>'
                . '</td>';
        }

        $html .= ''
            . '</tr>'
            . '<tr>'
            . '<td class="porc align-middle"><b>' . ToolBox::i18n()->trans('result') . '</b></td>'
            . '<td class="title table-info text-right">';

        $money = self::$resultado[self::$year]['total_mes']['media'];
        $lastmoney = self::$resultado[self::$lastyear]['total_mes']['media'];
        $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : $number;
        $html .= '<div class="small">';
        $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : $number;

        $html .= ''
            . '</div>'
            . '</td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'total table-info' : 'month';
            $money = self::$resultado[self::$year]['total_mes'][$x];
            $lastmoney = self::$resultado[self::$lastyear]['total_mes'][$x];
            $html .= '<td class="' . $css . ' text-right">';
            $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : $number;
            $html .= '<div class="small">';
            $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : $number;
            $html .= ''
                . '</div>'
                . '</td>';
        }

        $html .= '</tr>'
            . '</tbody>'
            . '</table>'
            . '</div>';

        return $html;
    }

    protected static function apply(array $formData)
    {
        $eje = new Ejercicio();
        $eje->loadFromCode($formData['codejercicio']);

        $year = date('Y', strtotime($eje->fechafin));

        /// seleccionamos el año anterior
        self::$codejercicio = FALSE;
        self::$codejercicio_ant = FALSE;
        self::$lastyear = FALSE;
        self::$year = FALSE;

        $modelEjerc = new Ejercicio();
        $where = [new DataBaseWhere('idempresa', $eje->idempresa)];
        $order = ['fechainicio' => 'desc'];

        foreach ($modelEjerc->all($where, $order, 0, 0) as $eje) {
            if ($eje->codejercicio == $formData['codejercicio'] or date('Y', strtotime($eje->fechafin)) == $year) {
                self::$codejercicio = $eje->codejercicio;
                self::$year = date('Y', strtotime($eje->fechafin));
            } else if (self::$year) {
                self::$codejercicio_ant = $eje->codejercicio;
                self::$lastyear = date('Y', strtotime($eje->fechafin));
                break;
            }
        }

        /// Llamamos a la función que crea los arrays con los datos,
        /// pasandole el año seleccionado y el anterior.
        self::build_year(self::$year, self::$codejercicio);
        self::build_year(self::$lastyear, self::$codejercicio_ant);
    }

    protected static function days_in_month($month, $year)
    {
        // calculate number of days in a month CALC_GREGORIAN
        return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
    }

    protected static function build_year($year, $codejercicio)
    {
        $dataBase = new DataBase();

        $date = array(
            'desde' => '',
            'hasta' => '',
        );
        $ventas = array(
            'total_mes' => [],
        );

        $gastos = array(
            'cuentas' => [],
            'total_cuenta' => [],
            'total_cuenta_mes' => [],
            'total_subcuenta' => [],
            'total_mes' => [],
            'porc_cuenta' => [],
            'porc_subcuenta' => [],
        );
        $resultado = array(
            'total_mes' => [],
        );
        self::$charts = array(
            'totales' => [],
            'distribucion' => [],
        );
        $ventas_total_meses = 0;
        $gastos_total_meses = 0;

        $asiento = new Asiento;
        $where = [
            new DataBaseWhere('codejercicio', $codejercicio),
            new DataBaseWhere('operacion', 'R')
        ];
        $asiento_regularizacion = $asiento->loadFromCode('', $where) ? intval($asiento->numero) : 0;

        // Recorremos los meses y ejecutamos una consulta filtrando por el mes
        for ($mes = 1; $mes <= 12; $mes++) {
            /// inicializamos
            $ventas['total_mes'][$mes] = 0;
            $gastos['total_mes'][$mes] = 0;
            $resultado['total_mes'][$mes] = 0;

            if ($year) {
                $dia_mes = self::days_in_month($mes, $year);
                $date['desde'] = date('01-' . $mes . '-' . $year);
                $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

                /**
                 *  VENTAS: Consulta con las lineasfacturascli
                 * *****************************************************************
                 */
                $sql = "select lfc.referencia, sum(lfc.pvptotal) as pvptotal from lineasfacturascli as lfc"
                    . " LEFT JOIN facturascli as fc ON lfc.idfactura = fc.idfactura"
                    . " where fc.fecha >= " . $dataBase->var2str($date['desde'])
                    . " AND fc.fecha <= " . $dataBase->var2str($date['hasta'])
                    . " group by lfc.referencia";

                // VENTAS: Recorremos lineasfacturascli y montamos arrays
                $lineas = $dataBase->select($sql);
                if ($lineas) {
                    foreach ($lineas as $dl) {
                        $pvptotal = round($dl['pvptotal'], FS_NF0);
                        $ventas['total_mes'][$mes] = $pvptotal + $ventas['total_mes'][$mes];
                        $ventas_total_meses = $pvptotal + $ventas_total_meses;
                    }
                }

                if ($dataBase->tableExists('partidas')) {
                    /**
                     *  GASTOS
                     * *****************************************************************
                     */
                    // Gastos: Consulta de las partidas y asientos del grupo 6
                    $sql = "select * from partidas as par"
                        . " LEFT JOIN asientos as asi ON par.idasiento = asi.idasiento"
                        . " where asi.fecha >= " . $dataBase->var2str($date['desde'])
                        . " AND asi.fecha <= " . $dataBase->var2str($date['hasta'])
                        . " AND codsubcuenta LIKE '6%'";

                    if ($asiento_regularizacion) {
                        $sql .= " AND asi.numero <> " . $dataBase->var2str($asiento_regularizacion);
                    }

                    $sql .= " ORDER BY codsubcuenta";

                    $partidas = $dataBase->select($sql);
                    if ($partidas) {
                        foreach ($partidas as $p) {
                            $codcuenta = substr($p['codsubcuenta'], 0, 3);
                            $codsubcuenta = $p['codsubcuenta'];
                            $pvptotal = (float)$p['debe'] - (float)$p['haber'];

                            // Array con los datos a mostrar
                            if (isset($gastos['total_cuenta_mes'][$codcuenta][$mes])) {
                                $gastos['total_cuenta_mes'][$codcuenta][$mes] += $pvptotal;
                            } else {
                                $gastos['total_cuenta_mes'][$codcuenta][$mes] = $pvptotal;
                            }

                            if (isset($gastos['total_cuenta'][$codcuenta])) {
                                $gastos['total_cuenta'][$codcuenta] += $pvptotal;
                            } else {
                                $gastos['total_cuenta'][$codcuenta] = $pvptotal;
                            }

                            if (isset($gastos['total_subcuenta'][$codcuenta][$codsubcuenta])) {
                                $gastos['total_subcuenta'][$codcuenta][$codsubcuenta] += $pvptotal;
                            } else {
                                $gastos['total_subcuenta'][$codcuenta][$codsubcuenta] = $pvptotal;
                            }

                            if (isset($gastos['total_mes'][$mes])) {
                                $gastos['total_mes'][$mes] += $pvptotal;
                            } else {
                                $gastos['total_mes'][$mes] = $pvptotal;
                            }

                            $gastos_total_meses = $pvptotal + $gastos_total_meses;

                            if (isset($gastos['cuentas'][$codcuenta][$codsubcuenta][$mes])) {
                                $gastos['cuentas'][$codcuenta][$codsubcuenta][$mes]['pvptotal'] += $pvptotal;
                            } else {
                                $gastos['cuentas'][$codcuenta][$codsubcuenta][$mes]['pvptotal'] = $pvptotal;
                            }
                        }
                    }
                }
            }

            /**
             *  RESULTADOS
             * *****************************************************************
             */
            $resultado['total_mes'][$mes] = round($ventas['total_mes'][$mes] - $gastos['total_mes'][$mes], FS_NF0);
        }

        /**
         *  TOTALES GLOBALES
         * *****************************************************************
         */
        $ventas['total_mes'][0] = round($ventas_total_meses, FS_NF0);
        $ventas['total_mes']['media'] = round($ventas_total_meses / 12, FS_NF0);
        $gastos['total_mes'][0] = round($gastos_total_meses, FS_NF0);
        $gastos['total_mes']['media'] = round($gastos_total_meses / 12, FS_NF0);
        $resultado['total_mes'][0] = round($ventas_total_meses - $gastos_total_meses, FS_NF0);
        $resultado['total_mes']['media'] = round(($ventas_total_meses - $gastos_total_meses) / 12, FS_NF0);

        // Variables globales para usar en la vista
        self::$ventas[$year] = $ventas;
        self::$gastos[$year] = $gastos;
        self::$resultado[$year] = $resultado;
    }
}