<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when data would break a financing-contract rule that the database can't check
 * itself, e.g. a predetermined return on a Mudarabah instalment.
 */
class ContractRuleViolation extends DomainException {}
