<?php

namespace App\Support;

use RuntimeException;

/**
 * ViaCEP could not answer (timeout, connection error, 5xx or an unexpected body).
 */
final class CepServiceUnavailable extends RuntimeException
{
}
