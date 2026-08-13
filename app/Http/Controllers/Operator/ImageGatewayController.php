<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Domain\OperatorException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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
        $validator = Validator::make($request->all(), [
            'submission_id' => ['required', 'uuid'],
            'radiograph_npz' => [in_array('radiograph', $form['missing'], true) ? 'required' : 'nullable', 'file'],
            'gain_npz' => [in_array('gain', $form['missing'], true) ? 'required' : 'nullable', 'file'],
        ]);
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
    ): View {
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
        } catch (OperatorException|ImageGatewayException) {
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

    private function dicomResponse(
        string $study,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
        string $disposition,
    ): Response {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $bytes = $gateway->dicom(
                $authorization->current(ImageGatewayCaptureService::STUDY_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $study,
            );

            return response($bytes, 200, [
                'Content-Type' => 'application/dicom',
                'Content-Disposition' => $disposition.'; filename="capture-'.$study.'.dcm"',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (OperatorException|ImageGatewayException) {
            abort(403);
        }
    }
}
