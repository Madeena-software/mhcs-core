<?php

declare(strict_types=1);

namespace App\Http\Controllers\Radiographer;

use App\Http\Controllers\Controller;
use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class WorkspaceController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer'];

    public function entry(Request $request, string $workspace, RadiographerWorkspace $service)
    {
        try {
            if (! $request->hasValidSignature() || ! ctype_digit((string) $request->query('generation'))) {
                throw new \DomainException;
            }
            if (! $request->user()) {
                $request->session()->put('url.intended', $request->fullUrl());

                return redirect('/login')->withHeaders(self::HEADERS);
            }
            $service->entry($workspace, (int) $request->query('generation'));
            $request->session()->put('radiographer.generations.'.$workspace, (int) $request->query('generation'));

            return redirect('/radiographer/workspaces/'.$workspace)->withHeaders(self::HEADERS);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function show(Request $request, string $workspace, RadiographerWorkspace $service)
    {
        try {
            $generation = $this->sessionGate($request, $workspace, $service);
            $data = $service->read($workspace, $generation);

            $response = response()->view('operator.study', $data + ['workspace' => $workspace, 'study_id' => $workspace, 'workspace_mode' => true, 'rows' => $data['actual']['rows'], 'columns' => $data['actual']['columns'], 'viewer_image_url' => route('radiographer.dicom', $workspace), 'viewer_return_url' => route('radiographer.workspace', $workspace), 'handoff_key' => (string) Str::uuid()])->withHeaders(self::HEADERS);

            return $this->disclosure($response, $service, $workspace, $generation);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function dicom(Request $request, string $workspace, RadiographerWorkspace $service)
    {
        try {
            $generation = $this->sessionGate($request, $workspace, $service);
            $bytes = $service->dicom($workspace, $generation);

            $response = response($bytes, 200, self::HEADERS + ['Content-Type' => 'application/dicom', 'Content-Disposition' => 'inline; filename="study.dcm"']);

            return $this->disclosure($response, $service, $workspace, $generation, $request->isMethod('HEAD') ? 'response-disclosure-authorized' : 'dicom-delivery-authorized');
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function save(Request $request, string $workspace, RadiographerWorkspace $service)
    {
        try {
            $generation = $this->sessionGate($request, $workspace, $service);
            $input = $request->except('_token');
            $result = $service->save($workspace, $input, $generation);

            $response = $request->expectsJson() ? response()->json($result)->withHeaders(self::HEADERS) : redirect('/radiographer/workspaces/'.$workspace)->withHeaders(self::HEADERS);

            return $this->disclosure($response, $service, $workspace, $generation);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function handoff(Request $request, string $workspace, RadiographerWorkspace $service)
    {
        try {
            $generation = $this->sessionGate($request, $workspace, $service);
            $result = $service->handoff($workspace, $request->except('_token'), $generation);

            $response = $request->expectsJson() ? response()->json($result)->withHeaders(self::HEADERS) : redirect('/radiographer/workspaces/'.$workspace)->withHeaders(self::HEADERS);

            return $this->disclosure($response, $service, $workspace, $generation);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    private function sessionGate(Request $request, string $workspace, RadiographerWorkspace $service): int
    {
        $generation = $request->session()->get('radiographer.generations.'.$workspace);
        if (! is_int($generation)) {
            throw new \DomainException;
        }

        return $generation;
    }

    public function manage(Request $request, string $reference, RadiographerWorkspace $service)
    {
        try {
            $result = [];
            if ($request->isMethod('post')) {
                $result = $service->manage($reference, (string) $request->input('action'), $request->except('_token', 'action'));
                if (! $request->expectsJson()) {
                    return redirect()->route('radiographer.manage', $reference, 303)->with('radiographer.issuance', ['reference' => $reference, 'result' => $result])->withHeaders(self::HEADERS);
                }
            } else {
                $issuance = $request->session()->pull('radiographer.issuance');
                $result = ($issuance['reference'] ?? null) === $reference ? $issuance['result'] : [];
            }
            $state = $service->manage($reference, 'read', []);
            if (isset($result['url']) && (($result['generation'] ?? null) !== $state['generation'] || $state['access_status'] !== 'active')) {
                $result = [];
            }
            $response = $request->expectsJson() ? response()->json($result ?: $state)->withHeaders(self::HEADERS) : response()->view('radiographer.manage', compact('reference', 'state', 'result'))->withHeaders(self::HEADERS);

            return $this->managementDisclosure($response, $service, $reference, $state);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    private function disclosure(Response $response, RadiographerWorkspace $service, string $workspace, int $generation, string $action = 'response-disclosure-authorized'): WorkspaceResponse
    {
        return new WorkspaceResponse($response,
            fn () => $service->run($workspace, $action, fn () => null, $generation),
            fn () => $this->unavailable($service));
    }

    private function managementDisclosure(Response $response, RadiographerWorkspace $service, string $reference, array $state): WorkspaceResponse
    {
        return new WorkspaceResponse($response,
            function () use ($service, $reference, $state) {
                if ($service->manage($reference, 'read', []) != $state) {
                    throw new \DomainException;
                }
            },
            fn () => $this->unavailable($service));
    }

    private function unavailable(RadiographerWorkspace $service)
    {
        try {
            $service->denied();
        } catch (Throwable) {
            return response()->view('radiologist.unavailable', [], 503, self::HEADERS);
        }

        return response()->view('radiologist.unavailable', [], 403, self::HEADERS);
    }
}
