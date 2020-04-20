<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Bus;

use Symfony\Component\Messenger\Envelope;

interface MessageResultExtractor
{
    public function extract(Envelope $message);
}
