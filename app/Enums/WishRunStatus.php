<?php

namespace App\Enums;

enum WishRunStatus: string
{
    // Berechnet, vom Organisator noch nicht freigegeben und änderbar.
    case Proposed = 'proposed';
    // Freigegeben: Buchungen sind angelegt, Benachrichtigungen versendet.
    case Released = 'released';
    // Durch eine Neuberechnung ersetzt.
    case Superseded = 'superseded';
}
