<?php
/**
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Dinamic\Model\Variante;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ResultReport
{
    protected static $codejercicio;
    protected static $codejercicio_ant;
    protected static $gastos;
    protected static $lastyear;
    protected static $parent_codcuenta;
    protected static $parent_codfamilia;
    protected static $resultado;
    protected static $ventas;
    protected static $year;

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

        self::$parent_codcuenta = isset($formData['parent_codcuenta']) ? (string) $formData['parent_codcuenta'] : null;
        self::$parent_codfamilia = isset($formData['parent_codfamilia']) ? (string) $formData['parent_codfamilia'] : null;

        /// Llamamos a la función que crea los arrays con los datos,
        /// pasandole el año seleccionado y el anterior.
        switch ($formData['action']) {
            case 'load-account':
            case 'load-purchases':
                self::purchases_build_year(self::$year, self::$codejercicio);
                self::purchases_build_year(self::$lastyear, self::$codejercicio_ant);
                break;
            case 'load-family':
            case 'load-sales':
                self::sales_build_year(self::$year, self::$codejercicio);
                self::sales_build_year(self::$lastyear, self::$codejercicio_ant);
                break;
            case 'load-summary':
                self::summary_build_year(self::$year, self::$codejercicio);
                self::summary_build_year(self::$lastyear, self::$codejercicio_ant);
                break;
        }
    }

    protected static function build_data($dl)
    {
        $pvptotal = round($dl['pvptotal'], FS_NF0);
        $referencia = $dl['referencia'];
        $producto = new Producto();
        $variante = new Variante();

        $articulo = false;
        if ($referencia) {
            if ($variante->loadFromCode($referencia)) {
                $articulo = true;
                $producto->loadFromCode($variante->idproducto);
                $art_desc = $producto->descripcion;
                $codfamilia = $producto->codfamilia;
                if (empty($codfamilia)) {
                    $codfamilia = 'SIN_FAMILIA';
                    $familia = 'Sin Familia';
                } else {
                    $modelFamilia = new Familia();
                    $modelFamilia->loadFromCode($codfamilia);
                    $familia = $modelFamilia->descripcion;
                }
            }
        }

        if (!$articulo) {
            $referencia = 'SIN_REFERENCIA';
            $art_desc = 'Artículo sin referencia';
            $codfamilia = 'SIN_FAMILIA';
            $familia = 'SIN_FAMILIA';
        }

        return array('ref' => $referencia, 'art_desc' => $art_desc, 'codfamilia' => $codfamilia, 'familia' => $familia, 'pvptotal' => $pvptotal);
    }

    protected static function days_in_month($month, $year)
    {
        // calculate number of days in a month CALC_GREGORIAN
        return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
    }

    protected static function defaultMoney()
    {
        return '<span style="color:#ccc;">' . ToolBox::coins()::format(0) . '</span>';
    }

    protected static function defaultPerc()
    {
        return '<span style="color:#ccc;">0 %</span>';
    }

    protected static function purchases_build_year($year, $codejercicio)
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
            'total_subcuenta' => [],
            'total_mes' => [],
            'porc_cuenta' => [],
            'porc_subcuenta' => [],
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
                $dia_mes = ResultReport::days_in_month($mes, $year);
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

                            if (self::$parent_codcuenta === $codcuenta) {
                                $codsubcuenta = $p['codsubcuenta'];

                                if (isset($gastos['total_subcuenta'][$codcuenta][$codsubcuenta])) {
                                    $gastos['total_subcuenta'][$codcuenta][$codsubcuenta] += $pvptotal;
                                } else {
                                    $gastos['total_subcuenta'][$codcuenta][$codsubcuenta] = $pvptotal;
                                }

                                if (isset($gastos['cuentas'][$codcuenta][$codsubcuenta][$mes])) {
                                    $gastos['cuentas'][$codcuenta][$codsubcuenta][$mes]['pvptotal'] += $pvptotal;
                                } else {
                                    $gastos['cuentas'][$codcuenta][$codsubcuenta][$mes]['pvptotal'] = $pvptotal;
                                }
                            } else {
                                $gastos['cuentas'][$codcuenta] = $codcuenta;
                            }
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
                            $gastos['descripciones'][$codcuenta] = $codcuenta . ' - ' . $cuenta->descripcion;
                        }

                        if (self::$parent_codcuenta === (string) $codcuenta) {
                            foreach ($arraycuenta as $codsubcuenta => $arraysubcuenta) {
                                $gastos['descripciones'][$codsubcuenta] = '-';
                                $subcuenta = new Subcuenta();
                                $where = [
                                    new DataBaseWhere('codsubcuenta', $codsubcuenta),
                                    new DataBaseWhere('codejercicio', $codejercicio)
                                ];
                                if ($subcuenta->loadFromCode('', $where)) {
                                    $gastos['descripciones'][$codsubcuenta] = $codsubcuenta . ' - ' . $subcuenta->descripcion;
                                }
                            }
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
                if (self::$parent_codcuenta === (string) $codcuenta) {
                    foreach ($cuenta as $codsubcuenta => $subcuenta) {
                        $gastos['porc_subcuenta'][$codcuenta][$codsubcuenta] = round($gastos['total_subcuenta'][$codcuenta][$codsubcuenta] * 100 / $gastos_total_meses, FS_NF0);
                    }
                }
            }
        }

        // Variables globales para usar en la vista
        self::$gastos[$year] = $gastos;
    }

    protected static function randomColor()
    {
        return substr(str_shuffle('ABCDEF0123456789'), 0, 6);
    }

    protected static function summary_build_year($year, $codejercicio)
    {
        self::sales_build_year($year, $codejercicio);
        self::purchases_build_year($year, $codejercicio);

        $resultado = array(
            'total_mes' => [],
        );

        // Recorremos los meses y ejecutamos una consulta filtrando por el mes
        for ($mes = 1; $mes <= 12; $mes++) {
            /// inicializamos
            $resultado['total_mes'][$mes] = 0;

            /**
             *  RESULTADOS
             * *****************************************************************
             */
            $resultado['total_mes'][$mes] = round(self::$ventas[$year]['total_mes'][$mes] - self::$gastos[$year]['total_mes'][$mes], FS_NF0);
        }

        /**
         *  TOTALES GLOBALES
         * *****************************************************************
         */
        $resultado['total_mes'][0] = round(self::$ventas[$year]['total_mes'][0] - self::$gastos[$year]['total_mes'][0], FS_NF0);
        $resultado['total_mes']['media'] = round((self::$ventas[$year]['total_mes']['media'] - self::$gastos[$year]['total_mes']['media']) / 12, FS_NF0);

        // Variables globales para usar en la vista
        self::$resultado[$year] = $resultado;
    }

    protected static function sales_build_year($year, $codejercicio)
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
            'total_ref' => [],
            'total_mes' => [],
            'porc_fam' => [],
            'porc_ref' => [],
        );

        $ventas_total_meses = 0;

        // Recorremos los meses y ejecutamos una consulta filtrando por el mes
        for ($mes = 1; $mes <= 12; $mes++) {
            /// inicializamos
            $ventas['total_mes'][$mes] = 0;

            if ($year) {
                $dia_mes = ResultReport::days_in_month($mes, $year);
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

                        if (self::$parent_codfamilia === (string) $codfamilia) {
                            if (isset($ventas['total_ref'][$codfamilia][$referencia])) {
                                $ventas['total_ref'][$codfamilia][$referencia] += $pvptotal;
                            } else {
                                $ventas['total_ref'][$codfamilia][$referencia] = $pvptotal;
                            }

                            if (isset($ventas['familias'][$codfamilia][$referencia][$mes])) {
                                $ventas['familias'][$codfamilia][$referencia][$mes]['pvptotal'] += $pvptotal;
                            } else {
                                $ventas['familias'][$codfamilia][$referencia][$mes]['pvptotal'] = $pvptotal;
                            }
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
                            if (self::$parent_codfamilia === (string) $codfamilia) {
                                $ventas['descripciones'][$referencia] = $data['art_desc'];
                            }
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
                if (self::$parent_codfamilia === (string) $codfamilia) {
                    foreach ($familias as $referencia => $array) {
                        $ventas['porc_ref'][$codfamilia][$referencia] = round($ventas['total_ref'][$codfamilia][$referencia] * 100 / $ventas_total_meses, FS_NF0);
                    }
                }
            }
        }

        // Variables globales para usar en la vista
        self::$ventas[$year] = $ventas;
    }
}