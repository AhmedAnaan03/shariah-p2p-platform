<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Mudarabah business report can't be submitted or decided (PB-29): wrong period, already
 * decided, more capital than is invested, or a final report that doesn't settle the capital.
 * The message says why, in words that never describe the capital as a debt.
 */
class ReportRefused extends RuntimeException {}
