<?php

namespace App\Support;

use Hashids\Hashids;
use Hashids\Math\BCMath;
use Hashids\Math\MathInterface;

/**
 * Hashids pinned to BcMath (specs/14): the GMP extension in some builds
 * crashes the PHP process (SIGILL) on adversarial hash input. BcMath
 * decodes the same values safely. Salt/min-length enforced by callers.
 */
class SafeHashids extends Hashids
{
    protected function getMathExtension(): MathInterface
    {
        return new BCMath();
    }
}
