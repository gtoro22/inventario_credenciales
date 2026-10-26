<?php

namespace App\Enums;

/** Slugs de los niveles de criticidad sembrados en `criticality_levels`. */
enum Criticality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
