<?php

namespace App\Enums;

enum JourneyStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
}
