<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2025-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CodeModel;

/**
 * Controlador para generar libros obligatorios de autónomos (libro de ingresos y libro de gastos)
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ReportBooks extends Controller
{
    /** @var string */
    public $desde;

    /** @var string */
    public $hasta;

    /** @var int */
    public $idempresa;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'books';
        $data['icon'] = 'fa-solid fa-book';
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

        $this->iniFilters();
        $this->generarLibro();
    }

    protected function generarLibro(): void
    {
        switch ($this->request->get('generar')) {
            case 'libro_ingresos':
                $this->libroIngresos();
                break;

            case 'libro_gastos':
                $this->libroGastos();
                break;
        }
    }

    protected function libroIngresos(): void
    {
        $sql = "SELECT f.fecha, f.numero, f.codigo, f.cifnif, f.nombrecliente,"
            . " f.observaciones, f.neto, f.totaliva, f.totalrecargo, f.total"
            . " FROM facturascli f"
            . " WHERE f.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND f.fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND f.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . " ORDER BY f.fecha ASC, CAST(f.numero AS UNSIGNED) ASC;";

        $data = $this->dataBase->select($sql);
        if (empty($data)) {
            Tools::log()->warning('no-data');
            return;
        }

        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        $filename = 'libro_ingresos_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // Cabeceras del libro de ingresos
        echo Tools::trans('date') . ';'
            . Tools::trans('invoice-number') . ';'
            . Tools::trans('document') . ';'
            . 'NIF' . ';'
            . Tools::trans('customer') . ';'
            . Tools::trans('concept') . ';'
            . Tools::trans('tax-base') . ';'
            . Tools::trans('vat') . ';'
            . Tools::trans('surcharge') . ';'
            . Tools::trans('total') . "\n";

        // Totales acumulados
        $totalNeto = 0;
        $totalIva = 0;
        $totalRecargo = 0;
        $totalGeneral = 0;
        $nfo = Tools::decimals();

        // Líneas del libro
        foreach ($data as $row) {
            echo $row['fecha'] . ';'
                . $row['numero'] . ';'
                . '"' . $row['codigo'] . '";'
                . '"' . $row['cifnif'] . '";'
                . '"' . Tools::fixHtml($row['nombrecliente']) . '";'
                . '"' . Tools::fixHtml($row['observaciones']) . '";'
                . number_format($row['neto'], $nfo, ',', '') . ';'
                . number_format($row['totaliva'], $nfo, ',', '') . ';'
                . number_format($row['totalrecargo'], $nfo, ',', '') . ';'
                . number_format($row['total'], $nfo, ',', '') . "\n";

            $totalNeto += $row['neto'];
            $totalIva += $row['totaliva'];
            $totalRecargo += $row['totalrecargo'];
            $totalGeneral += $row['total'];
        }

        // Línea de totales
        echo "\n" . strtoupper(Tools::trans('totals')) . ';;;;;;'
            . number_format($totalNeto, $nfo, ',', '') . ';'
            . number_format($totalIva, $nfo, ',', '') . ';'
            . number_format($totalRecargo, $nfo, ',', '') . ';'
            . number_format($totalGeneral, $nfo, ',', '') . "\n";
    }

    protected function libroGastos(): void
    {
        $sql = "SELECT a.fecha, a.numero,"
            . " COALESCE(f.numproveedor, p.documento) as documento,"
            . " p.codsubcuenta,"
            . " COALESCE(f.nombre, p.concepto) as concepto,"
            . " COALESCE(f.cifnif, p.cifnif) as cifnif,"
            . " COALESCE(f.neto, p.baseimponible) as baseimponible,"
            . " COALESCE(f.totaliva, p.iva) as iva,"
            . " COALESCE(f.totalrecargo, p.recargo) as recargo,"
            . " COALESCE(f.total, p.debe) as total"
            . " FROM asientos a"
            . " INNER JOIN partidas p ON a.idasiento = p.idasiento"
            . " LEFT JOIN facturasprov f ON a.idasiento = f.idasiento"
            . " WHERE a.fecha >= " . $this->dataBase->var2str($this->desde)
            . " AND a.fecha <= " . $this->dataBase->var2str($this->hasta)
            . " AND a.idempresa = " . $this->dataBase->var2str($this->idempresa)
            . " AND p.codsubcuenta LIKE '6%'"
            . " AND p.debe > 0"
            . " ORDER BY a.fecha ASC, CAST(a.numero AS UNSIGNED) ASC, p.codsubcuenta ASC;";

        $data = $this->dataBase->select($sql);
        if (empty($data)) {
            Tools::log()->warning('no-data');
            return;
        }

        $this->setTemplate(false);
        header("content-type:application/csv;charset=UTF-8");
        $filename = 'libro_gastos_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // Cabeceras del libro de gastos
        echo Tools::trans('date') . ';'
            . Tools::trans('number') . ';'
            . Tools::trans('document') . ';'
            . Tools::trans('subaccount') . ';'
            . Tools::trans('concept') . ';'
            . Tools::trans('nif') . ';'
            . Tools::trans('tax-base') . ';'
            . Tools::trans('vat') . ';'
            . Tools::trans('surcharge') . ';'
            . Tools::trans('total') . "\n";

        // Totales acumulados
        $totalBase = 0;
        $totalIva = 0;
        $totalRecargo = 0;
        $totalGeneral = 0;

        // Líneas del libro
        foreach ($data as $row) {
            echo $row['fecha'] . ';'
                . $row['numero'] . ';'
                . '"' . $row['documento'] . '";'
                . '"' . $row['codsubcuenta'] . '";'
                . '"' . Tools::fixHtml($row['concepto']) . '";'
                . '"' . $row['cifnif'] . '";'
                . number_format($row['baseimponible'], FS_NF0, ',', '') . ';'
                . number_format($row['iva'], FS_NF0, ',', '') . ';'
                . number_format($row['recargo'], FS_NF0, ',', '') . ';'
                . number_format($row['total'], FS_NF0, ',', '') . "\n";

            $totalBase += $row['baseimponible'];
            $totalIva += $row['iva'];
            $totalRecargo += $row['recargo'];
            $totalGeneral += $row['total'];
        }

        // Línea de totales
        echo "\n" . strtoupper(Tools::trans('totals')) . ';;;;;;'
            . number_format($totalBase, FS_NF0, ',', '') . ';'
            . number_format($totalIva, FS_NF0, ',', '') . ';'
            . number_format($totalRecargo, FS_NF0, ',', '') . ';'
            . number_format($totalGeneral, FS_NF0, ',', '') . "\n";
    }

    protected function iniFilters(): void
    {
        $this->desde = $this->request->get('desde', date('Y') . '-01-01');
        $this->hasta = $this->request->get('hasta', date('Y') . '-12-31');
        $this->idempresa = $this->request->get('idempresa', $this->user->idempresa);

        // Validar que la fecha desde sea anterior o igual a la fecha hasta
        if (strtotime($this->desde) > strtotime($this->hasta)) {
            Tools::log()->warning('start-date-later-end-date');
            $temp = $this->desde;
            $this->desde = $this->hasta;
            $this->hasta = $temp;
        }
    }
}
