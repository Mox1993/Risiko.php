<?php

declare(strict_types=1);

namespace Risiko\Domain;

use RuntimeException;

/**
 * Ein Regelverstoss.
 *
 * Die Nachricht ist fuer den Spieler bestimmt und wird unveraendert angezeigt -
 * also bitte "Zu diesem Gebiet besteht keine Grenze." und nicht "invalid edge".
 */
final class RuleViolation extends RuntimeException
{
}
