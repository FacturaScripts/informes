<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Dinamic\Model\Variante;

/**
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

        // seleccionamos el año anterior
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

        self::$parent_codcuenta = isset($formData['parent_codcuenta']) ? (string)$formData['parent_codcuenta'] : null;
        self::$parent_codfamilia = isset($formData['parent_codfamilia']) ? (string)$formData['parent_codfamilia'] : null;

        // Llamamos a la función que crea los arrays con los datos,
        // pasandole el año seleccionado y el anterior.
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
            $where = [new DataBaseWhere('referencia', $referencia)];
            if ($variante->loadFromCode('', $where)) {
                $articulo = true;
                $producto->loadFromCode($variante->idproducto);
                $descripcion = strlen($producto->descripcion) > 50 ? substr($producto->descripcion, 0, 50) . '...' : $producto->descripcion;
                $descripcion = $descripcion != '' ? ' - ' . $descripcion : $descripcion;
                $art_desc = $referencia . $descripcion;
                $codfamilia = $producto->codfamilia;

                if (empty($codfamilia)) {
                    $codfamilia = 'SIN_FAMILIA';
                    $familia = ToolBox::i18n()->trans('no-family');
                } else {
                    $modelFamilia = new Familia();
                    $modelFamilia->loadFromCode($codfamilia);
                    $familia = $modelFamilia->descripcion;
                }
            }
        }

        if (!$articulo) {
            $referencia = 'SIN_REFERENCIA';
            $art_desc = ToolBox::i18n()->trans('no-product-desc');
            $codfamilia = 'SIN_FAMILIA';
            $familia = 'SIN_FAMILIA';
        }

        return array('ref' => $referencia, 'art_desc' => $art_desc, 'codfamilia' => $codfamilia, 'familia' => $familia, 'pvptotal' => $pvptotal);
    }

    protected static function customerInvoices(array $ventas, array $date, string $codejercicio, int $mes, float &$ventas_total_ser_meses, float &$ventas_total_pag_meses, float &$ventas_total_age_meses): array
    {
        $modelFacturas = new FacturaCliente();

        $where = [
            new DataBaseWhere('fecha', $date['desde'], '>='),
            new DataBaseWhere('fecha', $date['hasta'], '<='),
            new DataBaseWhere('codejercicio', $codejercicio)
        ];

        foreach ($modelFacturas->all($where, [], 0, 0) as $factura) {
            // Series
            if (isset($ventas['total_ser_mes'][$factura->codserie][$mes])) {
                $ventas['total_ser_mes'][$factura->codserie][$mes] += $factura->neto;
            } else {
                $ventas['total_ser_mes'][$factura->codserie][$mes] = $factura->neto;
            }

            if (isset($ventas['total_ser'][$factura->codserie])) {
                $ventas['total_ser'][$factura->codserie] += $factura->neto;
            } else {
                $ventas['total_ser'][$factura->codserie] = $factura->neto;
            }

            $ventas['series'][$factura->codserie][$mes] = array('pvptotal' => $factura->neto);
            $ventas_total_ser_meses = $factura->neto + $ventas_total_ser_meses;

            // Pagos
            if (isset($ventas['total_pag_mes'][$factura->codpago][$mes])) {
                $ventas['total_pag_mes'][$factura->codpago][$mes] += $factura->neto;
            } else {
                $ventas['total_pag_mes'][$factura->codpago][$mes] = $factura->neto;
            }

            if (isset($ventas['total_pag'][$factura->codpago])) {
                $ventas['total_pag'][$factura->codpago] += $factura->neto;
            } else {
                $ventas['total_pag'][$factura->codpago] = $factura->neto;
            }

            $ventas['pagos'][$factura->codpago][$mes] = array('pvptotal' => $factura->neto);
            $ventas_total_pag_meses = $factura->neto + $ventas_total_pag_meses;

            // Agentes
            $codagente = $factura->codagente ?? 'SIN_AGENTE';
            if (isset($ventas['total_age_mes'][$codagente][$mes])) {
                $ventas['total_age_mes'][$codagente][$mes] += $factura->neto;
            } else {
                $ventas['total_age_mes'][$codagente][$mes] = $factura->neto;
            }

            if (isset($ventas['total_age'][$codagente])) {
                $ventas['total_age'][$codagente] += $factura->neto;
            } else {
                $ventas['total_age'][$codagente] = $factura->neto;
            }

            $ventas['agentes'][$codagente][$mes] = array('pvptotal' => $factura->neto);
            $ventas_total_age_meses = $factura->neto + $ventas_total_age_meses;
        }

        return $ventas;
    }

    protected static function days_in_month($month, $year)
    {
        // calculate number of days in a month CALC_GREGORIAN
        return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
    }

    protected static function defaultMoney(): string
    {
        return '<span style="color:#ccc;">' . ToolBox::coins()::format(0) . '</span>';
    }

    protected static function defaultPerc(): string
    {
        return '<span style="color:#ccc;">0.0 %</span>';
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

        // necesitamos el número de meses para calcular la media
        $countMonth = 0;

        // Recorremos los meses y ejecutamos una consulta filtrando por el mes
        for ($mes = 1; $mes <= 12; $mes++) {
            // inicializamos
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
                        . " AND asi.codejercicio = " . $dataBase->var2str($codejercicio)
                        . " AND codsubcuenta LIKE '6%'";

                    if ($asiento_regularizacion) {
                        $sql .= " AND asi.numero <> " . $dataBase->var2str($asiento_regularizacion);
                    }

                    $sql .= " ORDER BY codsubcuenta";

                    $partidas = $dataBase->select($sql);
                    if ($partidas) {
                        foreach ($partidas as $p) {
                            $codsubcuenta = $p['codsubcuenta'];
                            $codcuenta = substr($codsubcuenta, 0, 3);
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

                            if (isset($gastos['total_mes'][$mes])) {
                                $gastos['total_mes'][$mes] += $pvptotal;
                            } else {
                                $gastos['total_mes'][$mes] = $pvptotal;
                            }

                            $gastos_total_meses = $pvptotal + $gastos_total_meses;

                            if (self::$parent_codcuenta === $codcuenta) {
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
                                $gastos['cuentas'][$codcuenta]['codsubcuenta'] = $codsubcuenta;
                            }
                        }
                    }
                }

                // Las descripciones solo las necesitamos en el año seleccionado,
                // en el año anterior se omite
                if ($year == self::$year) {
                    $gastos = self::setDescriptionAccount($gastos, $codejercicio);
                }
            }

            if ($gastos['total_mes'][$mes] > 0) {
                $countMonth++;
            }
        }

        /**
         *  TOTALES GLOBALES
         * *****************************************************************
         */
        $gastos['total_mes'][0] = round($gastos_total_meses, FS_NF0);

        if ($countMonth > 0) {
            $gastos['total_mes']['media'] = round($gastos_total_meses / $countMonth, FS_NF0);
        } else {
            $gastos['total_mes']['media'] = round($gastos_total_meses, FS_NF0);
        }


        /**
         *  PORCENTAJES
         * *****************************************************************
         */

        // GASTOS: Calculamos los porcentajes con los totales globales
        $gastos = self::setPercentagePurchases($gastos, $gastos_total_meses);

        // Variables globales para usar en la vista
        self::$gastos[$year] = $gastos;
    }

    protected static function randomColor()
    {
        return substr(str_shuffle('ABCDEF0123456789'), 0, 6);
    }

    protected static function setDescriptionAccount(array $gastos, string $codejercicio): array
    {
        // GASTOS: Creamos un array con las descripciones de las cuentas
        foreach ($gastos['cuentas'] as $codcuenta => $arraycuenta) {
            // Añadimos las descripciones de las subcuentas
            // solo al desplegar una cuenta
            if (self::$parent_codcuenta === (string)$codcuenta) {
                $gastos = self::setDescriptionSubaccount($gastos, $arraycuenta, $codejercicio);
            } else {
                $gastos['descripciones'][$codcuenta] = '-';
                $subcuenta = new Subcuenta();
                $where = [
                    new DataBaseWhere('codsubcuenta', $arraycuenta['codsubcuenta']),
                    new DataBaseWhere('codejercicio', $codejercicio)
                ];
                if (false === $subcuenta->loadFromCode('', $where)) {
                    continue;
                }

                $cuenta = new Cuenta();
                $where = [new DataBaseWhere('codcuenta', $subcuenta->codcuenta),];

                if ($cuenta->loadFromCode('', $where)) {
                    $gastos['descripciones'][$codcuenta] = $codcuenta . ' - ' . $cuenta->descripcion;
                }
            }
        }

        return $gastos;
    }

    protected static function setDescriptionAgents(array $ventas): array
    {
        foreach ($ventas['agentes'] as $codagente => $agentes) {
            if ($codagente === 'SIN_AGENTE') {
                $ventas['agentes'][$codagente]['descripcion'] = ToolBox::i18n()->trans('no-agent');
                continue;
            }

            // buscamos el agente en la base de datos para asignar el nombre
            $agente = new Agente();
            if ($agente->loadFromCode($codagente)) {
                $ventas['agentes'][$codagente]['descripcion'] = $agente->nombre;
                continue;
            }

            // no lo hemos encontrado, pero por lo menos ponemos el código
            $ventas['agentes'][$codagente]['descripcion'] = $codagente;
        }

        return $ventas;
    }

    protected static function setDescriptionFamilies(array $ventas, string $codejercicio): array
    {
        // Recorremos ventas['familias'] crear un array con las descripciones de las familias
        foreach ($ventas['familias'] as $codfamilia => $familia) {
            foreach ($familia as $referencia => $array) {
                $dl['referencia'] = $referencia;
                $dl['pvptotal'] = 0;
                $data = self::build_data($dl);

                if (self::$parent_codfamilia === (string)$codfamilia) {
                    $ventas = self::setDescriptionProducts($ventas, $referencia, $data['art_desc']);
                } else {
                    $ventas['descripciones'][$codfamilia] = $data['familia'];
                }
            }
        }

        return $ventas;
    }

    protected static function setDescriptionPayments(array $ventas): array
    {
        foreach ($ventas['pagos'] as $codpago => $pagos) {
            $pago = new FormaPago();
            if ($pago->loadFromCode($codpago)) {
                $ventas['pagos'][$codpago]['descripcion'] = $pago->descripcion;
                continue;
            }

            $ventas['pagos'][$codpago]['descripcion'] = $codpago;
        }

        return $ventas;
    }

    protected static function setDescriptionProducts(array $ventas, string $referencia, string $desc): array
    {
        $ventas['descripciones'][$referencia] = $desc;
        return $ventas;
    }

    protected static function setDescriptionSubaccount(array $gastos, array $arraycuenta, string $codejercicio): array
    {
        foreach ($arraycuenta as $codsubcuenta => $arraysubcuenta) {
            $subcuenta = new Subcuenta();
            $where = [
                new DataBaseWhere('codsubcuenta', $codsubcuenta),
                new DataBaseWhere('codejercicio', $codejercicio)
            ];
            if ($subcuenta->loadFromCode('', $where)) {
                $gastos['descripciones'][$codsubcuenta] = $codsubcuenta . ' - ' . $subcuenta->descripcion;
                continue;
            }

            $gastos['descripciones'][$codsubcuenta] = '-';
        }

        return $gastos;
    }

    protected static function setDescriptionSeries(array $ventas): array
    {
        foreach ($ventas['series'] as $codserie => $series) {
            $serie = new Serie();
            if ($serie->loadFromCode($codserie)) {
                $ventas['series'][$codserie]['descripcion'] = $serie->descripcion;
                continue;
            }

            $ventas['series'][$codserie]['descripcion'] = $codserie;
        }

        return $ventas;
    }

    protected static function setPercentageAgents(array $ventas, float $ventas_total_age_meses): array
    {
        foreach ($ventas['agentes'] as $codagente => $agentes) {
            if ($ventas_total_age_meses != 0) {
                $ventas['porc_age'][$codagente] = round($ventas['total_age'][$codagente] * 100 / $ventas_total_age_meses, FS_NF0);
            }
        }

        return $ventas;
    }

    protected static function setPercentageFamilies(array $ventas, float $ventas_total_fam_meses): array
    {
        foreach ($ventas['familias'] as $codfamilia => $familias) {
            if ($ventas_total_fam_meses != 0) {
                $ventas['porc_fam'][$codfamilia] = round($ventas['total_fam'][$codfamilia] * 100 / $ventas_total_fam_meses, FS_NF0);

                // añadimos los porcentages de los productos
                if (self::$parent_codfamilia === (string)$codfamilia) {
                    $ventas = self::setPercentageProducts($ventas, $codfamilia, $ventas_total_fam_meses, $familias);
                }
            }
        }

        return $ventas;
    }

    protected static function setPercentagePayments(array $ventas, float $ventas_total_pag_meses): array
    {
        foreach ($ventas['pagos'] as $codpago => $pagos) {
            if ($ventas_total_pag_meses != 0) {
                $ventas['porc_pag'][$codpago] = round($ventas['total_pag'][$codpago] * 100 / $ventas_total_pag_meses, FS_NF0);
            }
        }

        return $ventas;
    }

    protected static function setPercentageProducts(array $ventas, string $codfamilia, float $ventas_total_fam_meses, array $familias): array
    {
        foreach ($familias as $referencia => $array) {
            $ventas['porc_ref'][$codfamilia][$referencia] = round($ventas['total_ref'][$codfamilia][$referencia] * 100 / $ventas_total_fam_meses, FS_NF0);
        }

        return $ventas;
    }

    protected static function setPercentageSeries(array $ventas, float $ventas_total_ser_meses): array
    {
        foreach ($ventas['series'] as $codserie => $series) {
            if ($ventas_total_ser_meses != 0) {
                $ventas['porc_ser'][$codserie] = round($ventas['total_ser'][$codserie] * 100 / $ventas_total_ser_meses, FS_NF0);
            }
        }

        return $ventas;
    }

    protected static function setPercentagePurchases(array $gastos, float $gastos_total_meses): array
    {
        foreach ($gastos['cuentas'] as $codcuenta => $cuenta) {
            if ($gastos_total_meses != 0) {
                $gastos['porc_cuenta'][$codcuenta] = round($gastos['total_cuenta'][$codcuenta] * 100 / $gastos_total_meses, FS_NF0);
                if (self::$parent_codcuenta === (string)$codcuenta) {
                    foreach ($cuenta as $codsubcuenta => $subcuenta) {
                        $gastos['porc_subcuenta'][$codcuenta][$codsubcuenta] = round($gastos['total_subcuenta'][$codcuenta][$codsubcuenta] * 100 / $gastos_total_meses, FS_NF0);
                    }
                }
            }
        }

        return $gastos;
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
            // inicializamos
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
        $resultado['total_mes']['media'] = round((self::$ventas[$year]['total_mes']['media'] - self::$gastos[$year]['total_mes']['media']), FS_NF0);

        // Variables globales para usar en la vista
        self::$resultado[$year] = $resultado;
    }

    protected static function sales_build_year($year, $codejercicio)
    {
        $date = array(
            'desde' => '',
            'hasta' => '',
        );

        $ventas = array(
            'agentes' => [],
            'descripciones' => [],
            'familias' => [],
            'pagos' => [],
            'series' => [],
            'total_fam' => [],
            'total_ser' => [],
            'total_pag' => [],
            'total_age' => [],
            'total_fam_mes' => [],
            'total_ser_mes' => [],
            'total_pag_mes' => [],
            'total_age_mes' => [],
            'total_ref' => [],
            'total_mes' => [],
            'porc_fam' => [],
            'porc_ser' => [],
            'porc_pag' => [],
            'porc_age' => [],
            'porc_ref' => [],
        );

        $ventas_total_fam_meses = 0;
        $ventas_total_ser_meses = 0;
        $ventas_total_pag_meses = 0;
        $ventas_total_age_meses = 0;

        // necesitamos el número de meses para calcular la media
        $countMonth = 0;

        // Recorremos los meses y ejecutamos una consulta filtrando por el mes
        for ($mes = 1; $mes <= 12; $mes++) {
            // inicializamos
            $ventas['total_mes'][$mes] = 0;

            if ($year) {
                $dia_mes = ResultReport::days_in_month($mes, $year);
                $date['desde'] = date('01-' . $mes . '-' . $year);
                $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

                /**
                 *  VENTAS: Consulta con las lineasfacturascli
                 * *****************************************************************
                 */
                $ventas = self::salesLineasFacturasCli($ventas, $date, $codejercicio, $mes, $ventas_total_fam_meses, $countMonth);

                // Recorremos las facturas
                $ventas = self::customerInvoices($ventas, $date, $codejercicio, $mes, $ventas_total_ser_meses, $ventas_total_pag_meses, $ventas_total_age_meses);

                // Las descripciones solo las necesitamos en el año seleccionado,
                // en el año anterior se omite
                if ($year == self::$year) {
                    $ventas = self::setDescriptionFamilies($ventas, $codejercicio);
                    $ventas = self::setDescriptionSeries($ventas);
                    $ventas = self::setDescriptionPayments($ventas);
                    $ventas = self::setDescriptionAgents($ventas);
                }
            }
        }

        /**
         *  TOTALES GLOBALES
         * *****************************************************************
         */
        $ventas['total_mes'][0] = round($ventas_total_fam_meses, FS_NF0);

        if ($countMonth > 0) {
            $ventas['total_mes']['media'] = round($ventas_total_fam_meses / $countMonth, FS_NF0);
        } else {
            $ventas['total_mes']['media'] = round($ventas_total_fam_meses, FS_NF0);
        }

        /**
         *  PORCENTAJES
         * *****************************************************************
         */
        // VENTAS: Calculamos los porcentajes con los totales globales
        $ventas = self::setPercentageFamilies($ventas, $ventas_total_fam_meses);
        $ventas = self::setPercentageSeries($ventas, $ventas_total_ser_meses);
        $ventas = self::setPercentagePayments($ventas, $ventas_total_pag_meses);
        $ventas = self::setPercentageAgents($ventas, $ventas_total_age_meses);

        // Variables globales para usar en la vista
        self::$ventas[$year] = $ventas;
    }

    protected static function salesLineasFacturasCli(array $ventas, array $date, string $codejercicio, int $mes, float &$ventas_total_fam_meses, int &$countMonth): array
    {
        $dataBase = new DataBase();

        $sql = "select lfc.referencia, sum(lfc.pvptotal) as pvptotal from lineasfacturascli as lfc"
            . " LEFT JOIN facturascli as fc ON lfc.idfactura = fc.idfactura"
            . " where fc.fecha >= " . $dataBase->var2str($date['desde'])
            . " AND fc.fecha <= " . $dataBase->var2str($date['hasta'])
            . " AND fc.codejercicio = " . $dataBase->var2str($codejercicio)
            . " group by lfc.referencia";

        // VENTAS: Recorremos lineasfacturascli y montamos arrays
        $lineas = $dataBase->select($sql);
        foreach ($lineas as $dl) {
            $data = self::build_data($dl);
            $pvptotal = (float)$data['pvptotal'];
            $referencia = $data['ref'];
            $codfamilia = $data['codfamilia'];

            // Familias
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

            // Solo al pinchar en una familia
            if (self::$parent_codfamilia === (string)$codfamilia) {
                // Productos
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

            // Totales
            $ventas['total_mes'][$mes] = $pvptotal + $ventas['total_mes'][$mes];
            $ventas_total_fam_meses = $pvptotal + $ventas_total_fam_meses;

            // Array temporal con los totales (falta añadir descripción familia)
            $ventas['familias'][$codfamilia][$referencia][$mes] = array('pvptotal' => $pvptotal);
        }

        if ($ventas['total_mes'][$mes] > 0) {
            $countMonth++;
        }

        return $ventas;
    }
}