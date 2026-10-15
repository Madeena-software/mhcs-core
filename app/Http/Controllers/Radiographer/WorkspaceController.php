<?php

declare(strict_types=1);

namespace App\Http\Controllers\Radiographer;

use App\Http\Controllers\Controller;
use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

            return response()->view('operator.study', $data + ['workspace' => $workspace, 'study_id' => $workspace, 'workspace_mode' => true, 'rows' => $data['actual']['rows'], 'columns' => $data['actual']['columns'], 'viewer_image_url' => route('radiographer.dicom', $workspace), 'viewer_return_url' => route('radiographer.workspace', $workspace), 'handoff_key' => (string) Str::uuid()])->withHeaders(self::HEADERS);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function dicom(Request $request, string $workspace, RadiographerWorkspace $service)
    {
        try {
            $generation = $this->sessionGate($request, $workspace, $service);
            $bytes = $service->dicom($workspace, $generation);

            return response($bytes, 200, self::HEADERS + ['Content-Type' => 'application/dicom', 'Content-Disposition' => 'inline; filename="study.dcm"']);
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

            return $request->expectsJson() ? response()->json($result)->withHeaders(self::HEADERS) : redirect('/radiographer/workspaces/'.$workspace)->withHeaders(self::HEADERS);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    public function handoff(Request $request, string $workspace, RadiographerWorkspace $service)
    {
        try {
            $generation = $this->sessionGate($request, $workspace, $service);
            $result = $service->handoff($workspace, $request->except('_token'), $generation);

            return $request->expectsJson() ? response()->json($result)->withHeaders(self::HEADERS) : redirect('/radiographer/workspaces/'.$workspace)->withHeaders(self::HEADERS);
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
            if ($request->isMethod('get')) {
                return response()->view('radiographer.manage', ['reference' => $reference, 'state' => $service->manage($reference, 'read', [])])->withHeaders(self::HEADERS);
            }
            $input = $request->except('_token', 'action');
            $result = $service->manage($reference, (string) $request->input('action'), $input);

            return $request->expectsJson() ? response()->json($result)->withHeaders(self::HEADERS) : response()->view('radiographer.manage', ['reference' => $reference, 'state' => $service->manage($reference, 'read', []), 'result' => $result])->withHeaders(self::HEADERS);
        } catch (Throwable) {
            return $this->unavailable($service);
        }
    }

    private function unavailable(RadiographerWorkspace $service)
    {
        try {
            $service->denied();
        } catch (Throwable) {
            return response('Ruang kerja tidak tersedia.', 503, self::HEADERS);
        }

        return response('Ruang kerja tidak tersedia. Silakan masuk melalui tautan penugasan yang berlaku.', 403, self::HEADERS);
    }
}
