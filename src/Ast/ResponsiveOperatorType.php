<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Types of responsive grid operations.
 *
 * Single arrows (>, <) create new rows in the grid.
 * Double arrows (>>, <<) nest content into the target slot as a subgrid.
 */
enum ResponsiveOperatorType: string
{
    /** Nest right: merge column INTO the slot to its left as nested subgrid content */
    case NestRight = '>>';

    /** Nest left: merge column INTO the slot to its right as nested subgrid content */
    case NestLeft = '<<';

    /** Fold down: create a new row below the current row for this column */
    case FoldDown = '>';

    /** Fold up: create a new row above the current row for this column */
    case FoldUp = '<';

    /** Hide: hide the column or row entirely */
    case Hide = '!';
}
