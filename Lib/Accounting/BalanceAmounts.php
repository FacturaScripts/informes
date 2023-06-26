<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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
        $this->format = $params['format'] ?? 'pdf';
        $level = (int)($params['level'] ?? '0');

        // obtenemos las cuentas
        $cuenta = new Cuenta();
        $accounts = $cuenta->all($this->getAccountWhere($params), ['codcuenta' => 'ASC'], 0, 0);

        // obtenemos las subcuentas
        $subcuenta = new Subcuenta();
        $subaccounts = $subcuenta->all($this->getSubAccountWhere($params), [], 0, 0);

        // obtenemos los importes por subcuenta
        $amounts = $this->getData($params);

        $rows = [];
        foreach ($accounts as $account) {
            $debe = $haber = 0.00;
            $this->combineData($account, $accounts, $amounts, $debe, $haber);
            if ($debe == 0 && $haber == 0) {
                continue;
            }

            $saldo = $debe - $haber;
            if ($level > 0 && strlen($account->codcuenta) > $level) {
                continue;
            }

            // añadimos la línea de la cuenta
            $bold = strlen($account->codcuenta) <= 1;
            $rows[] = [
                'cuenta' => $this->formatValue($account->codcuenta, 'text', $bold),
                'descripcion' => $this->formatValue($account->descripcion, 'text', $bold),
                'debe' => $this->formatValue($debe, 'money', $bold),
                'haber' => $this->formatValue($haber, 'money', $bold),
                'saldo' => $this->formatValue($saldo, 'money', $bold)
            ];

            if ($level > 0) {
                continue;
            }

            // añadimos las líneas de las subcuentas y recalculamos debe y haber para comprobar que cuadran
            foreach ($amounts as $amount) {
                if ($amount['idcuenta'] == $account->idcuenta) {
                    $rows[] = $this->processAmountLine($subaccounts, $amount);
                }
            }
        }

        // we need this multidimensional array for printing support
        $totals = [['debe' => 0.00, 'haber' => 0.00, 'saldo' => 0.00]];
        $this->combineTotals($amounts, $totals);

        // every page is a table
        return [$rows, $totals];
    }

    /**
     * @param Cuenta $selAccount
     * @param Cuenta[] $accounts
     * @param array $amounts
     * @param float $debe
     * @param float $haber
     * @param int $max
     */
    protected function combineData(Cuenta &$selAccount, array &$accounts, array &$amounts, float &$debe, float &$haber, int $max = 7): void
    {
        $max--;
        if ($max < 0) {
            ToolBox::log()->error('account loop on ' . $selAccount->codcuenta);
            return;
        }

        // calculamos debe y haber de esta cuenta
        foreach ($amounts as $row) {
            if ($row['idcuenta'] == $selAccount->idcuenta) {
                $debe += (float)$row['debe'];
                $haber += (float)$row['haber'];
            }
        }

        // sumamos debe y haber de las cuentas hijas
        foreach ($accounts as $account) {
            if ($account->parent_idcuenta == $selAccount->idcuenta) {
                $this->combineData($account, $accounts, $amounts, $debe, $haber, $max);
            }
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
                    return $prefix . ToolBox::coins()->format($value, FS_NF0, '') . $suffix;
                }
                return number_format($value, FS_NF0, '.', '');

            default:
                if ($this->format === 'PDF') {
                    return $prefix . ToolBox::utils()->fixHtml($value) . $suffix;
                }
                return ToolBox::utils()->fixHtml($value);
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

        return $this->dataBase->select($sql);
    }

    /**
     * @param array $params
     *
     * @return DataBaseWhere[]
     */
    protected function getAccountWhere(array $params = []): array
    {
        $where = [new DataBaseWhere('codejercicio', $this->exercise->codejercicio)];

        $subaccountFrom = $params['subaccount-from'] ?? '';
        if (!empty($subaccountFrom)) {
            $where[] = new DataBaseWhere('codcuenta', substr($subaccountFrom, 0, 1), '>=');
        }

        $subaccountTo = $params['subaccount-to'] ?? '';
        if (!empty($subaccountTo)) {
            $where[] = new DataBaseWhere('codcuenta', substr($subaccountTo, 0, 4), '<=');
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
     * @param array $params
     *
     * @return DataBaseWhere[]
     */
    protected function getSubAccountWhere(array $params = []): array
    {
        $where = [new DataBaseWhere('codejercicio', $this->exercise->codejercicio)];

        $subaccountFrom = $params['subaccount-from'] ?? '';
        if (!empty($subaccountFrom)) {
            $where[] = new DataBaseWhere('codsubcuenta', $subaccountFrom, '>=');
        }

        $subaccountTo = $params['subaccount-to'] ?? '';
        if (!empty($subaccountTo)) {
            $where[] = new DataBaseWhere('codsubcuenta', $subaccountTo, '<=');
        }
        return $where;
    }

    /**
     * @param Subcuenta[] $subaccounts
     * @param array $amount
     *
     * @return array
     */
    protected function processAmountLine(array $subaccounts, array $amount): array
    {
        $debe = (float)$amount['debe'];
        $haber = (float)$amount['haber'];
        $saldo = $debe - $haber;

        foreach ($subaccounts as $subc) {
            if ($subc->idsubcuenta == $amount['idsubcuenta']) {
                return [
                    'cuenta' => $subc->codsubcuenta,
                    'descripcion' => $this->formatValue($subc->descripcion, 'text'),
                    'debe' => $this->formatValue($debe),
                    'haber' => $this->formatValue($haber),
                    'saldo' => $this->formatValue($saldo)
                ];
            }
        }

        return [
            'cuenta' => '---',
            'descripcion' => '---',
            'debe' => $this->formatValue($debe),
            'haber' => $this->formatValue($haber),
            'saldo' => $this->formatValue($saldo)
        ];
    }
}
