<?php

declare(strict_types=1);

namespace App\Http\Controllers\Radiographer;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class WorkspaceResponse extends Response
{
    private bool $sending = false;

    private bool $head = false;

    private bool $rejected = false;

    public function __construct(Response $response, private Closure $authorize, private Closure $unavailable)
    {
        parent::__construct($response->getContent(), $response->getStatusCode());
        $this->headers = clone $response->headers;
    }

    private function release(): void
    {
        if ($this->rejected) {
            return;
        }
        try {
            ($this->authorize)();
        } catch (Throwable) {
            $this->rejected = true;
            $denied = ($this->unavailable)();
            $this->setContent($this->head ? '' : $denied->getContent());
            $this->setStatusCode($denied->getStatusCode());
            $this->headers = clone $denied->headers;
        }
    }

    public function prepare(Request $request): static
    {
        $this->head = $request->isMethod('HEAD');

        return parent::prepare($request);
    }

    public function send(bool $flush = true): static
    {
        // Commit the final authorization/audit before headers; never lock across network I/O.
        $this->release();
        $this->sending = true;
        try {
            return parent::send($flush);
        } finally {
            $this->sending = false;
        }
    }

    public function sendContent(): static
    {
        if (! $this->sending) {
            $this->release();
        }

        return parent::sendContent();
    }
}
