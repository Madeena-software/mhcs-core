<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Modules\Operator\Application\Services\OneStopMcuService;
use App\Modules\Operator\Domain\OperatorException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Throwable;

final class OneStopMcuController extends Controller
{
    public function index(OneStopMcuService $mcu): View
    {
        try {
            return view('operator.one-stop-mcu-worklist', ['entries' => $mcu->worklist()]);
        } catch (OperatorException) {
            abort(403);
        } catch (Throwable) {
            abort(500);
        }
    }

    public function create(string $admission, OneStopMcuService $mcu): View
    {
        try {
            return view('operator.one-stop-mcu-form', $mcu->form($admission));
        } catch (OperatorException) {
            abort(403);
        } catch (Throwable) {
            abort(500);
        }
    }

    public function store(Request $request, string $admission, OneStopMcuService $mcu): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'operation_id' => ['required', 'uuid'],
            'examined_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'systolic_bp' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'diastolic_bp' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'weight_kg' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'height_cm' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'temperature_c' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'glucose_mg_dl' => ['required', 'numeric', 'gte:0', 'max:999999.99'],
            'total_cholesterol_mg_dl' => ['required', 'numeric', 'gte:0', 'max:999999.99'],
            'uric_acid_mg_dl' => ['required', 'numeric', 'gte:0', 'max:999999.99'],
            'fasting_status' => ['required', 'in:fasting,non_fasting'],
            'fasting_duration_hours' => ['nullable', 'required_if:fasting_status,fasting', 'numeric', 'gte:0', 'max:9999.99'],
            'last_meal_at' => ['nullable', 'required_if:fasting_status,non_fasting', 'date_format:H:i'],
            'pef_attempt_i' => ['nullable', 'numeric', 'gt:0', 'max:999999.99'],
            'pef_attempt_ii' => ['nullable', 'numeric', 'gt:0', 'max:999999.99'],
            'pef_attempt_iii' => ['nullable', 'numeric', 'gt:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $mcu->record($admission, $validator->validated());

            return redirect()->route('operator.one-stop-mcu.index')->with('status', __('MCU screening examination saved.'));
        } catch (OperatorException $exception) {
            if ($exception->category === 'mcu_conflict') {
                abort(409);
            }
            if ($exception->category === 'mcu_invalid') {
                return back()->withErrors(['mcu' => __('The MCU examination values are invalid.')])->withInput();
            }
            abort(403);
        } catch (Throwable) {
            return back()->withErrors(['mcu' => __('The MCU examination could not be saved.')])->withInput();
        }
    }

    public function pdf(Request $request, string $admission, OneStopMcuService $mcu): Response
    {
        try {
            $data = $mcu->report($admission);
            $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 12, 'margin_bottom' => 12]);
            $pdf->WriteHTML(view('pdf.one-stop-mcu', $data)->render());

            return response($pdf->Output('', Destination::STRING_RETURN), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="one-stop-mcu-'.$admission.'.pdf"',
                'Cache-Control' => 'private, no-store',
                'Pragma' => 'no-cache',
            ]);
        } catch (OperatorException $exception) {
            if ($exception->category === 'mcu_not_found') {
                abort(404);
            }
            abort(403);
        } catch (Throwable) {
            abort(500);
        }
    }
}
