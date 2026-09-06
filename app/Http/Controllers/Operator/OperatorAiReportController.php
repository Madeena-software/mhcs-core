<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Modules\ImageGateway\Application\Contracts\ImageGatewayAiServiceContract;
use App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperatorAiReportController extends Controller
{
    public function download(
        string $study,
        Request $request,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
        ImageGatewayAiServiceContract $aiService,
        PrivateObjectStore $objects,
        AuditStore $audit,
        Clock $clock,
    ): StreamedResponse {
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
        } catch (OperatorException|ImageGatewayException) {
            abort(403, 'Cross-site access denied.');
        }

        $context = $authorization->current(ImageGatewayAiServiceContract::AI_REPORT_PURPOSE);
        $grant = $aiService->getReportAccess($study, $context, 'derived');

        if ($grant === null) {
            abort(404, 'The AI report is unavailable or not ready.');
        }

        $stream = $objects->getStream($grant, $context, 'image-gateway-ai', ImageGatewayAiServiceContract::AI_REPORT_PURPOSE);

        $now = $clock->now();
        $displayReference = (string) ($metadata['display_reference'] ?? $study);

        $audit->append(AuditEvent::fromContext(
            context: $context,
            action: 'operator.ai-report.download',
            source: 'operator-portal',
            outcome: 'success',
            occurredAt: $now,
            targetType: 'image-gateway.ai-report',
            targetId: $study,
            metadata: [
                'operator_id' => (string) $portal['profile']->getKey(),
                'study_id' => $study,
                'reference' => $displayReference,
                'client_ip' => (string) ($request->ip() ?? '127.0.0.1'),
                'format' => 'pdf',
            ],
        ));

        return response()->stream(
            function () use ($stream): void {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan-ai-'.$displayReference.'.pdf"',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function retry(
        string $study,
        OperatorAuthorization $authorization,
        ImageGatewayCaptureService $gateway,
        ImageGatewayAiServiceContract $aiService,
    ): RedirectResponse {
        try {
            $portal = $authorization->portal();
            $site = $authorization->portalSite($portal);

            $gateway->study(
                $authorization->current(ImageGatewayCaptureService::STUDY_PURPOSE),
                (string) $portal['profile']->getKey(),
                (string) $site->getKey(),
                (string) $site->operator_site_id,
                $study,
            );

            $context = $authorization->current(ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE);
            $aiService->retryStudy($study, $context);

            return redirect()->route('operator.study.results')
                ->with('status', __('Permintaan analisis AI telah diantrikan ulang.'));
        } catch (OperatorException|ImageGatewayException $exception) {
            return redirect()->route('operator.study.results')
                ->withErrors(['ai_report' => __($exception->getMessage())]);
        }
    }
}
