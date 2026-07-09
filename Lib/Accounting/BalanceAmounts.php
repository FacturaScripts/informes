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
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * Description of BalanceAmounts
 *
 * @author Carlos García Gómez  <carlos@facturascripts.com>
 * @author nazca                <comercial@nazcanetworks.com>
 */
class BalanceAmounts
{
    /** @var DataBase */
    protected $dataBase;

    /** @var string */
    protected $dateFrom;

    /** @var string */
    protected $dateTo;

    /** @var Ejercicio */
    protected $exercise;

    /** @var string */
    protected $format;

    /** @var bool */
    protected $showBalanceOpening;

    public function __construct()
    {
        $this->dataBase = new DataBase();
        $this->dataBase->connect();

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
        $this->format = strtoupper($params['format'] ?? 'PDF');
        $this->showBalanceOpening = (bool)($params['show_balance_opening'] ?? false);
        $level = (int)($params['level'] ?? '0');

        // obtenemos las cuentas
        $accounts = Cuenta::all($this->getAccountWhere($params), ['codcuenta' => 'ASC'], 0, 0);

        // obtenemos las subcuentas
        $subaccounts = Subcuenta::all($this->getSubAccountWhere($params), [], 0, 0);

        // obtenemos los importes por subcuenta
        $amounts = $this->getData($params);

        // si se solicita, cargamos las partidas del asiento de apertura indexadas por codsubcuenta
        $openingAmounts = $this->showBalanceOpening ? $this->getOpeningData($params) : [];

        // añadimos las subcuentas que solo aparecen en el asiento de apertura, sin movimientos en el periodo,
        // para que su saldo de apertura no desaparezca del informe
        $this->addOpeningOnlyAmounts($amounts, $openingAmounts, $subaccounts);

        // indexamos importes, cuentas hijas y subcuentas para evitar escaneos lineales dentro del bucle
        $amountsByCuenta = [];
        foreach ($amounts as $row) {
            $amountsByCuenta[$row['idcuenta']][] = $row;
        }
        $childrenByParent = [];
        foreach ($accounts as $account) {
            if ($account->parent_idcuenta) {
                $childrenByParent[$account->parent_idcuenta][] = $account;
            }
        }
        $subaccountsById = [];
        foreach ($subaccounts as $subaccount) {
            $subaccountsById[$subaccount->idsubcuenta] = $subaccount;
        }

        $ignoreOpening = (bool)($params['ignore_opening'] ?? false);
        $ignoreRegularization = (bool)($params['ignoreregularization'] ?? false);
        $ignoreClosure = (bool)($params['ignoreclosure'] ?? false);

        $rows = [];
        foreach ($accounts as $account) {
            if ($level > 0 && $level <= 4 && strlen($account->codcuenta) > $level) {
                continue;
            }

            $debe = $haber = 0.00;
            $this->combineData($account, $amountsByCuenta, $childrenByParent, $debe, $haber);
            if (abs($debe) < 0.01 && abs($haber) < 0.01 &&
                false === $this->hasOpening($account, $amountsByCuenta, $childrenByParent, $openingAmounts)) {
                continue;
            }

            $saldo = $debe - $haber;

            // añadimos la línea de la cuenta (opening siempre es 0 para cuentas agrupación)
            $bold = strlen($account->codcuenta) <= 1;
            $accountRow = [
                'cuenta' => $this->formatValue($account->codcuenta, 'text', $bold),
                'descripcion' => $this->formatValue($account->descripcion, 'text', $bold),
                'debe' => $this->formatValue($debe, 'money', $bold),
                'haber' => $this->formatValue($haber, 'money', $bold),
                'saldo' => $this->formatValue($saldo, 'money', $bold),
            ];
            if ($this->showBalanceOpening) {
                $accountRow['opening'] = $this->formatValue('0', 'money', $bold);
            }
            $rows[] = $accountRow;

            if ($level > 0 && $level <= 4) {
                continue;
            }

            // añadimos las líneas de las subcuentas y recalculamos debe y haber para comprobar que cuadren
            $debe2 = $haber2 = 0.00;

            // importes que pertenecen a esta cuenta
            $accountAmounts = $amountsByCuenta[$account->idcuenta] ?? [];

            // si hay nivel intermedio, agrupamos las subcuentas por prefijo según el nivel indicado
            if ($level > 0 && $level < $this->exercise->longsubcuenta) {
                $groupedAmounts = $this->groupAmountsByLevel($level, $accountAmounts, $openingAmounts);
                foreach ($groupedAmounts as $groupedAmount) {
                    $rows[] = $groupedAmount;
                    $debe2 += (float)$groupedAmount['_debe_raw'];
                    $haber2 += (float)$groupedAmount['_haber_raw'];
                }
            } else {
                foreach ($accountAmounts as $amount) {
                    if ($level > 4 && strlen($amount['codsubcuenta']) > $level) {
                        continue;
                    }

                    $rows[] = $this->processAmountLine($subaccountsById, $amount, $openingAmounts);
                    $debe2 += (float)$amount['debe'];
                    $haber2 += (float)$amount['haber'];
                }
            }
            if (abs($debe2) < 0.01 && abs($haber2) < 0.01) {
                continue;
            }

            // si se ha marcado la opción de ignorar asientos de apertura, regularización o cierre, no comprobamos que cuadren
            if ($ignoreOpening || $ignoreRegularization || $ignoreClosure) {
                continue;
            }

            // comprobamos que cuadran debe y haber
            if (abs($debe - $debe2) >= 0.01) {
                Tools::log()->error(
                    'debit-not-match-account',
                    ['%account%' => $account->codcuenta, '%debit%' => $debe, '%sum%' => $debe2]
                );
                return [];
            }
            if (abs($haber - $haber2) >= 0.01) {
                Tools::log()->error(
                    'credit-not-match-account',
                    ['%account%' => $account->codcuenta, '%credit%' => $haber, '%sum%' => $haber2]
                );
                return [];
            }
        }

        // we need this multidimensional array for printing support
        $totals = [['debe' => 0.00, 'haber' => 0.00, 'saldo' => 0.00]];
        $this->combineTotals($amounts, $totals);

        // every page is a table
        return [$rows, $totals];
    }

