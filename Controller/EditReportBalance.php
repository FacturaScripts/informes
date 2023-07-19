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

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Model\Subcuenta;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Plugins\Informes\Lib\Accounting\BalanceSheet;
use FacturaScripts\Plugins\Informes\Lib\Accounting\IncomeAndExpenditure;
use FacturaScripts\Plugins\Informes\Lib\Accounting\ProfitAndLoss;
use FacturaScripts\Plugins\Informes\Model\BalanceAccount;
use FacturaScripts\Plugins\Informes\Model\BalanceCode;
use FacturaScripts\Plugins\Informes\Model\ReportBalance;

/**
 * Description of EditReportBalance
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class EditReportBalance extends EditController
{
    public function getModelClassName(): string
    {
        return 'ReportBalance';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'balances';
        $data['icon'] = 'fas fa-book';
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        // ocultamos la columna empresa si solo hay una
        if ($this->empresa->count() < 2) {
            $this->views[$this->getMainViewName()]->disableColumn('company');
        }

        $this->createViewsBalanceCodes();
        $this->setTabsPosition('bottom');
    }

    protected function createViewsBalanceCodes(string $viewName = 'ListBalanceCode'): void
    {
        $this->addListView($viewName, 'BalanceCode', 'balance-codes');
        $this->views[$viewName]->addOrderBy(['codbalance'], 'code');
        $this->views[$viewName]->addOrderBy(['description1'], 'description-1');
        $this->views[$viewName]->addOrderBy(['description2'], 'description-2');
        $this->views[$viewName]->addOrderBy(['description3'], 'description-3');
        $this->views[$viewName]->addOrderBy(['description4'], 'description-4');
        $this->views[$viewName]->addSearchFields([
            'codbalance', 'description1', 'description2', 'description3', 'description4'
        ]);

        // ocultamos las columnas nature y sub-type
        $this->views[$viewName]->disableColumn('nature');
        $this->views[$viewName]->disableColumn('sub-type');

        // desactivamos los botones
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    protected function execAfterAction($action)
    {
        if ($action === 'find-problems') {
            $this->findBadAccounts();
            $this->findMissingAccounts();
        }

        parent::execAfterAction($action);
    }

    protected function exportAction()
    {
        $model = $this->getModel();
        $format = $this->request->get('option', 'PDF');
        $pages = $this->generateReport($model, $format);
        if (empty($pages)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return;
        }

        $this->setTemplate(false);
        $view = $this->views[$this->getMainViewName()];
        $this->exportManager->newDoc($format, $model->name);
        $this->exportManager->addModelPage($view->model, $view->getColumns(), $this->toolBox()->i18n()->trans('accounting-reports'));

        foreach ($pages as $data) {
            $headers = empty($data) ? [] : array_keys($data[0]);
            $options = [$headers[1] => ['display' => 'right']];

            if (isset($headers[2])) {
                $options[$headers[2]] = ['display' => 'right'];
            }
            $this->exportManager->addTablePage($headers, $data, $options);
        }

        $this->exportManager->show($this->response);
    }

    protected function findBadAccounts(): void
    {
        // cargamos el ejercicio para la fecha de inicio
        $exercise = new Ejercicio();
        $exercise->idempresa = $this->getModel()->idcompany;
        if (false === $exercise->loadFromDate($this->getModel()->startdate, false, false)) {
            self::toolBox()->i18nLog()->warning('exercise-not-found');
            return;
        }

        // recorremos todas las cuentas
        $accountModel = new Cuenta();
        $accounts = $accountModel->all([], [], 0, 0);
        foreach ($accounts as $account) {
            if (empty($account->parent_codcuenta)) {
                continue;
            }

            // comprobamos que el campo parent_codcuenta son los primeros caracteres del campo codcuenta
            $len = strlen($account->parent_codcuenta);
            if (substr($account->codcuenta, 0, $len) !== $account->parent_codcuenta) {
                $this->toolBox()->i18nLog()->warning('account-bad-parent', ['%codcuenta%' => $account->codcuenta]);
            }
        }

        // recorremos todas las subcuentas del ejercicio
        $subAccountModel = new Subcuenta();
        $where = [new DataBaseWhere('codejercicio', $exercise->codejercicio)];
        foreach ($subAccountModel->all($where, [], 0, 0) as $subAccount) {
            // comprobamos que el campo codcuenta son los primeros caracteres del campo codsubcuenta
            $len = strlen($subAccount->codcuenta);
            if (substr($subAccount->codsubcuenta, 0, $len) !== $subAccount->codcuenta) {
                $this->toolBox()->i18nLog()->warning('subaccount-bad-codcuenta', ['%codsubcuenta%' => $subAccount->codsubcuenta]);
                continue;
            }

            if (empty($subAccount->saldo)) {
                continue;
            }

            // comprobamos si existe otra cuenta que sean los primeros caracteres del campo codsubcuenta
            foreach ($accounts as $account) {
                $len2 = strlen($account->codcuenta);
                if ($len2 <= $len) {
                    continue;
                }

                if (substr($subAccount->codsubcuenta, 0, $len2) === $account->codcuenta) {
                    $this->toolBox()->i18nLog()->info('subaccount-alt-codcuenta', [
                        '%codsubcuenta%' => $subAccount->codsubcuenta,
                        '%codcuenta%' => $subAccount->codcuenta,
                        '%alternative%' => $account->codcuenta
                    ]);
                }
            }
        }
    }

    protected function findMissingAccounts(): void
    {
        // cargamos el ejercicio para la fecha de inicio
        $exercise = new Ejercicio();
        $exercise->idempresa = $this->getModel()->idcompany;
        if (false === $exercise->loadFromDate($this->getModel()->startdate, false, false)) {
            self::toolBox()->i18nLog()->warning('exercise-not-found');
            return;
        }

        // buscamos las cuentas con saldo
        $cuentaModel = new Cuenta();
        $whereCuenta = [new DataBaseWhere('codejercicio', $exercise->codejercicio)];
        foreach ($cuentaModel->all($whereCuenta, [], 0, 0) as $cuenta) {
            // excluimos las cuentas que empiezan por 6 o 7
            if (strpos($cuenta->codcuenta, '6') === 0 || strpos($cuenta->codcuenta, '7') === 0) {
                continue;
            }

            // calculamos el debe, el haber y el saldo de la cuenta
            $debe = $haber = 0;
            foreach ($cuenta->getSubcuentas() as $subcuenta) {
                $debe += $subcuenta->debe;
                $haber += $subcuenta->haber;
            }
            // si el debe y el haber es cero, no hacemos nada
            if (abs($debe) < 0.01 && abs($haber) < 0.01) {
                continue;
            }
            $saldo = $debe - $haber;

            // comprobamos si la cuenta existe en el balance
            $balanceCuenta = new BalanceAccount();
            $whereBalance = [
                new DataBaseWhere('idbalance', implode(',', $this->getBalanceCodes()), 'IN'),
                new DataBaseWhere('codcuenta', $cuenta->codcuenta)
            ];
            if ($balanceCuenta->loadFromCode('', $whereBalance)) {
                continue;
            }

            // comprobamos el padre
            if ($cuenta->parent_codcuenta) {
                $wherePadre = [
                    new DataBaseWhere('idbalance', implode(',', $this->getBalanceCodes()), 'IN'),
                    new DataBaseWhere('codcuenta', $cuenta->parent_codcuenta)
                ];
                if ($balanceCuenta->loadFromCode('', $wherePadre)) {
                    continue;
                }
            }

            // si no existe la relación, avisamos
            $this->toolBox()->i18nLog()->info('account-missing-in-balance', [
                '%codcuenta%' => $cuenta->codcuenta,
                '%saldo%' => round($saldo, FS_NF0)
            ]);
        }
    }

    protected function generateReport(ReportBalance $model, string $format): array
    {
        $params = [
            'channel' => $model->channel,
            'format' => $format,
            'idcompany' => $model->idcompany,
            'subtype' => $model->subtype,
            'comparative' => $model->comparative
        ];

        switch ($model->type) {
            case 'balance-sheet':
                $balanceAmount = new BalanceSheet();
                return $balanceAmount->generate($model->idcompany, $model->startdate, $model->enddate, $params);

            case 'profit-and-loss':
                $profitAndLoss = new ProfitAndLoss();
                return $profitAndLoss->generate($model->idcompany, $model->startdate, $model->enddate, $params);

            case 'income-and-expenses':
                $incomeAndExpenditure = new IncomeAndExpenditure();
                return $incomeAndExpenditure->generate($model->idcompany, $model->startdate, $model->enddate, $params);
        }

        return [];
    }

    protected function getBalanceCodes(): array
    {
        $codes = [];

        $balanceModel = new BalanceCode();
        $where = $this->getPreferencesWhere();
        foreach ($balanceModel->all($where, ['codbalance' => 'ASC'], 0, 0) as $balance) {
            $codes[] = $balance->id;
        }

        return $codes;
    }

    protected function getPreferencesWhere(): array
    {
        switch ($this->getModel()->type) {
            case 'balance-sheet':
                return [
                    new DataBaseWhere('subtype', $this->getModel()->subtype),
                    new DataBaseWhere('nature', 'A'),
                    new DataBaseWhere('nature', 'P', '=', 'OR')
                ];

            case 'profit-and-loss':
                return [
                    new DataBaseWhere('subtype', $this->getModel()->subtype),
                    new DataBaseWhere('nature', 'PG')
                ];

            case 'income-and-expenses':
                return [
                    new DataBaseWhere('subtype', $this->getModel()->subtype),
                    new DataBaseWhere('nature', 'IG')
                ];
        }

        return [];
    }

    /**
     * Loads the data to display.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        switch ($viewName) {
            case 'ListBalanceCode':
                $where = $this->getPreferencesWhere();
                $view->loadData('', $where);
                break;

            case $mainViewName:
                parent::loadData($viewName, $view);
                if (false === $view->model->exists()) {
                    break;
                }
                // añadimos el botón para encontrar problemas
                $this->addButton($viewName, [
                    'action' => 'find-problems',
                    'color' => 'warning',
                    'icon' => 'fas fa-search',
                    'label' => 'find-problems'
                ]);
                break;
        }
    }
}
