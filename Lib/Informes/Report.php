<?php
/**
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Base\ToolBox;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Report
{
    protected static $codejercicio;
    protected static $codejercicio_ant;
    protected static $familias;
    protected static $gastos;
    protected static $lastyear;
    protected static $resultado;
    protected static $ventas;
    protected static $year;

    protected static function days_in_month($month, $year)
    {
        // calculate number of days in a month CALC_GREGORIAN
        return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
    }

    protected static function defaultMoney()
    {
        return '<span style="color:#ccc;">' . ToolBox::coins()::format(0) . '</span>';
    }

    protected static function defaultPerc()
    {
        return '<span style="color:#ccc;">0 %</span>';
    }
}