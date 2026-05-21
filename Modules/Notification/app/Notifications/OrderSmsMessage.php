<?php

declare(strict_types=1);

namespace Modules\Notification\Notifications;

final class OrderSmsMessage
{
    public static function orderPaid(string $orderNumber): string
    {
        return "Kilora : votre commande {$orderNumber} est confirmée. Merci !";
    }

    public static function shipmentReady(string $confirmationCode): string
    {
        return "Kilora : code livraison {$confirmationCode}.";
    }
}
