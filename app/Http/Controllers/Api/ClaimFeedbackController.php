<?php

namespace Modules\Insurance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Services\ClaimReconciliationService;
use Modules\Insurance\Services\Connectors\Nhis\NhisFeedbackParser;

class ClaimFeedbackController extends Controller
{
    public function store(Request $request, ClaimReconciliationService $reconciliations, NhisFeedbackParser $parser)
    {
        $configuredSecret = (string) config('insurance.nhis.feedback_secret', '');
        if ($configuredSecret !== '') {
            $providedSecret = (string) $request->header('X-Insurance-Webhook-Secret', '');
            abort_unless(hash_equals($configuredSecret, $providedSecret), 401, 'Invalid webhook secret.');
        }

        $validated = $request->validate([
            'claim_number' => ['required', 'string', 'max:128'],
            'raw_xml' => ['required', 'string'],
            'claim_submission_id' => ['nullable', 'uuid'],
        ]);

        $claim = InsuranceClaim::query()->where('claim_number', $validated['claim_number'])->firstOrFail();
        $parsed = $parser->parse($validated['raw_xml']);

        $feedback = $reconciliations->applyFeedback($claim, array_merge($parsed, [
            'claim_submission_id' => $validated['claim_submission_id'] ?? null,
            'raw_payload' => $validated['raw_xml'],
            'feedback_type' => 'webhook',
        ]));

        return response()->json([
            'feedback_id' => $feedback->id,
            'decision_status' => $feedback->decision_status,
        ]);
    }
}
