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

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportTreasury extends Controller
{
    public $bancos;
    public $cajas;
    public $codejercicio;
    public $codejercicio_ant;
    public $da_gastoscobros;
    public $da_impuestos;
    public $da_reservasresultados;
    public $da_resultadoejercicioactual;
    public $da_resultadosituacion;
    public $da_tesoreria;
    public $desde;
    public $hasta;

    protected $ejercicio;
    protected $ejercicio_ant;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["menu"] = "reports";
        $data["title"] = "treasury";
        $data["icon"] = "fas fa-balance-scale-left";
        return $data;
    }

    public function getYears(): string
    {
        $code = $this->request->get('code', '');
        $html = '';
        $model = new Ejercicio();
        foreach ($model->all([], ['fechainicio' => 'desc'], 0, 0) as $row) {
            $emp = new Empresa();
            $emp->loadFromCode($row->idempresa);
            $selected = $code == $row->codejercicio ? 'selected' : '';
            $html .= '<option value="' . $row->codejercicio . '" ' . $selected . '>'
                . $row->nombre . ' | ' . $emp->nombrecorto
                . '</option>';
        }
        return $html;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->loadTreasury();
    }

    public function show_precio($price)
    {
        $priceFormat = ToolBox::coins()::format($price);
        $html = $price < 0 ? '<span class="text-danger">' . $priceFormat . '</span>' : $priceFormat;
        return $html;
    }

    protected function cuadro_tesoreria()
    {
        /**
         * Cuadro de tesorería.
         */
        $this->da_tesoreria = array(
            'total_cajas' => 0,
            'total_bancos' => 0,
            'total_tesoreria' => 0,
        );
        $this->get_bancos();
        foreach ($this->bancos as $banco) {
            $this->da_tesoreria["total_bancos"] += $banco->saldo;
        }

        $this->get_cajas();
        foreach ($this->cajas as $caja) {
            $this->da_tesoreria["total_cajas"] += $caja->saldo;
        }

        $this->da_tesoreria["total_tesoreria"] = $this->da_tesoreria["total_cajas"] + $this->da_tesoreria["total_bancos"];
    }

    protected function cuadro_gastos_y_cobros()
    {
        /**
         * Cuadro gastos y cobros.
         */
        $this->da_gastoscobros = array(
            'gastospdtepago' => -1 * $this->get_gastos_pendientes(),
            'clientespdtecobro' => $this->get_cobros_pendientes(),
            'nominaspdtepago' => $this->saldo_cuenta('465%', $this->desde, $this->hasta),
            'segsocialpdtepago' => $this->saldo_cuenta('476%', $this->desde, $this->hasta),
            'segsocialpdtecobro' => $this->saldo_cuenta('471%', $this->desde, $this->hasta),
            'total_gastoscobros' => 0,
        );

        $this->da_gastoscobros["total_gastoscobros"] = $this->da_gastoscobros["gastospdtepago"] + $this->da_gastoscobros["clientespdtecobro"] + $this->da_gastoscobros["nominaspdtepago"] + $this->da_gastoscobros["segsocialpdtepago"] + $this->da_gastoscobros["segsocialpdtecobro"];
    }

    protected function cuadro_impuestos()
    {
        /**
         * Cuadro de impuestos.
         */
        $this->da_impuestos = array(
            'irpf-mod111' => $this->saldo_cuenta('4751%', $this->desde, $this->hasta),
            'irpf-mod115' => 0,
            'iva-repercutido' => $this->saldo_cuenta('477%', $this->desde, $this->hasta),
            'iva-soportado' => $this->saldo_cuenta('472%', $this->desde, $this->hasta),
            'iva-devolver' => $this->saldo_cuenta('4700%', $this->desde, $this->hasta),
            'resultado_iva-mod303' => 0,
            'ventas_totales' => $this->get_ventas_totales(),
            'gastos_totales' => -1 * $this->saldo_cuenta('6%', $this->desde, $this->hasta),
            'resultado' => 0,
            'sociedades' => 0,
            'pago-ant' => $this->saldo_cuenta('473%', $this->desde, $this->hasta),
            'pagofraccionado-mod202' => 0,
            'resultado_ejanterior' => -1 * $this->saldo_cuenta('129%', $this->desde, $this->hasta),
            'resultado_negotros' => -1 * $this->saldo_cuenta('121%', $this->desde, $this->hasta),
            'total' => 0,
            'sociedades_ant' => 0,
            'sociedades_adelantos' => -1 * $this->saldo_cuenta('4709%', $this->desde, $this->hasta),
            'total-mod200' => 0,
        );

        // cogemos las cuentas del alquiler de la configuración para generar el mod-115
        $sql = "SELECT * FROM subcuentas WHERE idcuenta IN "
            . "(SELECT idcuenta FROM cuentas WHERE codcuentaesp = " . $this->dataBase->var2str('IRPFA')
            . " AND codejercicio = " . $this->dataBase->var2str($this->codejercicio) . ") ORDER BY codsubcuenta ASC;";

        $cuentasalquiler = $this->dataBase->select($sql);
        foreach ($cuentasalquiler as $cuentaalquiler) {
            if ($cuentaalquiler) {
                $this->da_impuestos["irpf-mod115"] += $this->saldo_cuenta($cuentaalquiler, $this->desde, $this->hasta);
                $this->da_impuestos["irpf-mod111"] -= $this->saldo_cuenta($cuentaalquiler, $this->desde, $this->hasta);
            }
        }

        $this->da_impuestos["resultado_iva-mod303"] = $this->da_impuestos["iva-repercutido"] + $this->da_impuestos["iva-soportado"] + $this->da_impuestos["iva-devolver"];

        $this->da_impuestos["resultado"] = $this->da_impuestos["ventas_totales"] + $this->da_impuestos["gastos_totales"];

        if ($this->da_impuestos["resultado"] < 0) {
            $this->da_impuestos["sociedades"] = 0;
        } else {
            $sociedades = isset($this->ejercicio_ant->impsociedades) ? floatval($this->ejercicio_ant->impsociedades) : 0;
            $this->da_impuestos["sociedades"] = -1 * $this->da_impuestos["resultado"] * $sociedades / 100;
        }

        $this->da_impuestos["pagofraccionado-mod202"] = $this->da_impuestos["sociedades"] + $this->da_impuestos["pago-ant"];

        $this->da_impuestos["total"] = $this->da_impuestos["resultado_ejanterior"] + $this->da_impuestos["resultado_negotros"];

        if ($this->da_impuestos["total"] < 0) {
            $this->da_impuestos["sociedades_ant"] = 0;
        } else {
            $sociedades = isset($this->ejercicio_ant->impsociedades) ? floatval($this->ejercicio_ant->impsociedades) : 0;
            $this->da_impuestos["sociedades_ant"] = $this->da_impuestos["total"] * $sociedades / 100;
        }

        $this->da_impuestos["total-mod200"] = $this->da_impuestos["sociedades_ant"] - $this->da_impuestos["sociedades_adelantos"];
    }

    protected function cuadro_resultados_situacion_corto()
    {
        $this->da_resultadosituacion["total"] = $this->da_tesoreria["total_tesoreria"] + $this->da_gastoscobros["total_gastoscobros"] +
            $this->da_impuestos["irpf-mod111"] + $this->da_impuestos["irpf-mod115"] + $this->da_impuestos["resultado_iva-mod303"] +
            $this->da_impuestos["pagofraccionado-mod202"] + $this->da_impuestos["total-mod200"];
    }

    protected function cuadro_reservas()
    {
        /**
         * Cuadro reservas + resultados
         */
        $this->da_reservasresultados = array(
            'reservalegal' => -1 * $this->saldo_cuenta('112%', $this->desde, $this->hasta),
            'reservasvoluntarias' => -1 * $this->saldo_cuenta('113%', $this->desde, $this->hasta),
            'resultadoejercicioanterior' => abs($this->saldo_cuenta('129%', $this->desde, $this->hasta)) - $this->saldo_cuenta('121%', $this->desde, $this->hasta),
            'total_reservas' => 0,
        );

        $this->da_reservasresultados["total_reservas"] = $this->da_reservasresultados["reservalegal"] + $this->da_reservasresultados["reservasvoluntarias"] + $this->da_reservasresultados["resultadoejercicioanterior"];
    }

    protected function cuadro_resultado_actual()
    {
        /**
         * Cuadro resultados ejercicio actual
         */
        $this->da_resultadoejercicioactual = array(
            'total_ventas' => $this->get_ventas_totales(),
            'total_gastos' => -1 * $this->saldo_cuenta('6%', $this->desde, $this->hasta),
            'resultadoexplotacion' => 0,
            'amortizacioninmovintang' => $this->saldo_cuenta('680%', $this->desde, $this->hasta),
            'amortizacioninmovmat' => $this->saldo_cuenta('681%', $this->desde, $this->hasta),
            'total_amort' => 0,
            'resultado_antes_impuestos' => 0,
            'impuesto_sociedades' => 0,
            'resultado_despues_impuestos' => 0,
        );

        $this->da_resultadoejercicioactual["resultadoexplotacion"] = $this->da_resultadoejercicioactual["total_ventas"] + $this->da_resultadoejercicioactual["total_gastos"];
        $this->da_resultadoejercicioactual["total_amort"] = $this->da_resultadoejercicioactual["amortizacioninmovintang"] + $this->da_resultadoejercicioactual["amortizacioninmovmat"];
        $this->da_resultadoejercicioactual["resultado_antes_impuestos"] = $this->da_resultadoejercicioactual["resultadoexplotacion"] + $this->da_resultadoejercicioactual["total_amort"];

        if ($this->da_resultadoejercicioactual["resultado_antes_impuestos"] < 0) {
            $this->da_resultadoejercicioactual["impuesto_sociedades"] = 0;
        } else {
            $sociedades = $this->ejercicio->impsociedades;
            $this->da_resultadoejercicioactual["impuesto_sociedades"] = -1 * $this->da_resultadoejercicioactual["resultado_antes_impuestos"] * $sociedades / 100;
        }

        $this->da_resultadoejercicioactual["resultado_despues_impuestos"] = $this->da_resultadoejercicioactual["resultado_antes_impuestos"] + $this->da_resultadoejercicioactual["impuesto_sociedades"];
    }

    protected function get_bancos()
    {
        $this->bancos = array();

        $sql = "SELECT * FROM subcuentas WHERE codcuenta = '572' AND codejercicio = "
            . $this->dataBase->var2str($this->codejercicio) . ";";

        $data = $this->dataBase->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $this->bancos[] = new subcuenta($d);
            }
        }
    }

    protected function get_cajas()
    {
        $this->cajas = array();

        $sql = "SELECT * FROM subcuentas WHERE idcuenta IN "
            . "(SELECT idcuenta FROM cuentas WHERE codcuentaesp = " . $this->dataBase->var2str('CAJA')
            . " AND codejercicio = " . $this->dataBase->var2str($this->codejercicio) . ") ORDER BY codsubcuenta ASC;";

        $data = $this->dataBase->select($sql);
        foreach ($data as $sc) {
            $this->cajas[] = (object)$sc;
        }
    }

    protected function get_gastos_pendientes()
    {
        $total = 0;

        $sql = "SELECT SUM(total) as total FROM facturasprov WHERE pagada = false;";
        $data = $this->dataBase->select($sql);
        if ($data) {
            $total = floatval($data[0]['total']);
        }

        return $total;
    }

    protected function get_cobros_pendientes()
    {
        $total = 0;

        $sql = "SELECT SUM(total) as total FROM facturascli WHERE pagada = false;";
        $data = $this->dataBase->select($sql);
        if ($data) {
            $total = floatval($data[0]['total']);
        }

        return $total;
    }

    protected function get_ventas_totales()
    {
        $total = 0;

        $sql = "SELECT SUM(neto) as total FROM facturascli WHERE fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND fecha <= " . $this->dataBase->var2str($this->hasta) . ';';
        $data = $this->dataBase->select($sql);
        if ($data) {
            $total = floatval($data[0]['total']);
        }

        return $total;
    }

    protected function get_compras_totales()
    {
        $total = 0;

        $sql = "SELECT SUM(neto) as total FROM facturasprov WHERE fecha >= " . $this->empresa->var2str($this->desde)
            . " AND fecha <= " . $this->empresa->var2str($this->hasta) . ';';
        $data = $this->dataBase->select($sql);
        if ($data) {
            $total = floatval($data[0]['total']);
        }

        return $total;
    }

    protected function loadTreasury()
    {
        $code = $this->request->get('code', '');
        $this->codejercicio = NULL;
        $this->codejercicio_ant = NULL;
        $this->desde = date('01-01-Y');
        $this->hasta = date('31-12-Y');

        /// seleccionamos el ejercicio actual
        $ejercicio = new Ejercicio();
        foreach ($ejercicio->all([], ['fechainicio' => 'desc'], 0, 0) as $eje) {
            if ($code == $eje->codejercicio || $code == '' && date('Y', strtotime($eje->fechafin)) == date('Y')) {
                $this->codejercicio = $eje->codejercicio;
                $this->desde = $eje->fechainicio;
                $this->hasta = $eje->fechafin;
            } else if ($this->codejercicio) {
                $this->codejercicio_ant = $eje->codejercicio;
                break;
            }
        }

        $this->ejercicio = new Ejercicio();
        $this->ejercicio->loadFromCode($this->codejercicio);

        $this->ejercicio_ant = new Ejercicio();
        if ($this->codejercicio_ant) {
            $this->ejercicio_ant->loadFromCode($this->codejercicio_ant);
        }

        $this->cuadro_tesoreria();
        $this->cuadro_gastos_y_cobros();
        $this->cuadro_reservas();
        $this->cuadro_resultado_actual();
        $this->cuadro_impuestos();
        $this->cuadro_resultados_situacion_corto();
    }

    protected function saldo_cuenta($cuenta, $desde, $hasta)
    {
        $saldo = 0;

        if ($this->dataBase->tableExists('partidas')) {
            /// calculamos el saldo de todos aquellos asientos que afecten a caja
            $sql = "select sum(debe-haber) as total from partidas where codsubcuenta LIKE '" . $cuenta . "' and idasiento"
                . " in (select idasiento from asientos where fecha >= " . $this->dataBase->var2str($desde)
                . " and fecha <= " . $this->dataBase->var2str($hasta) . ");";

            $data = $this->dataBase->select($sql);
            if ($data) {
                $saldo = floatval($data[0]['total']);
            }
        }

        return $saldo;
    }

    protected function saldo_cuenta_asiento_regularizacion($cuenta, $desde, $hasta, $numasientoregularizacion)
    {
        $saldo = 0;

        if ($this->dataBase->tableExists('co_partidas')) {
            /// calculamos el saldo de todos aquellos asientos que afecten a caja
            $sql = "select sum(debe-haber) as total from co_partidas where codsubcuenta LIKE '" . $cuenta . "' and idasiento"
                . " in (select idasiento from co_asientos where fecha >= " . $this->empresa->var2str($desde)
                . " and fecha <= " . $this->empresa->var2str($hasta) . " and numero = " . $numasientoregularizacion . ");";

            $data = $this->dataBase->select($sql);
            if ($data) {
                $saldo = floatval($data[0]['total']);
            }
        }

        return $saldo;
    }
}