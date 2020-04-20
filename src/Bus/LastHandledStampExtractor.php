<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Bus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class LastHandledStampExtractor implements MessageResultExtractor
{
    public function extract(Envelope $message)
    {
        $stamp = $message->last(HandledStamp::class);

        if (null === $stamp) {
            return null;
        }

        return $stamp->getResult();
    }
}
