<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A pledge can't go ahead, decided under the campaign's lock (PB-19): the campaign closed or
 * filled up since the page was loaded, the amount is below the minimum or above what is left,
 * or the investor may no longer pledge. The message is shown to the investor next to the amount.
 */
class PledgeRefused extends RuntimeException {}
