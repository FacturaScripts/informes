<?php
/**
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SalesResultReport extends Report
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

        if (self::$ventas[self::$year]) {
            $html .= ''
                . '<tr>'
                . '<td></td>'
                . '<td></td>';

            for ($x = 0; $x <= 12; $x++) {
                $css = $x == 0 ? 'total' : 'month';
                $money = self::$ventas[self::$year]['total_mes'][$x];
                $lastmoney = self::$ventas[self::$lastyear]['total_mes'][$x];
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

        foreach (self::$ventas[self::$year]['familias'] as $key => $value) {
            $html .= ''
                . '<tr data-toggle="collapse" data-target="#ventas-' . $key . '" class="accordion-toggle cursor-pointer ventas collapsed">'
                . '<td class="title">' . self::$ventas[self::$year]['descripciones'][$key] . '</td>'
                . '<td class="porc text-right">';

            $percentage = (float) self::$ventas[self::$year]['porc_fam'][$key];
            $html .= $percentage > 0 ? $percentage . ' %' : self::defaultPerc();

            $html .= ''
                . '</td>'
                . '<td class="total text-right">';

            $money = self::$ventas[self::$year]['total_fam'][$key];
            $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();

            $html .= ''
                . '</td>';

            for ($x = 1; $x <= 12; $x++) {
                $html .= '<td class="month text-right">';
                $html .= isset(self::$ventas[self::$year]['total_fam_mes'][$key][$x]) ? ToolBox::coins()::format(self::$ventas[self::$year]['total_fam_mes'][$key][$x]) : self::defaultMoney();
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

        self::$familias = [];
        foreach (CodeModel::all('familias', 'codfamilia', 'descripcion', false) as $row) {
            self::$familias[$row->code] = $row->description;
        }

        /// Llamamos a la función que crea los arrays con los datos,
        /// pasandole el año seleccionado y el anterior.
        self::build_year(self::$year, self::$codejercicio);
        self::build_year(self::$lastyear, self::$codejercicio_ant);
    }

    protected static function build_data($dl)
    {
        $referencia = 'SIN_REFERENCIA';
        $codfamilia = 'SIN_FAMILIA';
        $familia = 'SIN_FAMILIA';

        $pvptotal = round($dl['pvptotal'], FS_NF0);
        $producto = new Producto();
        $variante = new Variante();

        if ($variante->loadFromCode($dl['referencia']) && $producto->loadFromCode($variante->idproducto)) {
            $referencia = $variante->referencia;
            if ($producto->codfamilia) {
                $codfamilia = $producto->codfamilia;
                if (isset(self::$familias[$codfamilia])) {
                    $familia = self::$familias[$codfamilia];
                } else {
                    $modelFamilia = new Familia();
                    $modelFamilia->loadFromCode($producto->codfamilia);
                    $familia = $modelFamilia->descripcion;
                }
            }
        }

        return array('ref' => $referencia, 'codfamilia' => $codfamilia, 'familia' => $familia, 'pvptotal' => $pvptotal);
    }

    protected static function build_year($year, $codejercicio)
    {
        $dataBase = new DataBase();

        $date = array(
            'desde' => '',
            'hasta' => '',
        );
        $ventas = array(
            'familias' => [],
            'total_fam' => [],
            'total_fam_mes' => [],
            'porc_fam' => [],
        );

        /// inicializamos las familias
        foreach (CodeModel::all('familias', 'codfamilia', 'descripcion', false) as $row) {
            $ventas['familias'][$row->code] = [];
            $ventas['descripciones'][$row->code] = $row->description;
            $ventas['porc_fam'][$row->code] = 0;
            $ventas['total_fam'][$row->code] = 0;
        }

        $ventas_total_meses = 0;

        // Recorremos los meses y ejecutamos una consulta filtrando por el mes
        for ($mes = 1; $mes <= 12; $mes++) {
            /// inicializamos
            $ventas['total_mes'][$mes] = 0;

            if ($year) {
                $dia_mes = Report::days_in_month($mes, $year);
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
                    . " AND fc.codejercicio = " . $codejercicio
                    . " group by lfc.referencia";

                // VENTAS: Recorremos lineasfacturascli y montamos arrays
                $lineas = $dataBase->select($sql);
                if ($lineas) {
                    foreach ($lineas as $dl) {
                        $data = self::build_data($dl);
                        $pvptotal = (float) $data['pvptotal'];
                        $referencia = $data['ref'];
                        $codfamilia = $data['codfamilia'];

                        // Arrays con los datos a mostrar
                        if (isset($ventas['total_fam_mes'][$codfamilia][$mes])) {
                            $ventas['total_fam_mes'][$codfamilia][$mes] += $pvptotal;
                        } else {
                            $ventas['total_fam_mes'][$codfamilia][$mes] = $pvptotal;
                        }

                        if (isset($ventas['total_fam'][$codfamilia])) {
                            $ventas['total_fam'][$codfamilia] += $pvptotal;
                        } else {
                            $ventas['total_fam'][$codfamilia] = $pvptotal;
                        }

                        $ventas['total_mes'][$mes] = $pvptotal + $ventas['total_mes'][$mes];
                        $ventas_total_meses = $pvptotal + $ventas_total_meses;

                        // Array temporal con los totales (falta añadir descripción familia)
                        $ventas['familias'][$codfamilia][$referencia][$mes] = array('pvptotal' => $pvptotal);
                    }
                }

                // Las descripciones solo las necesitamos en el año seleccionado,
                // en el año anterior se omite
                if ($year == self::$year) {
                    // Recorremos ventas['familias'] crear un array con las descripciones de las familias
                    foreach ($ventas['familias'] as $codfamilia => $familia) {
                        foreach ($familia as $referencia => $array) {
                            $dl['referencia'] = $referencia;
                            $data = self::build_data($dl);

                            $ventas['descripciones'][$codfamilia] = $data['familia'];
                        }
                    }
                }
            }
        }

        /**
         *  TOTALES GLOBALES
         * *****************************************************************
         */
        $ventas['total_mes'][0] = round($ventas_total_meses, FS_NF0);
        $ventas['total_mes']['media'] = round($ventas_total_meses / 12, FS_NF0);

        /**
         *  PORCENTAJES
         * *****************************************************************
         */
        // VENTAS: Calculamos los porcentajes con los totales globales
        foreach ($ventas['familias'] as $codfamilia => $familias) {
            if ($ventas_total_meses != 0) {
                $ventas['porc_fam'][$codfamilia] = round($ventas['total_fam'][$codfamilia] * 100 / $ventas_total_meses, FS_NF0);
            }
        }

        // Variables globales para usar en la vista
        self::$ventas[$year] = $ventas;
    }
}