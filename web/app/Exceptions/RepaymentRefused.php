<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A repayment can't go ahead, decided under the campaign's lock (PB-35): the instalment is
 * already paid (a second click, or a page left open), it isn't the next one, the payoff amount
 * changed since it was shown, or the campaign isn't repaying. Nothing has been charged.
 */
class RepaymentRefused extends RuntimeException {}
