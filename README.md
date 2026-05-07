# Insurance Module

Multi-payer insurance orchestration for Flowrise HMS.

## Supported architecture
- Payer-agnostic core (`insurance_payers`, `insurance_claims`, policies, tariffs)
- NHIS XML-only connector workflow
- Private-insurer connector extension point
- Seamless integration with:
  - Patient module (`insurancePolicies` relation)
  - Billing module (`insurance_expected_amount`, reconciliation updates)

## Key endpoints
- `POST /api/v1/insurance/catalog/sync`
- `POST /api/v1/insurance/claims/submit`
- `POST /api/v1/insurance/claims/feedback`

## Reliability model
- Claim submission queued via `SubmitInsuranceClaimJob`
- Feedback polling/reconciliation via `PollInsuranceClaimFeedbackJob`
- Idempotent feedback persistence with `feedback_hash`

## NHIS standard
- NHIS submissions are XML-only.
- Claims are encoded by `NhisXmlEncoder`.
- Feedback XML is parsed by `NhisFeedbackParser`.