    /**
     * Añade a $amounts las subcuentas que aparecen en el asiento de apertura pero no tienen
     * movimientos en el periodo, con debe y haber a cero, para que se incluyan en el informe.
     *
     * @param array $amounts
     * @param array $openingAmounts
     * @param Subcuenta[] $subaccounts
     */
    protected function addOpeningOnlyAmounts(array &$amounts, array $openingAmounts, array $subaccounts): void
    {
        if (empty($openingAmounts)) {
            return;
        }

        $existing = [];
        foreach ($amounts as $row) {
            $existing[$row['codsubcuenta']] = true;
        }

        $added = false;
        foreach ($subaccounts as $subaccount) {
            if (isset($existing[$subaccount->codsubcuenta]) || false === isset($openingAmounts[$subaccount->codsubcuenta])) {
                continue;
            }

            $amounts[] = [
                'idcuenta' => $subaccount->idcuenta,
                'idsubcuenta' => $subaccount->idsubcuenta,
                'codsubcuenta' => $subaccount->codsubcuenta,
                'debe' => 0.00,
                'haber' => 0.00,
            ];
            $added = true;
        }

        // mantenemos el orden por codsubcuenta
        if ($added) {
            usort($amounts, function ($a, $b) {
                return strcmp($a['codsubcuenta'], $b['codsubcuenta']);
            });
        }
    }

    /**
     * @param Cuenta $selAccount
     * @param array $amountsByCuenta
     * @param array $childrenByParent
     * @param float $debe
     * @param float $haber
     * @param int $max
     */
    protected function combineData(Cuenta &$selAccount, array &$amountsByCuenta, array &$childrenByParent, float &$debe, float &$haber, int $max = 7): void
    {
        $max--;
        if ($max < 0) {
            Tools::log()->error('account loop on ' . $selAccount->codcuenta);
            return;
        }

        // calculamos debe y haber de esta cuenta
        foreach ($amountsByCuenta[$selAccount->idcuenta] ?? [] as $row) {
            $debe += (float)$row['debe'];
            $haber += (float)$row['haber'];
        }

        // sumamos debe y haber de las cuentas hijas
        foreach ($childrenByParent[$selAccount->idcuenta] ?? [] as $account) {
            $this->combineData($account, $amountsByCuenta, $childrenByParent, $debe, $haber, $max);
        }
    }

