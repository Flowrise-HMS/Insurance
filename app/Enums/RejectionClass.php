<?php

namespace Modules\Insurance\Enums;

enum RejectionClass: string
{
    case TransportRejected = 'transport_rejected';
    case SchemaRejected = 'schema_rejected';
    case BusinessRejected = 'business_rejected';
    case PayerRejected = 'payer_rejected';
}
