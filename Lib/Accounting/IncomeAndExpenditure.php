<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes\Lib\Accounting;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Plugins\Informes\Model\BalanceAccount;
use FacturaScripts\Plugins\Informes\Model\BalanceCode;

/**
 * Description of IncomeAndExpenditure
 *
 * @author Carlos García Gómez           <carlos@facturascripts.com>
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class IncomeAndExpenditure
{
    /** @var DataBase */
    protected $dataBase;

    /** @var string */
    protected $dateFrom;

    /** @var string */
    protected $dateFromPrev;

    /** @var string */
    protected $dateTo;

    /** @var string */
    protected $dateToPrev;

    /** @var Ejercicio */
    protected $exercise;

    /** @var Ejercicio */
    protected $exercisePrev;

    /** @var string */
    protected $format;

    public function __construct()
    {
        $this->dataBase = new DataBase();

        // needed dependencies
        new Partida();
    }

    public function generate(int $idcompany, string $dateFrom, string $dateTo, array $params = []): array
    {
        $this->exercise = new Ejercicio();
        $this->exercise->idempresa = $idcompany;
        if (false === $this->exercise->loadFromDate($dateFrom, false, false)) {
            return [];
        }

        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->dateFromPrev = $this->addToDate($dateFrom, '-1 year');
        $this->dateToPrev = $this->addToDate($dateTo, '-1 year');
        $this->exercisePrev = new Ejercicio();
        $where = [
            new DataBaseWhere('fechainicio', $this->dateFromPrev, '<='),
            new DataBaseWhere('fechafin', $this->dateToPrev, '>='),
            new DataBaseWhere('idempresa', $idcompany)
        ];
        $this->exercisePrev->loadFromCode('', $where);
        $this->format = $params['format'] ?? 'pdf';

        $return = [$this->getData('IG', $params)];

        // Si se ha elegido sin comparativo, eliminamos los datos del comparativo
        if (!($params['comparative'] ?? false)) {
            $code2 = $this->exercisePrev->codejercicio ?? '-';
            foreach ($return[0] as $key => $value) {
                unset($return[0][$key][$code2]);
            }
        }

        return $return;
    }

    protected function addToDate(string $date, string $add): string
    {
        return date('d-m-Y', strtotime($add, strtotime($date)));
    }

    /**
     * @param array $rows
     * @param BalanceCode[] $balances
     * @param string $code1
     * @param array $amounts1
     * @param string $code2
     * @param array $amounts2
     */
    protected function addTotalsRow(array &$rows, array $balances, string $code1, array $amounts1, string $code2, array $amounts2): void
    {
        $rows[] = ['descripcion' => '', $code1 => '', $code2 => ''];

        $levels = [];
        $total1 = $total2 = 0.00;
        foreach ($balances as $bal) {
            if (isset($levels[$bal->level1])) {
                continue;
            }

            $levels[$bal->level1] = $bal->level1;
            $total1 += $amounts1[$bal->level1];
            $total2 += $amounts2[$bal->level1] ?? 0.00;
        }

        $rows[] = [
            'descripcion' => $this->formatValue('Total (' . implode('+', $levels) . ')', 'text', true),
            $code1 => $this->formatValue($total1, 'money', true),
            $code2 => $this->formatValue($total2, 'money', true)
        ];
    }

    protected function formatValue(string $value, string $type = 'money', bool $bold = false): string
    {
        $prefix = $bold ? '<b>' : '';
        $suffix = $bold ? '</b>' : '';
        switch ($type) {
            case 'money':
                if ($this->format === 'PDF') {
                    return $prefix . ToolBox::coins()->format($value, FS_NF0, '') . $suffix;
                }
                return number_format($value, FS_NF0, '.', '');

            default:
                if ($this->format === 'PDF') {
                    return $prefix . ToolBox::utils()->fixHtml($value) . $suffix;
                }
                return ToolBox::utils()->fixHtml($value) ?? '';
        }
    }

    protected function getAmounts(BalanceCode $balance, string $codejercicio, array $params): float
    {
        $total = 0.00;
        if ($codejercicio === '-') {
            return $total;
        }

        $balAccount = new BalanceAccount();
        $where = [new DataBaseWhere('idbalance', $balance->id)];
        foreach ($balAccount->all($where, [], 0, 0) as $model) {
            $sql = "SELECT SUM(partidas.debe) AS debe, SUM(partidas.haber) AS haber"
                . " FROM partidas"
                . " LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento"
                . " WHERE asientos.codejercicio = " . $this->dataBase->var2str($codejercicio)
                . " AND partidas.codsubcuenta LIKE '" . $model->codcuenta . "%'";

            if ($model->codcuenta === '129') {
                $sql = "SELECT SUM(partidas.debe) as debe, SUM(partidas.haber) as haber"
                    . " FROM partidas"
                    . " LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento"
                    . " LEFT JOIN subcuentas ON partidas.idsubcuenta = subcuentas.idsubcuenta"
                    . " LEFT JOIN cuentas ON subcuentas.idcuenta = cuentas.idcuenta"
                    . " WHERE asientos.codejercicio = " . $this->dataBase->var2str($codejercicio)
                    . " AND (partidas.codsubcuenta LIKE '" . $model->codcuenta . "%' OR subcuentas.codcuenta LIKE '6%' OR subcuentas.codcuenta LIKE '7%')";
            }

            if ($codejercicio === $this->exercise->codejercicio) {
                $sql .= ' AND asientos.fecha BETWEEN ' . $this->dataBase->var2str($this->dateFrom)
                    . ' AND ' . $this->dataBase->var2str($this->dateTo);
            } elseif ($codejercicio === $this->exercisePrev->codejercicio) {
                $sql .= ' AND asientos.fecha BETWEEN ' . $this->dataBase->var2str($this->dateFromPrev)
                    . ' AND ' . $this->dataBase->var2str($this->dateToPrev);
            }

            $channel = $params['channel'] ?? '';
            if (!empty($channel)) {
                $sql .= ' AND asientos.canal = ' . $this->dataBase->var2str($channel);
            }

            $sql .= ' AND (asientos.operacion IS NULL OR asientos.operacion NOT IN '
                . '(' . $this->dataBase->var2str(Asiento::OPERATION_REGULARIZATION)
                . ',' . $this->dataBase->var2str(Asiento::OPERATION_CLOSING) . '))';

            foreach ($this->dataBase->select($sql) as $row) {
                $total += $balance->nature === 'A' ?
                    (float)$row['debe'] - (float)$row['haber'] :
                    (float)$row['haber'] - (float)$row['debe'];
            }
        }

        return $total;
    }

    protected function getData(string $nature = 'A', array $params = []): array
    {
        $rows = [];
        $code1 = $this->exercise->codejercicio;
        $code2 = $this->exercisePrev->codejercicio ?? '-';

        // get balance codes
        $balance = new BalanceCode();
        $where = [
            new DataBaseWhere('nature', $nature),
            new DataBaseWhere('subtype', $params['subtype'] ?? 'normal'),
            new DataBaseWhere('level1', '', '!=')
        ];
        $order = ['level1' => 'ASC', 'level2' => 'ASC', 'level3' => 'ASC', 'level4' => 'ASC'];
        $balances = $balance->all($where, $order, 0, 0);

        // get amounts
        $amountsE1 = [];
        $amountsE2 = [];
        $amountsNE1 = [];
        $amountsNE2 = [];
        foreach ($balances as $bal) {
            $this->sumAmounts($amountsE1, $amountsNE1, $bal, $code1, $params);
            $this->sumAmounts($amountsE2, $amountsNE2, $bal, $code2, $params);
        }

        // add to table
        $level1 = $level2 = $level3 = $level4 = '';
        foreach ($balances as $bal) {
            if ($bal->level1 != $level1 && !empty($bal->level1)) {
                $level1 = $bal->level1;
                $rows[] = ['descripcion' => '', $code1 => '', $code2 => ''];
                $rows[] = [
                    'descripcion' => $this->formatValue($bal->description1, 'text', true),
                    $code1 => $this->formatValue($amountsNE1[$bal->level1], 'money', true),
                    $code2 => $this->formatValue($amountsNE2[$bal->level1], 'money', true)
                ];
            }

            if ($bal->level2 != $level2 && !empty($bal->level2)) {
                $level2 = $bal->level2;
                $rows[] = [
                    'descripcion' => '  ' . $bal->description2,
                    $code1 => $this->formatValue($amountsNE1[$bal->level1 . '-' . $bal->level2]),
                    $code2 => $this->formatValue($amountsNE2[$bal->level1 . '-' . $bal->level2])
                ];
            }

            if ($bal->level3 != $level3 && !empty($bal->level3)) {
                $level3 = $bal->level3;
                $rows[] = [
                    'descripcion' => '    ' . $bal->description3,
                    $code1 => $this->formatValue($amountsNE1[$bal->level1 . '-' . $bal->level2 . '-' . $bal->level3]),
                    $code2 => $this->formatValue($amountsNE2[$bal->level1 . '-' . $bal->level2 . '-' . $bal->level3])
                ];
            }

            if ($bal->level4 != $level4 && !empty($bal->level4)) {
                $level4 = $bal->level4;
                if (empty($amountsE1[$bal->codbalance]) && empty($amountsE2[$bal->codbalance])) {
                    continue;
                }

                $rows[] = [
                    'descripcion' => '      ' . $bal->description4,
                    $code1 => $this->formatValue($amountsE1[$bal->codbalance]),
                    $code2 => $this->formatValue($amountsE2[$bal->codbalance])
                ];
            }
        }

        $this->addTotalsRow($rows, $balances, $code1, $amountsNE1, $code2, $amountsNE2);
        return $rows;
    }

    protected function sumAmounts(array &$amounts, array &$amountsN, BalanceCode $balance, string $codejercicio, array $params): void
    {
        $amounts[$balance->codbalance] = $total = $this->getAmounts($balance, $codejercicio, $params);

        if (isset($amountsN[$balance->level1])) {
            $amountsN[$balance->level1] += $total;
        } else {
            $amountsN[$balance->level1] = $total;
        }

        if (isset($amountsN[$balance->level1 . '-' . $balance->level2])) {
            $amountsN[$balance->level1 . '-' . $balance->level2] += $total;
        } else {
            $amountsN[$balance->level1 . '-' . $balance->level2] = $total;
        }

        if (isset($amountsN[$balance->level1 . '-' . $balance->level2 . '-' . $balance->level3])) {
            $amountsN[$balance->level1 . '-' . $balance->level2 . '-' . $balance->level3] += $total;
        } else {
            $amountsN[$balance->level1 . '-' . $balance->level2 . '-' . $balance->level3] = $total;
        }
    }
}
