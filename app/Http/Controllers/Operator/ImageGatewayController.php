<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Domain\OperatorException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use ZipArchive;

final class ImageGatewayController extends Controller
{
    public function captureShow(
        string $admission,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
    ): View {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $form = $gateway->captureForm(
                $authorization->current(ImageGatewayCaptureService::CAPTURE_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $admission,
            );

            return view('operator.xray-capture', ['admissionId' => $admission, 'form' => $form]);
        } catch (OperatorException|ImageGatewayException) {
            abort(403);
        }
    }

    public function captureStatus(
        string $admission,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
    ): JsonResponse {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $status = $gateway->captureStatus(
                $authorization->current(ImageGatewayCaptureService::CAPTURE_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $admission,
            );

            return response()->json($status + ['ready_results_url' => route('operator.study.results')]);
        } catch (OperatorException|ImageGatewayException) {
            abort(403);
        }
    }

    public function captureStore(
        Request $request,
        string $admission,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
    ): Response|RedirectResponse {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $form = $gateway->captureForm(
                $authorization->current(ImageGatewayCaptureService::CAPTURE_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $admission,
            );
        } catch (OperatorException|ImageGatewayException) {
            abort(403);
        }
        $input = $request->all();
        if ($form['metadata_editable']
            && is_array($input['metadata'] ?? null)
            && is_array($input['metadata']['examination'] ?? null)
            && is_string($input['metadata']['examination']['study_description'] ?? null)) {
            $input['metadata']['examination']['study_description'] = trim($input['metadata']['examination']['study_description']);
        }
        $rules = [
            'submission_id' => ['required', 'uuid'],
            'radiograph_npz' => [in_array('radiograph', $form['missing'], true) ? 'required' : 'nullable', 'file'],
            'gain_npz' => [in_array('gain', $form['missing'], true) ? 'required' : 'nullable', 'file'],
        ];
        if ($form['metadata_editable']) {
            $rules += ImageGatewayCaptureService::metadataRules();
        }
        $validator = Validator::make($input, $rules, ImageGatewayCaptureService::metadataMessages());
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            /** @var UploadedFile $radiograph */
            $radiograph = $request->file('radiograph_npz');
            /** @var UploadedFile $gain */
            $gain = $request->file('gain_npz');
            $result = $gateway->submit(
                $authorization->current(ImageGatewayCaptureService::CAPTURE_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $admission,
                (string) $validator->validated()['submission_id'],
                $form['metadata_editable'] ? ($validator->validated()['metadata'] ?? null) : null,
                $radiograph,
                $gain,
            );

            if ($request->expectsJson()) {
                return response()->json($result);
            }

            return redirect()->route('operator.study.results')->with('status', __('Capture accepted and queued for processing.'));
        } catch (OperatorException|ImageGatewayException $exception) {
            if ($exception instanceof ImageGatewayException && $exception->category === 'environment_forbidden') {
                abort(403);
            }

            return back()->withErrors(['capture' => __($exception->getMessage())])->withInput();
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }

            return back()->withErrors(['capture' => __('The capture could not be accepted.')])->withInput();
        }
    }

    public function study(
        string $study,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
    ): View {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $metadata = $gateway->study(
                $authorization->current(ImageGatewayCaptureService::STUDY_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $study,
            );

            return view('operator.study', $metadata);
        } catch (OperatorException|ImageGatewayException) {
            abort(403);
        }
    }

    public function results(
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
    ): View|RedirectResponse {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $studies = $gateway->studies(
                $authorization->current(ImageGatewayCaptureService::STUDY_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
            );

            return view('operator.study-results', ['studies' => $studies]);
        } catch (OperatorException|ImageGatewayException $exception) {
            if ($exception instanceof OperatorException && $exception->category === 'active_site_required') {
                return redirect()->route('operator.site')->withErrors(['site' => __($exception->getMessage())]);
            }

            abort(403);
        }
    }

    public function dicom(
        string $study,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
    ): Response {
        return $this->dicomResponse($study, $authorization, $gateway, 'inline');
    }

    public function download(
        string $study,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
    ): Response {
        return $this->dicomResponse($study, $authorization, $gateway, 'attachment');
    }

    public function batchDownload(
        Request $request,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
    ): Response|BinaryFileResponse {
        $validated = Validator::make($request->all(), [
            'studies' => ['required', 'array', 'min:1'],
            'studies.*' => ['required', 'string', 'uuid'],
        ])->validate();

        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $entries = $gateway->batch(
                $authorization->current(ImageGatewayCaptureService::STUDY_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $validated['studies'],
            );
            $path = tempnam(sys_get_temp_dir(), 'mhcs-dicom-');
            if ($path === false) {
                throw new ImageGatewayException('study_unavailable', 'The DICOM studies are unavailable.');
            }
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                @unlink($path);
                throw new ImageGatewayException('study_unavailable', 'The DICOM studies are unavailable.');
            }
            try {
                foreach ($entries as $entry) {
                    if ($zip->addFromString($entry['name'], $entry['bytes']) === false) {
                        throw new ImageGatewayException('study_unavailable', 'The DICOM studies are unavailable.');
                    }
                }
                if ($zip->close() === false) {
                    throw new ImageGatewayException('study_unavailable', 'The DICOM studies are unavailable.');
                }
                $response = response()->download($path, 'dicom-studies.zip', [
                    'Content-Type' => 'application/zip',
                    'Cache-Control' => 'no-store, private',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
                $response->deleteFileAfterSend(true);
                $keepFile = true;

                return $response;
            } finally {
                if (! ($keepFile ?? false)) {
                    @unlink($path);
                }
            }
        } catch (OperatorException|ImageGatewayException) {
            abort(403);
        }
    }

    private function dicomResponse(
        string $study,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
        string $disposition,
    ): Response {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $metadata = $gateway->study(
                $authorization->current(ImageGatewayCaptureService::STUDY_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $study,
            );
            $bytes = $gateway->dicom(
                $authorization->current(ImageGatewayCaptureService::STUDY_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $study,
            );

            return response($bytes, 200, [
                'Content-Type' => 'application/dicom',
                'Content-Disposition' => $disposition.'; filename="'.$metadata['filename'].'"',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (OperatorException|ImageGatewayException) {
            abort(403);
        }
    }
}
