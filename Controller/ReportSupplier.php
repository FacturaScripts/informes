<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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

/**
 * Controlador para generar un informe de proveedores con diferentes métricas (activos, por país, por grupo, etc.)
 *
 * @author Esteban Sánchez Martínez
 */

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Plugins\Informes\Model\Report;

class ReportSupplier extends Controller
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'supplier';
        $data['icon'] = 'fa-solid fa-users';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
    }

    protected function loadCompanies(): void
    {
    }

    protected function loadData(): void
    {
    }

    protected function loadTotalSupplier(): void
    {
    }

    protected function loadActiveSupplier(): void
    {
    }

    protected function loadActiveSupplierYear(): void
    {
    }

    protected function loadInactiveSupplier()
    {
    }

    protected function loadNewSuppliers30Days()
    {
    }

    protected function loadNewSuppliersByMonth()
    {
    }

    protected function loadNewSuppliersByYear()
    {
    }

    protected function loadSuppliersByCountry(): void
    {
    }

    protected function loadSupplierByProvince(): void
    {
    }

    protected function loadInvoicesByProvince()
    {
    }

    protected function loadSuppliersWithPayables(): void
    {
    }

    protected function loadTopCreditors(): void
    {
    }
}
