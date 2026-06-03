<?php

declare(strict_types=1);

namespace Modules\Notification\Notifications;

final class SellerSmsMessage
{
    public static function newPaidOrder(string $orderNumber): string
    {
        return "Kilora : nouvelle commande {$orderNumber}. Préparez l'expédition.";
    }

    public static function shipmentExitCode(string $orderNumber, string $exitCode): string
    {
        return "Kilora : code sortie {$exitCode} pour la commande {$orderNumber}.";
    }
}
