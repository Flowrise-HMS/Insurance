<?php

namespace Modules\Insurance\Enums;

enum ClaimStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Queued = 'queued';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Partial = 'partial';
    case Rejected = 'rejected';
    case Reconciled = 'reconciled';
}
