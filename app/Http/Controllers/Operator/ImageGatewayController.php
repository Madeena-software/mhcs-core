<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Modules\ImageGateway\Application\Services\SyntheticCaptureGatewayService;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Domain\OperatorException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ImageGatewayController extends Controller
{
    public function captureShow(
        string $admission,
        OperatorAuthorization $authorization,
        SyntheticCaptureGatewayService $gateway,
    ): View {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $form = $gateway->captureForm(
                $authorization->current(SyntheticCaptureGatewayService::CAPTURE_PURPOSE),
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
        SyntheticCaptureGatewayService $gateway,
    ): RedirectResponse {
        $validator = Validator::make($request->all(), [
            'submission_id' => ['required', 'uuid'],
            'radiographs' => ['required', 'array', 'size:1'],
            'radiographs.0' => ['required', 'file'],
            'gain' => ['required', 'file'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            /** @var list<\Illuminate\Http\UploadedFile> $radiographs */
            $radiographs = $request->file('radiographs');
            /** @var \Illuminate\Http\UploadedFile $gain */
            $gain = $request->file('gain');
            $result = $gateway->submit(
                $authorization->current(SyntheticCaptureGatewayService::CAPTURE_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $admission,
                (string) $validator->validated()['submission_id'],
                $radiographs,
                $gain,
            );

            return redirect()->route('operator.study.show', $result['study_id'])->with('status', 'Synthetic capture accepted.');
        } catch (OperatorException|ImageGatewayException $exception) {
            if ($exception instanceof ImageGatewayException && $exception->category === 'environment_forbidden') {
                abort(403);
            }

            return back()->withErrors(['capture' => $exception->getMessage()])->withInput();
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }

            return back()->withErrors(['capture' => 'The synthetic capture could not be accepted.'])->withInput();
        }
    }

    public function study(
        string $study,
        OperatorAuthorization $authorization,
        SyntheticCaptureGatewayService $gateway,
    ): View {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $metadata = $gateway->study(
                $authorization->current(SyntheticCaptureGatewayService::STUDY_PURPOSE),
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

    public function dicom(
        string $study,
        OperatorAuthorization $authorization,
        SyntheticCaptureGatewayService $gateway,
    ): Response {
        return $this->dicomResponse($study, $authorization, $gateway, 'inline');
    }

    public function download(
        string $study,
        OperatorAuthorization $authorization,
        SyntheticCaptureGatewayService $gateway,
    ): Response {
        return $this->dicomResponse($study, $authorization, $gateway, 'attachment');
    }

    private function dicomResponse(
        string $study,
        OperatorAuthorization $authorization,
        SyntheticCaptureGatewayService $gateway,
        string $disposition,
    ): Response {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);
            $bytes = $gateway->dicom(
                $authorization->current(SyntheticCaptureGatewayService::STUDY_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $study,
            );

            return response($bytes, 200, [
                'Content-Type' => 'application/dicom',
                'Content-Disposition' => $disposition.'; filename="synthetic-study.dcm"',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (OperatorException|ImageGatewayException) {
            abort(403);
        }
    }
}
