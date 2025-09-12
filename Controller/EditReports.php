<?php

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Informes\Model\ReportBoard;
use FacturaScripts\Plugins\Informes\Model\ReportBoardLine;

/**
 * Este es un controlador específico para ediciones. Permite una o varias pestañas.
 * Cada una con un xml y modelo diferente, puede ser de tipo edición, listado, html o panel.
 * Además, hace uso de archivos de XMLView para definir qué columnas mostrar y cómo.
 *
 * https://facturascripts.com/publicaciones/editcontroller-642
 */
class EditReports extends EditController
{
    public function getModelClassName(): string
    {
        return 'Reports';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'Reports';
        $data['title'] = 'Reports';
        $data['icon'] = 'fa-solid fa-search';
        return $data;
    }

    protected function selectAction(): array
    {
        $field = $this->request->get('field', '');

        switch ($field) {
            case 'column':
                // para enviar los datos al widget column (es dinámico así que va en cada request diferente)
                // term contiene el valor del parent (tabla seleccionada)
                $table = (string)$this->request->get('term', '');
                if (empty($table) || false === $this->dataBase->tableExists($table)) {
                    return [['key' => null, 'value' => Tools::trans('no-data')]];
                }

                // únicamente columnas de tipo DATE
                $dateColumns = [];
                foreach ($this->dataBase->getColumns($table) as $colName => $colData) {
                    $type = strtolower($colData['type'] ?? '');
                    if ($type === 'date') {
                        $dateColumns[] = $colName;
                    }
                }
                sort($dateColumns);

                $results = [];
                foreach ($dateColumns as $col) {
                    $results[] = ['key' => $col, 'value' => $col];
                }

                if (empty($results)) {
                    $results[] = ['key' => null, 'value' => Tools::trans('no-data')];
                }
                return $results;
        }

        // Fallback a la lógica genérica
        return parent::selectAction();
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        // cargar las tablas en el widget de tablas (es estática, no cambia, así que se le pasan los datos directamente)
        $tables = $this->dataBase->getTables();
        $columnTable = $this->tab($viewName)->columnForName('table');
        $columnTable->widget->setValuesFromArray($tables);

    }
}
