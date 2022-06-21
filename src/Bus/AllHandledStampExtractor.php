<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Bus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class AllHandledStampExtractor implements MessageResultExtractor
{
    public function extract(Envelope $message)
    {
        $results = [];

        foreach ($message->all() as $key => $stamp) {
            if (HandledStamp::class !== $key) {
                continue;
            }

            foreach ($stamp as $resultStamp) {
                \assert($resultStamp instanceof HandledStamp);

                $results[] = $resultStamp->getResult();
            }
        }

        return $results;
    }
}
