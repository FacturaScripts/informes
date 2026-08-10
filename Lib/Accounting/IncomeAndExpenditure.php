<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2026 Carlos García Gómez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Model\Asiento;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\BalanceAccount;
use FacturaScripts\Dinamic\Model\BalanceCode;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;

/**
 * Description of IncomeAndExpenditure
 *
 * @author Carlos García Gómez           <carlos@facturascripts.com>
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class IncomeAndExpenditure
{
    /** @var array */
    protected $accounts = [];

    /** @var string */
    protected $dateFrom;

    /** @var string */
    protected $dateFromPrev;

    /** @var string */
    protected $dateTo;

    /** @var string */
    protected $dateToPrev;

    /** @var DataBase */
    protected $db;

    /** @var Ejercicio */
    protected $exercise;

    /** @var Ejercicio */
    protected $exercisePrev;

    /** @var string */
    protected $format;

    /** @var array */
    protected $subaccounts = [];

    public function __construct()
    {
        $this->db = new DataBase();
        $this->db->connect();

        // needed dependencies
        new Partida();
        new BalanceAccount();
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
            Where::lte('fechainicio', $this->dateFromPrev),
            Where::gte('fechafin', $this->dateToPrev),
            Where::eq('idempresa', $idcompany)
        ];
        $this->exercisePrev->loadWhere($where);
        $this->format = $params['format'] ?? 'pdf';

        $return = [$this->getData('IG', $params)];
        if (empty($return[0])) {
            return [];
        }

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

    /**
     * Filtros comunes (rango de fechas, canal y exclusión de regularización/cierre)
     * para las consultas de saldos sobre la tabla de asientos.
     */
    protected function asientoFilters(string $codejercicio, string $channel): string
    {
        $sql = '';
        if ($codejercicio === $this->exercise->codejercicio) {
            $sql .= ' AND asientos.fecha BETWEEN ' . $this->db->var2str($this->dateFrom)
                . ' AND ' . $this->db->var2str($this->dateTo);
        } elseif ($codejercicio === $this->exercisePrev->codejercicio) {
            $sql .= ' AND asientos.fecha BETWEEN ' . $this->db->var2str($this->dateFromPrev)
                . ' AND ' . $this->db->var2str($this->dateToPrev);
        }

        if (!empty($channel)) {
            $sql .= ' AND asientos.canal = ' . $this->db->var2str($channel);
        }

        return $sql . ' AND (asientos.operacion IS NULL OR asientos.operacion NOT IN '
            . '(' . $this->db->var2str(Asiento::OPERATION_REGULARIZATION)
            . ',' . $this->db->var2str(Asiento::OPERATION_CLOSING) . '))';
    }

    protected function formatValue($value, string $type = 'money', bool $bold = false): string
    {
        $prefix = $bold ? '<b>' : '';
        $suffix = $bold ? '</b>' : '';
        switch ($type) {
            case 'money':
                if ($this->format === 'PDF') {
                    return $prefix . Tools::number($value) . $suffix;
                }
                $nf0 = Tools::settings('default', 'decimals', 2);
                return number_format($value, $nf0, '.', '');

            default:
                if ($this->format === 'PDF') {
                    return $prefix . Tools::fixHtml($value) . $suffix;
                }
                return Tools::fixHtml($value) ?? '';
        }
    }

    protected function getAmounts(BalanceCode $balance, string $codejercicio, array $params): float
    {
        $total = 0.00;
        if ($codejercicio === '-') {
            return $total;
        }

        $channel = $params['channel'] ?? '';
        foreach ($this->getBalanceAccounts($balance) as $model) {
            // la cuenta 129 tiene un cálculo especial (resultado del ejercicio)
            if ($model->codcuenta === '129') {
                $total += $this->getResultAmount($balance, $model, $codejercicio, $channel);
                continue;
            }

            // sumamos los saldos precargados de las subcuentas cuyo código empieza por el de la cuenta
            $debe = $haber = 0.00;
            $prefix = $model->codcuenta;
            $length = strlen($prefix);
            foreach ($this->getSubaccountBalances($codejercicio, $channel) as $codsubcuenta => $amounts) {
                if (strncmp($codsubcuenta, $prefix, $length) === 0) {
                    $debe += $amounts['debe'];
                    $haber += $amounts['haber'];
                }
            }

            // las cuentas de doble saldo solo computan en el lado que corresponde a su signo
            if (false === $model->matchesRestriction($debe, $haber)) {
                continue;
            }

            $total += $balance->calculate($debe, $haber);
        }

        return $total;
    }

    /**
     * @param BalanceCode $balance
     * @return BalanceAccount[]
     */
    protected function getBalanceAccounts(BalanceCode $balance): array
    {
        if (!array_key_exists($balance->id, $this->accounts)) {
            $where = [Where::eq('idbalance', $balance->id)];
            $this->accounts[$balance->id] = BalanceAccount::all($where, [], 0, 0);
        }

        return $this->accounts[$balance->id];
    }

    protected function getData(string $nature = 'A', array $params = []): array
    {
        $rows = [];
        $code1 = $this->exercise->codejercicio;
        $code2 = $this->exercisePrev->codejercicio ?? '-';

        // get balance codes
        $where = [
            Where::eq('nature', $nature),
            Where::eq('subtype', $params['subtype'] ?? 'normal'),
            Where::notEq('level1', '')
        ];
        $balances = $this->sortBalances(BalanceCode::all($where, [], 0, 0));

        // sin códigos de balance el informe saldría en blanco: avisamos y no generamos nada
        if (empty($balances)) {
            Tools::log()->warning('no-balance-codes-found', [
                '%nature%' => $nature,
                '%subtype%' => $params['subtype'] ?? 'normal'
            ]);
            return [];
        }

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
                if (!empty($amountsE1[$bal->codbalance]) || !empty($amountsE2[$bal->codbalance])) {
                    $rows[] = [
                        'descripcion' => '      ' . $bal->description4,
                        $code1 => $this->formatValue($amountsE1[$bal->codbalance]),
                        $code2 => $this->formatValue($amountsE2[$bal->codbalance])
                    ];
                }
            }
        }

        $this->addTotalsRow($rows, $balances, $code1, $amountsNE1, $code2, $amountsNE2);
        return $rows;
    }

    /**
     * Cálculo del resultado del ejercicio (cuenta 129) a partir de las cuentas
     * de gastos (6) e ingresos (7).
     */
    protected function getResultAmount(BalanceCode $balance, BalanceAccount $model, string $codejercicio, string $channel): float
    {
        $sql = "SELECT SUM(partidas.debe) as debe, SUM(partidas.haber) as haber"
            . " FROM partidas"
            . " LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento"
            . " LEFT JOIN subcuentas ON partidas.idsubcuenta = subcuentas.idsubcuenta"
            . " LEFT JOIN cuentas ON subcuentas.idcuenta = cuentas.idcuenta"
            . " WHERE asientos.codejercicio = " . $this->db->var2str($codejercicio)
            . " AND (partidas.codsubcuenta LIKE " . $this->db->var2str($model->codcuenta . '%')
            . " OR subcuentas.codcuenta LIKE '6%' OR subcuentas.codcuenta LIKE '7%')"
            . $this->asientoFilters($codejercicio, $channel);

        $total = 0.00;
        foreach ($this->db->select($sql) as $row) {
            $total += $balance->calculate((float)$row['debe'], (float)$row['haber']);
        }

        return $total;
    }

    /**
     * Devuelve, cacheados por ejercicio y canal, los saldos (debe/haber) agrupados
     * por subcuenta en una única consulta, evitando una consulta por cuenta.
     *
     * @return array<string, array{debe: float, haber: float}>
     */
    protected function getSubaccountBalances(string $codejercicio, string $channel): array
    {
        $key = $codejercicio . '-' . $channel;
        if (array_key_exists($key, $this->subaccounts)) {
            return $this->subaccounts[$key];
        }

        $sql = "SELECT partidas.codsubcuenta AS codsubcuenta,"
            . " SUM(partidas.debe) AS debe, SUM(partidas.haber) AS haber"
            . " FROM partidas"
            . " LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento"
            . " WHERE asientos.codejercicio = " . $this->db->var2str($codejercicio)
            . $this->asientoFilters($codejercicio, $channel)
            . " GROUP BY partidas.codsubcuenta";

        $balances = [];
        foreach ($this->db->select($sql) as $row) {
            $balances[$row['codsubcuenta']] = [
                'debe' => (float)$row['debe'],
                'haber' => (float)$row['haber']
            ];
        }

        $this->subaccounts[$key] = $balances;
        return $balances;
    }

    /**
     * Ordena los códigos de balance por nivel con comparación natural.
     * Se hace en PHP porque los niveles mezclan letras y números ('A', '2', '10'...)
     * y castear a entero en el ORDER BY falla en PostgreSQL.
     *
     * @param BalanceCode[] $balances
     * @return BalanceCode[]
     */
    protected function sortBalances(array $balances): array
    {
        usort($balances, function (BalanceCode $a, BalanceCode $b) {
            return strnatcmp((string)$a->level1, (string)$b->level1)
                ?: strnatcmp((string)$a->level2, (string)$b->level2)
                ?: strnatcmp((string)$a->level3, (string)$b->level3)
                ?: strnatcmp((string)$a->level4, (string)$b->level4);
        });

        return $balances;
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
