<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2020-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Plugins\Informes\Lib\Accounting\BalanceAmounts;
use FacturaScripts\Plugins\Informes\Model\ReportAmount;

/**
 * Description of EditReportAmount
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class EditReportAmount extends EditController
{
    public function getModelClassName(): string
    {
        return 'ReportAmount';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'balance-amounts';
        $data['icon'] = 'fas fa-calculator';
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();

        // disable company column if there is only one company
        if ($this->empresa->count() < 2) {
            $this->views[$this->getMainViewName()]->disableColumn('company');
        }
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
            $this->exportManager->addTablePage($headers, $data);
        }

        $this->exportManager->show($this->response);
    }

    protected function generateReport(ReportAmount $model, string $format): array
    {
        $balanceAmount = new BalanceAmounts();
        return $balanceAmount->generate(
            $model->idcompany,
            $model->startdate,
            $model->enddate,
            [
                'channel' => $model->channel,
                'format' => $format,
                'idcompany' => $model->idcompany,
                'ignoreclosure' => $model->ignoreclosure,
                'ignoreregularization' => $model->ignoreregularization,
                'level' => $model->level,
                'subaccount-from' => $model->startcodsubaccount,
                'subaccount-to' => $model->endcodsubaccount
            ]
        );
    }
}
