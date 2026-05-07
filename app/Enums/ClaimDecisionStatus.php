<?php

namespace Modules\Insurance\Enums;

enum ClaimDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Partial = 'partial';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
}
