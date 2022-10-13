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

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
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
        $data['icon'] = 'fas fa-balance-scale';
        return $data;
    }

    private function addCommonFilters(string $viewName)
    {
        // si hay más de una empresa, añadimos un filtro para seleccionarla
        if ($this->empresa->count() > 1) {
            $this->addFilterSelect($viewName, 'idcompany', 'company', 'idcompany', Empresas::codeModel());
        }

        $this->addFilterNumber($viewName, 'channel', 'channel', 'channel', '=');
    }

    protected function addGenerateButton(string $viewName)
    {
        $this->addButton($viewName, [
            'action' => 'generate-balances',
            'confirm' => true,
            'icon' => 'fas fa-magic',
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
    protected function createViewsAmount(string $viewName = 'ListReportAmount')
    {
        $this->addView($viewName, 'ReportAmount', 'sums-and-balances', 'fas fa-calculator');
        $this->addOrderBy($viewName, ['name'], 'name');
        $this->addOrderBy($viewName, ['idcompany', 'name'], 'company');
        $this->addSearchFields($viewName, ['name']);
        $this->addCommonFilters($viewName);
        $this->addGenerateButton($viewName);

        // desactivamos el botón de imprimir
        $this->setSettings($viewName, 'btnPrint', false);
    }

    /**
     * Inserts the view for sheet and Profit & Loss balances.
     *
     * @param string $viewName
     */
    protected function createViewsBalance(string $viewName = 'ListReportBalance')
    {
        $this->addView($viewName, 'ReportBalance', 'balances', 'fas fa-book');
        $this->addOrderBy($viewName, ['name'], 'name');
        $this->addOrderBy($viewName, ['idcompany', 'name'], 'company');
        $this->addSearchFields($viewName, ['name']);
        $this->addCommonFilters($viewName);
        $this->addGenerateButton($viewName);

        // desactivamos el botón de imprimir
        $this->setSettings($viewName, 'btnPrint', false);
    }

    /**
     * Inserts the view for ledger report.
     *
     * @param string $viewName
     */
    protected function createViewsLedger(string $viewName = 'ListReportLedger')
    {
        $this->addView($viewName, 'ReportLedger', 'ledger', 'fas fa-file-alt');
        $this->addOrderBy($viewName, ['name'], 'name');
        $this->addOrderBy($viewName, ['idcompany', 'name'], 'company');
        $this->addSearchFields($viewName, ['name']);
        $this->addCommonFilters($viewName);
        $this->addGenerateButton($viewName);

        // desactivamos el botón de imprimir
        $this->setSettings($viewName, 'btnPrint', false);
    }

    /**
     * Inserts the view for setting balances report.
     *
     * @param string $viewName
     */
    protected function createViewsPreferences(string $viewName = 'ListBalanceCode')
    {
        $this->addView($viewName, 'BalanceCode', 'balance-codes', 'fas fa-cogs');
        $this->addOrderBy($viewName, ['subtype', 'codbalance'], 'code', 1);
        $this->addOrderBy($viewName, ['description1'], 'description-1');
        $this->addOrderBy($viewName, ['description2'], 'description-2');
        $this->addOrderBy($viewName, ['description3'], 'description-3');
        $this->addOrderBy($viewName, ['description4'], 'description-4');
        $this->addSearchFields($viewName, [
            'codbalance', 'nature', 'description1', 'description2', 'description3', 'description4'
        ]);

        // añadimos filtro de nature
        $i18n = $this->toolBox()->i18n();
        $this->addFilterSelect($viewName, 'nature', 'nature', 'nature', [
            ['code' => '', 'description' => '------'],
            ['code' => 'A', 'description' => $i18n->trans('asset')],
            ['code' => 'P', 'description' => $i18n->trans('liabilities')],
            ['code' => 'PG', 'description' => $i18n->trans('profit-and-loss')],
            ['code' => 'IG', 'description' => $i18n->trans('income-and-expenses')]
        ]);

        // añadimos filtro de subtype
        $subTypes = $this->codeModel->all('balance_codes', 'subtype', 'subtype');
        foreach ($subTypes as $subtype) {
            $subtype->description = $i18n->trans($subtype->description);
        }
        $this->addFilterSelect($viewName, 'subtype', 'sub-type', 'subtype', $subTypes);
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'generate-balances':
                return $this->generateBalancesAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function generateBalancesAction(): bool
    {
        $total = 0;
        $ejercicioModel = new Ejercicio();
        foreach ($ejercicioModel->all() as $eje) {
            $this->generateBalances($total, $eje);
        }

        $this->toolBox()->i18nLog()->notice('items-added-correctly', ['%num%' => $total]);
        return true;
    }

    /**
     * @param int $total
     * @param Ejercicio $ejercicio
     */
    protected function generateBalances(&$total, $ejercicio)
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
            $ledger->name = $this->toolBox()->i18n()->trans('ledger') . ' ' . $ejercicio->nombre;
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
            $amounts->name = $this->toolBox()->i18n()->trans('balance-amounts') . ' ' . $ejercicio->nombre;
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
                $balance->name = $this->toolBox()->i18n()->trans($type) . ' ' . $ejercicio->nombre;
                $balance->startdate = $ejercicio->fechainicio;
                $balance->type = $type;
                $total += $balance->save() ? 1 : 0;
            }
        }
    }
}
