<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\ReportAmount;
use FacturaScripts\Dinamic\Model\ReportBalance;
use FacturaScripts\Dinamic\Model\ReportLedger;

/**
 * Description of ListReportAccounting
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class ListReportAccounting extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'accounting-reports';
        $data['icon'] = 'fa-solid fa-balance-scale';
        return $data;
    }

    private function addCommonFilters(string $viewName): void
    {
        // si hay más de una empresa, añadimos un filtro para seleccionarla
        if ($this->empresa->count() > 1) {
            $this->addFilterSelect($viewName, 'idcompany', 'company', 'idcompany', Empresas::codeModel());
        }

        $this->addFilterNumber($viewName, 'channel', 'channel', 'channel', '=');
    }

    protected function addGenerateButton(string $viewName): void
    {
        $this->addButton($viewName, [
            'action' => 'generate-balances',
            'confirm' => true,
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'generate'
        ]);
    }

    /**
     * Inserts the views or tabs to display.
     */
    protected function createViews()
    {
        $this->createViewsLedger();
        $this->createViewsAmount();
        $this->createViewsBalance();
        $this->createViewsPreferences();
    }

    /**
     * Inserts the view for amount balances.
     *
     * @param string $viewName
     */
    protected function createViewsAmount(string $viewName = 'ListReportAmount'): void
    {
        $this->addView($viewName, 'ReportAmount', 'sums-and-balances', 'fa-solid fa-calculator')
            ->addOrderBy(['name'], 'name')
            ->addOrderBy(['idcompany', 'name'], 'company')
            ->addSearchFields(['name'])
            ->setSettings('btnPrint', false);

        $this->addCommonFilters($viewName);
        $this->addGenerateButton($viewName);
    }

    /**
     * Inserts the view for sheet and Profit & Loss balances.
     *
     * @param string $viewName
     */
    protected function createViewsBalance(string $viewName = 'ListReportBalance'): void
    {
        $this->addView($viewName, 'ReportBalance', 'balances', 'fa-solid fa-book')
            ->addOrderBy(['name'], 'name')
            ->addOrderBy(['idcompany', 'name'], 'company')
            ->addSearchFields(['name'])
            ->setSettings('btnPrint', false);

        $this->addCommonFilters($viewName);
        $this->addGenerateButton($viewName);
    }

    /**
     * Inserts the view for ledger report.
     *
     * @param string $viewName
     */
    protected function createViewsLedger(string $viewName = 'ListReportLedger'): void
    {
        $this->addView($viewName, 'ReportLedger', 'ledger', 'fa-solid fa-file-alt')
            ->addOrderBy(['name'], 'name')
            ->addOrderBy(['idcompany', 'name'], 'company')
            ->addSearchFields(['name'])
            ->setSettings('btnPrint', false);

        $this->addCommonFilters($viewName);
        $this->addGenerateButton($viewName);
    }

    /**
     * Inserts the view for setting balances report.
     *
     * @param string $viewName
     */
    protected function createViewsPreferences(string $viewName = 'ListBalanceCode'): void
    {
        $this->addView($viewName, 'BalanceCode', 'balance-codes', 'fa-solid fa-cogs')
            ->addOrderBy(['subtype', 'codbalance'], 'code', 1)
            ->addOrderBy(['description1'], 'description-1')
            ->addOrderBy(['description2'], 'description-2')
            ->addOrderBy(['description3'], 'description-3')
            ->addOrderBy(['description4'], 'description-4')
            ->addSearchFields(['codbalance', 'nature', 'description1', 'description2', 'description3', 'description4']);

        // añadimos filtros
        $i18n = Tools::lang();
        $subTypes = $this->codeModel->all('balance_codes', 'subtype', 'subtype');
        foreach ($subTypes as $subtype) {
            $subtype->description = $i18n->trans($subtype->description);
        }

        $this->listView($viewName)
            ->addFilterSelect('nature', 'nature', 'nature', [
                ['code' => '', 'description' => '------'],
                ['code' => 'A', 'description' => $i18n->trans('asset')],
                ['code' => 'P', 'description' => $i18n->trans('liabilities')],
                ['code' => 'PG', 'description' => $i18n->trans('profit-and-loss')],
                ['code' => 'IG', 'description' => $i18n->trans('income-and-expenses')]
            ])
            ->addFilterSelect('subtype', 'sub-type', 'subtype', $subTypes);
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action == 'generate-balances') {
            return $this->generateBalancesAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function generateBalancesAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('permission-denied');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $total = 0;
        foreach (Ejercicio::all() as $eje) {
            $this->generateBalances($total, $eje);
        }

        Tools::log()->notice('items-added-correctly', ['%num%' => $total]);
        return true;
    }

    /**
     * @param int $total
     * @param Ejercicio $ejercicio
     */
    protected function generateBalances(&$total, $ejercicio): void
    {
        // ledger
        $ledger = new ReportLedger();
        $where = [
            new DataBaseWhere('startdate', $ejercicio->fechainicio),
            new DataBaseWhere('enddate', $ejercicio->fechafin),
            new DataBaseWhere('idcompany', $ejercicio->idempresa)
        ];
        if (false === $ledger->loadFromCode('', $where)) {
            $ledger->enddate = $ejercicio->fechafin;
            $ledger->idcompany = $ejercicio->idempresa;
            $ledger->name = Tools::lang()->trans('ledger') . ' ' . $ejercicio->nombre;
            $ledger->startdate = $ejercicio->fechainicio;
            $total += $ledger->save() ? 1 : 0;
        }

        // amounts
        $amounts = new ReportAmount();
        if (false === $amounts->loadFromCode('', $where)) {
            $amounts->enddate = $ejercicio->fechafin;
            $amounts->idcompany = $ejercicio->idempresa;
            $amounts->ignoreclosure = true;
            $amounts->ignoreregularization = true;
            $amounts->name = Tools::lang()->trans('balance-amounts') . ' ' . $ejercicio->nombre;
            $amounts->startdate = $ejercicio->fechainicio;
            $total += $amounts->save() ? 1 : 0;
        }

        // extra balances
        foreach ([ReportBalance::TYPE_INCOME, ReportBalance::TYPE_PROFIT, ReportBalance::TYPE_SHEET] as $type) {
            $balance = new ReportBalance();
            $where2 = [
                new DataBaseWhere('startdate', $ejercicio->fechainicio),
                new DataBaseWhere('enddate', $ejercicio->fechafin),
                new DataBaseWhere('idcompany', $ejercicio->idempresa),
                new DataBaseWhere('type', $type)
            ];
            if (false === $balance->loadFromCode('', $where2)) {
                $balance->enddate = $ejercicio->fechafin;
                $balance->idcompany = $ejercicio->idempresa;
                $balance->name = Tools::lang()->trans($type) . ' ' . $ejercicio->nombre;
                $balance->startdate = $ejercicio->fechainicio;
                $balance->type = $type;
                $total += $balance->save() ? 1 : 0;
            }
        }
    }
}
