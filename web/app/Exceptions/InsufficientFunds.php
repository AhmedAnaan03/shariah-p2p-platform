<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A transfer would take a user or escrow wallet below zero (PB-18). The ledger refuses it before
 * writing anything; the database CHECK on balance_after is the second line of defence.
 */
class InsufficientFunds extends RuntimeException {}
