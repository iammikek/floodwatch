<?php

namespace App\Enums;

enum ToolName: string
{
    case GetFloodData = 'GetFloodData';
    case GetHighwaysIncidents = 'GetHighwaysIncidents';
    case GetFloodForecast = 'GetFloodForecast';
    case GetRiverLevels = 'GetRiverLevels';
    case GetCorrelationSummary = 'GetCorrelationSummary';
}
