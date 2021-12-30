<?php
/**
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Cuenta;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PurchasesResultReport extends Report
{
    public static function render(array $formData)
    {
        self::apply($formData);

        $html = ''
            . '<div class="table-responsive">'
            . '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th></th>'
            . '<th class="porc text-right">%</th>'
            . '<th class="total text-right">' . ToolBox::i18n()->trans('total') . '</th>'
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
            . '<tbody>';

        if (self::$gastos[self::$year]) {
            $html .= ''
                . '<tr>'
                . '<td></td>'
                . '<td></td>';

            for ($x = 0; $x <= 12; $x++) {
                $css = $x == 0 ? 'total' : 'month';
                $money = self::$gastos[self::$year]['total_mes'][$x];
                $lastmoney = self::$gastos[self::$lastyear]['total_mes'][$x];
                $html .= '<td class="' . $css . ' text-right">';
                $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();
                $html .= '<div class="small">';
                $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : self::defaultMoney();
                $html .= ''
                    . '</div>'
                    . '</td>';
            }

            $html .= ''
                . '</tr>';
        }

        foreach (self::$gastos[self::$year]['cuentas'] as $key => $value) {
            $html .= ''
                . '<tr data-toggle="collapse" data-target="#gastos-' . $key . '" class="accordion-toggle cursor-pointer gastos collapsed">'
                . '<td class="title">' . self::$gastos[self::$year]['descripciones'][$key] . '</td>'
                . '<td class="porc text-right">';

            $percentage = (float) self::$gastos[self::$year]['porc_cuenta'][$key];
            $html .= $percentage > 0 ? $percentage . ' %' : self::defaultPerc();

            $html .= ''
                . '</td>'
                . '<td class="total text-right">';

            $money = self::$gastos[self::$year]['total_cuenta'][$key];
            $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();

            $html .= ''
                . '</td>';

            for ($x = 1; $x <= 12; $x++) {
                $html .= '<td class="month text-right">';
                $html .= isset(self::$gastos[self::$year]['total_cuenta_mes'][$key][$x]) ? ToolBox::coins()::format(self::$gastos[self::$year]['total_cuenta_mes'][$key][$x]) : self::defaultMoney();
                $html .= ''
                    . '</td>';
            }

            $html .= ''
                . '</tr>';
        }

        $html .= ''
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

    protected static function build_year($year, $codejercicio)
    {
        $dataBase = new DataBase();

        $date = array(
            'desde' => '',
            'hasta' => '',
        );

        $gastos = array(
            'cuentas' => [],
            'total_cuenta' => [],
            'total_cuenta_mes' => [],
            'total_mes' => [],
            'porc_cuenta' => [],
        );

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
            $gastos['total_mes'][$mes] = 0;

            if ($year) {
                $dia_mes = Report::days_in_month($mes, $year);
                $date['desde'] = date('01-' . $mes . '-' . $year);
                $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

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
                        . " AND asi.codejercicio = " . $codejercicio
                        . " AND codsubcuenta LIKE '6%'";

                    if ($asiento_regularizacion) {
                        $sql .= " AND asi.numero <> " . $dataBase->var2str($asiento_regularizacion);
                    }

                    $sql .= " ORDER BY codsubcuenta";

                    $partidas = $dataBase->select($sql);
                    if ($partidas) {
                        foreach ($partidas as $p) {
                            $codcuenta = substr($p['codsubcuenta'], 0, 3);
                            $pvptotal = (float) $p['debe'] - (float) $p['haber'];
                            $gastos['cuentas'][$codcuenta] = $codcuenta;

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

                            if (isset($gastos['total_mes'][$mes])) {
                                $gastos['total_mes'][$mes] += $pvptotal;
                            } else {
                                $gastos['total_mes'][$mes] = $pvptotal;
                            }

                            $gastos_total_meses = $pvptotal + $gastos_total_meses;
                        }
                    }
                }

                // Las descripciones solo las necesitamos en el año seleccionado,
                // en el año anterior se omite
                if ($year == self::$year) {
                    // GASTOS: Creamos un array con las descripciones de las cuentas
                    foreach ($gastos['cuentas'] as $codcuenta => $arraycuenta) {
                        $gastos['descripciones'][$codcuenta] = '-';
                        $cuenta = new Cuenta();
                        $where = [
                            new DataBaseWhere('codcuenta', $codcuenta),
                            new DataBaseWhere('codejercicio', $codejercicio)
                        ];

                        if ($cuenta->loadFromCode('', $where)) {
                            $gastos['descripciones'][$codcuenta] = $cuenta->descripcion;
                        }
                    }
                }
            }
        }

        /**
         *  TOTALES GLOBALES
         * *****************************************************************
         */
        $gastos['total_mes'][0] = round($gastos_total_meses, FS_NF0);
        $gastos['total_mes']['media'] = round($gastos_total_meses / 12, FS_NF0);

        /**
         *  PORCENTAJES
         * *****************************************************************
         */

        // GASTOS: Calculamos los porcentajes con los totales globales
        foreach ($gastos['cuentas'] as $codcuenta => $cuenta) {
            if ($gastos_total_meses != 0) {
                $gastos['porc_cuenta'][$codcuenta] = round($gastos['total_cuenta'][$codcuenta] * 100 / $gastos_total_meses, FS_NF0);
            }
        }

        // Variables globales para usar en la vista
        self::$gastos[$year] = $gastos;
    }
}