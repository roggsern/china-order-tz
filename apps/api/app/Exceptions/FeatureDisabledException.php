<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class FeatureDisabledException extends HttpException
{
    public function __construct(
        public readonly string $feature,
        string $message = 'This feature is not available.',
    ) {
        parent::__construct(403, $message);
    }

    public static function for(string $feature): self
    {
        return new self(
            $feature,
            sprintf('The %s feature is currently disabled.', str_replace('_', ' ', $feature)),
        );
    }
}
