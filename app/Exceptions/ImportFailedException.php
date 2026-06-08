<?php

namespace App\Exceptions;

use App\Support\ImportErrorReport;
use Exception;

class ImportFailedException extends Exception
{
    public function __construct(public readonly ImportErrorReport $report)
    {
        parent::__construct($report->summary());
    }
}
