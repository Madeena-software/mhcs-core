<?php

declare(strict_types=1);

namespace App\Http\Controllers\Radiologist;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Radiographer\WorkspaceResponse;
use App\Modules\Doctor\Application\Services\RadiologistWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class WorkspaceController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer'];

    public function entry(Request $request, string $workspace, RadiologistWorkspace $service)
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

            return redirect('/radiologist/workspaces/'.$workspace)->withHeaders(self::HEADERS);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    private function generation(Request $request, string $workspace): int
    {
        $g = $request->session()->get('radiologist.bindings.'.$workspace.'.generation');
        if (! is_int($g)) {
            throw new \DomainException;
        }

        return $g;
    }

    public function show(Request $request, string $workspace, RadiologistWorkspace $service)
    {
        try {
            $g = $this->generation($request, $workspace);
            $data = $service->read($workspace, $g);
            $response = response()->view('radiologist.workspace', $data + ['workspace' => $workspace, 'operation_key' => (string) Str::uuid()])->withHeaders(self::HEADERS);

            return $this->disclosure($response, $service, $workspace, $g);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function viewer(Request $request, string $workspace, RadiologistWorkspace $service)
    {
        try {
            $g = $this->generation($request, $workspace);
            $data = $service->read($workspace, $g);
            $response = response()->view('operator.study', $data + ['workspace_mode' => true, 'clinical_mode' => true, 'study_id' => $workspace, 'rows' => $data['actual']['rows'], 'columns' => $data['actual']['columns'], 'viewer_image_url' => route('radiologist.dicom', $workspace), 'viewer_return_url' => route('radiologist.workspace', $workspace)])->withHeaders(self::HEADERS);

            return $this->disclosure($response, $service, $workspace, $g);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function dicom(Request $request, string $workspace, RadiologistWorkspace $service)
    {
        try {
            $g = $this->generation($request, $workspace);
            $response = response($service->dicom($workspace, $g), 200, self::HEADERS + ['Content-Type' => 'application/dicom', 'Content-Disposition' => 'inline; filename="study.dcm"']);

            return $this->disclosure($response, $service, $workspace, $g, $request->isMethod('HEAD') ? 'disclosure-authorized' : 'dicom-delivery-authorized');
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function command(Request $request, string $workspace, string $action, RadiologistWorkspace $service)
    {
        try {
            $g = $this->generation($request, $workspace);
            $result = $service->command($workspace, $action, $request->except('_token'), $g);
            $response = $request->expectsJson() ? response()->json($result)->withHeaders(self::HEADERS) : redirect()->route('radiologist.workspace', $workspace)->withHeaders(self::HEADERS);

            return $this->disclosure($response, $service, $workspace, $g);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function study(Request $request, string $reference, RadiologistWorkspace $service)
    {
        try {
            return redirect()->route('radiologist.manage', $service->managementHandoff($reference))->withHeaders(self::HEADERS);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function manage(Request $request, string $handoff, RadiologistWorkspace $service)
    {
        try {
            $result = [];
            if (! $request->isMethod('get')) {
                $action = (string) $request->input('action');
                $input = $request->except('_token', 'action');
                $result = $action === 'credential' ? $service->verifyEligibility($handoff, $input) : $service->manage($handoff, $action, $input);
            }
            $state = $service->manage($handoff, 'read', []);
            $response = $request->expectsJson() ? response()->json($result ?: $state)->withHeaders(self::HEADERS) : response()->view('radiologist.manage', compact('state', 'result', 'handoff'))->withHeaders(self::HEADERS);

            return new WorkspaceResponse($response, fn () => $service->manage($handoff, 'read', []), fn () => $this->unavailable($service));
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    private function disclosure(Response $response, RadiologistWorkspace $service, string $workspace, int $generation, string $action = 'disclosure-authorized'): WorkspaceResponse
    {
        return new WorkspaceResponse($response, fn () => $service->run($workspace, $action, fn () => null, $generation), fn () => $this->unavailable($service));
    }

    private function unavailable(RadiologistWorkspace $service)
    {
        try {
            $service->denied();
        } catch (Throwable) {
            return response()->view('radiologist.unavailable', [], 503, self::HEADERS);
        }

        return response()->view('radiologist.unavailable', [], 403, self::HEADERS);
    }
}