    protected function combineTotals(array &$amounts, array &$totals): void
    {
        $debe = $haber = 0.00;
        foreach ($amounts as $row) {
            $debe += (float)$row['debe'];
            $haber += (float)$row['haber'];
        }
        $saldo = $debe - $haber;

        $totals[0]['debe'] = $this->formatValue($debe);
        $totals[0]['haber'] = $this->formatValue($haber);
        $totals[0]['saldo'] = $this->formatValue($saldo);
    }

    protected function formatValue(string $value, string $type = 'money', bool $bold = false): string
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
                return Tools::fixHtml($value);
        }
    }

    protected function getData(array $params = []): array
    {
        if (false === $this->dataBase->tableExists('partidas')) {
            return [];
        }

        $sql = 'SELECT subcuentas.idcuenta, partidas.idsubcuenta, partidas.codsubcuenta,'
            . ' SUM(partidas.debe) AS debe, SUM(partidas.haber) AS haber'
            . ' FROM partidas'
            . ' LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento'
            . ' LEFT JOIN subcuentas ON subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' WHERE ' . $this->getDataWhere($params)
            . ' GROUP BY 1, 2, 3'
            . ' ORDER BY 3 ASC';

        // descartamos las partidas cuya subcuenta ya no existe: ninguna cuenta las recogería
        // en las filas del informe, pero sí se sumarían en los totales, descuadrándolos
        $amounts = [];
        foreach ($this->dataBase->select($sql) as $row) {
            if (empty($row['idcuenta'])) {
                Tools::log()->warning('subaccount-not-found', [
                    '%subAccountCode%' => $row['codsubcuenta'],
                    '%code%' => $row['codsubcuenta'],
                ]);
                continue;
            }
            $amounts[] = $row;
        }
        return $amounts;
    }

    protected function getAccountWhere(array $params = []): array
    {
        $where = [Where::eq('codejercicio', $this->exercise->codejercicio)];

        $subaccountFrom = $params['subaccount-from'] ?? '';
        if (!empty($subaccountFrom)) {
            $where[] = Where::gte('codcuenta', substr($subaccountFrom, 0, 1));
        }

        $subaccountTo = $params['subaccount-to'] ?? '';
        if (!empty($subaccountTo)) {
            $where[] = Where::lte('codcuenta', substr($subaccountTo, 0, 4));
        }
        return $where;
    }

    protected function getDataWhere(array $params = []): string
    {
        $where = 'asientos.codejercicio = ' . $this->dataBase->var2str($this->exercise->codejercicio)
            . ' AND asientos.fecha BETWEEN ' . $this->dataBase->var2str($this->dateFrom)
            . ' AND ' . $this->dataBase->var2str($this->dateTo);

        $channel = $params['channel'] ?? '';
        if (!empty($channel)) {
            $where .= ' AND asientos.canal = ' . $this->dataBase->var2str($channel);
        }

        $ignoreOpening = (bool)($params['ignore_opening'] ?? false);
        if ($ignoreOpening) {
            $where .= ' AND (asientos.operacion IS NULL OR asientos.operacion != '
                . $this->dataBase->var2str(Asiento::OPERATION_OPENING) . ')';
        }

        $ignoreRegularization = (bool)($params['ignoreregularization'] ?? false);
        if ($ignoreRegularization) {
            $where .= ' AND (asientos.operacion IS NULL OR asientos.operacion != '
                . $this->dataBase->var2str(Asiento::OPERATION_REGULARIZATION) . ')';
        }

        $ignoreClosure = (bool)($params['ignoreclosure'] ?? false);
        if ($ignoreClosure) {
            $where .= ' AND (asientos.operacion IS NULL OR asientos.operacion != '
                . $this->dataBase->var2str(Asiento::OPERATION_CLOSING) . ')';
        }

        $subaccountFrom = $params['subaccount-from'] ?? '';
        if (!empty($subaccountFrom)) {
            $where .= ' AND partidas.codsubcuenta >= ' . $this->dataBase->var2str($subaccountFrom);
        }

        $subaccountTo = $params['subaccount-to'] ?? $subaccountFrom;
        if (!empty($subaccountTo)) {
            $where .= ' AND partidas.codsubcuenta <= ' . $this->dataBase->var2str($subaccountTo);
        }

        return $where;
    }

    /**
     * Comprueba si la cuenta (o alguna de sus cuentas hijas) tiene alguna subcuenta
     * presente en el asiento de apertura.
     *
     * @param Cuenta $selAccount
     * @param array $amountsByCuenta
     * @param array $childrenByParent
     * @param array $openingAmounts
     * @param int $max
     */
    protected function hasOpening(Cuenta &$selAccount, array &$amountsByCuenta, array &$childrenByParent, array &$openingAmounts, int $max = 7): bool
    {
        if (empty($openingAmounts)) {
            return false;
        }

        $max--;
        if ($max < 0) {
            return false;
        }

        foreach ($amountsByCuenta[$selAccount->idcuenta] ?? [] as $row) {
            if (isset($openingAmounts[$row['codsubcuenta']])) {
                return true;
            }
        }

        foreach ($childrenByParent[$selAccount->idcuenta] ?? [] as $account) {
            if ($this->hasOpening($account, $amountsByCuenta, $childrenByParent, $openingAmounts, $max)) {
                return true;
            }
        }

        return false;
    }

    protected function getSubAccountWhere(array $params = []): array
    {
        $where = [Where::eq('codejercicio', $this->exercise->codejercicio)];

        $subaccountFrom = $params['subaccount-from'] ?? '';
        if (!empty($subaccountFrom)) {
            $where[] = Where::gte('codsubcuenta', $subaccountFrom);
        }

        $subaccountTo = $params['subaccount-to'] ?? '';
        if (!empty($subaccountTo)) {
            $where[] = Where::lte('codsubcuenta', $subaccountTo);
        }
        return $where;
    }

    /**
     * Obtiene las partidas del asiento de apertura del ejercicio actual indexadas por codsubcuenta.
     * Solo toma el primer asiento de apertura encontrado (operacion = 'A').
     */
    protected function getOpeningData(array $params = []): array
    {
        if (false === $this->dataBase->tableExists('partidas')) {
            return [];
        }

        // buscamos el idasiento del asiento de apertura del ejercicio
        $sqlAsiento = 'SELECT idasiento FROM asientos'
            . ' WHERE codejercicio = ' . $this->dataBase->var2str($this->exercise->codejercicio)
            . ' AND operacion = ' . $this->dataBase->var2str(Asiento::OPERATION_OPENING)
            . ' ORDER BY idasiento ASC'
            . ' LIMIT 1';

        $rowAsiento = $this->dataBase->select($sqlAsiento);
        if (empty($rowAsiento)) {
            return [];
        }

        $idAsiento = $rowAsiento[0]['idasiento'];

        // obtenemos las partidas de ese asiento agrupadas por codsubcuenta
        $sql = 'SELECT codsubcuenta, SUM(debe) AS debe, SUM(haber) AS haber'
            . ' FROM partidas'
            . ' WHERE idasiento = ' . $this->dataBase->var2str($idAsiento);

        $subaccountFrom = $params['subaccount-from'] ?? '';
        if (!empty($subaccountFrom)) {
            $sql .= ' AND codsubcuenta >= ' . $this->dataBase->var2str($subaccountFrom);
        }

        $subaccountTo = $params['subaccount-to'] ?? $subaccountFrom;
        if (!empty($subaccountTo)) {
            $sql .= ' AND codsubcuenta <= ' . $this->dataBase->var2str($subaccountTo);
        }

        $sql .= ' GROUP BY codsubcuenta ORDER BY codsubcuenta ASC';

        // indexamos por codsubcuenta para búsqueda directa O(1)
        $result = [];
        foreach ($this->dataBase->select($sql) as $row) {
            $result[$row['codsubcuenta']] = [
                'debe' => (float)$row['debe'],
                'haber' => (float)$row['haber'],
            ];
        }
        return $result;
    }

    /**
     * Agrupa los importes de subcuentas por prefijo según el nivel indicado y devuelve
     * las filas ya formateadas. Cada fila incluye las claves internas '_debe_raw' y '_haber_raw'
     * con los valores numéricos para poder acumular los totales en el método llamante.
     *
     * Ejemplo con level=5 y subcuenta 4300000000:
     *   - El prefijo sería '43000' y se sumarían 4300000001, 4300000002, 4300000034, 4300004396.
     * Ejemplo con level=8 y subcuenta 4300000000:
     *   - El prefijo '43000000' acumula 4300000001 y 4300000002.
     *   - El prefijo '43000003' acumula 4300000034.
     *   - El prefijo '43000439' acumula 4300004396.
     */
    protected function groupAmountsByLevel(int $level, array $accountAmounts, array $openingAmounts = []): array
    {
        // agrupamos los importes por el prefijo de longitud $level
        $groups = [];
        foreach ($accountAmounts as $amount) {
            $prefix = substr($amount['codsubcuenta'], 0, $level);
            if (!isset($groups[$prefix])) {
                $groups[$prefix] = [
                    'prefix' => $prefix,
                    'debe' => 0.00,
                    'haber' => 0.00,
                    'opening' => null,
                ];
            }
            $groups[$prefix]['debe'] += (float)$amount['debe'];
            $groups[$prefix]['haber'] += (float)$amount['haber'];

            // acumulamos el saldo de apertura si está activo
            if ($this->showBalanceOpening) {
                $codsubcuenta = $amount['codsubcuenta'];
                if (isset($openingAmounts[$codsubcuenta])) {
                    $openingSaldo = $openingAmounts[$codsubcuenta]['debe'] - $openingAmounts[$codsubcuenta]['haber'];
                } else {
                    $openingSaldo = 0.00;
                }
                if ($groups[$prefix]['opening'] === null) {
                    $groups[$prefix]['opening'] = 0.00;
                }
                $groups[$prefix]['opening'] += $openingSaldo;
            }
        }

        // construimos las filas formateadas
        $rows = [];
        foreach ($groups as $group) {
            $debe = $group['debe'];
            $haber = $group['haber'];
            $saldo = $debe - $haber;

            $row = [
                'cuenta' => $group['prefix'],
                'descripcion' => $this->formatValue($group['prefix'], 'text'),
                'debe' => $this->formatValue((string)$debe),
                'haber' => $this->formatValue((string)$haber),
                'saldo' => $this->formatValue((string)$saldo),
                // valores raw para acumular totales en el método llamante
                '_debe_raw' => $debe,
                '_haber_raw' => $haber,
            ];

            if ($group['opening'] !== null) {
                $row['opening'] = $this->formatValue((string)$group['opening']);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    protected function processAmountLine(array $subaccountsById, array $amount, array $openingAmounts = []): array
    {
        $debe = (float)$amount['debe'];
        $haber = (float)$amount['haber'];
        $saldo = $debe - $haber;

        // calculamos el saldo de apertura: buscamos la subcuenta exacta en el asiento de apertura
        $openingSaldo = null;
        if ($this->showBalanceOpening) {
            $codsubcuenta = $amount['codsubcuenta'];
            if (isset($openingAmounts[$codsubcuenta])) {
                $openingSaldo = $openingAmounts[$codsubcuenta]['debe'] - $openingAmounts[$codsubcuenta]['haber'];
            } else {
                // la subcuenta no está en el asiento de apertura → 0
                $openingSaldo = 0.00;
            }
        }

        $subc = $subaccountsById[$amount['idsubcuenta']] ?? null;
        $row = [
            'cuenta' => $subc ? $subc->codsubcuenta : '---',
            'descripcion' => $subc ? $this->formatValue($subc->descripcion, 'text') : '---',
            'debe' => $this->formatValue($debe),
            'haber' => $this->formatValue($haber),
            'saldo' => $this->formatValue($saldo),
        ];
        if ($openingSaldo !== null) {
            $row['opening'] = $this->formatValue((string)$openingSaldo);
        }
        return $row;
    }
}
