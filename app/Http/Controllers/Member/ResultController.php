<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Radiographer\WorkspaceResponse;
use App\Modules\Member\Application\Contracts\ResultPublication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

final class ResultController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'"];

    private function generation(Request $request): int
    {
        if (! $request->hasValidSignature() || ! ctype_digit((string) $request->query('generation')) || array_diff(array_keys($request->query()), ['generation', 'expires', 'signature'])) {
            throw new \DomainException('result_unavailable');
        }

        return (int) $request->query('generation');
    }

    public function entry(Request $request, string $publication, ResultPublication $service)
    {
        try {
            $g = $this->generation($request);
            if (! $request->user()) {
                $request->session()->put('url.intended', $request->fullUrl());

                return redirect('/login')->withHeaders(self::HEADERS);
            }
            $data = $service->read($publication, $g, true);
            $response = response()->view('member.results.report', $data, 200, self::HEADERS);

            return new WorkspaceResponse($response, function () use ($request, $publication, $g, $service) {
                $this->generation($request);
                // Reload persisted session state after middleware saves it, before disclosure.
                $fresh = clone $request->session();
                $fresh->flush();
                $fresh->start();
                if ($fresh->get(Auth::guard('web')->getName()) !== Auth::id()
                    || $fresh->get('member.result.credential') !== $request->session()->get('member.result.credential')) {
                    throw new \DomainException('result_unavailable');
                }
                $service->read($publication, $g);
            }, fn () => $this->unavailable($service));
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function manage(Request $request, string $case, ResultPublication $service)
    {
        try {
            if (! $request->user()) {
                throw new \DomainException('result_unavailable');
            }
            $result = $request->isMethod('POST') ? $service->manage($case, (string) $request->input('action'), $request->except('_token', 'action')) : [];
            if ($request->isMethod('POST') && $request->input('action') !== 'publish') {
                $state = $service->manage($case, 'status');
            } else {
                try {
                    $state = $service->manage($case, 'read');
                } catch (Throwable) {
                    $state = $service->manage($case, 'status');
                }
            }
            $key = (string) Str::uuid();
            $response = $request->expectsJson() ? response()->json($result ?: $state, 200, self::HEADERS) : response()->view('member.results.manage', compact('case', 'state', 'result', 'key'), 200, self::HEADERS);

            return new WorkspaceResponse($response, fn () => $service->manage($case, $state['report'] === null ? 'status' : 'read'), fn () => $this->unavailable($service));
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    private function unavailable(ResultPublication $service)
    {
        $status = 403;
        try {
            $service->denied();
        } catch (Throwable) {
            $status = 503;
        }

        return response()->view('member.results.unavailable', [], $status, self::HEADERS);
    }
}
