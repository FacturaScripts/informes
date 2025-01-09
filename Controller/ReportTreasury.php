<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportTreasury extends Controller
{
    /** @var array */
    public $bancos = [];

    /** @var array */
    public $cajas = [];

    /** @var string */
    public $code = null;

    /** @var string */
    public $codejercicio = null;

    /** @var string */
    public $codejercicio_ant = null;

    /** @var array */
    public $da_gastos_cobros = [];

    /** @var array */
    public $da_impuestos = [];

    /** @var array */
    public $da_reservas_resultados = [];

    /** @var array */
    public $da_resultado_actual = [];

    /** @var array */
    public $da_resultado_situacion = [];

    /** @var array */
    public $da_tesoreria = [];

    /** @var string */
    public $desde = null;

    /** @var string */
    public $hasta = null;

    /** @var Ejercicio */
    protected $ejercicio;

    /** @var Ejercicio */
    protected $ejercicio_ant;

    public function getCompanies(): array
    {
        return Empresas::all();
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'treasury';
        $data['icon'] = 'fa-solid fa-balance-scale-left';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->loadTreasury();
    }

    protected function cuadroTesoreria(): void
    {
        $this->da_tesoreria = [
            'total_cajas' => 0,
            'total_bancos' => 0,
            'total_tesoreria' => 0,
        ];

        $this->loadBancos();
        foreach ($this->bancos as $banco) {
            $this->da_tesoreria["total_bancos"] += $banco->saldo;
        }

        $this->loadCajas();
        foreach ($this->cajas as $caja) {
            $this->da_tesoreria["total_cajas"] += $caja->saldo;
        }

        $this->da_tesoreria["total_tesoreria"] = $this->da_tesoreria["total_cajas"] + $this->da_tesoreria["total_bancos"];
    }

    protected function cuadroGastosCobros(): void
    {
        $this->da_gastos_cobros = [
            'gastospdtepago' => -1 * $this->getGastosPendientes(),
            'clientespdtecobro' => $this->getCobrosPendientes(),
            'nominaspdtepago' => $this->saldoCuenta('465%', $this->desde, $this->hasta),
            'segsocialpdtepago' => $this->saldoCuenta('476%', $this->desde, $this->hasta),
            'segsocialpdtecobro' => $this->saldoCuenta('471%', $this->desde, $this->hasta),
            'total_gastoscobros' => 0,
        ];

        $this->da_gastos_cobros["total_gastoscobros"] = $this->da_gastos_cobros["gastospdtepago"]
            + $this->da_gastos_cobros["clientespdtecobro"]
            + $this->da_gastos_cobros["nominaspdtepago"]
            + $this->da_gastos_cobros["segsocialpdtepago"]
            + $this->da_gastos_cobros["segsocialpdtecobro"];
    }

    protected function cuadroImpuestos(): void
    {
        $this->da_impuestos = [
            'irpf-mod111' => $this->saldoCuenta('4751%', $this->desde, $this->hasta),
            'irpf-mod115' => 0,
            'iva-repercutido' => $this->saldoCuenta('477%', $this->desde, $this->hasta),
            'iva-soportado' => $this->saldoCuenta('472%', $this->desde, $this->hasta),
            'iva-devolver' => $this->saldoCuenta('4700%', $this->desde, $this->hasta),
            'resultado_iva-mod303' => 0,
            'ventas_totales' => $this->getVentasTotales(),
            'gastos_totales' => -1 * $this->saldoCuenta('6%', $this->desde, $this->hasta),
            'resultado' => 0,
            'sociedades' => 0,
            'pago-ant' => $this->saldoCuenta('473%', $this->desde, $this->hasta),
            'pagofraccionado-mod202' => 0,
            'resultado_ejanterior' => -1 * $this->saldoCuenta('129%', $this->desde, $this->hasta),
            'resultado_negotros' => -1 * $this->saldoCuenta('121%', $this->desde, $this->hasta),
            'total' => 0,
            'sociedades_ant' => 0,
            'sociedades_adelantos' => -1 * $this->saldoCuenta('4709%', $this->desde, $this->hasta),
            'total-mod200' => 0,
        ];

        // cogemos las cuentas del alquiler de la configuración para generar el mod-115
        $sql = "SELECT * FROM subcuentas WHERE idcuenta IN "
            . "(SELECT idcuenta FROM cuentas WHERE codcuentaesp = " . $this->dataBase->var2str('IRPFA')
            . " AND codejercicio = " . $this->dataBase->var2str($this->codejercicio)
            . ") ORDER BY codsubcuenta ASC;";

        $cuentas_alquiler = $this->dataBase->select($sql);
        foreach ($cuentas_alquiler as $cta_alquiler) {
            if ($cta_alquiler) {
                $this->da_impuestos["irpf-mod115"] += $this->saldoCuenta($cta_alquiler, $this->desde, $this->hasta);
                $this->da_impuestos["irpf-mod111"] -= $this->saldoCuenta($cta_alquiler, $this->desde, $this->hasta);
            }
        }

        $this->da_impuestos["resultado_iva-mod303"] = $this->da_impuestos["iva-repercutido"]
            + $this->da_impuestos["iva-soportado"]
            + $this->da_impuestos["iva-devolver"];

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

    protected function cuadroResultadosSituacionCorto(): void
    {
        $this->da_resultado_situacion["total"] = $this->da_tesoreria["total_tesoreria"]
            + $this->da_gastos_cobros["total_gastoscobros"]
            + $this->da_impuestos["irpf-mod111"]
            + $this->da_impuestos["irpf-mod115"]
            + $this->da_impuestos["resultado_iva-mod303"]
            + $this->da_impuestos["pagofraccionado-mod202"]
            + $this->da_impuestos["total-mod200"];
    }

    protected function cuadroReservas(): void
    {
        $this->da_reservas_resultados = [
            'reservalegal' => -1 * $this->saldoCuenta('112%', $this->desde, $this->hasta),
            'reservasvoluntarias' => -1 * $this->saldoCuenta('113%', $this->desde, $this->hasta),
            'resultadoejercicioanterior' => abs($this->saldoCuenta('129%', $this->desde, $this->hasta)) - $this->saldoCuenta('121%', $this->desde, $this->hasta),
            'total_reservas' => 0,
        ];

        $this->da_reservas_resultados["total_reservas"] = $this->da_reservas_resultados["reservalegal"]
            + $this->da_reservas_resultados["reservasvoluntarias"]
            + $this->da_reservas_resultados["resultadoejercicioanterior"];
    }

    protected function cuadroResultadoActual(): void
    {
        $this->da_resultado_actual = [
            'total_ventas' => $this->getVentasTotales(),
            'total_gastos' => -1 * $this->saldoCuenta('6%', $this->desde, $this->hasta),
            'resultadoexplotacion' => 0,
            'amortizacioninmovintang' => $this->saldoCuenta('680%', $this->desde, $this->hasta),
            'amortizacioninmovmat' => $this->saldoCuenta('681%', $this->desde, $this->hasta),
            'total_amort' => 0,
            'resultado_antes_impuestos' => 0,
            'impuesto_sociedades' => 0,
            'resultado_despues_impuestos' => 0,
        ];

        $this->da_resultado_actual["resultadoexplotacion"] = $this->da_resultado_actual["total_ventas"] + $this->da_resultado_actual["total_gastos"];
        $this->da_resultado_actual["total_amort"] = $this->da_resultado_actual["amortizacioninmovintang"] + $this->da_resultado_actual["amortizacioninmovmat"];
        $this->da_resultado_actual["resultado_antes_impuestos"] = $this->da_resultado_actual["resultadoexplotacion"] + $this->da_resultado_actual["total_amort"];

        if ($this->da_resultado_actual["resultado_antes_impuestos"] < 0) {
            $this->da_resultado_actual["impuesto_sociedades"] = 0;
        } else {
            $sociedades = $this->ejercicio->impsociedades;
            $this->da_resultado_actual["impuesto_sociedades"] = -1 * $this->da_resultado_actual["resultado_antes_impuestos"] * $sociedades / 100;
        }

        $this->da_resultado_actual["resultado_despues_impuestos"] = $this->da_resultado_actual["resultado_antes_impuestos"]
            + $this->da_resultado_actual["impuesto_sociedades"];
    }

    protected function loadBancos(): void
    {
        $this->bancos = [];

        $sql = "SELECT * FROM subcuentas WHERE codcuenta = '572' AND codejercicio = "
            . $this->dataBase->var2str($this->codejercicio) . ";";

        $data = $this->dataBase->select($sql);
        foreach ($data as $d) {
            $this->bancos[] = new subcuenta($d);
        }
    }

    protected function loadCajas(): void
    {
        $this->cajas = [];

        $sql = "SELECT * FROM subcuentas WHERE idcuenta IN "
            . "(SELECT idcuenta FROM cuentas WHERE codcuentaesp = " . $this->dataBase->var2str('CAJA')
            . " AND codejercicio = " . $this->dataBase->var2str($this->codejercicio) . ") ORDER BY codsubcuenta ASC;";

        $data = $this->dataBase->select($sql);
        foreach ($data as $sc) {
            $this->cajas[] = (object)$sc;
        }
    }

    protected function getGastosPendientes(): float
    {
        $total = 0.0;

        $sql = "SELECT SUM(total) as total FROM facturasprov WHERE pagada = false;";
        $data = $this->dataBase->select($sql);
        if ($data) {
            $total = floatval($data[0]['total']);
        }

        return $total;
    }

    protected function getCobrosPendientes(): float
    {
        $total = 0.0;

        $sql = "SELECT SUM(total) as total FROM facturascli WHERE pagada = false;";
        $data = $this->dataBase->select($sql);
        if ($data) {
            $total = floatval($data[0]['total']);
        }

        return $total;
    }

    protected function getVentasTotales(): float
    {
        $total = 0.0;

        $sql = "SELECT SUM(neto) as total FROM facturascli WHERE fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND fecha <= " . $this->dataBase->var2str($this->hasta) . ';';
        $data = $this->dataBase->select($sql);
        if ($data) {
            $total = floatval($data[0]['total']);
        }

        return $total;
    }

    protected function loadTreasury(): void
    {
        $this->code = $this->request->get('code', '');
        $this->codejercicio = null;
        $this->codejercicio_ant = null;
        $this->desde = date('01-01-Y');
        $this->ejercicio = new Ejercicio();
        $this->ejercicio_ant = new Ejercicio();
        $this->hasta = date('31-12-Y');

        // seleccionamos el ejercicio actual
        foreach (Ejercicio::all([], ['idempresa' => 'ASC', 'fechainicio' => 'DESC'], 0, 0) as $eje) {
            // si ya tenemos ejercicio, pero no tiene la misma empresa, paramos
            if ($this->ejercicio->exists() && $this->ejercicio->idempresa !== $eje->idempresa) {
                break;
            }

            if ($this->code === $eje->codejercicio || empty($this->code) && date('Y', strtotime($eje->fechafin)) === date('Y')) {
                $this->ejercicio = $eje;
                $this->code = $this->ejercicio->codejercicio;
                $this->codejercicio = $this->ejercicio->codejercicio;
                $this->desde = $this->ejercicio->fechainicio;
                $this->hasta = $this->ejercicio->fechafin;
            } elseif ($this->ejercicio->exists()) {
                $this->ejercicio_ant = $eje;
                $this->codejercicio_ant = $this->ejercicio_ant->codejercicio;
                break;
            }
        }

        // comprobamos el ejercicio
        if (false === $this->ejercicio->exists()) {
            return;
        }

        // comprobamos el ejercicio anterior
        if ($this->codejercicio_ant && false === $this->ejercicio_ant->exists()) {
            return;
        }

        $this->cuadroTesoreria();
        $this->cuadroGastosCobros();
        $this->cuadroReservas();
        $this->cuadroResultadoActual();
        $this->cuadroImpuestos();
        $this->cuadroResultadosSituacionCorto();
    }

    protected function saldoCuenta(string $cuenta, string $desde, string $hasta): float
    {
        $saldo = 0.0;

        if ($this->dataBase->tableExists('partidas')) {
            // calculamos el saldo de todos aquellos asientos que afecten a caja
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
}
